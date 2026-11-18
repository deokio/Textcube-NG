<?php
/// Copyright (c) 2004-2016, Needlworks  / Tatter Network Foundation
/// All rights reserved. Licensed under the GPL.
/// See the GNU General Public License for more details. (/documents/LICENSE, /documents/COPYRIGHT)
/* Textcube-NG: 무결성 검사 면제 설정(r3-14, owner/plugin 목록 보기의 '무결성 검사 없이 항상 허용'). 서비스 설정 pluginIntegrityExempt 에 이름을 넣거나 뺀다.
   서비스 관리자(group.creators)만. control/plugin/allow/ 와 같은 틀(POST·CSRF·creators, framework/boot/30-Auth.php 의 /control 제한도 같음).
   이름은 이름 규칙(줄 끝 포함 /D)·plugins 폴더·index.xml 이 있어야 한다(pluginGuardSetExempt 가 다시 확인). 엄격 모드에서도 켜고 끌 수 있다.
   다른 이름은 지금 저장값(읽을 때 다시 검사한 pluginGuardExemptList(true))을 그대로 둔다. */
$IV = array(
	'POST' => array(
		'name' => array('directory', 'default' => null),
		'exempt' => array(array('0', '1'))
	)
);
require ROOT . '/library/preprocessor.php';
requirePostMethod();
requireStrictRoute();
if (!Acl::check('group.creators'))
	Respond::ResultPage(false);

requireModel('common.plugin');
$name = isset($_POST['name']) ? $_POST['name'] : null;
if (!is_string($name) || preg_match('/^[-a-zA-Z0-9_ ]+$/D', $name) !== 1 || !pluginGuardNameIsValid($name)
	|| !is_dir(ROOT . '/plugins/' . $name) || !is_file(ROOT . '/plugins/' . $name . '/index.xml'))
	Respond::ResultPage(1);

$on = (isset($_POST['exempt']) && $_POST['exempt'] === '1');
$result = pluginGuardSetExempt($name, $on);
clearPluginSettingCache();
Respond::ResultPage($result ? 0 : 1);
?>
