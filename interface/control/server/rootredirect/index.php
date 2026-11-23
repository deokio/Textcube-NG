<?php
/// Copyright (c) 2004-2016, Needlworks  / Tatter Network Foundation
/// All rights reserved. Licensed under the GPL.
/// See the GNU General Public License for more details. (/documents/LICENSE, /documents/COPYRIGHT)
$IV = array(
	'POST' => array(
		'rootRedirectURL' => array('string', 'mandatory' => false)
	)
);
require ROOT . '/library/preprocessor.php';
requireStrictRoute();
if (!acl::check('group.creators'))
	Respond::ResultPage(false);

$url = isset($_POST['rootRedirectURL']) ? trim($_POST['rootRedirectURL']) : '';
if (!empty($url) && !preg_match('@^https?://@i', $url)) {
	Respond::ResultPage(false);
}
$result = Setting::setServiceSettingGlobal('rootRedirectURL', $url);
Respond::ResultPage($result);
?>
