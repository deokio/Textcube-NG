<?php
/// Copyright (c) 2004-2016, Needlworks  / Tatter Network Foundation
/// All rights reserved. Licensed under the GPL.
/// See the GNU General Public License for more details. (/documents/LICENSE, /documents/COPYRIGHT)
$IV = array(
	'POST' => array(
		'confirmativePassword' => array('string', 'mandatory' => false),
		'removeAttachments' => array(array('0', '1'), 'dafault' => null)
	)
);
require ROOT . '/library/preprocessor.php';
requireStrictRoute();
if (empty($_POST['confirmativePassword']))
	Respond::ResultPage(1);
// Textcube-NG: 현재 비밀번호가 틀리면 재인증과 같은 세션 횟수를 세어 한도에 이르면 로그아웃한다(v3.202)
if (!recordPasswordConfirmResult(User::confirmPassword(User::getBlogOwner(getBlogId()), $_POST['confirmativePassword'])))
	Respond::ResultPage(1);
DataMaintenance::removeAll(Validator::getBool(@$_POST['removeAttachments']));
CacheControl::flushAll();
Respond::ResultPage(0);
?>
