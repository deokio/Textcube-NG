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
requireModel("blog.feed");
requireModel("blog.entry");
requireStrictBlogURL();
publishEntries();
// Textcube-NG: r3-27 R-435 — 등록되지 않은 Host·포트(uri.isRegisteredOrigin === false, PageCache 와 같은 판정)의 요청은 피드 캐시 파일을 읽지도 쓰지도 않고 매번 만든다(위조 Host 의 URL 이 cache/rss 에 굳어 모든 구독자에게 나가는 것을 막음)
if (Model_Context::getInstance()->getProperty('uri.isRegisteredOrigin') === false) {
	header('Content-Type: application/rss+xml; charset=utf-8');
	$result = refreshFeed($blogid, 'rss', true);
	if (!is_string($result)) $result = '';
} else {
	if (!file_exists(__TEXTCUBE_CACHE_DIR__."/rss/$blogid.xml"))
		refreshFeed($blogid,'rss');
	header('Content-Type: application/rss+xml; charset=utf-8');
	$fileHandle = fopen(__TEXTCUBE_CACHE_DIR__."/rss/$blogid.xml", 'r+');
	$result = fread($fileHandle, filesize(__TEXTCUBE_CACHE_DIR__."/rss/$blogid.xml"));
	fclose($fileHandle);
}
fireEvent('FeedOBStart');
echo fireEvent('ViewRSS', $result);
fireEvent('FeedOBEnd');

requireModel("blog.cron");
checkCronJob();
?>
