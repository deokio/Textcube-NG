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

$ajaxcall = (isset($_REQUEST['ajaxcall']) && $_REQUEST['ajaxcall'] == true)  ? true : false;

$IV = array(
	'REQUEST' => array(
		'sidebarNumber' => array('int'),
		'modulePos' => array('int'),
		'targetSidebarNumber' => array('int'),
		'targetPos' => array('int'),
		'viewMode' => array('string', 'default' => '')
	)
);

// Textcube-NG: IV 검사(preprocessor, 빠진 값이면 404)보다 먼저 메서드를 본다 — POST 가 아니면 405(requirePostMethod 와 같은 응답)(r3-5)
if (!isset($_SERVER['REQUEST_METHOD']) || strtoupper((string)$_SERVER['REQUEST_METHOD']) !== 'POST') {
	header('HTTP/1.1 405 Method Not Allowed');
	header('Allow: POST');
	exit;
}
require ROOT . '/library/preprocessor.php';
requirePostMethod();	// Textcube-NG: 상태 변경은 POST 만 받는다(그 밖은 405) — 링크·<img> 로 실행되던 GET CSRF 차단(v3.148)
requireLibrary('blog.skin');
requireModel("blog.sidebar");
requireStrictRoute();
$skin = new Skin($skinSetting['skin']);
$sidebarCount = count($skin->sidebarBasicModules);
$sidebarOrder = getSidebarModuleOrderData($sidebarCount);

if (!is_array($sidebarOrder) || !isset($sidebarOrder[$_REQUEST['sidebarNumber']]) || !is_array($sidebarOrder[$_REQUEST['sidebarNumber']]) || $_REQUEST['targetPos'] < 0 || $_REQUEST['targetSidebarNumber'] < 0 || $_REQUEST['targetSidebarNumber'] >= count($sidebarOrder) || !isset($sidebarOrder[$_REQUEST['targetSidebarNumber']]) || !is_array($sidebarOrder[$_REQUEST['targetSidebarNumber']]) || $_REQUEST['targetPos'] > count($sidebarOrder[$_REQUEST['targetSidebarNumber']])) {	// Textcube-NG: 순서 설정이 없거나(null) 없는 영역 번호면 PHP 8 에서 count()·array_splice() 가 TypeError, 원본 5.6 은 경고 뒤 깨진 순서(null 영역·모듈 유실)를 저장했다 — 두 영역이 배열일 때만 옮기고 아니면 -1(v3.221)
	if (!$ajaxcall)	// Textcube-NG: 링크도 POST(postNavigate)라 메서드 대신 ajaxcall 로 XHR 을 구분(v3.148)
		header('Location: '. $blogURL . '/owner/skin/sidebar' . $_REQUEST['viewMode']);
	else
		Respond::ResultPage(-1);
} else {
	if (($_REQUEST['sidebarNumber'] == $_REQUEST['targetSidebarNumber'])
		&& ($_REQUEST['modulePos'] < $_REQUEST['targetPos'])) 
	{
		$_REQUEST['targetPos']--;
	}
	$temp = array_splice($sidebarOrder[$_REQUEST['sidebarNumber']], $_REQUEST['modulePos'], 1);
	array_splice($sidebarOrder[$_REQUEST['targetSidebarNumber']], $_REQUEST['targetPos'], 0, $temp);
	
	setBlogSetting("sidebarOrder", serialize($sidebarOrder));
	$skin->purgeCache();
}

if ($_REQUEST['viewMode'] != '') $_REQUEST['viewMode'] = '?' . $_REQUEST['viewMode'];

if (!$ajaxcall)	// Textcube-NG: 위와 같음(v3.148)
	header('Location: '. $blogURL . '/owner/skin/sidebar' . $_REQUEST['viewMode']);
else
	Respond::ResultPage(0);
?>
