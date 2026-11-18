<?php
/// Copyright (c) 2004-2016, Needlworks  / Tatter Network Foundation
/// All rights reserved. Licensed under the GPL.
/// See the GNU General Public License for more details. (/documents/LICENSE, /documents/COPYRIGHT)
/* Textcube-NG: r3-42 다시 검사(켜진 플러그인만). 끄지 않고 지금 파일로 무결성 기록을 새로 남긴다 — Plugins 행·설정은 그대로 둔다.
   켜기와 같은 검사(pluginActivationGate)를 거친 뒤 기준값을 지우고(pluginGuardRemoveBaseline) 새로 남긴다(pluginGuardBuildRecord).
   POST·CSRF 토큰(requireStrictRoute)만 받는다. 다른 플러그인 작업이 처리 중이면(블로그별 잠금) 기다리지 않고 거절한다 */
$IV = array(
	'POST' => array(
		'name' => array('directory', 'default' => null)
	)
);
require ROOT . '/library/preprocessor.php';
requireModel('common.plugin');
requirePostMethod();
requireStrictRoute();

if (empty($_POST['name']))
	Respond::ResultPage(1);
if (pluginOpLock(false) === false)
	Respond::ResultPage(array(1, pluginOpBusyMessage()));
pluginOpRefreshActive();
if (recheckPlugin($_POST['name']))
	Respond::ResultPage(pluginGuardResultCode(0));
Respond::ResultPage(pluginGuardResultCode(1));
?>
