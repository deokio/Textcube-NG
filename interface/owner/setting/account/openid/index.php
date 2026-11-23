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
///
/// ---- Modification Notice ----
/// OpenID 2.0 (EOL) 제거 → OIDC 전용. 계정에 OIDC 식별자를 연결(add)/해제(del)한다.
/// 연결은 이미 id_token 으로 검증된 현재 OIDC 식별자(Acl 'openid')만 사용하므로 Yadis fetch/재인증이 없다.
/// Textcube-NG: 연결·해제 전에 계정 비밀번호를 다시 확인한다(v3.149, library/auth.php isReauthenticated()·재인증 표지).
define('OPENID_REGISTERS', 10); /* check also ../index.php */

// Textcube-NG: 연결 준비(prepare)·해제(del)는 POST + 비밀번호 재확인(confirmativePassword). 연결(add)은 외부 로그인에서
// 돌아오는 GET 이라 prepare 가 남긴 재인증 표지로 확인한다. 입력 규칙은 메서드별(v3.149)
$__openidPost = isset($_SERVER['REQUEST_METHOD']) && strtoupper((string)$_SERVER['REQUEST_METHOD']) === 'POST';
if ($__openidPost) {
	$IV = array(
		'POST' => array(
			'openid_identifier' => array('string', 'default'=>''),
			'mode' => array(array('prepare', 'del')),
			'confirmativePassword' => array('string', 'mandatory' => false)
		)
	);
} else {
	$IV = array(
		'GET' => array(
			// Textcube-NG: GET에서 쓰지 않는 openid_identifier 규칙 정리(r3-8)
			'mode' => array('string'),
			'provider' => array('string', 'default'=>'')	// 연결하려는 공급자: 'oidc' 또는 소셜 로그인 공급자 키(account 화면이 붙임, v3.153)
		)
	);
}

require ROOT . '/library/preprocessor.php';
require_once ROOT . '/framework/legacy/Textcube.Control.OIDC.php';

global $openid_list;
$openid_list = array();
for( $i=0; $i<OPENID_REGISTERS; $i++ )
{
	$openid = Setting::getUserSetting( "openid." . $i ,null,true);
	if( !empty($openid) ) {
		array_push( $openid_list, $openid );
	}
}

function exitWithError($msg)
{
	$context = Model_Context::getInstance();
	$safeMsg      = json_encode($msg);
	$safeRedirect = json_encode($context->getProperty('uri.blog') . '/owner/setting/account');
	echo "<html><head><script type=\"text/javascript\">//<![CDATA[".CRLF
		."alert($safeMsg); document.location.href=$safeRedirect; //]]></script></head></html>";
	exit;
}

/// OIDC 식별자 연결 — 현재 로그인된 OIDC 신원(Acl 'openid')을 본 계정(UserSettings openid.*)에 연결한다.
/// id_token 으로 이미 서명·iss·aud 검증된 식별자를 쓰되, 요청한 공급자의 신원이고 재인증 표지 이후에 인증된 것만 연결한다(v3.153).
/// 그렇지 않으면 OIDC 연결은 OIDC 로그인으로 다시 보내고, 소셜 연결은 거절한다.
/// 연결은 owner 세션(requireOwnership)에서만 일어나고 식별자는 항상 현재 세션의 본인 OIDC 이므로 타인 식별자를 주입할 수 없다.
function addOIDCIdentity()
{
	global $openid_list;
	$context = Model_Context::getInstance();
	$currentOpenID = Acl::getIdentity( 'openid' );
	// 연결하려는 공급자 — account 화면의 OpenID 연결 폼은 'oidc', 소셜 "연결" 버튼은 공급자 키를 돌아올 주소에 붙인다.
	// 비어 있으면 'oidc'(이전 주소 호환). 소셜 키는 CL_SocialLogin 이 켜져 있고 그 공급자를 쓸 수 있을 때만(account 화면의 버튼 표시와 같은 판정) (v3.153)
	$provider = isset($_GET['provider']) ? (string)$_GET['provider'] : '';
	if( $provider === '' ) $provider = 'oidc';
	$socialProviderOK = false;
	if( $provider !== 'oidc' && isActivePlugin('CL_SocialLogin') ) {
		require_once ROOT . '/plugins/CL_SocialLogin/index.php';
		$socialProviderOK = CL_SocialLogin_isEnabled($provider);
	}

	// [보안] 현재 세션 신원(Acl 'openid')이 요청한 공급자의 것이고, 재인증 표지(mode=prepare) 이후에 인증된 것일 때만 연결한다(v3.153).
	// 소셜 "연결"을 눌렀는데 세션에 남아 있던 OIDC·다른 공급자 신원이, OpenID 연결 폼인데 소셜 신원이 연결되지 않게 한다(신원 종류를 섞지 않음).
	// 인증 시각은 OIDCClient::establishGuestIdentityWith 가 남긴 $_SESSION['openid']['authenticated'](OIDC·소셜 공통, 표지 time 과 같은 시계).
	// 표지 시각은 checkReauthMarker 가 지우기 전에 읽는다 — 표지 자체의 유효성(작업·사용자·블로그·5분)은 checkReauthMarker 가 판정한다.
	$reauthTime = (isset($_SESSION['reauth']) && is_array($_SESSION['reauth']) && isset($_SESSION['reauth']['time'])) ? intval($_SESSION['reauth']['time']) : 0;
	$authTime = (isset($_SESSION['openid']) && is_array($_SESSION['openid']) && isset($_SESSION['openid']['authenticated'])) ? intval($_SESSION['openid']['authenticated']) : 0;
	$identityKind = OpenID::identityKind($currentOpenID);
	if( $provider === 'oidc' ) {
		$identityMatches = ($identityKind === 'oidc');
	} else {
		$identityMatches = ($socialProviderOK && $identityKind === 'social' && strpos((string)$currentOpenID, $provider . ':') === 0);
	}
	$identityOK = ($identityMatches && $reauthTime > 0 && $authTime >= $reauthTime);
	$toOIDCLogin = (!$identityOK && $provider === 'oidc' && OIDCClient::isEnabled());

	// Textcube-NG: mode=prepare(POST + 비밀번호 재확인)가 남긴 표지(같은 사용자·블로그, 5분 이내)가 있을 때만 연결한다.
	// OIDC 로그인으로 보낼 때는 표지를 남겨 두고, 연결하거나 거절할 때는 지운다(1회용). (v3.149, v3.153)
	if( !checkReauthMarker('openid-add', !$toOIDCLogin) ) {
		exitWithError( _t('비밀번호 확인이 필요합니다. 다시 시도해 주십시오.') );
	}

	if( $toOIDCLogin ) {
		$return = $context->getProperty('uri.blog') . '/owner/setting/account/openid?mode=add&provider=oidc';
		header( "Location: " . $context->getProperty('uri.blog') . "/login/openid?action=try_auth&requestURI=" . urlencode($return) );
		exit(0);
	}
	if( !$identityOK ) {
		// OIDC 미설정 문구는 login/openid(try_auth) 의 문구와 같다.
		if( $provider === 'oidc' ) exitWithError( _t('OpenID 로그인이 설정되어 있지 않습니다. 관리자가 OIDC 를 설정해야 합니다.') );
		exitWithError( _t('연결할 계정의 인증을 확인하지 못했습니다. 다시 시도해 주십시오.') );
	}

	if( in_array($currentOpenID, $openid_list) ) {
		exitWithError( _t('이미 연결된 오픈아이디 입니다') . " : " . OpenID::getDisplayName($currentOpenID) );
	}

	for( $i=0; $i<OPENID_REGISTERS; $i++ )
	{
		$openid = Setting::getUserSetting( "openid." . $i, null, true );
		if( empty($openid) ) {
			Setting::setUserSetting( "openid." . $i, $currentOpenID, true );
			break;
		}
	}

	$safeMsg      = json_encode(_t('연결하였습니다.') . ' : ' . OpenID::getDisplayName($currentOpenID));
	$safeRedirect = json_encode($context->getProperty('uri.blog') . '/owner/setting/account');
	echo "<html><head><script type=\"text/javascript\">//<![CDATA[".CRLF
		."alert($safeMsg); document.location.href=$safeRedirect; //]]></script></head></html>";
}

function deleteOpenID($openidForDel)
{
	$context = Model_Context::getInstance();
	for( $i=0; $i<OPENID_REGISTERS; $i++ )
	{
		$openid = Setting::getUserSetting( "openid." . $i , null, true);
		if( $openid == $openidForDel ) {
			Setting::removeUserSetting( "openid." . $i, true);
			break;
		}
	}

	$safeMsg      = json_encode(_t('삭제되었습니다.'));
	$safeRedirect = json_encode($context->getProperty('uri.blog') . '/owner/setting/account');
	echo "<html><head><script type=\"text/javascript\">//<![CDATA[".CRLF
		."alert($safeMsg); document.location.href=$safeRedirect; //]]></script></head></html>";
}

if ($__openidPost) {
	// Textcube-NG: 비밀번호 재확인 뒤 prepare 는 연결 표지(5분·1회)를 남기고, del 은 해제한다(v3.149)
	$__reauthOK = isReauthenticated(isset($_POST['confirmativePassword']) ? $_POST['confirmativePassword'] : null);
	if ($_POST['mode'] == 'prepare') {
		if (!$__reauthOK) Respond::ResultPage(9);
		setReauthMarker('openid-add');
		Respond::ResultPage(0);
	}
	if (!$__reauthOK) exitWithError(_t('비밀번호가 일치하지 않습니다.'));
	deleteOpenID($_POST['openid_identifier']);
} else {
	switch( $_GET['mode'] ) {
		case 'del':
		case 'prepare':
			requirePostMethod();	// Textcube-NG: 해제·연결 준비는 POST 만(405) — 링크·<img> 로 실행되던 GET CSRF 차단(v3.149)
			break;
		case 'add':
			addOIDCIdentity();
			break;
		default:	// [보안] Textcube-NG: 알 수 없는 mode 는 400 — 원본은 mode 가 없거나 틀려도 연결(add)로 처리했다(r3-7, 결정 461f)
			header('HTTP/1.1 400 Bad Request');
			exit;
	}
}
?>
