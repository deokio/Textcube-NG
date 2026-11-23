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
	'POST' => array(
		'coverpageNumber' => array('int'),
		'modulePos' => array('int'),
		'viewMode' => array('string', 'default' => '')
	)
);
require ROOT . '/library/preprocessor.php';
requirePostMethod();	// Textcube-NG: 상태 변경은 POST 만 받는다(그 밖은 405) — 링크·<img> 로 실행되던 GET CSRF 차단(v3.148)
requireStrictRoute();
requireLibrary('blog.skin');
requireModel("blog.sidebar");
requireModel("blog.coverpage");


$skin = new Skin($skinSetting['skin']);
$coverpageCount = count($skin->coverpageBasicModules);
$coverpageOrder = deleteCoverpageModuleOrderData(getCoverpageModuleOrderData($coverpageCount), $_POST['coverpageNumber'], $_POST['modulePos']);
setBlogSetting("coverpageOrder", serialize($coverpageOrder));

//Respond::PrintResult(array('error' => 0));
if ($_POST['viewMode'] != '') $_POST['viewMode'] = '?' . $_POST['viewMode'];
header('Location: '. $blogURL . '/owner/skin/coverpage' . $_POST['viewMode']);
?>
