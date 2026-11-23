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

$IV = array(
	'GET' => array(
		'requestURI' => array('string', 'default' => null)
	),
	'POST' => array(
		'requestURI' => array('string', 'default' => null)
	)
);
define('__TEXTCUBE_LOGIN__',true);
require ROOT . '/library/preprocessor.php';
$ctx = Model_Context::getInstance();
$userURL = $ctx->getProperty('user.homepage');
$blogURL = $ctx->getProperty('uri.blog');
if (substr($blogURL, -1) != '/') $blogURL .= '/';
if (!isset($userURL) ) $userURL = '/';
if (substr($userURL, -1) != '/') $userURL .= '/';

if (isset($_GET['requestURI']))
	$_POST['requestURI'] = $_GET['requestURI'];
if (doesHaveMembership()) {
	if (!empty($_POST['requestURI']))
		$returnURL = getSafeRedirectURI($_POST['requestURI'], $blogURL);
	else
		$returnURL = $blogURL;
} else {
	$returnURL = $blogURL;
}
logout();
header("Location: $returnURL");
?>
