<?php
/// Copyright (c) 2004-2016, Needlworks  / Tatter Network Foundation
/// All rights reserved. Licensed under the GPL.
/// See the GNU General Public License for more details. (/documents/LICENSE, /documents/COPYRIGHT)
/* Textcube-NG: 플러그인 하나를 서비스 허용 목록(pluginAllowlist)에 더한다(r3-13, owner/plugin 차단 경고 상자의 '허용 목록에 추가').
   서비스 관리자(group.creators)만. control/plugin/save/ 와 같은 틀(POST·CSRF·creators). 이름은 이름 규칙(줄 끝 포함 /D)·plugins 폴더·index.xml 이 있어야 한다.
   허용 목록 사용 여부(pluginAllowlistEnabled)는 바꾸지 않는다. 다른 이름은 지금 저장값(pluginGuardAllowlist(true))을 그대로 둔다. */
$IV = array(
	'POST' => array(
		'name' => array('directory', 'default' => null)
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

$allow = pluginGuardAllowlist(true);
$allowlist = $allow[1];
$allowlist[] = $name;
$allowlist = array_values(array_unique($allowlist));
sort($allowlist, SORT_STRING);
$result = Setting::setServiceSettingGlobal('pluginAllowlist', json_encode($allowlist));
clearPluginSettingCache();
Respond::ResultPage($result ? 0 : 1);
?>
