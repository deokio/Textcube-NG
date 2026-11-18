<?php
/// Copyright (c) 2004-2016, Needlworks  / Tatter Network Foundation
/// All rights reserved. Licensed under the GPL.
/// See the GNU General Public License for more details. (/documents/LICENSE, /documents/COPYRIGHT)

$ajaxcall = (isset($_REQUEST['ajaxcall']) && $_REQUEST['ajaxcall'] == true) ? true : false;	// Textcube-NG: sidebar/register 와 같게 XHR 을 ajaxcall 로 구분(r3-5)

$IV = array(
	'REQUEST' => array(
		'coverpageNumber' => array('int'),
		'modulePos' => array('int', 'default' => -1),
		'moduleId' => array('string', 'default' => ''),
		'viewMode' => array('string', 'default' => '')
		)
	);
require ROOT . '/library/preprocessor.php';
requireLibrary('blog.skin');
requireModel("blog.sidebar");
requireModel("blog.coverpage");
requireStrictRoute();

$skin = new Skin($skinSetting['skin']);
$coverpageCount = count($skin->coverpageBasicModules);

$module = explode(':', $_REQUEST['moduleId']);

if (($module !== false) && (count($module) == 3) && 
	($_REQUEST['coverpageNumber'] >= 0) 	&& ($_REQUEST['coverpageNumber'] < $coverpageCount))
{
	$coverpageOrder = getCoverpageModuleOrderData($coverpageCount);
	$coverpageOrder = addCoverpageModuleOrderData($coverpageOrder, $_REQUEST['coverpageNumber'], $_REQUEST['modulePos'], $module);
	if (!is_null($coverpageOrder)) {
		setBlogSetting("coverpageOrder", serialize($coverpageOrder));
	}
}

if ($_REQUEST['viewMode'] != '') $_REQUEST['viewMode'] = '?' . $_REQUEST['viewMode'];

// Textcube-NG: XHR(ajaxcall)에는 ResultPage XML 로 답한다. 원본은 아무 것도 보내지 않아 coverpage.js 가 onVerify 를 늘 true 로 덮어썼다(r3-5)
if ($ajaxcall == false) {
	if ($_SERVER['REQUEST_METHOD'] != 'POST')
		header('Location: '. $blogURL . '/owner/skin/coverpage' . $_REQUEST['viewMode']);
} else {
	Respond::ResultPage(0);
}
?>
