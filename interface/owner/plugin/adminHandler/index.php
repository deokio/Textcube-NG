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

requireStrictRoute();

if (isset($_REQUEST['name']) && is_string($_REQUEST['name']) && isset($adminHandlerMappings[$_REQUEST['name']]))	// Textcube-NG: name 은 문자열만, 확인한 $_REQUEST['name'] 으로 핸들러를 찾는다(원본은 아래에서 $_GET['name'] 을 다시 읽었다)(r3-7, 449a)
{

	$IV = array (
		'REQUEST' => array(
			'name' => array('string')
			)
		);

	foreach($adminHandlerMappings[$_REQUEST['name']]['params'] as $param) {
		$ivItem = array ( $param['type']);
		if (isset($param['default']) && !is_null($param['default']) ) $ivItem['default'] = $param['default'];
		if (isset($param['mandatory']) && !is_null($param['mandatory']) ) $ivItem['mandatory'] = $param['mandatory'];

		$IV['REQUEST'][$param['name']] = $ivItem;
	}

	if (Validator::validate($IV)) {
		$context = Model_Context::getInstance();

		$plugin = $adminHandlerMappings[$_REQUEST['name']]['plugin'];
		$handler = $adminHandlerMappings[$_REQUEST['name']]['handler'];

		$pluginAccessURL = $blogURL . '/owner/plugin/adminMenu?name=' . $plugin;
		$pluginMenuURL = 'invalid link';
		$pluginHandlerURL = $blogURL . '/owner/plugin/adminHandler?name=' . $plugin;
		$pluginSelfURL = $blogURL . '/owner/plugin/adminHandler?name=' . $plugin . '/' . $handler;

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

?>
