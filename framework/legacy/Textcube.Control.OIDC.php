<?php
/// OpenID Connect (OIDC) client — replaces legacy OpenID 2.0 (EOL: providers dropped 2.0 support ~2015).
/// Dependency-free: uses only openssl + JSON + curl/streams (no composer / external bundle).
///
/// SECURITY — double opt-in (disabled by default):
///   gate 1: CL_OpenID plugin must be ACTIVE  (getCurrentSetting() is only valid while active)
///   gate 2: plugin config must set oidc_enabled='y' AND provide issuer/client_id/client_secret
/// Neither activating the plugin alone, nor leaving config blank, enables OIDC.
///
/// Stage 1: config gate. Stage 2: Authorization Code flow + RS256 id_token verification
///   (state, nonce, PKCE S256, JWKS signature, iss/aud/exp/nonce checks).
/// Stage 3 (this file): guest comment identity mapping — claims → Acl 'openid' identity,
///   reusing the legacy single source of truth so the whole guest-comment flow works unchanged.

class OIDCClient {

	static $error = null;

	// ---------- Stage 1: double opt-in gate ----------

	static function config() {
		if (!class_exists('Setting') || !method_exists('Setting', 'fetchConfigVal')) return array();
		if (!function_exists('getCurrentSetting')) return array();
		$raw = getCurrentSetting('CL_OpenID');
		if (empty($raw)) return array();
		$cfg = Setting::fetchConfigVal($raw);
		return is_array($cfg) ? $cfg : array();
	}

	static function isEnabled() {
		$cfg = self::config();
		if (!isset($cfg['oidc_enabled']) || $cfg['oidc_enabled'] !== 'y') return false;
		foreach (array('oidc_issuer', 'oidc_client_id', 'oidc_client_secret') as $k) {
			if (empty($cfg[$k])) return false;
		}
		return true;
	}

	static function setting($key, $default = null) {
		$cfg = self::config();
		return isset($cfg[$key]) && $cfg[$key] !== '' ? $cfg[$key] : $default;
	}

	static function redirectUri() {
		$r = self::setting('oidc_redirect_uri');
		if (!empty($r)) return $r;
		$ctx = Model_Context::getInstance();
		return $ctx->getProperty('uri.host') . $ctx->getProperty('uri.blog') . '/login/openid/callback';
	}

	// ---------- Stage 2: Authorization Code flow ----------

	/// GET {issuer}/.well-known/openid-configuration → endpoints array (cached per request).
	static function discover() {
		static $cache = null;
		if ($cache !== null) return $cache;
		$issuer = self::setting('oidc_issuer');
		if (empty($issuer)) return ($cache = false);
		$json = self::httpGet(rtrim($issuer, '/') . '/.well-known/openid-configuration');
		$d = json_decode((string)$json, true);
		if (!is_array($d) || empty($d['authorization_endpoint']) || empty($d['token_endpoint']) || empty($d['jwks_uri'])) {
			return ($cache = false);
		}
		// Textcube-NG: 서버가 요청할 token_endpoint·jwks_uri 는 safeTarget() 을 통과해야 하고(콜백 때 httpGet/httpPost 가 다시 검사),
		// 브라우저로만 보내는 authorization_endpoint 는 https 문자열인지만 본다(v3.157)
		if (!is_string($d['authorization_endpoint']) || strtolower((string)parse_url($d['authorization_endpoint'], PHP_URL_SCHEME)) !== 'https'
			|| !self::safeTarget($d['token_endpoint']) || !self::safeTarget($d['jwks_uri'])) {
			return ($cache = false);
		}
		return ($cache = $d);
	}

	/// Build the authorization redirect URL; stashes state/nonce/PKCE-verifier in the session.
	static function buildAuthUrl($extra = array()) {
		$d = self::discover();
		if (!$d) return self::fail('discovery');
		$state    = self::randTok();
		$nonce    = self::randTok();
		$verifier = self::b64urlEncode(random_bytes(32));
		$challenge = self::b64urlEncode(hash('sha256', $verifier, true));
		$_SESSION['oidc_state']    = $state;
		$_SESSION['oidc_nonce']    = $nonce;
		$_SESSION['oidc_verifier'] = $verifier;
		$params = array(
			'response_type'         => 'code',
			'client_id'             => self::setting('oidc_client_id'),
			'redirect_uri'          => self::redirectUri(),
			'scope'                 => 'openid email profile',
			'state'                 => $state,
			'nonce'                 => $nonce,
			'code_challenge'        => $challenge,
			'code_challenge_method' => 'S256',
		) + $extra;
		return $d['authorization_endpoint'] . '?' . http_build_query($params);
	}

	/// Exchange code → tokens → verify id_token. Returns claims [sub,email,name] or false (self::$error set).
	static function handleCallback($code, $state) {
		if (empty($code)) return self::fail('no_code');
		if (empty($_SESSION['oidc_state']) || !hash_equals((string)$_SESSION['oidc_state'], (string)$state)) {
			return self::fail('state_mismatch');
		}
		$d = self::discover();
		if (!$d) return self::fail('discovery');
		$resp = self::httpPost($d['token_endpoint'], array(
			'grant_type'    => 'authorization_code',
			'code'          => $code,
			'redirect_uri'  => self::redirectUri(),
			'client_id'     => self::setting('oidc_client_id'),
			'client_secret' => self::setting('oidc_client_secret'),
			'code_verifier' => isset($_SESSION['oidc_verifier']) ? $_SESSION['oidc_verifier'] : '',
		));
		$tok = json_decode((string)$resp, true);
		if (!is_array($tok) || empty($tok['id_token'])) return self::fail('token_exchange');
		return self::verifyIdToken($tok['id_token'], $d, isset($_SESSION['oidc_nonce']) ? $_SESSION['oidc_nonce'] : null);
	}

	/// 공급자 사용자 식별자(OIDC sub·소셜 id) 형식(v3.153): 문자열(정수는 문자열로 봄), 출력 가능한 ASCII(0x20-0x7E) 1~255자.
	/// 식별자가 세션·DB·화면으로 그대로 가므로 제어 문자·비ASCII·배열 등은 받지 않는다.
	static function isValidSubject($sub) {
		if (is_int($sub)) $sub = (string)$sub;
		return is_string($sub) && preg_match('/\A[\x20-\x7E]{1,255}\z/', $sub) === 1;
	}

	/// Verify an id_token JWT (RS256 only). $expectedNonce: session nonce. Returns claims or false.
	static function verifyIdToken($jwt, $disco, $expectedNonce) {
		$parts = explode('.', (string)$jwt);
		if (count($parts) !== 3) return self::fail('jwt_format');
		$header  = json_decode(self::b64urlDecode($parts[0]), true);
		$payload = json_decode(self::b64urlDecode($parts[1]), true);
		$sig     = self::b64urlDecode($parts[2]);
		if (!is_array($header) || !is_array($payload)) return self::fail('jwt_decode');
		if (!isset($header['alg']) || $header['alg'] !== 'RS256') return self::fail('alg');   // RS256 only — reject 'none'/HS256

		$jwks = json_decode((string)self::httpGet($disco['jwks_uri']), true);
		$pem = self::jwkToPem($jwks, isset($header['kid']) ? $header['kid'] : null);
		if (!$pem) return self::fail('no_jwk');

		$ok = openssl_verify($parts[0] . '.' . $parts[1], $sig, $pem, OPENSSL_ALGO_SHA256);
		if ($ok !== 1) return self::fail('bad_signature');

		if (!isset($payload['iss']) || $payload['iss'] !== self::setting('oidc_issuer')) return self::fail('iss');
		$aud = isset($payload['aud']) ? $payload['aud'] : '';
		$aud = is_array($aud) ? $aud : array($aud);
		if (!in_array(self::setting('oidc_client_id'), $aud, true)) return self::fail('aud');
		if (!isset($payload['exp']) || (int)$payload['exp'] < time()) return self::fail('expired');
		if ($expectedNonce !== null) {
			if (!isset($payload['nonce']) || !hash_equals((string)$expectedNonce, (string)$payload['nonce'])) return self::fail('nonce');
		}

		// [보안] sub 형식 검증(v3.153) — sub 는 식별자(oidc:{iss}#{sub})로 세션·DB·화면에 그대로 간다.
		if (!isset($payload['sub']) || !self::isValidSubject($payload['sub'])) return self::fail('no_subject');

		return array(
			'sub'   => (string)$payload['sub'],
			'email' => isset($payload['email']) ? $payload['email'] : '',
			'name'  => isset($payload['name']) ? $payload['name'] : (isset($payload['preferred_username']) ? $payload['preferred_username'] : ''),
		);
	}

	// ---------- Stage 3: guest comment identity ----------

	/// OIDC claims → 게스트 댓글 작성자 신원. legacy OpenIDConsumer::setAcl() 의 게스트 경로를 OIDC로 적응.
	/// Acl 'openid' identity 하나가 blog.comment(저장/조회/삭제)·view·comment/add 의 단일 출처이므로,
	/// 식별자만 주입하면 기존 게스트 댓글 흐름 전체가 그대로 동작한다(다운스트림 무수정).
	/// 식별자 = OIDC subject 'oidc:{iss}#{sub}' (provider별 불투명·안정·고유). 표시명은 name/email.
	/// 사용자 계정 매핑(관리자 로그인)은 단계 4에서 이 메서드를 확장한다.
	static function establishGuestIdentity($claims) {
		if (!is_array($claims) || !isset($claims['sub']) || !self::isValidSubject($claims['sub'])) return self::fail('no_subject');
		$identifier = self::subjectId($claims);
		// 이름이 없으면 이메일·식별자 대신 공급자 이름으로 표시한다(establishGuestIdentityWith, v3.153) — 공개 댓글 이름에 이메일이 나가지 않게.
		$name = isset($claims['name']) ? $claims['name'] : '';
		return self::establishGuestIdentityWith($identifier, $name);
	}

	/// 임의 식별자/표시명으로 게스트 신원 설정 — 소셜 로그인 등 OIDC 외 provider 가 동일 인프라를 재사용한다.
	/// 식별자는 호출자가 보증(provider 검증된 안정 ID). Acl 'openid' 단일 슬롯이라 댓글/연결 흐름이 그대로 동작.
	static function establishGuestIdentityWith($identifier, $name) {
		if (empty($identifier)) return self::fail('no_subject');
		Acl::authorize('openid', $identifier);
		if (!is_string($name) || $name === '') $name = OpenID::getDisplayName($identifier);
		// 표시명을 세션에 직접 기록(legacy setUserInfo 와 동일 구조). OpenIDConsumer 의 인스턴스 메서드를
		// 정적 호출하지 않는다 — PHP 8 에서 non-static 메서드의 정적 호출은 치명적 오류이기 때문.
		if (!isset($_SESSION['openid']) || !is_array($_SESSION['openid'])) $_SESSION['openid'] = array();
		$_SESSION['openid']['nickname'] = $name;
		$_SESSION['openid']['homepage'] = '';
		// 인증 시각 — 계정 연결(owner/setting/account/openid)이 재인증 표지 뒤에 새로 인증한 신원인지 확인한다(v3.153).
		$_SESSION['openid']['authenticated'] = Timestamp::getUNIXtime();
		if (class_exists('Session') && method_exists('Session', 'authorize') && defined('SESSION_OPENID_USERID')) {
			$ctx = Model_Context::getInstance();
			if (!Session::authorize(intval($ctx->getProperty('blog.id')), SESSION_OPENID_USERID)) {
				// [보안] 새 세션 ID 를 받지 못하면 신원을 되돌린다 — 그대로 두면 로그인 전 세션(같은 IP 가 함께 쓰는 익명 세션일 수
				// 있음)에 신원이 저장된다(v3.153).
				Acl::authorize('openid', null);
				unset($_SESSION['openid']);
				return self::fail('session');
			}
		}
		return $identifier;
	}

	/// 안정·고유 게스트 식별자. legacy openid URL 과 구분되도록 'oidc:' 접두(ViewCommenter 가 링크 생략에 사용).
	static function subjectId($claims) {
		return 'oidc:' . self::setting('oidc_issuer') . '#' . $claims['sub'];
	}

	// ---------- Stage 4: user/admin login mapping ----------

	/// 게스트 신원 설정 + 연결된 사용자 계정으로의 승격. legacy OpenIDConsumer::setAcl() 의 사용자 경로를 OIDC로 적응.
	/// 사용자가 owner/setting/account 에서 자신의 계정에 OIDC 식별자를 명시적으로 연결(UserSettings openid.*)한 경우에만
	/// 해당 계정으로 로그인된다. 자동 계정생성은 하지 않는다(계정탈취 방지).
	/// 식별자는 sub 기반(iss#sub)이라 email 공유/변경·provider 사칭으로는 매핑을 가로챌 수 없다.
	static function establishIdentity($claims) {
		if (!is_array($claims) || !isset($claims['sub']) || !self::isValidSubject($claims['sub'])) return self::fail('no_subject');
		$identifier = self::subjectId($claims);
		// 이름이 없으면 이메일·식별자 대신 공급자 이름으로 표시한다(establishGuestIdentityWith, v3.153) — 공개 댓글 이름에 이메일이 나가지 않게.
		$name = isset($claims['name']) ? $claims['name'] : '';
		return self::establishIdentityWith($identifier, $name);
	}

	/// 임의 식별자/표시명으로 게스트 신원 + 연결계정 승격 — 소셜 로그인 등 OIDC 외 provider 가 재사용.
	/// 연결(UserSettings openid.* 정확 일치)된 계정이 있고 writers 권한일 때만 사용자 세션 승격. 자동 계정생성 없음.
	static function establishIdentityWith($identifier, $name) {
		$id = self::establishGuestIdentityWith($identifier, $name);   // Acl 'openid' + 표시명 + 게스트 세션
		if ($id === false) return false;
		$userid = self::mapToUser($identifier);
		if ($userid === null) return $identifier;              // 연결된 계정 없음 → 게스트 유지
		Acl::authorize('textcube', $userid);
		$ctx = Model_Context::getInstance();
		$blogid = intval($ctx->getProperty('blog.id'));
		// writers 권한이 있는 계정에 한해 실제 사용자 세션으로 승격(legacy setAcl 과 동일 판정).
		if (method_exists('Acl', 'getCurrentPrivilege') && in_array('group.writers', Acl::getCurrentPrivilege())) {
			if (!Session::authorize($blogid, $userid)) {
				// [보안] 사용자 세션을 만들지 못하면 신원·권한을 모두 되돌린다(v3.153).
				Acl::clearAcl();
				unset($_SESSION['userid'], $_SESSION['openid']);
				return self::fail('session');
			}
		}
		return $identifier;
	}

	/// UserSettings 의 openid.* 항목 중 값이 식별자와 정확히 일치하는 사용자 id(없으면 null).
	/// escape=true 를 명시해 식별자(iss·sub)에 의한 SQL 인젝션을 방어한다.
	static function mapToUser($identifier) {
		if (!class_exists('DBModel')) return null;
		$pool = DBModel::getInstance();
		$pool->reset('UserSettings');
		$pool->setQualifier('name', 'like', 'openid.', true);
		$pool->setQualifier('value', 'equals', $identifier, true);
		$pool->setOrder('userid', 'ASC');
		$result = $pool->getCell('userid');
		return !empty($result) ? $result : null;
	}

	// ---------- helpers ----------

	/// RSA JWK (n,e) → PEM SubjectPublicKeyInfo via minimal ASN.1 DER. Picks key by kid (or first RSA key).
	static function jwkToPem($jwks, $kid) {
		if (!is_array($jwks) || empty($jwks['keys'])) return null;
		$jwk = null;
		foreach ($jwks['keys'] as $k) {
			if (!isset($k['kty']) || $k['kty'] !== 'RSA') continue;
			if ($kid !== null && isset($k['kid']) && $k['kid'] !== $kid) continue;
			$jwk = $k;
			if ($kid === null || (isset($k['kid']) && $k['kid'] === $kid)) break;
		}
		if (!$jwk || empty($jwk['n']) || empty($jwk['e'])) return null;
		$n = self::b64urlDecode($jwk['n']);
		$e = self::b64urlDecode($jwk['e']);
		$rsaPubKey = self::asn1Seq(self::asn1Int($n) . self::asn1Int($e));
		$bitString = "\x03" . self::asn1Len(strlen($rsaPubKey) + 1) . "\x00" . $rsaPubKey;
		$algId = "\x30\x0d\x06\x09\x2a\x86\x48\x86\xf7\x0d\x01\x01\x01\x05\x00"; // rsaEncryption OID + NULL
		$spki = self::asn1Seq($algId . $bitString);
		return "-----BEGIN PUBLIC KEY-----\n" . chunk_split(base64_encode($spki), 64, "\n") . "-----END PUBLIC KEY-----\n";
	}

	static function asn1Len($len) {
		if ($len < 0x80) return chr($len);
		$bytes = '';
		while ($len > 0) { $bytes = chr($len & 0xff) . $bytes; $len >>= 8; }
		return chr(0x80 | strlen($bytes)) . $bytes;
	}
	static function asn1Int($bytes) {
		if (strlen($bytes) === 0) $bytes = "\x00";
		if (ord($bytes[0]) & 0x80) $bytes = "\x00" . $bytes; // force positive
		return "\x02" . self::asn1Len(strlen($bytes)) . $bytes;
	}
	static function asn1Seq($content) {
		return "\x30" . self::asn1Len(strlen($content)) . $content;
	}

	static function b64urlDecode($s) {
		$s = strtr((string)$s, '-_', '+/');
		$pad = strlen($s) % 4;
		if ($pad) $s .= str_repeat('=', 4 - $pad);
		return base64_decode($s);
	}
	static function b64urlEncode($s) {
		return rtrim(strtr(base64_encode($s), '+/', '-_'), '=');
	}
	static function randTok() {
		return rtrim(strtr(base64_encode(random_bytes(16)), '+/', '-_'), '=');
	}

	// Textcube-NG: issuer 는 블로그 관리자가 정하고(CL_OpenID 블로그별 설정) token_endpoint·jwks_uri 는 그 서버의 discovery 문서가 정한다.
	// 이 주소들을 검사 없이 curl(file://·gopher:// 등 기본 프로토콜, 환경 변수 프록시)·file_get_contents(리다이렉트를 따라감, phar:// 등 래퍼)로
	// 열어 비로그인 방문자의 /login/openid 로 서버 안쪽 주소에 요청할 수 있었다. https·443·공인 IPv4 만 받고, 확인한 IP 로 고정해 연결하며
	// 리다이렉트는 따르지 않는다. 통과하면 array(host, ip, path, url), 아니면 false(v3.157)
	static function safeTarget($url) {
		$u = (is_string($url) && !preg_match('/[\x00-\x20\x7f]/', $url)) ? @parse_url($url) : false;	// Textcube-NG: 제어 문자·공백은 parse_url 전에 거부(parse_url 이 '_' 로 바꿔 아래 경로 검사를 지나감)(v3.157 보완)
		if (!is_array($u) || !isset($u['scheme'], $u['host']) || strtolower($u['scheme']) !== 'https') return false;
		if (isset($u['user']) || isset($u['pass'])) return false;
		if (isset($u['port']) && (int)$u['port'] !== 443) return false;
		$host = strtolower($u['host']);
		if (!preg_match('/^[a-z0-9-]+(\.[a-z0-9-]+)*\z/', $host) || strlen($host) > 253) return false;	// 끝 점·빈 조각·[IPv6] 거부
		$path = (isset($u['path']) && $u['path'] !== '') ? $u['path'] : '/';
		if (isset($u['query'])) $path .= '?' . $u['query'];
		if (preg_match('/[\x00-\x20\x7f]/', $path)) return false;
		if (($ip = HTTPRequest::getPublicAddress($host)) === false) return false;
		return array('host' => $host, 'ip' => $ip, 'path' => $path, 'url' => 'https://' . $host . $path);
	}

	// Textcube-NG: config.php 의 $service['useLoginProxy'] 가 true(불리언)일 때만 curl 이 환경 변수 프록시(https_proxy 등)를 쓴다(기본 false).
	// 켜도 safeTarget() 주소 검사는 그대로이고, 프록시를 거치면 IP 고정은 프록시가 맡는다(v3.157 보완)
	static function useProxy() {
		global $service;
		return isset($service['useLoginProxy']) && $service['useLoginProxy'] === true;
	}

	// Textcube-NG: https 만, 리다이렉트 안 따름, 확인한 IP 로만 연결, 환경 변수 프록시(https_proxy 등) 무시(v3.157) — useProxy() 면 프록시 사용(v3.157 보완)
	static function curlOptions($t) {
		$o = array(
			CURLOPT_RETURNTRANSFER => true, CURLOPT_TIMEOUT => 10,
			CURLOPT_SSL_VERIFYPEER => true, CURLOPT_SSL_VERIFYHOST => 2,
			CURLOPT_FOLLOWLOCATION => false,
			CURLOPT_PROTOCOLS => CURLPROTO_HTTPS, CURLOPT_REDIR_PROTOCOLS => CURLPROTO_HTTPS,
			CURLOPT_RESOLVE => array($t['host'] . ':443:' . $t['ip']),
		);
		$o += getTlsCurlOptions();	// Textcube-NG: config 의 tls_cafile/tls_capath 를 curl 에도(r4-4)
		if (!self::useProxy()) $o[CURLOPT_PROXY] = '';
		return $o;
	}

	// Textcube-NG: curl 이 없을 때 — 확인한 IP 주소로 열고 Host 헤더·SNI·인증서 이름 확인은 원래 호스트로, 리다이렉트 안 따름, 10초(v3.157)
	static function streamRequest($t, $method, $header, $body) {
		$http = array('method' => $method, 'header' => "Host: {$t['host']}\r\n" . $header,
			'timeout' => 10, 'follow_location' => 0);
		if ($body !== null) $http['content'] = $body;
		$ctx = stream_context_create(array('http' => $http,
			'ssl' => getTlsStreamOptions($t['host'])));
		return @file_get_contents('https://' . $t['ip'] . $t['path'], false, $ctx);
	}

	static function httpGet($url) {
		if (!($t = self::safeTarget($url))) return false;	// Textcube-NG: 주소 검사(v3.157)
		if (function_exists('curl_init')) {
			$ch = curl_init($t['url']);
			curl_setopt_array($ch, self::curlOptions($t));
			$r = curl_exec($ch); $ip = curl_getinfo($ch, CURLINFO_PRIMARY_IP); curl_close($ch);
			return ($ip === $t['ip'] || self::useProxy()) ? $r : false;	// Textcube-NG: 확인한 IP 가 아닌 곳의 응답은 쓰지 않음(v3.157) — 프록시를 쓰면 연결 주소가 프록시라 보지 않음(v3.157 보완)
		}
		return self::streamRequest($t, 'GET', '', null);
	}
	static function httpPost($url, $params) {
		$body = http_build_query($params);
		if (!($t = self::safeTarget($url))) return false;	// Textcube-NG: 주소 검사(v3.157)
		if (function_exists('curl_init')) {
			$ch = curl_init($t['url']);
			curl_setopt_array($ch, self::curlOptions($t) + array(
				CURLOPT_POST => true, CURLOPT_POSTFIELDS => $body,
				CURLOPT_HTTPHEADER => array('Accept: application/json', 'Content-Type: application/x-www-form-urlencoded'),
			));
			$r = curl_exec($ch); $ip = curl_getinfo($ch, CURLINFO_PRIMARY_IP); curl_close($ch);
			return ($ip === $t['ip'] || self::useProxy()) ? $r : false;	// Textcube-NG: 확인한 IP 가 아닌 곳의 응답은 쓰지 않음(v3.157) — 프록시를 쓰면 연결 주소가 프록시라 보지 않음(v3.157 보완)
		}
		return self::streamRequest($t, 'POST', "Content-Type: application/x-www-form-urlencoded\r\nAccept: application/json\r\n", $body);
	}

	static function fail($code) { self::$error = $code; return false; }
}
?>
