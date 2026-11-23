<?php
/// Copyright (c) 2004-2016, Needlworks  / Tatter Network Foundation
/// All rights reserved. Licensed under the GPL.
/// See the GNU General Public License for more details. (/documents/LICENSE, /documents/COPYRIGHT)
///
/// CL_SocialLogin 인증 시작 진입점(얇은 디스패처). 로직은 플러그인(plugins/CL_SocialLogin/lib/SocialOAuth.php).
/// 활성·설정된 provider 의 authorization endpoint 로 리다이렉트한다. 콜백은 /login/social/callback.
/// 이중 옵트인: 플러그인 비활성 또는 provider 미설정이면 동작하지 않는다.

define('__TEXTCUBE_ADMINPANEL__', true);

$IV = array(
	'GET' => array(
		'provider'   => array('string', 'mandatory' => false),
		'requestURI' => array('string', 'mandatory' => false),
	)
);
require ROOT . '/library/preprocessor.php';

$context = Model_Context::getInstance();
$blogURL = $context->getProperty('uri.blog');

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

$provider   = isset($_GET['provider']) ? $_GET['provider'] : '';
$requestURI = !empty($_GET['requestURI']) ? getSafeRedirectURI($_GET['requestURI'], $blogURL . '/') : ($blogURL . '/');

if (!CL_SocialLogin_isEnabled($provider)) {
	_social_error(_text('해당 소셜 로그인이 설정되어 있지 않습니다.'), $blogURL . '/login');
}

$url = CL_SocialLogin_OAuth::buildAuthUrl($provider, $requestURI);
if ($url === false) {
	_social_error(_text('소셜 로그인을 시작할 수 없습니다') . ' : ' . CL_SocialLogin_OAuth::$error, $blogURL . '/login');
}
header("HTTP/1.0 302 Moved Temporarily");
header("Location: " . $url);
print("<html><body></body></html>");
exit(0);
?>
