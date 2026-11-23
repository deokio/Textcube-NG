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

define('__TEXTCUBE_ADMINPANEL__',true);

$IV = array(
	'GET' => array(
		'requestURI' => array('url', 'mandatory' => false )
	)
);

require ROOT . '/library/preprocessor.php';
require_once ROOT . '/framework/legacy/Textcube.Control.OIDC.php';

global $openid_session;
global $openid_session_id;

$context = Model_Context::getInstance();
// OIDC 설정 여부 — login/openid?action=try_auth 와 같은 판정(v3.153).
$oidcEnabled = OIDCClient::isEnabled();
if( empty( $_GET['requestURI'] ) ) {
	// 단일 블로그 경로 모드에서 uri.blog 가 빈 문자열이면 '취소' 링크가 이 화면 자신이 되므로 '/' 를 붙인다(v3.153).
	$requestURI = $context->getProperty('uri.blog') . '/';
} else {
	$requestURI = getSafeRedirectURI(filterJavaScript($_GET['requestURI']), $context->getProperty('uri.blog') . '/');
	// 이미 OpenID(OIDC) 신원이면 돌아간다. 소셜 신원은 OpenID 로그인이 아니므로 이 화면을 보여 준다(신원 종류를 섞지 않음, v3.153).
	if( OpenID::identityKind( Acl::getIdentity( 'openid' ) ) === 'oidc' ) {
		header( "Location: $requestURI" );
		exit;
	}
}

list( $openid_help_link, $openid_signup_link ) = fireEvent( 'OpenIDAffiliateLinks', $requestURI );

$img_url = $context->getProperty('uri.host').$context->getProperty('service.path') . "/plugins/" . basename(dirname( __file__ )) . "/login-bg.gif";

// 과거 OpenID 2.0 식별자 입력란·'오픈아이디 기억' 쿠키는 OIDC 로그인에서 쓰지 않는다(login/openid 가 읽지 않음) — 입력란과 함께 제거(v3.153).
?>
<!DOCTYPE html PUBLIC "-//W3C//DTD XHTML 1.1//EN" "http://www.w3.org/TR/xhtml11/DTD/xhtml11.dtd">
<html><head>
<title><?php echo _text('텍스트큐브') .":". _text('오픈아이디 인증'); ?></title>
<meta http-equiv="Content-Type" content="text/html; charset=utf-8" />
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
</head>
<body id="body-login">
	<div id="temp-wrap">
		<div id="all-wrap">
			<form method="get" name="openid_form" action="<?php echo $context->getProperty('uri.blog') . '/login/openid?action=try_auth'?>" >
        		<input type="hidden" name="action" value="try_auth" />
        		<input type="hidden" name="requestURI" value="<?php echo htmlspecialchars($requestURI, ENT_QUOTES); ?>" />
				<div id="data-outbox">
					<div id="login-box">
						<div id="logo-box">
							<img src="<?php echo $context->getProperty('service.path').$adminSkinSetting['skin'];?>/image/logo_textcube.png" alt="<?php echo _text('텍스트큐브 로고'); ?>" />
			            </div>
			            
			            <div class="field-box">
							<h1><?php echo _text('오픈아이디 게스트 로그인'); ?></h1>
							
			            	<?php if( $oidcEnabled ) { ?>
							
							<div class="button-box">
								<input type="submit" class="login-button input-button" name="openid_login" value="<?php echo _text('로그인');?>" />
							</div>
							<?php } else { ?>
							<div id="message-box">
								<?php echo _text('OpenID 로그인이 설정되어 있지 않습니다. 관리자가 OIDC 를 설정해야 합니다.');?>
							</div>
							<?php } ?>
							<?php // '취소'는 돌아갈 주소 링크 — 제출 버튼이면 폼이 try_auth 로 가서 로그인을 시작한다(이식본 퇴행 수정, v3.153) ?>
							<div class="return-box">
								<a href="<?php echo htmlspecialchars($requestURI, ENT_QUOTES); ?>"><?php echo _text('취소') ?></a>
							</div>
							
		            		<ul>
								<?php if( !empty( $openid_help_link ) ) { ?>
								<li id="openid-help"><a href="<?php echo $openid_help_link; ?>" ><?php echo _text('오픈아이디란?') ?></a></li>
								<?php } ?>
								<?php if( !empty( $openid_signup_link ) ) { ?>
								<li><a href="<?php echo $openid_signup_link; ?>"><?php echo _text('오픈아이디 발급하기'); ?></a></li>
								<?php } ?>
							</ul>
						</div>
					</div>
				</div>
			</form>
		</div>
	</div>
</body>
</html>