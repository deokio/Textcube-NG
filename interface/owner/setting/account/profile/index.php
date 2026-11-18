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
$IV = array(
	'POST' => array(
		'email'    => array('email'),
		'nickname' => array('string'),
		'confirmativePassword' => array('string', 'mandatory' => false)
	)
);
require ROOT . '/library/preprocessor.php';
requireStrictRoute();
// Textcube-NG: 로그인 ID(e-mail)를 바꿀 때는 현재 비밀번호를 다시 확인한다(필명만 바꿀 때는 그대로), 틀리면 9(v3.149)
if (strcmp($_POST['email'], (string)User::getEmail(getUserId())) != 0
	&& !isReauthenticated(isset($_POST['confirmativePassword']) ? $_POST['confirmativePassword'] : null))
	Respond::ResultPage(9);
if (User::changeSetting(getUserId(), $_POST['email'], $_POST['nickname'])) {
	Respond::ResultPage(0);
}
Respond::ResultPage( - 1);
?>
