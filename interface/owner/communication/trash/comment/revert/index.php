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
require ROOT . '/library/preprocessor.php';
requirePostMethod();	// Textcube-NG: 상태 변경은 POST 만 받는다(그 밖은 405) — 링크·<img> 로 실행되던 GET CSRF 차단(v3.148)
requireStrictRoute();
requireModel("blog.comment");


if(isset($suri['id'])) {
	$isAjaxRequest = checkAjaxRequest();
	
	if (revertCommentInOwner($blogid, $suri['id']) === true)
		$isAjaxRequest ? Respond::ResultPage(0) : header("Location: ".getSafeRedirectURI(isset($_SERVER['HTTP_REFERER']) ? (string)$_SERVER['HTTP_REFERER'] : '', $blogURL.'/owner/communication/trash/comment')); // Textcube-NG: Referer 가 없거나 다른 출처면 /owner/communication/trash/comment 로(r3-8)
	else
		$isAjaxRequest ? Respond::ResultPage(-1) : header("Location: ".getSafeRedirectURI(isset($_SERVER['HTTP_REFERER']) ? (string)$_SERVER['HTTP_REFERER'] : '', $blogURL.'/owner/communication/trash/comment')); // Textcube-NG: Referer 가 없거나 다른 출처면 /owner/communication/trash/comment 로(r3-8)
} else {
	$targets = explode('~*_)', $_POST['targets']);
	for ($i = 0; $i < count($targets); $i++) {
		if ($targets[$i] == '')
			continue;
		revertCommentInOwner($blogid, $targets[$i], false);
	}
	Respond::ResultPage(0);
}
?>
