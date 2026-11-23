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
requireModel('blog.entry');

// Textcube-NG: 스킨 목록·적용(selectSkin)은 디렉토리 이름에 '-' 를 허용하는데 원본은 파일 이름 규칙(Validator::filename)만 받아
// 이름에 '-' 가 든 스킨의 미리보기가 404 였다(pat.im 1336). 디렉토리 이름 규칙(Validator::directory, '/'·'.'·'..' 불가)도 받는다.
// 문자열이 아니면(skin[]=…) PHP 8 에서 preg_match() TypeError(HTTP 500), 없으면 경고가 나므로 먼저 404(v3.139)
if(!isset($_GET['skin']) || !is_string($_GET['skin'])
	|| (!Validator::filename($_GET['skin']) && !Validator::directory($_GET['skin']) && $_GET['skin'] != "customize/$blogid"))
	Respond::NotFoundPage();
$skinSetting['skin'] = $_GET['skin'];
$skin = new Skin($skinSetting['skin'], true);
list($entries, $paging) = getEntriesWithPaging($blogid, $suri['page'], $blog['entriesOnPage']);

require ROOT . '/interface/common/blog/begin.php';
require ROOT . '/interface/common/blog/entries.php';

$pageTitle = _t('스킨 미리보기');

require ROOT . '/interface/common/blog/end.php';
?>
