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
		'loginid' => array('string', 'mandatory' => false ),
		'password' => array('string', 'default' => null),
		'requestURI' => array('string', 'default' => null ),
		'refererURI' => array('string', 'default' => null ),
		'session' => array('string' , 32, 32, 'default' => null),
		'try' => array(array(1,2,3), 'default' => null),
	),
	'POST' => array(
		'loginid' => array('string', 'default' => null),
		'password' => array('string', 'default' => null),
		'requestURI' => array('string', 'default' => null),
		'refererURI' => array('string', 'default' => null),
		'reset' => array(array('on') ,'default' => null),
		'save' => array('any', 'default' => null),
		'teamblogPatch' => array('string', 'default' => null)
	)
);
define('__TEXTCUBE_LOGIN__',true);
define('__TEXTCUBE_ADMINPANEL__',true);
require ROOT . '/library/preprocessor.php';
$context = Model_Context::getInstance(); 

//$blogURL = getBlogURL();
/* [보안] 메일 링크(초대·임시 암호)의 인증 토큰(32자리 16진)은 1회용이라(Auth::authenticate), 주소를 여는 것만으로 로그인하면
   메일 보안 검사·미리보기가 링크를 먼저 열어 토큰이 소모된다. 토큰 형식 값이 주소(GET)로 오면 바로 로그인하지 않고
   입력란에 채운 로그인 화면을 보여 로그인 버튼(POST)으로 진행한다. 그 밖의 GET 비밀번호는 원본과 같이 바로 로그인한다. */
$tokenFromLink = null;
if (isset($_GET['loginid']))
	$_POST['loginid'] = $_GET['loginid'];
if (isset($_GET['password'])) {
	if (preg_match('/^[0-9a-f]{32}$/i', $_GET['password']))
		$tokenFromLink = $_GET['password'];
	else
		$_POST['password'] = $_GET['password'];
}
/* Textcube-NG: Referer 가 이 로그인 화면 자신이면 Referer 가 없는 것으로 본다(requestURI·refererURI 둘 다 — 원본에서 Referer 없이
   로그인할 때와 같은 uri.blog, 즉 블로그 첫 화면으로 감). 원본은 주소창으로 연 로그인 화면에서 로그인하면(single·domain 루트는
   uri.blog 가 '' 라 아래 else 가 POST 의 숨은 requestURI 를 '' 로 덮음) Referer(로그인 화면)가 이동 주소가 되고, 로그인된 로그인
   화면이 다시 Referer 로 보내 리디렉션이 끝없이 반복됐다(ERR_TOO_MANY_REDIRECTS). 자신 판정: 호스트가 지금 요청의 호스트와 같은
   http(s) 주소이고(포트 무시, __redirectTargetStatus() 의 호스트 비교와 같은 방식) 경로·쿼리가 지금 요청 주소와 같거나(/login/·
   /login/index.php 처럼 같은 화면의 다른 주소) uri.blog.'/login' 화면(뒤에 끝·'/'·'?'·'#', 대소문자 무시 — 대소문자를 가리지 않는
   파일 시스템에서는 /LOGIN 도 이 화면). 판정이 참이어도 새 이동 대상은 없다(v3.159) */
$httpReferer = isset($_SERVER['HTTP_REFERER']) ? (string)$_SERVER['HTTP_REFERER'] : '';
$currentHost = rtrim(strtolower(explode(':', (string)$_SERVER['HTTP_HOST'])[0]), '.');
if ($currentHost !== '' && preg_match('@^https?://([A-Za-z0-9._-]+)(?::[0-9]*)?([/?#].*)$@is', $httpReferer, $refererParts)
	&& rtrim(strtolower($refererParts[1]), '.') === $currentHost
	&& ((isset($_SERVER['REQUEST_URI']) && $refererParts[2] === $_SERVER['REQUEST_URI'])
		|| preg_match('@^' . preg_quote($context->getProperty('uri.blog') . '/login', '@') . '/?(?:[?#]|$)@i', $refererParts[2])))
	$httpReferer = '';
unset($currentHost, $refererParts);
if (!empty($_GET['requestURI']))
	$_POST['requestURI'] = $_GET['requestURI'];
else if (empty($_POST['requestURI']) && !empty($httpReferer) )
	$_POST['requestURI'] = $httpReferer;
else
	$_POST['requestURI'] = $context->getProperty('uri.blog');
if (!empty($_GET['refererURI'])) $_POST['refererURI'] = $_GET['refererURI'];
else $_POST['refererURI'] = $httpReferer;

$message = '';
$showPasswordReset = false;
if (isset($_GET['session']) && isset($_GET['requestURI'])) {
	global $service;
	/* [보안] 다중 도메인 로그인 인계(owner/control footer iframe, 아래 다른 호스트로의 로그인 이동)로 받은 세션 ID.
	   원본은 받은 값을 확인 없이 세션 쿠키로 설정해, 다른 사이트가 방문자를 이 주소로 보내기만 하면 임의 세션 ID 를
	   심을 수 있었다(세션 고정·로그인 CSRF). 인증된 세션이고 그 세션을 만든 클라이언트와 주소가 같을 때만 설정하고
	   (Session::write 도 같은 주소의 행만 갱신), 그 밖에는 쿠키를 바꾸지 않고 이동만 한다.
	   쿠키 속성은 Session::authorize 의 세션 쿠키와 같게(HttpOnly, SameSite=Lax, useSSL 이면 Secure). */
	if (Session::isOwnAuthorizedSession($_GET['session'])) {
		setCookieCompat( Session::getName(), $_GET['session'], array('expires' => 0, 'path' => $context->getProperty('service.session_cookie_path'), 'domain' => $context->getProperty('service.session_cookie_domain'), 'secure' => (bool)$context->getProperty('service.useSSL', false), 'httponly' => true, 'samesite' => 'Lax'));
	}
	header('Location: ' . getSafeRedirectURI($_GET['requestURI'], $context->getProperty('uri.blog') . '/', true));
	exit;
} else if (!empty($_POST['loginid']) && !empty($_POST['reset'])) {
	/* [보안] 계정 확인 방지: 입력한 이메일이 이 블로그 회원인지와 관계없이 같은 안내를 보인다(원본은 회원이 아니면
	   '권한이 없습니다.'). 메일은 이 블로그 회원의 저장된 주소로만 발송된다(resetPassword). */
	resetPassword($blogid, $_POST['loginid']);
	$message = _text('지정된 이메일로 로그인 정보가 전달되었습니다.');
} else if (!empty($_POST['loginid']) && !empty($_POST['password'])) {
	$isLogin = login($_POST['loginid'],$_POST['password']);
	if (!$isLogin) {
		// Textcube-NG: 로그인 잠금 방식(r3-14) — 접속 IP 잠금(loginLockScope 'ip')으로 실패했으면 제한 이유를 밝히고, 그 밖의 실패는
		// 계정 확인 방지(v3.202)를 위해 같은 문구를 보인다. 남은 시간은 loginLockShowRemaining 이 true 일 때만 분 단위(올림)로 보인다
		$ipLockRemaining = __loginIpLockRemaining();
		if ($ipLockRemaining > 0)
			$message = __loginLockShowRemaining()
				? _textf('로그인 실패가 많아 이 IP 주소의 로그인이 잠시 제한되었습니다. 약 %1분 뒤 다시 시도해 주십시오.', (string)intdiv($ipLockRemaining + 59, 60))
				: _text('로그인 실패가 많아 이 IP 주소의 로그인이 잠시 제한되었습니다. 잠시 뒤 다시 시도해 주십시오.');
		else
			$message = _text('아이디 또는 비밀번호가 틀렸습니다.');
		/* [보안] 계정 확인 방지: '암호 초기화' 선택란을 입력한 이메일이 이 블로그 회원인지와 관계없이 보인다(원본은 회원일 때만
		   보여, 틀린 비밀번호로 로그인해 보는 것만으로 회원 이메일인지 알 수 있었다). */
		if (!doesHaveMembership()){
			$showPasswordReset = true;
		}
	} else {
		if (!doesHaveOwnership()) {
			$message = _text('서비스의 회원이지만 이 블로그의 구성원이 아닙니다. 주소를 확인해 주시기 바랍니다.');
		}
	}
}
if (!is_null($tokenFromLink) && empty($message))
	$message = _text('메일로 받은 로그인 정보가 입력되었습니다. 로그인 버튼을 눌러 주십시오.');
$authResult = fireEvent('LOGIN_try_auth', false);
if (doesHaveOwnership()) {
	if (!empty($_POST['requestURI'])) {
		$requestURI = getSafeRedirectURI($_POST['requestURI'], $context->getProperty('uri.blog') . '/');
		$url = parse_url($requestURI);
		if ($url && isset($url['host']) && !StringUtil::endsWith( '.' . $url['host'], '.' . $context->getProperty('service.domain')))
			$redirect = $context->getProperty('uri.blog')."/login?requestURI=" . rawurlencode($requestURI) . '&session=' . rawurlencode(session_id());
		else
			$redirect = $requestURI;
	} else {
		$redirect = getSafeRedirectURI($_POST['refererURI'], $context->getProperty('uri.blog') . '/');
	}
	if (empty($_SESSION['lastloginRedirected']) || $_SESSION['lastloginRedirected'] != $redirect) {
		$_SESSION['lastloginRedirected'] = $redirect;
	} else {
		unset($_SESSION['lastloginRedirected']);
	}
	header('Location: '.$redirect);
	exit;
} else if (doesHaveMembership() && empty($message)) {
	$message = _text('서비스의 회원이지만 이 블로그의 구성원이 아닙니다. 주소를 확인해 주시기 바랍니다.');
}
// Textcube-NG: 로그인 폼은 같은 출처 frame 에만 표시 — 클릭재킹 방지. 위의 SSO 홉(requestURI·session 처리)은
// exit 로 이 앞에서 끝나 다른 도메인 iframe 에서 그대로 동작한다(v3.147)
header('X-Frame-Options: SAMEORIGIN');
?><!DOCTYPE html PUBLIC "-//W3C//DTD XHTML 1.1//EN" "http://www.w3.org/TR/xhtml11/DTD/xhtml11.dtd">
<html xmlns="http://www.w3.org/1999/xhtml" xml:lang="ko">
<head>
	<meta http-equiv="Content-Type" content="text/html; charset=utf-8" />
	<title><?php echo _text('Textcube - Login');?></title>
<?php
	$browser = Utils_Browser::getInstance();
	if($browser->isMobile()) {
?>
	<meta name="viewport" content="width=device-width,initial-scale=1,user-scalable=no" />
<?php
	}
?>
	<link rel="stylesheet" type="text/css" href="<?php echo $context->getProperty('service.path').$adminSkinSetting['skin'];?>/basic.css" />
	<link rel="stylesheet" type="text/css" href="<?php echo $context->getProperty('service.path').$adminSkinSetting['skin'];?>/login.css" />
	<!--[if lte IE 6]>
		<link rel="stylesheet" type="text/css" href="<?php echo $context->getProperty('service.path').$adminSkinSetting['skin'];?>/basic.ie.css" />
		<link rel="stylesheet" type="text/css" href="<?php echo $context->getProperty('service.path').$adminSkinSetting['skin'];?>/login.ie.css" />
	<![endif]-->
	<!--[if IE 7]>
		<link rel="stylesheet" type="text/css" href="<?php echo $context->getProperty('service.path').$adminSkinSetting['skin'];?>/basic.ie7.css" />
		<link rel="stylesheet" type="text/css" href="<?php echo $context->getProperty('service.path').$adminSkinSetting['skin'];?>/login.ie7.css" />
	<![endif]-->
	<script type="text/javascript" src="<?php echo $context->getProperty('service.resourcepath');?>/script/byTextcube.js"></script>
<?php /* Textcube-NG: 이식본에서 고친 스크립트(EAF4.js·common3.js·gallery.js·jQuery, v3.147·v3.207)는 externalresources 설정과 관계없이 이 서버의 /resources 에서 읽는다 — 외부 리소스 주소의 원본 스크립트에는 CSRF 토큰 패치가 없다(r3-3) */ ?>
	<script type="text/javascript" src="<?php echo $context->getProperty('service.path');?>/resources/script/jquery/jquery-<?php echo JQUERY_VERSION;?>.js"></script>
	<script type="text/javascript">jQuery.noConflict();</script>
	<script type="text/javascript" src="<?php echo $context->getProperty('service.path');?>/resources/script/EAF4.js?v=<?php echo filemtime(ROOT . '/resources/script/EAF4.js');?>"></script>
	<script type="text/javascript" src="<?php echo $context->getProperty('service.path');?>/resources/script/common3.js?v=<?php echo filemtime(ROOT . '/resources/script/common3.js');?>"></script>
	<script type="text/javascript">
		//<![CDATA[
			var servicePath = "<?php echo $context->getProperty('service.path');?>";
			var blogURL = "<?php echo $context->getProperty('uri.blog');?>";
			var adminSkin = "<?php echo $adminSkinSetting['skin'];?>";

			window.addEventListener("load", execLoadFunction, false);
			
			function execLoadFunction() {
				document.forms[0].<?php echo (empty($_COOKIE['TSSESSION_LOGINID']) ? 'loginid' : 'password');?>.focus();
<?php
	$browser = Utils_Browser::getInstance();
	if($browser->isMobile()) {
?>
				setTimeout(scrollTo, 0, 0, 1);
<?php
	}
?>
			}
			function tcCheckupNotice(msg, url, label, note) {	// Textcube-NG: 점검 안내를 브라우저 confirm 대신 페이지 위 안내 줄로 보인다(r3-16, R-395)
				var bar = document.getElementById("tc-checkup-notice");
				if (!bar) {
					bar = document.createElement("div");
					bar.id = "tc-checkup-notice";
					bar.setAttribute("role", "alert");
					bar.style.cssText = "position:relative;z-index:1000;padding:8px 12px;background:#fff8d6;border-bottom:1px solid #e0c96a;color:#333;font-size:12px;line-height:1.6;text-align:center;";
					bar.appendChild(document.createElement("span"));
					var go = document.createElement("a");
					go.style.cssText = "margin-left:10px;font-weight:bold;";
					bar.appendChild(go);
					var later = document.createElement("button");
					later.type = "button";
					later.style.cssText = "margin-left:8px;";
					later.appendChild(document.createTextNode("<?php echo _text('나중에');?>"));
					later.onclick = function () { bar.style.display = "none"; };
					bar.appendChild(later);
					document.body.insertBefore(bar, document.body.firstChild);
				}
				bar.childNodes[0].textContent = note ? msg + " " + note : msg;
				bar.childNodes[1].href = url;
				bar.childNodes[1].textContent = label;
				bar.style.display = "";
			}
		//]]>
	</script>
<?php
if (!file_exists(__TEXTCUBE_CACHE_DIR__.'/CHECKUP')) {
?>
	<script type="text/javascript">
		//<![CDATA[
			window.addEventListener("load", checkTextcubeVersion, false);
			function checkTextcubeVersion() {
				tcCheckupNotice("<?php echo _text('버전업 체크를 위한 파일을 생성합니다. 지금 생성하시겠습니까?');?>", "<?php echo $context->getProperty('uri.blog');?>/checkup", "<?php echo _text('지금 생성');?>");	// Textcube-NG: confirm 대신 안내 줄(r3-16, R-395)
			}
		//]]>
	</script>
<?php
} else if (!Checkup::isCurrent()) {	// PHP port: NG 점검 표시(2행) 포함 비교(Checkup, Textcube.Core.php)
?>
	<script type="text/javascript">
		//<![CDATA[
			window.addEventListener("load", checkTextcubeVersion, false);
			function checkTextcubeVersion() {
				tcCheckupNotice("<?php echo _text('텍스트큐브 시스템 점검이 필요합니다. 지금 점검하시겠습니까?');?>", "<?php echo $context->getProperty('uri.blog');?>/checkup", "<?php echo _text('지금 점검');?>");	// Textcube-NG: confirm 대신 안내 줄(r3-16, R-395)
				}
		//]]>
	</script>
<?php
}
?>
</head>
<body id="body-login">
	<div id="temp-wrap">
		<div id="all-wrap">
			<div id="data-outbox">
				<div id="login-box">
					<div id="logo-box">
						<img src="<?php echo $context->getProperty('service.path').$adminSkinSetting['skin'];?>/image/logo_textcube.png" alt="<?php echo _text('텍스트큐브 로고');?>" />
					</div>
					<form method="post" action="">
						<input type="hidden" name="requestURI" value="<?php echo htmlspecialchars($_POST['requestURI'], ENT_QUOTES | ENT_SUBSTITUTE);?>" />
						<input type="hidden" name="refererURI" value="<?php echo htmlspecialchars($_POST['refererURI'], ENT_QUOTES | ENT_SUBSTITUTE); ?>" />
						<div id="basic-field-box" class="field-box">
							<dl id="email-line">
								<dt><label for="loginid"><?php echo _text('이메일');?></label></dt>
								<dd><input type="text" class="input-text" id="loginid" name="loginid" value="<?php echo htmlspecialchars(empty($_POST['loginid']) ? (empty($_COOKIE['TSSESSION_LOGINID']) ? '' : $_COOKIE['TSSESSION_LOGINID']) : $_POST['loginid'], ENT_QUOTES | ENT_SUBSTITUTE);?>" maxlength="64" tabindex="1" /></dd>
							</dl>
							<dl id="password-line">
								<dt><label for="password"><?php echo _text('비밀번호');?></label></dt>
								<dd><input type="password" class="input-text" id="password" name="password"<?php echo (is_null($tokenFromLink) ? '' : ' value="' . htmlspecialchars($tokenFromLink, ENT_QUOTES | ENT_SUBSTITUTE) . '"');?> onkeydown="if (event.keyCode == 13) document.forms[0].submit()" maxlength="64" tabindex="2" /></dd>
							</dl>
							<dl id="checkbox-line">
								<dt><span class="label"><?php echo _text('선택사항');?></span></dt>
								<dd>
									<div id="email-save"><input type="checkbox" id="save" class="checkbox" name="save"<?php echo (empty($_COOKIE['TSSESSION_LOGINID']) ? '' : 'checked="checked"');?> /><label for="save"><?php echo _text('이메일 저장');?></label></div>
									<?php echo ($showPasswordReset ? '<div id="password_int"><input type="checkbox" class="checkbox" id="reset" name="reset" /><label for="reset">' . _text('암호 초기화') . '</label></div>'.CRLF : '');?>
								</dd>
							</dl>
							
							<div class="button-box">
								<input type="submit" class="login-button input-button" name="button_login" value="<?php echo _text('로그인');?>" />
							</div>
							<div class="return-box">
								<a href="<?php echo $context->getProperty('uri.blog');?>"><?php echo _text('블로그로 돌아가기');?></a>
							</div>
						</div>
					</form>
						
<?php
// OpenID 블록은 OIDC 가 설정됐을 때만 보인다 — 설정 전에는 눌러도 오류만 난다(login/openid?action=try_auth, v3.153).
// 과거 OpenID 2.0 식별자 입력란·'오픈아이디 저장' 쿠키는 OIDC 로그인에서 쓰지 않으므로(login/openid 가 읽지 않음) 제거.
require_once ROOT . '/framework/legacy/Textcube.Control.OIDC.php';
if( isActivePlugin('CL_OpenID') && OIDCClient::isEnabled() ) {
	list( $openid_help_link, $openid_signup_link ) = fireEvent( "OpenIDAffiliateLinks", array('',''), $_POST['requestURI'] );
?>
					<form method="get" name="openid_form" action="<?php echo $context->getProperty('uri.blog'); ?>/login/openid?action=try_auth">
						<input type="hidden" name="requestURI" value="<?php echo htmlspecialchars($_POST['requestURI'], ENT_QUOTES | ENT_SUBSTITUTE); ?>" />
						<input type="hidden" name="action" value="try_auth" />
						
						<div id="openid-field-box" class="field-box">
							<h2 class="login-method-title"><?php echo _text('관리자 계정과 연결된 오픈아이디');?></h2>
							
							<div class="button-box">
								<input type="submit" class="login-button input-button" id="openid-login-button" name="openid_login" value="<?php echo _text('로그인'); ?>" />
							</div>
							
							<?php if (!empty($openid_help_link) || !empty($openid_signup_link)) { ?>
							<ul id="openid-intro">
								<?php if( !empty( $openid_help_link ) ) { ?>
								<li id="openid-help"><a href="<?php echo $openid_help_link; ?>" ><?php echo _text('오픈아이디란?') ?></a></li>
								<?php } ?>
								<?php if( !empty( $openid_signup_link ) ) { ?>
								<li id="openid-generate"><a href="<?php echo $openid_signup_link; ?>"><?php echo _text('오픈아이디 발급하기'); ?></a></li>
								<?php } ?>
							</ul>
							<?php } ?>
						</div>
					</form>
<?php } ?>
<?php
						echo fireEvent('LOGIN_add_form', '', $_POST['requestURI'] );
if (!empty($message)) {
?>
					<div id="message-box">
						<?php echo $message.CRLF;?>
					</div>
<?php
}
?>
				</div> <!-- login-box -->
			</div> <!-- data-outbox -->
		</div> <!-- all-wrap -->
	</div> <!-- temp-wrap -->
<?php
	if( function_exists('__tcSqlLogDump') ) { 
		__tcSqlLogDump();
	}
?>
</body>
</html>
