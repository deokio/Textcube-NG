<?php
/// Copyright (c) 2004-2007, Needlworks / Tatter Network Foundation
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
///
/// ---- Modification Notice ----
/// OpenID 2.0 컨슈머 제거에 따라, 과거 OpenIDConsumer::setComment / setOpenIDLogoDisplay 호출을
/// 동등한 설정 저장으로 인라인화한다. 플러그인 자동 활성(activatePlugin)은 이중 옵트인 정책상 제거한다.

$IV = array(
	'POST' => array(
		'openidonlycomment' => array('bool', 'mandatory' => true ),
		'openidlogodisplay' => array('bool', 'mandatory' => true )
	)
);

require ROOT . '/library/preprocessor.php';
requireLibrary('blog.skin');
requireStrictRoute();
$skin = new Skin($skinSetting['skin']);

if( !Acl::check( array("group.administrators") ) ) {
	Respond::ResultPage(-1);
	exit;
}

$commentMode = empty($_POST['openidonlycomment']) ? '' : 'openid';
if( Setting::setBlogSettingGlobal( "AddCommentMode", $commentMode ) &&
	Setting::setBlogSettingGlobal( "OpenIDLogoDisplay", $_POST['openidlogodisplay'] ) ) {
	$skin->purgeCache();
	Respond::ResultPage(0);
} else {
	Respond::ResultPage(-1);
}
?>
