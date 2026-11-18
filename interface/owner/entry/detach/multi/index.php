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
		'names' => array('string', 'default' => null)
	)
);
require ROOT . '/library/preprocessor.php';
requireModel("blog.attachment");


requireStrictRoute();
/* [보안] 원본은 entryId 를 확인하지 않아 다른 사람의 글·임시 첨부를 지울 수 있었다
   → 첨부 parent 는 공통 기준(blog.attachment.php getAttachmentParentForEdit()) */
$attachmentParent = getAttachmentParentForEdit($blogid, isset($suri['id']) ? $suri['id'] : null);
if ($attachmentParent !== false && !empty($_POST['names']) && deleteAttachmentMulti($blogid, $attachmentParent, $_POST['names']))
	Respond::ResultPage(0);
else
	Respond::ResultPage( - 1);
?>
