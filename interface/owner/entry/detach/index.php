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
// [보안] Textcube-NG: 입력값 규칙($IV) — 빈 값은 원본처럼 아래에서 실패(-1) 처리(r3-7, 결정 472b)
$IV = array(
	'POST' => array(
		'name' => array('filename', 'mandatory' => false)
	)
);
require ROOT . '/library/preprocessor.php';
requirePostMethod();	// Textcube-NG: 상태 변경은 POST 만 받는다(그 밖은 405) — 링크·<img> 로 실행되던 GET CSRF 차단(v3.155)
requireModel("blog.attachment");


requireStrictRoute();
/* [보안] 원본은 entryId 를 확인하지 않아(deleteAttachment() 도 이름만으로 지움) 다른 사람의 글·임시 첨부를 지울 수 있었다
   → 첨부 parent 는 공통 기준(blog.attachment.php getAttachmentParentForEdit()) */
$attachmentParent = getAttachmentParentForEdit($blogid, isset($suri['id']) ? $suri['id'] : null);
if ($attachmentParent !== false && !empty($_POST['name']) && deleteAttachment($blogid, $attachmentParent, $_POST['name']))
	Respond::ResultPage(0);
else
	Respond::ResultPage(-1);
?>
