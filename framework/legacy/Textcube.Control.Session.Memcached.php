<?php
/// Copyright (c) 2004-2016, Needlworks  / Tatter Network Foundation
/// All rights reserved. Licensed under the GPL.
/// See the GNU General Public License for more details. (/documents/LICENSE, /documents/COPYRIGHT)
///
/// ---- Modification Notice (GPL §2(a)) ----
/// Modified 2026 by @deokio for PHP 8.5 compatibility,
/// performed with AI assistance (Anthropic Claude) under human review.
/// Changes consist primarily of mechanical PHP migration transformations
/// per the official PHP upgrade documentation.
/// No additional copyright is asserted over these modifications.
/// See CHANGELOG.md and SECURITY.md for full modification history.

define( 'SESSION_OPENID_USERID', -1 );

final class Session {
	private static $sessionName = null;
	private static $mc = null;
	private static $context;
	private static $pool;
	private static $issuedId = null;	// [보안] 이 요청에서 authorize() 가 발급한 세션 ID
	private static function initialize() {
		global $memcache;        /** After PHP 5.0.5, session write performs after object destruction. */
		self::$mc = $memcache;   /** To Avoid this, just copy memcache handle into Session object.     */
		self::$context = Model_Context::getInstance();
		self::$pool = DBModel::getInstance();
	}

	public static function open($savePath, $sessionName) {
		return true;
	}

	public static function close() {
		return true;
	}

	public static function getName() {
		if(is_null(self::$mc)) self::initialize();
		if( self::$sessionName == null ) {
			if( !is_null(self::$context->getProperty('service.session_cookie'))) {
				self::$sessionName = self::$context->getProperty('service.session_cookie');
			} else {
				self::$sessionName = 'TSSESSION'.self::$context->getProperty('service.domain').self::$context->getProperty('service.path');
				self::$sessionName = preg_replace( '/[^a-zA-Z0-9]/', '', self::$sessionName );
			}
		}
		return self::$sessionName;
	}

	public static function read($id) {
		if(is_null(self::$mc)) self::initialize();
		//return self::$mc->get(self::$context->getProperty('service.domain')."/sessions/{$id}/{$_SERVER['REMOTE_ADDR']}");
		// Textcube-NG: 세션을 만든 주소(authorizedAddress·anonymousSession 키)와 다른 주소의 요청에는 세션 값을 주지 않는다(r3-24, R-449, DB 세션 read() 의 address 조건과 같은 역할)
		if (!self::addressMatches($id)) return '';
		$data = self::$mc->get(self::$context->getProperty('service.domain')."/sessions/{$id}");
		return is_string($data) ? $data : '';
	}

	public static function write($id, $data) {
		if(is_null(self::$mc)) self::initialize();
		$id = POD::escapeString($id);
		// Textcube-NG: 주소가 다르면 저장하지 않는다 — DB 세션 write() 의 address 조건(0행 갱신 = 저장 안 함, true 반환)과 같다(r3-24, R-449)
		if (!self::addressMatches($id)) return true;
		//return self::$mc->set(self::$context->getProperty('service.domain')."/sessions/{$id}/{$_SERVER['REMOTE_ADDR']}",$data,0,self::$context->getProperty('service.timeout'));
		return self::$mc->set(self::$context->getProperty('service.domain')."/sessions/{$id}",$data,0,self::$context->getProperty('service.timeout'));
	}

	public static function destroy($id, $setCookie = false) {
		//self::$mc->delete(self::$context->getProperty('service.domain')."/sessions/{$id}/{$_SERVER['REMOTE_ADDR']}");
		self::$mc->delete(self::$context->getProperty('service.domain')."/sessions/{$id}");
		self::$mc->delete(self::$context->getProperty('service.domain')."/anonymousSession/{$id}");	// Textcube-NG: 익명 세션 표지 키는 세션 ID(r3-7, 결정 468c)
		self::$mc->delete(self::$context->getProperty('service.domain')."/authorizedSession/{$id}");
		self::$mc->delete(self::$context->getProperty('service.domain')."/authorizedAddress/{$id}");
		return true;
	}

	public static function gc($maxLifeTime = false) {
		if(is_null(self::$context)) {
			self::initialize();
		}
		self::$pool->reset('SessionVisits');
		self::$pool->delete();
		return true;
		/* Code below needs testing. Currently it consumes server load. Flushing make visitor counter a little bit inaccurrate, but it's neglectable.*/
		$keys = self::getMemcacheKeys();
		$authorizedIds = array();
		$authorizedAddr = array();
		$removeIds = array();
		$removeAddr = array();

		$idPattern = '/sessions\/(?<id>\w+)/i';
		$authorizedidPattern = '/authorizedSession\/(?<id>\w+)/i';
		$addrPattern = '/anonymousSession\/(?<addr>\w+)/i';
		foreach ($keys as $key) {
			if (preg_match($idPattern, $key, $matches) > 0 || preg_match($authorizedidPattern, $key, $matches) > 0 ){
				array_push($authorizedIds, $matches['id']);
			} else if (preg_match($addrPattern, $key, $matches) > 0) {
				array_push($authorizedAddr, $matches['addr']);
			}
		}
		self::$pool->reset('SessionVisits');
		$results = self::$pool->getAll('id, address');
		foreach ($results as $info) {
			if (!in_array($info['id'],$authorizedIds)) {
				array_push($removeIds, $info['id']);
			}
			if (!in_array($info['address'],$authorizedAddr)) {
				array_push($removeAddr, $info['id']);
			}
		}
		if (count($removeIds) < 25) {
			self::$pool->resetQualifiers();
			self::$pool->setQualifier('id','hasoneof',$removeIds);
			self::$pool->delete();
		} else {
			foreach ($removeIds as $id) {
				self::$pool->resetQualifiers();
				self::$pool->setQualifier('id','eq',$id);
				self::$pool->delete();
			}
		}
		foreach ($removeAddr as $addr) {
			self::$pool->resetQualifiers();
			self::$pool->setQualifier('address','eq',$addr);
			self::$pool->delete();
		}
		return true;
	}

	/* [보안] Textcube-NG: 익명 세션 표지를 IP 가 아니라 세션 ID 로 둔다 — 원본은 IP 마다 익명 세션 하나를 나눠 썼다. 지금 요청의 세션 ID 가 이 주소에서 만든
	   익명 세션일 때만 그대로 쓰고 표지 기한(원본과 같은 120초)을 다시 센다(r3-7, 결정 468c) */
	private static function getAnonymousSession($currentId = null) {
		if (!is_string($currentId) || !preg_match('/\A[0-9a-f]{32}\z/', $currentId))
			return false;
		$key = self::$context->getProperty('service.domain')."/anonymousSession/{$currentId}";
		if (self::$mc->get($key) !== $_SERVER['REMOTE_ADDR'])
			return false;
		self::$mc->set($key, $_SERVER['REMOTE_ADDR'], 0, 120);
		return $currentId;
	}

	private static function newAnonymousSession() {
		for ($i = 0; $i < 3; $i++) {
			// Textcube-NG: 같은 IP 의 익명 세션을 다시 쓰지 않고 새로 만든다(r3-7, 결정 468c)
			$id = dechex(random_int(0x10000000, 0x7FFFFFFF)) . dechex(random_int(0x10000000, 0x7FFFFFFF)) . dechex(random_int(0x10000000, 0x7FFFFFFF)) . dechex(random_int(0x10000000, 0x7FFFFFFF));
			$result = self::$mc->set(self::$context->getProperty('service.domain')."/sessions/{$id}",true,0,self::$context->getProperty('service.timeout'));
			if ($result) {
				$result = self::$mc->set(self::$context->getProperty('service.domain')."/anonymousSession/{$id}",$_SERVER['REMOTE_ADDR'],0,120); // anonymous session timeout is 120 sec. Textcube-NG: 키는 세션 ID, 값은 만든 주소(r3-7, 결정 468c)
				return $id;
			}
		}
		return false;
	}

	public static function setSessionAnonymous($currentId) {
		$id = self::getAnonymousSession($currentId);
		if ($id !== false) {
			if ($id != $currentId)
				session_id($id);
			return true;
		}
		$id = self::newAnonymousSession();
		if ($id !== false) {
			session_id($id);
			return true;
		}
		return false;
	}

	/* Textcube-NG: 세션 값을 만든 주소와 지금 요청 주소가 맞는지(r3-24, R-449). 만든 주소가 기록돼 있지 않으면(익명 표지 120초 만료 등) 막지 않는다. */
	private static function addressMatches($id) {
		$domain = self::$context->getProperty('service.domain');
		foreach (array('authorizedAddress', 'anonymousSession') as $kind) {
			$address = self::$mc->get($domain."/{$kind}/{$id}");
			if (is_string($address) && $address !== $_SERVER['REMOTE_ADDR'])
				return false;
		}
		return true;
	}

	/* Textcube-NG: 인증된 세션은 authorize() 가 기록한 authorizedAddress 가 지금 주소와 같을 때만 인정한다(isOwnAuthorizedSession 과 같은 검사, r3-24, R-449) */
	private static function authorizedAddressMatches($id) {
		$address = self::$mc->get(self::$context->getProperty('service.domain')."/authorizedAddress/{$id}");
		return is_string($address) && $address === $_SERVER['REMOTE_ADDR'];
	}

	public static function isAuthorized($id) {
		if(is_null(self::$mc)) self::initialize();
		/* OpenID and Admin sessions are treated as authorized ones*/
		//$userid = self::$mc->get(self::$context->getProperty('service.domain')."/authorizedSession/{$id}/{$_SERVER['REMOTE_ADDR']}");
		$id = POD::escapeString($id);
		$userid = self::$mc->get(self::$context->getProperty('service.domain')."/authorizedSession/{$id}");
		if(!empty($userid) && self::authorizedAddressMatches($id)) return true;
		else return false;
	}

	public static function isGuestOpenIDSession($id) {
		if(is_null(self::$mc)) self::initialize();
		$id = POD::escapeString($id);
		//$userid = self::$mc->get(self::$context->getProperty('service.domain')."/authorizedSession/{$id}/{$_SERVER['REMOTE_ADDR']}");
		$userid = self::$mc->get(self::$context->getProperty('service.domain')."/authorizedSession/{$id}");
		if(!empty($userid) && $userid < 0 && self::authorizedAddressMatches($id)) return true;
		else return false;
	}

	/* [보안] 다중 도메인 로그인 인계(/login?session=)로 받은 세션 ID 확인 — 인증된 세션이고 그 세션을 만든
	   클라이언트와 주소가 같을 때만 true(주소는 authorize() 가 authorizedAddress 키에 기록, DB 세션의 address 와 같은 역할). */
	public static function isOwnAuthorizedSession($id) {
		if(is_null(self::$mc)) self::initialize();
		if (!is_string($id) || !preg_match('/^[0-9a-f]{32}$/', $id))
			return false;
		$userid = self::$mc->get(self::$context->getProperty('service.domain')."/authorizedSession/{$id}");
		$address = self::$mc->get(self::$context->getProperty('service.domain')."/authorizedAddress/{$id}");
		return (!empty($userid) && is_string($address) && $address === $_SERVER['REMOTE_ADDR']);
	}

	public static function set() {
		if(is_null(self::$mc)) self::initialize();
		// 원본은 ?TSSESSION=<세션 ID> GET 파라미터를 세션 ID 로 받았다(Flash 업로더용, STAGE1 에서 업로더 제거).
		// URL 로 세션 ID 를 받으면 세션 고정·탈취 통로가 되므로 쿠키만 사용한다.
		if ( !empty($_COOKIE[session_name()]) ) {
			$id = $_COOKIE[session_name()];
		} else {
			$id = '';
		}
		if ((strlen($id) < 32) || !self::isAuthorized($id)) {
			self::setSessionAnonymous($id);
		}
	}

	public static function authorize($blogid, $userid) {
		if(is_null(self::$mc)) self::initialize();
		$blogid = intval($blogid);
		$userid = intval($userid);
		$session_cookie_path = "/";
		if( !is_null(self::$context->getProperty('service.session_cookie_path') )) {
			$session_cookie_path = self::$context->getProperty('service.session_cookie_path');
		}
		if (!is_numeric($userid)) return false;
		if( $userid != SESSION_OPENID_USERID ) { /* OpenID session : -1 */
			$_SESSION['userid'] = $userid;
		}
		/* [보안] 세션 고정 방지: 인증할 때마다 새 세션 ID 를 발급한다. 원본은 현재 세션이 이미 인증된 세션이면 그대로 쓰고
		   OpenID 게스트 세션이면 그 ID 의 userid 만 바꿨다 → 로그인 전에 브라우저에 심어 둔 세션 ID 가 로그인 후에도 유지되어
		   그 ID 를 아는 쪽이 같은 로그인 상태를 쓸 수 있었다. 이 요청에서 방금 발급한 ID(OIDC 게스트 → 연결 계정 승격)는
		   이 클라이언트 전용이므로 userid 만 바꾼다. */
		$id = session_id();
		if (self::$issuedId !== null && $id === self::$issuedId) {
			if ($userid == SESSION_OPENID_USERID)
				return true;
			$result = self::$mc->set(self::$context->getProperty('service.domain')."/authorizedSession/{$id}",$userid,0,self::$context->getProperty('service.timeout'));
			if ($result) {
				return true;
			}
		}
		for ($i = 0; $i < 3; $i++) {
			$id = dechex(random_int(0x10000000, 0x7FFFFFFF)) . dechex(random_int(0x10000000, 0x7FFFFFFF)) . dechex(random_int(0x10000000, 0x7FFFFFFF)) . dechex(random_int(0x10000000, 0x7FFFFFFF));
			//$result = self::$mc->set(self::$context->getProperty('service.domain')."/authorizedSession/{$id}/{$_SERVER['REMOTE_ADDR']}",$userid,0,self::$context->getProperty('service.timeout'));
			$result = self::$mc->set(self::$context->getProperty('service.domain')."/authorizedSession/{$id}",$userid,0,self::$context->getProperty('service.timeout'));

			if ($result) {
				// [보안] 세션을 만든 클라이언트 주소 — isOwnAuthorizedSession() 이 로그인 인계 때 확인
				self::$mc->set(self::$context->getProperty('service.domain')."/authorizedAddress/{$id}",$_SERVER['REMOTE_ADDR'],0,self::$context->getProperty('service.timeout'));
				// PHP 7.x+: session_id() cannot change the active session ID after session_start().
				// 원본(PHP 5) @session_id($id) 는 write 대상만 새 ID 로 바꾸고 이전 ID(IP 당 공유 익명 세션)는 기록하지 않았다.
				// 이전 ID 에 로그인 데이터를 기록하면 그 쿠키를 가진 같은 IP 의 다른 클라이언트가 로그인되고 로그아웃·timeout 후에도
				// 유지되므로, session_abort() 로 이전 ID 를 저장 없이 닫고 새 ID 로 다시 시작한다.
				// See: https://www.php.net/manual/en/function.session-id.php , https://www.php.net/manual/en/function.session-abort.php
				$currentData = session_encode();
				session_abort();
				session_id($id);
				@session_start();
				if ($currentData !== false) {
					session_decode($currentData);
				}
				// Textcube-NG: 인증으로 새 세션 ID 를 발급할 때마다 CSRF 비밀값도 새로 만든다 — 로그인 전에 심은 세션 데이터로
				// 토큰을 예측할 수 없게 한다(library/auth.php getCSRFToken, v3.147)
				$_SESSION['csrfToken'] = bin2hex(random_bytes(32));
				self::$issuedId = $id;
				setcookie( self::getName(), $id, array('expires' => 0, 'path' => $session_cookie_path, 'domain' => self::$context->getProperty('service.session_cookie_domain'), 'secure' => (bool)self::$context->getProperty('service.useSSL', false), 'httponly' => true, 'samesite' => 'Lax'));
				return true;
			}
		}
		return false;
	}

	public function getMemcacheKeys($limit = 10000) {
		$keysFound = array();
		$slabs = self::$mc->getExtendedStats('slabs');
		foreach ($slabs as $serverSlabs) {
			foreach ($serverSlabs as $slabId => $slabMeta) {
				try {
					$cacheDump = self::$mc->getExtendedStats('cachedump', (int) $slabId, 1000);
				} catch (Exception $e) {
					continue;
				}

				if (!is_array($cacheDump)) {
					continue;
				}

				foreach ($cacheDump as $dump) {

					if (!is_array($dump)) {
						continue;
					}

					foreach ($dump as $key => $value) {
						$keysFound[] = $key;

						if (count($keysFound) == $limit) {
						    return $keysFound;
						}
					}
				}
			}
		}
		return $keysFound;
	}
}
?>
