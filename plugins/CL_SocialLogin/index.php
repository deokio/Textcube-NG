<?php
/// Copyright (c) 2004-2016, Needlworks  / Tatter Network Foundation
/// All rights reserved. Licensed under the GPL.
/// See the GNU General Public License for more details. (/documents/LICENSE, /documents/COPYRIGHT)
///
/// CL_SocialLogin — 카카오/네이버/페이스북 등 소셜 OAuth 로그인 통합 플러그인.
/// 기존 OIDC 신원 인프라(Acl 'openid' 식별자 · UserSettings openid.* 명시적 연결 · Comments.openid
/// 게스트 댓글 · ViewCommenter · OIDCClient::mapToUser)를 그대로 재사용하고, 그 위에 다중 프로바이더
/// OAuth2/OIDC 어댑터를 얹는다. 식별자 = '{provider}:{프로바이더_사용자ID}'.
///
/// 이중 옵트인(기본 비활성): ① 플러그인 활성 ② 프로바이더별 enable='y' + Client ID/Secret 입력.
/// 인증 시작은 auth.php, 콜백은 callback.php(고정 서비스레벨 redirect_uri). 이 파일은 설정·버튼·표시 담당.

if (!defined('ROOT')) {
	header('HTTP/1.1 403 Forbidden');
	header("Connection: close");
	exit;
}

/// Textcube-NG: 페이스북 Graph API 기본 버전(r3-14). 설정 칸(facebook_api_version)이 비었거나 형식이 다를 때 쓴다
if (!defined('CL_SOCIALLOGIN_FACEBOOK_API_VERSION')) define('CL_SOCIALLOGIN_FACEBOOK_API_VERSION', 'v26.0');

/// ---------- Provider registry ----------
/// 프로바이더별 변이(endpoint/scope/필드 매핑)만 여기에 둔다. 나머지 흐름(state/PKCE/신원)은 전부 공통.
/// type: 'oidc'(id_token RS256 검증 가능) | 'oauth2'(access_token → userinfo).
/// Textcube-NG: 페이스북 Graph API 버전은 설정 값(facebook_api_version)으로 정한다. $cfg 는 CL_SocialLogin_config() 결과이고,
/// 넘기지 않으면(null) 설정을 읽지 않고 기본 버전을 쓴다(라벨·색만 쓰는 표시 경로). 주소를 쓰는 경로(CL_SocialLogin_OAuth::provider)는
/// 설정을 넘긴다. 이 함수는 CL_SocialLogin_config() 를 부르지 않는다(r3-14)
function CL_SocialLogin_providers($cfg = null) {
	$facebookVersion = CL_SocialLogin_facebookApiVersion($cfg);
	return array(
		'kakao' => array(
			'label'         => 'Kakao',
			'type'          => 'oidc',
			'issuer'        => 'https://kauth.kakao.com',
			'authorize_url' => 'https://kauth.kakao.com/oauth/authorize',
			'token_url'     => 'https://kauth.kakao.com/oauth/token',
			'userinfo_url'  => 'https://kapi.kakao.com/v2/user/me',
			'scope'         => 'openid profile_nickname',
			'color'         => '#FEE500',
			'textcolor'     => '#191600',
		),
		'naver' => array(
			'label'         => 'Naver',
			'type'          => 'oauth2',
			'authorize_url' => 'https://nid.naver.com/oauth2.0/authorize',
			'token_url'     => 'https://nid.naver.com/oauth2.0/token',
			'userinfo_url'  => 'https://openapi.naver.com/v1/nid/me',
			'scope'         => '',
			'color'         => '#03C75A',
			'textcolor'     => '#ffffff',
		),
		'facebook' => array(
			'label'         => 'Facebook',
			'type'          => 'oauth2',
			'authorize_url' => 'https://www.facebook.com/' . $facebookVersion . '/dialog/oauth',
			'token_url'     => 'https://graph.facebook.com/' . $facebookVersion . '/oauth/access_token',
			'userinfo_url'  => 'https://graph.facebook.com/' . $facebookVersion . '/me?fields=id,name,email',
			'scope'         => 'email public_profile',
			'color'         => '#1877F2',
			'textcolor'     => '#ffffff',
		),
	);
}

/// Textcube-NG: 페이스북 Graph API 버전(r3-14). $cfg['facebook_api_version'] 이 문자열이고 'v' + 숫자 1~3자리 + '.' + 숫자 1~2자리
/// 형식일 때만 그 값, 그 밖(배열이 아닌 $cfg·키 없음·빈 값·배열 값·다른 형식)은 기본 버전. 앞뒤 공백·줄바꿈은 읽을 때 잘라 낸다(저장 경로는
/// 그대로 둔다). 값은 주소 경로에 그대로 들어가므로 자른 뒤 형식 밖의 문자(/ ? # 공백 등)는 받지 않는다. 정규식의 D 는 끝의 줄바꿈 하나를 허용하는 $ 의 동작을 끈다
function CL_SocialLogin_facebookApiVersion($cfg = null) {
	if (is_array($cfg) && isset($cfg['facebook_api_version']) && is_string($cfg['facebook_api_version'])) {
		$v = trim($cfg['facebook_api_version']);
		if (preg_match('/^v\d{1,3}\.\d{1,2}$/D', $v)) return $v;
	}
	return CL_SOCIALLOGIN_FACEBOOK_API_VERSION;
}

/// 플러그인 config(블로그 스코프) 읽기. 활성 플러그인일 때만 유효(getCurrentSetting).
function CL_SocialLogin_config() {
	if (!function_exists('getCurrentSetting') || !class_exists('Setting')) return array();
	$raw = getCurrentSetting('CL_SocialLogin');
	if (empty($raw)) return array();
	$cfg = Setting::fetchConfigVal($raw);
	return is_array($cfg) ? $cfg : array();
}

/// 프로바이더 사용 가능 여부: enable='y' + client_id + client_secret 모두 충족.
function CL_SocialLogin_isEnabled($provider, $cfg = null) {
	$providers = CL_SocialLogin_providers();
	if (!isset($providers[$provider])) return false;
	if ($cfg === null) $cfg = CL_SocialLogin_config();
	if (empty($cfg[$provider . '_enabled']) || $cfg[$provider . '_enabled'] !== 'y') return false;
	if (empty($cfg[$provider . '_client_id']) || empty($cfg[$provider . '_client_secret'])) return false;
	return true;
}

/// 블로그별 redirect_uri — 각 블로그가 자기 호스트의 콜백을 쓴다(블로그 독립·크로스도메인 회피).
/// 각 블로그 관리자가 자기 OAuth 앱에 이 주소(자기 블로그의 callback)를 등록한다.
function CL_SocialLogin_redirectUri() {
	$ctx  = Model_Context::getInstance();
	$host = $ctx->getProperty('uri.host');
	$blog = $ctx->getProperty('uri.blog');
	/* [보안] uri.host 의 스킴은 service.useSSL 로만 정해져 https 로 운영해도 http:// 콜백 주소가 나왔다(제공자가 인가 코드를 평문 주소로 보냄) →
	   uri.service 와 같은 신뢰 판정(신뢰 프록시 플러그인이 신뢰한 연결은 proxy.scheme, config.php trustedProxies 에 속한 연결은
	   X-Forwarded-Proto, 그 밖의 연결은 헤더 무시) → 서버 변수 HTTPS → service.useSSL 순. 호스트·포트·경로 접두는 그대로 (v3.152) */
	$scheme = '';
	if ($ctx->getProperty('proxy.trusted') === true)
		$scheme = (string)$ctx->getProperty('proxy.scheme');
	else if (defined('__TEXTCUBE_TRUSTED_PEER__') && isset($_SERVER['HTTP_X_FORWARDED_PROTO']))
		$scheme = strtolower(trim($_SERVER['HTTP_X_FORWARDED_PROTO']));
	if ($scheme !== 'https' && $scheme !== 'http') {
		if (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') $scheme = 'https';
		else $scheme = $ctx->getProperty('service.useSSL', false) ? 'https' : 'http';
	}
	/* Textcube-NG: r3-27 R-426 — config.php 의 service.serviceURL 이 있으면 그 스킴·호스트·포트를 콜백 주소에 쓴다(요청 Host 를 위조해 콜백 주소를 바꾸지 못하게). 설정이 없으면(기본) 아래 기존 동작 그대로. domain 모드는 블로그마다 호스트가 달라 건너뛴다. 서비스 주소가 //host 이면 위에서 정한 스킴(요청 기준)을 붙여 같은 방어를 한다(r3-34) */
	$configuredService = $ctx->getProperty('service.serviceURL');
	if (is_string($configuredService)) $configuredService = URL::toAbsolute($configuredService, $scheme);
	if (is_string($configuredService) && $configuredService !== '' && $ctx->getProperty('service.type') !== 'domain'
		&& preg_match('@^(https?://[^/]+)@i', $configuredService, $configuredOrigin))
		return $configuredOrigin[1] . ($blog !== null ? $blog : '') . '/login/social/callback';
	if ($host !== null) $host = preg_replace('@^https?://@i', $scheme . '://', $host, 1);
	return ($host !== null ? $host : '') . ($blog !== null ? $blog : '') . '/login/social/callback';
}

/// ---------- 설정 화면(manifest) ----------
/// registry 루프로 프로바이더별 enable/client_id/client_secret 필드를 생성. 저장값은 프레임워크가 자동 prefill.
function CL_SocialLogin_ConfigHandler($plugin) {
	$redirect = CL_SocialLogin_redirectUri();
	$xml  = '<?xml version="1.0" encoding="utf-8"?>';
	$xml .= '<config>';
	$xml .= '<fieldset legend="' . htmlspecialchars(_f('소셜 로그인 — 기본 비활성. 사용할 프로바이더만 활성으로 바꾸고 Client ID/Secret 을 입력하세요. Redirect URI(콜백): %1 — 이 주소를 각 프로바이더 개발자콘솔에 등록하세요.', $redirect), ENT_QUOTES, 'UTF-8') . '">';
	$xml .= '</fieldset>';
	// Textcube-NG: 댓글·방명록 입력 폼에 방문자용 소셜 로그인 버튼을 자동으로 넣을지(기본 표시, 저장값이 없어도 표시). CL_SocialLogin_CommentForm 이 읽는다(r3-39)
	$xml .= '<fieldset legend="' . htmlspecialchars(_t('댓글·방명록 입력 폼'), ENT_QUOTES, 'UTF-8') . '">';
	$xml .= '<field type="select" name="comment_form_auto" title="' . htmlspecialchars(_t('소셜 로그인 버튼 자동 표시'), ENT_QUOTES, 'UTF-8') . '">';
	$xml .= '<op value="y" checked="checked">' . htmlspecialchars(_t('표시 (기본)'), ENT_QUOTES, 'UTF-8') . '</op>';
	$xml .= '<op value="n">' . htmlspecialchars(_t('표시하지 않음'), ENT_QUOTES, 'UTF-8') . '</op>';
	$xml .= '<caption>' . htmlspecialchars(_t('활성화한 공급자의 로그인 버튼을 댓글·방명록의 방문자 입력 칸 위에 자동으로 넣습니다. 소셜 계정으로 로그인한 방문자에게는 로그인 상태와 로그아웃 링크를 보여 줍니다. 스킨에 소셜 로그인 치환자를 따로 넣어 쓰고 있다면 버튼이 두 번 나오지 않도록 표시하지 않음으로 바꾸세요.'), ENT_QUOTES, 'UTF-8') . '</caption>';
	$xml .= '</field>';
	$xml .= '</fieldset>';
	// Textcube-NG: 제공자별 안내(콘솔에서 만들 것·쓰는 API 와 버전·Redirect URI 등록·동의 항목)를 활성화 칸 아래 caption 으로 둔다.
	// caption 은 설정 화면에서 escape 없이 출력되므로(common.plugin.php TreatType) 고정 문구만 넣고 사이트 값(Redirect URI 등)은 넣지 않는다(r3-14)
	$guide = array(
		'kakao'    => _t('Kakao Developers에서 앱을 만들고 카카오 로그인과 OpenID Connect를 켜세요. 이 플러그인은 카카오 로그인(OAuth 2.0 + OpenID Connect)의 ID 토큰으로 사용자를 확인하고, ID 토큰이 없을 때만 사용자 정보 가져오기 API v2(/v2/user/me)를 씁니다. 카카오 로그인에는 따로 고를 API 버전이 없습니다. 위의 Redirect URI를 카카오 로그인의 Redirect URI에 등록하세요. 동의 항목: 닉네임, 카카오계정(이메일) — 이메일은 앱 설정에 따라 별도 권한이 필요할 수 있습니다. 이메일 동의가 없어도 로그인 판정에는 영향이 없습니다(이 플러그인은 이메일을 쓰지 않습니다).'),
		'naver'    => _t('네이버 개발자센터에서 애플리케이션을 등록하고 사용 API로 네이버 로그인을 고르세요. 이 플러그인은 네이버 로그인(OAuth 2.0)과 회원 프로필 조회 API v1(/v1/nid/me)을 씁니다. 네이버 로그인에는 따로 고를 API 버전이 없습니다. 제공 정보에서 이메일 주소와 이름(또는 별명)을 고르고, 서비스 URL에 블로그 주소를, Callback URL에 위의 Redirect URI를 넣으세요.'),
		'facebook' => _t('Meta for Developers에서 앱을 만들고 Facebook 로그인을 추가하세요. 이 플러그인은 Facebook 로그인(OAuth 2.0)과 Graph API의 /me(필드 id, name, email)를 쓰며, 버전은 아래 Graph API 버전 칸의 값을 씁니다. 위의 Redirect URI를 Facebook 로그인 설정의 유효한 OAuth 리디렉션 URI에 등록하세요. 요청 권한: email, public_profile.'),
	);
	// Textcube-NG: Client ID·Client Secret 칸 caption 을 제공자별 문구로 나눈다(r3-14). 제공자별 문구가 없는 제공자는 기존 공통 문구를 쓴다
	$idGuide = array(
		'kakao'    => _t('Kakao Developers 앱의 REST API 키입니다.'),
		'naver'    => _t('네이버 개발자센터 애플리케이션 정보의 Client ID입니다.'),
		'facebook' => _t('Meta for Developers 앱의 앱 ID입니다.'),
	);
	$secretGuide = array(
		'kakao'    => _t('카카오 로그인 보안 설정에서 만든 Client Secret 코드입니다. 이 플러그인은 Client Secret이 있어야 동작하므로 사용 상태로 두세요.'),
		'naver'    => _t('네이버 개발자센터 애플리케이션 정보의 Client Secret입니다.'),
		'facebook' => _t('Meta for Developers 앱의 앱 시크릿 코드입니다.'),
	);
	foreach (CL_SocialLogin_providers() as $key => $p) {
		$L = htmlspecialchars($p['label'], ENT_QUOTES | ENT_SUBSTITUTE);
		$xml .= '<fieldset legend="' . $L . '">';
		$xml .= '<field type="select" name="' . $key . '_enabled" title="' . htmlspecialchars(_f('%1 활성화', $p['label']), ENT_QUOTES, 'UTF-8') . '">';
		$xml .= '<op value="n" checked="checked">' . htmlspecialchars(_t('비활성 (기본)'), ENT_QUOTES, 'UTF-8') . '</op>';
		$xml .= '<op value="y">' . htmlspecialchars(_t('활성'), ENT_QUOTES, 'UTF-8') . '</op>';
		if (isset($guide[$key])) $xml .= '<caption>' . htmlspecialchars($guide[$key], ENT_QUOTES, 'UTF-8') . '</caption>';
		$xml .= '</field>';
		$xml .= '<field type="text" name="' . $key . '_client_id" title="Client ID" value="" size="60">';
		$xml .= '<caption>' . htmlspecialchars(isset($idGuide[$key]) ? $idGuide[$key] : _f('%1 개발자콘솔의 앱 키(REST API 키 / Client ID).', $p['label']), ENT_QUOTES, 'UTF-8') . '</caption>';
		$xml .= '</field>';
		$xml .= '<field type="text" name="' . $key . '_client_secret" title="Client Secret" value="" size="60">';
		$xml .= '<caption>' . htmlspecialchars(isset($secretGuide[$key]) ? $secretGuide[$key] : _f('%1 Client Secret. (네이버=Client Secret, 카카오=보안 Client Secret, 페이스북=앱 시크릿)', $p['label']), ENT_QUOTES, 'UTF-8') . '</caption>';
		$xml .= '</field>';
		// Textcube-NG: 페이스북 Graph API 버전 칸(기본 CL_SOCIALLOGIN_FACEBOOK_API_VERSION). 저장값이 없으면 value 가 기본값으로 보인다(r3-14)
		if ($key === 'facebook') {
			$xml .= '<field type="text" name="facebook_api_version" title="' . htmlspecialchars(_t('Graph API 버전'), ENT_QUOTES, 'UTF-8') . '" value="' . htmlspecialchars(CL_SOCIALLOGIN_FACEBOOK_API_VERSION, ENT_QUOTES, 'UTF-8') . '" size="10">';
			$xml .= '<caption>' . htmlspecialchars(_f('Graph API 버전. 형식은 vN.N(예: %1)입니다. 비우거나 형식이 맞지 않으면 %1을 씁니다. Graph API는 버전마다 사용 기한(만료일)이 있으므로 Meta 개발자 문서의 Graph API 버전 목록(Changelog)에서 만료일을 확인하고, 쓰는 버전이 만료되기 전에 새 버전으로 바꾸세요.', CL_SOCIALLOGIN_FACEBOOK_API_VERSION), ENT_QUOTES, 'UTF-8') . '</caption>';
			$xml .= '</field>';
		}
		$xml .= '</fieldset>';
	}
	$xml .= '</config>';
	return $xml;
}

/// ---------- 로그인 폼 버튼(LOGIN_add_form 이벤트) ----------
/// 활성 프로바이더만 버튼 렌더. 각 버튼은 auth.php?provider=&requestURI= 로 인증을 시작한다.
function CL_SocialLogin_LoginForm($content, $requestURI) {
	$context = Model_Context::getInstance();
	$cfg  = CL_SocialLogin_config();
	$base = $context->getProperty('uri.blog') . '/login/social';
	$buttons = '';
	foreach (CL_SocialLogin_providers() as $key => $p) {
		if (!CL_SocialLogin_isEnabled($key, $cfg)) continue;
		$url = $base . '?provider=' . rawurlencode($key) . '&requestURI=' . rawurlencode((string)$requestURI);
		$buttons .= '<li class="social-login-item social-' . $key . '">'
			. '<a class="social-login-link" href="' . htmlspecialchars($url, ENT_QUOTES) . '"'
			. ' style="background-color:' . htmlspecialchars($p['color'], ENT_QUOTES) . ';color:' . htmlspecialchars($p['textcolor'], ENT_QUOTES) . ';">'
			. htmlspecialchars(_textf('%1 (으)로 로그인', $p['label']), ENT_QUOTES | ENT_SUBSTITUTE)
			. '</a></li>';
	}
	if ($buttons === '') return $content;
	// 제목은 관리자 로그인 화면의 OpenID 블록과 같은 형식(h2.login-method-title), 배치는 관리자 스킨 login.css(v3.153).
	return $content
		. '<div id="social-login-box" class="field-box">'
		. '<h2 class="login-method-title">' . htmlspecialchars(_text('관리자 계정과 연결된 소셜 계정'), ENT_QUOTES | ENT_SUBSTITUTE) . '</h2>'
		. '<ul id="social-login-list">' . $buttons . '</ul>'
		. '</div>';
}

/// ---------- 방문자용 소셜 로그인 상자(스킨 치환자·댓글 폼 자동 삽입 공용, r3-39) ----------
/// 소셜 신원(registry 에 있는 공급자)으로 로그인한 상태면 "{공급자} 계정으로 로그인됨 · 로그아웃", 그 밖(미로그인·OIDC 신원)은 버튼.
/// 쓸 수 있는 공급자가 없으면 빈 문자열. 식별자 원문은 출력하지 않는다. 방문자마다 출력이 다르다.
/// $auto 가 true 면(댓글·방명록 폼 자동 삽입) 오픈아이디 패널(addOpenIDPannel)과 같은 모양 — 글자 한 줄에 안내 문구와 공급자별
/// 대괄호 링크([Kakao][Naver]…, 글자색 #ff6200), 로그인 상태면 "… 계정으로 로그인됨 [로그아웃]" — 으로 내고 클래스 tc-social-login-auto 를 붙인다.
/// $prefix 는 자동 삽입 자리('rp'=댓글, 'guest'=방명록)로 안내 문구를 고른다(r3-40).
/// $auto 가 false 면(스킨 치환자) 출력은 v3.153 의 CL_SocialLogin_LoginTag 와 같다.
function CL_SocialLogin_visitorBox($auto = false, $prefix = '') {
	$context   = Model_Context::getInstance();
	$providers = CL_SocialLogin_providers();
	$return    = OpenID::getReturnURI();
	$identity  = Acl::getIdentity('openid');
	$boxOpen   = $auto ? '<div class="tc-social-login tc-social-login-auto" style="width:100%;text-align:left;padding:0 0 5px 0;">' : '<div class="tc-social-login">';
	if (OpenID::identityKind($identity) === 'social') {
		$key = substr($identity, 0, strpos($identity, ':'));
		if (isset($providers[$key])) {
			$logout = $context->getProperty('uri.blog') . '/login/openid?action=logout&requestURI=' . rawurlencode($return);
			$logoutLink = '<a class="tc-social-logout" href="' . htmlspecialchars($logout, ENT_QUOTES) . '">' . htmlspecialchars(_text('로그아웃'), ENT_QUOTES | ENT_SUBSTITUTE) . '</a>';
			return $boxOpen
				. '<span class="tc-social-login-state">' . htmlspecialchars(_textf('%1 계정으로 로그인됨', $providers[$key]['label']), ENT_QUOTES | ENT_SUBSTITUTE) . '</span> '
				. ($auto ? '<span>[' . $logoutLink . ']</span>' : $logoutLink)
				. '</div>';
		}
	}
	$cfg  = CL_SocialLogin_config();
	$base = $context->getProperty('uri.blog') . '/login/social';
	$buttons = '';
	foreach ($providers as $key => $p) {
		if (!CL_SocialLogin_isEnabled($key, $cfg)) continue;
		$url = $base . '?provider=' . rawurlencode($key) . '&requestURI=' . rawurlencode($return);
		if ($auto) {
			// 오픈아이디 패널의 [로그인] 링크와 같은 모양(글자색 #ff6200, r3-40)
			$buttons .= '[<a class="tc-social-login-link social-' . $key . '" href="' . htmlspecialchars($url, ENT_QUOTES) . '">'
				. '<span style="color:#ff6200">' . htmlspecialchars($p['label'], ENT_QUOTES | ENT_SUBSTITUTE) . '</span></a>]';
			continue;
		}
		$buttons .= '<li class="tc-social-login-item social-' . $key . '">'
			. '<a class="tc-social-login-link" href="' . htmlspecialchars($url, ENT_QUOTES) . '"'
			. ' style="background-color:' . htmlspecialchars($p['color'], ENT_QUOTES) . ';color:' . htmlspecialchars($p['textcolor'], ENT_QUOTES) . ';">'
			. htmlspecialchars(_textf('%1 (으)로 로그인', $p['label']), ENT_QUOTES | ENT_SUBSTITUTE)
			. '</a></li>';
	}
	if ($buttons === '') return '';
	if ($auto) {
		if ($prefix === 'rp') $title = _text('소셜 계정으로 로그인 후 댓글 쓰기');
		else if ($prefix === 'guest') $title = _text('소셜 계정으로 로그인 후 방명록 쓰기');
		else $title = _text('소셜 계정으로 로그인');
		return $boxOpen . '<span class="tc-social-login-title">' . htmlspecialchars($title, ENT_QUOTES | ENT_SUBSTITUTE) . '</span> '
			. '<span class="tc-social-login-list">' . $buttons . '</span></div>';
	}
	return $boxOpen . '<ul class="tc-social-login-list">' . $buttons . '</ul></div>';
}

/// ---------- 스킨 치환자 [##_SocialLogin_##] (v3.153) ----------
/// 방문자용 소셜 로그인 버튼 / 로그인 상태(CL_SocialLogin_visitorBox). 스킨의 원하는 자리에 넣는다. 댓글·방명록 폼에 자동으로 넣는 것은
/// 아래 CL_SocialLogin_CommentForm 이 맡으며, 이 치환자를 폼 안에 넣어 쓰면 설정에서 자동 표시를 끈다(r3-39).
/// 방문자마다 출력이 다르므로 스킨의 keylog 블록 안에 넣지 말 것.
function CL_SocialLogin_LoginTag($target) {
	return $target . CL_SocialLogin_visitorBox(false);
}

/// ---------- 댓글·방명록 입력 폼 자동 삽입(ViewCommentGuestForm·ViewCommentOpenIDOnlyForm 이벤트, r3-39) ----------
/// 방문자(비회원) 입력 블록 앞, 오픈아이디 전용 모드에서 입력 폼 대신 나오는 안내 블록 앞에 방문자용 상자를 넣는다.
/// 설정 comment_form_auto 가 'n' 이면 넣지 않는다(저장값이 없으면 넣는다). 쓸 수 있는 공급자가 없으면 블록을 그대로 돌려준다.
function CL_SocialLogin_CommentForm($target, $prefix) {
	if (!is_string($target)) return $target;
	$cfg = CL_SocialLogin_config();
	if (isset($cfg['comment_form_auto']) && $cfg['comment_form_auto'] === 'n') return $target;
	$box = CL_SocialLogin_visitorBox(true, (string)$prefix);
	return ($box === '') ? $target : $box . $target;
}

/// ---------- 댓글·방명록 입력 폼 '소셜 계정으로 글쓰기' 선택(ViewCommentGuestForm 이벤트, r3-41) ----------
/// 오픈아이디 패널(addOpenIDPannel)의 comment_type 라디오 줄과 같은 모양으로 "○ 소셜 계정으로 글쓰기 [Kakao][Naver]…" 줄
/// (value="social")을 패널 첫 줄에 넣는다. CL_OpenID 가 꺼져 패널이 없으면 같은 모양의 두 줄 패널(소셜 · 이름/비밀번호)을 만든다.
/// 소셜 계정으로 로그인한 상태면 이 줄을 기본 선택하고 이름·비밀번호 칸을 잠근다(view.php 의 $disabled2 와 같은 정규식).
/// 글쓴이 이름은 서버(interface/blog/comment/add)가 세션의 표시 이름으로 정한다. 로그인하지 않고 이 줄을 골라 보내면
/// common3.js 가 data-tc-social 값을 보고 막으며, 서버도 소셜 신원이 없으면 받지 않는다.
/// 설정 comment_form_auto 가 'n' 이면 넣지 않는다. 쓸 수 있는 공급자가 없고 소셜 로그인 상태도 아니면 블록을 그대로 돌려준다.
function CL_SocialLogin_CommentGuestForm($target, $prefix) {
	if (!is_string($target)) return $target;
	$cfg = CL_SocialLogin_config();
	if (isset($cfg['comment_form_auto']) && $cfg['comment_form_auto'] === 'n') return $target;
	$prefix = (string)$prefix;
	// 접두어는 치환자 이름과 정규식에 들어간다 — view.php 가 넘기는 'rp'·'guest' 같은 영문 소문자만 받는다
	if (!preg_match('/\A[a-z]+\z/', $prefix)) return $target;
	$context   = Model_Context::getInstance();
	$providers = CL_SocialLogin_providers();
	$return    = OpenID::getReturnURI();
	$identity  = Acl::getIdentity('openid');
	$social    = false;
	$state     = '';
	if (OpenID::identityKind($identity) === 'social') {
		$key = substr($identity, 0, strpos($identity, ':'));
		if (isset($providers[$key])) {
			$social = true;
			$logout = $context->getProperty('uri.blog') . '/login/openid?action=logout&requestURI=' . rawurlencode($return);
			$state = '<span class="tc-social-login-state">' . htmlspecialchars(_textf('%1 계정으로 로그인됨', $providers[$key]['label']), ENT_QUOTES | ENT_SUBSTITUTE) . '</span> '
				. '[<a class="tc-social-logout" href="' . htmlspecialchars($logout, ENT_QUOTES) . '">' . htmlspecialchars(_text('로그아웃'), ENT_QUOTES | ENT_SUBSTITUTE) . '</a>]';
		}
	}
	if (!$social) {
		$base = $context->getProperty('uri.blog') . '/login/social';
		foreach ($providers as $key => $p) {
			if (!CL_SocialLogin_isEnabled($key, $cfg)) continue;
			$url = $base . '?provider=' . rawurlencode($key) . '&requestURI=' . rawurlencode($return);
			$state .= '[<a class="tc-social-login-link social-' . $key . '" href="' . htmlspecialchars($url, ENT_QUOTES) . '">'
				. '<span style="color:#ff6200">' . htmlspecialchars($p['label'], ENT_QUOTES | ENT_SUBSTITUTE) . '</span></a>]';
		}
		if ($state === '') return $target;
	}
	// 모양은 addOpenIDPannel 의 $pannel_style·$radio_style·$label_style 과 같다
	$panelStyle = 'style="width:100%; text-align:left"';
	$radioStyle = 'style="width:15px;vertical-align:text-bottom;height:15px;border:0px;margin:0px;padding:0px;"';
	$labelStyle = 'style="display:inline;margin-top:0px;padding-left:0px;cursor:pointer"';
	$nameField  = '[##_' . $prefix . '_input_name_##]';
	$pwField    = '[##_' . $prefix . '_input_password_##]';
	$row = '<div class="commentTypeSocial" ' . $panelStyle . '>'
		. '<input class="commentTypeCheckbox" ' . ($social ? 'checked="checked" ' : '') . 'type="radio" ' . CRLF
		. $radioStyle . CRLF
		. 'id="comment_type_[##_article_rep_id_##]_social" ' . CRLF
		. 'name="comment_type" value="social" data-tc-social="' . ($social ? 'ok' : 'login') . '" '
		. 'data-tc-social-msg="' . htmlspecialchars(_text('소셜 계정으로 로그인해 주십시오.'), ENT_QUOTES | ENT_SUBSTITUTE) . '" ' . CRLF
		. 'onclick="this.form.' . $nameField . '.disabled=this.form.' . $pwField . '.disabled=true;if(this.form.openid_identifier)this.form.openid_identifier.disabled=true;"' . CRLF
		. '/> ' . CRLF
		. '<label for="comment_type_[##_article_rep_id_##]_social" ' . $labelStyle . '>'
		. htmlspecialchars(_text('소셜 계정으로 글쓰기'), ENT_QUOTES | ENT_SUBSTITUTE)
		. '</label> <span class="tc-social-login-list">' . $state . '</span></div>' . CRLF;
	if ($social) {
		// 다른 줄(오픈아이디·이름/비밀번호)의 기본 선택을 풀고 이름·비밀번호 칸을 잠근다
		$target = str_replace('<input class="commentTypeCheckbox" checked="checked" type="radio" ', '<input class="commentTypeCheckbox"  type="radio" ', $target);
		$disabled = 'disabled="disabled"';
		$target = preg_replace("/(.*)(<input)((?:[^>]+)name_\\[##_article_rep_id_##\\](?:[^>]+)>(?:.*))/sm", "$1$2 $disabled $3", $target);
		$target = preg_replace("/(.*)(<input)((?:[^>]+)password_\\[##_article_rep_id_##\\](?:[^>]+)>(?:.*))/sm", "$1$2 $disabled $3", $target);
		$target = preg_replace("/(.*)(<input)((?:[^>]+)\\[##_{$prefix}_input_name_##\\](?:[^>]+)>(?:.*))/sm", "$1$2 $disabled $3", $target);
		$target = preg_replace("/(.*)(<input)((?:[^>]+)\\[##_{$prefix}_input_password_##\\](?:[^>]+)>(?:.*))/sm", "$1$2 $disabled $3", $target);
	}
	$panelOpen = '<div class="commentOuterPannel">' . CRLF;
	if (strpos($target, $panelOpen) === 0) {
		// 오픈아이디 패널이 있으면 그 첫 줄로
		return $panelOpen . $row . substr($target, strlen($panelOpen));
	}
	// CL_OpenID 가 꺼져 패널이 없으면 같은 모양의 두 줄 패널을 만든다
	$idpwd = '<div class="commentTypeNamepassword" ' . $panelStyle . ' >' . CRLF
		. '<input class="commentTypeCheckbox" ' . ($social ? '' : 'checked="checked" ') . 'type="radio" ' . CRLF
		. $radioStyle . CRLF
		. 'id="comment_type_[##_article_rep_id_##]_idpwd" ' . CRLF
		. 'name="comment_type" value="idpwd" ' . CRLF
		. 'onclick="this.form.' . $nameField . '.disabled=this.form.' . $pwField . '.disabled=false;"' . CRLF
		. '/> ' . CRLF
		. '<label for="comment_type_[##_article_rep_id_##]_idpwd" ' . $labelStyle . '>'
		. htmlspecialchars(_text('이름/비밀번호로 글쓰기'), ENT_QUOTES | ENT_SUBSTITUTE) . '</label></div>' . CRLF;
	return $panelOpen . $row . $idpwd . $target . '</div>';
}

/// ---------- 댓글 작성자 표시(ViewCommenter) ----------
/// 소셜 식별자({provider}:{id})는 URL 이 아니므로 하이퍼링크 없이 title 만 부여(깨진 href·인젝션 방지).
/// 우리 식별자가 아니면(예: OIDC 'oidc:…') 그대로 두어 CL_OpenID 가 처리하게 한다.
/// title 에 위장 구별 표지(식별자 HMAC 앞 8자)를 붙이고, 홈페이지가 없을 때의 <span> 표시에도 붙인다(OpenID::addCommenterTitle, v3.153).
function CL_SocialLogin_ViewCommenter($name, $comment) {
	if (empty($comment['openid'])) return $name;
	if (!empty($comment['secret'])) return $name;
	$id = $comment['openid'];
	if (OpenID::identityKind($id) !== 'social') return $name;
	$providers = CL_SocialLogin_providers();
	$matched = substr($id, 0, strpos($id, ':'));
	if (!isset($providers[$matched])) return $name;
	$title = _textf('%1 계정으로 작성하였습니다', $providers[$matched]['label']);
	$badge = OpenID::getIdentityBadge($id);
	if ($badge !== '') $title .= ' (#' . $badge . ')';
	return OpenID::addCommenterTitle($name, $comment, htmlspecialchars($title, ENT_QUOTES));
}
?>
