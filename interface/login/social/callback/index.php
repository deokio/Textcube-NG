<?php
/// Copyright (c) 2004-2016, Needlworks  / Tatter Network Foundation
/// All rights reserved. Licensed under the GPL.
/// See the GNU General Public License for more details. (/documents/LICENSE, /documents/COPYRIGHT)
///
/// CL_SocialLogin 콜백 진입점(얇은 디스패처). provider 가 code/state 와 함께 돌아오는 redirect_uri.
/// 로직은 플러그인: code→token→(oidc id_token | oauth2 userinfo) 검증 후, 성공한 경우에만 신원을 설정한다.
/// 계정에 식별자가 연결돼 있으면 그 사용자로 로그인(명시적 연결만), 아니면 게스트 댓글 작성자로 남는다.
/// 이 콜백은 그 블로그 자신의 주소이므로 블로그 컨텍스트·세션이 정확히 일치한다(크로스도메인 없음).

define('__TEXTCUBE_ADMINPANEL__', true);

$IV = array(
	'GET' => array(
		'code'              => array('string', 'mandatory' => false),
		'state'             => array('string', 'mandatory' => false),
		'error'             => array('string', 'mandatory' => false),
		'error_description' => array('string', 'mandatory' => false),
	)
);
require ROOT . '/library/preprocessor.php';

$context = Model_Context::getInstance();
$blogURL = $context->getProperty('uri.blog');
$return  = (isset($_SESSION['cl_social']) && is_array($_SESSION['cl_social']) && !empty($_SESSION['cl_social']['return']))
	? getSafeRedirectURI($_SESSION['cl_social']['return'], $blogURL . '/') : ($blogURL . '/');

function _social_redirect($location)
{
	header("HTTP/1.0 302 Moved Temporarily");
	header("Location: " . $location);
	print("<html><body></body></html>");
	exit(0);
}

function _social_error($msg, $location)
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

if (!function_exists('isActivePlugin') || !isActivePlugin('CL_SocialLogin')) {
	_social_error(_text('소셜 로그인 플러그인이 활성화되어 있지 않습니다.'), $blogURL . '/login');
}
require_once ROOT . '/plugins/CL_SocialLogin/lib/SocialOAuth.php';

// provider 측 오류(사용자 취소 등).
if (!empty($_GET['error'])) {
	$desc = !empty($_GET['error_description']) ? $_GET['error_description'] : $_GET['error'];
	_social_error(_text('소셜 로그인이 취소되었거나 실패했습니다') . ' : ' . $desc, $return);
}

$identity = CL_SocialLogin_OAuth::handleCallback(
	isset($_GET['code'])  ? $_GET['code']  : '',
	isset($_GET['state']) ? $_GET['state'] : ''
);
if ($identity === false) {
	// state 불일치·토큰 위조·id_token(RS256/iss/aud/exp/nonce) 위반은 모두 여기로 떨어진다.
	_social_error(_text('소셜 로그인 검증에 실패했습니다') . ' : ' . CL_SocialLogin_OAuth::$error, $return);
}

// 검증 성공 — 신원 설정(게스트 댓글 + 연결된 계정이면 로그인). 1회용 세션 state 폐기.
if (CL_SocialLogin_OAuth::establishIdentity($identity) === false) {
	// 신원을 설정하지 못함(세션 발급 실패 등, v3.153) — 1회용 state 를 지우고 오류를 알린다. 실패 사유는 공용 OIDCClient 에 있다.
	unset($_SESSION['cl_social']);
	_social_error(_text('소셜 로그인 검증에 실패했습니다') . ' : ' . OIDCClient::$error, $return);
}
unset($_SESSION['cl_social']);

_social_redirect($return);
?>
