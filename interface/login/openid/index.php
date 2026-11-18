<?php
/// Copyright (c) 2004-2016, Needlworks  / Tatter Network Foundation
/// All rights reserved. Licensed under the GPL.
/// See the GNU General Public License for more details. (/documents/LICENSE, /documents/COPYRIGHT)
///
/// ---- Modification Notice ----
/// OpenID 2.0 (EOL) 제거 → OIDC 전용 진입점. 인증 시작(try_auth)과 로그아웃(logout)만 처리하며,
/// provider 콜백은 /login/openid/callback 에서 받는다. OIDC 미설정 시 로그인은 동작하지 않는다(이중 옵트인).

define('__TEXTCUBE_ADMINPANEL__',true);

$IV = array(
	'GET' => array(
		'action'     => array('string', 'mandatory' => false ),
		'requestURI' => array('string', 'mandatory' => false ),
	)
);
require ROOT . '/library/preprocessor.php';
require_once ROOT . '/framework/legacy/Textcube.Control.OIDC.php';

/// OIDC 인증 시작 — provider authorization endpoint 로 리다이렉트.
/// state/nonce/PKCE-verifier 는 buildAuthUrl() 이 세션에 저장하고 콜백에서 검증한다.
function OIDCStartAuth()
{
	$context = Model_Context::getInstance();
	$requestURI = !empty($_GET['requestURI']) ? getSafeRedirectURI($_GET['requestURI'], $context->getProperty('uri.blog') . '/') : ($context->getProperty('uri.blog') . '/');
	$_SESSION['oidc_return'] = $requestURI;
	$url = OIDCClient::buildAuthUrl();
	if( $url === false ) {
		OpenIDConsumer::printErrorReturn( _text('OIDC 인증을 시작할 수 없습니다') . ' : ' . OIDCClient::$error, $requestURI );
	}
	header("HTTP/1.0 302 Moved Temporarily");
	header("Location: " . $url);
	print( "<html><body></body></html>" );
	exit(0);
}

function LogoutOpenID()
{
	$context = Model_Context::getInstance();
	OpenIDConsumer::logout();
	$requestURI = !empty($_GET['requestURI']) ? getSafeRedirectURI($_GET['requestURI'], $context->getProperty('uri.blog') . '/') : ($context->getProperty('uri.blog') . '/');
	header("HTTP/1.0 302 Moved Temporarily");
	header("Location: ".$requestURI);

	// Hack for avoiding textcube zero-length content
	print( "<html><body></body></html>" );
	exit(0);
}

if( empty($_GET['action']) ) {
	$_GET['action'] = 'try_auth';
}
switch( $_GET['action'] ) {
case 'try_auth':
	if( OIDCClient::isEnabled() ) {
		OIDCStartAuth();
	} else {
		$context = Model_Context::getInstance();
		OpenIDConsumer::printErrorReturn( _text('OpenID 로그인이 설정되어 있지 않습니다. 관리자가 OIDC 를 설정해야 합니다.'), $context->getProperty('uri.blog') . '/' );
	}
	break;
case 'logout':
	LogoutOpenID();
	break;
default:
	exit;
}
?>
