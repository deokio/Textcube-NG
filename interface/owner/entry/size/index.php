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
		'parent' => array('int')
	)
);
require ROOT . '/library/preprocessor.php';
requireModel('blog.attachment');
/* [보안] 원본은 parent 를 그대로 써서 다른 사람의 글·임시 첨부 용량을 볼 수 있었다 → 공통 기준(getAttachmentParentForEdit()) */
$attachmentParent = getAttachmentParentForEdit($blogid, $_GET['parent']);
if ($attachmentParent === false)
	Respond::PrintResult(array ('error' => 1, 'result' => ''));
$result = getAttachmentSizeLabel($blogid, $attachmentParent);
Respond::PrintResult(array ('error' => empty($result) ? 1 : 0, 'result' => $result));
?> 
