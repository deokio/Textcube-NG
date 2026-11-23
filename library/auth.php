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

/// Textcube-NG: 로그인 시도 제한 — 허용 실패 횟수. config.php 의 $service['loginFailureLimit'](기본 5). 숫자가 아니거나 1보다
/// 작으면 5(v3.202)
function __loginFailureLimit() {
	global $service;
	if (!isset($service['loginFailureLimit']) || !is_numeric($service['loginFailureLimit']) || intval($service['loginFailureLimit']) < 1) return 5;
	return intval($service['loginFailureLimit']);
}

/// Textcube-NG: 로그인 시도 제한 — 계정의 실패 기록(횟수·마지막 실패 시각)으로 상태를 정한다. 기록이 없거나 마지막 실패에서
/// 900초(15분)가 지났으면(시각이 미래여도) 'none', 횟수가 한도 이상이면 'locked', 그 밖은 'counting'(v3.202)
function __loginLockStatus($count, $time, $limit, $now) {
	if (!is_numeric($count) || intval($count) < 1 || !is_numeric($time)) return 'none';
	$age = intval($now) - intval($time);
	if ($age < 0 || $age >= 900) return 'none';
	return (intval($count) >= intval($limit)) ? 'locked' : 'counting';
}

/// Textcube-NG: 로그인 시도 제한 — 응답 시간 맞춤. 잠긴 계정에도 Auth::authenticate 의 비밀번호 확인과 같은 행 조회(Users,
/// loginid)와 Auth::verifyPassword(행이 없으면 Auth::hashPassword)를 하고 결과는 버린다. 로그인·인증 토큰 사용·lastlogin·
/// 저장값 갱신은 하지 않는다(v3.202)
function __loginTimingVerify($loginid, $password) {
	global $database;
	$loginid = POD::escapeString($loginid);
	$row = POD::queryRow("SELECT userid, loginid, name, password FROM {$database['prefix']}Users WHERE loginid = '$loginid'", 'both', false);
	if (empty($row))
		Auth::hashPassword($password);
	else
		Auth::verifyPassword($password, $row['password']);
}

/// Textcube-NG: 로그인 잠금 방식(r3-14) — config.php 의 $service['loginLockScope']. 'ip' 일 때만 'ip', 그 밖(없음·다른 값)은
/// 'account'(기본, 계정별 잠금만)
function __loginLockScope() {
	global $service;
	return (isset($service['loginLockScope']) && $service['loginLockScope'] === 'ip') ? 'ip' : 'account';
}

/// Textcube-NG: 로그인 잠금 방식(r3-14) — config.php 의 $service['loginLockShowRemaining']. true(불리언)일 때만 켜짐(기본 false).
/// 접속 IP 잠금의 안내 문구에서만 쓴다
function __loginLockShowRemaining() {
	global $service;
	return (isset($service['loginLockShowRemaining']) && $service['loginLockShowRemaining'] === true);
}

/// Textcube-NG: 로그인 잠금 방식(r3-14) — 접속 IP 별 허용 실패 횟수. config.php 의 $service['loginIpFailureLimit'](기본 5).
/// 숫자가 아니거나 1보다 작으면 5(__loginFailureLimit 과 같은 방식)
function __loginIpFailureLimit() {
	global $service;
	if (!isset($service['loginIpFailureLimit']) || !is_numeric($service['loginIpFailureLimit']) || intval($service['loginIpFailureLimit']) < 1) return 5;
	return intval($service['loginIpFailureLimit']);
}

/// Textcube-NG: 로그인 잠금 방식(r3-14) — 접속 IP 실패 기록(ServiceSettings 의 LoginFailIP_* 행)의 최대 개수.
/// config.php 의 $service['loginIpRecordLimit'](기본 500). 1~10000 의 정수(정수 모양 문자열 포함)가 아니면 500(불리언·배열·소수점 아래가 있는 수도 500. 20.0처럼 정수 값인 float는 그 정수로 받음).
/// ServiceSettings 는 요청마다 표 전체를 읽으므로(Setting::getServiceSetting) 행 수를 묶는다
function __loginIpRecordLimit() {
	global $service;
	if (!isset($service['loginIpRecordLimit']) || is_bool($service['loginIpRecordLimit'])) return 500;
	$limit = filter_var($service['loginIpRecordLimit'], FILTER_VALIDATE_INT, array('options' => array('min_range' => 1, 'max_range' => 10000)));
	return ($limit === false) ? 500 : $limit;
}

/// Textcube-NG: 로그인 잠금 방식(r3-14) — 접속 IP 의 실패 기록 이름(ServiceSettings.name). 주소가 IP 형식(FILTER_VALIDATE_IP)이
/// 아니면 null(IP 잠금을 하지 않는다). IPv6 는 주소 하나(/128) 단위이고, IPv4-mapped IPv6(::ffff:a.b.c.d)는 같은 IPv4 주소의
/// 다른 표기이므로 IPv4 로 센다. 표기(대소문자·0 생략·::)는 inet_pton 의 16바이트 값으로 맞춘다.
/// 이름은 'LoginFailIP_' 와 sha1 앞 20자(32바이트, ServiceSettings.name varchar(32))
function __loginIpLockName($address) {
	if (!is_string($address) || filter_var($address, FILTER_VALIDATE_IP) === false) return null;
	$bin = inet_pton($address);
	if ($bin === false) return null;
	if (strlen($bin) === 16 && substr($bin, 0, 12) === str_repeat("\0", 10) . "\xff\xff") $bin = substr($bin, 12);
	return 'LoginFailIP_' . substr(sha1($bin), 0, 20);
}

/// Textcube-NG: 로그인 잠금 방식(r3-14) — 실패 기록 값 '횟수:마지막 실패 시각' 을 array(횟수, 시각) 으로. 형식이 다르면 array(null, null)
function __loginIpLockRecord($value) {
	if (is_string($value) && preg_match('/^([0-9]{1,9}):([0-9]{1,10})$/D', $value, $match)) return array($match[1], $match[2]);
	return array(null, null);
}

/// Textcube-NG: 로그인 잠금 방식(r3-14) — 접속 IP 실패 기록 한 행을 DB 에서 바로 읽는다(이름 일치, escape). 전역 캐시
/// ($__serviceSetting)는 요청 첫머리에 읽은 값이라 쓰지 않는다. array(횟수, 시각, 행 있음) 을 돌려준다. 행이 없으면
/// array(null, null, false), 행은 있으나 값 형식이 다르면 array(null, null, true)(value 열은 4 DB 모두 NOT NULL 이므로 getCell 의
/// null 은 행 없음이다)
function __loginIpLockRead($name) {
	$query = DBModel::getInstance();
	$query->reset('ServiceSettings');
	$query->setQualifier('name', 'equals', $name, true);
	$value = $query->getCell('value');
	if ($value === null) return array(null, null, false);
	$record = __loginIpLockRecord($value);
	return array($record[0], $record[1], true);
}

/// Textcube-NG: 로그인 잠금 방식(r3-14) — 접속 IP 의 실패를 하나 센다. 기록이 없거나 마지막 실패에서 15분이 지났으면 1부터.
/// 횟수는 999999999 에서 멈춘다(기록 형식 9자리). 이 IP 의 행이 없어 새로 만들 때만 행 수 상한을 본다: 기한 지난 기록을 지우고도
/// 남은 기록이 loginIpRecordLimit 이상이면 새 IP 는 기록하지 않는다(그 시도에는 계정별 잠금만 적용된다). 이미 행이 있는 IP 는
/// 상한과 관계없이 계속 센다. Setting::setServiceSettingGlobal 은 이름·값을 escape 하고 캐시도 고친다
function __loginIpLockFail($name, $record) {
	if (empty($record[2]) && __loginIpLockPrune() >= __loginIpRecordLimit()) return;
	$now = Timestamp::getUNIXtime();
	$count = (__loginLockStatus($record[0], $record[1], __loginIpFailureLimit(), $now) !== 'none') ? min(intval($record[0]) + 1, 999999999) : 1;
	Setting::setServiceSettingGlobal($name, $count . ':' . $now);
}

/// Textcube-NG: 로그인 잠금 방식(r3-14) — 접속 IP 실패 기록 한 행을 이름 일치로 지운다(escape). Setting::removeServiceSetting 은
/// LIKE '%이름%' 로 지우므로 쓰지 않는다. 전역 캐시($__serviceSetting)에서도 뺀다
function __loginIpLockRemove($name) {
	global $__serviceSetting;
	$query = DBModel::getInstance();
	$query->reset('ServiceSettings');
	$query->setQualifier('name', 'equals', $name, true);
	$query->delete();
	if (is_array($__serviceSetting)) unset($__serviceSetting[$name]);
}

/// Textcube-NG: 로그인 잠금 방식(r3-14) — login() 이 접속 IP 잠금으로 실패를 돌려줄 때의 남은 시간(초, 1~900). 그 밖은 0.
/// login() 이 정하고 로그인 화면(interface/login/index.php)이 읽는다
function __loginIpLockRemaining($set = null) {
	static $remaining = 0;
	if ($set !== null) $remaining = max(0, intval($set));
	return $remaining;
}

/// Textcube-NG: 로그인 잠금 방식(r3-14) — 마지막 실패에서 15분이 지났거나 값 형식이 다른 접속 IP 실패 기록을 지우고, 남은 기록
/// 개수를 돌려준다(목록 질의가 실패하면 0). 'ip' 일 때 로그인에 성공하면 부르고(돌려준 값은 쓰지 않음), 새 IP 의 실패를 기록하기
/// 전에도 부른다(행 수 상한). LIKE 의 '_' 는 아무 글자와 맞으므로 이름을 PHP 에서 다시 거르고, 거른 이름만 센다
function __loginIpLockPrune() {
	$query = DBModel::getInstance();
	$query->reset('ServiceSettings');
	$query->setQualifier('name', 'like', 'LoginFailIP_', true);
	$rows = $query->getAll('name, value');
	if (!is_array($rows)) return 0;
	$now = Timestamp::getUNIXtime();
	$remaining = 0;
	foreach ($rows as $row) {
		if (!is_string($row[0]) || !preg_match('/^LoginFailIP_[0-9a-f]{20}$/D', $row[0])) continue;
		$record = __loginIpLockRecord($row[1]);
		if (__loginLockStatus($record[0], $record[1], __loginIpFailureLimit(), $now) === 'none')
			__loginIpLockRemove($row[0]);
		else
			$remaining++;
	}
	return $remaining;
}

function login($loginid, $password, $preKnownPassword = null) {
	$ctx = Model_Context::getInstance();
	$blogid = getBlogId();
	// Textcube-NG: NUL 문자가 든 로그인 ID 는 받지 않는다(r3-3)
	if (strpos((string)$loginid, "\0") !== false) return false;
	// Textcube-NG: 로그인 잠금 방식(r3-14) — $service['loginLockScope'] 가 'ip' 이면 접속 IP 별 실패 기록(ServiceSettings,
	// __loginIpLockName)도 센다. 한도($service['loginIpFailureLimit'])에 이르면 마지막 실패에서 15분 동안 그 IP 의 모든 로그인을
	// 실패로 돌려준다. 이 응답은 계정과 관계없으므로 계정 조회·응답 시간 맞춤은 하지 않는다. 아래 계정별 잠금은 그대로 함께 적용한다.
	// 'account'(기본)이거나 접속 주소가 IP 형식이 아니면 IP 기록 질의를 하지 않는다
	__loginIpLockRemaining(0);
	$ipLockName = (__loginLockScope() === 'ip') ? __loginIpLockName(isset($_SERVER['REMOTE_ADDR']) ? $_SERVER['REMOTE_ADDR'] : null) : null;
	$ipFailure = array(null, null, false);
	if ($ipLockName !== null) {
		$ipFailure = __loginIpLockRead($ipLockName);
		$ipNow = Timestamp::getUNIXtime();
		if (__loginLockStatus($ipFailure[0], $ipFailure[1], __loginIpFailureLimit(), $ipNow) === 'locked') {
			Acl::clearAcl();
			__loginIpLockRemaining(900 - ($ipNow - intval($ipFailure[1])));
			return false;
		}
	}
	// Textcube-NG: 로그인 시도 제한 — 계정별 실패 횟수·시각을 UserSettings(LoginFailureCount·LoginFailureTime)에 두고, 한도
	// ($service['loginFailureLimit'], 기본 5)에 이르면 마지막 실패에서 15분 동안 비밀번호가 맞아도 실패로 돌려준다(v3.202)
	$lockUserid = intval(User::getUserIdByEmail($loginid));
	$lockStatus = 'none';
	// Textcube-NG: 응답 시간 맞춤 — 없는 계정(userid 0, 기록이 없다)도 실패 기록을 같은 질의로 읽는다(v3.202)
	$failureCount = Setting::getUserSettingGlobal('LoginFailureCount', null, $lockUserid, true);
	$failureTime = Setting::getUserSettingGlobal('LoginFailureTime', null, $lockUserid, true);
	if ($lockUserid > 0) {
		$lockStatus = __loginLockStatus($failureCount, $failureTime, __loginFailureLimit(), Timestamp::getUNIXtime());
		if ($lockStatus === 'locked') {
			Acl::clearAcl();
			// Textcube-NG: 응답 시간 맞춤 — 잠긴 동안에도 Auth::authenticate 와 같은 행 조회·비밀번호 확인(결과는 버림)과 실패
			// 기록 쓰기 2회를 한다. 읽은 값을 그대로 쓰므로 횟수·잠금 시각은 바뀌지 않는다(v3.202). 블로그 API 비밀번호 읽기와 32자 hex 입력의
			// 계정·토큰 조회도 같이 하고, 기록 쓰기는 없는 계정과 같은 지우기 질의(지워지는 행 없음)로 해 잠긴 계정의 응답 차이를 줄인다(r3-3)
			Setting::getBlogSettingGlobal("blogApiPassword", "");
			if ((strlen((string)$password) == 32) && preg_match('/[0-9a-f]{32}/i', $password)) {
				getUserIdByEmail($loginid);
				Auth::getValidAuthToken($lockUserid);
			}
			__loginTimingVerify($loginid, $password);
			Setting::removeUserSettingGlobal('LoginFailureCount', 0);
			Setting::removeUserSettingGlobal('LoginFailureTime', 0);
			// Textcube-NG: 로그인 잠금 방식(r3-14) — 'ip' 이면 잠긴 계정의 시도도 접속 IP 실패로 센다(아래 인증 실패와 같은 쓰기 1회)
			if ($ipLockName !== null) __loginIpLockFail($ipLockName, $ipFailure);
			return false;
		}
	}
	$userid = Auth::authenticate($blogid , $loginid, $password );

	if( $userid === false ) {
		if ($lockUserid > 0) {
			Setting::setUserSettingGlobal('LoginFailureCount', ($lockStatus === 'counting') ? intval($failureCount) + 1 : 1, $lockUserid);
			Setting::setUserSettingGlobal('LoginFailureTime', Timestamp::getUNIXtime(), $lockUserid);
		} else {
			// Textcube-NG: 응답 시간 맞춤 — 없는 계정도 같은 표(UserSettings)에 쓰기 질의 2회를 보낸다. 기록을 남기지 않도록
			// 행이 없는 userid 0 의 같은 이름 행을 지우는 질의(지워지는 행 없음)로 한다(v3.202)
			Setting::removeUserSettingGlobal('LoginFailureCount', 0);
			Setting::removeUserSettingGlobal('LoginFailureTime', 0);
		}
		// Textcube-NG: 로그인 잠금 방식(r3-14) — 'ip' 이면 있는 계정·없는 계정의 실패를 똑같이 접속 IP 실패로 센다
		if ($ipLockName !== null) __loginIpLockFail($ipLockName, $ipFailure);
		return false;
	}
	if ($lockUserid > 0 && (!empty($failureCount) || !empty($failureTime))) {
		Setting::removeUserSettingGlobal('LoginFailureCount', $lockUserid);
		Setting::removeUserSettingGlobal('LoginFailureTime', $lockUserid);
	}
	// Textcube-NG: 로그인 잠금 방식(r3-14) — 'ip' 이면 로그인 성공 때 기한이 지난 접속 IP 실패 기록을 지운다. 이 IP 의 기록은
	// 15분이 지나기 전에는 지우지 않는다(성공한 계정으로 다른 계정 시도 횟수를 비우지 못하게)
	if ($ipLockName !== null) __loginIpLockPrune();

	if (empty($_POST['save'])) {
		setCookieCompat('TSSESSION_LOGINID', '', array('expires' => time() - 31536000, 'path' => $ctx->getProperty('service.path') . '/', 'domain' => $ctx->getProperty('service.domain'), 'secure' => (bool)$ctx->getProperty('service.useSSL', false), 'httponly' => true, 'samesite' => 'Lax'));
	} else {
		setCookieCompat('TSSESSION_LOGINID', $loginid, array('expires' => time() + 31536000, 'path' => $ctx->getProperty('service.path') . '/', 'domain' => $ctx->getProperty('service.domain'), 'secure' => (bool)$ctx->getProperty('service.useSSL', false), 'httponly' => true, 'samesite' => 'Lax'));
	}

	/* [보안] 세션 고정 방지: 인증에 성공하면 현재 블로그 권한과 관계없이 새 세션 ID 를 발급한다. 원본은 현재 블로그의
	   글쓴이(group.writers)일 때만 발급해, 그 밖의 회원은 로그인 정보가 로그인 전 세션(같은 주소의 클라이언트가 함께 쓰는
	   익명 세션)에 저장되었다. 새 ID 를 만들지 못하면 로그인 정보를 지우고 실패로 처리한다. */
	if (!Session::authorize($blogid, $userid)) {
		Acl::clearAcl();
		unset($_SESSION['userid']);
		return false;
	}
	// Textcube-NG: 로그인하면 이전 세션의 재인증 실패 횟수(reauthFailures)와 재인증 표지(reauth)를 지운다(v3.202)
	unset($_SESSION['reauthFailures'], $_SESSION['reauth']);
	/* [보안] 인증 토큰(초대·임시 암호)으로 로그인한 세션 표시. 토큰은 로그인에 쓰면 지워지므로(1회용, Auth::authenticate)
	   현재 비밀번호 없이 비밀번호를 정하는 것(owner/setting/account/password)은 이 표시로 허용한다. 원본은 계정에
	   토큰이 남아 있는 동안 허용했다. */
	if (Auth::$authTokenLogin)
		$_SESSION['authTokenLogin'] = array('userid' => intval($userid), 'time' => Timestamp::getUNIXtime());
	else
		unset($_SESSION['authTokenLogin']);
	return true;
}

function logout() {
	fireEvent("Logout");
	Acl::clearAcl();
	Transaction::clear();
	session_destroy();
}

function requireLogin() {
	global $service, $hostURL, $blogURL;
	if(isset($_POST['refererURI'])) $_GET['refererURI'] = $_POST['refererURI'];
	else if(isset($_SESSION['refererURI'])) {
		$_GET['refererURI'] = $_SESSION['refererURI'];
		unset($_SESSION['refererURI']);
	}
	if (!empty($service['loginURL'])) {
		header("Location: {$service['loginURL']}?requestURI=" . rawurlencode("{$hostURL}{$_SERVER['REQUEST_URI']}") . (isset($_GET['refererURI']) && !empty($_GET['refererURI']) ? "&refererURI=". rawurlencode($_GET['refererURI']) : ''));
	} else {
		$requestURI = rawurlencode("{$hostURL}{$_SERVER['REQUEST_URI']}") .  (isset($_GET['refererURI']) && !empty($_GET['refererURI']) ? "&refererURI=". rawurlencode($_GET['refererURI']) : '');

		header ("Location: $hostURL$blogURL/login?requestURI=" . $requestURI );
	}
	exit;
}

function doesHaveMembership() {
	return Acl::getIdentity('textcube') !== null;
}

function requireMembership() {
	global $hostURL;
	if( doesHaveMembership() ) return true;
	$_SESSION['refererURI'] = $hostURL.$_SERVER['REQUEST_URI'];
	requireLogin();
}

function getUserId() {
	return intval(Acl::getIdentity('textcube'));
}

/*
function getBlogId() {
	global $blogid;
	return intval($blogid);
}*/

function setBlogId($id) {
	global $blogid;
	$blogid = $id;
}

function doesHaveOwnership($extra_aco=null) {
	return Acl::check( array("group.administrators","group.writers"), $extra_aco);
}

function requireOwnership() {
	if (doesHaveOwnership())
		return true;
	requireLogin();
	return false;
}

// PHP port(Textcube-NG): r3-10 R-283 보호글 비밀번호 쿠키(<cookie_prefix>GUEST_PASSWORD) 읽기·비교를 한곳에 모음 — 글 보기(interface/common/blog/entries.php)·
// 모바일 글(library/view/mobileView.php)·첨부(interface/blog/attachment.php). 쿠키가 없거나 문자열이 아니면(GUEST_PASSWORD[]=… 배열) null 이라 trim() 에
// 배열을 넘기지 않는다. 비교는 hash_equals()(PHP 5.6+, 두 값 모두 문자열일 때만)로 하고, 원본의 느슨한 == (숫자 문자열 '1e1' == '10' 등)는 쓰지 않는다
function getGuestPasswordCookie() {
	$name = Model_Context::getInstance()->getProperty('service.cookie_prefix') . 'GUEST_PASSWORD';
	if (!isset($_COOKIE[$name]) || !is_string($_COOKIE[$name]))
		return null;
	return $_COOKIE[$name];
}

function checkGuestPasswordCookie($password) {
	$cookie = getGuestPasswordCookie();
	if ($cookie === null || !(is_string($password) || $password === null))
		return false;
	return hash_equals(trim((string)$password), trim($cookie));
}

/// CSRF 강화 (본 포트 개선, upstream 미존재): 기존 host-only Referer 검증은 단일 host
/// path-모드 멀티블로그의 cross-blog CSRF를 막지 못함. host 동일 시 블로그 스코프까지 검증.
/// single/domain 모드는 기존 동작 유지(회귀 0), path 모드만 blogname 비교.
/// Textcube-NG: blogname 을 추출하지 못하면(Referer 가 origin 까지만 옴) 'unresolved' — 기존 fail-open(통과 + notice) 대신
/// 호출부가 판정한다. CSRF 토큰 경로(owner/reader 의 GET·HEAD 외)만 통과, Referer 만 보는 경로는 412(v3.147).
/// 블로그 이름이 빈 블로그(path 모드 루트)는 루트 Referer 를 'pass' 로 본다.
/// 회귀+강화 검증: _sectest/csrf_strictroute_test.php (20 케이스 ALL PASS),
/// _sectest/csrf_token_test.php (트리의 실제 코드 추출, 77 케이스 ALL PASS).
function __referentBlogScopeStatus($referer, $hostHeader, $serviceType, $basePath, $blogName) {
	if (!is_string($referer) || $referer === '') return 'block';
	$url = parse_url($referer);
	if ($url === false || empty($url['host'])) return 'block';
	$refHost = strtolower($url['host']);
	$srvHost = strtolower(explode(':', (string)$hostHeader)[0]);
	if ($refHost !== $srvHost) return 'block';
	if ($serviceType !== 'path') return 'pass';
	$refPath = isset($url['path']) ? $url['path'] : '';
	if ($basePath !== '' && strpos($refPath, $basePath) === 0)
		$refPath = substr($refPath, strlen($basePath));
	$seg = explode('/', ltrim($refPath, '/'));
	$refBlog = isset($seg[0]) ? urldecode($seg[0]) : '';
	if ($refBlog === '') return ((string)$blogName === '') ? 'pass' : 'unresolved';
	return ($refBlog === (string)$blogName) ? 'pass' : 'block';
}

/// Textcube-NG: CSRF 토큰 (본 포트 개선, upstream 미존재, v3.147). 세션 비밀값($_SESSION['csrfToken'], 인증 시 재발급 —
/// Session::authorize)과 blogid 로 만든 HMAC-SHA256(64자 16진). XHR 은 X-CSRF-Token 헤더, 폼은 POST csrfToken 으로 보낸다
/// (URL 매개변수 토큰은 받지 않음). preprocessor 가 요청 값을 $GLOBALS['__csrfTokenSubmitted'] 에 보관하고 핸들러에서는 지운다.
/// blogid 에 묶어 domain 모드에서 한 세션을 쓰는 다른 블로그 화면의 토큰으로는 요청할 수 없다. 같은 출처(path 모드의 다른
/// 블로그)는 관리 화면을 직접 읽을 수 있어 막지 못한다 — SECURITY.md 참조.
function __csrfTokenFor($secret, $blogid) {
	return hash_hmac('sha256', 'blog:' . intval($blogid), $secret);
}

function __csrfTokenStatus($submitted, $secret, $blogid) {
	if (!is_string($secret) || strlen($secret) !== 64) return 'block';
	if (!is_string($submitted) || strlen($submitted) !== 64) return 'block';
	return hash_equals(__csrfTokenFor($secret, $blogid), $submitted) ? 'pass' : 'block';
}

function getCSRFToken() {
	if (!doesHaveMembership()) return '';
	// 배포 전에 로그인한 세션에는 비밀값이 없다 — 처음 부를 때 만든다(지연 생성)
	if (!isset($_SESSION['csrfToken']) || !is_string($_SESSION['csrfToken']) || strlen($_SESSION['csrfToken']) !== 64)
		$_SESSION['csrfToken'] = bin2hex(random_bytes(32));
	return __csrfTokenFor($_SESSION['csrfToken'], getBlogId());
}

function __isCSRFTokenValid() {
	if (!doesHaveMembership()) return false;
	$submitted = isset($GLOBALS['__csrfTokenSubmitted']) ? $GLOBALS['__csrfTokenSubmitted'] : '';
	$secret = isset($_SESSION['csrfToken']) ? $_SESSION['csrfToken'] : null;
	return __csrfTokenStatus($submitted, $secret, getBlogId()) === 'pass';
}

function requireCSRFToken() {
	if (!__isCSRFTokenValid()) __respondPreconditionFailed();
}

function requireStrictRoute() {
	$context = Model_Context::getInstance();
	$method = isset($_SERVER['REQUEST_METHOD']) ? strtoupper((string)$_SERVER['REQUEST_METHOD']) : 'GET';
	$referer = isset($_SERVER['HTTP_REFERER']) ? $_SERVER['HTTP_REFERER'] : null;
	// Textcube-NG: owner/reader 의 GET·HEAD 외 요청(토큰 경로)은 CSRF 토큰 필수. 토큰이 맞고 Referer 가 없으면 통과(Referer 를
	// 보내지 않는 브라우저), 있으면 아래 판정을 유지하되 path 모드 블로그 이름 미추출('unresolved')은 통과. 그 밖(GET, blog
	// 인터페이스)은 Referer 만 보며 'unresolved' 도 412 — 기존 fail-open(통과 + E_USER_NOTICE)을 없앴다(v3.147)
	$tokenRoute = in_array($context->getProperty('uri.interfaceType'), array('owner', 'reader'), true)
		&& $method !== 'GET' && $method !== 'HEAD';
	if ($tokenRoute) {
		if (!__isCSRFTokenValid()) __respondPreconditionFailed();
		if (!is_string($referer) || $referer === '') return;
	}
	$__scope = __referentBlogScopeStatus($referer,
			isset($_SERVER['HTTP_HOST']) ? $_SERVER['HTTP_HOST'] : '',
			$context->getProperty('service.type'),
			(string)$context->getProperty('service.path'),
			(string)$context->getProperty('blog.name'));
	if ($__scope === 'pass' || ($tokenRoute && $__scope === 'unresolved'))
		return;
	__respondPreconditionFailed();
}

/// Textcube-NG: 상태 변경 핸들러는 POST 만 받는다 — 그 밖의 메서드는 405(v3.147, interface/blog/line.php)
function requirePostMethod() {
	if (isset($_SERVER['REQUEST_METHOD']) && strtoupper((string)$_SERVER['REQUEST_METHOD']) === 'POST') return;
	header('HTTP/1.1 405 Method Not Allowed');
	header('Allow: POST');
	exit;
}

/// Textcube-NG: 민감 설정 변경 전 재인증 — 현재 로그인 사용자의 비밀번호를 다시 확인한다(v3.149). 비밀번호가 없는
/// 계정(소셜·OIDC 전용, 저장 해시가 빈 값)은 항상 실패(User::confirmPassword → Auth::verifyPassword). 틀리면 세션당 횟수를
/// 세어 한도(__passwordConfirmFailureLimit, 기본 5)에 이르면 로그아웃(세션 파기)하고(r3-15, R-380), 맞으면 횟수를 지운다. 빈 값은 세지 않는다. 호출부는 실패 시 응답 코드 9.
function isReauthenticated($password) {
	if (!doesHaveMembership()) return false;
	if (!is_string($password) || $password === '') return false;
	if (!User::confirmPassword(getUserId(), $password)) {
		$_SESSION['reauthFailures'] = (isset($_SESSION['reauthFailures']) ? intval($_SESSION['reauthFailures']) : 0) + 1;
		if ($_SESSION['reauthFailures'] >= __passwordConfirmFailureLimit()) logout();	// Textcube-NG: recordPasswordConfirmResult 와 같은 한도(r3-15, R-380)
		return false;
	}
	unset($_SESSION['reauthFailures']);
	return true;
}

/// Textcube-NG: 현재 비밀번호 확인 실패 제한 — 허용 실패 횟수. config.php 의 $service['passwordConfirmFailureLimit'](기본 5).
/// 숫자가 아니거나 1보다 작으면 5(v3.202)
function __passwordConfirmFailureLimit() {
	global $service;
	if (!isset($service['passwordConfirmFailureLimit']) || !is_numeric($service['passwordConfirmFailureLimit']) || intval($service['passwordConfirmFailureLimit']) < 1) return 5;
	return intval($service['passwordConfirmFailureLimit']);
}

/// Textcube-NG: 현재 비밀번호 확인 결과 기록 — 데이터 전체 삭제(owner/data/remove)·비밀번호 변경(owner/setting/account/password)의
/// 현재 비밀번호 확인 결과를 받아, 틀리면 재인증(isReauthenticated)과 같은 세션 횟수(reauthFailures)를 더하고 한도
/// (__passwordConfirmFailureLimit)에 이르면 로그아웃(세션 파기)한다. 맞으면 횟수를 지운다. 확인 결과를 그대로 돌려준다(v3.202)
function recordPasswordConfirmResult($confirmed) {
	if (!$confirmed) {
		$_SESSION['reauthFailures'] = (isset($_SESSION['reauthFailures']) ? intval($_SESSION['reauthFailures']) : 0) + 1;
		if ($_SESSION['reauthFailures'] >= __passwordConfirmFailureLimit()) logout();
		return false;
	}
	unset($_SESSION['reauthFailures']);
	return true;
}

/// Textcube-NG: 재인증 표지 — 외부 로그인(소셜·OIDC)을 거쳐 GET 으로 돌아오는 작업(오픈아이디 연결)은 비밀번호를 실을
/// 수 없으므로, 먼저 POST 로 재인증한 뒤 세션에 표지를 남기고 작업 직전에 확인·삭제한다(1회용). 같은 작업·사용자·블로그이고
/// 300초 이내일 때만 'valid'(v3.149). 세션 데이터는 Session::authorize() 가 새 ID 로 옮기므로 외부 로그인 뒤에도 남는다.
function __reauthMarkerStatus($marker, $action, $userid, $blogid, $now) {
	if (!is_array($marker) || !isset($marker['action'], $marker['userid'], $marker['blogid'], $marker['time']))
		return 'missing';
	if ($marker['action'] !== $action || intval($marker['userid']) !== intval($userid) || intval($marker['blogid']) !== intval($blogid))
		return 'mismatch';
	$age = intval($now) - intval($marker['time']);
	if ($age < 0 || $age >= 300) return 'expired';
	return 'valid';
}

function setReauthMarker($action) {
	$_SESSION['reauth'] = array('action' => $action, 'userid' => getUserId(), 'blogid' => getBlogId(), 'time' => Timestamp::getUNIXtime());
}

/// 표지가 유효하면 true. $consume 이면(작업 직전) 결과와 관계없이 지우고, 만료된 표지는 늘 지운다.
function checkReauthMarker($action, $consume) {
	if (!doesHaveMembership()) return false;
	$status = __reauthMarkerStatus(isset($_SESSION['reauth']) ? $_SESSION['reauth'] : null, $action, getUserId(), getBlogId(), Timestamp::getUNIXtime());
	if ($consume || $status === 'expired') unset($_SESSION['reauth']);
	return $status === 'valid';
}

/// Textcube-NG: 412 응답 — 기존 requireStrictRoute() 의 출력(헤더 3줄 + HTML)을 그대로 옮겼다(v3.147)
function __respondPreconditionFailed() {
	header('HTTP/1.1 412 Precondition Failed');
	header('Content-Type: text/html');
	header("Connection: close");
?>
<!DOCTYPE html>
<html>
<head>
	<meta charset="UTF-8">
	<title><?php echo _t('Precondition Failed');?></title>
</head>
<body>
	<h1><?php echo _t('Precondition Failed');?></h1>
</body>
</html>
<?php
	exit;
}

function requireStrictBlogURL() {
	$context = Model_Context::getInstance();
	if($context->getProperty('uri.isStrictBlogURL') == true) return;
	header('HTTP/1.1 404 Not found');
	exit;
}

/// 오픈 리다이렉트 방지 (본 포트 개선, upstream 미존재): requestURI 등 사용자 입력을 Location /
/// document.location 으로 보내기 전에 대상이 이 서비스 안인지 판정한다(순수 함수, 'pass'/'block').
/// 허용: '/' 로 시작하는 상대 경로('//' 제외), http(s) 이면서 host 가 현재 HTTP_HOST · service.domain ·
/// (domain 모드) service.domain 의 하위 도메인 · 현재 블로그 secondaryDomain(www 변형 포함, URIHandler 조회 규칙과 동일).
/// 'about:blank' 는 $allowBlank 일 때만(멀티도메인 세션 전달 iframe, common/*/footer.php).
/// 검증: _sectest/redirect_target_test.php
function __redirectTargetStatus($target, $hostHeader, $serviceType, $serviceDomain, $secondaryDomain, $allowBlank = false) {
	if (!is_string($target) || $target === '') return 'block';
	// Browsers drop tabs/newlines and read '\' as '/', so such characters could turn a path into '//host'.
	if (preg_match('/[\x00-\x20\x7F\\\\]/', $target)) return 'block';
	if ($target === 'about:blank') return $allowBlank ? 'pass' : 'block';
	if ($target[0] === '/')
		return (isset($target[1]) && $target[1] === '/') ? 'block' : 'pass';
	if (!preg_match('@^https?://([A-Za-z0-9._-]+)(?::[0-9]*)?(?:[/?#]|$)@i', $target, $match)) return 'block';
	$host = rtrim(strtolower($match[1]), '.');
	if ($host === '') return 'block';
	$current = rtrim(strtolower(explode(':', (string)$hostHeader)[0]), '.');
	if ($current !== '' && $host === $current) return 'pass';
	$domain = rtrim(strtolower(explode(':', (string)$serviceDomain)[0]), '.');
	if ($domain !== '') {
		if ($host === $domain) return 'pass';
		if ($serviceType === 'domain' && substr($host, -strlen('.' . $domain)) === '.' . $domain) return 'pass';
	}
	$secondary = strtolower(trim((string)$secondaryDomain));
	if ($secondary !== '') {
		$alternative = (substr($host, 0, 4) === 'www.') ? substr($host, 4) : 'www.' . $host;
		if ($secondary === $host || $secondary === $alternative) return 'pass';
	}
	return 'block';
}

function getSafeRedirectURI($target, $fallback, $allowBlank = false) {
	$context = Model_Context::getInstance();
	$status = __redirectTargetStatus($target,
			isset($_SERVER['HTTP_HOST']) ? $_SERVER['HTTP_HOST'] : '',
			$context->getProperty('service.type'),
			(string)$context->getProperty('service.domain'),
			(string)$context->getProperty('blog.secondaryDomain'),
			$allowBlank);
	return ($status === 'pass') ? $target : $fallback;
}

function requirePrivilege($AC) {
	if(Acl::check($AC)) return true;
	else header('HTTP/1.1 404 Not found');
	exit;
}

function validateAPIKey($blogid, $loginid, $key) {
	global $service;
	/* [보안] Textcube-NG: API 키는 블로그 API 요청(ttxml·댓글 알리미 RSS/Atom)에만 쓰는 별도 값이다(결정 461j: 사용 범위 제한). 원본은 API 키를 정하지 않은
	   계정이면 계정 비밀번호를 API 키 자리에 받아, 화면 로그인 비밀번호가 ttxml 주소(GET)로 오갈 수 있었다 → 기본은 API 키가 없으면 403,
	   $service['allowPasswordAsAPIKey'] === true 일 때만 원본처럼 비밀번호 대체(이 함수는 API 요청에서만 부르며 로그인 세션을 만들지 않는다).
	   값은 문자열만, 비교는 hash_equals. 로그인 ID 는 getUserIdByEmail() 이 escape 하므로 여기서 escape 하지 않는다(원본은 두 번 escape)(r3-7) */
	if (!is_string($loginid) || $loginid === '')
		return false;
	if (!is_string($key) || $key === '') {
		header('HTTP/1.1 403 Forbidden');
		exit;
	}
	$userid = User::getUserIdByEmail($loginid);
	if (empty($userid)) {
		// Textcube-NG: 없는 사용자도 다른 실패 갈래와 같이 403 — 원본은 getUserIdByEmail 의 null 이 `=== false` 를 지나 confirmPassword 실패로 403 이었다.
		// notifycomment(RSS·Atom)는 반환값을 보지 않으므로 false 만 돌려주면 피드가 나간다(r3-7 보충, 461j-5)
		header('HTTP/1.1 403 Forbidden');
		exit;
	}
	$currentAPIKey = Setting::getUserSettingGlobal('APIKey',null,$userid);
	if ($currentAPIKey === null || $currentAPIKey === '') {
		if (!(isset($GLOBALS['service']['allowPasswordAsAPIKey']) && $GLOBALS['service']['allowPasswordAsAPIKey'] === true) || !User::confirmPassword($userid, $key)) {
			header('HTTP/1.1 403 Forbidden');
			exit;
		}
	} else if (!hash_equals((string)$currentAPIKey, $key)) {
		header('HTTP/1.1 403 Forbidden');
		exit;
	}
	return true;
}

function isLoginId($blogid, $loginid) {
	global $database;
	$loginid = POD::escapeString($loginid);

	// 팀블로그 :: 팀원 확인
	$result = POD::queryCount("SELECT u.userid
			FROM {$database['prefix']}Users u,
				{$database['prefix']}Privileges t
			WHERE t.blogid = $blogid
				AND u.loginid = '$loginid'
				AND t.userid = u.userid");
	// End TeamBlog
	if ($result && $result === 1)
		return true;
	return false;
}

function generatePassword() {
	/* [보안] 원본은 정수 하나를 base64 인코딩한 문자열의 일부 8자를 사용해 가능한 값이 약 10^6 가지 이하였다
	   (rand → random_int 로 바꿔도 출력 공간은 같음). 임시 비밀번호·보호글 기본 비밀번호에 쓰이므로
	   CSPRNG(random_int)로 소문자·숫자 12자(약 62비트)를 생성한다. */
	$chars = 'abcdefghijklmnopqrstuvwxyz0123456789';
	$password = '';
	for ($i = 0; $i < 12; $i++) {
		$password .= $chars[random_int(0, 35)];
	}
	return $password;
}

function resetPassword($blogid, $loginid) {
	global $service, $serviceURL;
	/* [보안] 원본은 메일 발송 앞의 $result 가 정의되지 않아 항상 false 를 돌려주어 메일이 발송되지 않았고(upstream 동일),
	   global $database 가 없어 비밀번호 조회도 실패했다. 기능을 되살리면서 다음과 같이 바꾼다.
	   - 링크: 요청 Host 헤더로 만든 $hostURL 대신 설정 기준 블로그 주소(getBlogURLById)로, 초대 메일과 같은 형식(getInvitationLink).
	     원본 링크의 requestURI 에 넣던 저장된 비밀번호 해시는 넣지 않는다.
	   - 토큰: Auth::issueAuthToken(CSPRNG, 이름 'AuthToken', 유효 기간·1회용 — 초대 토큰과 같음). 원본 이름 'Authtoken' 은
	     대소문자를 구분하는 DB 에서 로그인(Auth::authenticate)이 찾지 못한다.
	   - 받는 주소: 입력값이 아니라 저장된 로그인 ID(User::getEmail). 입력값은 DB 비교 규칙(대소문자·악센트 무시 등)에 따라
	     다른 주소로도 같은 계정에 일치할 수 있다.
	   - 유효한 토큰이 5분 안에 발급되었으면 새로 발급·발송하지 않는다(반복 요청으로 메일 폭주·방금 보낸 토큰 덮어쓰기 방지).
	   - 메일 발송에 실패하면 발급한 토큰을 지운다. */
	if (!isLoginId($blogid, $loginid))
		return false;
	$userid = intval(User::getUserIdByEmail($loginid));
	if ($userid < 1)
		return false;
	$email = User::getEmail($userid);
	if (empty($email))
		return false;
	$query = DBModel::getInstance();
	$query->reset('UserSettings');
	$query->setQualifier('userid', 'equals', $userid);
	$query->setQualifier('name', 'equals', 'AuthTokenIssued', true);
	$issued = $query->getCell('value');
	if (is_numeric($issued) && (Timestamp::getUNIXtime() - intval($issued)) < 300 && !is_null(Auth::getValidAuthToken($userid)))
		return false;
	$authtoken = Auth::issueAuthToken($userid);
	if (empty($authtoken))
		return false;
	// Textcube-NG: Host 헤더 검사 — 메일의 주소(링크·이미지)는 요청 Host 가 아니라 domain 과 config.php 의 포트(적지 않았으면 서비스 포트)로
	// 만든다(v3.205)
	$mailPort = isset($service['configuredPort']) ? $service['configuredPort'] : (isset($service['port']) ? $service['port'] : null);
	$mailDomain = $service['domain'] . (!empty($mailPort) ? ':' . $mailPort : '');
	$mailBlogName = (string)getBlogName($blogid);
	$mailServiceURL = isset($service['serviceURL']) ? URL::toAbsolute($service['serviceURL']) : (Model_Context::getInstance()->getProperty('service.useSSL', false) ? 'https://' : 'http://') . ((isset($service['type']) && $service['type'] == 'domain' && $mailBlogName !== '') ? $mailBlogName . '.' : '') . $mailDomain . $service['path'];	// Textcube-NG: 서비스 주소가 //host 이면 useSSL 기준 스킴을 붙인다(빈 값일 때의 기본 분기와 같은 규칙)(r3-34)
	$blogURL = getBlogURLById($blogid, $mailDomain);

	//$headers = "From: Your Textcube Blog <textcube@{$service['domain']}>\n" . 'X-Mailer: ' . TEXTCUBE_NAME . "\n" . "MIME-Version: 1.0\nContent-Type: text/html; charset=utf-8\n";
	$message = file_get_contents(ROOT . "/resources/style/letter/letter.html");
	$message = str_replace('[##_title_##]', _text('텍스트큐브 블로그 로그인 정보'), $message);
	$message = str_replace('[##_content_##]', _text('블로그 로그인을 위한 임시 암호가 생성 되었습니다. 이 이메일에 로그인할 수 있는 인증 정보가 포함되어 있습니다.'), $message);
	$message = str_replace('[##_images_##]', $mailServiceURL."/resources/style/letter", $message);
	$message = str_replace('[##_link_##]', getInvitationLink($blogURL, $email, null, $authtoken), $message);
	$message = str_replace('[##_link_title_##]', _text('여기를 클릭하시면 로그인하여 암호를 변경하실 수 있습니다.'), $message);
	$message = str_replace('[##_sender_##]', '', $message);
	$ret = sendEmail('Your Textcube Blog',"textcube@{$service['domain']}",'',$email, encodeMail(_text('블로그 로그인 암호가 초기화되었습니다.')), $message );
	if (true !== $ret) {
		Auth::removeAuthToken($userid, $authtoken);
		return false;
	}
	return true;
}
?>
