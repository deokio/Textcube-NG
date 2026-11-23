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
		'templateId' => array('int', 'default' => 0),
		'isSaved' => array('bool', 'default' => false),
		'entryId' => array('int', 'default' => 0)
	)
);
require ROOT . '/library/preprocessor.php';
requireModel('blog.entry');
requireModel('blog.attachment');


requireStrictRoute();

if (!is_null($entry = getEntry($blogid, $_POST['templateId']))) {
	/* [보안] 원본은 templateId 가 서식(category -4)인지 확인하지 않아 서식이 아닌 글(내용·첨부)이나 새 글 임시 첨부(templateId 0)를 복사할 수 있었다 */
	if($entry['category'] != -4) Respond::ResultPage(1);
	if(!$_POST['isSaved']) {
		$entry['category'] = 0;
		$entry['visibility'] = 0;
		$entry['published'] = 'UNIX_TIMESTAMP()';
		$id = addEntry($blogid,$entry);
		/* Textcube-NG: addEntry() 가 실패(false)하면 아래 deleteAttachments() 가 parent 0(업그레이드 전 임시 첨부)을 지우게 된다 → 중단 */
		if(intval($id) <= 0) Respond::ResultPage(1);
	} else {
		if($_POST['entryId'] == 0) Respond::ResultPage(1);
		$id = $_POST['entryId'];
		/* [보안] 원본은 entryId 를 확인하지 않아 아래에서 다른 사람의 글·로고(-1)의 첨부를 지우고 서식 첨부로 바꿀 수 있었다
		   → 다룰 수 있는 글(blog.attachment.php canEditAttachmentParent())만 */
		if(intval($id) <= 0 || !canEditAttachmentParent($blogid, intval($id))) Respond::ResultPage(1);
	}
	// Delete original attachments.
	deleteAttachments($blogid, $id);
	if(copyAttachments($blogid, $_POST['templateId'], $id) === true) {
		$result = array("error"=>"0",
			"title"=>$entry['title'],
			"content"=>$entry['content'],
			"entryId"=>$id);
		Respond::PrintResult($result);
	}
}
Respond::ResultPage(1);
?>
