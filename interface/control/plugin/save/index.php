<?php
/// Copyright (c) 2004-2016, Needlworks  / Tatter Network Foundation
/// All rights reserved. Licensed under the GPL.
/// See the GNU General Public License for more details. (/documents/LICENSE, /documents/COPYRIGHT)
/* Textcube-NG: 플러그인 허용 목록·무결성 엄격 모드 저장(v3.211). 서비스 관리자(group.creators)만.
   server/taglimit/ 와 같은 형식. 값은 서비스 설정 pluginAllowlistEnabled('1'/'0')·pluginAllowlist(JSON 배열)·pluginIntegrityStrictEnabled('1'/'0', 기본 '0') */
$IV = array(
	'POST' => array(
		'enabled' => array('int', 0, 1, 'default' => 0),
		'strict' => array('int', 0, 1, 'default' => 0),
		'allowlist' => array('string', 'default' => ''),
		'noticeLimit' => array('int', 1, 200, 'default' => 20)	// Textcube-NG: 대시보드 플러그인 알림 저장 개수(v3.219)
	)
);
require ROOT . '/library/preprocessor.php';
requirePostMethod();
requireStrictRoute();
if (!acl::check('group.creators'))
	Respond::ResultPage(false);

requireModel('common.plugin');
$allowlist = array();
$decoded = json_decode((string)$_POST['allowlist'], true);
if (is_array($decoded)) {
	foreach ($decoded as $name) {
		if (is_string($name) && pluginGuardNameIsValid($name) && is_dir(ROOT . '/plugins/' . $name)) {
			$allowlist[] = $name;
		}
	}
}
$allowlist = array_values(array_unique($allowlist));
sort($allowlist, SORT_STRING);

$result1 = Setting::setServiceSettingGlobal('pluginAllowlistEnabled', (intval($_POST['enabled']) === 1) ? '1' : '0');
$result2 = Setting::setServiceSettingGlobal('pluginAllowlist', json_encode($allowlist));
$result3 = Setting::setServiceSettingGlobal('pluginIntegrityStrictEnabled', (intval($_POST['strict']) === 1) ? '1' : '0');
// Textcube-NG: 대시보드 플러그인 알림 저장 개수(서비스 설정 pluginNoticeLimit, 1~200, 기본 20)(v3.219)
$result4 = Setting::setServiceSettingGlobal('pluginNoticeLimit', intval($_POST['noticeLimit']));
clearPluginSettingCache();
Respond::ResultPage(($result1 && $result2 && $result3 && $result4) ? 0 : 1);
?>
