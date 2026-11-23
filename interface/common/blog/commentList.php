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

$commentListView = $skin->commentList;
$itemsView = '';
foreach ($commentList['items'] as $item) {
	$itemView = $skin->commentListItem;
	dress('rplist_rep_regdate', fireEvent('ViewCommentListDate', Timestamp::format3($item['written']), $item['written']), $itemView);
	dress('rplist_rep_link', "$blogURL/".($blog['useSloganOnPost'] ? "entry/".URL::encode($item['slogan'],$service['useEncodedURL']) : $item['entry'])."#comment{$item['id']}", $itemView);
	
	dress('rplist_rep_name', htmlspecialchars($item['name'], ENT_QUOTES | ENT_SUBSTITUTE), $itemView);
	dress('rplist_rep_body', htmlspecialchars(fireEvent('ViewCommentListTitle', UTF8::lessenAsEm($item['comment'], 70)), ENT_QUOTES | ENT_SUBSTITUTE), $itemView);
	$itemsView .= $itemView;
}
dress('rplist_rep', $itemsView, $commentListView);
dress('rplist_conform', htmlspecialchars(fireEvent('ViewCommentListHeadTitle', $commentList['title']), ENT_QUOTES | ENT_SUBSTITUTE), $commentListView);
dress('rplist_count', count($commentList['items']), $commentListView);
dress('rplist', $commentListView, $view);
?>
