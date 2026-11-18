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

function getNoticesWithPaging($blogid, $search, $page, $count) {
	global $database, $folderURL, $suri;
	$aux = '';
	if (($search !== true) && $search) {
		$search = escapeSearchString($search);
		$aux = "AND (title LIKE '%$search%' OR content LIKE '%$search%')";
	}
	$visibility = doesHaveOwnership() ? '' : 'AND visibility > 1';
	$sql = "SELECT * FROM {$database['prefix']}Entries WHERE blogid = $blogid AND draft = 0 $visibility AND category = -2 $aux ORDER BY published DESC";
	return Paging::fetch($sql, $page, $count, "$folderURL/{$suri['value']}");
}

function getNotice($blogid, $id) {
	$query = getDefaultDBModelOnNotice($blogid);
	$query->setQualifier('id','equals',$id);
	return $query->getAll('id, title, slogan, published, userid');
}

function getNotices($blogid) {
	$query = getDefaultDBModelOnNotice($blogid);
	return $query->getAll('id, title, slogan, published, userid');
}

function getRecentNotices($blogid) {
	$context = Model_Context::getInstance();
	$query = getDefaultDBModelOnNotice($blogid);
	$query->setLimit($context->getProperty('skin.noticesOnRecent'));
	return $query->getAll('id, title, slogan, published, userid');
}
function getDefaultDBModelOnNotice($blogid) {
	$context = Model_Context::getInstance();
	$query = DBModel::getInstance();
	$query->reset('Entries');
	$query->setQualifier('blogid','equals',$blogid);
	$query->setQualifier('draft','equals',0);
	if(!doesHaveOwnership()) {
		$query->setQualifier('visibility','bigger',1);
	} else if (!Acl::check('group.editors')) {
		// Textcube-NG: 글관리 권한(group.editors)이 없는 구성원은 자기 공지와 비공개가 아닌 공지만 — (userid = 사용자 OR visibility > 0)(v3.145)
		$query->setQualifierSet(array('userid','equals',(int)getUserId(),false),'OR',array('visibility','bigger',0,false));
	}
	$query->setQualifier('category','equals',-2);
	$query->setOrder('published','DESC');
	return $query;
}
?>
