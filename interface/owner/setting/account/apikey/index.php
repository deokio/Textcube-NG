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
$IV = array(
	'POST' => array(
		'APIKey' => array('string', 'default'=>''),
		'confirmativePassword' => array('string', 'mandatory' => false)
	)
);
require ROOT . '/library/preprocessor.php';
requireStrictRoute();
if (!isReauthenticated(isset($_POST['confirmativePassword']) ? $_POST['confirmativePassword'] : null))	// Textcube-NG: API 키(비밀번호 대용) 변경 전 비밀번호 재확인, 틀리면 9(v3.149)
	Respond::ResultPage(9);
$result = false;
$result = changeAPIKey(getUserId(), $_POST['APIKey']);
if($result) Respond::ResultPage(0);
else Respond::ResultPage(-1);
?>
