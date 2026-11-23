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
define('NO_SESSION', true);
define('__TEXTCUBE_LOGIN__',true);
define('__TEXTCUBE_CUSTOM_HEADER__', true);

require ROOT . '/library/preprocessor.php';
requireModel("blog.category");

requireStrictBlogURL();

$children = array();
$cache = pageCache::getInstance();
if(!empty($suri['id'])) {
	$categoryId = $suri['id'];
	if(in_array($categoryId, getCategoryVisibilityList($blogid, 'private'))) return false;
	$categoryTitle = getCategoryNameById(getBlogId(), $categoryId);
} else if (!empty($suri['value'])) {
 	$categoryId = getCategoryIdByLabel(getBlogId(), $suri['value']);
	if(in_array($categoryId, getCategoryVisibilityList($blogid, 'private'))) return false;
 	$categoryTitle = $suri['value'];
} else { 	// If no category is mentioned, redirect it to total atom.
	header ("Location: $hostURL$blogURL/atom");
	exit;
}

$cache->reset('categoryATOM-'.$categoryId);
if(!$cache->load()) {
	// Textcube-NG: R-592 — 분류 깊이와 관계없이 자손 분류 전체의 글을 넣고(사용자 결정 ②), 손님 전용 피드이므로 비공개 분류(비공개 분류의 자손 포함)는 뺀다.
	//   원본은 최상위 분류일 때 직계 자식을 공개 여부와 관계없이 넣어, 비공개 하위 분류의 공개 글이 손님에게 부모 분류 피드로 나왔다
	$categoryIds = array_values(array_diff(array_merge(array($categoryId), getCategoryDescendantIds(getBlogId(), $categoryId)), getCategoryVisibilityList(getBlogId(), 'private')));
	if (empty($categoryIds)) return false;
	requireModel("blog.feed");
	$result = getCategoryFeedByCategoryId(getBlogId(),$categoryIds,'atom',$categoryTitle);
	if($result !== false) {
		$cache->reset('categoryATOM-'.$categoryId);
		$cache->contents = $result;
		$cache->update();
	}
}
header('Content-Type: application/atom+xml; charset=utf-8');
fireEvent('FeedOBStart');
echo fireEvent('ViewCategoryATOM', $cache->contents);
fireEvent('FeedOBEnd');
?>
