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

// Textcube-NG: r3-27 R-427 — suggestLocatives() 가 비공개 분류(하위 분류 포함)의 글 지역을 손님에게 내지 않도록 getPrivateCategoryExclusionQuery() 를 쓴다. 소유자는 getEntries() 그대로이고, getEntries() 는 비공개 분류 제외가 없어 손님 경로는 getLocatives() 가 직접 조회한다(draft 0·visibility > 0 은 getEntries() 와 같음)
requireModel('blog.category');

function getLocatives($blogid) {
	// Textcube-NG: r3-27 R-427 — 손님(비소유자)은 비공개 분류의 글(/location 목록 누출 경로)을 뺀다. 별칭 e 는 getPrivateCategoryExclusionQuery() 의 e.category 와 맞춘 것. 반환 열은 소유자와 같음
	if (doesHaveOwnership())
		return getEntries($blogid, 'id, userid, title, slogan, location', 'length(location) > 1 AND category > -1', 'location');
	global $database;
	$blogid = intval($blogid);
	return POD::queryAll('SELECT e.id, e.userid, e.title, e.slogan, e.location FROM '.$database['prefix'].'Entries e WHERE e.blogid = '.$blogid.' AND e.draft = 0 AND e.visibility > 0'.getPrivateCategoryExclusionQuery($blogid).' AND length(e.location) > 1 AND e.category > -1 ORDER BY e.location');
}

function suggestLocatives($blogid, $filter) {
	global $database;
	$locatives = array();
	/* Textcube-NG: LIKE 값을 큰따옴표로 감싸던 것을 작은따옴표로(v3.135). SQLite·PostgreSQL 의 POD::escapeString() 은 작은따옴표만
	   escape 하므로 '"' 로 조건을 벗어날 수 있었다(인증 없는 /locationSuggest 로 다른 블로그·비공개 글의 지역 노출).
	   보이는 글은 getLocatives() 와 같은 규칙: draft 0, 방문자는 공개 글(visibility > 0) 중 비공개 분류(하위 분류 포함)의 글을 뺀 것(getPrivateCategoryExclusionQuery, r3-27 R-427), 편집자가 아닌 필자는 getEntries() 처럼 자기 글 또는 공개 글. getLocatives() 의 category > -1 조건은 여기에 없다(r3-29 R-499) */
	$visibility = doesHaveOwnership() ? '' : ' AND visibility > 0'.getPrivateCategoryExclusionQuery($blogid);
	$visibility .= (doesHaveOwnership() && !Acl::check('group.editors')) ? ' AND (userid = '.getUserId().' OR visibility > 0)' : '';
	$result = POD::queryAll('SELECT DISTINCT location, COUNT(*) cnt FROM '.$database['prefix'].'Entries e WHERE blogid = '.$blogid.' AND draft = 0'.$visibility.' AND location LIKE \''.POD::escapeString($filter).'%\' GROUP BY location ORDER BY cnt DESC LIMIT 10');
	if ($result) {
		foreach ($result as $locative) {
			$locatives[] = $locative[0];
		}
	}
	return $locatives;
}
?>
