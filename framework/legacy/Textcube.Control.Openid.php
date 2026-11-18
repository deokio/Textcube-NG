<?php
/// Copyright (c) 2004-2007, Needlworks / Tatter Network Foundation
/// All rights reserved. Licensed under the GPL.
/// See the GNU General Public License for more details. (/documents/LICENSE, /documents/COPYRIGHT)
///
/// ---- Modification Notice ----
/// OpenID 2.0 (protocol EOL — 주요 Provider 가 2015 년경 지원 종료) 제거.
/// 인증은 OpenID Connect(OIDC, Textcube.Control.OIDC.php)로 일원화한다.
/// phpopenid(library/contrib/phpopenid) 의존과 Yadis/Consumer/Transaction 기반 2.0 인증 흐름
///   (fetch/tryAuth/finishAuth/setAcl/update/setDelegate/setComment 등)을 모두 제거하고,
///   OIDC 흐름과 댓글 표시가 공유하는 경량 헬퍼만 남긴다.
/// 모든 헬퍼를 static 으로 통일한다 — 기존 코드는 non-static 메서드를 정적(::)으로 호출했고,
///   이는 PHP 8 에서 치명적 오류이므로 호출 형태와 정의를 일치시킨다.

class OpenID {
	public static function setCookie( $key, $value )
	{
		$context = Model_Context::getInstance();
		$session_cookie_path = "/";
		$savedPath = $context->getProperty('service.session_cookie_path');
		if( !empty($savedPath)) {
			$session_cookie_path = $context->getProperty('service.session_cookie_path');
		}
		if( !headers_sent() ) {
			setcookie( $key, $value, array('expires' => time()+3600*24*30, 'path' => $session_cookie_path, 'secure' => (bool)$context->getProperty('service.useSSL', false), 'httponly' => true, 'samesite' => 'Lax'));
		}
	}

	public static function clearCookie( $key )
	{
		$context = Model_Context::getInstance();
		$session_cookie_path = "/";
		$savedPath = $context->getProperty('service.session_cookie_path');
		if( !empty($savedPath)) {
			$session_cookie_path = $context->getProperty('service.session_cookie_path');
		}
		if( !headers_sent() ) {
			setcookie( $key, '', array('expires' => time()-3600, 'path' => $session_cookie_path, 'secure' => (bool)$context->getProperty('service.useSSL', false), 'httponly' => true, 'samesite' => 'Lax'));
		}
	}

	public static function getDisplayName( $openid )
	{
		$openid = (string)$openid;
		// OIDC 신원(oidc:{iss}#{sub})은 issuer 만 표시한다(식별자 원문 노출 방지).
		if( strpos($openid, 'oidc:') === 0 ) {
			$issuer = substr($openid, 5);
			$hash = strpos($issuer, '#');
			if( $hash !== false ) { $issuer = substr($issuer, 0, $hash); }
			if( strlen($issuer) > 40 ) { $issuer = substr($issuer, 0, 36) . "..."; }
			return $issuer;
		}
		// 소셜 신원({공급자}:{id})은 공급자 이름만 표시한다 — 공급자 사용자 id 원문을 이름으로 내보내지 않는다(v3.153).
		if( self::identityKind($openid) === 'social' ) {
			$provider = substr($openid, 0, strpos($openid, ':'));
			if( function_exists('CL_SocialLogin_providers') ) {
				$providers = CL_SocialLogin_providers();
				if( isset($providers[$provider]['label']) ) {
					return $providers[$provider]['label'];
				}
			}
			return ucfirst($provider);
		}
		$s = explode( '#', $openid );
		$openid = $s[0];
		if( strlen($openid) > 40 ) {
			$openid = substr($openid,0,36) . "...";
		}
		return $openid;
	}

	/// 신원 식별자의 종류(v3.153): 'oidc'(oidc:{iss}#{sub}) / 'url'(과거 OpenID 2.0, http(s) 주소) / 'social'({공급자}:{id}) / ''(그 밖).
	/// 홈페이지 링크는 'url' 일 때만 만든다 — 'kakao:123' 같은 식별자가 href 로 들어가지 않게 한다.
	public static function identityKind( $openid )
	{
		if( !is_string($openid) || $openid === '' ) return '';
		// [보안] Textcube-NG: 형식 전체를 본다 — 제어 문자·공백·따옴표·꺾쇠·역슬래시·백틱이 들면 '', oidc 는 oidc:{iss}#{sub}, url 은 호스트가 있는 http(s) 주소,
		// social 은 {공급자}:{공백 없는 값}. 원본은 앞부분만 봤다(r3-7, 결정 468b)
		if( preg_match('/[\x00-\x20\x7f"\'<>\\\\`]/', $openid) ) return '';
		if( strpos($openid, 'oidc:') === 0 ) return preg_match('/\Aoidc:[^#]+#.+\z/s', $openid) ? 'oidc' : '';
		if( preg_match('/\Ahttps?:\/\//i', $openid) ) {
			$host = parse_url($openid, PHP_URL_HOST);
			if( !is_string($host) || $host === '' ) return '';
			// userinfo(user:pass@)가 든 주소는 '' — 링크가 보여 주는 앞부분과 실제 호스트가 달라지는 혼동을 막는다(r3-7 보충, 결정 468b userinfo)
			if( parse_url($openid, PHP_URL_USER) !== null || parse_url($openid, PHP_URL_PASS) !== null || preg_match('/\Ahttps?:\/\/[^\/?#]*@/i', $openid) ) return '';
			$check = $openid;
			if( preg_match('/[\x80-\xff]/', $host) ) {
				// 비 ASCII 호스트는 punycode 로 바꿔 검사한다 — intl 확장(idn_to_ascii)이 없거나 바꾸지 못하면 ''(결정 468b). 7.4~8.5 모두 IDNA_DEFAULT·UTS46
				if( !function_exists('idn_to_ascii') ) return '';
				$ascii = idn_to_ascii($host, IDNA_DEFAULT, INTL_IDNA_VARIANT_UTS46);
				if( !is_string($ascii) || $ascii === '' ) return '';
				$pos = strpos($openid, $host);
				$check = substr($openid, 0, $pos) . $ascii . substr($openid, $pos + strlen($host));
			}
			return (filter_var($check, FILTER_VALIDATE_URL) !== false) ? 'url' : '';
		}
		if( preg_match('/\A[a-z0-9_]+:\S+\z/', $openid) ) return 'social';
		return '';
	}

	/// 로그인·로그아웃 뒤 돌아올 주소(v3.153). GET 이면 지금 주소, 그 밖(ajax 댓글 응답 등 POST)이면 Referer.
	/// getSafeRedirectURI 로 상대 경로·같은 호스트·서비스 도메인만 받고, 아니면 블로그 첫 화면.
	public static function getReturnURI()
	{
		$context = Model_Context::getInstance();
		$fallback = $context->getProperty('uri.blog') . '/';
		if( isset($_SERVER['REQUEST_METHOD']) && $_SERVER['REQUEST_METHOD'] === 'GET' ) {
			$target = isset($_SERVER['REQUEST_URI']) ? $_SERVER['REQUEST_URI'] : '';
		} else {
			$target = isset($_SERVER['HTTP_REFERER']) ? $_SERVER['HTTP_REFERER'] : '';
		}
		return getSafeRedirectURI($target, $fallback);
	}

	/// 방문자 OpenID 로그인 화면 주소(v3.153). $return 이 없으면 getReturnURI(). 출력할 때는 호출자가 escape 한다.
	public static function getGuestLoginURI( $return = null )
	{
		$context = Model_Context::getInstance();
		if( $return === null ) {
			$return = self::getReturnURI();
		}
		return $context->getProperty('uri.blog') . '/login/openid/guest?requestURI=' . rawurlencode($return);
	}

	/// 댓글 작성자 표시에 title 을 붙인다(v3.153, CL_OpenID·CL_SocialLogin 공용). view.php 가 만든 작성자 표시
	/// (`<a …>이름</a>` 또는 홈페이지가 없을 때의 `<span …>이름</span>`) 가운데 글자가 작성자 이름과 같은 것에만 붙이고,
	/// 원래 있던 title 은 지운다. $escapedTitle 은 호출자가 htmlspecialchars(…, ENT_QUOTES) 한 값이어야 한다.
	public static function addCommenterTitle( $name, $comment, $escapedTitle )
	{
		if( !is_string($name) || !isset($comment['name']) ) {
			return $name;
		}
		preg_match_all('@<(a|span)(\s[^>]*)?>(.*)</\1>@Usi', $name, $temp, PREG_SET_ORDER);
		foreach( $temp as $m ) {
			if( strip_tags($m[3]) == $comment['name'] ) {
				$attributes = preg_replace('@\stitle\s*=\s*("[^"]*"|\'[^\']*\'|[^\s>]*)@i', '', isset($m[2]) ? $m[2] : '');
				$name = str_replace($m[0], '<' . $m[1] . $attributes . ' title="' . $escapedTitle . '">' . $m[3] . '</' . $m[1] . '>', $name);
			}
		}
		return $name;
	}

	/// 위장 구별 표지(v3.153): HMAC-SHA256(서비스 비밀값, 식별자) 앞 8자. 같은 이름이라도 계정이 다르면 표지가 다르다.
	/// 식별자 원문은 드러나지 않는다. 비밀값을 얻지 못하면 ''(표지 생략).
	public static function getIdentityBadge( $identifier )
	{
		if( !is_string($identifier) || $identifier === '' ) {
			return '';
		}
		$secret = self::getIdentityBadgeSecret();
		if( $secret === '' ) {
			return '';
		}
		return substr(hash_hmac('sha256', $identifier, $secret), 0, 8);
	}

	/// 표지 비밀값: ServiceSettings 'identityBadgeSecret'(서비스 전역). 없으면 insert 만 시도하고(덮어쓰지 않음 — 동시에 만들면
	/// 먼저 저장된 하나로 수렴) 다시 읽은 값을 쓴다. 32자 미만이면 ''.
	public static function getIdentityBadgeSecret()
	{
		static $secret = null;
		if( $secret !== null ) {
			return $secret;
		}
		if( !class_exists('DBModel') ) {
			return '';
		}
		$query = DBModel::getInstance();
		$query->reset('ServiceSettings');
		$query->setQualifier('name', 'equals', 'identityBadgeSecret', true);
		$value = $query->getCell('value');
		if( $value === null || $value === false || $value === '' ) {
			$query->reset('ServiceSettings');
			$query->setAttribute('name', 'identityBadgeSecret', true);
			$query->setAttribute('value', bin2hex(random_bytes(32)), true);
			/* Textcube-NG: 다른 요청이 먼저 만들었으면 이 INSERT 는 기본 키 중복으로 실패한다(r3-9, 468e). MySQL·MySQLi 어댑터는 경고를 내지 않지만
			   PostgreSQL(pg_query)·SQLite3·Cubrid 어댑터는 경고를 내고, 전역 Debug 오류 처리기는 @ 를 무시하므로
			   framework/data/SQLite3/Adapter.php 의 rollback()·commit() 과 같이 이 호출 동안만 오류 처리기를 바꿔 경고를 감춘다.
			   실패해도 아래에서 다시 읽은 값(먼저 저장된 하나)을 쓴다. */
			set_error_handler(function () { return true; });
			$query->insert();
			restore_error_handler();
			$query->reset('ServiceSettings');
			$query->setQualifier('name', 'equals', 'identityBadgeSecret', true);
			$value = $query->getCell('value');
		}
		$secret = (is_string($value) && strlen($value) >= 32) ? $value : '';
		return $secret;
	}
}

/// 과거 OpenID 2.0 컨슈머. 2.0 프로토콜 구현은 제거되었고, OIDC 흐름/댓글 처리와 공유하는
/// 정적 헬퍼(세션 표시정보·로그아웃·에러 출력·리다이렉트)만 유지한다. OpenID 헬퍼를 상속한다.
class OpenIDConsumer extends OpenID {

	public static function logout()
	{
		Acl::authorize('openid', null );
		OpenID::setCookie( 'openid_auto', 'n' );
		self::clearUserInfo();
	}

	public static function clearUserInfo()
	{
		unset( $_SESSION['openid'] );
	}

	public static function setUserInfo( $nickname, $homepage )
	{
		if( !isset( $_SESSION['openid'] ) || !is_array( $_SESSION['openid'] ) ) {
			$_SESSION['openid'] = array();
		}
		$_SESSION['openid']['nickname'] = $nickname;
		$_SESSION['openid']['homepage'] = $homepage;
	}

	/// 게스트 댓글 작성자의 표시정보 갱신. 세션을 갱신하고, 과거 OpenID 2.0 사용자(OpenIDUsers)
	/// 레코드가 있을 때만 그 표시정보를 동기화한다. OIDC 식별자는 OpenIDUsers 레코드가 없어 no-op.
	public static function updateUserInfo( $nickname, $homepage )
	{
		$openid = Acl::getIdentity( 'openid' );
		if( empty($openid) ) {
			return false;
		}
		self::setUserInfo( $nickname, $homepage );

		if( !class_exists('DBModel') ) {
			return true;
		}
		$pool = DBModel::getInstance();
		$pool->reset('OpenIDUsers');
		$pool->setQualifier('openid','equals',$openid,true);
		$result = $pool->getCell('openidinfo');
		if( empty($result) ) {
			return true;   // 연결된 OpenIDUsers 레코드 없음(OIDC 게스트 등) → no-op
		}
		$data = unserialize( $result );
		if( !is_array($data) ) {
			$data = array();
		}
		if( !empty($nickname) ) $data['nickname'] = $nickname;
		if( !empty($homepage) ) $data['homepage'] = $homepage;

		$pool->reset('OpenIDUsers');
		$pool->setAttribute('openidinfo',serialize($data),true);
		$pool->setQualifier('openid','equals',$openid,true);
		$pool->update();
		return true;
	}

	public static function printErrorReturn( $msg, $location )
	{
		header("HTTP/1.0 200 OK");
		header("Content-type: text/html; charset=utf-8");
		$safeMsg      = json_encode($msg);
		$safeLocation = json_encode($location);
		print "<html><head><meta http-equiv=\"Content-Type\" content=\"text/html; charset=utf-8\" /></head><body><script type=\"text/javascript\">//<![CDATA[" . CRLF . "alert($safeMsg);";
		if( $location ) {
			print "document.location.href=$safeLocation;";
		}
		print "//]]>" . CRLF . "</script></body></html>";
		exit(0);
	}

	public static function redirect( $location )
	{
		header("HTTP/1.0 302 Moved Temporarily");
		header("Location: $location");
		print( "<html><body></body></html>" );
		exit(0);
	}
}
?>
