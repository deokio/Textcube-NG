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
		'visibility' => array('int', 0, 3, 'default' => 0),
		'command' =>  array('string', 'mandatory' => false)
		)
	);

require ROOT . '/library/preprocessor.php';
requirePostMethod();	// Textcube-NG: 상태 변경은 POST 만 받는다(그 밖은 405) — 링크·<img> 로 실행되던 GET CSRF 차단(v3.148)
requireModel("blog.entry");

requireStrictRoute();

// TeamBlog ACL check whether or not current user can edit this post.
/* [보안] 원본은 group.writers(팀블로그 전 구성원이 가짐)를 검사해 작성자 확인이 항상 생략됐다 →
   edit/delete 핸들러와 같게 편집자(group.editors)가 아니면 대상 글의 작성자인지 확인 */
if(Acl::check('group.editors') === false && !empty($suri['id'])) {
	if(getUserIdOfEntry(getBlogId(), $suri['id']) != getUserId()) { 
		@header("location:".$blogURL ."/owner/entry");
		exit;
	}
}

//$isAjaxRequest = checkAjaxRequest();
	
if (!isset($_POST['command'])) {
	$temp = setEntryVisibility($suri['id'], isset($_POST['visibility']) ? $_POST['visibility'] : 0) == true ? 0 : 1;
	$countResult = POD::queryExistence("SELECT id 
			FROM {$database['prefix']}Entries 
			WHERE blogid = ".getBlogId()." AND visibility = 3");
	if ($countResult == false) {
		$countResult = 0;
	} else {
		$countResult = 1;
	}
	Respond::PrintResult(array('error' => $temp, 'countSyndicated' => $countResult), false);
} else {
	switch ($_POST['command']) {
		case "protect":
			$_POST['command'] = 1;
			break;
		case "public":
			$_POST['command'] = 2;
			break;
		case "syndicate":
			$_POST['command'] = 3;
			break;
		case "private":
		default:
			$_POST['command'] = 0;
			break;
	}
	setEntryVisibility($suri['id'], $_POST['command']);
	header("Location: ".$_SERVER['HTTP_REFERER']);
}
?>
