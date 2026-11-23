<?php
/// Copyright (c) 2004-2016, Needlworks  / Tatter Network Foundation
/// All rights reserved. Licensed under the GPL.
/// See the GNU General Public License for more details. (/documents/LICENSE, /documents/COPYRIGHT)
$IV = array(
	'POST' => array(
		'domainRootBehavior'      => array('string', 'mandatory' => false),
		'domainRootRedirectURL'   => array('string', 'mandatory' => false),
		'domainRootBlogId'        => array('string', 'mandatory' => false),
		'domainMismatchBehavior'  => array('string', 'mandatory' => false),
		'domainMismatchRedirectURL' => array('string', 'mandatory' => false),
		'domainMismatchBlogId'    => array('string', 'mandatory' => false),
	)
);
require ROOT . '/library/preprocessor.php';
requireStrictRoute();
if (!acl::check('group.creators'))
	Respond::ResultPage(false);

$allowedBehaviors = array('', 'redirect', 'blog', '404');

$rootBehavior = isset($_POST['domainRootBehavior']) ? trim($_POST['domainRootBehavior']) : '';
if (!in_array($rootBehavior, $allowedBehaviors)) Respond::ResultPage(false);

$rootRedirectURL = isset($_POST['domainRootRedirectURL']) ? trim($_POST['domainRootRedirectURL']) : '';
if (!empty($rootRedirectURL) && !preg_match('@^https?://@i', $rootRedirectURL)) Respond::ResultPage(false);

$rootBlogId = isset($_POST['domainRootBlogId']) ? trim($_POST['domainRootBlogId']) : '';
if (!empty($rootBlogId) && !ctype_digit($rootBlogId)) Respond::ResultPage(false);

$mismatchBehavior = isset($_POST['domainMismatchBehavior']) ? trim($_POST['domainMismatchBehavior']) : '';
if (!in_array($mismatchBehavior, $allowedBehaviors)) Respond::ResultPage(false);

$mismatchRedirectURL = isset($_POST['domainMismatchRedirectURL']) ? trim($_POST['domainMismatchRedirectURL']) : '';
if (!empty($mismatchRedirectURL) && !preg_match('@^https?://@i', $mismatchRedirectURL)) Respond::ResultPage(false);

$mismatchBlogId = isset($_POST['domainMismatchBlogId']) ? trim($_POST['domainMismatchBlogId']) : '';
if (!empty($mismatchBlogId) && !ctype_digit($mismatchBlogId)) Respond::ResultPage(false);

$result = Setting::setServiceSettingGlobal('domainRootBehavior', $rootBehavior);
$result = $result && Setting::setServiceSettingGlobal('domainRootRedirectURL', $rootRedirectURL);
$result = $result && Setting::setServiceSettingGlobal('domainRootBlogId', $rootBlogId);
$result = $result && Setting::setServiceSettingGlobal('domainMismatchBehavior', $mismatchBehavior);
$result = $result && Setting::setServiceSettingGlobal('domainMismatchRedirectURL', $mismatchRedirectURL);
$result = $result && Setting::setServiceSettingGlobal('domainMismatchBlogId', $mismatchBlogId);
Respond::ResultPage($result);
?>
