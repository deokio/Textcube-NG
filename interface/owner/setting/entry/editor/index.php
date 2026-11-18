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
requirePostMethod();	// Textcube-NG: 상태 변경은 POST 만 받는다(그 밖은 405) — 링크·<img> 로 실행되던 GET CSRF 차단(v3.148)
requireStrictRoute();
if(array_key_exists('useBlogAPI',  $_REQUEST)) {
	if (($_REQUEST['useBlogAPI'] == "yes") || ($_REQUEST['useBlogAPI'] == "1") || ($_REQUEST['useBlogAPI'] == "true"))
		$useBlogAPI = '1';
	else 
		$useBlogAPI = '0';
} else $useBlogAPI = '0';

if (!array_key_exists('defaultEditor',$_REQUEST) || !array_key_exists('defaultFormatter',$_REQUEST))
	Respond::ResultPage( -1);

if (Setting::setBlogSettingGlobal("defaultEditor", $_REQUEST['defaultEditor']) 
		&& Setting::setBlogSettingGlobal("defaultFormatter", $_REQUEST['defaultFormatter']) 
		&& Setting::setBlogSettingGlobal("useBlogAPI", $useBlogAPI) 
		&& Setting::setBlogSettingGlobal("blogApiPassword", $_REQUEST['blogApiPassword']) ) {
	Respond::ResultPage(0);
}
Respond::ResultPage( -1);
?>
