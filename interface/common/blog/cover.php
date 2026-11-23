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


if (isset($cache->contents)) {
	dress('cover', $cache->contents, $view);
} else if (isset($skin)) {
	if( rtrim( $suri['url'], '/' ) == $pathURL ) {
		/* same code exists in entries.php */
		$automaticLink .= "<link rel=\"meta\" type=\"application/rdf+xml\" title=\"FOAF\" href=\"{$defaultURL}/foaf\" />\n";
	}
	$coverView = $skin->cover;
	$itemsView = '';
	handleCoverpages($skin, false);
	$coverpageModule = $skin->coverpageModule;
	foreach ($coverpageModule as $coverpageItem) {	
		$itemsView .= str_replace('[##_cover_content_##]',$coverpageItem, $skin->coverItem);
	}

	dress('cover_rep', $itemsView, $coverView);
	/* Add webslice feature */
	if(Setting::getBlogSettingGlobal('useMicroformat',3) == 3) {
		$coverView = addWebSlice($coverView, 'coverPageWebslice',  htmlspecialchars($blog['title'].' - '._t('표지'), ENT_QUOTES | ENT_SUBSTITUTE));
	}
	dress('cover', $coverView, $view);
	dress('foaf_url', "$defaultURL/foaf", $view);

	if(isset($cache)) { 
		$cache->contents = $coverView;
		$cache->dbContents = $paging;
		$cache->update();
	}
}
?>
