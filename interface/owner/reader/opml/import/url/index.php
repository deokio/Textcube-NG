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
// Textcube-NG: 원본은 입력 규칙 변수 이름이 $VI 로 잘못 적혀 preprocessor 의 입력 검사(Validator::validate($IV))가 동작하지 않았다(v3.144)
$IV = array(
	'POST' => array(
		'url' => array('url')
	)
);
require ROOT . '/library/preprocessor.php';
requireStrictRoute();
set_time_limit(60);
$result = importOPMLFromURL($blogid, $_POST['url']);
Respond::PrintResult($result);
?>
