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
define('NO_SESSION', true);
define('__TEXTCUBE_CUSTOM_HEADER__', true);
define('__TEXTCUBE_LOGIN__',true);

require ROOT . '/library/preprocessor.php';
//requireModel("blog.entry");
requireModel("blog.tag");

requireStrictBlogURL();
$blogid = getBlogId();
$children = array();
$cache = pageCache::getInstance();
if(!empty($suri['id']) && Setting::getBlogSettingGlobal('useSloganOnTag',1) != 1) { // Textcube-NG: 태그를 이름으로 쓰는 설정이면 값 전체가 숫자(또는 & 앞이 숫자)인 이름을 번호로 보지 않음(blog/tag.php 와 같은 판정)(r3-8)
	$tagId = $suri['id'];
	$tagTitle = getTagById($blogid, $tagId);
} else if (!empty($suri['value'])) {
 	$tagId = getTagId($blogid, $suri['value']);
	$tagTitle = $suri['value'];
} else { 	// If no tag is mentioned, redirect it to total atom.
	header ("Location: $hostURL$blogURL/atom");
	exit;
}

$cache->reset('tagATOM-'.$tagId);
if(!$cache->load()) {
	requireModel("blog.feed");
	$result = getTagFeedByTagId(getBlogId(),$tagId,'atom',$tagTitle);
	if($result !== false) {
		$cache->reset('tagATOM-'.$tagId);
		$cache->contents = $result;
		$cache->update();
	}
}
header('Content-Type: application/atom+xml; charset=utf-8');
fireEvent('FeedOBStart');
echo fireEvent('ViewTagATOM', $cache->contents);
fireEvent('FeedOBEnd');
?>
