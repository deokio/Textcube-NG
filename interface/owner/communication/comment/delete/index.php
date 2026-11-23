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
		'targets' => array('list', 'default' => '', 'mandatory' => false),
		'ip' => array('ip', 'default' => '', 'mandatory' => false),
		'targetIPs' => array('string', 'default' => '', 'mandatory' => false)
	)
);
require ROOT . '/library/preprocessor.php';
requirePostMethod();	// Textcube-NG: 상태 변경은 POST 만 받는다(그 밖은 405) — 링크·<img> 로 실행되던 GET CSRF 차단(v3.148)
requireModel("blog.comment");
requireStrictRoute();

$isAjaxRequest = checkAjaxRequest();
if(isset($suri['id'])) {
	if (trashCommentInOwner($blogid, $suri['id']) === true)
		$isAjaxRequest ? Respond::ResultPage(0) : header("Location: ".getSafeRedirectURI(isset($_SERVER['HTTP_REFERER']) ? (string)$_SERVER['HTTP_REFERER'] : '', $blogURL.'/owner/communication/comment')); // Textcube-NG: Referer 가 없거나 다른 출처면 /owner/communication/comment 로(r3-8)
	else
		$isAjaxRequest ? Respond::ResultPage(-1) : header("Location: ".getSafeRedirectURI(isset($_SERVER['HTTP_REFERER']) ? (string)$_SERVER['HTTP_REFERER'] : '', $blogURL.'/owner/communication/comment')); // Textcube-NG: Referer 가 없거나 다른 출처면 /owner/communication/comment 로(r3-8)
} else {
	if(!empty($_POST['targets'])) {
		/* Textcube-NG: 원본은 댓글마다 같은 글의 캐시를 비우고 댓글 수를 다시 셌다(r3-9, 503-16).
		   댓글을 모두 옮긴 뒤 옮긴 댓글이 있는 글마다 한 번씩 한다(비우는 캐시·댓글 수는 같다). */
		$trashedEntries = array();
		foreach(explode(',', $_POST['targets']) as $target)
			trashCommentInOwner($blogid, $target, $trashedEntries);
		flushTrashedCommentsInOwner($blogid, $trashedEntries);
	}
	if(!empty($_POST['targetIPs'])) {
		$targetIPs = array_unique(explode(',', $_POST['targetIPs']));
		foreach($targetIPs as $target) {
			if (Validator::ip($target))
				trashCommentInOwnerByIP($blogid, $target);
		}
	}
	if(!empty($_POST['ip'])) {
			trashCommentInOwnerByIP($blogid, $_POST['ip']);
	}
	Respond::ResultPage(0);
}
?>
