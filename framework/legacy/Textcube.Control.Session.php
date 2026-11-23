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

define( 'SESSION_OPENID_USERID', -1 );

final class Session {
	private static $sessionMicrotime;
	private static $sessionName = null;
	private static $sessionDBRepair = false;
	private static $sConfig = null;
	private static $context = null;
	private static $pool = null;
	private static $issuedId = null;	// [보안] 이 요청에서 authorize() 가 발급한 세션 ID

	function __construct() {
		$sessionMicrotime = Timer::getMicroTime();
	}

	private static function initialize() {
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
		if(is_null(self::$context)) self::initialize();
		if( self::$sessionName == null ) {
			if( self::$context->getProperty('service.session_cookie') !== null) {
				self::$sessionName = self::$context->getProperty('service.session_cookie');
			} else {
				self::$sessionName = 'TSSESSION'.self::$context->getProperty('service.domain').self::$context->getProperty('service.path');
				self::$sessionName = preg_replace( '/[^a-zA-Z0-9]/', '', self::$sessionName );
			}
		}
		return self::$sessionName;
	}

	public static function read($id) {
		if(is_null(self::$context)) self::initialize();
		$id = POD::escapeString($id);

		if ($result = self::query('cell',"SELECT privilege FROM ".self::$context->getProperty('database.prefix')."Sessions ".
///			WHERE id = '$id' AND address = '{$_SERVER['REMOTE_ADDR']}' AND updated >= (".(Timestamp::getUNIXtime() - self::$context->getProperty('service.timeout')).")")) {
			"WHERE id = '$id' AND address = '".POD::escapeString($_SERVER['REMOTE_ADDR'])."' AND updated >= (".(Timestamp::getUNIXtime() - self::$context->getProperty('service.timeout')).")")) {
			return $result;
		}
		return '';
	}

	public static function write($id, $data) {
		if(is_null(self::$context)) self::initialize();

		if (strlen($id) < 32)
			return false;
		$userid = Acl::getIdentity('textcube');
		if( empty($userid) ) {
			$userid = Acl::getIdentity('openid') ? SESSION_OPENID_USERID : '';
		}
		if( empty($userid) ) $userid = 'null';
		$id = POD::escapeString($id);
		$data    = POD::escapeString($data);
		$server  = POD::escapeString($_SERVER['HTTP_HOST']);
		$request = POD::escapeString(UTF8::lessenAsByte($_SERVER['REQUEST_URI'], 255, ''));
		$referer = isset($_SERVER['HTTP_REFERER']) ? POD::escapeString(UTF8::lessenAsByte($_SERVER['HTTP_REFERER'], 255, '')) : '';
		$timer = Timer::getMicroTime() - self::$sessionMicrotime;
		$result = self::query('count',"UPDATE ".self::$context->getProperty('database.prefix')."Sessions
				SET userid = $userid, privilege = '$data', server = '$server', request = '$request', referer = '$referer', timer = $timer, updated = ".Timestamp::getUNIXtime()."
				WHERE id = '$id' AND address = '".POD::escapeString($_SERVER['REMOTE_ADDR'])."'");	// [보안] REMOTE_ADDR escape
		if ($result && $result == 1) {
			@POD::commit();
			return true;
		}
		// PHP 7.0+: save handler write() 가 false 를 반환하면 "Failed to write session data" 경고를 낸다(PHP 5 는 무시).
		// 0행 갱신은 원본 설계상 저장하지 않는 경우(gc·timeout 으로 행이 삭제됨, 또는 address 불일치)이므로
		// 원본과 같이 저장하지 않고 실패로 보고하지 않는다.
		// See: https://www.php.net/manual/en/sessionhandlerinterface.write.php
		return true;
	}

	public static function destroy($id, $setCookie = false) {
		if(is_null(self::$context)) self::initialize();
		$id = POD::escapeString($id);
		self::query('query',"DELETE FROM ".self::$context->getProperty('database.prefix')."Sessions ".
//			WHERE id = '$id' AND address = '{$_SERVER['REMOTE_ADDR']}'");
			"WHERE id = '$id'");
		self::gc();
		return true;
	}

	public static function gc($maxLifeTime = false) {
		if(is_null(self::$context)) self::initialize();
		self::query('query',"DELETE FROM ".self::$context->getProperty('database.prefix')."Sessions
			WHERE updated < ".(Timestamp::getUNIXtime() - self::$context->getProperty('service.timeout')));
		$result = self::query('all',"SELECT DISTINCT v.id, v.address
			FROM ".self::$context->getProperty('database.prefix')."SessionVisits v
			LEFT JOIN ".self::$context->getProperty('database.prefix')."Sessions s ON v.id = s.id AND v.address = s.address
			WHERE s.id IS NULL AND s.address IS NULL");
		if ($result) {
			foreach ($result as $g) {
				// Textcube-NG: r3-30 R-505 — DB 에서 읽은 id·address 도 escape(원본 시절 escape 없이 저장된 행의 2차 주입 방지)
				self::query('query',"DELETE FROM ".self::$context->getProperty('database.prefix')."SessionVisits WHERE id = '".POD::escapeString($g['id'])."' AND address = '".POD::escapeString($g['address'])."'");
			}
		}
		return 0;
	}

	/* [보안] Textcube-NG: 같은 IP 의 다른 방문자 익명 세션을 넘겨주지 않는다 — 원본은 주소만 맞으면 아무 익명 세션 ID 나 돌려줘 같은 NAT·프록시 뒤 방문자들이
	   한 세션(세션 데이터·CSRF 토큰)을 나눠 썼다. 지금 요청이 가진 세션 ID 가 이 주소의 유효한 익명 세션일 때만 그대로 쓴다(r3-7, 결정 468c) */
	private static function getAnonymousSession($currentId = null) {
		if(is_null(self::$context)) self::initialize();
		if (!is_string($currentId) || !preg_match('/\A[0-9a-f]{32}\z/', $currentId))
			return false;
		$result = self::query('cell',"SELECT id FROM ".self::$context->getProperty('database.prefix')."Sessions WHERE id = '".$currentId."' AND address = '".POD::escapeString($_SERVER['REMOTE_ADDR'])."' AND userid IS NULL AND preexistence IS NULL AND updated >= ".(Timestamp::getUNIXtime() - self::$context->getProperty('service.timeout')));
		//$result = self::query('cell',"SELECT id FROM ".self::$context->getProperty('database.prefix')."Sessions WHERE userid IS NULL AND preexistence IS NULL");
		if ($result)
			return $result;
		return false;
	}

	private static function newAnonymousSession() {
		if(is_null(self::$context)) self::initialize();
		$meet_again_baby = 900;
		$t = self::$context->getProperty('service.timeout');
		if( !empty($t)) {
			$meet_again_baby = self::$context->getProperty('service.timeout');
		}

 		//If you are not a robot, subsequent UPDATE query will override to proper timestamp.
		 //Anonymous session lifespan is 60 sec.
		$meet_again_baby -= 60;

		for ($i = 0; $i < 3; $i++) {
			// Textcube-NG: 같은 IP 의 익명 세션을 다시 쓰지 않고 새로 만든다(r3-7, 결정 468c)
			$id = dechex(random_int(0x10000000, 0x7FFFFFFF)) . dechex(random_int(0x10000000, 0x7FFFFFFF)) . dechex(random_int(0x10000000, 0x7FFFFFFF)) . dechex(random_int(0x10000000, 0x7FFFFFFF));
			$result = self::query('count',"INSERT INTO ".self::$context->getProperty('database.prefix')."Sessions (id, address, server, request, referer, created, updated) VALUES('$id', '".POD::escapeString($_SERVER['REMOTE_ADDR'])."', '', '', '', ".Timestamp::getUNIXtime().",".(Timestamp::getUNIXtime() - $meet_again_baby).")");	// [보안] REMOTE_ADDR escape
			if ($result > 0)
				return $id;
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

	public static function isAuthorized($id) {
		/* OpenID and Admin sessions are treated as authorized ones*/
		if(is_null(self::$context)) self::initialize();
		$id = POD::escapeString($id);

		$result = self::query('cell',"SELECT id
			FROM ".self::$context->getProperty('database.prefix')."Sessions
			WHERE id = '$id' AND address = '".POD::escapeString($_SERVER['REMOTE_ADDR'])."' "
				."AND (userid IS NOT NULL OR preexistence IS NOT NULL)");	// Textcube-NG: 주소가 다른 요청의 쿠키는 인증된 세션으로 보지 않는다(r3-24, R-449, write()·isOwnAuthorizedSession 과 같은 escape)
		if ($result) {
			return true;
		}
		return false;
	}

	public static function isGuestOpenIDSession($id) {
		if(is_null(self::$context)) self::initialize();
		// Textcube-NG: r3-27 R-468 — $id 를 SQL 에 그대로 붙이던 것을 escape(isAuthorizedSession·isOwnAuthorizedSession 은 이미 호출처가 형식을 검사하거나 escape 한다)
		$result = self::query('cell',"SELECT id
			FROM ".self::$context->getProperty('database.prefix')."Sessions
			WHERE id = '".POD::escapeString((string) $id)."' AND address = '".POD::escapeString($_SERVER['REMOTE_ADDR'])."'".
				" AND userid < 0");	// Textcube-NG: 주소 검사 복원(r3-24, R-449)
		if ($result)
			return true;
		return false;
	}

	/* [보안] 다중 도메인 로그인 인계(/login?session=)로 받은 세션 ID 확인 — 인증된(만료 전) 세션이고 그 세션을 만든
	   클라이언트와 주소가 같을 때만 true. Session::write 도 같은 주소의 행만 갱신한다. */
	public static function isOwnAuthorizedSession($id) {
		if(is_null(self::$context)) self::initialize();
		if (!is_string($id) || !preg_match('/^[0-9a-f]{32}$/', $id))
			return false;
		$result = self::query('cell',"SELECT id
			FROM ".self::$context->getProperty('database.prefix')."Sessions
			WHERE id = '$id' AND address = '".POD::escapeString($_SERVER['REMOTE_ADDR'])."'
				AND (userid IS NOT NULL OR preexistence IS NOT NULL)
				AND updated >= ".(Timestamp::getUNIXtime() - self::$context->getProperty('service.timeout')));
		return $result ? true : false;
	}

	public static function set() {
		self::$sessionMicrotime = Timer::getMicroTime();
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
		if(is_null(self::$context)) self::initialize();
		$blogid = intval($blogid);
		$userid = intval($userid);
		$session_cookie_path = "/";
		$t = self::$context->getProperty('service.session_cookie_path');
		if( !empty($t)) {
			$session_cookie_path = self::$context->getProperty('service.session_cookie_path');
		}
		if (!is_numeric($userid))
			return false;
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
			$result = self::query('execute',"UPDATE ".self::$context->getProperty('database.prefix')."Sessions ".
				"SET userid = $userid WHERE id = '".POD::escapeString($id)."'");
			if ($result) {
				return true;
			}
		}
		for ($i = 0; $i < 3; $i++) {
			$id = dechex(random_int(0x10000000, 0x7FFFFFFF)) . dechex(random_int(0x10000000, 0x7FFFFFFF)) . dechex(random_int(0x10000000, 0x7FFFFFFF)) . dechex(random_int(0x10000000, 0x7FFFFFFF));
			$result = self::query('execute',"INSERT INTO ".self::$context->getProperty('database.prefix')."Sessions
				(id, address, userid, created, updated)
				VALUES('$id', '".POD::escapeString($_SERVER['REMOTE_ADDR'])."', $userid, ".Timestamp::getUNIXtime().",".Timestamp::getUNIXtime().")");	// [보안] REMOTE_ADDR escape
			if ($result) {
				// PHP 7.x+: session_id() cannot change the active session ID after session_start().
				// 원본(PHP 5) @session_id($id) 는 write 대상만 새 ID 로 바꾸고 이전 ID(IP 당 공유 익명 세션)는 기록하지 않았다.
				// 이전 ID 에 로그인 데이터를 기록하면 그 쿠키를 가진 같은 IP 의 다른 클라이언트가 로그인되고 로그아웃·timeout 후에도
				// 유지되므로, session_abort() 로 이전 ID 를 저장 없이 닫고 새 ID 로 다시 시작한다.
				// See: https://www.php.net/manual/en/function.session-id.php , https://www.php.net/manual/en/function.session-abort.php
				if (function_exists('session_abort')) {
					$currentData = session_encode();
					session_abort();
					session_id($id);
					@session_start();
					if ($currentData !== false) {
						session_decode($currentData);
					}
				} else {
					// Textcube-NG: PHP 5.5 에는 session_abort()(5.6)가 없다. 5.x 는 시작된 세션의 ID 를 session_id() 로 바꿀 수 있고
					// 요청 끝 write() 는 새 ID 로만 기록한다(원본 방식) — 이전 ID(IP 공유 익명 세션)에는 기록하지 않는다(r4-4)
					@session_id($id);
				}
				// Textcube-NG: 인증으로 새 세션 ID 를 발급할 때마다 CSRF 비밀값도 새로 만든다 — 로그인 전에 심은 세션 데이터로
				// 토큰을 예측할 수 없게 한다(library/auth.php getCSRFToken, v3.147)
				$_SESSION['csrfToken'] = bin2hex(random_bytes(32));
				self::$issuedId = $id;
				//$service['domain'] = $service['domain'].':8888';
				setCookieCompat( self::getName(), $id, array('expires' => 0, 'path' => $session_cookie_path, 'domain' => self::$context->getProperty('service.session_cookie_domain'), 'secure' => (bool)self::$context->getProperty('service.useSSL', false), 'httponly' => true, 'samesite' => 'Lax'));
				return true;
			}
		}
		return false;
	}

	/* Customized queryset (for recovering Session tables) */
	private static function query($mode='query',$sql) {
		if(is_null(self::$context)) self::initialize();

		$result = self::DBQuery($mode,$sql);
		if($result === false) {
			if (self::$sessionDBRepair === false) {
				@POD::query("REPAIR TABLE ".self::$context->getProperty('database.prefix')."Sessions");
				@POD::query("REPAIR TABLE ".self::$context->getProperty('database.prefix')."SessionVisits");
				$result = self::DBQuery($mode,$sql);
				self::$sessionDBRepair = true;
			}
		}
		return $result;
	}

	private static function DBQuery($mode='query',$sql) {
		switch($mode) {
			case 'cell':	return POD::queryCell($sql);
			case 'row':		return POD::queryRow($sql);
			case 'execute':	return POD::execute($sql);
			case 'count':	return POD::queryCount($sql);
			case 'all':		return POD::queryAll($sql);
			case 'query':default:
							return POD::query($sql);
		}
		return null;
	}
}
?>
