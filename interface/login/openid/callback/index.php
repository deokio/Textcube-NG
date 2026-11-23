<?php
/// Copyright (c) 2004-2016, Needlworks  / Tatter Network Foundation
/// All rights reserved. Licensed under the GPL.
/// See the GNU General Public License for more details. (/documents/LICENSE, /documents/COPYRIGHT)
///
/// OIDC Authorization Code 콜백 — provider 가 code/state 와 함께 돌아오는 redirect_uri.
/// 단계 3~4: code→token→id_token(RS256/iss/aud/exp/nonce) 검증 후, 검증에 성공한 경우에만
///   신원(Acl 'openid')을 설정한다. 계정에 식별자가 연결되어 있으면 그 사용자로 로그인(단계 4),
///   아니면 게스트 댓글 작성자로 남는다. 1회용 state/nonce/PKCE-verifier 는 폐기한다.
/// 이중 옵트인: OIDCClient::isEnabled() 가 false 면 콜백 자체를 거부한다.

define('__TEXTCUBE_ADMINPANEL__', true);

$IV = array(
	'GET' => array(
		'code'              => array('string', 'mandatory' => false),
		'state'             => array('string', 'mandatory' => false),
		'error'             => array('string', 'mandatory' => false),
		'error_description' => array('string', 'mandatory' => false),
		'iss'               => array('string', 'mandatory' => false),
		'session_state'     => array('string', 'mandatory' => false),
	)
);
require ROOT . '/library/preprocessor.php';
require_once ROOT . '/framework/legacy/Textcube.Control.OIDC.php';

$context = Model_Context::getInstance();
$return = !empty($_SESSION['oidc_return']) ? getSafeRedirectURI($_SESSION['oidc_return'], $context->getProperty('uri.blog') . '/') : ($context->getProperty('uri.blog') . '/');

function _oidc_redirect($location)
{
	header("HTTP/1.0 302 Moved Temporarily");
	header("Location: " . $location);
	print("<html><body></body></html>");
	exit(0);
}

function _oidc_error($msg, $location)
{
	header("HTTP/1.0 200 OK");
	header("Content-type: text/html; charset=utf-8");
	$safeMsg = json_encode($msg);
	$safeLoc = json_encode($location);
	print "<html><head><meta http-equiv=\"Content-Type\" content=\"text/html; charset=utf-8\" /></head><body><script type=\"text/javascript\">//<![CDATA[\n";
	print "alert($safeMsg);";
	print "document.location.href=$safeLoc;";
	print "//]]>\n</script></body></html>";
	exit(0);
}

// 이중 옵트인 게이트 — 비활성 시 콜백 거부.
if( !OIDCClient::isEnabled() ) {
	_oidc_error(_text('OIDC 가 활성화되어 있지 않습니다'), $context->getProperty('uri.blog') . '/');
}

// provider 측 오류(사용자 취소 등).
if( !empty($_GET['error']) ) {
	$desc = !empty($_GET['error_description']) ? $_GET['error_description'] : $_GET['error'];
	_oidc_error(_text('OIDC 인증이 취소되었거나 실패했습니다') . ' : ' . $desc, $return);
}

$claims = OIDCClient::handleCallback(
	isset($_GET['code'])  ? $_GET['code']  : '',
	isset($_GET['state']) ? $_GET['state'] : ''
);
if( $claims === false ) {
	// state/nonce/서명/aud/iss/exp 위반은 모두 여기로 떨어진다(검증 실패).
	_oidc_error(_text('OIDC 인증 검증에 실패했습니다') . ' : ' . OIDCClient::$error, $return);
}

// 검증 성공 — 신원 설정(게스트 + 연결 계정 매핑). 1회용 토큰 폐기.
if( OIDCClient::establishIdentity($claims) === false ) {
	// 신원을 설정하지 못함(식별자 형식·세션 발급 실패, v3.153) — 1회용 값을 지우고 오류를 알린다.
	unset($_SESSION['oidc_state'], $_SESSION['oidc_nonce'], $_SESSION['oidc_verifier'], $_SESSION['oidc_return']);
	_oidc_error(_text('OIDC 인증 검증에 실패했습니다') . ' : ' . OIDCClient::$error, $return);
}
unset($_SESSION['oidc_state'], $_SESSION['oidc_nonce'], $_SESSION['oidc_verifier'], $_SESSION['oidc_return']);

_oidc_redirect($return);
?>
