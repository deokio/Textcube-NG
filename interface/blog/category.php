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
require ROOT . '/library/preprocessor.php';

$cache = pageCache::getInstance();

if(!isset($suri['id']) || (Setting::getBlogSettingGlobal('useSloganOnCategory',1) == 1)) {
	$category = empty($suri['value']) ? 0 : getCategoryIdByLabel($blogid, $suri['value']);
	$listFeedURL = 'category/'.$suri['value'];
} else {
	$category = $suri['id'];
	$suri['value'] = getCategoryLabelById($blogid, $category);
	$listFeedURL = 'category/'.$suri['id'];
}
if(!doesHaveOwnership() && getCategoryVisibility($blogid, $category) < 2)
	$category = null;
// Textcube-NG: 비공개 분류의 하위 분류(getCategoryVisibilityList 'private' 에 함께 들어 있다)도 손님에게는 없는 분류로 본다(목록·본문 모두)(r3-5)
if(!doesHaveOwnership() && $category !== null && in_array($category, getCategoryVisibilityList($blogid, 'private')))
	$category = null;

// Keylog as category description
$keylogEntry = null;

if(Setting::getBlogSettingGlobal('useKeywordAsCategory',false)==true) {
	$cache->reset();
	$cache->name = 'keyword-category-'.$category.'-';
	if($cache->load()) {
		$keylogEntry = unserialize($cache->contents);
	} else {
        $entries = array();
		$entries[0] = getKeylogByTitle(getBlogId(), $suri['value']);
		if(!empty($entries[0])) {
			$keylogEntry = $entries;
			$cache->contents = serialize($entries);
			$cache->update();
		}
        unset($entries);
    }
	$cache->reset();
}

fireEvent('OBStart');	// Textcube-NG: archive·entry·cover 처리기와 같이 화면 출력 앞뒤로 OBStart·OBEnd 를 낸다(SearchOptimizer 가 스킨의 description 메타 중복을 뺀다)(r3-5)
if ($skinSetting['showListOnCategory'] != 0) {
	require ROOT . '/interface/common/blog/begin.php';
	if(!empty($keylogEntry)) {
		$entries = $keylogEntry;
		require ROOT . '/interface/common/blog/entries.php';
		unset($keylogEntry);
		unset($entries);
	}
    $cache->reset();
    // Textcube-NG: ?page[]= 처럼 page 가 배열이면 원본은 Array to string conversion(5.6/7.4 Notice, PHP 8 Warning) 뒤 'Array' 이름의 캐시를 따로 만들었다. 목록은 Paging::fetch() 가 숫자가 아닌 page 를 1 쪽으로 보므로 1 쪽 캐시 이름을 쓴다(v3.221)
    $cache->name = 'categoryList-'.$category."-".(is_numeric($suri['page']) ? $suri['page'] : 1)."-";
	if (!$cache->load()) {
		if(!$listWithPaging = getEntryListWithPagingByCategory($blogid, $category, $suri['page'], $blog['entriesOnList']))
			$listWithPaging = array(array(), array('total' => 0));
		$list = array('title' => (empty($suri['value']) ? getCategoryLabelById($blogid, 0) : $suri['value']),
				'items' => $listWithPaging[0],
				'count' => $listWithPaging[1]['total'],
				'category' => $category);
		$paging = $listWithPaging[1];
		$listView = null;
	} else {
		$paging = $cache->dbContents;
		$listView = $cache->contents;
	}
	require ROOT . '/interface/common/blog/list.php';
}

$entries = array();
if ($skinSetting['showListOnCategory'] != 2) {
    unset($cache);
    if($skinSetting['showListOnCategory'] == 1) $skinSetting['showListWithTotalEntries'] = true;
	if($skinSetting['showListOnCategory'] == 0) require ROOT . '/interface/common/blog/begin.php';

	if(!empty($keylogEntry)) {
		$entries = $keylogEntry;
		require ROOT . '/interface/common/blog/entries.php';
		unset($keylogEntry);
		unset($entries);
	}
	list($entries, $paging) = getEntriesWithPagingByCategory($blogid, $category, $suri['page'], $blog['entriesOnList'], ($skinSetting['showListOnCategory'] == 3 ? $blog['entriesOnPage'] : $blog['entriesOnList']));
	require ROOT . '/interface/common/blog/entries.php';
}
require ROOT . '/interface/common/blog/end.php';
fireEvent('OBEnd');	// (r3-5)

?>
