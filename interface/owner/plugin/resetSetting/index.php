<?php
/// Copyright (c) 2004-2016, Needlworks  / Tatter Network Foundation
/// All rights reserved. Licensed under the GPL.
/// See the GNU General Public License for more details. (/documents/LICENSE, /documents/COPYRIGHT)
/* Textcube-NG: r3-42 플러그인 설정 초기화. scope=active — 켜진 플러그인의 설정을 지운다(기본값). scope=kept — 꺼진 플러그인의 보관 설정을 지운다.
   범위와 지금 상태가 맞지 않으면 실패(1). 서비스 관리자 전용 플러그인은 서비스 관리자만(owner/plugin/deactivate 와 같은 검사).
   POST·CSRF 토큰(requireStrictRoute)만 받는다. 다른 플러그인 작업이 처리 중이면(블로그별 잠금) 기다리지 않고 거절한다 */
$IV = array(
	'POST' => array(
		'name' => array('directory', 'default' => null),
		'scope' => array(array('active', 'kept'))
	)
);
require ROOT . '/library/preprocessor.php';
requireModel('common.plugin');
requirePostMethod();
requireStrictRoute();

if (empty($_POST['name']))
	Respond::ResultPage(1);
$pluginInfo = getPluginInformation(trim($_POST['name']));
if (is_array($pluginInfo) && $pluginInfo['privilege'] == 'administrator' && !Acl::check('group.creators'))
	Respond::ResultPage(1);
if (pluginOpLock(false) === false)
	Respond::ResultPage(array(1, pluginOpBusyMessage()));
pluginOpRefreshActive();
if (resetPluginConfig($_POST['name'], $_POST['scope']))
	Respond::ResultPage(0);
Respond::ResultPage(1);
?>
