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


if(isset($cache->contents)) {
	$tagView = $cache->contents;
} else {
	$tagView = $skin->siteTag;
	list($maxTagFreq, $minTagFreq) = getTagFrequencyRange();
	$itemsView = '';
	foreach ($siteTags as $siteTag) {
		$itemView = $skin->siteTagItem;
		dress('tag_name', htmlspecialchars($siteTag['name'], ENT_QUOTES | ENT_SUBSTITUTE), $itemView);
		dress('tag_link', "$blogURL/tag/" . (Setting::getBlogSettingGlobal('useSloganOnTag',true) ? URL::encode($siteTag['name'],$service['useEncodedURL']) : $siteTag['id']), $itemView);
		dress('tag_class', "cloud" . getTagFrequency($siteTag['name'], $maxTagFreq, $minTagFreq), $itemView);
		$itemsView .= $itemView;
	}
	dress('tag_rep', $itemsView, $tagView);
	if(isset($cache)) {
		$cache->contents = $tagView;
		$cache->update();
	}
}
dress('tag', $tagView, $view);
?>
