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
requirePostMethod();	// [보안] Textcube-NG: 캐시 비우기는 POST + 같은 화면 토큰만 — 원본은 GET 링크·<img> 로도 실행됐다(r3-7, 503-7)
requireStrictRoute();


if(CacheControl::flushAll(getBlogId()))
	Respond::ResultPage(0);
else 
	Respond::ResultPage(-1);
?>
