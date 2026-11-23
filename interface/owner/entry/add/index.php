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
		'visibility' => array('int', 0, 3),
		'starred' => array('int', 0, 2),
		'category' => array('int', 'default' => 0),
		'title' => array('string'),
		'content' => array('string'),
		'contentformatter' => array('string'),
		'contenteditor' => array('string'),
		'permalink' => array('string', 'default' => ''),
		'location' => array('string', 'default' => '/'),
		'latitude'   => array('number', 'default' => null, 'min' => -90.0, 'max' => 90.0, 'bypass'=>true),
		'longitude'   => array('number', 'default' => null, 'min' => -180.0, 'max' => 180.0, 'bypass'=>true),
		'tag' => array('string', 'default' => ''),
		'acceptcomment' => array(array('0', '1'), 'default' => '0'),
		'accepttrackback' => array(array('0', '1'), 'default' => '0'),
		'published' => array('int', 0, 'default' => 1),
		'draft' => array(array('0', '1'), 'default' => '0')
		)
	);
require ROOT . '/library/preprocessor.php';
requireModel("blog.entry");


requireStrictRoute();
$entry = array();
$entry['visibility'] = $_POST['visibility'];
$entry['starred'] = $_POST['starred'];
$entry['category'] = empty($_POST['category']) ? 0 : $_POST['category'];
$entry['title'] = $_POST['title'];
if ((isset($_POST['permalink'])) && ($_POST['permalink'] != '')) {
	$entry['slogan'] = $_POST['permalink'];
}
$entry['content'] = $_POST['content'];
$entry['contentformatter'] = $_POST['contentformatter'];
$entry['contenteditor'] = $_POST['contenteditor'];
$entry['location'] = empty($_POST['location']) ? '/' : $_POST['location'];
$entry['latitude'] = (empty($_POST['latitude']) || $_POST['latitude'] === 'null' || $_POST['latitude'] === 'undefined') ? null : $_POST['latitude'];
$entry['longitude'] = (empty($_POST['longitude']) || $_POST['longitude'] === 'null' || $_POST['longitude'] === 'undefined') ? null : $_POST['longitude'];
$entry['tag'] = empty($_POST['tag']) ? '' : $_POST['tag'];
$entry['acceptcomment'] = empty($_POST['acceptcomment']) ? 0 : 1;
$entry['accepttrackback'] = empty($_POST['accepttrackback']) ? 0 : 1;
$entry['published'] = empty($_POST['published']) ? 1 : $_POST['published'];
$entry['draft'] = empty($_POST['draft']) ? 0 : $_POST['draft'];
if ($id = addEntry($blogid, $entry)) {
	// 새 글 저장 전 업로드된 임시 첨부파일을 새 entryId로 이전
	// [보안] 현재 사용자의 임시 첨부(parent = -(userid+1))만 — 원본(포트)은 이 블로그의 parent=0 전부(다른 구성원의 임시 첨부 포함)
	moveTemporaryAttachments($blogid, getUserId(), $id);
	fireEvent('AddPost', $id, $entry);
	Setting::setBlogSettingGlobal('LatestEditedEntry_user'.getUserId(),$id);
}
$result = array();
$result['error'] = (($id !== false) === true ? 0 : 1);
$result['entryId'] = $id;
Respond::PrintResult($result);
?>
