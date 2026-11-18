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
	'GET' => array(
		'name' => array('string')
	)
);

require ROOT . '/library/preprocessor.php';
requireModel("blog.attachment");
/* [보안] 원본은 entryId 를 확인하지 않아 다른 사람의 글·임시 첨부를 이름으로 조회할 수 있었다 → 공통 기준(getAttachmentParentForEdit()) */
$attachmentParent = getAttachmentParentForEdit($blogid, isset($suri['id']) ? $suri['id'] : null);
if ($attachmentParent === false) {
	header('HTTP/1.1 403 Forbidden');
	exit;
}
$file = array_pop($_FILES);
$attachment = getAttachmentByLabel($blogid, $attachmentParent, $_GET['name']);
$result = escapeJSInCData(getPrettyAttachmentLabel($attachment)) . '!^|' . escapeJSInCData(getAttachmentValue($attachment));
echo 'result=' . base64_encode(trim($result));
?>
