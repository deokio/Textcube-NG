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

// global variables for Category information cache
global $__gCacheCategoryTree, $__gCacheCategoryRaw, $__gCacheCategoryVisibilityList, $__gCategoryError;

$__gCacheCategoryTree = array();
$__gCacheCategoryRaw = array();
$__gCacheCategoryVisibilityList = array();
$__gCategoryError = '';	// Textcube-NG: R-592 — 분류 만들기·옮기기·지우기가 거부한 까닭(depth·label·duplicate·self·descendant·children·invalid). 관리 화면이 getCategoryErrorMessage() 로 문구를 고른다

function getCategoryId($blogid, $name, $parentName = false) {
	requireComponent('Needlworks.Cache.PageCache');

	global $__gCacheCategoryRaw;

	if(empty($__gCacheCategoryRaw)) getCategories($blogid, 'raw'); //To cache category information.
	if($result = MMCache::queryRow($__gCacheCategoryRaw,'name',$name)) {
		if($parentName == false) {
			return $result['id'];
		} else {
			$parent = MMCache::queryRow($__gCacheCategoryRaw,'name',$parentName);
			if($parent['id'] == $result['parent']) return $result['id'];
		}
	}
	return null;
}

function getCategoryIdByLabel($blogid, $label) {
	requireComponent('Needlworks.Cache.PageCache');

	global $__gCacheCategoryRaw;
	if (empty($label))
		return 0;

	if(empty($__gCacheCategoryRaw)) getCategories($blogid, 'raw'); //To cache category information.
	if($result = MMCache::queryRow($__gCacheCategoryRaw,'label',$label))
		return $result['id'];
	else return null;
}

function getCategoryNameById($blogid, $id) {
	requireComponent('Needlworks.Cache.PageCache');

	global $__gCacheCategoryRaw;

	if(empty($__gCacheCategoryRaw)) getCategories($blogid, 'raw'); //To cache category information.
	if($result = MMCache::queryRow($__gCacheCategoryRaw,'id',$id))
		return $result['name'];
	else return _text('전체');
}

function getCategoryBodyIdById($blogid, $id) {
	requireComponent('Needlworks.Cache.PageCache');

	global $__gCacheCategoryRaw;

	if(empty($__gCacheCategoryRaw)) getCategories($blogid, 'raw'); //To cache category information.
	$result = MMCache::queryRow($__gCacheCategoryRaw,'id',$id);
	if (($id === 0) || ($result == '') || ($id === null))
		return 'tt-body-category';
	else return $result['bodyid'];
}

function getEntriesCountByCategory($blogid, $id) {
	requireComponent('Needlworks.Cache.PageCache');

	global $__gCacheCategoryRaw;

	if(empty($__gCacheCategoryRaw)) getCategories($blogid, 'raw'); //To cache category information.
	$result = MMCache::queryRow($__gCacheCategoryRaw,'id',$id);
	if (($id === 0) || ($result == '') || ($id === null)) {
		return 0;
	} else {
		if(doesHaveOwnership() && Acl::check('group.editors')) return $result['entriesinlogin'];
		else return $result['entries'];
	}
}

function getCategoryLabelById($blogid, $id) {
	requireComponent('Needlworks.Cache.PageCache');

	global $__gCacheCategoryRaw;

	if ($id === null)
		return '';

	if(empty($__gCacheCategoryRaw)) getCategories($blogid, 'raw'); //To cache category information.
	if($result = MMCache::queryRow($__gCacheCategoryRaw,'id',$id))
		return $result['label'];
	else return _text('분류 전체보기');
}

function getCategoryLinkById($blogid, $id) {
	if (($id === null) || ($id === 0))
		return '';
	// Textcube-NG: R-592 — 깊이와 관계없이 최상위부터 자기까지의 이름을 '/' 로 잇는다(2단 결과는 원본과 같음)
	$parts = array();
	foreach (array_reverse(getCategoryAncestorIds($blogid, $id)) as $ancestorId)
		array_push($parts, rawurlencode(htmlspecialchars(escapeURL(getCategoryNameById($blogid, $ancestorId)))));
	array_push($parts, rawurlencode(htmlspecialchars(escapeURL(getCategoryNameById($blogid, $id)))));
	return implode('/', $parts);
}

function getCategory($blogid, $id = null, $field = null) {
	requireComponent('Needlworks.Cache.PageCache');

	global $__gCacheCategoryRaw;

	if ($id === null)
		return '';

	if(empty($__gCacheCategoryRaw)) getCategories($blogid, 'raw'); //To cache category information.
	if($result = MMCache::queryRow($__gCacheCategoryRaw,'id',$id)) {
		if(!empty($field) && isset($result[$field])) {
			return $result[$field];
		} else return $result;
	} else return false;
}

function getCategories($blogid, $format = 'tree') {
	global $database;
	global $__gCacheCategoryTree, $__gCacheCategoryRaw;
	if($format == 'tree' && !empty($__gCacheCategoryTree))
		return $__gCacheCategoryTree;
	else if($format == 'raw' && !empty($__gCacheCategoryRaw))
		return $__gCacheCategoryRaw;
	$rows = POD::queryAllWithCache("SELECT *
			FROM {$database['prefix']}Categories
			WHERE blogid = $blogid
				AND id >= 0
			ORDER BY parent, priority");
	$categories = array();
	if( empty($rows) ) {
		$rows = array();
	}
	if($format == 'raw') {
		foreach ($rows as $category) {
			$categories[$category['id']] = $category;
		}
		$__gCacheCategoryRaw = $categories;
		return $categories;
	}
	// Textcube-NG: R-592 글갈래 다단계 — 행 순서(ORDER BY parent 의 NULL 위치는 DB 마다 다름)에 기대지 않고, 부모별로 묶은 뒤 최상위부터 재귀로 붙인다.
	//   최상위 배열(키 = id, 순서 = priority)과 children 꼴은 원본과 같고, 2단 아래 노드에도 children 이 붙는다(원본은 3단 아래를 버렸다). 부모가 없거나 순환 안의 행은 트리에 넣지 않는다(raw 에는 남음)
	$byParent = array();
	foreach ($rows as $category) {
		$parentKey = ($category['parent'] === null || $category['parent'] === '') ? 'root' : (string)$category['parent'];
		if (!isset($byParent[$parentKey]))
			$byParent[$parentKey] = array();
		array_push($byParent[$parentKey], $category);
	}
	$visited = array();
	if (isset($byParent['root'])) {
		foreach ($byParent['root'] as $category) {
			$visited[(string)$category['id']] = true;
			$category['children'] = __buildCategoryTreeChildren($byParent, $category['id'], $visited, 1);
			$categories[$category['id']] = $category;
		}
	}
	$__gCacheCategoryTree = $categories;
	return $categories;
}

function __buildCategoryTreeChildren(&$byParent, $parentId, &$visited, $level) {
	$children = array();
	if ($level > 64 || !isset($byParent[(string)$parentId]))
		return $children;
	foreach ($byParent[(string)$parentId] as $category) {
		if (isset($visited[(string)$category['id']]))
			continue;
		$visited[(string)$category['id']] = true;
		$category['children'] = __buildCategoryTreeChildren($byParent, $category['id'], $visited, $level + 1);
		array_push($children, $category);
	}
	return $children;
}

function getCategoryVisibilityList($blogid, $mode = 'private') {
	requireComponent('Needlworks.Cache.PageCache');

	global $__gCacheCategoryVisibilityList, $__gCacheCategoryRaw;

	if(!array_key_exists($mode,$__gCacheCategoryVisibilityList)) {
		switch($mode) {
			case 'public':
				$visibility = 2;
				break;
			case 'private':
			default:
				$visibility = 1;
		}
		if(empty($__gCacheCategoryRaw)) getCategories($blogid, 'raw'); //To cache category information.
		if($list = MMCache::queryColumn($__gCacheCategoryRaw,'visibility',$visibility,'id')) {
			$__gCacheCategoryVisibilityList[$mode] = $list;
		} else {
			$__gCacheCategoryVisibilityList[$mode] = array();
		}
		// Textcube-NG: 비공개 분류(visibility 1)의 하위 분류도 비공개 목록에 넣는다(하위 분류 자신의 visibility 가 2 여도). 이 목록·getPrivateCategoryExclusionQuery 를 쓰는 손님용 분류 화면·글 목록·피드·댓글·첨부·SEO 가 그 글을 내지 않는다.
		// 분류 자신의 공개 값(c.visibility)만 보던 글 목록(blog.entry.php)·피드(blog.feed.php)·표지 최신 글(MT_Meta_RecentPS_Default, 여러 블로그 표지는 블로그별 목록) 질의에도 이 목록을 더했다(r3-5)
		if ($visibility == 1 && is_array($__gCacheCategoryRaw)) {
			// Textcube-NG: R-592 — 3단 아래에서 손자가 부모보다 먼저 읽혀도 빠지지 않게, 더 넣을 것이 없을 때까지 되풀이한다(자손 전체)
			do {
				$added = false;
				foreach ($__gCacheCategoryRaw as $row) {
					if (isset($row['parent']) && in_array($row['parent'], $__gCacheCategoryVisibilityList[$mode]) && !in_array($row['id'], $__gCacheCategoryVisibilityList[$mode])) {
						array_push($__gCacheCategoryVisibilityList[$mode], $row['id']);
						$added = true;
					}
				}
			} while ($added);
		}
	}
	return $__gCacheCategoryVisibilityList[$mode];
}

function getPrivateCategoryExclusionQuery($blogid) {
	$exclusionList = getCategoryVisibilityList($blogid, 'private');
	if(empty($exclusionList)) return '';
	return '  AND e.category NOT IN ('.implode(',',$exclusionList).')';
}

function getCategoriesSkin() {
	global $service;
	$setting = getSkinSettings(getBlogId());
	$skin = array('name' => "{$setting['skin']}",
			'url'               => $service['path'] . "/skin/tree/{$setting['tree']}",
			'labelLength'       => $setting['labelLengthOnTree'],
			'showValue'         => $setting['showValueOnTree'],
			'itemColor'         => "{$setting['colorOnTree']}",
			'itemBgColor'       => "{$setting['bgColorOnTree']}",
			'activeItemColor'   => "{$setting['activeColorOnTree']}",
			'activeItemBgColor' => "{$setting['activeBgColorOnTree']}", );
	return $skin;
}

function getParentCategoryId($blogid, $id) {
	global $__gCacheCategoryRaw;

	if(empty($__gCacheCategoryRaw)) getCategories($blogid, 'raw'); //To cache category information.
	if($result = MMCache::queryRow($__gCacheCategoryRaw,'id',$id))
		return $result['parent'];
	return null;
}

function getChildCategoryId($blogid, $id) {
	global $__gCacheCategoryRaw;

	if(empty($__gCacheCategoryRaw)) getCategories($blogid, 'raw'); //To cache category information.
	if($result = MMCache::queryColumn($__gCacheCategoryRaw,'parent',$id,'id'))
		return $result;
	return null;
}

function getNumberChildCategory($id = null) {
	global $database;
	// [보안] $id 를 따옴표 없이 SQL 에 넣으므로 정수로 변환
	$sql = "SELECT * FROM {$database['prefix']}Categories WHERE blogid = ".getBlogId()." AND parent " . ($id === null ? 'IS NULL' : "= " . (int)$id);
	//$result = POD::queryRow($sql);
	return POD::queryCell($sql);
}

function getNumberEntryInCategories($id) {
	global $database;
	// [보안] $id 를 따옴표 없이 SQL 에 넣으므로 정수로 변환
	return POD::queryCell("SELECT COUNT(*) FROM {$database['prefix']}Entries WHERE blogid = ".getBlogId()." AND draft = 0 AND category " . ($id === null ? 'IS NULL' : "= " . (int)$id));
}

function addCategory($blogid, $parent, $name, $id = null, $priority = null) {
	global $database, $__gCategoryError;
	$__gCategoryError = '';

	if (empty($name))
		return false;
	if (!is_null($parent) && !Validator::id($parent))
		return false;
	if(!is_null($id) && !Validator::isInteger($id,0)) {
		return false;
	}
	if($priority !== null && !Validator::isInteger($priority,0)) {
		return false;
	}

	if (!is_null($parent)) {
		// Textcube-NG: R-592 — 부모는 분류 캐시로 확인한다. 깊이 한도(블로그 설정 categoryMaxDepth, 기본 3)를 넘거나 경로(label = 조상 이름 전체/이름)가 255자를 넘으면 만들지 않는다(사용자 결정 ①·⑤)
		$parentRows = __getCategoryRaw($blogid, true);
		if (!isset($parentRows[$parent]))
			return false;
		if (getCategoryDepth($blogid, $parent) + 1 > getCategoryMaxDepth($blogid)) {
			$__gCategoryError = 'depth';
			return false;
		}
		$label = buildCategoryLabel($blogid, $parent, $name);
		if (isCategoryLabelTooLong($label)) {
			$__gCategoryError = 'label';
			return false;
		}
	} else {
		$parent = 'NULL';
		$label = $name;
	}

	$label = POD::escapeString(UTF8::lessenAsEncoding($label, 255));
	$name = POD::escapeString(UTF8::lessenAsEncoding($name, 127));

	if($parent == 'NULL') {
		$parentStr = 'AND parent is null';
	} else {
		$parentStr = "AND parent = $parent";
	}

	$sql = "SELECT count(*) FROM {$database['prefix']}Categories WHERE blogid = $blogid AND name = '$name' $parentStr";

	if (POD::queryCell($sql) > 0) {
		$__gCategoryError = 'duplicate';
		return false;
	}

	if(!is_null($priority)) {
		if(POD::queryExistence("SELECT * FROM {$database['prefix']}Categories WHERE blogid = $blogid AND priority = $priority")) {
			return false;
		} else {
			$newPriority = $priority;
		}
	} else {
		$newPriority = POD::queryCell("SELECT MAX(priority) FROM {$database['prefix']}Categories WHERE blogid = $blogid") + 1;
	}

	// Determine ID.
	if(!is_null($id)) {
		$sql = "SELECT * FROM {$database['prefix']}Categories WHERE blogid = $blogid AND id = $id";
		if(POD::queryExistence($sql)) {
			return false;
		} else {
			$newId = $id;
		}
	} else {
		$newId = POD::queryCell("SELECT MAX(id) FROM {$database['prefix']}Categories WHERE blogid = $blogid") + 1;
	}

	$result = POD::query("INSERT INTO {$database['prefix']}Categories (blogid, id, parent, name, priority, entries, entriesinlogin, label, visibility) VALUES ($blogid, $newId, $parent, '$name', $newPriority, 0, 0, '$label', 2)");

	updateEntriesOfCategory($blogid,$newId);
	return $result ? true : false;
}

function deleteCategory($blogid, $id) {
	global $database, $__gCategoryError;
	$__gCategoryError = '';

	if (!is_numeric($id))
		return false;
	$id = (int)$id;
	// Textcube-NG: R-592 — 0(전체)과 하위 분류가 있는 분류는 지우지 않는다(사용자 결정 ⑧ — 원본은 관리 화면만 단추를 숨기고 서버는 지워 고아 분류가 남았다). 지운 분류의 조상 캐시도 비운다
	if ($id == 0)
		return false;
	if (POD::queryCell("SELECT COUNT(*) FROM {$database['prefix']}Categories WHERE blogid = $blogid AND parent = $id") > 0) {
		$__gCategoryError = 'children';
		return false;
	}
	// Textcube-NG: r4-14 — 글(초안 아님)이 있는 분류도 서버에서 거부한다(관리 화면 getNumberEntryInCategories() 와 같은 조건, 사용자 결정 11-23). 원본은 화면만 단추를 숨겨 POST 로 지우면 글이 없는 분류를 가리켰다(dojoref 2.5)
	if (POD::queryCell("SELECT COUNT(*) FROM {$database['prefix']}Entries WHERE blogid = $blogid AND draft = 0 AND category = $id") > 0) {
		$__gCategoryError = 'entries';
		return false;
	}
	CacheControl::flushCategory($id);
	foreach (getCategoryAncestorIds($blogid, $id) as $ancestorId)
		CacheControl::flushCategory($ancestorId);
	POD::execute("DELETE FROM {$database['prefix']}Categories WHERE blogid = $blogid AND id = $id");
	updateEntriesOfCategory($blogid);
	return true;
}

function modifyCategory($blogid, $id, $name, $bodyid) {
	global $database, $__gCategoryError;
	$__gCategoryError = '';
	requireModel('blog.feed');
	if($id==0) checkRootCategoryExistence($blogid);
	if ((empty($name)) && (empty($bodyid)))
		return false;
	// Textcube-NG: r4-16 — body id 는 스킨 <body id="…">·글 class 에 들어가므로 가져오기(r4-11)와 같은 규칙(영문·숫자·_·- 20자 이하)만 받는다.
	//   형식이 틀리면 body id 는 저장된 값을 그대로 두고 안내한다. 같이 보낸 이름은 저장한다(사용자 결정 11-24 00:4x). 원본은 20자로 자르고 SQL escape 만 해 따옴표·태그가 그대로 출력됐다(dojoref 2.4)
	$bodyidRejected = false;
	if (is_string($bodyid) && $bodyid !== '' && !preg_match('/\A[A-Za-z0-9_-]{1,20}\z/', $bodyid)) {
		$__gCategoryError = 'bodyid';
		if (empty($name))
			return false;
		$bodyidRejected = true;
	}
	// Textcube-NG: R-592 — 경로(label)는 조상 이름 전체 + 새 이름(원본은 부모 이름 하나). 새 이름으로 자기나 자손의 경로가 255자를 넘으면 바꾸지 않는다(사용자 결정 ⑤).
	//   자손의 경로는 아래 updateEntriesOfCategory() 가 블로그 전체를 다시 셀 때 함께 바꾼다
	$label = buildCategoryLabel($blogid, getParentCategoryId($blogid, $id), $name);
	if (isCategoryLabelTooLong($label) || __isCategorySubtreeLabelTooLong($blogid, $id, $label, 0)) {
		$__gCategoryError = 'label';
		return false;
	}
	$label = POD::escapeString(UTF8::lessenAsEncoding($label, 255));
	$name = POD::escapeString(UTF8::lessenAsEncoding($name, 127));
	if ($bodyidRejected)	// Textcube-NG: r4-16 — 이름만 바꾸고 body id 는 저장된 값 그대로
		$bodyid = (string)POD::queryCell("SELECT bodyid FROM {$database['prefix']}Categories WHERE blogid = $blogid AND id = ".(int)$id);
	$bodyid = POD::escapeString(UTF8::lessenAsEncoding($bodyid, 20));
	if(POD::queryExistence("SELECT name
		FROM {$database['prefix']}Categories
		WHERE blogid = $blogid AND name = '".$name."' AND bodyid = '".$bodyid."'"))
		return false;
	$sql = "SELECT *
		FROM {$database['prefix']}Categories
		WHERE blogid = $blogid
			AND id = $id";
	// $sql = "SELECT count(*) FROM {$database['prefix']}Categories WHERE blogid = $blogid AND name='$name' $parentStr";
	if(POD::queryExistence($sql) == false)
		return false;

	$result = POD::query("UPDATE {$database['prefix']}Categories
		SET name = '$name',
			label = '$label',
			bodyid = '$bodyid'
		WHERE blogid = $blogid
			AND id = $id");
	if ($result)
		clearFeed();
	updateEntriesOfCategory($blogid,$id);
	CacheControl::flushCategory($id);
	foreach (getCategoryDescendantIds($blogid, $id) as $descendantId)	// Textcube-NG: R-592 — 경로가 바뀐 자손 분류 캐시
		CacheControl::flushCategory($descendantId);
	return $result ? true : false;
}

function updateCategoryByEntryId($blogid, $entryId, $action = 'add',$parameters = null) {

	$entry = getEntry($blogid, $entryId);
	// for deleteEntry
	if(is_null($entry) and isset($parameters['entry']))
		$entry = $parameters['entry'];
	$categoryId = $entry['category'];

	// Textcube-NG: R-592 — 글 수는 자손 합산(사용자 결정 ③)이므로 원본의 '부모 하나' 대신 조상 전체를 함께 고친다.
	//   분류가 바뀌면 옛 사슬(분류 + 조상)에만 있는 분류는 빼고, 새 사슬에만 있는 분류는 더하고, 둘 다에 있는 분류는 공개 값이 바뀔 때만 공개 글 수를 고친다
	//   (원본 1→2단·2→1단 특례가 이 식에 들어 있다. 원본은 공개 값이 함께 바뀌어도 공통 부모의 공개 글 수를 고치지 않았다)
	switch($action) {
		case 'add':
		case 'delete':
			foreach (array_merge(array($categoryId), getCategoryAncestorIds($blogid, $categoryId)) as $target)
				updateCategoryByCategoryId($blogid, $target, $action, array('visibility'=> $entry['visibility']));
			break;
		case 'update':
			if(isset($parameters['category']) && $parameters['category'][0] != $parameters['category'][1]) { // category is changed. oldcategory - 1, newcategory + 1.
				$oldChain = array_merge(array($parameters['category'][0]), getCategoryAncestorIds($blogid, $parameters['category'][0]));
				$newChain = array_merge(array($parameters['category'][1]), getCategoryAncestorIds($blogid, $parameters['category'][1]));
				foreach ($oldChain as $target) {
					if (in_array($target, $newChain))
						updateCategoryByCategoryId($blogid, $target, 'update', $parameters);
					else
						updateCategoryByCategoryId($blogid, $target, 'delete', array('visibility'=>$parameters['visibility'][0]));
				}
				foreach ($newChain as $target) {
					if (!in_array($target, $oldChain))
						updateCategoryByCategoryId($blogid, $target, 'add', array('visibility'=>$parameters['visibility'][1]));
				}
			} else {	// Same category case. should see the visibility change
				if(isset($parameters['visibility']) && $parameters['visibility'][0] != $parameters['visibility'][1]) {
					foreach (array_merge(array($categoryId), getCategoryAncestorIds($blogid, $categoryId)) as $target)
						updateCategoryByCategoryId($blogid, $target, 'update', $parameters);
				}
			}
		default:
			break;
	}
}

function updateCategoryByCategoryId($blogid, $categoryid, $action = 'add', $parameters = null) {
	global $database;
	$count        = getCategory($blogid, $categoryid, 'entries');
	$countInLogin = getCategory($blogid, $categoryid, 'entriesinlogin');
	if(empty($count)) $count = 0;
	if(empty($countInLogin)) $countInLogin = 0;
	switch($action) {
		case 'add':
			$countInLogin += 1;
			if(isset($parameters['visibility'])) {
				if($parameters['visibility'] > 1) $count += 1;
			}
			break;
		case 'delete':
			$countInLogin -= 1;
			if(isset($parameters['visibility'])) {
				if($parameters['visibility'] > 1) $count -= 1;
			}
			break;
		case 'update':
			if(isset($parameters['visibility'])) {
				if($parameters['visibility'][0]	< 2 && $parameters['visibility'][1] > 1) { // private -> public
					$count += 1;
				} else if($parameters['visibility'][0] > 1 && $parameters['visibility'][1] < 2) { // public -> private
					$count -= 1;
				} else { // no change
					return true;
				}
			}
	}

	return POD::query("UPDATE {$database['prefix']}Categories SET entries = $count, entriesinlogin = $countInLogin WHERE blogid = $blogid AND id = $categoryid");
}

function updateEntriesOfCategory($blogid, $categoryId = - 1) {
	global $database;
	// Textcube-NG: R-592 — 분류별 글 수를 두 질의로 센 뒤, 분류 전체를 최상위부터 재귀로 돌며 글 수(자기 + 자손 합, 사용자 결정 ③)와 경로(label = 조상 이름 전체)를 다시 만든다.
	//   값이 바뀐 행만 UPDATE 한다. 원본은 최상위와 그 직계 자식만 셌고(3단 아래 빠짐) 행마다 질의 두 개를 했다. $categoryId 는 받되 늘 블로그 전체를 다시 센다
	$counts = array();
	$countsInLogin = array();
	$rows = POD::queryAll("SELECT category, COUNT(id) AS n FROM {$database['prefix']}Entries WHERE blogid = $blogid AND draft = 0 AND visibility > 0 GROUP BY category");
	if (is_array($rows)) {
		foreach ($rows as $row)
			$counts[(string)$row['category']] = (int)$row['n'];
	}
	$rows = POD::queryAll("SELECT category, COUNT(id) AS n FROM {$database['prefix']}Entries WHERE blogid = $blogid AND draft = 0 GROUP BY category");
	if (is_array($rows)) {
		foreach ($rows as $row)
			$countsInLogin[(string)$row['category']] = (int)$row['n'];
	}
	$rows = POD::queryAll("SELECT * FROM {$database['prefix']}Categories WHERE blogid = $blogid");
	$byParent = array();
	if (is_array($rows)) {
		foreach ($rows as $row) {
			$parentKey = ($row['parent'] === null || $row['parent'] === '') ? 'root' : (string)$row['parent'];
			if (!isset($byParent[$parentKey]))
				$byParent[$parentKey] = array();
			array_push($byParent[$parentKey], $row);
		}
	}
	$visited = array();
	if (isset($byParent['root'])) {
		foreach ($byParent['root'] as $row)
			__updateEntriesOfCategoryNode($blogid, $byParent, $row, null, $counts, $countsInLogin, $visited, 1);
	}
	clearCategoryCache();
	if($categoryId >=0) {
		CacheControl::flushCategory($categoryId);
		foreach (getCategoryAncestorIds($blogid, $categoryId) as $ancestorId)
			CacheControl::flushCategory($ancestorId);
	}
	clearCategoryCache();
	return true;
}

function __updateEntriesOfCategoryNode($blogid, &$byParent, $row, $parentLabel, &$counts, &$countsInLogin, &$visited, $level) {
	global $database;
	$id = (string)$row['id'];
	if (isset($visited[$id]) || $level > 64)
		return array(0, 0);
	$visited[$id] = true;
	$name = UTF8::lessenAsEncoding($row['name'], 127);
	$label = ($parentLabel === null) ? $name : UTF8::lessenAsEncoding($parentLabel . '/' . $name, 255);
	$entries = isset($counts[$id]) ? $counts[$id] : 0;
	$entriesInLogin = isset($countsInLogin[$id]) ? $countsInLogin[$id] : 0;
	if (isset($byParent[$id])) {
		foreach ($byParent[$id] as $child) {
			list($childEntries, $childEntriesInLogin) = __updateEntriesOfCategoryNode($blogid, $byParent, $child, $label, $counts, $countsInLogin, $visited, $level + 1);
			$entries += $childEntries;
			$entriesInLogin += $childEntriesInLogin;
		}
	}
	$oldEntries = isset($row['entries']) ? $row['entries'] : null;
	$oldEntriesInLogin = isset($row['entriesinlogin']) ? $row['entriesinlogin'] : (isset($row['entriesInLogin']) ? $row['entriesInLogin'] : null);
	if ($oldEntries === null || $oldEntriesInLogin === null || !isset($row['label']) || (int)$oldEntries != $entries || (int)$oldEntriesInLogin != $entriesInLogin || (string)$row['label'] !== $label)
		POD::query("UPDATE {$database['prefix']}Categories SET entries = $entries, entriesinlogin = $entriesInLogin, label = '" . POD::escapeString($label) . "' WHERE blogid = $blogid AND id = " . (int)$row['id']);
	return array($entries, $entriesInLogin);
}

function moveCategory($blogid, $id, $direction) {
	global $database;
	// Textcube-NG: R-592 — 위·아래는 같은 부모 아래 형제끼리 순서만 맞바꾼다(사용자 결정 ⑥). 상위 분류(깊이)는 setCategoryParent() 로 바꾼다.
	//   원본은 바꿀 형제가 없으면 위·아래로 다른 분류 안에 들어가거나 나오며 깊이를 바꿨다(2단 전용)
	$id = (int)$id;
	if ($id <= 0)
		return false;
	if ($direction == 'up') {
		$sign = '<';
		$arrange = 'DESC';
	} else {
		$sign = '>';
		$arrange = 'ASC';
	}
	$row = POD::queryRow("SELECT parent, priority FROM {$database['prefix']}Categories WHERE blogid = $blogid AND id = $id");
	if (empty($row))
		return false;
	$myPriority = (int)$row['priority'];
	$aux = ($row['parent'] === null || $row['parent'] === '') ? 'parent IS NULL' : 'parent = ' . (int)$row['parent'];
	$next = POD::queryRow("SELECT id, priority FROM {$database['prefix']}Categories WHERE $aux AND blogid = $blogid AND id != 0 AND priority $sign $myPriority ORDER BY priority $arrange LIMIT 1");
	if (!empty($next) && !is_null($next['id'])) {
		$nextId = (int)$next['id'];
		$nextPriority = (int)$next['priority'];
		POD::query("UPDATE {$database['prefix']}Categories SET priority = $myPriority WHERE id = $nextId AND blogid = $blogid");
		POD::query("UPDATE {$database['prefix']}Categories SET priority = $nextPriority WHERE id = $id AND blogid = $blogid");
	}
	updateEntriesOfCategory($blogid);
	CacheControl::flushCategory($id);
	return true;
}

function checkRootCategoryExistence($blogid) {
	global $database;
	$sql = "SELECT count(*) FROM {$database['prefix']}Categories WHERE blogid = $blogid AND id = 0";
	if(!(POD::queryCell($sql))) {
		$name = _text('전체');
		$result = addCategory($blogid,null,$name,0);
		return $result ? true : false;
	}
	return false;
}

function getCategoryVisibility($blogid, $id) {
	$categories = getCategories($blogid,'raw');
	if( isset($categories[$id]) ) {
		if( isset( $categories[$id]['visibility'] ) ) {
			return $categories[$id]['visibility'];
		}
	}
	return 2;
}

function getParentCategoryVisibility($blogid, $id) {
	if($id == 0) return false;
	// Textcube-NG: R-592 — 부모 하나가 아니라 조상 전체를 본다. 비공개(visibility < 2)인 조상이 있으면 그 값, 조상이 모두 공개면 2, 조상이 없으면 false
	$ancestors = getCategoryAncestorIds($blogid, $id);
	if (empty($ancestors)) return false;
	$categories = getCategories($blogid,'raw');
	foreach ($ancestors as $ancestorId) {
		if (isset($categories[$ancestorId]['visibility']) && !empty($categories[$ancestorId]['visibility']) && $categories[$ancestorId]['visibility'] < 2)
			return $categories[$ancestorId]['visibility'];
	}
	return 2;
}

function setCategoryVisibility($blogid, $id, $visibility) {
	global $database;
	requireModel('blog.feed');
	if($id == 0) return false;
	$parentVisibility = getParentCategoryVisibility($blogid, $id);
	if ($parentVisibility!==false && $parentVisibility < 2) return false; // return without changing if parent category is set to hidden.
	$result = POD::query("UPDATE {$database['prefix']}Categories
		SET visibility = $visibility
		WHERE blogid = $blogid
			AND id = $id");
	if ($result && $visibility == 1) $result = setChildCategoryVisibility($blogid, $id, $visibility);
	if ($result)
		clearFeed();
	updateEntriesOfCategory($blogid);
	CacheControl::flushCategory($id);
	return $result ? $visibility : false;
}

function setChildCategoryVisibility($blogid, $id, $visibility) {
	global $database;
	if($id == 0) return false;
	// Textcube-NG: R-592 — 직계 자식뿐 아니라 자손 전체(분류 캐시를 다시 읽어 셈)를 같은 공개 값으로
	clearCategoryCache();
	$childCategories = getCategoryDescendantIds($blogid, $id);
	if(!empty($childCategories)) {
		foreach($childCategories as $childCategory) {
			$result = POD::query("UPDATE {$database['prefix']}Categories
				SET visibility = $visibility
				WHERE blogid = $blogid AND id = $childCategory");
			if($result == false) return false;
			CacheControl::flushCategory($childCategory);
		}
		return $result ? $visibility : false;
	}
	return $visibility;
}

/* Textcube-NG: R-592 글갈래 다단계(사용자 결정 11-23) — 아래 함수는 분류 전체(getCategories($blogid, 'raw') 캐시)만 읽어 조상·자손·깊이·경로를 PHP 에서 셈한다.
   부모를 따라 올라가다 자기로 돌아오는 순환은 방문 집합과 64단계 상한으로 끊는다. 클로저 재귀 없이 이름 있는 함수와 반복문만 쓴다(php5.5 트리 문법) */
function __getCategoryRaw($blogid, $fresh = false) {
	global $__gCacheCategoryRaw;
	if ($fresh)
		clearCategoryCache();
	if (empty($__gCacheCategoryRaw))
		getCategories($blogid, 'raw');
	return is_array($__gCacheCategoryRaw) ? $__gCacheCategoryRaw : array();
}

// 분류를 몇 단까지 나눌 수 있는지(블로그 설정 categoryMaxDepth, 기본 3, 2~10 — 사용자 결정 ①)
function getCategoryMaxDepth($blogid) {
	$depth = (int)Setting::getBlogSettingGlobal('categoryMaxDepth', 3, $blogid);
	if ($depth < 2)
		$depth = 2;
	else if ($depth > 10)
		$depth = 10;
	return $depth;
}

// 부모부터 최상위까지의 id(자기 제외). 0·없는 id 는 빈 배열
function getCategoryAncestorIds($blogid, $id) {
	$ancestors = array();
	if ($id === null || $id === '' || !is_numeric($id) || $id <= 0)
		return $ancestors;
	$categories = __getCategoryRaw($blogid);
	if (!isset($categories[$id]))
		return $ancestors;
	$visited = array((string)(int)$id => true);
	$current = $categories[$id]['parent'];
	while ($current !== null && $current !== '' && isset($categories[$current]) && !isset($visited[(string)$current]) && count($ancestors) < 64) {
		$visited[(string)$current] = true;
		array_push($ancestors, (int)$current);
		$current = $categories[$current]['parent'];
	}
	return $ancestors;
}

// 부모 id => 자식 id 목록(priority 순)
function __getCategoryChildMap($categories) {
	$rows = array();
	foreach ($categories as $category) {
		if ($category['parent'] === null || $category['parent'] === '')
			continue;
		array_push($rows, $category);
	}
	usort($rows, '__compareCategoryPriority');
	$byParent = array();
	foreach ($rows as $category) {
		$parentKey = (string)$category['parent'];
		if (!isset($byParent[$parentKey]))
			$byParent[$parentKey] = array();
		array_push($byParent[$parentKey], (int)$category['id']);
	}
	return $byParent;
}

function __compareCategoryPriority($a, $b) {
	if ((int)$a['priority'] == (int)$b['priority'])
		return ((int)$a['id'] < (int)$b['id']) ? -1 : (((int)$a['id'] > (int)$b['id']) ? 1 : 0);
	return ((int)$a['priority'] < (int)$b['priority']) ? -1 : 1;
}

// 자손 전체 id(자기 제외, 너비 우선·형제는 priority 순). 0 이면 빈 배열
function getCategoryDescendantIds($blogid, $id) {
	$descendants = array();
	if ($id === null || $id === '' || !is_numeric($id) || $id <= 0)
		return $descendants;
	$byParent = __getCategoryChildMap(__getCategoryRaw($blogid));
	$queue = array((string)(int)$id);
	$visited = array((string)(int)$id => true);
	while (!empty($queue)) {
		$parentKey = array_shift($queue);
		if (!isset($byParent[$parentKey]))
			continue;
		foreach ($byParent[$parentKey] as $childId) {
			if (isset($visited[(string)$childId]))
				continue;
			$visited[(string)$childId] = true;
			array_push($descendants, $childId);
			array_push($queue, (string)$childId);
		}
	}
	return $descendants;
}

// 0(전체) = 0, 최상위 = 1, …
function getCategoryDepth($blogid, $id) {
	if ($id === null || $id === '' || !is_numeric($id) || $id <= 0)
		return 0;
	return 1 + count(getCategoryAncestorIds($blogid, $id));
}

// 자기를 포함한 아래 단 수(잎 = 1). 옮기기 깊이 검사용
function getCategorySubtreeHeight($blogid, $id) {
	if ($id === null || $id === '' || !is_numeric($id) || $id <= 0)
		return 0;
	$byParent = __getCategoryChildMap(__getCategoryRaw($blogid));
	$visited = array();
	return __getCategorySubtreeHeightNode($byParent, (int)$id, $visited, 0);
}

function __getCategorySubtreeHeightNode(&$byParent, $id, &$visited, $level) {
	if (isset($visited[(string)$id]) || $level > 64)
		return 0;
	$visited[(string)$id] = true;
	$height = 1;
	if (isset($byParent[(string)$id])) {
		foreach ($byParent[(string)$id] as $childId) {
			$childHeight = 1 + __getCategorySubtreeHeightNode($byParent, $childId, $visited, $level + 1);
			if ($childHeight > $height)
				$height = $childHeight;
		}
	}
	return $height;
}

// 지금 분류 트리의 가장 깊은 단(설정 줄이기 검사용)
function getCategoryTreeMaxDepth($blogid) {
	$max = 0;
	foreach (__getCategoryRaw($blogid) as $category) {
		if ($category['id'] <= 0)
			continue;
		$depth = getCategoryDepth($blogid, $category['id']);
		if ($depth > $max)
			$max = $depth;
	}
	return $max;
}

// 경로(label): 조상 이름(최상위부터) + 부모 이름 + 이름을 '/' 로 잇는다. 이름은 열 길이(127자)로 먼저 자른다
function buildCategoryLabel($blogid, $parentId, $name) {
	$names = array();
	if ($parentId !== null && $parentId !== '' && is_numeric($parentId) && $parentId > 0) {
		foreach (array_reverse(getCategoryAncestorIds($blogid, $parentId)) as $ancestorId)
			array_push($names, UTF8::lessenAsEncoding(getCategoryNameById($blogid, $ancestorId), 127));
		array_push($names, UTF8::lessenAsEncoding(getCategoryNameById($blogid, $parentId), 127));
	}
	array_push($names, UTF8::lessenAsEncoding($name, 127));
	return implode('/', $names);
}

// 저장할 때 자르는 함수와 같은 기준으로 label 열(255자)을 넘는지 본다(사용자 결정 ⑤ — 넘으면 거부)
function isCategoryLabelTooLong($label) {
	return UTF8::lessenAsEncoding($label, 255) !== $label;
}

// $label 을 자기 경로로 할 때 자손 경로 가운데 255자를 넘는 것이 있는지
function __isCategorySubtreeLabelTooLong($blogid, $id, $label, $level) {
	if ($level > 64)
		return false;
	foreach (__getCategoryRaw($blogid) as $category) {
		if ($category['parent'] === null || $category['parent'] === '' || $category['parent'] != $id || $category['id'] == $id)
			continue;
		$childLabel = $label . '/' . UTF8::lessenAsEncoding($category['name'], 127);
		if (isCategoryLabelTooLong($childLabel) || __isCategorySubtreeLabelTooLong($blogid, $category['id'], $childLabel, $level + 1))
			return true;
	}
	return false;
}

// 선택 목록용: 깊이 우선 순서의 행(depth = 단, hiddenByAncestor = 자기나 조상 가운데 비공개, subPath = 2단부터 자기까지의 이름)
function getCategoriesFlat($blogid, $withRoot = false) {
	$flat = array();
	foreach (getCategories($blogid) as $category) {
		if (!$withRoot && $category['id'] == 0)
			continue;
		__appendCategoriesFlat($flat, $category, 1, false, array());
	}
	return $flat;
}

// 최상위 분류 $id 의 자손 행(깊이 우선) — 선택 목록이 최상위 분류 줄 다음에 붙인다(행 꼴은 getCategoriesFlat() 과 같음)
function getCategoryDescendantRows($blogid, $id) {
	$tree = getCategories($blogid);
	$flat = array();
	if (!isset($tree[$id]) || empty($tree[$id]['children']))
		return $flat;
	$hidden = !($tree[$id]['visibility'] > 1);
	foreach ($tree[$id]['children'] as $child)
		__appendCategoriesFlat($flat, $child, 2, $hidden, array());
	return $flat;
}

function __appendCategoriesFlat(&$flat, $category, $depth, $hiddenAbove, $subPath) {
	if ($depth > 64)
		return;
	$children = isset($category['children']) ? $category['children'] : array();
	$row = $category;
	unset($row['children']);
	$row['depth'] = $depth;
	$row['hiddenByAncestor'] = $hiddenAbove || !($category['visibility'] > 1);
	$row['subPath'] = ($depth > 1) ? array_merge($subPath, array($category['name'])) : array();
	array_push($flat, $row);
	foreach ($children as $child)
		__appendCategoriesFlat($flat, $child, $depth + 1, $row['hiddenByAncestor'], $row['subPath']);
}

// 상위 분류를 $newParent(0·null = 최상위)로 바꿀 수 있는지 — true, 'same'(그대로) 또는 거부 까닭
function canSetCategoryParent($blogid, $id, $newParent) {
	$categories = __getCategoryRaw($blogid, true);
	if (!is_numeric($id) || $id <= 0 || !isset($categories[$id]))
		return 'invalid';
	$id = (int)$id;
	$newParent = (empty($newParent) || !is_numeric($newParent)) ? 0 : (int)$newParent;
	$currentParent = ($categories[$id]['parent'] === null || $categories[$id]['parent'] === '') ? 0 : (int)$categories[$id]['parent'];
	if ($newParent == $currentParent)
		return 'same';
	if ($newParent == $id)
		return 'self';
	if ($newParent > 0 && !isset($categories[$newParent]))
		return 'invalid';
	if ($newParent > 0 && in_array($newParent, getCategoryDescendantIds($blogid, $id)))
		return 'descendant';
	if (getCategoryDepth($blogid, $newParent) + getCategorySubtreeHeight($blogid, $id) > getCategoryMaxDepth($blogid))
		return 'depth';
	foreach ($categories as $category) {
		$parent = ($category['parent'] === null || $category['parent'] === '') ? 0 : (int)$category['parent'];
		if ($category['id'] != $id && $category['id'] != 0 && $parent == $newParent && $category['name'] == $categories[$id]['name'])
			return 'duplicate';
	}
	$label = buildCategoryLabel($blogid, ($newParent > 0 ? $newParent : null), $categories[$id]['name']);
	if (isCategoryLabelTooLong($label) || __isCategorySubtreeLabelTooLong($blogid, $id, $label, 0))
		return 'label';
	return true;
}

// 상위 분류 바꾸기(사용자 결정 ⑥) — 새 형제의 맨 뒤로 가고, 글 수·경로를 다시 센다. 비공개 분류 아래로 옮기면 옮긴 분류와 자손도 비공개로 맞춘다
function setCategoryParent($blogid, $id, $newParent) {
	global $database, $__gCategoryError;
	requireModel('blog.feed');
	$__gCategoryError = '';
	$check = canSetCategoryParent($blogid, $id, $newParent);
	if ($check === 'same')
		return true;
	if ($check !== true) {
		$__gCategoryError = $check;
		return false;
	}
	$id = (int)$id;
	$newParent = (empty($newParent) || !is_numeric($newParent)) ? 0 : (int)$newParent;
	$oldAncestors = getCategoryAncestorIds($blogid, $id);
	$newPriority = POD::queryCell("SELECT MAX(priority) FROM {$database['prefix']}Categories WHERE blogid = $blogid") + 1;
	if (!POD::execute("UPDATE {$database['prefix']}Categories SET parent = " . ($newParent > 0 ? $newParent : 'NULL') . ", priority = $newPriority WHERE blogid = $blogid AND id = $id")) {
		$__gCategoryError = 'invalid';
		return false;
	}
	clearCategoryCache();
	$parentVisibility = getParentCategoryVisibility($blogid, $id);
	if ($parentVisibility !== false && $parentVisibility < 2) {
		POD::execute("UPDATE {$database['prefix']}Categories SET visibility = 1 WHERE blogid = $blogid AND id = $id");
		setChildCategoryVisibility($blogid, $id, 1);
	}
	updateEntriesOfCategory($blogid);
	foreach (array_merge(array($id), $oldAncestors, getCategoryAncestorIds($blogid, $id), getCategoryDescendantIds($blogid, $id)) as $flushId)
		CacheControl::flushCategory($flushId);
	clearFeed();
	CacheControl::flushDBCache('comment');
	CacheControl::flushDBCache('trackback');
	return true;
}

// 분류 만들기·옮기기·지우기가 거부한 까닭의 안내 문구(관리 화면 alert — 작은따옴표를 넣지 않는다). 까닭이 없으면 $default
function getCategoryErrorMessage($blogid, $default = null) {
	global $__gCategoryError;
	switch ($__gCategoryError) {
		case 'self':
		case 'descendant':
			return _t('자기 자신이나 하위 분류 아래로는 옮길 수 없습니다');
		case 'depth':
			return _f('분류는 %1단까지 허용됩니다.', getCategoryMaxDepth($blogid));
		case 'duplicate':
			return _t('같은 이름의 카테고리가 이미 존재합니다');
		case 'label':
			return _t('분류 경로가 너무 깁니다. 이름을 줄여 주십시오');
		case 'children':
			return _t('하위 분류가 있으므로 삭제할 수 없습니다.');
		case 'entries':	// Textcube-NG: r4-14 — 관리 화면과 같은 문구(번역 있음)
			return _t('분류에 등록된 글이 있으므로 삭제할 수 없습니다.');
		case 'bodyid':	// Textcube-NG: r4-16 — 새 문구(번역 없음)
			return _t('Body id 는 영문·숫자·밑줄(_)·붙임표(-)로 20자 이하만 쓸 수 있습니다. Body id 는 바꾸지 않았습니다');
		case 'invalid':
			return _t('분류를 옮기지 못했습니다');
	}
	return ($default === null) ? _t('같은 이름의 카테고리가 이미 존재합니다') : $default;
}

function clearCategoryCache() {
	// Textcube-NG: r4-16 — 공개 목록 캐시(getCategoryVisibilityList)도 비운다. 원본은 같은 요청 안에서 공개 값을 바꾼 뒤 옛 목록을 돌려줄 수 있었다(dojoref 2.7→2.5)
	global $__gCacheCategoryTree, $__gCacheCategoryRaw, $__gCacheCategoryVisibilityList;
	if(isset($__gCacheCategoryTree))
		$__gCacheCategoryTree = array();
	if(isset($__gCacheCategoryRaw))
		$__gCacheCategoryRaw = array();
	if(isset($__gCacheCategoryVisibilityList))
		$__gCacheCategoryVisibilityList = array();
}
?>
