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
		'published' => array('int', 0, 'default' => 0)
	)
);
require ROOT . '/library/preprocessor.php';

requireModel('blog.entry');

requireStrictRoute();
if(empty($suri['id'])) Respond::ResultPage(1);
/* [보안] 원본은 임시 저장 시 작성자를 확인하지 않아 팀블로그 글쓴이가 다른 사람의 글에 임시 저장본(draft)을 만들 수 있었다
   → update 핸들러와 같게 편집자가 아니면 대상 글의 작성자인지 확인 */
if(!Acl::check('group.editors')) {
	if(getUserIdOfEntry(getBlogId(), (int)$suri['id']) != getUserId()) {
		Respond::ResultPage(1);
	}
}
$entry['id'] = $suri['id'];
$entry['draft'] = 1;
$entry['visibility'] = $_POST['visibility'];
$entry['starred'] = $_POST['starred'];
$entry['category'] = $_POST['category'];
$entry['title'] = $_POST['title'];
$entry['content'] = $_POST['content'];
$entry['contentformatter'] = $_POST['contentformatter'];
$entry['contenteditor'] = $_POST['contenteditor'];
$entry['location'] = empty($_POST['location']) ? '/' : $_POST['location'];
$entry['latitude'] = (empty($_POST['latitude']) || $_POST['latitude'] === 'null' || $_POST['latitude'] === 'undefined') ? null : $_POST['latitude'];
$entry['longitude'] = (empty($_POST['longitude']) || $_POST['longitude'] === 'null' || $_POST['longitude'] === 'undefined') ? null : $_POST['longitude'];
$entry['tag'] = empty($_POST['tag']) ? '' : $_POST['tag'];
$entry['slogan'] = $_POST['permalink'];
$entry['acceptcomment'] = empty($_POST['acceptcomment']) ? 0 : 1;
$entry['accepttrackback'] = empty($_POST['accepttrackback']) ? 0 : 1;
$entry['published'] = empty($_POST['published']) ? 0 : $_POST['published'];
$id = saveDraftEntry($blogid, $entry);
if ($id > 0) {
	setBlogSetting('LatestEditedEntry_user'.getUserId(),$id);
	$result = array();
	$result['error'] = ($id > 0) ? 0 : 1;
	$result['entryId'] = $id;
	$result['suriid'] = $suri['id'];
	Respond::PrintResult($result);
} else {
	Respond::ResultPage(1);
}
?>
