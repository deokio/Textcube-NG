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

$trackbackListView = $skin->trackbackList;
$itemsView = '';
foreach ($trackbackList['items'] as $item) {
	$itemView = $skin->trackbackListItem;
	dress('tblist_rep_regdate', fireEvent('ViewTrackbackListDate', Timestamp::format3($item['written'])), $itemView);
	dress('tblist_rep_link', "$blogURL/".($blog['useSloganOnPost'] ? "entry/".URL::encode($item['slogan'],$service['useEncodedURL']) : $item['entry'])."#trackback{$item['id']}", $itemView);
	dress('tblist_rep_subject', htmlspecialchars($item['subject'], ENT_QUOTES | ENT_SUBSTITUTE), $itemView);
	dress('tblist_rep_body', htmlspecialchars(fireEvent('ViewTrackbackListTitle', UTF8::lessenAsEm($item['excerpt'], 100)), ENT_QUOTES | ENT_SUBSTITUTE), $itemView);
	$itemsView .= $itemView;
}
dress('tblist_rep', $itemsView, $trackbackListView);
dress('tblist_conform', htmlspecialchars(fireEvent('ViewTrackbackListHeadTitle', $trackbackList['title']), ENT_QUOTES | ENT_SUBSTITUTE), $trackbackListView);
dress('tblist_count', count($trackbackList['items']), $trackbackListView);
dress('tblist', $trackbackListView, $view);
?>
