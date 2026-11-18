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

require ROOT . '/library/preprocessor.php';

/* [보안] 플러그인 관리 화면의 설정 저장(POST)에 출처 검사가 없었다(adminHandler 와 다름) → POST 는 같은 블로그에서 온 요청만 허용 */
if (isset($_SERVER['REQUEST_METHOD']) && $_SERVER['REQUEST_METHOD'] == 'POST')
	requireStrictRoute();

/* Textcube-NG: 서비스 관리(/control) 메뉴에 붙는 플러그인(<topMenu>control</topMenu>)의 관리 화면은 서비스 관리자만 연다 —
   /control 핸들러와 같이 requirePrivilege('group.creators')(아니면 404) 뒤 /control 화면 형식(control header·footer)으로 보인다 (v3.206) */
$useControlPanel = isset($_REQUEST['name']) && is_string($_REQUEST['name']) && isset($adminMenuMappings[$_REQUEST['name']])
	&& $adminMenuMappings[$_REQUEST['name']]['topMenu'] == 'control';
if ($useControlPanel)
	requirePrivilege('group.creators');
require ROOT . '/interface/common/' . ($useControlPanel ? 'control' : 'owner') . '/header.php';

/* [보안] 서비스 관리자 전용 플러그인(<privilege>administrator</privilege>)의 관리 화면은 서비스 관리자만 — 메뉴 목록(header.php)과
   같은 기준을 화면 주소에도 적용한다 (v3.151) */
// Textcube-NG: name 이 배열이면 PHP 8 에서 isset($adminMenuMappings[배열]) 이 TypeError(원본 5.6 은 경고 뒤 false) — 22줄과 같이 문자열인지 먼저 본다 (v3.221)
if ((isset($_REQUEST['name'])) && is_string($_REQUEST['name']) && (isset($adminMenuMappings[$_REQUEST['name']]))
	&& !(isset($adminMenuMappings[$_REQUEST['name']]['privilege']) && $adminMenuMappings[$_REQUEST['name']]['privilege'] == 'administrator'
		&& !Acl::check('group.creators')))
{

	$IV = array (
		'REQUEST' => array(
			'name' => array('string')
			)
		);

	foreach($adminMenuMappings[$_REQUEST['name']]['params'] as $param) {
		$ivItem = array ( $param['type']);
		if (isset($param['default']) && !is_null($param['default']) ) $ivItem['default'] = $param['default'];
		if (isset($param['mandatory']) && !is_null($param['mandatory']) ) $ivItem['mandatory'] = $param['mandatory'];

		$IV['REQUEST'][$param['name']] = $ivItem;
	}

	if (Validator::validate($IV)) {
		$context = Model_Context::getInstance();
		$_GET = $_POST = $_REQUEST;
		$plugin = $adminMenuMappings[$_REQUEST['name']]['plugin'];
		$handler = $adminMenuMappings[$_REQUEST['name']]['handler'];

		$pluginAccessURL = $blogURL . '/owner/plugin/adminMenu?name=' . $plugin;
		$pluginMenuURL = $blogURL . '/owner/plugin/adminMenu?name=' . $plugin . '/' . $handler;
		$pluginHandlerURL = $blogURL . '/owner/plugin/adminHandler?name=' . $plugin;
		$pluginSelfURL = $pluginMenuURL;

		$pluginAccessParam = '?name=' . $plugin;
		$pluginSelfParam = '?name=' . $plugin . '/' . $handler;

		$context->setProperty('plugin.uri.access',$pluginAccessURL);
		$context->setProperty('plugin.uri.menu',$pluginMenuURL);
		$context->setProperty('plugin.uri.handler',$pluginHandlerURL);
		$context->setProperty('plugin.uri.self',$pluginSelfURL);
		$context->setProperty('plugin.parameter.access',$pluginAccessParam);
		$context->setProperty('plugin.parameter.self',$pluginSelfParam);
		$context->setProperty('plugin.uri',$context->getProperty('service.path')."/plugins/{$plugin}");
		$context->setProperty('plugin.path',ROOT . "/plugins/{$plugin}");
		$context->setProperty('plugin.name',$plugin);

		$pluginURL = $context->getProperty('plugin.uri');
		$pluginPath = $context->getProperty('plugin.path');
		$pluginName = $context->getProperty('plugin.name');

		// Loading locale resource
		$languageDomain = null;
		if(is_dir($pluginPath . '/locale/')) {
			$locale = Locales::getInstance();
			$languageDomain = $locale->domain;
			if(file_exists($pluginPath.'/locale/'.$locale->defaultLanguage.'.php')) {
				$locale->setDirectory($pluginPath.'/locale');
				$locale->set($locale->defaultLanguage, $pluginName);
				$locale->domain = $pluginName;
			}
		}
		include_once (ROOT . "/plugins/{$plugin}/index.php");
		if (function_exists($handler)) {
			if( !empty( $configMappings[$plugin]['config'] ) ) {
				$configVal = getCurrentSetting($plugin);
				$context->setProperty('plugin.config',Setting::fetchConfigVal($configVal));
			} else {
				$configVal ='';
				$context->setProperty('plugin.config',array());
			}
			call_user_func($handler);
		}
		/// unload.
		if(!is_null($languageDomain)) $locale->domain = $languageDomain;
		$context->unsetProperty('plugin.uri');
		$context->unsetProperty('plugin.path');
		$context->unsetProperty('plugin.name');
		$context->unsetProperty('plugin.uri.access');
		$context->unsetProperty('plugin.uri.menu');
		$context->unsetProperty('plugin.uri.handler');
		$context->unsetProperty('plugin.uri.self');
		$context->unsetProperty('plugin.parameter.access');
		$context->unsetProperty('plugin.parameter.self');
	}
}
require ROOT . '/interface/common/' . ($useControlPanel ? 'control' : 'owner') . '/footer.php';	/* Textcube-NG: header 와 같은 쪽 (v3.206) */
?>
