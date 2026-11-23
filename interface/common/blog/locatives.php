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

$locativeView = '[##_SKIN_local_rep_start_##]';
$prevPath = array();
foreach ($locatives as $locative) {
	$path = explode('/', $locative['location']);
	array_shift($path);
	for ($depth = $i = 0; $i < count($path); $i++, $depth += 20) {
		$spotView = $skin->locativeSpot;
		dress('local_spot_depth', $depth, $spotView);
		dress('local_spot', htmlspecialchars($path[$i], ENT_QUOTES | ENT_SUBSTITUTE), $spotView);
		if (empty($prevPath[$i]) || ($path[$i] != $prevPath[$i]))
			$locativeView .= $spotView;
	}
	$entryView = $skin->locativeEntry;
	dress('local_info_depth', $depth + 20, $entryView);
	dress('local_info_link', "$blogURL/" . ($blog['useSloganOnPost'] ? 'entry/' . URL::encode($locative['slogan'],$service['useEncodedURL']) : $locative['id']), $entryView);
	dress('local_info_title', htmlspecialchars($locative['title'], ENT_QUOTES | ENT_SUBSTITUTE), $entryView);
	dress('local_info_author', User::getName($locative['userid']), $entryView);
	$locativeView .= $entryView;
	$prevPath = $path;
}
dress('local', '[##_SKIN_local_start_##]'.str_replace('[##_local_spot_rep_##]', $locativeView, $skin->locative).'[##_SKIN_local_end_##]', $view);
?>
