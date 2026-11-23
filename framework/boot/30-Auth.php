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

// Of course, BITWISE must be BITWISE! (2^)
define( 'BITWISE_EDITOR', 0x1 );              // 00001
define( 'BITWISE_ADMINISTRATOR', 0x2 );       // 00010
define( 'BITWISE_CREATOR', 0x8 );             // 01000
define( 'BITWISE_OWNER', 0x10 );              // 10000

/* static */
global $sAcoPredefinedChain;
$sAcoPredefinedChain = 
	array(	
		"group.creators"       => array( "group.owners" ),
		"group.owners"         => array( "group.administrators", "group.editors" ),
		"group.administrators" => array( "group.writers" ),
		"group.editors"        => array( "group.writers" ),
		"group.writers"	       => array( "group.readers" )
		);

/* static */
global $requiredPrivFromUri;
$requiredPrivFromUri = array(
		"group.administrators" => array( 
			'/owner/center/dashboard*',
			'/owner/center/about',
			'/owner/communication*',
			'/owner/network*',
			'/owner/reader*',
			'/owner/setting*',
			'/owner/plugin/admin*',
			/* [보안] 원본 표에 없던 관리 경로(데이터 백업·복원, 스킨, 플러그인 설정, 분류·태그 관리)는
			   getRequiredPrivFromUrl() 이 빈 배열(=허용)을 돌려 팀블로그 글쓴이도 접근할 수 있었다.
			   관리 메뉴 노출 조건(interface/common/owner/header.php)과 같게 지정한다. */
			'/owner/data*',
			'/owner/skin*',
			'/owner/plugin*',
			'/owner/entry/category*',
			'/owner/entry/tag*'
			),
		"group.editors" => array(
			'/owner/center/dashboard',
			'/owner/center/about',
			'/owner/entry/post',
			'/owner/entry',
			'/owner/entry/add*',
			'/owner/entry/attach*',
			'/owner/entry/delete*',
			'/owner/entry/detach*',
			'/owner/entry/draft*',
			'/owner/entry/finish*',
			'/owner/entry/edit*',
			'/owner/entry/line*',
			'/owner/entry/loadTemplate*',
			'/owner/entry/post*',
			'/owner/entry/preview*',
			'/owner/entry/protect*',
			'/owner/entry/size*',
			'/owner/entry/visibility*',
			'/owner/communication/comment*',
			'/owner/communication/trackback*',
			'/owner/communication/trash*',
			'/owner/communication/notify*',
			'/owner/network/reader',
			'/owner/reader',
			'/owner/plugin/admin*',
			/* [보안] 분류·태그 관리 — 편집자 메뉴 노출 조건과 같게 편집자 이상 */
			'/owner/entry/category*',
			'/owner/entry/tag*',
			/* [보안] 리더 패널·편집기 화면 설정 저장 — 핸들러가 허용 키 3개(정수 값)만 받으므로 편집자·글쓴이에게도 연다.
			   /owner/setting* (관리자 이상)보다 좁은 경로로, 다른 설정 화면에는 영향 없음 */
			'/owner/setting/userSetting/set*'
			),
		"group.writers" => array(
			'/owner/center/dashboard',
			'/owner/center/about',
			'/owner/entry/post',
			'/owner/entry',
			'/owner/entry/add*',
			'/owner/entry/attach*',
			'/owner/entry/delete*',
			'/owner/entry/detach*',
			'/owner/entry/draft*',
			'/owner/entry/finish*',
			'/owner/entry/edit*',
			'/owner/entry/line*',
			'/owner/entry/loadTemplate*',
			'/owner/entry/post*',
			'/owner/entry/preview*',
			'/owner/entry/protect*',
			'/owner/entry/size*',
			'/owner/entry/visibility*',
			'/owner/communication/trackback*',
			'/owner/communication/trash*',
			'/owner/communication/comment*',
			'/owner/communication/notify*',
			'/owner/network/reader',
			'/owner/network/teamblog/changeBlog*',
			'/owner/help*',
			'/owner/setting/account*',
			'/owner/reader',
			'/owner/plugin/admin*',
			'/owner/openid',
			'/owner/setting/account*',
			/* [보안] 기본 거부 전환에 따라 글쓴이가 쓰던 경로를 명시한다: /owner(조각보로 이동), /owner/keep(글 편집 중
			   세션 유지), 글 목록의 별표·분류 일괄 변경·글쓴이 변경(대상 글 작성자·권한 확인은 각 핸들러에서) */
			'/owner',
			'/owner/keep',
			'/owner/entry/star*',
			'/owner/entry/changeCategory*',
			'/owner/entry/setAuthor*',
			/* [보안] 리더 패널·편집기 화면 설정 저장(편집자 목록과 같은 이유) */
			'/owner/setting/userSetting/set*'
			)
		);

/* Access Request Object: i.e. user */
class Privilege {

/* predefined Aros
	group.owners:         Owners of $blogid's blog system. (Usually unique id.);
	group.administrators: Administrators of $blogid's blog system.
	group.editors:        Adminitrators of $blogid's $blogid's postings.
	group.writers:        Writers to $blogids's blog.
	group.readers:        Readers to $blogids's blog.
	group.guests:         Guests
*/
	function __construct() {
	}

	static function expand($priv) {
		global $sAcoPredefinedChain;
		$predefined_aros = array_keys( $sAcoPredefinedChain );
		do {
			$done = true;
			$new_added_obj = array();
			foreach( $priv as $obj ) {
				if( !in_array( $obj, $predefined_aros ) ) {
					continue;
				}

				foreach( $sAcoPredefinedChain[$obj] as $expand_obj ) {
					if( in_array( $expand_obj, $priv ) ) {
						continue;
					}
					array_push( $new_added_obj, $expand_obj );
				}
			}
			if( !empty( $new_added_obj ) ) {
				$priv = array_merge( $priv, $new_added_obj );
				$done = false;
			}
		} while( ! $done );

		$arranged_objs = array();
		foreach( $priv as $obj ) {
			if( !in_array( $obj, $arranged_objs ) ) {
				array_push( $arranged_objs, $obj );
			}
		}
		return $arranged_objs;
	}

	static function adjust( $priv )
	{
		$blogid = getBlogId();
		if( !Acl::isAvailable($blogid) ) {
			Acl::setAcl( $blogid );
		}

		$currpriv = Acl::getCurrentPrivilege();
		foreach( $priv as $obj ) {
			if( function_exists("fireEvent") ) {
				$currpriv = call_user_func( "fireEvent", "AclAdjustPrivilege", $currpriv, $obj );
			}
		}

		return $currpriv;
	}
}

/* Access Control Object: i.e. uri, components, functions */
class Aco {
	function __construct() {
	}

	static function adjust( $priv, $otherPriv ) {
		// $priv is an string array
		if( !empty($otherPriv) ) {
			if( is_array($otherPriv) ) {
				$priv = array_merge($priv, $otherPriv);
			} else {
				array_push($priv, $otherPriv);
			}
		}
		if( function_exists("fireEvent") ) {
			$priv = call_user_func("fireEvent", "AclAdjustAco", $priv);
		}
		return $priv;
	}

	static function getRequiredPrivFromUrl( $testingUri ) {
		global $requiredPrivFromUri;
		// [보안] 대소문자 비구분 파일시스템(Windows 등)에서는 '/owner/Setting' 처럼 대소문자만 다른 경로도 같은 핸들러를 로드 → 소문자로 비교
		$testingUri = strtolower((string)$testingUri);
		/* [보안] 서비스 관리(/control) 경로는 원본에서 이 표의 검사 대상이 아니어서 각 핸들러의 개별 검사에만 의존했다
		   (control/server 화면은 검사 누락). 서비스 관리자(group.creators)만 허용한다. */
		if( $testingUri == "/control" || substr($testingUri, 0, 9) == "/control/" ) {
			return array( "group.creators" );
		}
		if( substr($testingUri, 0, 6) != "/owner" ) {
			return array();
		}
		//$priv = array( "group.owners" );
		$priv = array();
		foreach( $requiredPrivFromUri as $acoObj => $uriArray ) {
			foreach( $uriArray as $uri ) {
				if ($testingUri == strtolower($uri) ) {
					array_push( $priv, $acoObj );
					break;
				} elseif( substr($uri,-1) == "*" ) {
					if( substr($testingUri, 0, strlen($uri)-1) == strtolower(substr($uri,0,-1)) ) {
						array_push( $priv, $acoObj );
						break;
					}
				} 

			}
		}
		/* [보안] 원본은 표에 없는 /owner 경로에 빈 배열을 돌려 전 구성원에게 허용(기본 허용)했다 → 관리자 이상으로 기본 거부 */
		if( empty($priv) ) {
			$priv = array( "group.administrators" );
		}
		return $priv;
	}
}

class Acl {

	function __construct() {
		$this->context = Model_Context::getInstance();
	}

	static function authorize( $domain, $userid ) {
		$context = Model_Context::getInstance();
		if( !isset( $_SESSION['identity'] ) ) {
			$_SESSION['identity'] = array();
		}
		if( !isset( $_SESSION['identity'][$domain] ) ) {
			$_SESSION['identity'][$domain] = array();
		}
		$_SESSION['identity'][$domain] = $userid;

		if( $domain != 'textcube' ) {
			return;
		}

		/* Support code for legacy */
		$_SESSION['userid'] = $userid;

		if( $userid == 1 ) {
			$ownership = "group.creators";
		} else {
			$ownership = "group.owners";
		}
		$data = DBModel::getInstance();
		$data->reset('Privileges');
		$data->setQualifier('userid','equals',intval($userid));
		$result = $data->getAll('blogid,acl');

		foreach( $result as $rec ) {
			$priv = array("group.writers", "textcube.$userid");

			if( $rec['acl'] & BITWISE_OWNER ) {
				array_push($priv, $ownership);
			}
			if( $rec['acl'] & BITWISE_EDITOR ) {
				array_push($priv, "group.editors");
			}
			if( $rec['acl'] & BITWISE_ADMINISTRATOR ) {
				array_push($priv, "group.administrators");
			}

			Acl::setAcl( $rec['blogid'], $priv, false );
		}

		$blogid = getBlogId();
		$data->reset('Privileges');
		$data->setQualifier('blogid','equals',$blogid);
		$data->setQualifier('userid','equals',intval($userid));
		$data->setAttribute('lastLogin',Timestamp::getUNIXtime());
		$data->update();
		return;
	}

	static function setBasicAcl( $userid ) {
		/* Remain for compatibility */
	}

	static function setTeamAcl( $userid ) {
		/* Remain for compatibility */
	}

	static function getIdentity( $domain ) {
		if( empty($_SESSION['identity'][$domain]) ) {
			return null;
		}
		return $_SESSION['identity'][$domain];
	}

	static function check($requiredPriv = null, $otherPriv = null) {
		if( !is_array( $requiredPriv ) ) {
			$requiredPriv = array( $requiredPriv );
		}

		/* Adujsting access control object from plugins */
		$requiredPriv = Aco::adjust($requiredPriv, $otherPriv);

		/* Adujsting required object from plugins by requiredPriv*/
		$currentPriv = Privilege::adjust($requiredPriv);

		/* We need one of requiredPriv elements is in currentPriv array */
		foreach( $requiredPriv as $obj ) {
			if(in_array($obj, $currentPriv)) {
				return true;
			}
		}

		return false;
	}

	static function setAcl( $blogid, $priv = null, $add = false ) {

		if( !isset( $_SESSION['acl'] ) ) {
			$_SESSION['acl'] = array();
		}

		if( !isset( $_SESSION['acl']["blog.$blogid"] ) ) {
			$_SESSION['acl']["blog.$blogid"] = array();
		}

		if( is_null($priv) ) {
			return;
		}

		if( !is_array($priv) ) {
			$priv = array( $priv );
		}

		if( $add ) {
			$priv = array_merge( $_SESSION['acl']["blog.$blogid"], $priv );
		}

		$_SESSION['acl']["blog.$blogid"] = Privilege::expand($priv);
	}

	static function getCurrentPrivilege($blogid=null) {
		if( is_null($blogid) ) {
			$blogid = getBlogId();
		}
		if( Acl::isAvailable($blogid) ) {
			return $_SESSION['acl']["blog.$blogid"];
		}
		return array();
	}

	static function clearAcl() {
		if( isset( $_SESSION['acl'] ) ) {
			unset($_SESSION['acl']);
		}
		if( isset( $_SESSION['identity'] ) ) {
			unset($_SESSION['identity']);
		}
	}

	static function isAvailable($blogid) {
		if( !isset( $_SESSION['acl'] ) || 
			!is_array( $_SESSION['acl'] ) || 
			!isset( $_SESSION['acl']["blog.$blogid"] ) ) {
			return false;
		}

		return true;
	}

}

class Auth {
	/* [보안] 계정 비밀번호: 원본은 md5(입력)을 SQL 에서 비교했다 → 저장값을 읽어 verifyPassword 로 확인한다.
	   저장 방식은 $service['passwordHash'] 로 선택한다(framework/id/textcube/config.default.php, config.php 에서 변경).
		 'md5'     기본. 원본과 같은 md5 저장. password_hash 값으로 저장된 계정은 로그인 성공 시 md5 로 되돌린다.
		 'multi'   새로 설정·변경하는 비밀번호만 password_hash(PASSWORD_DEFAULT) 로 저장하고 기존 md5 값은 그대로 둔다.
		 'migrate' 'multi' 에 더해 기존 md5 값을 로그인 성공 시 password_hash 값으로 다시 저장한다(authenticate).
	   확인은 방식과 무관하게 md5(32자리 16진)·password_hash 값을 모두 받으므로 방식을 바꿔도 로그인이 막히지 않는다.
	   그 밖의 값은 'md5' 로 취급한다. */
	static function passwordHashMode() {
		global $service;
		$mode = isset($service['passwordHash']) ? $service['passwordHash'] : 'md5';
		return in_array($mode, array('md5', 'multi', 'migrate'), true) ? $mode : 'md5';
	}

	static function hashPassword($password) {
		if (!is_scalar($password))
			return false;
		if (Auth::passwordHashMode() == 'md5')
			return md5((string)$password);	// 원본과 같은 저장값
		if (strpos((string)$password, "\0") !== false)
			return false;	// bcrypt 는 NUL 이후를 버리거나(7.4) ValueError(8.x) → 해시하지 않음
		return password_hash((string)$password, PASSWORD_DEFAULT);
	}

	static function isLegacyPasswordHash($hash) {
		return is_string($hash) && (bool)preg_match('/^[0-9a-f]{32}$/i', $hash);
	}

	static function verifyPassword($password, $hash) {
		if (!is_scalar($password) || !is_string($hash) || $hash === '')
			return false;
		if (Auth::isLegacyPasswordHash($hash))
			return hash_equals(strtolower($hash), md5((string)$password));
		if (strpos((string)$password, "\0") !== false)
			return false;	// password_hash 값은 NUL 없는 입력으로만 만든다(hashPassword). bcrypt 는 NUL 이후를 무시하므로 거부
		return password_verify((string)$password, $hash);
	}

	/* 로그인 성공 후 저장값을 현재 방식으로 다시 저장할지. md5 값은 'migrate' 방식에서만,
	   password_hash 값은 'md5' 방식(md5 로 되돌림)이거나 기본 알고리즘·비용이 바뀐 경우 */
	static function passwordNeedsRehash($hash) {
		$mode = Auth::passwordHashMode();
		if (Auth::isLegacyPasswordHash($hash))
			return ($mode == 'migrate');
		if ($mode == 'md5')
			return true;
		return password_needs_rehash($hash, PASSWORD_DEFAULT);
	}

	/* 비밀번호 저장. 'md5' 방식은 원본과 같은 md5 값을 저장한다. password_hash 값은 Users.password 열이 그보다 짧은 DB
	   (점검으로 넓히기 전 varchar(32) 등)에서 저장이 실패하거나 잘려 로그인이 막히지 않도록 저장값을 다시 읽어 확인하고,
	   다르면 원본과 같은 md5 로 저장한다. */
	static function storePassword($userid, $password) {
		global $database;
		$userid = intval($userid);
		$hash = Auth::hashPassword($password);
		if ($userid < 1 || $hash === false)
			return false;
		if (Auth::isLegacyPasswordHash($hash))	// 'md5' 방식(32자리 16진 값만 들어감)
			return POD::execute("UPDATE {$database['prefix']}Users SET password = '$hash' WHERE userid = $userid");
		if (POD::execute("UPDATE {$database['prefix']}Users SET password = '" . POD::escapeString($hash) . "' WHERE userid = $userid")
			&& (POD::queryCell("SELECT password FROM {$database['prefix']}Users WHERE userid = $userid", 0, false) === $hash))
			return true;
		return POD::execute("UPDATE {$database['prefix']}Users SET password = '" . md5((string)$password) . "' WHERE userid = $userid");
	}

	/* [보안] 인증 토큰(UserSettings 'AuthToken' — 초대 메일 링크, 관리 화면 '임시 암호'). 원본은 만료가 없고 로그인에 써도
	   지워지지 않아(authenticate 끝의 삭제 코드가 주석 처리됨) 메일·화면에 남은 값으로 계속 로그인할 수 있었다.
	   → 발급 시각을 UserSettings 'AuthTokenIssued'(UNIX 시각)에 함께 저장하고 $service['authTokenLifetime'](초, 기본 7일,
	     0 이면 만료 없음)이 지나면 지운다. 로그인에 쓰면 지운다(1회용). 테이블 구조와 AuthToken 값 형식(32자리 16진)은 원본과 같다.
	   발급 시각이 없는 토큰(이 기능 이전에 발급)은 처음 확인한 시각을 발급 시각으로 기록한다. */
	static $authTokenLogin = false;	// 마지막 authenticate 가 인증 토큰으로 성공했는지(library/auth.php login 이 세션에 표시)

	static function authTokenLifetime() {
		global $service;
		if (!isset($service['authTokenLifetime']) || !is_numeric($service['authTokenLifetime']))
			return 604800;
		return max(0, intval($service['authTokenLifetime']));
	}

	static function issueAuthToken($userid) {
		$userid = intval($userid);
		if ($userid < 1)
			return false;
		$authtoken = bin2hex(random_bytes(16));	// CSPRNG 128비트(원본과 같은 32자리 16진 형식)
		// 발급 시각을 먼저 기록(토큰보다 늦게 기록하면 그 사이의 확인에서 새 토큰이 이전 발급 시각으로 만료 처리될 수 있음)
		if (!Auth::__setUserSettingValue($userid, 'AuthTokenIssued', (string)Timestamp::getUNIXtime())
			|| !Auth::__setUserSettingValue($userid, 'AuthToken', $authtoken))
			return false;
		return $authtoken;
	}

	static function getValidAuthToken($userid) {
		$userid = intval($userid);
		if ($userid < 1)
			return null;
		$query = DBModel::getInstance();
		$query->reset('UserSettings');
		$query->setQualifier('userid', 'equals', $userid);
		$query->setQualifier('name', 'equals', 'AuthToken', true);
		$authtoken = $query->getCell('value');
		if (!is_string($authtoken) || $authtoken === '')
			return null;
		$query->reset('UserSettings');
		$query->setQualifier('userid', 'equals', $userid);
		$query->setQualifier('name', 'equals', 'AuthTokenIssued', true);
		$issued = $query->getCell('value');
		if (!is_numeric($issued)) {	// 이 기능 이전에 발급된 토큰: 지금을 발급 시각으로 기록
			Auth::__setUserSettingValue($userid, 'AuthTokenIssued', (string)Timestamp::getUNIXtime());
			return $authtoken;
		}
		$lifetime = Auth::authTokenLifetime();
		if ($lifetime > 0 && (Timestamp::getUNIXtime() - intval($issued)) > $lifetime) {
			Auth::removeAuthToken($userid, $authtoken);
			return null;
		}
		return $authtoken;
	}

	/* $authtoken 을 주면 저장값이 그 값인 행만 지우고 지운 행이 있을 때만 true(사용·만료 처리 — 같은 토큰을 동시에 써도 한 번만
	   성공하고, 그 사이 새로 발급된 토큰은 지우지 않음). 주지 않으면 그 계정의 토큰을 지우고 DELETE 실행 결과를 돌려준다
	   (원본의 DELETE ... LIMIT 1 은 MySQL 전용 문법). 토큰을 지우면 발급 시각도 지운다. */
	static function removeAuthToken($userid, $authtoken = null) {
		global $userSetting;
		$userid = intval($userid);
		if ($userid < 1)
			return false;
		$query = DBModel::getInstance();
		$query->reset('UserSettings');
		$query->setQualifier('userid', 'equals', $userid);
		$query->setQualifier('name', 'equals', 'AuthToken', true);
		if (is_null($authtoken)) {
			$result = $query->delete();
		} else {
			$query->setQualifier('value', 'equals', (string)$authtoken, true);
			$result = (intval($query->delete(null, 'count')) > 0);
		}
		if ($result) {
			$query->reset('UserSettings');
			$query->setQualifier('userid', 'equals', $userid);
			$query->setQualifier('name', 'equals', 'AuthTokenIssued', true);
			$query->delete();
		}
		$userSetting = array();	// Setting::getUserSettingGlobal 캐시
		return $result;
	}

	static function __setUserSettingValue($userid, $name, $value) {
		global $userSetting;
		$query = DBModel::getInstance();
		$query->reset('UserSettings');
		$query->setQualifier('userid', 'equals', intval($userid));
		$query->setQualifier('name', 'equals', $name, true);
		$query->setAttribute('userid', intval($userid));
		$query->setAttribute('name', $name, true);
		$query->setAttribute('value', $value, true);
		$userSetting = array();	// Setting::getUserSettingGlobal 캐시
		return $query->replace();
	}

	static function login($loginid, $password) {
		global $blogid;
		// Textcube-NG: 블로그 API(XML-RPC api_login) 로그인에도 웹 로그인(library/auth.php login())의 로그인 시도 제한을 같은 helper 로
		// 적용한다 — 원본은 실패를 세지 않아 이 경로로는 횟수 제한 없이 비밀번호를 시도할 수 있었다(r3-16, R-376). 접속 IP 잠금
		// ($service['loginLockScope'] 'ip')·계정 잠금·실패 기록·성공 때 기록 지우기·잠긴 동안 응답 시간 맞춤을 login() 과 같게 하고,
		// 쿠키·세션은 다루지 않는다. 잠금 안내 남은 시간(__loginIpLockRemaining)은 로그인 화면용이라 쓰지 않는다.
		// helper(library/auth.php, 모두 같은 파일)가 불려 있지 않으면 전처럼 인증만 한다
		if (strpos((string)$loginid, "\0") !== false) return false;
		if (!function_exists('__loginLockStatus') || !function_exists('__loginIpLockName')) {
			if( Auth::authenticate($blogid,$loginid,$password,true) === false ) {
				return false;
			}
			return true;
		}
		$ipLockName = (__loginLockScope() === 'ip') ? __loginIpLockName(isset($_SERVER['REMOTE_ADDR']) ? $_SERVER['REMOTE_ADDR'] : null) : null;
		$ipFailure = array(null, null, false);
		if ($ipLockName !== null) {
			$ipFailure = __loginIpLockRead($ipLockName);
			if (__loginLockStatus($ipFailure[0], $ipFailure[1], __loginIpFailureLimit(), Timestamp::getUNIXtime()) === 'locked') {
				Acl::clearAcl();
				return false;
			}
		}
		$lockUserid = intval(User::getUserIdByEmail($loginid));
		$lockStatus = 'none';
		$failureCount = Setting::getUserSettingGlobal('LoginFailureCount', null, $lockUserid, true);
		$failureTime = Setting::getUserSettingGlobal('LoginFailureTime', null, $lockUserid, true);
		if ($lockUserid > 0) {
			$lockStatus = __loginLockStatus($failureCount, $failureTime, __loginFailureLimit(), Timestamp::getUNIXtime());
			if ($lockStatus === 'locked') {
				Acl::clearAcl();
				Setting::getBlogSettingGlobal("blogApiPassword", "");
				if ((strlen((string)$password) == 32) && preg_match('/[0-9a-f]{32}/i', $password)) {
					if (function_exists('getUserIdByEmail')) getUserIdByEmail($loginid);
					Auth::getValidAuthToken($lockUserid);
				}
				__loginTimingVerify($loginid, $password);
				Setting::removeUserSettingGlobal('LoginFailureCount', 0);
				Setting::removeUserSettingGlobal('LoginFailureTime', 0);
				if ($ipLockName !== null) __loginIpLockFail($ipLockName, $ipFailure);
				return false;
			}
		}
		if( Auth::authenticate($blogid,$loginid,$password,true) === false ) {
			if ($lockUserid > 0) {
				Setting::setUserSettingGlobal('LoginFailureCount', ($lockStatus === 'counting') ? intval($failureCount) + 1 : 1, $lockUserid);
				Setting::setUserSettingGlobal('LoginFailureTime', Timestamp::getUNIXtime(), $lockUserid);
			} else {
				Setting::removeUserSettingGlobal('LoginFailureCount', 0);
				Setting::removeUserSettingGlobal('LoginFailureTime', 0);
			}
			if ($ipLockName !== null) __loginIpLockFail($ipLockName, $ipFailure);
			return false;
		}
		if ($lockUserid > 0 && (!empty($failureCount) || !empty($failureTime))) {
			Setting::removeUserSettingGlobal('LoginFailureCount', $lockUserid);
			Setting::removeUserSettingGlobal('LoginFailureTime', $lockUserid);
		}
		if ($ipLockName !== null) __loginIpLockPrune();
		return true;
	}

	static function authenticate( $blogid, $loginid, $password, $blogapi = false ) {
		global $database;
		$session = array(); 
		Acl::clearAcl();
		$checkPassword = false;	// [보안] 저장된 비밀번호 확인 여부(아래 verifyPassword, 원본 md5 SQL 비교 대체)
		Auth::$authTokenLogin = false;
		// Textcube-NG: NUL 문자가 든 로그인 ID 는 받지 않는다(r3-3)
		if (strpos((string)$loginid, "\0") !== false) return false;
		$loginid_raw = $loginid;	// Textcube-NG: escape 전 값(아래 getUserIdByEmail() 에 넘김, php8.x 트리와 같은 이름)(r3-3)
		$loginid = POD::escapeString($loginid);

		$blogApiPassword = Setting::getBlogSettingGlobal("blogApiPassword", "");

		if ((strlen($password) == 32) && preg_match('/[0-9a-f]{32}/i', $password)) { // Raw login. ( with/without auth token)
			$userid = getUserIdByEmail($loginid_raw);	// Textcube-NG: getUserIdByEmail() 이 스스로 escape 하므로 escape 전 값을 넘긴다(두 번 escape 하면 따옴표·역슬래시가 든 ID 를 못 찾음)(r3-3)
			if(!empty($userid) && !is_null($userid)) {
				/* [보안] 인증 토큰은 유효 기간을 확인하고(Auth::getValidAuthToken, 만료된 토큰은 지움) 로그인에 쓰면 지운다
				   (Auth::removeAuthToken 이 그 값의 행을 지운 경우에만 성공 → 1회용). 원본은 만료가 없고 사용 후에도 남았다. */
				$authtoken = Auth::getValidAuthToken($userid);
				if (!empty($authtoken) && ($authtoken === $password) && Auth::removeAuthToken($userid, $authtoken)) {	// If user requested auth token, use it to confirm.
					$session['userid'] = $userid;
					Auth::$authTokenLogin = true;
				} else {	// login with password
					$checkPassword = true;
				}
			} else {
				Auth::hashPassword($password);	// Textcube-NG: 없는 로그인 ID 도 해시 1회 계산 — 아래 일반 로그인과 같은 응답 시간 맞춤(r3-3)
				return false;
			}
		} else if( $blogapi && !empty($blogApiPassword) ) {	// BlogAPI login
			/* [보안] 블로그 API 비밀번호: 원본은 이 값을 SQL 문자열 비교로 넣어 "로그인 ID 존재 + API 비밀번호 일치"만으로
			   블로그와 무관한 임의 사용자(서비스 관리자 포함)로 인증되었다. API 비밀번호는 PHP 에서 hash_equals 로 비교하고
			   현재 블로그($blogid)의 구성원(Privileges) 계정에 한해 허용한다. 일치하지 않으면 일반 비밀번호로 확인한다
			   (원본의 escape 된 비밀번호 md5 비교는 일반 로그인과 같은 원문 md5 비교로 통일). */
			if (hash_equals((string)$blogApiPassword, (string)$password)) {
				$session = POD::queryRow("SELECT u.userid, u.loginid, u.name FROM {$database['prefix']}Users u, {$database['prefix']}Privileges p WHERE u.loginid = '$loginid' AND p.userid = u.userid AND p.blogid = " . intval($blogid));
			}
			if ( empty($session) ) $checkPassword = true;
		} else {	// Normal login
			$checkPassword = true;
		}
		if ( empty($session) && $checkPassword ) {
			/* [보안] 원본은 md5(입력)을 SQL 에서 비교했다 → 로그인 ID 행의 저장값을 읽어 Auth::verifyPassword 로 확인(md5·password_hash 값 모두).
			   저장값 형식이 $service['passwordHash'] 방식과 다르면 로그인 성공 시 Auth::storePassword 로 다시 저장(Auth::passwordNeedsRehash) */
			$row = POD::queryRow("SELECT userid, loginid, name, password FROM {$database['prefix']}Users WHERE loginid = '$loginid'", 'both', false);
			if (empty($row)) {
				Auth::hashPassword($password);	// 없는 로그인 ID 도 해시 1회 계산(응답 시간 차이로 계정 존재 추정 완화)
			} else if (Auth::verifyPassword($password, $row['password'])) {
				$session = array('userid' => $row['userid'], 'loginid' => $row['loginid'], 'name' => $row['name']);
				if (Auth::passwordNeedsRehash($row['password']))
					Auth::storePassword($row['userid'], $password);
			}
		}
		if ( empty($session) ) {
			/* You should compare return value with '=== false' which checks with variable types*/
			return false;
		}
		$userid = $session['userid'];

		Acl::authorize( 'textcube', $userid );
		POD::execute("UPDATE {$database['prefix']}Users SET lastlogin = ".Timestamp::getUNIXtime()." WHERE loginid = '$loginid'");
//		POD::execute("DELETE FROM {$database['prefix']}UserSettings WHERE userid = '$userid' AND name = 'AuthToken' LIMIT 1");
		return $userid;
	}

}
?>
