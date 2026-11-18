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

function getEntriesTotalCount($blogid) {
	global $database;
	$visibility = doesHaveOwnership() ? '' : 'AND e.visibility > 0'.getPrivateCategoryExclusionQuery($blogid);
	$visibility .= (doesHaveOwnership() && !Acl::check('group.editors')) ? ' AND (e.userid = '.getUserId().' OR e.visibility > 0)' : '';
	$count =  POD::queryCell("SELECT COUNT(id)
		FROM {$database['prefix']}Entries e
		WHERE e.blogid = $blogid AND e.draft = 0 $visibility AND e.category >= 0");
	return ($count ? $count : 0);
}

function getNoticesTotalCount($blogid) {
	global $database;
	$visibility = doesHaveOwnership() ? '' : 'AND e.visibility > 0';
	$visibility .= (doesHaveOwnership() && !Acl::check('group.editors')) ? ' AND (e.userid = '.getUserId().' OR e.visibility > 0)' : '';
	return POD::queryCell("SELECT COUNT(*)
		FROM {$database['prefix']}Entries e
		WHERE e.blogid = $blogid AND e.draft = 0 $visibility AND e.category = -2");
}

function getEntries($blogid, $attributes = '*', $condition = false, $order = 'published DESC') {
	global $database;
	if (!empty($condition))
		$condition = 'AND ' . $condition;
	$visibility = doesHaveOwnership() ? '' : 'AND visibility > 0';
	$visibility .= (doesHaveOwnership() && !Acl::check('group.editors')) ? ' AND (userid = '.getUserId().' OR visibility > 0)' : '';
	return POD::queryAll("SELECT $attributes FROM {$database['prefix']}Entries WHERE blogid = $blogid AND draft = 0 $visibility $condition ORDER BY $order");
}


function getTemplates($blogid, $attributes = '*', $condition = false, $order = 'published DESC') {
	global $database;
	if (!empty($condition))
		$condition = 'AND ' . $condition;
	return POD::queryAll("SELECT $attributes
			FROM {$database['prefix']}Entries
			WHERE blogid = $blogid
				AND draft = 0 AND category = -4 $condition
				ORDER BY $order");
}

function getEntry($blogid, $id, $draft = false) {
	global $database;
	requireModel('blog.attachment');
	if($id == 0) {
		if (!doesHaveOwnership())
			return null;
		// HTML5 XHR 업로더 전환으로 인해 새 글 임시 첨부(parent = -(userid+1), 업그레이드 전 parent=0) 파일은 저장 전까지 유지해야 함.
		// 삭제는 사용자가 orphan-check 경고에서 "파일 무시하고 계속"을 선택할 때만 수행.
		return array('id'    => 0,
				'userid'     => 0,
				'draft'      => 0,
				'visibility' => 0,
				'starred'    => 1,
				'category'   => 0,
				'location'   => '',
				'latitude'   => null,
				'longitude'  => null,
				'title'      => '',
				'content'    => '',
				'contentformatter' => getDefaultFormatter(),
				'contenteditor'    => getDefaultEditor(),
				'acceptcomment'    => 1,
				'accepttrackback'  => 1,
				'published'  => time(),
				'slogan'     => '');
	}
	if ($draft) {
		$entry = POD::queryRow("SELECT * FROM {$database['prefix']}Entries
				WHERE blogid = $blogid
					AND id = $id
					AND draft = 1");
		if (!$entry)
			return null;
		if ($entry['published'] == 1)
			$entry['republish'] = true;
		else if ($entry['published'] != 0)
			$entry['appointed'] = $entry['published'];
		if ($id != 0)
			$entry['published'] = POD::queryCell("SELECT published
					FROM {$database['prefix']}Entries
					WHERE blogid = $blogid AND id = $id AND draft = 0");
		return $entry;
	} else {
		$visibility = doesHaveOwnership() ? '' : 'AND visibility > 0';
		// Textcube-NG: 글관리 권한(group.editors)이 없는 구성원은 남의 비공개 글을 열 수 없다 — 서식(category -4)은 서식 불러오기
		// (loadTemplate)가 남의 서식을 읽으므로 예외(v3.145)
		$visibility .= (doesHaveOwnership() && !Acl::check('group.editors')) ? ' AND (userid = '.getUserId().' OR visibility > 0 OR category = -4)' : '';
		$entry = POD::queryRow("SELECT *
				FROM {$database['prefix']}Entries
				WHERE blogid = $blogid AND id = $id AND draft = 0 $visibility");
		if (!$entry)
			return null;
		if ($entry['visibility'] < 0)
			$entry['appointed'] = $entry['published'];
		return $entry;
	}
}

function getUserIdOfEntry($blogid, $id, $draft = false) {
	global $database;
	$result = POD::queryCell("SELECT userid
		FROM {$database['prefix']}Entries
		WHERE
			blogid = $blogid AND id = $id");
	if(!empty($result)) return $result;
	else return null;
}

function getEntryAttributes($blogid, $id, $attributeNames) {
	global $database;

	if (stristr($attributeNames, "from") != false) // security check!
		return null;

	$visibility = doesHaveOwnership() ? '' : 'AND visibility > 0';
	$attributes = POD::queryRow("SELECT $attributeNames FROM {$database['prefix']}Entries WHERE blogid = $blogid AND id = $id AND draft = 0 $visibility");
	return $attributes;
}

function getEntryListWithPagingByCategory($blogid, $category, $page, $count) {
	global $database, $suri, $folderURL, $blog;
	if ($category === null)
		return array();
	if (!doesHaveOwnership() && getCategoryVisibility($blogid, $category) < 2 && $category != 0)
		return array();
	// Textcube-NG: 비공개 분류의 하위 분류도 손님에게는 빈 목록(category.php 와 같은 판정)(r3-5)
	if (!doesHaveOwnership() && $category != 0 && in_array($category, getCategoryVisibilityList($blogid, 'private')))
		return array();
	if ($category > 0) {
		$categories = POD::queryColumn("SELECT id FROM {$database['prefix']}Categories WHERE blogid = $blogid AND parent = $category");
		array_push($categories, $category);
		if(!doesHaveOwnership())
			$categories = array_diff($categories, getCategoryVisibilityList($blogid, 'private'));
		if (empty($categories))	// Textcube-NG: 남는 분류가 없으면 'IN ()' SQL 오류 대신 빈 목록(위 return array() 와 같음. 위 비공개 목록 검사 뒤에는 보통 생기지 않는다)(r3-5)
			return array();
		$cond = 'AND e.category IN (' . implode(', ', $categories) . ')';
		$visibility = doesHaveOwnership() ? '' : 'AND e.visibility > 0';
	} else {
		$cond = 'AND e.category >= 0';
		$visibility = doesHaveOwnership() ? '' : 'AND e.visibility > 0'.getPrivateCategoryExclusionQuery($blogid);
	}
	$visibility .= (doesHaveOwnership() && !Acl::check('group.editors')) ? ' AND (e.userid = '.getUserId().' OR e.visibility > 0)' : '';

	$sql = "SELECT e.blogid,e.userid,e.id,e.title,e.comments,e.slogan,e.published
			FROM {$database['prefix']}Entries e
			WHERE e.blogid = $blogid AND e.draft = 0 $visibility $cond
			ORDER BY e.published DESC";
	return Paging::fetch($sql, $page, $count, "$folderURL/".((!getBlogSetting('useSloganOnCategory',true) && isset($suri['id'])) ? $suri['id'] : $suri['value']));
}

function getEntryListWithPagingByAuthor($blogid, $author, $page, $count) {
	global $database, $suri, $folderURL;
	if ($author === null)
		return array();
	$userid = User::getUserIdByName($author);
	if(empty($userid)) return array();
	$visibility = doesHaveOwnership() ? '' : 'AND e.visibility > 0'.getPrivateCategoryExclusionQuery($blogid);
	$visibility .= (doesHaveOwnership() && !Acl::check('group.editors')) ? ' AND (e.userid = '.getUserId().' OR e.visibility > 0)' : '';

	$sql = "SELECT e.blogid,e.userid,e.id,e.title,e.comments,e.slogan,e.published
			FROM {$database['prefix']}Entries e
			WHERE e.blogid = $blogid AND e.userid = $userid AND e.draft = 0 $visibility
			ORDER BY e.published DESC";
	return Paging::fetch($sql, $page, $count, "$folderURL/{$suri['value']}");
}

function getEntryListWithPagingByTag($blogid, $tag, $page, $count) {
	global $database, $suri, $folderURL;
	if ($tag === null)
		return array(array(), array('url'=>'','prefix'=>'','postfix'=>''));
	$tag = POD::escapeString($tag);
	$visibility = doesHaveOwnership() ? '' : 'AND e.visibility > 0'.getPrivateCategoryExclusionQuery($blogid);
	$visibility .= (doesHaveOwnership() && !Acl::check('group.editors')) ? ' AND (e.userid = '.getUserId().' OR e.visibility > 0)' : '';
	$sql = "SELECT e.blogid, e.userid, e.id, e.title, e.comments, e.slogan, e.published
		FROM {$database['prefix']}Entries e
		LEFT JOIN {$database['prefix']}TagRelations t ON e.id = t.entry AND e.blogid = t.blogid
		WHERE e.blogid = $blogid AND e.draft = 0 $visibility AND e.category >= 0 AND t.tag = '$tag'
		ORDER BY published DESC";
	return Paging::fetch($sql, $page, $count, "$folderURL/".((!getBlogSetting('useSloganOnTag',true) && isset($suri['id'])) ? $suri['id'] : $suri['value']));
}

function getEntryListWithPagingByPeriod($blogid, $period, $page, $count) {
	global $database, $suri, $folderURL;
	// Textcube-NG: 날짜가 아닌 기간이면 getTimeFromPeriod() 가 false 라 원본은 'published >=  AND' 문법 오류로 빈 목록(total null)을 돌려줬다. SQL 을 만들지 않고 같은 값을 돌려준다(v3.201)
	$periodStart = getTimeFromPeriod($period);
	$periodEnd = getTimeFromPeriod(addPeriod($period));
	if ($periodStart === false || $periodEnd === false)
		return array(array(), array('url' => "$folderURL/{$suri['value']}", 'prefix' => '?page=', 'postfix' => '', 'onclick' => null, 'total' => null));
	$cond = "AND e.published >= $periodStart AND e.published < $periodEnd";
	$visibility = doesHaveOwnership() ? '' : 'AND e.visibility > 0'.getPrivateCategoryExclusionQuery($blogid);
	$visibility .= (doesHaveOwnership() && !Acl::check('group.editors')) ? ' AND (e.userid = '.getUserId().' OR e.visibility > 0)' : '';
	$sql = "SELECT e.blogid, e.userid, e.id, e.title, e.comments, e.slogan, e.published
		FROM {$database['prefix']}Entries e
		WHERE e.blogid = $blogid AND e.draft = 0 $visibility AND e.category >= 0 $cond
		ORDER BY e.published DESC";
	return Paging::fetch($sql, $page, $count, "$folderURL/{$suri['value']}");
}

/**
 * @security raw-sql-escape
 * LIKE 절에 사용되는 $search는 escapeSearchString()으로 %, _, 특수문자를 이스케이프 처리함.
 * Paging::fetch()가 완성된 SQL을 받아 실행하는 구조상 prepared statement 적용 불가.
 */
function getEntryListWithPagingBySearch($blogid, $search, $page, $count) {
	global $database, $suri, $folderURL;
	$search = escapeSearchString($search);
	$cond = strlen($search) == 0 ? 'AND 0' : "AND (title LIKE '%$search%' OR content LIKE '%$search%')";
	$visibility = doesHaveOwnership() ? '' : 'AND e.visibility > 1'.getPrivateCategoryExclusionQuery($blogid);
	$visibility .= (doesHaveOwnership() && !Acl::check('group.editors')) ? ' AND (e.userid = '.getUserId().' OR e.visibility > 0)' : '';
	$sql = "SELECT e.blogid, e.userid, e.id, e.title, e.comments, e.slogan, e.published
		FROM {$database['prefix']}Entries e
		WHERE e.blogid = $blogid AND e.draft = 0 $visibility AND e.category >= 0 $cond
		ORDER BY e.published DESC";
	return Paging::fetch($sql, $page, $count, "$folderURL/{$suri['value']}");
}

function getEntriesWithPaging($blogid, $page, $count) {
	global $database;
	$visibility = doesHaveOwnership() ? '' : 'AND e.visibility > 0 AND (c.visibility > 1 OR e.category = 0)'.getPrivateCategoryExclusionQuery($blogid);	// Textcube-NG: 비공개 분류의 하위 분류 글도 뺀다(c.visibility 는 분류 자신의 값만 본다)(r3-5)
	$visibility .= (doesHaveOwnership() && !Acl::check('group.editors')) ? ' AND (e.userid = '.getUserId().' OR e.visibility > 0)' : '';
	$sql = "SELECT e.*, c.label AS categoryLabel
		FROM {$database['prefix']}Entries e
		LEFT JOIN {$database['prefix']}Categories c ON e.blogid = c.blogid AND e.category = c.id
		WHERE e.blogid = $blogid AND e.draft = 0 $visibility AND e.category >= 0
		ORDER BY e.published DESC";
	return Paging::fetch($sql, $page, $count);
}

function getEntriesWithPagingByCategory($blogid, $category, $page, $count, $countItem) {
	global $database, $folderURL, $suri;
	if ($category === null)
		return Paging::fetch(null, $page, $count, "$folderURL/{$suri['value']}");
	// Textcube-NG: 원본은 글 작성자 조건(e.userid, writers 전용)을 별칭 e 가 없는 Categories 질의에 붙여 SQL 오류가 났고
	// (PHP 8 에서 array_push(null) TypeError, HTTP 500), 그 뒤 $visibility 를 덮어써 전체 분류(category 0)에서 writers 에게
	// 다른 사람의 비공개 글이 보였다. Categories 질의에는 분류 공개 조건만 쓰고, 작성자 조건은 아래 글 질의 조건에 붙인다
	// (getEntryListWithPagingByCategory 와 같은 규칙)(v3.138)
	$visibility = doesHaveOwnership() ? '' : 'AND visibility > 1';
	if ($category > 0) {
		$categories = POD::queryColumn("SELECT id FROM {$database['prefix']}Categories WHERE blogid = $blogid AND parent = $category $visibility");
		array_push($categories, $category);
		$cond = 'AND e.category IN (' . implode(', ', $categories) . ')';
		$visibility = doesHaveOwnership() ? '' : 'AND e.visibility > 0';
	} else {
		$cond = 'AND e.category >= 0';
		$visibility = doesHaveOwnership() ? '' : 'AND e.visibility > 0 AND (c.visibility > 1 OR e.category = 0)'.getPrivateCategoryExclusionQuery($blogid);	// Textcube-NG: 비공개 분류의 하위 분류 글도 뺀다(c.visibility 는 분류 자신의 값만 본다)(r3-5)
	}
	$visibility .= (doesHaveOwnership() && !Acl::check('group.editors')) ? ' AND (e.userid = '.getUserId().' OR e.visibility > 0)' : '';
	$sql = "SELECT e.*, c.label AS categoryLabel
		FROM {$database['prefix']}Entries AS e
		LEFT JOIN {$database['prefix']}Categories c ON e.category = c.id AND e.blogid = c.blogid
		WHERE e.blogid = $blogid AND e.draft = 0 $visibility $cond
		ORDER BY e.published DESC";
	return Paging::fetch($sql, $page, $count, "$folderURL/".((!getBlogSetting('useSloganOnCategory',true) && isset($suri['id'])) ? $suri['id'] : $suri['value']),"?page=",$countItem);
}

function getEntriesWithPagingByTag($blogid, $tag, $page, $count, $countItem = null) {
	global $database, $folderURL, $suri;
	if ($tag === null)
		return Paging::fetch(null, $page, $count, "$folderURL/{$suri['value']}");
	$tag = POD::escapeString($tag);
	$visibility = doesHaveOwnership() ? '' : 'AND e.visibility > 0'.getPrivateCategoryExclusionQuery($blogid);
	$visibility .= (doesHaveOwnership() && !Acl::check('group.editors')) ? ' AND (e.userid = '.getUserId().' OR e.visibility > 0)' : '';
	$sql = "SELECT e.*, c.label AS categoryLabel
		FROM {$database['prefix']}Entries e
		LEFT JOIN {$database['prefix']}Categories c ON e.blogid = c.blogid AND e.category = c.id
		LEFT JOIN {$database['prefix']}TagRelations t ON e.id = t.entry AND e.blogid = t.blogid
		WHERE e.blogid = $blogid AND e.draft = 0 $visibility AND e.category >= 0 AND t.tag = '$tag'
		ORDER BY e.published DESC";
	return Paging::fetch($sql, $page, $count, "$folderURL/".((!getBlogSetting('useSloganOnTag',true) && isset($suri['id'])) ? $suri['id'] : $suri['value']),"?page=", $countItem);
}

function getEntriesWithPagingByNotice($blogid, $page, $count, $countItem = null) {
	global $database, $folderURL, $suri;
	$visibility = doesHaveOwnership() ? '' : 'AND visibility = 2';
	$visibility .= (doesHaveOwnership() && !Acl::check('group.editors')) ? ' AND (e.userid = '.getUserId().' OR e.visibility > 0)' : '';
	// Textcube-NG: 원본은 별칭 e 없이 작성자 조건(e.userid)을 붙여 writers 일 때 SQL 오류로 공지 목록이 비었고, draft 조건이 없어
	// 공개 공지의 임시 저장(draft = 1, 발행 전 내용)이 방문자에게도 보였다. 별칭 e 와 draft = 0 을 쓴다(getNoticesTotalCount 와 같은 조건)(v3.138)
	$sql = "SELECT *
		FROM {$database['prefix']}Entries e
		WHERE blogid = $blogid AND draft = 0 $visibility AND category = -2
		ORDER BY published DESC";
	return Paging::fetch($sql, $page, $count, "$folderURL/{$suri['value']}","?page=", $countItem);
}

function getEntriesWithPagingByPeriod($blogid, $period, $page, $count) {
	global $database, $folderURL, $suri;
	// Textcube-NG: 날짜가 아닌 기간이면 getTimeFromPeriod() 가 false 라 원본은 'published >=  AND' 문법 오류로 빈 목록(total null)을 돌려줬다. SQL 을 만들지 않고 같은 값을 돌려준다(v3.201)
	$periodStart = getTimeFromPeriod($period);
	$periodEnd = getTimeFromPeriod(addPeriod($period));
	if ($periodStart === false || $periodEnd === false)
		return array(array(), array('url' => "$folderURL/{$suri['value']}", 'prefix' => '?page=', 'postfix' => '', 'onclick' => null, 'total' => null));
	$cond = "AND published >= $periodStart AND published < $periodEnd";
	$visibility = doesHaveOwnership() ? '' : 'AND e.visibility > 0 AND (c.visibility > 1 OR e.category = 0)'.getPrivateCategoryExclusionQuery($blogid);	// Textcube-NG: 비공개 분류의 하위 분류 글도 뺀다(c.visibility 는 분류 자신의 값만 본다)(r3-5)
	$visibility .= (doesHaveOwnership() && !Acl::check('group.editors')) ? ' AND (e.userid = '.getUserId().' OR e.visibility > 0)' : '';
	$sql = "SELECT e.*, c.label AS categoryLabel
		FROM {$database['prefix']}Entries e
		LEFT JOIN {$database['prefix']}Categories c ON e.blogid = c.blogid AND e.category = c.id
		WHERE e.blogid = $blogid AND e.draft = 0 $visibility AND e.category >= 0 $cond
		ORDER BY e.published DESC";
	return Paging::fetch($sql, $page, $count, "$folderURL/{$suri['value']}");
}

/**
 * @security raw-sql-escape
 * LIKE 절에 사용되는 $search는 escapeSearchString()으로 %, _, 특수문자를 이스케이프 처리함.
 * Paging::fetch()가 완성된 SQL을 받아 실행하는 구조상 prepared statement 적용 불가.
 */
function getEntriesWithPagingBySearch($blogid, $search, $page, $count, $countItem) {
	global $database, $folderURL, $suri;
	$search = escapeSearchString($search);
	$cond = strlen($search) == 0 ? 'AND 0' : "AND (e.title LIKE '%$search%' OR e.content LIKE '%$search%')";
	$visibility = doesHaveOwnership() ? '' : 'AND e.visibility > 1 AND (c.visibility > 1 OR e.category = 0)'.getPrivateCategoryExclusionQuery($blogid);	// Textcube-NG: 비공개 분류의 하위 분류 글도 뺀다(c.visibility 는 분류 자신의 값만 본다)(r3-5)
	$visibility .= (doesHaveOwnership() && !Acl::check('group.editors')) ? ' AND (e.userid = '.getUserId().' OR e.visibility > 0)' : '';
	$sql = "SELECT e.*, c.label AS categoryLabel
		FROM {$database['prefix']}Entries e
		LEFT JOIN {$database['prefix']}Categories c ON e.blogid = c.blogid AND e.category = c.id
		WHERE e.blogid = $blogid AND e.draft = 0 $visibility AND e.category >= 0 $cond
		ORDER BY e.published DESC";
	return Paging::fetch($sql, $page, $count, "$folderURL/{$suri['value']}","?page=", $countItem);
}

function getEntriesWithPagingByAuthor($blogid, $author, $page, $count, $countItem = null) {
	global $database, $folderURL, $suri;
	$userid = User::getUserIdByName($author);
	$visibility = doesHaveOwnership() ? '' : 'AND e.visibility > 0 AND (c.visibility > 1 OR e.category = 0)'.getPrivateCategoryExclusionQuery($blogid);	// Textcube-NG: 비공개 분류의 하위 분류 글도 뺀다(c.visibility 는 분류 자신의 값만 본다)(r3-5)
	$visibility .= (doesHaveOwnership() && !Acl::check('group.editors')) ? ' AND (e.userid = '.getUserId().' OR e.visibility > 0)' : '';
	$sql = "SELECT e.*, c.label categoryLabel
		FROM {$database['prefix']}Entries e
		LEFT JOIN {$database['prefix']}Categories c ON e.blogid = c.blogid AND e.category = c.id
		WHERE e.blogid = $blogid AND e.userid = $userid AND e.draft = 0 $visibility AND e.category >= 0
		ORDER BY e.published DESC";
	return Paging::fetch($sql, $page, $count, "$folderURL/{$suri['value']}","?page=", $countItem);
}

function getEntriesWithPagingForOwner($blogid, $category, $search, $page, $count, $visibility = null, $starred = null, $draft = null, $tag = null) {
	global $database, $suri;

	$teamMemberFilter = "";
	if( ! Acl::check("group.editors", "entry.list") ) {
		$teamMemberFilter = " AND e.userid = ".getUserId();
	}

	$sqlTable = "SELECT e.*, c.label AS categoryLabel, d.id AS draft
		FROM {$database['prefix']}Entries e
		LEFT JOIN {$database['prefix']}Categories c ON e.category = c.id AND e.blogid = c.blogid
		LEFT JOIN {$database['prefix']}Entries d ON e.blogid = d.blogid AND e.id = d.id AND d.draft = 1 ";
	$sql = " WHERE e.blogid = $blogid AND e.draft = 0" . $teamMemberFilter;
	if ($category > 0) {
		$categories = POD::queryColumn("SELECT id FROM {$database['prefix']}Categories WHERE blogid = $blogid AND parent = $category");
		array_push($categories, $category);
		$sql .= ' AND e.category IN (' . implode(', ', $categories) . ')';
	} else if ($category == -3) {
		$sql .= ' AND e.category = 0';
	} else if ($category == -5) {
		$sql .= ' AND e.category >= -2';
	} else if ($category == 0) {
		$sql .= ' AND e.category >= 0';
	} else {
		$sql .= ' AND e.category = '.$category;
	}
	if(isset($visibility)) {
		if(Validator::isInteger($visibility,0,3)) {
			$sql .= ' AND e.visibility = '.$visibility;
		} else {
			$sql .= ' AND e.visibility '.$visibility;
		}
	}
	if(isset($starred)) {
		if(Validator::isInteger($starred,0,3)) {
			$sql .= ' AND e.starred = '.$starred;
		} else {
			$sql .= ' AND e.starred '.$starred;
		}
	}
	if (!empty($search)) {
		$search = escapeSearchString($search);
		$sql .= " AND (e.title LIKE '%$search%' OR e.content LIKE '%$search%')";
	}
	if (!empty($tag)) {
		$sqlTable .= " LEFT JOIN {$database['prefix']}TagRelations t ON e.id = t.entry AND e.blogid = t.blogid ";
		$sql .= ' AND t.tag = '.$tag;
	}

	$sql .= ' ORDER BY e.published DESC';
	return Paging::fetch($sqlTable.$sql, $page, $count);
}

function getEntryWithPaging($blogid, $id, $isNotice = false, $categoryId = false) {
	global $database, $folderURL;
	requireModel('blog.category');
	$entries = array();
	$paging = Paging::init($folderURL, '/');
	$visibility = doesHaveOwnership() ? '' : 'AND e.visibility > 0';
	$visibility .= ($isNotice || doesHaveOwnership()) ? '' : getPrivateCategoryExclusionQuery($blogid);
	$visibility .= (doesHaveOwnership() && !Acl::check('group.editors')) ? ' AND (e.userid = '.getUserId().' OR e.visibility > 0)' : '';
	$category = $isNotice ? 'e.category = -2' : 'e.category >= 0';
	if($categoryId !== false) {
		if($categoryId != 0) {	// Not a 'total' category.
			$childCategories = getChildCategoryId($blogid, $categoryId);
			if(!empty($childCategories)) {
				$category = 'e.category IN ('.$categoryId.','.implode(",",$childCategories).')';
			} else {
				$category = 'e.category = '.$categoryId;
			}
		}
	}
	$currentEntry = POD::queryRow("SELECT e.*, c.label AS categoryLabel
		FROM {$database['prefix']}Entries e
		LEFT JOIN {$database['prefix']}Categories c ON e.blogid = c.blogid AND e.category = c.id
		WHERE e.blogid = $blogid
			AND e.id = $id
			AND e.draft = 0 $visibility AND $category");
	$result = POD::queryColumn("SELECT e.id
		FROM {$database['prefix']}Entries e
		LEFT JOIN {$database['prefix']}Categories c ON e.blogid = c.blogid AND e.category = c.id
		WHERE e.blogid = $blogid
			AND e.draft = 0 $visibility AND $category
		ORDER BY e.published DESC");
	if (!$result || !$currentEntry)
		return array($entries, $paging);

	if($categoryId !== false) {
		$paging['pages'] = $categoryId == 0 ? getEntriesTotalCount($blogid):getEntriesCountByCategory($blogid, $categoryId);
		$paging['postfix'] = '?category='.$categoryId;
	} else {
		$paging['pages'] = ($isNotice) ? getNoticesTotalCount($blogid) : getEntriesTotalCount($blogid);
	}

	for ($i = 1; $entryId = array_shift($result); $i++) {
		if ($entryId != $id) {
			if (array_push($paging['before'], $entryId) > 4) {
				if ($i == 5)
					$paging['first'] = array_shift($paging['before']);
				else
					array_shift($paging['before']);
			}
			continue;
		}
		$paging['page'] = $i;
		array_push($entries, $currentEntry);
		$paging['after'] = array();
		for ($i++; (count($paging['after']) < 4) && ($entryId = array_shift($result)); $i++)
			array_push($paging['after'], $entryId);
		if ($i < $paging['pages']) {
			while ($entryId = array_shift($result))
				$paging['last'] = $entryId;
		}
		if (count($paging['before']) > 0)
			$paging['prev'] = $paging['before'][count($paging['before']) - 1];
		if (isset($paging['after'][0]))
			$paging['next'] = $paging['after'][0];
		return array($entries, $paging);
	}
	$paging['page'] = $paging['pages'] + 1;
	return array($entries, $paging);
}

function getEntryWithPagingBySlogan($blogid, $slogan, $isNotice = false, $categoryId = false) {
	global $database;
	global $blogURL;
	requireModel('blog.category');
	$entries = array();
	$paging = $isNotice ? Paging::init($blogURL."/notice", '/') : Paging::init($blogURL."/entry", '/');
	$visibility = doesHaveOwnership() ? '' : 'AND e.visibility > 0';
	$visibility .= ($isNotice || doesHaveOwnership()) ? '' : getPrivateCategoryExclusionQuery($blogid);
	$visibility .= (doesHaveOwnership() && !Acl::check('group.editors')) ? ' AND (e.userid = '.getUserId().' OR e.visibility > 0)' : '';
	$category = $isNotice ? 'e.category = -2' : 'e.category >= 0';
	if($categoryId !== false) {
		if(!$categoryId == 0) {	// Not a 'total' category.
			$childCategories = getChildCategoryId($blogid, $categoryId);
			if(!empty($childCategories)) {
				$category = 'e.category IN ('.$categoryId.','.implode(",",$childCategories).')';
			} else {
				$category = 'e.category = '.$categoryId;
			}
		}
	}
	$currentEntry = POD::queryRow("SELECT e.*, c.label AS categoryLabel
		FROM {$database['prefix']}Entries e
		LEFT JOIN {$database['prefix']}Categories c ON e.blogid = c.blogid AND e.category = c.id
		WHERE e.blogid = $blogid
			AND e.slogan = '".POD::escapeString($slogan)."'
			AND e.draft = 0 $visibility AND $category");

	$result = POD::queryAll("SELECT e.id, e.slogan
		FROM {$database['prefix']}Entries e
		LEFT JOIN {$database['prefix']}Categories c ON e.blogid = c.blogid AND e.category = c.id
		WHERE e.blogid = $blogid
			AND e.draft = 0 $visibility AND $category
		ORDER BY e.published DESC");
	if (!$result || !$currentEntry)
		return array($entries, $paging);

	if($categoryId !== false) {
		$paging['pages'] = $categoryId == 0 ? getEntriesTotalCount($blogid):getEntriesCountByCategory($blogid, $categoryId);
		$paging['postfix'] = '?category='.$categoryId;
	} else {
		$paging['pages'] = ($isNotice) ? getNoticesTotalCount($blogid) : getEntriesTotalCount($blogid);
	}

	for ($i = 1; $entry = array_shift($result); $i++) {
		if ($entry['slogan'] != $slogan) {
			if (array_push($paging['before'], $entry['slogan']) > 4) {
				if ($i == 5)
					$paging['first'] = array_shift($paging['before']);
				else
					array_shift($paging['before']);
			}
			continue;
		}
		$paging['page'] = $i;
		array_push($entries, $currentEntry);
		$paging['after'] = array();
		for ($i++; (count($paging['after']) < 4) && ($entry = array_shift($result)); $i++)
			array_push($paging['after'], $entry['slogan']);
		if ($i < $paging['pages']) {
			while ($entry = array_shift($result))
				$paging['last'] = $entry['slogan'];
		}
		if (count($paging['before']) > 0)
			$paging['prev'] = $paging['before'][count($paging['before']) - 1];
		if (isset($paging['after'][0]))
			$paging['next'] = $paging['after'][0];
		return array($entries, $paging);
	}
	$paging['page'] = $paging['pages'] + 1;
	return array($entries, $paging);
}

function getSlogan($slogan) {
	$slogan = str_replace("\xC2\xB7", '-', $slogan);	// Textcube-NG: 가운뎃점(·, U+00B7)을 '-' 로(아래 단계에서 공백과 함께 '-' 하나로 합쳐진다)(r3-20)
	$slogan = preg_replace('/-+/', ' ', $slogan);
	$slogan = preg_replace('@[!-/:-\@\[-\^`{-~]+@', '', $slogan);
	$slogan = preg_replace('/\s+/', '-', $slogan);
	$slogan = trim($slogan, '-');
	return strlen($slogan) > 0 ? $slogan : 'XFile';
}

function getRecentEntries($blogid) {
	global $database, $skinSetting;
	$entries = array();
	$visibility = doesHaveOwnership() ? '' : 'AND e.visibility > 0'.getPrivateCategoryExclusionQuery($blogid);
	// Textcube-NG: 글관리 권한(group.editors)이 없는 구성원은 남의 비공개 글을 보지 않는다(같은 파일 글 목록과 같은 조건)(v3.145)
	$visibility .= (doesHaveOwnership() && !Acl::check('group.editors')) ? ' AND (e.userid = '.getUserId().' OR e.visibility > 0)' : '';
	$result = POD::queryAll("SELECT e.id, e.userid, e.title, e.slogan, e.comments, e.published
		FROM {$database['prefix']}Entries e
		WHERE e.blogid = $blogid AND e.draft = 0 $visibility AND e.category >= 0
		ORDER BY published DESC LIMIT {$skinSetting['entriesOnRecent']}");
	if($result) {
		return $result;
	} else {
		return array();
	}
}

function addEntry($blogid, $entry, $userid = null) {
	global $database, $blog, $gCacheStorage;
	requireModel("blog.attachment");
	requireModel("blog.feed");
	requireModel("blog.category");
	requireModel("blog.tag");
	requireModel("blog.locative");
	requireComponent('Textcube.Data.Tag');

	if(empty($userid)) $entry['userid'] = getUserId();
	else $entry['userid'] = $userid;
	$entry['title'] = UTF8::lessenAsEncoding(trim($entry['title']), 255);
	$entry['location'] = UTF8::lessenAsEncoding(trim($entry['location']), 255);
	$entry['slogan'] = array_key_exists('slogan', $entry) ? trim($entry['slogan']) : '';

	if((empty($entry['slogan']))||($entry['category'] == -1)) {
		$slogan = $slogan0 = getSlogan($entry['title']);
	} else {
		$slogan = $slogan0 = getSlogan($entry['slogan']);
	}

	$sloganRaw = UTF8::lessenAsEncoding($slogan, 255); // unescaped, for prepared stmt
	$slogan = POD::escapeString($sloganRaw);
	$title = POD::escapeString($entry['title']);

	if($entry['category'] == -1) {
		if($entry['visibility'] == 1 || $entry['visibility'] == 3)
			return false;
		if(POD::queryCell("SELECT count(*) FROM {$database['prefix']}Entries WHERE blogid = $blogid AND draft = 0 AND title = '$title' AND category = -1") > 0)
			return false;
	}

	if ($entry['category'] < 0) {
		if ($entry['visibility'] == 1) $entry['visibility'] = 0;
		if ($entry['visibility'] == 3) $entry['visibility'] = 2;
	}
	if ($entry['category'] == -4) {
		$entry['visibility'] = 0;
	}

	$result = POD::queryCount("SELECT slogan FROM {$database['prefix']}Entries WHERE blogid = $blogid AND slogan = '$slogan' AND draft = 0 LIMIT 1");
	for ($i = 1; $result > 0; $i++) {
		if ($i > 1000)
			return false;
		$sloganRaw = UTF8::lessenAsEncoding($slogan0, 245) . '-' . $i;
		$slogan = POD::escapeString($sloganRaw);
		$result = POD::queryCount("SELECT slogan FROM {$database['prefix']}Entries WHERE blogid = $blogid AND slogan = '$slogan' AND draft = 0 LIMIT 1");
	}
	// [보안] Textcube-NG: 글관리 권한(group.editors)이 없는 구성원(writers)의 본문은 스크립트를 거른다(filterJavaScript), editors 이상·로그인 없는 호출(cron·플러그인)은 원본대로(r3-7, 결정 455g)
	if (doesHaveOwnership() && !Acl::check('group.editors')) $entry['content'] = filterJavaScript($entry['content']);
	$userid = $entry['userid'];
	$contentVal = $entry['content'];
	$contentformatterVal = $entry['contentformatter'];
	$contenteditorVal = $entry['contenteditor'];
	$passwordVal = generatePassword();
	$locationVal = $entry['location'];
	$latitudeVal = (isset($entry['latitude']) && is_numeric($entry['latitude'])) ? (float)$entry['latitude'] : null;
	$longitudeVal = (isset($entry['longitude']) && is_numeric($entry['longitude'])) ? (float)$entry['longitude'] : null;
	$useTimestamp = true;
	$publishedVal = null;
	if (!isset($entry['firstEntry']) && isset($entry['published']) && is_numeric($entry['published']) && ($entry['published'] >= 2)) {
		$publishedVal = (int)$entry['published'];
		$useTimestamp = false;
		$entry['visibility'] = 0 - $entry['visibility'];
		if($entry['visibility'] < 0) {
			$closestReservedTime = Setting::getBlogSettingGlobal('closestReservedPostTime',INT_MAX);
			if($publishedVal < $closestReservedTime) {
				Setting::setBlogSetting('closestReservedPostTime',$publishedVal,true);
			}
		}
	}

	$currentMaxId = POD::queryCell("SELECT MAX(id) FROM {$database['prefix']}Entries WHERE blogid = $blogid AND draft = 0");
	if(!empty($currentMaxId) && $currentMaxId > 0) {
		$id = $currentMaxId + 1;
	} else {
		$id = 1;
	}

	// Prepared statement — INSERT with external user input (SQL Injection 대응)
	// $slogan and $title were escaped for use in earlier SELECT queries;
	// for the prepared statement use the original unescaped values.
	$titleVal  = $entry['title'];
	$sloganVal = $sloganRaw; // $sloganRaw tracks the unescaped final slogan (set in the loop above)

	if ($useTimestamp) {
		$stmt = POD::prepare("INSERT INTO {$database['prefix']}Entries
				(blogid, userid, id, draft, visibility, starred, category, title, slogan, content, contentformatter,
				 contenteditor, location, latitude, longitude, password, acceptcomment, accepttrackback, published, created, modified,
				 comments, trackbacks, pingbacks)
				VALUES (?,?,?,0,?,?,?,?,?,?,?,?,?,?,?,?,?,?,UNIX_TIMESTAMP(),UNIX_TIMESTAMP(),UNIX_TIMESTAMP(),0,0,0)");
	} else {
		$stmt = POD::prepare("INSERT INTO {$database['prefix']}Entries
				(blogid, userid, id, draft, visibility, starred, category, title, slogan, content, contentformatter,
				 contenteditor, location, latitude, longitude, password, acceptcomment, accepttrackback, published, created, modified,
				 comments, trackbacks, pingbacks)
				VALUES (?,?,?,0,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,UNIX_TIMESTAMP(),UNIX_TIMESTAMP(),0,0,0)");
	}
	if (!$stmt) return false;

	if ($useTimestamp) {
		// types: blogid(i) userid(i) id(i) visibility(i) starred(i) category(i) title(s) slogan(s) content(s) contentformatter(s) contenteditor(s) location(s) latitude(d) longitude(d) password(s) acceptcomment(i) accepttrackback(i) = 17 params
		POD::bindAndExecute($stmt, 'iiiiiissssssddsii',
			(int)$blogid,
			(int)$userid,
			(int)$id,
			(int)$entry['visibility'],
			(int)$entry['starred'],
			(int)$entry['category'],
			$titleVal,
			$sloganVal,
			$contentVal,
			$contentformatterVal,
			$contenteditorVal,
			$locationVal,
			$latitudeVal,
			$longitudeVal,
			$passwordVal,
			(int)$entry['acceptcomment'],
			(int)$entry['accepttrackback']
		);
	} else {
		// types: blogid(i) userid(i) id(i) visibility(i) starred(i) category(i) title(s) slogan(s) content(s) contentformatter(s) contenteditor(s) location(s) latitude(d) longitude(d) password(s) acceptcomment(i) accepttrackback(i) published(i) = 18 params
		POD::bindAndExecute($stmt, 'iiiiiissssssddsiii',
			(int)$blogid,
			(int)$userid,
			(int)$id,
			(int)$entry['visibility'],
			(int)$entry['starred'],
			(int)$entry['category'],
			$titleVal,
			$sloganVal,
			$contentVal,
			$contentformatterVal,
			$contenteditorVal,
			$locationVal,
			$latitudeVal,
			$longitudeVal,
			$passwordVal,
			(int)$entry['acceptcomment'],
			(int)$entry['accepttrackback'],
			$publishedVal
		);
	}
	$result = $stmt->affected_rows > 0 || $stmt->errno === 0;
	$stmt->close();
	if (!$result)
		return false;
	/* [보안] 원본은 저장하는 사람과 관계없이 이 블로그의 새 글 임시 첨부(parent=0)를 모두 이 글로 옮겼다(팀블로그에서 다른 구성원의 임시 첨부를 가져감)
	   → 현재 사용자의 임시 첨부(parent = -(userid+1), blog.attachment.php getTemporaryAttachmentParent())만 옮긴다 */
	requireModel('blog.attachment');
	moveTemporaryAttachments($blogid, getUserId(), $id);
	POD::query("DELETE FROM {$database['prefix']}Entries WHERE blogid = $blogid AND id = $id AND draft = 1");
	updateCategoryByEntryId($blogid, $id, 'add');
	CacheControl::flushEntry($id);
	clearFeed();

	if ($entry['visibility'] == 3)
		syndicateEntry($id, 'create');
	if ($entry['visibility'] >= 2) {
		CacheControl::flushAuthor($userid);
		CacheControl::flushDBCache('entry');
		$gCacheStorage->purge();
	}

	if (!empty($entry['tag'])) {
		$tags = getTagsWithEntryString($entry['tag']);
		Tag::addTagsWithEntryId($blogid, $id, $tags);
	}
	return $id;
}

function updateEntry($blogid, $entry, $updateDraft = 0) {
	global $database, $blog, $gCacheStorage;
	requireModel('blog.tag');
	requireModel('blog.locative');
	requireModel('blog.attachment');
	requireModel('blog.category');
	requireModel('blog.feed');
	requireComponent('Textcube.Data.Tag');

	if($entry['id'] == 0) return false;

	$oldEntry = POD::queryRow("SELECT *
		FROM {$database['prefix']}Entries
		WHERE blogid = $blogid
		AND id = {$entry['id']}
		AND draft = 0");
	if(empty($oldEntry)) return false;

	if(empty($entry['userid'])) $entry['userid'] = getUserId();
	$entry['title'] = UTF8::lessenAsEncoding(trim($entry['title']));
	$entry['location'] = UTF8::lessenAsEncoding(trim($entry['location']));
	$entry['slogan'] = array_key_exists('slogan', $entry) ? trim($entry['slogan']) : '';
	if(empty($entry['slogan'])) {
		$slogan = $slogan0 = getSlogan($entry['title']);
	} else {
		$slogan = $slogan0 = getSlogan($entry['slogan']);
	}
	$sloganRaw = UTF8::lessenAsEncoding($slogan, 255); // unescaped, for prepared stmt
	$slogan = POD::escapeString($sloganRaw);
	$title = POD::escapeString($entry['title']);

	if($entry['category'] == -1) {
		if($entry['visibility'] == 1 || $entry['visibility'] == 3)
			return false;
		if(POD::queryCell("SELECT count(*)
			FROM {$database['prefix']}Entries
			WHERE blogid = $blogid
				AND id <> {$entry['id']}
				AND draft = 0
				AND title = '$title'
				AND category = -1") > 0)
			return false;
	}

	if ($entry['category'] < 0) {
		if ($entry['visibility'] == 1) $entry['visibility'] = 0;
		if ($entry['visibility'] == 3) $entry['visibility'] = 2;
	}
	if ($entry['category'] == -4) {
		$entry['visibility'] = 0;
	}

	$result = POD::queryCount("SELECT slogan
		FROM {$database['prefix']}Entries
		WHERE blogid = $blogid
		AND slogan = '$slogan'
		AND id = {$entry['id']}
		AND draft = 0
		LIMIT 1");
	if ($result == 0) { // if changed
		$result = POD::queryCount("SELECT slogan FROM {$database['prefix']}Entries WHERE blogid = $blogid AND slogan = '$slogan' AND draft = 0 LIMIT 1");
		for ($i = 1; $result > 0; $i++) {
			if ($i > 1000)
				return false;
			$sloganRaw = UTF8::lessenAsEncoding($slogan0, 245) . '-' . $i;
			$slogan = POD::escapeString($sloganRaw);
			$result = POD::queryCount("SELECT slogan FROM {$database['prefix']}Entries WHERE blogid = $blogid AND slogan = '$slogan' AND draft = 0 LIMIT 1");
		}
	}
	$tags = getTagsWithEntryString($entry['tag']);
	Tag::modifyTagsWithEntryId($blogid, $entry['id'], $tags);

	$locationVal = $entry['location'];
	$latitudeVal = (isset($entry['latitude']) && is_numeric($entry['latitude'])) ? (float)$entry['latitude'] : null;
	$longitudeVal = (isset($entry['longitude']) && is_numeric($entry['longitude'])) ? (float)$entry['longitude'] : null;
	// [보안] Textcube-NG: 글관리 권한(group.editors)이 없는 구성원(writers)의 본문은 스크립트를 거른다(filterJavaScript), editors 이상·로그인 없는 호출(cron·플러그인)은 원본대로(r3-7, 결정 455g)
	if (doesHaveOwnership() && !Acl::check('group.editors')) $entry['content'] = filterJavaScript($entry['content']);
	$contentVal = $entry['content'];
	$contentformatterVal = $entry['contentformatter'];
	$contenteditorVal = $entry['contenteditor'];
	$publishedMode = 'keep'; // keep current published value
	$publishedVal = null;
	switch ($entry['published']) {
		case 0:
			$publishedMode = 'keep';
			break;
		case 1:
			$publishedMode = 'now';
			break;
		default:
			$publishedVal = (int)$entry['published'];
			$publishedMode = 'custom';
			$entry['visibility'] = 0 - $entry['visibility'];
			if($entry['visibility'] < 0) {
				$closestReservedTime = Setting::getBlogSettingGlobal('closestReservedPostTime',9999999999);
				if($publishedVal < $closestReservedTime) {
					Setting::setBlogSetting('closestReservedPostTime',$publishedVal,true);
				}
			}
			break;
	}

	// Prepared statement — UPDATE with external user input (SQL Injection 대응)
	// published column handling differs by $publishedMode:
	//   keep   → published = published  (no placeholder)
	//   now    → published = UNIX_TIMESTAMP()  (no placeholder)
	//   custom → published = ?  (one extra placeholder)
	$titleVal  = $entry['title'];

	if ($publishedMode === 'keep') {
		$sql = "UPDATE {$database['prefix']}Entries
				SET
					userid           = ?,
					visibility       = ?,
					starred          = ?,
					category         = ?,
					draft            = 0,
					location         = ?,
					latitude         = ?,
					longitude        = ?,
					title            = ?,
					content          = ?,
					contentformatter = ?,
					contenteditor    = ?,
					slogan           = ?,
					acceptcomment    = ?,
					accepttrackback  = ?,
					published        = published,
					modified         = UNIX_TIMESTAMP()
				WHERE blogid = ? AND id = ? AND draft = ?";
		// types: userid(i) visibility(i) starred(i) category(i) location(s) latitude(d) longitude(d) title(s) content(s) contentformatter(s) contenteditor(s) slogan(s) acceptcomment(i) accepttrackback(i) blogid(i) id(i) draft(i) = 17 params
		$stmt = POD::prepare($sql);
		if (!$stmt) return false;
		POD::bindAndExecute($stmt, 'iiiisddsssssiiiii',
			(int)$entry['userid'],
			(int)$entry['visibility'],
			(int)$entry['starred'],
			(int)$entry['category'],
			$locationVal,
			$latitudeVal,
			$longitudeVal,
			$titleVal,
			$contentVal,
			$contentformatterVal,
			$contenteditorVal,
			$sloganRaw,
			(int)$entry['acceptcomment'],
			(int)$entry['accepttrackback'],
			(int)$blogid,
			(int)$entry['id'],
			(int)$updateDraft
		);
	} elseif ($publishedMode === 'now') {
		$sql = "UPDATE {$database['prefix']}Entries
				SET
					userid           = ?,
					visibility       = ?,
					starred          = ?,
					category         = ?,
					draft            = 0,
					location         = ?,
					latitude         = ?,
					longitude        = ?,
					title            = ?,
					content          = ?,
					contentformatter = ?,
					contenteditor    = ?,
					slogan           = ?,
					acceptcomment    = ?,
					accepttrackback  = ?,
					published        = UNIX_TIMESTAMP(),
					modified         = UNIX_TIMESTAMP()
				WHERE blogid = ? AND id = ? AND draft = ?";
		// same 17 params
		$stmt = POD::prepare($sql);
		if (!$stmt) return false;
		POD::bindAndExecute($stmt, 'iiiisddsssssiiiii',
			(int)$entry['userid'],
			(int)$entry['visibility'],
			(int)$entry['starred'],
			(int)$entry['category'],
			$locationVal,
			$latitudeVal,
			$longitudeVal,
			$titleVal,
			$contentVal,
			$contentformatterVal,
			$contenteditorVal,
			$sloganRaw,
			(int)$entry['acceptcomment'],
			(int)$entry['accepttrackback'],
			(int)$blogid,
			(int)$entry['id'],
			(int)$updateDraft
		);
	} else {
		$sql = "UPDATE {$database['prefix']}Entries
				SET
					userid           = ?,
					visibility       = ?,
					starred          = ?,
					category         = ?,
					draft            = 0,
					location         = ?,
					latitude         = ?,
					longitude        = ?,
					title            = ?,
					content          = ?,
					contentformatter = ?,
					contenteditor    = ?,
					slogan           = ?,
					acceptcomment    = ?,
					accepttrackback  = ?,
					published        = ?,
					modified         = UNIX_TIMESTAMP()
				WHERE blogid = ? AND id = ? AND draft = ?";
		// types: userid(i) visibility(i) starred(i) category(i) location(s) latitude(d) longitude(d) title(s) content(s) contentformatter(s) contenteditor(s) slogan(s) acceptcomment(i) accepttrackback(i) published(i) blogid(i) id(i) draft(i) = 18 params
		$stmt = POD::prepare($sql);
		if (!$stmt) return false;
		POD::bindAndExecute($stmt, 'iiiisddsssssiiiiii',
			(int)$entry['userid'],
			(int)$entry['visibility'],
			(int)$entry['starred'],
			(int)$entry['category'],
			$locationVal,
			$latitudeVal,
			$longitudeVal,
			$titleVal,
			$contentVal,
			$contentformatterVal,
			$contenteditorVal,
			$sloganRaw,
			(int)$entry['acceptcomment'],
			(int)$entry['accepttrackback'],
			$publishedVal,
			(int)$blogid,
			(int)$entry['id'],
			(int)$updateDraft
		);
	}
	$result = $stmt->affected_rows >= 0 && $stmt->errno === 0;
	$stmt->close();

	if ($result)
		@POD::query("DELETE FROM {$database['prefix']}Entries WHERE blogid = $blogid AND id = {$entry['id']} AND draft = 1");

	updateCategoryByEntryId($blogid, $entry['id'], 'update',
		array('category'=>array($oldEntry['category'],$entry['category']),
			'visibility'=>array($oldEntry['visibility'],$entry['visibility'])
		));

	CacheControl::flushEntry($entry['id']);
	/* Textcube-NG: 공개 여부가 바뀌면 태그 페이지(tagPage) 캐시도 비운다 — 방문자 /tag 는 공개 글의 태그만 보여 준다(v3.136).
	   태그 구성이 바뀐 경우는 Tag::modifyTagsWithEntryId() 가 비운다 */
	if ($oldEntry['visibility'] != $entry['visibility'])
		CacheControl::flushTagPage();
	$gCacheStorage->purge();
	if ($entry['visibility'] == 3)
		syndicateEntry($entry['id'], 'modify');
	/* [보안] 원본은 저장하는 사람과 관계없이 이 블로그의 새 글 임시 첨부(parent=0)를 모두 이 글로 옮겼다(팀블로그에서 다른 구성원의 임시 첨부를 가져감)
	   → 현재 사용자의 임시 첨부(parent = -(userid+1), blog.attachment.php getTemporaryAttachmentParent())만 옮긴다 */
	moveTemporaryAttachments($blogid, getUserId(), $entry['id']);
	if ($entry['visibility'] >= 2)
		clearFeed();
	return $result ? $entry['id'] : false;
}

function saveDraftEntry($blogid, $entry) {
	global $database, $blog;
	requireModel('blog.tag');
	requireModel('blog.locative');
	requireModel('blog.attachment');
	requireModel('blog.category');
	requireModel('blog.feed');
	requireComponent('Textcube.Data.Tag');

	if($entry['id'] == 0) return -11;
	// Textcube-NG: 공개 범위(visibility)는 0~3 정수, 발행 시각(published)은 0 이상 정수만 받는다(draft 핸들러 IV 'int',0,3 / 'int',0 과 같은 범위).
	// 모델을 직접 부를 때 숫자가 아닌 값("abc"·"5abc")이나 범위 밖 값이 그대로 SQL 에 들어가거나 0·5 로 바뀌어 저장되던 것을 거부한다(false → 핸들러 error 1)(r3-5)
	if (!Validator::isInteger($entry['visibility'], 0, 3) || !Validator::isInteger($entry['published'], 0))
		return false;

	$draftCount = POD::queryCell("SELECT count(*) FROM {$database['prefix']}Entries
		WHERE blogid = $blogid
			AND id = ".$entry['id']."
			AND draft = 1");

	if($draftCount > 0) { // draft가 없으면 insert를, 있으면 update를.
		$doUpdate = true;
	} else {
		$doUpdate = false;
	}
	// 원 글을 읽어서 몇가지 정보를 보존한다. 원래 글이 없는 경우 draft는 저장될 수 없다.
	$origEntry = POD::queryRow("SELECT created, comments, trackbacks, pingbacks, password, published
		FROM {$database['prefix']}Entries
		WHERE blogid = $blogid
			AND id = ".$entry['id']."
			AND draft = 0");
	if(empty($origEntry)) return -12;

	$created = $origEntry['created'];
	$comments = $origEntry['comments'];
	$trackbacks = $origEntry['trackbacks'];
	$pingbacks = $origEntry['pingbacks'];
	$password = $origEntry['password'];

	if(empty($entry['userid'])) $entry['userid'] = getUserId();
	$entry['title'] = UTF8::lessenAsEncoding(trim($entry['title']));
	$entry['location'] = UTF8::lessenAsEncoding(trim($entry['location']));
	$entry['slogan'] = array_key_exists('slogan', $entry) ? trim($entry['slogan']) : '';
	if(empty($entry['slogan'])) {
		$slogan = $slogan0 = getSlogan($entry['title']);
	} else {
		$slogan = $slogan0 = getSlogan($entry['slogan']);
	}
	$slogan = POD::escapeString(UTF8::lessenAsEncoding($slogan, 255));
	$title = POD::escapeString($entry['title']);

	if($entry['category'] == -1) {
		if($entry['visibility'] == 1 || $entry['visibility'] == 3)
			return false;
		if(POD::queryCell("SELECT count(*)
			FROM {$database['prefix']}Entries
			WHERE blogid = $blogid
				AND id <> {$entry['id']}
				AND draft = 0
				AND title = '$title'
				AND category = -1") > 0)
			return -13;
	}

	if ($entry['category'] < 0) {
		if ($entry['visibility'] == 1) $entry['visibility'] = 0;
		if ($entry['visibility'] == 3) $entry['visibility'] = 2;
	}
	if ($entry['category'] == -4) {
		$entry['visibility'] = 0;
	}

	$result = POD::queryCount("SELECT slogan
		FROM {$database['prefix']}Entries
		WHERE blogid = $blogid
		AND slogan = '$slogan'
		AND id = {$entry['id']}
		AND draft = 0 LIMIT 1");
	if ($result == 0) { // if changed
		$result = POD::queryExistence("SELECT slogan FROM {$database['prefix']}Entries WHERE blogid = $blogid AND slogan = '$slogan' AND draft = 0 LIMIT 1");
		for ($i = 1; $result != false; $i++) {
			if ($i > 1000)
				return false;
			$slogan = POD::escapeString(UTF8::lessenAsEncoding($slogan0, 245) . '-' . $i);
			$result = POD::queryExistence("SELECT slogan FROM {$database['prefix']}Entries WHERE blogid = $blogid AND slogan = '$slogan' AND draft = 0 LIMIT 1");
		}
	}
	// Textcube-NG: 임시 저장은 발행된 글의 태그 연결(TagRelations)을 바꾸지 않는다. 원본은 임시 저장만 해도 공개 글의 태그가 바뀌었다.
	// 임시 글의 태그는 따로 저장하지 않으므로 발행 전까지 남지 않는다(사용자 결정 503-9 A)(r3-8)

	$location = POD::escapeString($entry['location']);
	$latitude = (isset($entry['latitude']) && is_numeric($entry['latitude'])) ? $entry['latitude'] : 'NULL';
	$longitude = (isset($entry['longitude']) && is_numeric($entry['longitude'])) ? $entry['longitude'] : 'NULL';
	// [보안] Textcube-NG: 글관리 권한(group.editors)이 없는 구성원(writers)의 본문은 스크립트를 거른다(filterJavaScript), editors 이상·로그인 없는 호출(cron·플러그인)은 원본대로(r3-7, 결정 455g)
	if (doesHaveOwnership() && !Acl::check('group.editors')) $entry['content'] = filterJavaScript($entry['content']);
	$content = POD::escapeString($entry['content']);
	$contentformatter = POD::escapeString($entry['contentformatter']);
	$contenteditor = POD::escapeString($entry['contenteditor']);
	switch ($entry['published']) {
		case 0:
			$published = 'published';
			break;
		case 1:
			$published = 'UNIX_TIMESTAMP()';
			break;
		default:
			// Textcube-NG: 원본은 값을 따옴표 없이 SQL 에 넣어 정수가 아니면(모델 직접 호출) SQL 오류가 나거나 식이 그대로 들어갔다("UNIX_TIMESTAMP()"). 정수로 넣는다(v3.201)
			$published = (int)$entry['published'];
			$entry['visibility'] = 0 - $entry['visibility'];
			break;
	}

	if($doUpdate) {
		$result = POD::query("UPDATE {$database['prefix']}Entries
			SET
				userid             = {$entry['userid']},
				visibility         = {$entry['visibility']},
				starred            = {$entry['starred']},
				category           = {$entry['category']},
				draft              = 1,
				location           = '$location',
				latitude           = $latitude,
				longitude          = $longitude,
				title              = '$title',
				content            = '$content',
				contentformatter   = '$contentformatter',
				contenteditor      = '$contenteditor',
				slogan             = '$slogan',
				acceptcomment      = {$entry['acceptcomment']},
				accepttrackback    = {$entry['accepttrackback']},
				published          = $published,
				modified           = UNIX_TIMESTAMP()
			WHERE blogid = $blogid AND id = {$entry['id']} AND draft = 1");
	} else {
		/* Textcube-NG: 원본은 발행 시각을 유지할 때(published 0) INSERT 의 VALUES 에 열 이름 published 를 그대로 넣었다.
		   INSERT 의 값에서는 같은 행의 열을 참조할 수 없어 SQLite 는 'no such column' 오류 — 기존 글의 첫 임시 저장이 늘 실패하고
		   편집 화면이 5분마다 다시 시도했다(pat.im 1249). 원 글의 발행 시각을 정수로 넣는다(UPDATE 경로는 그대로)(v3.137) */
		if ($published === 'published')
			$published = intval($origEntry['published']);
		$result = POD::query("INSERT INTO {$database['prefix']}Entries
			(blogid, userid, id, draft, visibility, starred, category, title, slogan, content, contentformatter,
			 contenteditor, location, password, acceptcomment, accepttrackback, published, created, modified,
			 comments, trackbacks, pingbacks)
			VALUES (
			$blogid,
			{$entry['userid']},
			{$entry['id']},
			1,
			{$entry['visibility']},
			{$entry['starred']},
			{$entry['category']},
			'$title',
			'$slogan',
			'$content',
			'$contentformatter',
			'$contenteditor',
			'$location',
			'$password',
			{$entry['acceptcomment']},
			{$entry['accepttrackback']},
			$published,
			$created,
			UNIX_TIMESTAMP(),
			$comments,
			$trackbacks,
			$pingbacks)");
	}
	return $result ? $entry['id'] : false;
}

function updateRemoteResponsesOfEntry($blogid, $id) {
	global $database;
	$trackbacks = POD::queryCell("SELECT COUNT(*) FROM {$database['prefix']}RemoteResponses WHERE blogid = $blogid AND entry = $id AND isfiltered = 0 AND responsetype = 'trackback'");
	$pingbacks  = POD::queryCell("SELECT COUNT(*) FROM {$database['prefix']}RemoteResponses WHERE blogid = $blogid AND entry = $id AND isfiltered = 0 AND responsetype = 'pingback'");
	if ($trackbacks === null || $pingbacks === null)
		return false;
	return POD::execute("UPDATE {$database['prefix']}Entries SET trackbacks = $trackbacks, pingbacks = $pingbacks WHERE blogid = $blogid AND id = $id");
}

function deleteEntry($blogid, $id) {
	global $database, $gCacheStorage;
	requireModel("blog.feed");
	requireModel("blog.category");
	requireModel("blog.attachment");
	requireModel("blog.tag");
	requireComponent("Textcube.Data.Tag");

	$target = getEntry($blogid, $id);
	if (is_null($target)) return false;
	if (POD::queryCell("SELECT visibility FROM {$database['prefix']}Entries WHERE blogid = $blogid AND id = $id") == 3)
		syndicateEntry($id, 'delete');
	CacheControl::flushEntry($id);
	CacheControl::flushDBCache('entry');
	CacheControl::flushDBCache('comment');
	CacheControl::flushDBCache('trackback');
	$gCacheStorage->purge();
	$result = POD::queryCount("DELETE FROM {$database['prefix']}Entries WHERE blogid = $blogid AND id = $id");
	if ($result > 0) {
		$result = POD::query("DELETE FROM {$database['prefix']}Comments WHERE blogid = $blogid AND entry = $id");
		$result = POD::query("DELETE FROM {$database['prefix']}RemoteResponses WHERE blogid = $blogid AND entry = $id");
		$result = POD::query("DELETE FROM {$database['prefix']}RemoteResponseLogs WHERE blogid = $blogid AND entry = $id");
		updateCategoryByEntryId($blogid, $id, 'delete', array('entry' => $target));
		deleteAttachments($blogid, $id);

		Tag::deleteTagsWithEntryId($blogid, $id);
		clearFeed();
		fireEvent('DeletePost', $id, null);
		return true;
	}
	return false;
}

function changeCategoryOfEntries($blogid, $entries, $category) {
	global $database;
	requireModel("blog.category");
	requireModel("blog.feed");

	$targets = array_unique(preg_split('/,/', $entries, -1, PREG_SPLIT_NO_EMPTY));
	$effectedCategories = array();
	if ( count($targets)<1 || !is_numeric($category) )
		return false;

	if ($category == -1) { // Check Keyword duplication
		foreach($targets as $entryId) {
			$title = POD::queryCell("SELECT title FROM {$database['prefix']}Entries WHERE blogid = $blogid AND id = $entryId AND draft = 0");
			if (is_null($title)) return false;
			if (POD::queryExistence("SELECT id FROM {$database['prefix']}Entries WHERE blogid = $blogid AND id <> $entryId AND draft = 0 AND title = '$title' AND category = -1") == true) return false;
		}
	} else {
		$parent = getParentCategoryId($blogid, $category); // Textcube-NG: 정의되지 않은 $categoryId 대신 옮길 분류. 최상위 분류(null)는 넣지 않음 — 원본은 null 로 빈 id 질의·전체 분류 캐시 비우기(r3-8)
		if(!is_null($parent)) array_push($effectedCategories, $parent);
	}

	foreach($targets as $entryId) {
		list($effectedCategoryId, $oldVisibility) = POD::queryRow("SELECT category, visibility FROM {$database['prefix']}Entries WHERE blogid = $blogid AND id = $entryId AND draft = 0");
		$visibility = 	$oldVisibility;
		if ($category < 0) {
			if ($visibility == 1) $visibility = 0;
			if ($visibility == 3) $visibility = 2;
		}

		if (($oldVisibility == 3) && ($visibility != 3))
			syndicateEntry($entryId, 'delete');

		POD::execute("UPDATE {$database['prefix']}Entries SET category = $category , visibility = $visibility WHERE blogid = $blogid AND id = $entryId");
		CacheControl::flushEntry($entryId); // Textcube-NG: 글 화면 캐시(entry-<id>-)도 비움 — 원본은 옮긴 뒤에도 예전 분류로 남았다(r3-8)

		if (!in_array($effectedCategoryId, $effectedCategories)) {
			array_push($effectedCategories, $effectedCategoryId);
			$parent = getParentCategoryId($blogid, $effectedCategoryId);
			if(!is_null($parent)) array_push($effectedCategories, $parent);
		}
	}
	$effected = false;
	foreach($effectedCategories as $effectedCategory) {
		updateEntriesOfCategory($blogid, $effectedCategory);
		$effected = true;
	}

	if(updateEntriesOfCategory($blogid, $category)) {
		if ($effected) {
			clearFeed();
			CacheControl::flushDBCache('comment');
			CacheControl::flushDBCache('trackback');
		}
		return true;
	}
	return false;
}

function changeAuthorOfEntries($blogid, $entries, $userid) {
	global $database;
	requireModel("blog.feed");

	$targets = array_unique(preg_split('/,/', $entries, -1, PREG_SPLIT_NO_EMPTY));
	foreach($targets as $entryId) {
		POD::execute("UPDATE {$database['prefix']}Entries SET userid = $userid WHERE blogid = $blogid AND id = $entryId");
	}
	clearFeed();
	CacheControl::flushAuthor();
	return true;
}

function setEntryVisibility($id, $visibility) {
	global $database;
	requireModel("blog.feed");
	requireModel("blog.category");
	requireComponent('Needlworks.Cache.PageCache');
	$blogid = getBlogId();
	if (($visibility < 0) || ($visibility > 3))
		return false;
	list($oldVisibility, $category) = POD::queryRow("SELECT visibility, category FROM {$database['prefix']}Entries WHERE blogid = $blogid AND id = $id AND draft = 0");

	if ($category < 0) {
		if ($visibility == 1) $visibility = 0;
		if ($visibility == 3) $visibility = 2;
	}

	if ($oldVisibility === null)
		return false;
	if ($visibility == $oldVisibility)
		return true;

	if ($oldVisibility == 3)
		syndicateEntry($id, 'delete');
	else if ($visibility == 3) {
		if (!syndicateEntry($id, 'create')) {
			POD::query("UPDATE {$database['prefix']}Entries
				SET visibility = $oldVisibility,
					modified = UNIX_TIMESTAMP()
				WHERE blogid = $blogid AND id = $id");
			return false;
		}
	}

	$result = POD::queryCount("UPDATE {$database['prefix']}Entries
		SET visibility = $visibility,
			modified = UNIX_TIMESTAMP()
		WHERE blogid = $blogid AND id = $id");
	if (!$result)		// Error.
		return false;
	if ($result == 0)	// Not changed.
		return true;

	if ($category >= 0) {
		if ((($oldVisibility >= 2) && ($visibility < 2)) || (($oldVisibility < 2) && ($visibility >= 2)))
			clearFeed();
		if ((($oldVisibility == 3) && ($visibility <= 2)) || (($oldVisibility <= 2) && ($visibility == 3)))
			clearFeed();
		if ($category > 0)
			updateCategoryByEntryId($blogid, $id, 'update',$parameters = array('visibility' => array($oldVisibility, $visibility)));
//			updateEntriesOfCategory($blogid, $category);
	}
	CacheControl::flushEntry($id);
	CacheControl::flushDBCache('entry');
	CacheControl::flushDBCache('comment');
	CacheControl::flushDBCache('trackback');
	CacheControl::flushTagPage();	// Textcube-NG: 공개 여부가 바뀌면 태그 페이지(tagPage) 캐시도 비운다(v3.136)
	fireEvent('ChangeVisibility', $visibility, $id);
	return true;
}

function protectEntry($id, $password) {
	global $database;
	$password = POD::escapeString($password);
	$result = POD::queryCount("UPDATE {$database['prefix']}Entries SET password = '$password', modified = UNIX_TIMESTAMP() WHERE blogid = ".getBlogId()." AND id = $id AND visibility = 1");
	if($result > 0) {
		CacheControl::flushEntry($id);
		CacheControl::flushDBCache('entry');
		CacheControl::flushDBCache('comment');
		CacheControl::flushDBCache('trackback');
		return true;
	} else return false;
}

function syndicateEntry($id, $mode) {
	$context = Model_Context::getInstance();
	$pool = DBModel::getInstance();

	$pool->reset('XMLRPCPingSettings');
	$pool->setQualifier('blogid','equals',$context->getProperty('blog.id'));
	$sites = $pool->getAll('url,pingtype');

	$entry = getEntry($context->getProperty('blog.id'), $id);
	if (is_null($entry)) return false;

	if(!empty($sites)) {
		foreach ($sites as $site) {
			$rpc = new XMLRPC();
			$rpc->url = $site['url'];
			// [보안] Textcube-NG: 발행 알림 주소는 공인 IPv4 80/443 만(기본). $service['pingPublicOnly'] === false 이면 원본처럼 제한 없음(r3-7, 결정 446f)
			$rpc->publicOnly = !(isset($GLOBALS['service']['pingPublicOnly']) && $GLOBALS['service']['pingPublicOnly'] === false);
			$result[$site['url']] = $rpc->call($context->getProperty('blog.title'), $context->getProperty('uri.default'));
		}
	}
	if($mode == 'create') {
		fireEvent('CreatePostSyndicate', $id, $entry);
	} else if($mode == 'modify') {
		fireEvent('ModifyPostSyndicate', $id, $entry);
	} else if($mode == 'delete') {
		fireEvent('DeletePostSyndicate', $id, $entry);
	}
	return true;
}

function publishEntries() {
	global $database;
	$blogid = getBlogId();
	$closestReservedTime = Setting::getBlogSettingGlobal('closestReservedPostTime',INT_MAX);
	if($closestReservedTime < Timestamp::getUNIXtime()) {
		$entries = POD::queryAll("SELECT id, visibility, category
			FROM {$database['prefix']}Entries
			WHERE blogid = $blogid AND draft = 0 AND visibility < 0 AND published < UNIX_TIMESTAMP()");
		if (count($entries) == 0)
			return;
		foreach ($entries as $entry) {
			$result = POD::query("UPDATE {$database['prefix']}Entries
				SET visibility = 0
				WHERE blogid = $blogid AND id = {$entry['id']} AND draft = 0");
			if ($entry['visibility'] == -3) {
				if ($result && setEntryVisibility($entry['id'], 2)) {
					$updatedEntry = getEntry($blogid, $entry['id']);
					if (!is_null($updatedEntry)) {
						fireEvent('UpdatePost', $entry['id'], $updatedEntry);
						setEntryVisibility($entry['id'], 3);
					}
				}
			} else {
				if ($result) {
					setEntryVisibility($entry['id'], abs($entry['visibility']));
					$updatedEntry = getEntry($blogid, $entry['id']);
					if (!is_null($updatedEntry)) {
						fireEvent('UpdatePost', $entry['id'], $updatedEntry);
					}
				}
			}
		}
		$newClosestTime = POD::queryCell("SELECT min(published)
			FROM {$database['prefix']}Entries
			WHERE blogid = $blogid AND draft = 0 AND visibility < 0 AND published > UNIX_TIMESTAMP()");
		if(!empty($newClosestTime)) Setting::setBlogSettingGlobal('closestReservedPostTime',$newClosestTime);
		else Setting::setBlogSettingGlobal('closestReservedPostTime',INT_MAX);
	}
}

function getTagsWithEntryString($entryTag) {
	global $database;
	$tags = explode(',', $entryTag);

	$ret = array();

	foreach ($tags as $tag) {
		$tag = UTF8::lessenAsEncoding($tag, 255, '');
		$tag = str_replace('&quot;', '"', $tag);
		$tag = str_replace('&#39;', '\'', $tag);
		$tag = preg_replace('/ +/', ' ', $tag);
		$tag = preg_replace('/[\x00-\x1f]|[\x7f]/', '', $tag);
		$tag = preg_replace('/^(-|\s)+/', '', $tag);
		$tag = preg_replace('/(-|\s)+$/', '', $tag);
		$tag = trim($tag);

		array_push($ret, $tag);
	}

	return $ret;
}

function getEntryVisibilityName($visibility) {
	switch (abs($visibility)) {
		case 0:
			return _text('비공개');
		case 1:
			return _text('보호');
		case 2:
			return _text('공개');
		case 3:default:
			return _text('발행');
	}
}

function getSloganById($blogid, $id) {
	global $database;
	$result = POD::queryCell("SELECT slogan FROM {$database['prefix']}Entries WHERE blogid = $blogid AND id = $id");
	if (is_null($result))
		return false;
	else
		return $result;
}

function getEntryIdBySlogan($blogid, $slogan) {
	global $database;
	$result = POD::queryCell("SELECT id
		FROM {$database['prefix']}Entries
		WHERE blogid = $blogid
			AND slogan = '".POD::escapeString($slogan)."'");
	if(!$result) return false;
	else return $result;
}

function setEntryStar($entryId, $mark) {
	global $database;
	$result = POD::query("UPDATE {$database['prefix']}Entries
		SET starred = ".$mark."
		WHERE blogid = ".getBlogId()." AND id = ".$entryId);
	if(!$result) return false;
	else return true;
}

function getEntriesByTagId($blogid, $tagId) {
	global $database;

	return POD::queryAll('SELECT e.blogid, e.userid, e.id, e.title, e.comments, e.slogan, e.published FROM '.$database['prefix'].'Entries e LEFT JOIN '.$database['prefix'].'TagRelations t ON e.id = t.entry AND e.blogid = t.blogid WHERE e.blogid = '.$blogid.' AND t.tag = '.$tagId);
}
?>
