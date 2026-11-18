<?php
/// Copyright (c) 2004-2016, Needlworks  / Tatter Network Foundation
/// All rights reserved. Licensed under the GPL.
/// See the GNU General Public License for more details. (/documents/LICENSE, /documents/COPYRIGHT)
///
/// CL_SocialLogin OAuth 코어 — registry 기반 OAuth2/OIDC 클라이언트.
/// OIDCClient 의 순수 helper(httpGet/httpPost/b64url/jwkToPem)를 재사용하고, 세션 기반 state/nonce/PKCE 를 쓴다.
/// 결과를 정규화: {provider, id, email, name, identifier='{provider}:{표지}:{id}'}(표지 = client_id 의 SHA-256 앞 12자, r3-9).
///
/// 보안: state(CSRF) 일치, OIDC(kakao)는 id_token RS256 + iss/aud/exp/nonce 검증, PKCE(S256).
/// OAuth2(naver/facebook)는 confidential client(client_secret 서버 교환) + state. email 은 받아 정규화만 하고 계정 연결·표시에 쓰지 않으며(r3-15),
/// 매칭 키는 provider 사용자 id 만 사용(위조 불가·안정).

if (!defined('ROOT')) {
	header('HTTP/1.1 403 Forbidden');
	header("Connection: close");
	exit;
}
require_once ROOT . '/plugins/CL_SocialLogin/index.php';
require_once ROOT . '/framework/legacy/Textcube.Control.OIDC.php';   // 순수 helper 재사용

class CL_SocialLogin_OAuth {

	static $error = null;

	static function fail($code) { self::$error = $code; return false; }

	static function provider($key) {
		// Textcube-NG: 주소(authorize·token·userinfo)를 쓰는 경로라 설정을 넘겨 페이스북 Graph API 버전을 설정 값으로 정한다(r3-14)
		$p = CL_SocialLogin_providers(CL_SocialLogin_config());
		return isset($p[$key]) ? $p[$key] : null;
	}

	/// 인증 시작 URL 생성. state/nonce/verifier + 컨텍스트를 세션에 보관(콜백에서 검증).
	static function buildAuthUrl($providerKey, $returnURL) {
		$p = self::provider($providerKey);
		if (!$p) return self::fail('unknown_provider');
		$cfg = CL_SocialLogin_config();
		if (!CL_SocialLogin_isEnabled($providerKey, $cfg)) return self::fail('not_enabled');

		$state     = OIDCClient::randTok();
		$nonce     = OIDCClient::randTok();
		$verifier  = OIDCClient::b64urlEncode(random_bytes(32));
		$challenge = OIDCClient::b64urlEncode(hash('sha256', $verifier, true));
		$_SESSION['cl_social'] = array(
			'provider' => $providerKey,
			'state'    => $state,
			'nonce'    => $nonce,
			'verifier' => $verifier,
			'return'   => (string)$returnURL,
		);
		$params = array(
			'response_type' => 'code',
			'client_id'     => $cfg[$providerKey . '_client_id'],
			'redirect_uri'  => CL_SocialLogin_redirectUri(),
			'state'         => $state,
		);
		if (!empty($p['scope'])) $params['scope'] = $p['scope'];
		if ($p['type'] === 'oidc') {
			$params['nonce']                 = $nonce;
			$params['code_challenge']        = $challenge;
			$params['code_challenge_method'] = 'S256';
		}
		return $p['authorize_url'] . '?' . http_build_query($params);
	}

	/// code → token → 신원. state 일치 + (oidc) id_token 검증 / (oauth2) userinfo. 반환 정규화 또는 false.
	static function handleCallback($code, $state) {
		if (empty($code)) return self::fail('no_code');
		$s = isset($_SESSION['cl_social']) && is_array($_SESSION['cl_social']) ? $_SESSION['cl_social'] : null;
		if (!$s || empty($s['state']) || !hash_equals((string)$s['state'], (string)$state)) {
			return self::fail('state_mismatch');
		}
		$providerKey = $s['provider'];
		$p = self::provider($providerKey);
		if (!$p) return self::fail('unknown_provider');
		$cfg = CL_SocialLogin_config();
		if (!CL_SocialLogin_isEnabled($providerKey, $cfg)) return self::fail('not_enabled');

		$tokenParams = array(
			'grant_type'    => 'authorization_code',
			'code'          => $code,
			'redirect_uri'  => CL_SocialLogin_redirectUri(),
			'client_id'     => $cfg[$providerKey . '_client_id'],
			'client_secret' => $cfg[$providerKey . '_client_secret'],
		);
		if ($p['type'] === 'oidc' && !empty($s['verifier'])) {
			$tokenParams['code_verifier'] = $s['verifier'];
		}
		if ($p['type'] === 'oauth2') {
			$tokenParams['state'] = $state;   // Naver 는 token 교환에 state 를 요구(타 provider 는 무시).
		}
		$resp = OIDCClient::httpPost($p['token_url'], $tokenParams);
		$tok = json_decode((string)$resp, true);
		if (!is_array($tok)) return self::fail('token_decode');

		// OIDC: id_token(RS256) 검증 — Kakao.
		if ($p['type'] === 'oidc' && !empty($tok['id_token'])) {
			$claims = self::verifyIdToken($tok['id_token'], $p, $cfg[$providerKey . '_client_id'], isset($s['nonce']) ? $s['nonce'] : null);
			if ($claims === false) return false;
			return self::normalize($providerKey, array('id' => $claims['sub'], 'email' => ($claims['email'] ?? ''), 'name' => ($claims['name'] ?? '')), $cfg[$providerKey . '_client_id']);
		}

		// OAuth2: access_token → userinfo.
		if (empty($tok['access_token'])) return self::fail('no_access_token');
		$raw = self::httpGetBearer($p['userinfo_url'], $tok['access_token']);
		$userinfo = json_decode((string)$raw, true);
		if (!is_array($userinfo)) return self::fail('userinfo_decode');
		return self::normalize($providerKey, self::mapUser($providerKey, $userinfo), $cfg[$providerKey . '_client_id']);
	}

	/// provider userinfo 응답 → {id,email,name} 정규화(프로바이더별 구조 흡수).
	static function mapUser($providerKey, $resp) {
		switch ($providerKey) {
			case 'naver':   // { response: { id, email, name, nickname } }
				$r = (isset($resp['response']) && is_array($resp['response'])) ? $resp['response'] : array();
				return array(
					'id'    => isset($r['id'])    ? $r['id']    : '',
					'email' => isset($r['email']) ? $r['email'] : '',
					'name'  => isset($r['name'])  ? $r['name']  : (isset($r['nickname']) ? $r['nickname'] : ''),
				);
			case 'facebook': // { id, name, email }
				return array(
					'id'    => isset($resp['id'])    ? $resp['id']    : '',
					'email' => isset($resp['email']) ? $resp['email'] : '',
					'name'  => isset($resp['name'])  ? $resp['name']  : '',
				);
			case 'kakao':   // { id, kakao_account: { email, profile: { nickname } } } (userinfo 경로 사용 시)
				$acc  = (isset($resp['kakao_account']) && is_array($resp['kakao_account'])) ? $resp['kakao_account'] : array();
				$prof = (isset($acc['profile']) && is_array($acc['profile'])) ? $acc['profile'] : array();
				return array(
					'id'    => isset($resp['id'])      ? $resp['id']      : '',
					'email' => isset($acc['email'])    ? $acc['email']    : '',
					'name'  => isset($prof['nickname']) ? $prof['nickname'] : '',
				);
			default:
				return array(
					'id'    => isset($resp['id'])    ? $resp['id']    : '',
					'email' => isset($resp['email']) ? $resp['email'] : '',
					'name'  => isset($resp['name'])  ? $resp['name']  : '',
				);
		}
	}

	/// Textcube-NG: 식별자의 앱 구분 표지(r3-9, 468d) — client_id 의 SHA-256 앞 12자(16진수). 공급자 사용자 id 는 앱(client_id)마다 따로 매겨져
	/// '{provider}:{id}' 로는 다른 블로그(다른 앱)의 같은 id 와 구별되지 않았다(UserSettings openid.* 계정 연결은 사용자 전역).
	/// 옛 식별자는 interface/blog/checkup.php 가 옮긴다(같은 계산).
	static function clientTag($clientId) {
		return substr(hash('sha256', (string)$clientId), 0, 12);
	}

	static function normalize($providerKey, $u, $clientId) {
		// [보안] 공급자 사용자 id 형식 검증(v3.153) — 식별자({provider}:{표지}:{id})로 세션·DB·화면에 그대로 간다(OIDC sub 와 같은 기준).
		if (!is_array($u) || !isset($u['id']) || !OIDCClient::isValidSubject($u['id'])) return self::fail('no_user_id');
		$id = (string)$u['id'];
		return array(
			'provider'   => $providerKey,
			'id'         => $id,
			'email'      => (isset($u['email']) && is_string($u['email'])) ? $u['email'] : '',
			'name'       => (isset($u['name']) && is_string($u['name'])) ? $u['name'] : '',
			'identifier' => $providerKey . ':' . self::clientTag($clientId) . ':' . $id,
		);
	}

	/// 검증된 신원으로 게스트 댓글 신원 + (연결된 계정이면) 사용자 로그인 설정.
	/// OIDCClient 의 신원 인프라(Acl 'openid' · UserSettings openid.* 매핑 · Session)를 그대로 재사용한다.
	/// 식별자는 '{provider}:{표지}:{id}'(provider·앱 보증·안정, normalize()). 연결된 계정이 없으면 게스트로 남는다(자동생성 없음).
	static function establishIdentity($identity) {
		if (!is_array($identity) || empty($identity['identifier'])) return self::fail('no_identity');
		// 이름이 없으면 이메일·식별자 대신 공급자 이름으로 표시한다(OIDCClient::establishGuestIdentityWith, v3.153).
		$name = isset($identity['name']) ? $identity['name'] : '';
		return OIDCClient::establishIdentityWith($identity['identifier'], $name);
	}

	/// OIDC id_token(RS256) 검증 — provider issuer 의 JWKS 로 서명·iss·aud·exp·nonce 확인. RS256 외(none/HS256) 거부.
	static function verifyIdToken($jwt, $p, $clientId, $expectedNonce) {
		$parts = explode('.', (string)$jwt);
		if (count($parts) !== 3) return self::fail('jwt_format');
		$header  = json_decode(OIDCClient::b64urlDecode($parts[0]), true);
		$payload = json_decode(OIDCClient::b64urlDecode($parts[1]), true);
		$sig     = OIDCClient::b64urlDecode($parts[2]);
		if (!is_array($header) || !is_array($payload)) return self::fail('jwt_decode');
		if (!isset($header['alg']) || $header['alg'] !== 'RS256') return self::fail('alg');

		$disco = json_decode((string)OIDCClient::httpGet(rtrim($p['issuer'], '/') . '/.well-known/openid-configuration'), true);
		if (!is_array($disco) || empty($disco['jwks_uri'])) return self::fail('discovery');
		$jwks = json_decode((string)OIDCClient::httpGet($disco['jwks_uri']), true);
		$pem = OIDCClient::jwkToPem($jwks, isset($header['kid']) ? $header['kid'] : null);
		if (!$pem) return self::fail('no_jwk');
		if (openssl_verify($parts[0] . '.' . $parts[1], $sig, $pem, OPENSSL_ALGO_SHA256) !== 1) return self::fail('bad_signature');

		if (!isset($payload['iss']) || rtrim((string)$payload['iss'], '/') !== rtrim($p['issuer'], '/')) return self::fail('iss');
		$aud = isset($payload['aud']) ? (is_array($payload['aud']) ? $payload['aud'] : array($payload['aud'])) : array();
		if (!in_array($clientId, $aud, true)) return self::fail('aud');
		if (!isset($payload['exp']) || (int)$payload['exp'] < time()) return self::fail('expired');
		if ($expectedNonce !== null && (!isset($payload['nonce']) || !hash_equals((string)$expectedNonce, (string)$payload['nonce']))) return self::fail('nonce');

		return array(
			'sub'   => isset($payload['sub'])   ? $payload['sub']   : '',
			'email' => isset($payload['email']) ? $payload['email'] : '',
			'name'  => isset($payload['name'])  ? $payload['name']  : (isset($payload['nickname']) ? $payload['nickname'] : ''),
		);
	}

	/// userinfo GET (Authorization: Bearer). OIDCClient::httpGet 은 헤더 미지원이라 별도 구현.
	/* [보안] Textcube-NG: OIDCClient::httpGet·httpPost 와 같은 요청 경로로 모은다(결정 477) — https·443·공인 IPv4 만(safeTarget), 확인한 IP 로만 연결,
	   리다이렉트·환경 변수 프록시 안 씀(curlOptions·useProxy), curl 이 없으면 streamRequest. 원본은 주소 검사 없이 열었고 curl 이 없으면 리다이렉트를 따라갔다.
	   토큰에 줄바꿈이 있으면 보내지 않는다(헤더 주입)(r3-7) */
	static function httpGetBearer($url, $accessToken) {
		if (!class_exists('OIDCClient') || !is_string($accessToken) || $accessToken === '' || preg_match('/[\r\n]/', $accessToken))
			return false;
		if (!($t = OIDCClient::safeTarget($url)))
			return false;
		if (function_exists('curl_init')) {
			$ch = curl_init($t['url']);
			curl_setopt_array($ch, OIDCClient::curlOptions($t) + array(
				CURLOPT_HTTPHEADER     => array('Authorization: Bearer ' . $accessToken, 'Accept: application/json'),
			));
			$r = curl_exec($ch); $ip = curl_getinfo($ch, CURLINFO_PRIMARY_IP);
			return ($ip === $t['ip'] || OIDCClient::useProxy()) ? $r : false;
		}
		return OIDCClient::streamRequest($t, 'GET', "Authorization: Bearer " . $accessToken . "\r\nAccept: application/json\r\n", null);
	}
}
?>
