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
require ROOT . '/library/preprocessor.php';

// Textcube-NG: ?search[]= ·?q[]= 처럼 배열이 오면 원본(5.6)은 strlen() Warning 뒤 'Array' 로 댓글·트랙백을 검색했고 PHP 8 은 strlen() TypeError 로 멈췄다(HTTP 500). 문자열이 아닌 값은 받지 않고 경로 값을 쓴다(addopenid 의 __T__ 와 같은 방식, v3.221)
$search = (isset($_GET['search']) && is_string($_GET['search'])) ? $_GET['search'] : $suri['value'];
$search = (isset($_GET['q']) && is_string($_GET['q'])) ? $_GET['q'] : $search; // Consider the common search query GET name. (for compatibility)
$list = array('title' => '', 'items' => array(), 'count' => 0);

fireEvent('OBStart');	// Textcube-NG: archive·entry·cover 처리기와 같이 화면 출력 앞뒤로 OBStart·OBEnd 를 낸다(SearchOptimizer 가 스킨의 description 메타 중복을 뺀다)(r3-5)
if (strlen($search) > 0 && !empty($suri['page'])) {
	$listWithPaging = getEntryListWithPagingBySearch($blogid, $search, $suri['page'], $blog['entriesOnList']);
	$list = array('title' => $search, 'items' => $listWithPaging[0], 'count' => $listWithPaging[1]['total']);
	$listFeedURL = 'search/'.$search;
	$paging = $listWithPaging[1];
	require ROOT . '/interface/common/blog/begin.php';
	require ROOT . '/interface/common/blog/list.php';
}

if ($suri['page'] === true || $suri['page'] === '1') {
	$commentList = getCommentList($blogid, $search);
	$trackbackList = getTrackbackList($blogid, $search);
	if(!isset($view)) require ROOT . '/interface/common/blog/begin.php';
	if(isset($commentList))   require ROOT . '/interface/common/blog/commentList.php';
	if(isset($trackbackList)) require ROOT . '/interface/common/blog/trackbackList.php';
}

if ($skinSetting['showListOnSearch'] != 2) {
	if(!isset($view)) require ROOT . '/interface/common/blog/begin.php';
	list($entries, $paging) = getEntriesWithPagingBySearch($blogid, $search, $suri['page'], $blog['entriesOnList'], $blog['entriesOnList']);
	require ROOT . '/interface/common/blog/entries.php';
}
require ROOT . '/interface/common/blog/end.php';
fireEvent('OBEnd');	// (r3-5)
?>
