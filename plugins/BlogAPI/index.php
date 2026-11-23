<?php
/* BlogAPI RSD automarker for Textcube 1.8
   ----------------------------------
   Version 1.8
   Needlworks development team.

   Creator          : coolengineer
   Maintainer       : coolengineer

   Created at       : 2006.8.6
   Last modified at : 2010.4.30
 
 This plugin adds RSD link into blog skin.
 For the detail, visit http://forum.tattersite.com/ko


 General Public License
 http://www.gnu.org/licenses/gpl.html

 This program is free software; you can redistribute it and/or modify
 it under the terms of the GNU General Public License as published by
 the Free Software Foundation; either version 2 of the License, or
 (at your option) any later version.

*/
function AddRSD($target)
{
	global $hostURL, $blogURL;
	$target .= '<link rel="EditURI" type="application/rsd+xml" title="RSD" href="'.$hostURL.$blogURL.'/api?rsd" />'.CRLF;
	return $target;
}

// 플러그인 설정 탭에 OLW 연결 정보를 표시하기 위한 manifestHandler.
// handleConfig() 가 이 함수를 호출하고, 반환된 XML 을 파싱해 설정 폼으로 렌더링한다.
// URL 은 블로그 설정에 의해 자동 결정되므로 편집 불가 — 빈 fieldset legend 로 읽기 전용 표시.
function BlogAPI_ConfigHandler($plugin)
{
	global $hostURL, $blogURL;
	$apiUrl = htmlspecialchars($hostURL . $blogURL . '/api', ENT_QUOTES, 'UTF-8');
	$rsdUrl = htmlspecialchars($hostURL . $blogURL . '/api?rsd', ENT_QUOTES, 'UTF-8');
	return '<?xml version="1.0" encoding="utf-8"?>'
		. '<config>'
		. '<fieldset legend="' . htmlspecialchars(_t('Open Live Writer 연결 설정 (읽기 전용 — 블로그 URL 에 의해 자동 결정)'), ENT_QUOTES, 'UTF-8') . '">'
		. '</fieldset>'
		. '<fieldset legend="' . htmlspecialchars(_t('API 주소'), ENT_QUOTES, 'UTF-8') . ' : ' . $apiUrl . '">'
		. '</fieldset>'
		. '<fieldset legend="' . htmlspecialchars(_t('RSD 주소'), ENT_QUOTES, 'UTF-8') . ' : ' . $rsdUrl . '">'
		. '</fieldset>'
		. '<fieldset legend="' . htmlspecialchars(_t('블로그 종류 : Movable Type API 또는 Metaweblog API / 사용자명·비밀번호 : 블로그 관리자 계정'), ENT_QUOTES, 'UTF-8') . '">'
		. '</fieldset>'
		. '</config>';
}
?>
