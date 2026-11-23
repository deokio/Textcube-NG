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
$ajaxcall= false;
if (isset($_REQUEST['ajaxcall'])) {
	$ajaxcall= true;
}


/*$IV = array(
	'REQUEST' => array(
		'coverpageNumber' => array('int'),
		'modulePos' => array('int'),
		)
	);*/
	
if (!array_key_exists('viewMode', $_REQUEST)) $_REQUEST['viewMode'] = '';

require ROOT . '/library/preprocessor.php';
requireModel("blog.coverpage");

requireStrictRoute();

$coverpageOrderData = getCoverpageModuleOrderData();

if (!isset($_REQUEST['coverpageNumber']) || !is_numeric($_REQUEST['coverpageNumber'])) Respond::NotFoundPage($ajaxcall);	// Textcube-NG: 실패 응답에도 $ajaxcall 을 넘긴다(sidebar/setPlugin 과 같게, 원본은 AJAX 요청에 HTML 페이지로 답함, r3-15)
if (!isset($_REQUEST['modulePos']) || !is_numeric($_REQUEST['modulePos'])) Respond::NotFoundPage($ajaxcall);

$coverpageNumber = $_REQUEST['coverpageNumber'];
$modulePos = $_REQUEST['modulePos'];

if (($coverpageNumber < 0)) Respond::ErrorPage(null,null,null,$ajaxcall);
if (!isset($coverpageOrderData[$coverpageNumber]) || !isset($coverpageOrderData[$coverpageNumber][$modulePos])) Respond::ErrorPage(null,null,null,$ajaxcall);

$pluginData = $coverpageOrderData[$coverpageNumber][$modulePos];
if ($pluginData['type'] != 3) Respond::ErrorPage(null,null,null,$ajaxcall);

$plugin = $pluginData['id']['plugin'];
$handler = $pluginData['id']['handler'];
$oldParameters = $pluginData['parameters'];

$identifier = $plugin . '/' . $handler;

$parameters = array();
foreach($coverpageMappings as $item) {
	if (($item['plugin'] == $plugin) && ($item['handler'] == $handler)) {
		$parameters = $item['parameters'];
		break;
	}
}

$newParameter = array();

foreach($parameters as $item)
{
	if (isset($_REQUEST[$item['name']])) {
		switch($item['type']) {
			case 'string':
				break;
			case 'int':
				/* [보안] 원본의 숫자 검사는 'continue;'(switch 안에서는 break 와 같음, 이식본은 break 로 표기)라 무효였다
				   → 숫자가 아니면 저장하지 않고, 숫자면 정수로 저장(위젯 플러그인이 이 값을 SQL LIMIT 등에 그대로 쓰는 경우 대비) */
				if (!is_numeric($_REQUEST[$item['name']])) {
					continue 2;
				}
				$newParameter[$item['name']] = (int)$_REQUEST[$item['name']];
				continue 2;
			default:
				break;
		}	
        $newParameter[$item['name']] = $_REQUEST[$item['name']];
	}
}
$eventName = 'ModifyPluginParam_'.$plugin;
fireEvent($eventName,null,$plugin);
$coverpageOrderData[$coverpageNumber][$modulePos]['parameters'] = $newParameter;
setBlogSetting("coverpageOrder", serialize($coverpageOrderData));

if ($ajaxcall == false) {
	if ($_REQUEST['viewMode'] != '') $_REQUEST['viewMode'] = '?' . $_REQUEST['viewMode'];
	header('Location: '. $blogURL . '/owner/skin/coverpage' . $_REQUEST['viewMode']);
} else {	// Textcube-NG: XHR(ajaxcall)에는 ResultPage XML 로 답한다(coverpage.js 의 onVerify 덮어쓰기를 지움)(r3-5)
	Respond::ResultPage(0);
}
?>
