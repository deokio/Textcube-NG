<?php
/* Frypan Anti-spam Service adapter for Textcube
   ---------------------------------------------
   Version 2.1
   Tatter Network Foundation development team / Needlworks.

   Creator          : Gendoh
   Maintainer       : inureyes

   Created at       : 2006.6.8
   Last modified at : 2015.2.25

 General Public License
 http://www.gnu.org/licenses/gpl.html

 This program is free software; you can redistribute it and/or modify
 it under the terms of the GNU General Public License as published by
 the Free Software Foundation; either version 2 of the License, or
 (at your option) any later version.

*/
function FAS_Call($type, $name, $title, $url, $content)
{
	$context = Model_Context::getInstance();
	$pool = DBModel::getInstance();

	$blogstr = $context->getProperty('uri.host').$context->getProperty('uri.blog');

	$DDosTimeWindowSize = 300;

	// 플러그인 설정에서 프로토콜·도메인·인증키 읽기. 미설정 시 원래 기본값 사용.
	// getCurrentSetting() 은 플러그인 활성 상태에서만 유효하므로 별도 예외 처리 불필요.
	$pluginConfig = Setting::fetchConfigVal(getCurrentSetting('EAS')) ?: array();
	$fasScheme   = !empty($pluginConfig['fas_scheme'])   ? trim($pluginConfig['fas_scheme'])   : 'http';
	if (!in_array($fasScheme, array('http', 'https'))) $fasScheme = 'http';
	$fasDomain   = !empty($pluginConfig['fas_domain'])   ? trim($pluginConfig['fas_domain'])   : 'antispam.textcube.org';
	$fasRpcPath  = !empty($pluginConfig['fas_rpcpath'])  ? trim($pluginConfig['fas_rpcpath'])  : '/RPC/';
	if ($fasRpcPath[0] !== '/') $fasRpcPath = '/' . $fasRpcPath;
	$fasAuthKey  = !empty($pluginConfig['fas_authkey'])  ? trim($pluginConfig['fas_authkey'])  : '';

	$rpc = new XMLRPC();
	// Textcube-NG: FAS 서버 주소는 블로그 관리자가 정하는 값(fas_domain 은 host:port 도 받음)이라 댓글이 올 때마다 서버 안쪽 주소로
	// 댓글 내용·작성자 IP·인증키를 POST 할 수 있었다. 공인 주소·80/443 만, 실패하면 아래 로컬 검사(v3.156)
	$rpc->publicOnly = true;
	$rpc->url = $fasScheme . '://' . rtrim($fasDomain, '/') . $fasRpcPath;

	// 인증키 미설정: 기존 FAS 호환 7-param 모드.
	// 인증키 설정: 커스텀 엔진 8-param 모드 (authKey 를 8번째 파라미터로 추가).
	$callResult = !empty($fasAuthKey)
		? $rpc->call('checkSpam', $blogstr, $type, $name, $title, $url, $content, $_SERVER['REMOTE_ADDR'], $fasAuthKey)
		: $rpc->call('checkSpam', $blogstr, $type, $name, $title, $url, $content, $_SERVER['REMOTE_ADDR']);

	if ($callResult == false)
	{
		// call fail
		// Do Local spam check with "Thief-cat algorithm"
		$count = 0;

		if ($type == 2) // Trackback Case
		{
			$storage = "RemoteResponses";
			$pool->reset($storage);

			$pool->setQualifier("url","eq",$url,true);
			$pool->setQualifier("isfiltered",">",0);

			if ($cnt = $pool->getCount("id")) {
				$count += $cnt;
			}

		} else { // Comment Case
			$storage = "Comments";
			$pool->reset($storage);
			$pool->setQualifier("comment","eq",$content,true);	// Textcube-NG: 가변 변수 $$content(오타) 대신 댓글 본문 $content 로 찾는다(r3-3)
			$pool->setQualifier("name","eq",$name,true);
			$pool->setQualifier("homepage","eq",$url,true);
			$pool->setQualifier("isfiltered",">",0);

			if ($cnt = $pool->getCount("id")) {
				$count += $cnt;
			}
		}

		// Check IP
		$pool->reset($storage);
		$pool->setQualifier("ip","eq",$_SERVER['REMOTE_ADDR'],true);
		$pool->setQualifier("written",">",Timestamp::getUNIXtime()-$DDosTimeWindowSize);

		if ($cnt = $pool->getCount("id")) {
			$count += $cnt;
		}

		if ($count >= 10) {
			return false;
		}

		return true;
	}

	if (!is_null($rpc->fault)) {
		// FAS has some problem
		return true;
	}

	if (!is_array($rpc->result)) {
		// FAS returned a non-struct result (PHP 8: string offset access throws TypeError)
		return true;
	}

	if (isset($rpc->result['result']) && $rpc->result['result'] == true) {
		return false; // it's spam
	}

	return true;
}

function FAS_AddingTrackback($target, $mother)
{
	return $target && FAS_Call(2, $mother['site'], $mother['title'], $mother['url'], $mother['excerpt']);
}

function FAS_AddingComment($target, $mother)
{
	if ($mother['secret'] ==  true) // it's secret(only owner can see it)
	{
		// Don't touch
		return $target;
	}

	$type = 1; // comment
	if ($mother['entry'] == 0) $type = 3; // guestbook

	return $target && FAS_Call($type, $mother['name'], '', $mother['homepage'], $mother['comment']);
}

// 플러그인 설정 탭용 manifestHandler.
function EAS_ConfigHandler($plugin)
{
	$context = Model_Context::getInstance();
	// 현재 블로그 식별자 — 모든 checkSpam 호출에 포함되는 값.
	$blogstr = htmlspecialchars(
		$context->getProperty('uri.host') . $context->getProperty('uri.blog'),
		ENT_QUOTES, 'UTF-8'
	);
	return '<?xml version="1.0" encoding="utf-8"?>'
		. '<config>'

		. '<fieldset legend="' . htmlspecialchars(_t('FAS 서버 설정'), ENT_QUOTES, 'UTF-8') . '">'
		. '<field type="select" name="fas_scheme" title="' . htmlspecialchars(_t('프로토콜'), ENT_QUOTES, 'UTF-8') . '">'
		. '<op value="http" checked="checked">HTTP</op>'
		. '<op value="https">HTTPS</op>'
		. '<caption>' . htmlspecialchars(_t('HTTPS 선택 시 서버에 유효한 SSL 인증서가 필요하며 PHP OpenSSL 확장이 활성화되어 있어야 합니다.'), ENT_QUOTES, 'UTF-8') . '</caption>'
		. '</field>'
		. '<field type="text" name="fas_domain" title="' . htmlspecialchars(_t('서버 도메인'), ENT_QUOTES, 'UTF-8') . '" value="antispam.textcube.org" size="50">'
		. '<caption>' . htmlspecialchars(_t('antispam.textcube.org 는 현재 서비스 중단 상태입니다.'), ENT_QUOTES, 'UTF-8') . ' '
		. htmlspecialchars(_t('자체 FAS 호환 서버를 운영하는 경우 해당 도메인(호스트명)만 입력하세요.'), ENT_QUOTES, 'UTF-8') . ' '
		. htmlspecialchars(_t('사설망·내부 주소와 80/443 외 포트는 쓸 수 없습니다.'), ENT_QUOTES, 'UTF-8') . ' '	// Textcube-NG: 공인 주소 제한 안내(v3.156)
		. htmlspecialchars(_t('현재 블로그 식별자'), ENT_QUOTES, 'UTF-8') . ': ' . $blogstr . '</caption>'
		. '</field>'
		. '<field type="text" name="fas_rpcpath" title="' . htmlspecialchars(_t('RPC 경로'), ENT_QUOTES, 'UTF-8') . '" value="/RPC/" size="50">'
		. '<caption>' . htmlspecialchars(_t('XML-RPC 엔드포인트 경로. 기본값: /RPC/ — 엔드포인트 전체 URL: {프로토콜}://{도메인}{경로}'), ENT_QUOTES, 'UTF-8') . '</caption>'
		. '</field>'
		. '<field type="text" name="fas_authkey" title="' . htmlspecialchars(_t('인증키 (선택)'), ENT_QUOTES, 'UTF-8') . '" value="" size="50">'
		. '<caption>' . htmlspecialchars(_t('커스텀 스팸 엔진 서버에서 요구하는 인증키.'), ENT_QUOTES, 'UTF-8') . ' '
		. htmlspecialchars(_t('입력 시 checkSpam 8번째 파라미터로 전달 (커스텀 8-param 모드).'), ENT_QUOTES, 'UTF-8') . ' '
		. htmlspecialchars(_t('미입력 시 기존 FAS 호환 7-param 모드로 동작.'), ENT_QUOTES, 'UTF-8') . '</caption>'
		. '</field>'
		. '</fieldset>'

		. '</config>';
}
?>
