<?php
/// Copyright (c) 2004-2016, Needlworks  / Tatter Network Foundation
/// All rights reserved. Licensed under the GPL.
/// See the GNU General Public License for more details. (/documents/LICENSE, /documents/COPYRIGHT)
$IV = array(
	'POST' => array(
		'pathNotFoundBehavior'    => array('string', 'mandatory' => false),
		'pathNotFoundRedirectURL' => array('string', 'mandatory' => false),
	)
);
require ROOT . '/library/preprocessor.php';
requireStrictRoute();
if (!acl::check('group.creators'))
	Respond::ResultPage(false);

$allowedBehaviors = array('', 'redirect', '404');

$behavior = isset($_POST['pathNotFoundBehavior']) ? trim($_POST['pathNotFoundBehavior']) : '';
if (!in_array($behavior, $allowedBehaviors)) Respond::ResultPage(false);

$redirectURL = isset($_POST['pathNotFoundRedirectURL']) ? trim($_POST['pathNotFoundRedirectURL']) : '';
if (!empty($redirectURL) && !preg_match('@^https?://@i', $redirectURL)) Respond::ResultPage(false);

$result = Setting::setServiceSettingGlobal('pathNotFoundBehavior', $behavior);
$result = $result && Setting::setServiceSettingGlobal('pathNotFoundRedirectURL', $redirectURL);
Respond::ResultPage($result);
?>
