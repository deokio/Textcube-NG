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
	'FILES' => array(
		'Filedata' => array('file')
	)
);

require ROOT . '/library/preprocessor.php';
requireModel("blog.attachment");
requireStrictRoute();
/* [보안] 원본은 다른 첨부 핸들러(attach·detach)와 달리 requireStrictRoute() 가 없었고, entryId 를 확인하지 않아
   다른 사람의 글(비공개 포함)·임시 첨부에도 올릴 수 있었다 → 첨부 parent 는 공통 기준(blog.attachment.php getAttachmentParentForEdit()) */
$attachmentParent = getAttachmentParentForEdit($blogid, isset($suri['id']) ? $suri['id'] : null);
if ($attachmentParent === false) {
	header('HTTP/1.1 403 Forbidden');
	echo "&error";
	exit;
}
$file = array_pop($_FILES);
$attachment = addAttachment($blogid, $attachmentParent, $file);
/* Textcube-NG: 원본은 결과와 무관하게 &success 를 돌려줘(같은 이름 파일, DB 잠금 등으로 첨부하지 못해도) 업로더가 성공으로 표시했다
   → 첨부하지 못했으면(false) HTTP 500 */
if ($attachment === false) {
	header('HTTP/1.1 500 Internal Server Error');
	echo "&error";
	exit;
}
// Textcube-NG: svg 정화로 외부 참조를 지웠으면 업로더에 개수를 알린다(&svgext=N, 정수, 업로더 경고는 library/view/ownerView.php)(r3-20)
echo "&success" . (!empty($attachment['svgExternal']) ? '&svgext=' . (int)$attachment['svgExternal'] : '');
?>
