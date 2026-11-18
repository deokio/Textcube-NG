<?php
/// Copyright (c) 2004-2016, Needlworks  / Tatter Network Foundation
/// All rights reserved. Licensed under the GPL.
/// See the GNU General Public License for more details. (/documents/LICENSE, /documents/COPYRIGHT)
require ROOT . '/library/preprocessor.php';

$cache = pageCache::getInstance();
$author = empty($suri['value']) ? '' : $suri['value'];
$authorId = User::getUserIdByName($author);
if(empty($authorId)) exit;

fireEvent('OBStart');	// Textcube-NG: archive·entry·cover 처리기와 같이 화면 출력 앞뒤로 OBStart·OBEnd 를 낸다(SearchOptimizer 가 스킨의 description 메타 중복을 뺀다)(r3-5)
if ($skinSetting['showListOnAuthor'] != 0) {
	// Textcube-NG: page 가 배열이면 원본은 Array to string conversion 뒤 'Array' 캐시를 따로 만들었다 — Paging::fetch() 와 같게 1 쪽 캐시 이름을 쓴다(category.php 와 같은 방식, v3.221)
	$cache->name = 'authorList-'.$authorId."-".(is_numeric($suri['page']) ? $suri['page'] : 1)."-";
	if (!$cache->load()) {
		if(!$listWithPaging = getEntryListWithPagingByAuthor($blogid, $author, $suri['page'], $blog['entriesOnList']))
			$listWithPaging = array(array(), array('total' => 0));
		$list = array('title' => $author, 'items' => $listWithPaging[0], 'count' => $listWithPaging[1]['total']);
		$paging = $listWithPaging[1];
		$listView = null;
	} else {
		$paging = $cache->dbContents;
		$listView = $cache->contents;
	}
	require ROOT . '/interface/common/blog/begin.php';
	$listFeedURL = 'author/'.URL::encode($author);
	require ROOT . '/interface/common/blog/list.php';
}

$entries = array();
if ($skinSetting['showListOnAuthor'] != 2) {
	unset($cache);
	list($entries, $paging) = getEntriesWithPagingByAuthor($blogid, $author, $suri['page'], $blog['entriesOnList'], ($skinSetting['showListOnAuthor'] == 3 ? $blog['entriesOnPage'] : $blog['entriesOnList']));
	if($skinSetting['showListOnAuthor'] == 0) require ROOT . '/interface/common/blog/begin.php';
	require ROOT . '/interface/common/blog/entries.php';
}
require ROOT . '/interface/common/blog/end.php';
fireEvent('OBEnd');	// (r3-5)

?>
