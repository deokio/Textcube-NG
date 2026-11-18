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

$IV = array(
	'POST' => array(
		'command' => array('any', 'mandatory' => false, 'default'=>'block'),
		'id' => array('id', 'mandatory' => false ),
		'mode' => array(array('ip', 'url', 'content', 'name', 'whiteurl' ) ),
		'value' => array('string', 'mandatory' => false)
	)
);

require ROOT . '/library/preprocessor.php';
requirePostMethod();	// Textcube-NG: 상태 변경은 POST 만 받는다(그 밖은 405) — 링크·<img> 로 실행되던 GET CSRF 차단(v3.148)
requireStrictRoute();

$isAjaxRequest = checkAjaxRequest();
$filter = new Filter();

if ($_POST['command'] == 'unblock') {
	if (empty($_POST['id'])) {
		$filter->type = $_POST['mode'];
		$filter->pattern = $_POST['value'];
	} else {
		$filter->id = $_POST['id'];
	}
	if ($filter->remove()) {
		$isAjaxRequest ? Respond::PrintResult(array('error' => 0)) : header("Location: ".getSafeRedirectURI(isset($_SERVER['HTTP_REFERER']) ? (string)$_SERVER['HTTP_REFERER'] : '', $blogURL.'/owner/communication/filter')); // Textcube-NG: Referer 가 없거나 다른 출처면 /owner/communication/filter 로(r3-8)
	} else {
		$isAjaxRequest ? Respond::PrintResult(array('error' => 1, 'msg' => POD::error())) : header("Location: ".getSafeRedirectURI(isset($_SERVER['HTTP_REFERER']) ? (string)$_SERVER['HTTP_REFERER'] : '', $blogURL.'/owner/communication/filter')); // Textcube-NG: Referer 가 없거나 다른 출처면 /owner/communication/filter 로(r3-8)
	}
} else {
	$filter->type = $_POST['mode'];
	$filter->pattern = $_POST['value'];
	if ($filter->add())
		$isAjaxRequest ? Respond::PrintResult(array('error' => 0,'id' => $filter->id)) : header("Location: ".getSafeRedirectURI(isset($_SERVER['HTTP_REFERER']) ? (string)$_SERVER['HTTP_REFERER'] : '', $blogURL.'/owner/communication/filter')); // Textcube-NG: Referer 가 없거나 다른 출처면 /owner/communication/filter 로(r3-8)
	else
		$isAjaxRequest ? Respond::PrintResult(array('error' => 1, 'msg' => POD::error())) : header("Location: ".getSafeRedirectURI(isset($_SERVER['HTTP_REFERER']) ? (string)$_SERVER['HTTP_REFERER'] : '', $blogURL.'/owner/communication/filter')); // Textcube-NG: Referer 가 없거나 다른 출처면 /owner/communication/filter 로(r3-8)
}
?>
