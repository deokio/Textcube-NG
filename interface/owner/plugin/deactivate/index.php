<?php
/// Copyright (c) 2004-2016, Needlworks  / Tatter Network Foundation
/// All rights reserved. Licensed under the GPL.
/// See the GNU General Public License for more details. (/documents/LICENSE, /documents/COPYRIGHT)
$IV = array(
	'POST' => array(
		'name' => array('directory', 'default'=> null)
	)
);
require ROOT . '/library/preprocessor.php';
requireModel('common.plugin');
// Textcube-NG: r3-43 R-574 POST 만 받는다(recheck·resetSetting 과 같이, 다른 메서드는 405)
requirePostMethod();
requireStrictRoute();

if(empty($_POST['name'])) Respond::ResultPage(1);
// Textcube-NG: r3-42 다른 플러그인 작업이 처리 중이면(블로그별 잠금) 기다리지 않고 거절하고(켜기 실패와 같은 응답 형식), 잠근 뒤에는
// 켜진 플러그인 목록을 DB 에서 다시 읽는다(요청 처음에 읽은 목록은 앞선 요청이 끝나기 전 것일 수 있다). 잠금은 응답(exit) 때 풀린다
if (pluginOpLock(false) === false)
	Respond::ResultPage(array(1, pluginOpBusyMessage()));
pluginOpRefreshActive();
// Textcube-NG: r3-43 R-575 '마지막 편집기·포매터' 검사의 개수도 잠근 뒤 다시 읽은 켜진 목록으로 다시 센다(요청 처음의 개수는 앞선 요청이 끈 것을 모른다).
// library/plugins.php 와 같이 플러그인 검사로 막힌 것은 세지 않고, 플러그인 코드 캐시(cache/code/plugins-<blogid>.php)를 쓸 때는 원래처럼 세지 않는다
global $activePlugins;
if (!file_exists(__TEXTCUBE_CACHE_DIR__ . "/code/plugins-" . getBlogId() . ".php")) {
	$editorCount = 0;
	$formatterCount = 0;
	foreach ($activePlugins as $activePlugin) {
		if (function_exists('pluginGuardIsLoadable') && !pluginGuardIsLoadable($activePlugin)) continue;
		$activePluginInfo = getPluginInformation($activePlugin);
		if (!is_array($activePluginInfo) || !is_array($activePluginInfo['scope'])) continue;
		if (in_array('editor', $activePluginInfo['scope'])) $editorCount = $editorCount + 1;
		if (in_array('formatter', $activePluginInfo['scope'])) $formatterCount = $formatterCount + 1;
	}
	unset($activePlugin, $activePluginInfo);
}
$pluginInfo = getPluginInformation(trim($_POST['name']));
/* [보안] 서비스 관리자 전용 플러그인(<privilege>administrator</privilege>)은 서비스 관리자만 끈다 (v3.151) */
if (is_array($pluginInfo) && $pluginInfo['privilege'] == 'administrator' && !Acl::check('group.creators'))
	Respond::ResultPage(1);
// Textcube-NG: r3-42 없는 플러그인이면 getPluginInformation() 이 false 라 scope 가 null 이다. PHP 8 은 in_array() 에 배열이 아닌 값이면 TypeError(500)를 내므로
// 빈 배열로 둔다(원본 PHP 5 는 경고 뒤 null 을 돌려 editor·formatter 검사를 지나 deactivatePlugin() 의 결과로 응답했다)
$pluginScope = (is_array($pluginInfo) && is_array($pluginInfo['scope'])) ? $pluginInfo['scope'] : array();
// PHP port(Textcube-NG): r3-10 R-314 플러그인 검사(pluginGuardEvaluate)가 차단한 편집기·포매터는 불러오지 않으므로 '마지막 하나는 끌 수 없음' 검사를 건너뛴다
// (차단된 것만 남았을 때도 끌 수 있게). $pluginGuardBlocked 는 library/plugins.php 가 채우는 [플러그인 이름 => 사유] 배열
global $pluginGuardBlocked;
$pluginGuardTargetBlocked = is_array($pluginGuardBlocked) && isset($pluginGuardBlocked[trim($_POST['name'])]);
if(!$pluginGuardTargetBlocked && in_array('editor',$pluginScope) && $editorCount == 1)
	Respond::ResultPage(2);
if(!$pluginGuardTargetBlocked && in_array('formatter',$pluginScope) && $formatterCount == 1)
	Respond::ResultPage(2);
// Textcube-NG: r3-42 설정 보관 한도를 넘거나 보관 저장이 실패하면 끄지 않고(설정을 지우며 끄지 않음) 안내를 돌려준다(켜기 실패와 같은 형식 array(1, 메시지))
global $pluginKeptRefusal;
if (deactivatePlugin($_POST['name'], true))
	Respond::ResultPage(0);
if (!empty($pluginKeptRefusal))
	Respond::ResultPage(array(1, pluginKeptRefusalMessage($pluginKeptRefusal)));
Respond::ResultPage(1);
?>
