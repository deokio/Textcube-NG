<?php
/// Copyright (c) 2004-2016, Needlworks  / Tatter Network Foundation
/// All rights reserved. Licensed under the GPL.
/// See the GNU General Public License for more details. (/documents/LICENSE, /documents/COPYRIGHT)

if (!defined('ROOT')) {
	header('HTTP/1.1 403 Forbidden');
	header("Connection: close");
	exit;
}

if( !defined( 'OPENID_REGISTERS' ) ) {
	define('OPENID_REGISTERS', 10);
}

global $hostURL, $service;

function openid_Logout($target)
{
	OpenIDConsumer::logout();
	return $target;
}

/// OpenID 2.0 (EOL) 제거에 따라 자동로그인(openid_hardcore_login)과
/// 위임 head 출력(openid_add_delegate)은 삭제되었다. 인증은 OIDC(login/openid)로 일원화된다.

function openid_ViewCommenter($name, $comment)
{
	$context = Model_Context::getInstance();

	if( $comment['secret'] ) {
		return $name;
	}
	if( empty($comment['openid']) ) {
		return $name;
	}

	$kind = OpenID::identityKind($comment['openid']);

	// OIDC(OpenID Connect) 신원: 식별자가 URL 이 아니므로(oidc:{iss}#{sub}) 하이퍼링크 대신
	// title 만 부여한다(깨진 href·href 인젝션 방지). 표시명은 댓글 작성자명을 그대로 사용.
	// title 에 위장 구별 표지(식별자 HMAC 앞 8자)를 붙이고, 홈페이지가 없을 때의 <span> 표시에도 붙인다(v3.153).
	if( $kind === 'oidc' ) {
		$title = _textf("OIDC(%1)로 작성하였습니다", OpenID::getDisplayName($comment['openid']));
		$badge = OpenID::getIdentityBadge($comment['openid']);
		if( $badge !== '' ) {
			$title .= ' (#' . $badge . ')';
		}
		return OpenID::addCommenterTitle($name, $comment, htmlspecialchars($title, ENT_QUOTES));
	}

	// OIDC 외 비-URL 식별자(소셜 로그인 'kakao:'/'naver:'/'facebook:' 등)는 URL 이 아니므로
	// OpenID 2.0 URL 링크로 처리하지 않고 그대로 통과시킨다(CL_SocialLogin 등 타 플러그인이 표시 처리).
	// URL 판정은 OpenID::identityKind — 'kakao:a://b' 처럼 '://' 가 들어간 비-URL 식별자를 링크로 만들지 않는다(v3.153).
	if( $kind !== 'url' ) {
		return $name;
	}

	// [보안] 저장된 식별자를 href·title 에 넣기 전에 escape 한다(v3.153).
	$openidURL = htmlspecialchars($comment['openid'], ENT_QUOTES);
	$openidTitle = htmlspecialchars(_textf("오픈아이디(%1)로 작성하였습니다", $comment['openid'] ), ENT_QUOTES);
	$openidlogodisplay = Setting::getBlogSettingGlobal( "OpenIDLogoDisplay", 0 );
	if( $openidlogodisplay ) {
		$name = "<a href=\"".$openidURL."\" class=\"openid\"><img src=\"" .$context->getProperty('service.path'). "/resources/image/icon_openid.gif\" alt=\"OpenID Logo\" title=\"" .
			$openidTitle . "\" /></a>" . $name;
	} else {
		$name = OpenID::addCommenterTitle($name, $comment, $openidTitle);
		$name .= "<a href=\"".$openidURL."\" class=\"openid\">&nbsp;</a>";
	}
	return $name;
}

/// 스킨 치환자 [##_OpenIDLogin_##] (v3.153) — 방문자 OpenID(OIDC) 로그인 링크 / 로그인 상태.
/// OIDC 가 설정됐을 때만 출력한다. 소셜 신원(CL_SocialLogin)으로 로그인한 상태에서는 아무것도 출력하지 않는다(신원 종류를 섞어 표시하지 않음).
/// 식별자 원문은 출력하지 않는다. 방문자마다 출력이 다르므로 스킨의 keylog 블록 안에 넣지 말 것.
function openid_LoginTag($target)
{
	require_once ROOT . '/framework/legacy/Textcube.Control.OIDC.php';
	if( !OIDCClient::isEnabled() ) {
		return $target;
	}
	$context = Model_Context::getInstance();
	$identity = Acl::getIdentity('openid');
	$kind = OpenID::identityKind($identity);
	if( $kind === 'social' ) {
		return $target;
	}
	if( $kind === 'oidc' ) {
		$logout = $context->getProperty('uri.blog') . '/login/openid?action=logout&requestURI=' . rawurlencode(OpenID::getReturnURI());
		$html = '<span class="tc-openid-login-state">' . htmlspecialchars(_text('현재 로그인한 오픈아이디') . ' ' . OpenID::getDisplayName($identity), ENT_QUOTES) . '</span> '
			. '<a class="tc-openid-logout" href="' . htmlspecialchars($logout, ENT_QUOTES) . '">' . htmlspecialchars(_text('로그아웃'), ENT_QUOTES) . '</a>';
	} else {
		$html = '<a class="tc-openid-login-link" href="' . htmlspecialchars(OpenID::getGuestLoginURI(), ENT_QUOTES) . '">' . htmlspecialchars(_text('오픈아이디 게스트 로그인'), ENT_QUOTES) . '</a>';
	}
	return $target . '<div class="tc-openid-login">' . $html . '</div>';
}

function openid_OpenIDAffiliateLinks( $links, $requestURI )
{
	include_once "affiliate.php";
	return array( $openid_help_link, $openid_signup_link );
}

/// OIDC (OpenID Connect) 설정 탭 — 이중 옵트인 게이트2.
/// 플러그인 활성화만으로는 동작하지 않으며, 여기서 "활성"으로 바꾸고 Provider 정보를 모두 입력해야
/// OIDCClient::isEnabled() 가 true 가 되어 OIDC 로그인이 동작한다(인증 흐름은 단계 2~에서 구현).
function CL_OpenID_ConfigHandler($plugin)
{
	return '<?xml version="1.0" encoding="utf-8"?>'
		. '<config>'
		. '<fieldset legend="' . htmlspecialchars(_t('OpenID Connect (OIDC) — 기본 비활성. 명시적으로 활성화 + Provider 설정을 모두 입력해야 동작합니다.'), ENT_QUOTES, 'UTF-8') . '">'
		. '<field type="select" name="oidc_enabled" title="' . htmlspecialchars(_t('OIDC 활성화'), ENT_QUOTES, 'UTF-8') . '">'
		. '<op value="n" checked="checked">' . htmlspecialchars(_t('비활성 (기본)'), ENT_QUOTES, 'UTF-8') . '</op>'
		. '<op value="y">' . htmlspecialchars(_t('활성'), ENT_QUOTES, 'UTF-8') . '</op>'
		. '<caption>' . htmlspecialchars(_t('플러그인 활성화만으로는 동작하지 않습니다. 이 항목을 "활성"으로 바꾸고 아래 Provider 정보를 모두 입력해야 OIDC 로그인이 동작합니다.'), ENT_QUOTES, 'UTF-8') . '</caption>'
		. '</field>'
		. '<field type="text" name="oidc_issuer" title="Issuer URL" value="" size="60">'
		. '<caption>' . htmlspecialchars(_t('OIDC Provider issuer. 예: https://accounts.google.com (자동으로 /.well-known/openid-configuration 을 조회합니다)'), ENT_QUOTES, 'UTF-8') . '</caption>'
		. '</field>'
		. '<field type="text" name="oidc_client_id" title="Client ID" value="" size="60"></field>'
		. '<field type="text" name="oidc_client_secret" title="Client Secret" value="" size="60">'
		. '<caption>' . htmlspecialchars(_t('Provider 에 등록한 클라이언트 자격증명.'), ENT_QUOTES, 'UTF-8') . '</caption>'
		. '</field>'
		. '<field type="text" name="oidc_redirect_uri" title="' . htmlspecialchars(_t('Redirect URI (선택)'), ENT_QUOTES, 'UTF-8') . '" value="" size="60">'
		. '<caption>' . htmlspecialchars(_t('Provider 에 등록한 콜백 URL. 미입력 시 {블로그}/login/openid/callback 자동 사용(단계 2).'), ENT_QUOTES, 'UTF-8') . '</caption>'
		. '</field>'
		. '</fieldset>'
		. '</config>';
}
?>
