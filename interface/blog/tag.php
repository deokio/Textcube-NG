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
fireEvent('OBStart');	// Textcube-NG: archive·entry·cover 처리기와 같이 화면 출력 앞뒤로 OBStart·OBEnd 를 낸다(SearchOptimizer 가 스킨의 description 메타 중복을 뺀다)(r3-5)
if (strlen($suri['value'])) {
	if(!isset($suri['id']) || (Setting::getBlogSettingGlobal('useSloganOnTag',1) == 1)) {
		$tag = getTagId($blogid, $suri['value']);
		$listFeedURL = 'tag/'.$suri['value'];
	} else {
		$tag = $suri['id'];
		$suri['value'] = getTagById($blogid, $suri['id']);
		$listFeedURL = 'tag/'.$suri['id'];
	}

	$preservedEntries =
	$entryCache =
		null;	// Textcube-NG: 503-18a·b 뒤로 쓰지 않는 $listCache 초기화를 뺌(목록 캐시는 $listView·$listCacheName 으로 넘김)(r3-8)
	if ($skinSetting['showListOnTag'] != 0) {
		$cache->reset();
		// Textcube-NG: page 가 배열이면 원본은 Array to string conversion 뒤 'Array' 캐시를 따로 만들었다 — Paging::fetch() 와 같게 1 쪽 캐시 이름을 쓴다(category.php 와 같은 방식, v3.221)
		$cache->name = 'tagList-'.$tag.'-'.(is_numeric($suri['page']) ? $suri['page'] : 1).'-';
		if(!$cache->load()) {
			$listWithPaging = getEntryListWithPagingByTag($blogid, $tag, $suri['page'], $blog['entriesOnList']);
			if (!array_key_exists('total',$listWithPaging[1])) $listWithPaging[1]['total'] = 0;
			$list = array('title' => $suri['value'], 'items' => $listWithPaging[0], 'count' => $listWithPaging[1]['total']);
			$paging = $listWithPaging[1];
			$listView = null;
		} else {
			$paging = $cache->dbContents;
			$listView = $cache->contents;	// Textcube-NG: category.php 와 같이 캐시된 목록을 list.php 에 넘긴다. 원본은 캐시가 있으면 $list 가 없어 목록이 빠졌다(r3-8)
		}
		$listCacheName = $cache->name;	// Textcube-NG: 목록을 출력할 때 이 이름으로 다시 맞춘다(r3-8)
	}

	if ($skinSetting['showListOnTag'] != 2) {
		list($entries, $paging) = getEntriesWithPagingByTag($blogid, $tag, $suri['page'], $blog['entriesOnList'],($skinSetting['showListOnTag'] == 3 ? $blog['entriesOnPage'] : $blog['entriesOnList']));
		if($skinSetting['showListOnTag'] == 1) $skinSetting['showListWithTotalEntries'] = true;
		$preservedEntries = $entries;
		unset($entries);
	}

	// Now, print starts.
	require ROOT . '/interface/common/blog/begin.php';
	if(Setting::getBlogSettingGlobal('useKeywordAsTag',true)==true) {
		$cache->reset();
		$cache->name = 'keyword-'.$tag.'-';
		if($cache->load()) {
			require ROOT . '/interface/common/blog/entries.php';
		} else {
			$entries[0] = getKeylogByTitle(getBlogId(), $suri['value']);
			if(!empty($entries[0])) {
				require ROOT . '/interface/common/blog/entries.php';
				unset($entries);
			}
		}
	}

	if ($skinSetting['showListOnTag'] != 0) {
		// Textcube-NG: 키워드 캐시가 같은 pageCache 객체의 이름을 바꾸므로 목록 캐시 이름으로 다시 맞춰, 캐시가 없을 때 list.php 가 만든 목록을 저장한다.
		// 원본은 캐시가 없으면 $cache 가 null 이라 저장하지 않았다(r3-8)
		$cache = pageCache::getInstance();
		$cache->reset();
		$cache->name = $listCacheName;
		require ROOT . '/interface/common/blog/list.php';
	}
	unset($cache);
	if ($skinSetting['showListOnTag'] != 2) {
		$entries = $preservedEntries;
		require ROOT . '/interface/common/blog/entries.php';
	}

} else {
	require ROOT . '/interface/common/blog/begin.php';
	$cache->reset();
	$cache->name = 'tagPage';
	/* Textcube-NG: 캐시를 만들 때의 태그 목록 개수(getTagsOnTagPage(), v3.136)를 캐시 기록(dbContents)에 두고, 지금 값과 다르면 다시 만든다.
	   서비스 상한이 바뀌면 여러 블로그의 값이 함께 달라지는데, 캐시는 블로그별(pageCache/<blogid>)이라 상한을 저장할 때
	   다른 블로그의 캐시를 지울 수 없다. 이 기록이 없는 예전 캐시도 이렇게 한 번 다시 만들어진다 */
	$tagsOnTagPage = getTagsOnTagPage($blogid);
	if(!$cache->load() || !is_numeric($cache->dbContents) || intval($cache->dbContents) !== $tagsOnTagPage) {
		$cache->contents = null;
		$cache->dbContents = $tagsOnTagPage;
		$siteTags = getSiteTags($blogid);
	}
	require ROOT . '/interface/common/blog/siteTags.php';
	unset($cache);
}
require ROOT . '/interface/common/blog/end.php';
fireEvent('OBEnd');	// (r3-5)
?>
