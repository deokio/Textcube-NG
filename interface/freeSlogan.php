<?php
/// Copyright (c) 2004-2016, Needlworks  / Tatter Network Foundation
/// All rights reserved. Licensed under the GPL.
/// See the GNU General Public License for more details. (/documents/LICENSE, /documents/COPYRIGHT)
///
/// ---- Modification Notice (GPL §2(a)) ----
/// Modified 2026 by @deokio for PHP 8.5 compatibility,
/// performed with AI assistance (Anthropic Claude) under human review.
/// Changes consist primarily of mechanical PHP migration transformations
/// per the official PHP upgrade documentation.
/// No additional copyright is asserted over these modifications.
/// See CHANGELOG.md and SECURITY.md for full modification history.
// Textcube-NG: 쿠키가 하나도 없는 요청(탐색기·크롤러 등)은 세션을 열지 않는다(세션 조회·쓰기 없음). 이때 session_start 가 보내던 기본 캐시 금지 헤더(session.cache_limiter 'nocache')는 여기서 보낸다. NO_SESSION 이 이미 정해져 있으면 그대로 둔다(v3.212)
if (empty($_COOKIE) && !defined('NO_SESSION')) {
	define('NO_SESSION', true);
	header('Expires: Thu, 19 Nov 1981 08:52:00 GMT');
	header('Cache-Control: no-store, no-cache, must-revalidate');
	header('Pragma: no-cache');
}
require ROOT . '/library/preprocessor.php';
// Textcube-NG: path 모드에서 없는 블로그 이름으로 기본 블로그에 떨어진 요청(uri.isStrictBlogURL false, pathNotFoundBehavior '')은 등록 페이지를 내지 않고 404 로 끝낸다(rss·atom 처리기와 같은 requireStrictBlogURL, 중복 콘텐츠 방지)(r3-5)
requireStrictBlogURL();
// Textcube-NG: AccessFreeSlogan 에 넘기는 $URLInfo 는 어디에서도 정의되지 않았다(PHP 8 에서 implode() 에 null → TypeError). 블로그 기준 요청 경로(decode 된 값, 앞뒤 '/' 뗌)로 정의한다. 아래 fireEvent 호출 줄은 바꾸지 않는다(v3.212)
$freeSloganPath = (isset($suri['value']) && is_string($suri['value'])) ? trim($suri['value'], '/') : '';
// Textcube-NG: fancyURL 0·1(주소가 '…/index.php?/<경로>'·'…/?/<경로>')이면 URIHandler 의 depth 0 분기(원본과 같은 로직, 고치지 않음)가 '?' 로 시작하는 값을 '' 로 바꿔 등록 페이지가 모두 404 가 된다. Dispatcher 의 등록 페이지 판정(freeSloganAllowed)과 같이 input 의 '?' 앞 경로(path 모드는 첫 단계인 블로그 이름을 뗌, Dispatcher 와 같이 index.php 를 거친 요청은 single 로 봄)를 decode 1회 해서 쓴다(v3.212)
if (($context->getProperty('service.fancyURL') === 0 || $context->getProperty('service.fancyURL') === 1) && isset($uri->uri['input']) && is_string($uri->uri['input'])) {
	$freeSloganPath = $uri->uri['input'];
	if (($freeSloganCut = strpos($freeSloganPath, '?')) !== false) $freeSloganPath = substr($freeSloganPath, 0, $freeSloganCut);
	// Textcube-NG: path 모드 호스트 루트의 robots.txt·llms.txt(Dispatcher::isHostRootFreeSloganFile)는 블로그 이름이 없으므로 첫 단계를 떼지 않는다(r3-5)
	if ($context->getProperty('service.type') === 'path' && !defined('__TEXTCUBE_NO_FANCY_URL__') && !Dispatcher::isHostRootFreeSloganFile($freeSloganPath)) $freeSloganPath = (($freeSloganCut = strpos($freeSloganPath, '/')) !== false) ? substr($freeSloganPath, $freeSloganCut + 1) : '';
	$freeSloganPath = trim(URL::decode($freeSloganPath), '/');
	unset($freeSloganCut);
}
$URLInfo = array('path' => $freeSloganPath, 'fragment' => explode('/', $freeSloganPath));
unset($freeSloganPath);

if (eventExists('AccessFreeSlogan')) {
	$info = fireEvent('AccessFreeSlogan', implode('/', $URLInfo['fragment']), $URLInfo);
	
	if (Validator::id($info)) {
		$entries = array();
		
		$objEntry = new Notice();
		
		if ($objEntry->doesExist($info)) {
			list($entries) = getEntryWithPaging($blogid, $info, true);
		} else {
			$objEntry = new Post();
			
			if ($objEntry->doesExist($info))
				list($entries, $paging) = getEntryWithPaging($blogid, $info);
		}
		
		fireEvent('OBStart');
		require ROOT . '/interface/common/blog/begin.php';

		if (empty($entries)) {
			header('HTTP/1.1 404 Not Found');
			if (empty($skin->pageError)) {
				dress('article_rep', '<div class="TCwarning">' . _text('존재하지 않는 페이지입니다.') . '</div>', $view);
			} else {
				dress('article_rep', NULL, $view);
				dress('page_error', $skin->pageError, $view);
			}
			unset($paging);
		} else {
			require ROOT . '/interface/common/blog/entries.php';
		}
		require ROOT . '/interface/common/blog/end.php';
		
		fireEvent('OBEnd');
		exit;
	} else if (is_array($info) && isset($info['title'], $info['body']) && is_string($info['title']) && is_string($info['body'])) {
		// Textcube-NG: 리스너가 돌려준 페이지(제목·본문)를 스킨으로 싸서 본문 자리에 낸다(ⓐ, 글 본문처럼 HTML 을 거르지 않음). 문자열 반환은 더는 그대로 출력하지 않는다(반사 방지). 완전히 새 페이지(ⓑ)는 리스너가 직접 출력한 뒤 exit 한다(v3.212)
		$entries = array();
		// Textcube-NG: 리스너의 선택 키(r3-13). 'body_id' 는 영문으로 시작하는 영문·숫자·_·- 64자 이하일 때만 body id 로 쓴다(begin.php 가 다른 판정보다 먼저 씀).
		// 'skin' 이 'media' 이고 스킨 바깥 틀에 <s_media> 블록([##_media_##], 블록 안 [##_media_content_##])이 있으면 그 블록의 [##_media_content_##] 에 본문을 넣어
		// [##_media_##] 자리에 내고 글 자리(article_rep)는 비운다(interface/common/blog/locatives.php 의 <s_local> 방식). 이때 본문은 'media_body'(문자열)가 있으면 그것, 없으면 'body'.
		// 블록이 없거나 다른 블록 안에 있거나 스킨 캐시가 옛것(media 속성 없음)이면 지금처럼 글 자리에 'body' 를 낸다
		$freeSloganBodyId = (isset($info['body_id']) && is_string($info['body_id']) && preg_match('/^[A-Za-z][A-Za-z0-9_-]{0,63}\z/', $info['body_id'])) ? $info['body_id'] : '';
		fireEvent('OBStart');
		require ROOT . '/interface/common/blog/begin.php';
		unset($freeSloganBodyId);
		$pageTitle = $info['title'];
		if (isset($info['skin']) && $info['skin'] === 'media' && isset($skin->media) && is_string($skin->media)
			&& strpos($skin->media, '[##_media_content_##]') !== false && strpos($view, '[##_media_##]') !== false) {
			$contentContainer['freeslogan_page'] = (isset($info['media_body']) && is_string($info['media_body'])) ? $info['media_body'] : $info['body'];
			dress('media', str_replace('[##_media_content_##]', setTempTag('freeslogan_page'), $skin->media), $view);
			dress('article_rep', '', $view);
		} else {
			$contentContainer['freeslogan_page'] = $info['body'];
			dress('article_rep', setTempTag('freeslogan_page'), $view);
		}
		require ROOT . '/interface/common/blog/end.php';
		fireEvent('OBEnd');
		exit;
	}
}

requireLibrary('error');
errorExit(404);
?>
