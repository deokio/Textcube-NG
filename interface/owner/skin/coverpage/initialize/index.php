<?php
/// Copyright (c) 2004-2016, Needlworks  / Tatter Network Foundation
/// All rights reserved. Licensed under the GPL.
/// See the GNU General Public License for more details. (/documents/LICENSE, /documents/COPYRIGHT)
///
/// ---- Modification Notice (GPL §2(a)) ----
/// Modified 2026 by @deokio for PHP 8.5 compatibility,
/// performed with AI assistance (Anthropic Claude) under human review.
/// Changes consist primarily of mechanical PHP migration transformations
/// per the official PHP upgrade documentation.
/// No additional copyright is asserted over these modifications.
/// See CHANGELOG.md and SECURITY.md for full modification history.
// [보안] Textcube-NG: viewMode 는 safe·tag 만 받는다 — 원본은 검사 없이 Location 헤더로 돌려보냈다(r3-7, 결정 455h)
$IV = array(
	'REQUEST' => array(
		'viewMode' => array(array('safe', 'tag'), 'mandatory' => false)
	)
);
require ROOT . '/library/preprocessor.php';
requirePostMethod();	// Textcube-NG: 상태 변경은 POST 만 받는다(그 밖은 405) — 링크·<img> 로 실행되던 GET CSRF 차단(v3.148)
requireStrictRoute();

if (!array_key_exists('viewMode', $_REQUEST)) $_REQUEST['viewMode'] = '';
else $_REQUEST['viewMode'] = '?' . $_REQUEST['viewMode'];

POD::execute("DELETE FROM `{$database['prefix']}BlogSettings` WHERE `blogid` = {$blogid} AND `name` = 'coverpageOrder'");
header('Location: '. $blogURL . '/owner/skin/coverpage' . $_REQUEST['viewMode']);
?>
