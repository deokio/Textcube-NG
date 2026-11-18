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

function getTagId($blogid, $name) {
	global $database;
	$query = DBModel::getInstance();
	$query->reset('Tags');
	$query->setQualifier('name','equals',$name,true);
	return $query->getCell('id');
}

function getTagById($blogid, $id) {
	$query = DBModel::getInstance();
	$query->reset('Tags');
	$query->setQualifier('id','equals',$id);
	return $query->getCell('name');
}

function getTags($blogid, $entry) {
	global $database;
	/* Textcube-NG: 방문자 분기는 같은 블로그(e.blogid = r.blogid)의 공개 글 본문 행(e.draft = 0)만 — Entries 의 키는 (blogid, id) 이고
	   자동 저장 행(draft = 1)은 편집 화면의 공개 설정 값을 가지므로, 빠지면 비공개 글의 태그가 보인다(v3.135) */
	$tags = array();
	if (doesHaveOwnership())
		$result = POD::queryAll("SELECT t.* FROM {$database['prefix']}Tags t
			INNER JOIN {$database['prefix']}TagRelations r ON r.blogid = $blogid AND r.tag = t.id AND r.entry = $entry AND r.tag = t.id
			GROUP BY r.tag, t.id, t.name 
			ORDER BY t.name");
	else
		$result = POD::queryAll("SELECT t.* FROM {$database['prefix']}Tags t
			INNER JOIN {$database['prefix']}TagRelations r ON r.blogid = $blogid AND r.entry = $entry AND r.tag = t.id
			INNER JOIN {$database['prefix']}Entries e ON e.blogid = r.blogid AND e.id = r.entry AND e.draft = 0 AND e.visibility > 0
			GROUP BY r.tag, t.id, t.name
			ORDER BY t.name");
	if(!empty($result)) {
		$tags = $result;
	}
	return $tags;
}

// Textcube-NG: r3-27 R-427 — 손님·구성원 공개 질의가 비공개 분류(하위 분류 포함)의 글 태그를 내지 않도록 getPrivateCategoryExclusionQuery() 를 쓴다(blog.entry.php 의 글 목록과 같은 규칙). 편집자(group.editors)·글 주인 분기는 그대로
requireModel('blog.category');

function getRandomTags($blogid) {
	global $database, $skinSetting;
	/* Textcube-NG: 방문자 분기는 같은 블로그(e.blogid = r.blogid)의 공개 글 본문 행(e.draft = 0)만 — Entries 의 키는 (blogid, id) 이고
	   자동 저장 행(draft = 1)은 편집 화면의 공개 설정 값을 가지므로, 빠지면 비공개 글의 태그가 보인다(v3.135) */
	$tags = array();
	$aux = ($skinSetting['tagsOnTagbox'] == - 1) ? '' : "LIMIT {$skinSetting['tagsOnTagbox']}";
	// Textcube-NG: 글관리 권한(group.editors)이 없는 구성원은 남의 비공개 글 태그를 보지 않는다 — 전체 태그(Entries 조인 없음)는
	// group.editors 만, 그 밖의 구성원은 자기 글과 비공개가 아닌 글(원본은 구성원 모두 전체 태그)(v3.145)
	$visibility = (doesHaveOwnership() && !Acl::check('group.editors')) ? 'AND (e.userid = '.getUserId().' OR e.visibility > 0)' : 'AND e.visibility > 0'.getPrivateCategoryExclusionQuery($blogid);
	if ($skinSetting['tagboxAlign'] == 1) { // order by count
		if (doesHaveOwnership() && Acl::check('group.editors'))
			$tags = POD::queryAll("SELECT t.name, count(*) AS cnt, t.id FROM {$database['prefix']}Tags t 
				INNER JOIN {$database['prefix']}TagRelations r ON r.blogid = $blogid AND r.tag = t.id
				GROUP BY r.tag, t.name, t.id 
				ORDER BY cnt DESC $aux");
		else
			$tags = POD::queryAll("SELECT t.name, count(*) AS cnt, t.id FROM {$database['prefix']}Tags t,
				{$database['prefix']}TagRelations r, 
				{$database['prefix']}Entries e 
				WHERE r.entry = e.id AND e.draft = 0 $visibility AND t.id = r.tag AND r.blogid = $blogid AND e.blogid = $blogid 
				GROUP BY r.tag, t.name, t.id
				ORDER BY cnt DESC $aux");
	} else if ($skinSetting['tagboxAlign'] == 2) {  // order by name
		if (doesHaveOwnership() && Acl::check('group.editors'))
			$tags = POD::queryAll("SELECT DISTINCT t.name, count(*) AS cnt, t.id FROM {$database['prefix']}Tags t, 
				{$database['prefix']}TagRelations r 
				WHERE t.id = r.tag AND r.blogid = $blogid 
				GROUP BY r.tag, t.name, t.id
				ORDER BY t.name $aux");
		else
			$tags = POD::queryAll("SELECT DISTINCT t.name, count(*) AS cnt, t.id FROM {$database['prefix']}Tags t, 
				{$database['prefix']}TagRelations r,
				{$database['prefix']}Entries e 
				WHERE r.entry = e.id AND e.draft = 0 $visibility AND t.id = r.tag AND r.blogid = $blogid AND e.blogid = $blogid
				GROUP BY r.tag, t.name, t.id
				ORDER BY t.name $aux");
	} else { // random
		if (doesHaveOwnership() && Acl::check('group.editors'))
			$tags = POD::queryAll("SELECT t.name, count(*) AS cnt, t.id FROM {$database['prefix']}Tags t,
				{$database['prefix']}TagRelations r
				WHERE t.id = r.tag AND r.blogid = $blogid
				GROUP BY r.tag, t.name, t.id 
				ORDER BY RAND() $aux");
		else
			$tags = POD::queryAll("SELECT t.name, count(*) AS cnt, t.id FROM {$database['prefix']}Tags t,
				{$database['prefix']}TagRelations r,
				{$database['prefix']}Entries e
				WHERE r.entry = e.id AND e.draft = 0 $visibility AND t.id = r.tag AND r.blogid = $blogid AND e.blogid = $blogid
				GROUP BY r.tag, t.name, t.id
				ORDER BY RAND() $aux");
	}
	return $tags;
}

/* Textcube-NG: 태그 목록 개수(v3.136, pat.im 1241). 원본 getSiteTags() 는 LIMIT 2000 고정이라 태그가 많은 블로그는
   태그 목록 페이지(/tag)·태그 관리 화면에 일부만 나왔다. 블로그 설정 tagsOnTagPage(기본 2000)로 바꿀 수 있게 하되
   서비스 설정 maxTagsOnTagPage(기본 2000, 서비스 관리자가 1 ~ 100000 으로 지정)를 넘지 못하게 한다(큰 값으로 인한 부하 방지).
   읽을 때도 다시 제한하므로 DB 값이 상한보다 크거나 상한을 나중에 낮춰도 넘지 않는다 */
function getMaxTagsOnTagPage() {
	$max = Setting::getServiceSettingGlobal('maxTagsOnTagPage', 2000);
	if (!is_numeric($max))
		return 2000;
	return max(1, min(100000, intval($max)));
}

function getTagsOnTagPage($blogid) {
	$count = Setting::getBlogSettingGlobal('tagsOnTagPage', 2000, $blogid);
	if (!is_numeric($count))
		$count = 2000;
	return max(1, min(getMaxTagsOnTagPage(), intval($count)));
}

function setTagsOnTagPage($blogid, $count) {
	if (!is_numeric($count) || intval($count) != $count)
		return false;
	$count = intval($count);
	if ($count < 1 || $count > getMaxTagsOnTagPage())
		return false;
	return (bool)Setting::setBlogSettingGlobal('tagsOnTagPage', $count, $blogid);
}

function getSiteTags($blogid) {
	global $database;
	/* Textcube-NG: 방문자 분기는 같은 블로그(e.blogid = r.blogid)의 공개 글 본문 행(e.draft = 0)만 — Entries 의 키는 (blogid, id) 이고
	   자동 저장 행(draft = 1)은 편집 화면의 공개 설정 값을 가지므로, 빠지면 비공개 글의 태그가 보인다(v3.135) */
	/* Textcube-NG: 개수 상한은 getTagsOnTagPage()(정수, 1 ~ 서비스 상한). 원본은 LIMIT 2000 고정(v3.136) */
	$tagsOnTagPage = getTagsOnTagPage($blogid);
	// Textcube-NG: 글관리 권한(group.editors)이 없는 구성원은 남의 비공개 글 태그를 보지 않는다 — 전체 태그(Entries 조인 없음)는
	// group.editors 만, 그 밖의 구성원은 자기 글과 비공개가 아닌 글(원본은 구성원 모두 전체 태그)(v3.145)
	$visibility = (doesHaveOwnership() && !Acl::check('group.editors')) ? 'AND (e.userid = '.getUserId().' OR e.visibility > 0)' : 'AND e.visibility > 0'.getPrivateCategoryExclusionQuery($blogid);
	if (doesHaveOwnership() && Acl::check('group.editors'))
		$names = POD::queryAll("SELECT t.id, name FROM {$database['prefix']}Tags t, 
			{$database['prefix']}TagRelations r 
			WHERE t.id = r.tag AND r.blogid = $blogid 
			GROUP BY r.tag, t.id, name
			ORDER BY t.name ASC
			LIMIT $tagsOnTagPage");
	else
		$names = POD::queryAll("SELECT t.id, t.name FROM {$database['prefix']}Tags t, 
			{$database['prefix']}TagRelations r,
			{$database['prefix']}Entries e
			WHERE r.entry = e.id AND e.blogid = r.blogid AND e.draft = 0 $visibility AND t.id = r.tag AND r.blogid = $blogid 
			GROUP BY r.tag, t.id, t.name
			ORDER BY t.name ASC
			LIMIT $tagsOnTagPage");
	if(!empty($names)) return $names;
	else $names = array();
	return $names;
}

function getTagFrequencyRange() {
	global $database;
	/* Textcube-NG: 방문자 분기는 같은 블로그(e.blogid = r.blogid)의 공개 글 본문 행(e.draft = 0)만 — Entries 의 키는 (blogid, id) 이고
	   자동 저장 행(draft = 1)은 편집 화면의 공개 설정 값을 가지므로, 빠지면 비공개 글의 태그가 보인다(v3.135) */
	$blogid = getBlogId();
	$max = $min = 0;
	// Textcube-NG: 글관리 권한(group.editors)이 없는 구성원은 남의 비공개 글 태그를 보지 않는다 — 전체 태그(Entries 조인 없음)는
	// group.editors 만, 그 밖의 구성원은 자기 글과 비공개가 아닌 글(원본은 구성원 모두 전체 태그)(v3.145)
	$visibility = (doesHaveOwnership() && !Acl::check('group.editors')) ? 'AND (e.userid = '.getUserId().' OR e.visibility > 0)' : 'AND e.visibility > 0'.getPrivateCategoryExclusionQuery($blogid);
	if (doesHaveOwnership() && Acl::check('group.editors'))
		$max = POD::queryCell("SELECT count(r.entry) AS cnt FROM {$database['prefix']}TagRelations r 
			WHERE r.blogid = $blogid 
			GROUP BY r.tag
			ORDER BY cnt 
			DESC LIMIT 1");
	else
		$max = POD::queryCell("SELECT count(r.entry) AS cnt FROM {$database['prefix']}TagRelations r
			INNER JOIN {$database['prefix']}Entries e ON r.blogid = e.blogid AND e.draft = 0 $visibility AND r.entry = e.id
			WHERE r.blogid = $blogid 
			GROUP BY r.tag 
			ORDER BY cnt 
			DESC LIMIT 1");
/*	if (doesHaveOwnership())
		$min = POD::queryCell("SELECT count(r.entry) cnt FROM {$database['prefix']}TagRelations r 
			WHERE r.blogid = $blogid 
			GROUP BY r.tag 
			ORDER BY cnt 
			LIMIT 1");
	else
		$min = POD::queryCell("SELECT count(r.entry) cnt FROM {$database['prefix']}TagRelations r
			INNER JOIN {$database['prefix']}Entries e 
				ON e.blogid = r.blogid AND e.visibility > 0 AND r.entry = e.id
			WHERE r.blogid = $blogid 
			GROUP BY r.tag 
			ORDER BY cnt 
			LIMIT 1");*/
	$max = ($max === null ? 0 : $max);
	//$min = ($min === null ? 0 : $min);
	$min = 1;
	return array($max, $min);
}

function getTagFrequency($tag, $max, $min) {
	global $database;
	/* Textcube-NG: 방문자 분기는 같은 블로그(e.blogid = r.blogid)의 공개 글 본문 행(e.draft = 0)만 — Entries 의 키는 (blogid, id) 이고
	   자동 저장 행(draft = 1)은 편집 화면의 공개 설정 값을 가지므로, 빠지면 비공개 글의 태그가 보인다(v3.135) */
	$blogid = getBlogId();
	if (is_array($tag) && array_key_exists('cnt', $tag)) $count = $tag['cnt'];
	else {
		if (!is_array($tag)) {
			$tag = array('name' => $tag);
		} 
		// Textcube-NG: 글관리 권한(group.editors)이 없는 구성원은 남의 비공개 글 태그를 보지 않는다 — 전체 태그(Entries 조인 없음)는
		// group.editors 만, 그 밖의 구성원은 자기 글과 비공개가 아닌 글(원본은 구성원 모두 전체 태그)(v3.145)
		$visibility = (doesHaveOwnership() && !Acl::check('group.editors')) ? 'AND (e.userid = '.getUserId().' OR e.visibility > 0)' : 'AND e.visibility > 0'.getPrivateCategoryExclusionQuery($blogid);
		if (doesHaveOwnership() && Acl::check('group.editors'))
			$count = POD::queryCell("SELECT count(*) FROM {$database['prefix']}Tags t
				INNER JOIN {$database['prefix']}TagRelations r ON r.tag = t.id AND r.blogid = $blogid
				WHERE t.name = '" . POD::escapeString($tag['name']) . "'");
		else
			$count = POD::queryCell("SELECT count(*) FROM {$database['prefix']}Tags t
				INNER JOIN {$database['prefix']}TagRelations r ON r.tag = t.id AND r.blogid = $blogid 
				INNER JOIN {$database['prefix']}Entries e ON e.blogid = r.blogid AND e.id = r.entry AND e.draft = 0 $visibility 
				WHERE t.name = '" . POD::escapeString($tag['name']) . "'");
	}
	$dist = $max / 3;
	if ($count == $min)
		return 5;
	else if ($count == $max)
		return 1;
	else if ($count >= $min + ($dist * 2))
		return 2;
	else if ($count >= $min + $dist)
		return 3;
	else
		return 4;
}

function suggestLocalTags($blogid, $filter) {
	global $database;
	$tags = array();
	/* Textcube-NG: $filter 를 SQL 에 그대로 붙이던 것을 막는다(인증 없는 /suggest 의 SQL 삽입, v3.135).
	   편집기 태그 입력(resources/script/generaltag.js tcTagFunction_showLocalSuggestionWithoutQuery)이 보내는
	   'name like "<입력값>%"' 형식만 받아(첫 '"' 는 '\"' 로 옴) 입력값을 escape 한 LIKE 조건으로 다시 만든다.
	   기본값 '1' 은 조건 없음, 그 밖의 형식은 빈 목록. 방문자는 같은 블로그의 공개 글(draft 0) 태그만 — getSiteTags() 와 같은 규칙.
	   ON 없는 INNER JOIN(PostgreSQL 오류)은 쉼표 조인으로 */
	if ($filter === '1') {
		$condition = '';
	} else if (preg_match('/^name like "(.*)%"$/s', $filter, $match)) {
		$condition = "AND t.name LIKE '" . POD::escapeString(str_replace('\\"', '"', $match[1])) . "%'";
	} else {
		return $tags;
	}
	// Textcube-NG: 글관리 권한(group.editors)이 없는 구성원은 남의 비공개 글 태그를 보지 않는다 — 전체 태그(Entries 조인 없음)는
	// group.editors 만, 그 밖의 구성원은 자기 글과 비공개가 아닌 글(원본은 구성원 모두 전체 태그)(v3.145)
	$visibility = (doesHaveOwnership() && !Acl::check('group.editors')) ? 'AND (e.userid = '.getUserId().' OR e.visibility > 0)' : 'AND e.visibility > 0'.getPrivateCategoryExclusionQuery($blogid);
	if (doesHaveOwnership() && Acl::check('group.editors'))
		$result = POD::queryAll("SELECT DISTINCT t.name, count(*) AS cnt
			FROM {$database['prefix']}Tags t,
				{$database['prefix']}TagRelations r
			WHERE t.id = r.tag
				AND r.blogid = $blogid
				$condition
			GROUP BY r.tag, t.name
			ORDER BY cnt
			DESC LIMIT 10");
	else
		$result = POD::queryAll("SELECT DISTINCT t.name, count(*) AS cnt
			FROM {$database['prefix']}Tags t,
				{$database['prefix']}TagRelations r,
				{$database['prefix']}Entries e
			WHERE t.id = r.tag
				AND r.blogid = $blogid
				AND e.blogid = r.blogid AND e.id = r.entry AND e.draft = 0 $visibility
				$condition
			GROUP BY r.tag, t.name
			ORDER BY cnt
			DESC LIMIT 10");
	if ($result) {
		foreach($result as $tag)
			array_push($tags, $tag[0]);
	}
	return $tags;
}

function deleteTagById($blogid, $id) {
	global $database;

	/// delete relation
	$result = POD::execute('DELETE FROM '.$database['prefix'].'TagRelations WHERE blogid = '.$blogid.' AND tag = '.$id);
	if (!$result) {
		return false;
	}
	/* Textcube-NG: 지운 태그의 목록·피드 캐시와 태그 페이지(tagPage) 캐시를 비운다(v3.136). 원본은 태그 관리 화면에서 지워도
	   /tag 캐시에 남았다 */
	CacheControl::flushTag($id);
	
	$count = POD::queryCell('SELECT COUNT(*) FROM '.$database['prefix'].'TagRelations WHERE tag = '.$id);
	if (intval($count) == 0) {
		POD::execute('DELETE FROM '.$database['prefix'].'Tags WHERE id = '.$id);
	}

	return true;
}


function addTag($blogid, $name) {
	$tagId = getTagId($blogid,$name);
	if(empty($tagId)) {
		$query = DBModel::getInstance();
		$query->reset("Tags");
		$insertId = Tag::_getMaxId()+1;
		$query->setAttribute('id',$insertId);
		$query->setAttribute('name',$name,true);
		if($query->insert()) return $insertId;
		else return false;
	} else return $tagId;
}

function renameTag($blogid, $id, $name) {
	// 1. If tag with new name already exists, skip the tag creation process.
	// 2. If tag with new name does not exist in this service, create new tag.
	// 3. Modify the tag relation information
	// 4. If older tag is not used anymore, drop the tag.
	global $database;
	/* Textcube-NG: 이름 변경 결함 수정(v3.136)
	   - 새 이름이 옛 태그와 같은 태그로 풀리면(같은 이름, MySQL 의 대소문자 무시 비교 등) 그대로 둔다. 원본은 관계를 그대로 둔 채
	     deleteTagById() 로 그 태그의 관계를 모두 지웠다.
	   - 두 태그를 모두 가진 글은 옛 관계를 먼저 지운다. 원본은 UPDATE 가 기본 키(blogid, tag, entry) 중복으로 실패한 뒤
	     deleteTagById() 가 옛 태그의 관계를 모두 지워, 다른 글에서도 태그가 사라졌다.
	   - 새 태그를 만들지 못하면 false. 원본은 그래도 옛 태그의 관계를 지웠다.
	   Tags 는 모든 블로그가 함께 쓰므로 태그 이름 자체는 바꾸지 않는다(원본과 같음). 고치는 테이블의 서브쿼리를
	   DELETE 에 쓰지 않는다(MySQL 1093) */
	$blogid = intval($blogid);
	$oldTagId = intval($id);
	// Textcube-NG: 글 저장 경로(getTagsWithEntryString)와 같은 순서로 이름을 정리한다 — 길이 상한, &quot;·&#39; 풀기, 공백 합치기,
	// 제어 문자 빼기, 앞뒤 '-'·공백 빼기. 정리 뒤 빈 이름이면 false(글 저장 경로도 빈 태그는 넣지 않음)(r3-8)
	$name = UTF8::lessenAsEncoding($name, 255, '');
	$name = str_replace('&quot;', '"', $name);
	$name = str_replace('&#39;', '\'', $name);
	$name = preg_replace('/ +/', ' ', $name);
	$name = preg_replace('/[\x00-\x1f]|[\x7f]/', '', $name);
	$name = preg_replace('/^(-|\s)+/', '', $name);
	$name = preg_replace('/(-|\s)+$/', '', $name);
	$name = trim($name);
	if ($name === '' || strpos($name, ',') !== false)	// Textcube-NG: 쉼표가 든 이름도 false — 글 저장 경로가 쉼표로 태그를 나누므로 그 글을 다시 저장하면 쪼개진다(r3-15)
		return false;
	$newTagId = addTag($blogid, $name);
	if (empty($newTagId))
		return false;
	if (intval($newTagId) == $oldTagId)
		return $newTagId;
	$entries = POD::queryColumn('SELECT entry FROM '.$database['prefix'].'TagRelations WHERE blogid = '.$blogid.' AND tag = '.intval($newTagId));
	if (!empty($entries)) {
		$query = DBModel::getInstance();
		$query->reset('TagRelations');
		$query->setQualifier('blogid','equals',$blogid);
		$query->setQualifier('tag','equals',$oldTagId);
		$query->setQualifier('entry','hasoneof',array_map('intval', $entries));
		$query->delete();
	}
	$query = DBModel::getInstance();
	$query->reset("TagRelations");
	$query->setAttribute('tag',$newTagId);
	$query->setQualifier('blogid','equals',$blogid);
	$query->setQualifier('tag','equals',$oldTagId);
	$query->update();
	deleteTagById($blogid, $oldTagId);
	CacheControl::flushTag($newTagId);
	return $newTagId;
}
?>
