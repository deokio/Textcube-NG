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

require ROOT . '/library/preprocessor.php';
requirePostMethod();	// Textcube-NG: 상태 변경은 POST 만 받는다(그 밖은 405) — 링크·<img> 로 실행되던 GET CSRF 차단(v3.148)

requireModel('blog.trash');
requireModel('blog.response.remote');
requireModel('blog.sidebar');
requireLibrary('blog.skin');

requireStrictRoute();
requireCSRFToken();	// Textcube-NG: blog 인터페이스라 preprocessor 의 토큰 검사가 없어 직접 요구한다(v3.148)
/* [보안] 원본은 소유 권한을 확인하지 않아 로그인하지 않은 요청으로도 걸린 글(트랙백)을 휴지통으로 옮길 수 있었다 →
   블로그 구성원만(owner 쪽 communication/trackback/delete 와 같은 범위) */
if (!doesHaveOwnership()) {
	Respond::PrintResult(array('error' => 1));
}
$blogid = getBlogId();
$suri['id'] = (int)$suri['id'];
$entryId = trashTrackback($blogid, $suri['id']);
if ($entryId !== false) {
	$skin = new Skin($skinSetting['skin']);
	
	$trackbackCount = getTrackbackCount($blogid, $entryId);
	list($tempTag, $trackbackCountContent) = getTrackbackCountPart($trackbackCount, $skin);
	$recentTrackbackContent = getRecentTrackbacksView(getRecentTrackbacks($blogid), $skin->recentTrackback, $skin->recentTrackbackItem);
	$entry = array();
	$entry['id'] = $entryId;
	$entry['slogan'] = getSloganById($blogid, $entry['id']);
	$trackbackListContent = getTrackbacksView($entry, $skin, true);
}
if ($trackbackListContent === false)
	Respond::PrintResult(array('error' => 1));
else
	Respond::PrintResult(array('error' => 0, 'trackbackList' => $trackbackListContent, 'trackbackCount' => $trackbackCountContent, 'recentTrackbacks' => $recentTrackbackContent));
?>
