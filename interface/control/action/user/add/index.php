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
// [보안] Textcube-NG: $IV 는 preprocessor 보다 먼저 정해야 검사된다 — 원본은 require 뒤에 정해 입력값을 검사하지 않았다(r3-7, 459b, php8.4·8.5 와 같은 순서)
$IV = array(
	'POST' => array(
		'name' => array('string'),
		'email' => array('email')
	) 
);
require ROOT . '/library/preprocessor.php';
requirePostMethod();	// Textcube-NG: 상태 변경은 POST 만 받는다(그 밖은 405) — 링크·<img> 로 실행되던 GET CSRF 차단(v3.148)
requireStrictRoute();
requirePrivilege('group.creators');

$result = User::add($_POST['email'], $_POST['name']);
if ($result===true) {
	Respond::PrintResult(array('error' => 0));
}
else {
	Respond::PrintResult(array('error' => -1 , 'result' =>$result));
}

?>
