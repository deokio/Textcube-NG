<?php
/// Copyright (c) 2004-2016, Needlworks  / Tatter Network Foundation
/// All rights reserved. Licensed under the GPL.
/// See the GNU General Public License for more details. (/documents/LICENSE, /documents/COPYRIGHT)
// Textcube-NG: sitemaps.org 형식 XML 사이트맵(/sitemap)(r3-20)
//   - 공개 블로그(blog visibility 2)만 낸다. 비공개·구성원 공개 블로그와 등록되지 않은 Host·포트(uri.isRegisteredOrigin)는 404.
//   - 첫 화면, 방명록, 공개 글(공개 분류·분류 없음, 비공개 분류의 하위 분류 제외), 공지를 싣는다. 주소는 RSS 와 같은 $defaultURL 바탕.
//   - 한 파일 50000 개(sitemaps.org 상한)를 넘으면 /sitemap 은 sitemapindex 를 내고 각 쪽은 /sitemap?page=N 으로 낸다. 캐시는 두지 않는다.

define('NO_SESSION', true);
define('__TEXTCUBE_LOGIN__',true);
define('__TEXTCUBE_CUSTOM_HEADER__', true);

require ROOT . '/library/preprocessor.php';
requireModel("blog.entry");
requireModel("blog.category");
requireStrictBlogURL();
$context = Model_Context::getInstance();
if ($context->getProperty('uri.isRegisteredOrigin') !== true) Respond::NotFoundPage();
if (Setting::getBlogSettingGlobal('visibility', 2) != 2) Respond::NotFoundPage();
publishEntries();

$sitemapPerFile = 50000;
$sitemapBase = rtrim($defaultURL, '/');
$sitemapNow = Timestamp::getUNIXtime();
$sitemapFrom = "FROM {$database['prefix']}Entries e LEFT JOIN {$database['prefix']}Categories c ON e.blogid = c.blogid AND e.category = c.id";
$sitemapWhere = "e.blogid = $blogid AND e.draft = 0 AND e.visibility >= 2 AND e.published <= $sitemapNow"
	. " AND ((e.category >= 0 AND (c.visibility > 1 OR e.category = 0)" . getPrivateCategoryExclusionQuery($blogid) . ") OR e.category = -2)";
$sitemapTotal = 2 + (int) POD::queryCell("SELECT COUNT(*) $sitemapFrom WHERE $sitemapWhere");
$sitemapPages = (int) ceil($sitemapTotal / $sitemapPerFile);
$sitemapHasPage = isset($_GET['page']) || isset($_POST['page']);
$sitemapPage = $suri['page'];
if ($sitemapHasPage && (!is_string($sitemapPage) || !preg_match('/^[0-9]+$/D', $sitemapPage) || (int) $sitemapPage < 1 || (int) $sitemapPage > $sitemapPages))
	Respond::NotFoundPage();

header('Content-Type: application/xml; charset=utf-8');
header('X-Content-Type-Options: nosniff');
$sitemapXml = function ($s) { return htmlspecialchars((string) $s, ENT_QUOTES | ENT_XML1, 'UTF-8'); };
echo '<?xml version="1.0" encoding="UTF-8"?>' . "\n";
if ($sitemapPages > 1 && !$sitemapHasPage) {
	echo '<sitemapindex xmlns="http://www.sitemaps.org/schemas/sitemap/0.9">' . "\n";
	for ($i = 1; $i <= $sitemapPages; $i++)
		echo "\t<sitemap><loc>" . $sitemapXml($sitemapBase . '/sitemap?page=' . $i) . "</loc></sitemap>\n";
	echo "</sitemapindex>\n";
	exit;
}
$sitemapPageNo = $sitemapHasPage ? (int) $sitemapPage : 1;
$sitemapOffset = ($sitemapPageNo - 1) * $sitemapPerFile;
$sitemapLimit = $sitemapPerFile;
echo '<urlset xmlns="http://www.sitemaps.org/schemas/sitemap/0.9">' . "\n";
if ($sitemapPageNo === 1) {
	echo "\t<url><loc>" . $sitemapXml($sitemapBase . '/') . "</loc></url>\n";
	echo "\t<url><loc>" . $sitemapXml($sitemapBase . '/guestbook') . "</loc></url>\n";
	$sitemapLimit -= 2;
} else {
	$sitemapOffset -= 2;
}
$sitemapRows = POD::queryAll("SELECT e.id, e.slogan, e.category, e.modified $sitemapFrom WHERE $sitemapWhere ORDER BY e.published DESC, e.id DESC LIMIT " . (int) $sitemapLimit . " OFFSET " . (int) $sitemapOffset);
if (is_array($sitemapRows)) {
	foreach ($sitemapRows as $sitemapRow) {
		// 글 주소는 interface/common/blog/entries.php 의 permalink 와 같은 규칙
		$sitemapTail = $blog['useSloganOnPost'] ? URL::encode($sitemapRow['slogan'], $service['useEncodedURL']) : $sitemapRow['id'];
		if ((int) $sitemapRow['category'] === -2)
			$sitemapPath = '/notice/' . $sitemapTail;
		else
			$sitemapPath = '/' . ($blog['useSloganOnPost'] ? 'entry/' . $sitemapTail : $sitemapTail);
		echo "\t<url><loc>" . $sitemapXml($sitemapBase . $sitemapPath) . "</loc><lastmod>" . $sitemapXml(Timestamp::getISO8601($sitemapRow['modified'])) . "</lastmod></url>\n";
	}
}
echo "</urlset>\n";
