<?php
/// Copyright (c) 2004-2016, Needlworks  / Tatter Network Foundation
/// All rights reserved. Licensed under the GPL.
/// See the GNU General Public License for more details. (/documents/LICENSE, /documents/COPYRIGHT)
///
/// ---- Modification Notice (GPL §2(a)) ----
/// Modified 2026 by @deokio for PHP 5.5 - 7.3 compatibility,
/// performed with AI assistance (Anthropic Claude) under human review.
/// Changes consist primarily of mechanical PHP migration transformations
/// per the official PHP upgrade documentation.
/// No additional copyright is asserted over these modifications.
/// See CHANGELOG.md and SECURITY.md for full modification history.
$IV = array(
	'POST' => array(
		'skinName' => array('directory' ,'mandatory' => false)
	)
);
require ROOT . '/library/preprocessor.php';
requirePostMethod();	// Textcube-NG: 상태 변경은 POST 만 받는다(그 밖은 405) — 링크·<img> 로 실행되던 GET CSRF 차단(v3.148)
requireStrictRoute();

$isAjaxRequest = checkAjaxRequest();
$result = selectSkin($blogid, $_POST['skinName']); // Textcube-NG: POST 전용 경로라 비 Ajax 요청도 POST 값을 씀(r3-8)

if ($result === true) {
	$isAjaxRequest ? Respond::PrintResult(array('error' => 0)) : header("Location: ".getSafeRedirectURI(isset($_SERVER['HTTP_REFERER']) ? (string)$_SERVER['HTTP_REFERER'] : '', $blogURL.'/owner/skin')); // Textcube-NG: Referer 가 없거나 다른 출처면 /owner/skin 로(r3-8)
} else {
	$isAjaxRequest ? Respond::PrintResult(array('error' => 1, 'msg' => _t('스킨을 변경하지 못했습니다.'))) : header("Location: ".getSafeRedirectURI(isset($_SERVER['HTTP_REFERER']) ? (string)$_SERVER['HTTP_REFERER'] : '', $blogURL.'/owner/skin')); // Textcube-NG: Referer 가 없거나 다른 출처면 /owner/skin 로(r3-8)
}
?>
