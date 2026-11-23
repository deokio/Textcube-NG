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
		'pwd' => array('string','default'=>''),
		'prevPwd' => array('string','default'=>'')
	)
);
require ROOT . '/library/preprocessor.php';
requireStrictRoute();
$result = false;
/* [보안] 현재 비밀번호 없이 변경은 인증 토큰으로 로그인한 세션(library/auth.php login 이 표시)에서, 같은 계정이고
   토큰 유효 시간(Auth::authTokenLifetime, 0 이면 제한 없음) 안일 때만 허용한다. 원본은 계정에 토큰이 남아 있으면
   (다른 방법으로 로그인한 세션도) 허용했다. 토큰은 로그인에 쓰면 지워진다(1회용). */
$isAuthToken = false;
if (isset($_SESSION['authTokenLogin']['userid']) && (intval($_SESSION['authTokenLogin']['userid']) == getUserId())) {
	$lifetime = Auth::authTokenLifetime();
	$isAuthToken = ($lifetime == 0 || (Timestamp::getUNIXtime() - intval($_SESSION['authTokenLogin']['time'])) <= $lifetime);
}
if($_POST['pwd'] != '' && (($_POST['prevPwd'] != '') || ($isAuthToken != false))) {
	// Textcube-NG: 현재 비밀번호가 틀리면 재인증과 같은 세션 횟수를 세어 한도에 이르면 로그아웃한다(v3.202)
	if (!$isAuthToken && !recordPasswordConfirmResult(User::confirmPassword(getUserId(), $_POST['prevPwd']))) Respond::ResultPage(-1);
	$result = changePassword(getUserId(), $_POST['pwd'], $_POST['prevPwd'], $isAuthToken);
}
if($result) {
	unset($_SESSION['authTokenLogin']);	// 한 번 변경하면 해제
	Respond::ResultPage(0);
}
else Respond::ResultPage(-1);
?>
