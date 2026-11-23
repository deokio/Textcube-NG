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

$keywordView = $skin->keyword;
$previousGroup = '';
$itemsView = '';
if(isset($keywords)) {
	foreach ($keywords as $item) {
		$itemView = $skin->keywordItem;
		dress('keyword_label', htmlspecialchars($item, ENT_QUOTES | ENT_SUBSTITUTE), $itemView);
		if($skinSetting['keylogSkin']!= null) {
			dress('onclick_keyword', "openKeyword('$blogURL/keylog/" . escapeJSInAttribute($item) . "'); return false;", $itemView);
		} else {
			dress('onclick_keyword', "return false;", $itemView);
		}
		$itemsView .= $itemView;
	}
}
dress('keyword_rep', $itemsView, $keywordView);
dress('keyword', $keywordView, $view);
?>
