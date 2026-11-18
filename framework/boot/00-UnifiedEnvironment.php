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

/// @brief Environment unifier.
ini_set('session.use_trans_sid', '0');
if (intval(ini_get("session.auto_start")) == 1) {
   @session_destroy();
   @ini_set('session.auto_start', '0');
}
if (intval(ini_get("memory_limit")) < 24)
	@ini_set('memory_limit','24M');
// get_magic_quotes_gpc() was deprecated in PHP 7.4 and removed in PHP 8.0; set_magic_quotes_runtime() was removed in PHP 7.0.
// Equivalent replacement: apply null-byte normalization to all superglobals,
// preserving the original routine structure. (php.net/migration70.incompatible)
function normalizeSuperglobalInput($value) {
	if (is_array($value))
		return array_map('normalizeSuperglobalInput', $value);
	elseif (is_string($value))
		return str_replace(chr(0), '', $value);
	else
		return $value;
}

$_GET     = array_map('normalizeSuperglobalInput', $_GET);
$_POST    = array_map('normalizeSuperglobalInput', $_POST);
$_COOKIE  = array_map('normalizeSuperglobalInput', $_COOKIE);
$_ENV     = array_map('normalizeSuperglobalInput', $_ENV);
//$_FILES = array_map('normalizeSuperglobalInput', $_FILES); // binary content, intentionally skipped
$_REQUEST = array_map('normalizeSuperglobalInput', $_REQUEST);
$_SERVER  = array_map('normalizeSuperglobalInput', $_SERVER);

if (!isset($_SERVER['REQUEST_TIME']))
	$_SERVER['REQUEST_TIME'] = time();

// Textcube-NG: Host 헤더가 없는 요청(HTTP/1.0, HTTP/3 에서 웹 서버가 :authority 를 HTTP_HOST 로 넘기지 않을 때)은 HTTP_HOST 가 없어
// PHP 8 경고(Undefined array key)와 null 인자 비권장 경고(PHP 8.1+, explode()·SQLite3::escapeString())가 났다. 원본에서 null 이던
// 값과 같은 뜻의 '' 로 둔다(블로그 판정은 원본과 같음 — SERVER_NAME 등으로 대체하면 domain 멀티블로그에서 다른 블로그로 판정될 수 있음)(v3.140)
if (!isset($_SERVER['HTTP_HOST']))
	$_SERVER['HTTP_HOST'] = '';
// Textcube-NG: Host 헤더 검사 — 원본은 ':' 로 나눈 첫 조각을 Host, 둘째 조각을 포트로 써서 문자를 검사하지 않았고 IPv6 주소([::1]:80)는
// 잘못 나뉘었다. RFC 3986 의 reg-name(비예약 문자·퍼센트 인코딩·sub-delims)·IPv6 리터럴과 포트(빈 값 또는 1~65535)만 받고
// 그 밖은 400 으로 끝낸다. 빈 Host 와 'host:'(빈 포트)는 지금처럼 둔다(v3.205)
$host = $_SERVER['HTTP_HOST'];
$hostPort = null;
if ($host !== '' && $host[0] === '[') {
	$hostEnd = strpos($host, ']');
	$hostValid = ($hostEnd !== false && filter_var(substr($host, 1, $hostEnd - 1), FILTER_VALIDATE_IP, FILTER_FLAG_IPV6) !== false);
	$hostName = ($hostEnd !== false) ? substr($host, 0, $hostEnd + 1) : $host;
	$hostRest = ($hostEnd !== false) ? substr($host, $hostEnd + 1) : '';
} else {
	$hostEnd = strpos($host, ':');
	$hostName = ($hostEnd !== false) ? substr($host, 0, $hostEnd) : $host;
	$hostRest = ($hostEnd !== false) ? substr($host, $hostEnd) : '';
	$hostValid = (preg_match('/^(?:[A-Za-z0-9\-._~!$&\'()*+,;=]|%[0-9A-Fa-f]{2})*$/', $hostName) === 1);
}
if ($hostValid && $hostRest !== '') {
	if ($hostRest[0] !== ':') $hostValid = false;
	else {
		$hostPort = substr($hostRest, 1);
		if ($hostPort !== '' && !(ctype_digit($hostPort) && intval($hostPort) >= 1 && intval($hostPort) <= 65535)) $hostValid = false;
	}
}
if (!$hostValid) {
	header('HTTP/1.1 400 Bad Request');
	exit;
}
$_SERVER['HTTP_HOST'] = $hostName;
if (!is_null($hostPort)) $_SERVER['SERVER_PORT'] = $hostPort;
unset($host, $hostEnd, $hostName, $hostRest, $hostPort, $hostValid);

/* [보안] 클라이언트 IP 결정: 원본은 Client-IP / X-Forwarded-For 요청 헤더 값을 검사 없이 REMOTE_ADDR 로 덮어써
   클라이언트가 보낸 임의 문자열이 세션·방문 통계 SQL, IP 필터(차단), 댓글·트랙백 IP 로 전달되었다.
   이 헤더는 접속 주소(REMOTE_ADDR)가 config.php 의 $service['trustedProxies'](신뢰 프록시 IP 또는 CIDR 배열,
   설치 시 127.0.0.1·서버 주소 기록, 항목이 없으면 빈 배열)에 속할 때만 사용하고 유효한 IP 형식(FILTER_VALIDATE_IP)만 채택한다. X-Forwarded-For 는 오른쪽
   (가장 가까운 프록시가 추가한 값)부터 신뢰 프록시를 건너뛴 첫 주소를 사용하고, 없을 때만 Client-IP 를 사용한다. */
function isTrustedProxyAddress($address, $trustedProxies) {
	$bin = @inet_pton((string)$address);
	if ($bin === false) return false;
	foreach ((array)$trustedProxies as $entry) {
		$entry = explode('/', trim((string)$entry), 2);
		$net = @inet_pton($entry[0]);
		if ($net === false || strlen($net) !== strlen($bin)) continue;
		$bits = strlen($bin) * 8;
		if (isset($entry[1])) {
			if (!ctype_digit($entry[1]) || (int)$entry[1] > $bits) continue;
			$bits = (int)$entry[1];
		}
		$bytes = intdiv($bits, 8);
		if (substr($bin, 0, $bytes) !== substr($net, 0, $bytes)) continue;
		if ($bits % 8 && ((ord($bin[$bytes]) ^ ord($net[$bytes])) & (0xFF << (8 - $bits % 8)) & 0xFF)) continue;
		return true;
	}
	return false;
}
if (!empty($GLOBALS['service']['trustedProxies']) && isset($_SERVER['REMOTE_ADDR'])
	&& isTrustedProxyAddress($_SERVER['REMOTE_ADDR'], $GLOBALS['service']['trustedProxies'])) {
	/* [보안] config.php trustedProxies 에 속한 연결 — URIHandler 는 이 연결에서만 X-Forwarded-Proto 를 읽는다 (v3.151) */
	define('__TEXTCUBE_TRUSTED_PEER__', true);
	$clientAddress = null;
	if (isset($_SERVER['HTTP_X_FORWARDED_FOR'])) {
		foreach (array_reverse(explode(',', $_SERVER['HTTP_X_FORWARDED_FOR'])) as $hop) {
			$hop = trim($hop);
			if (filter_var($hop, FILTER_VALIDATE_IP) === false) break;
			$clientAddress = $hop;
			if (!isTrustedProxyAddress($hop, $GLOBALS['service']['trustedProxies'])) break;
		}
	} else if (isset($_SERVER['HTTP_CLIENT_IP']) && filter_var(trim($_SERVER['HTTP_CLIENT_IP']), FILTER_VALIDATE_IP) !== false) {
		$clientAddress = trim($_SERVER['HTTP_CLIENT_IP']);
	}
	if ($clientAddress !== null) $_SERVER['REMOTE_ADDR'] = $clientAddress;
	unset($clientAddress, $hop);
}
/* Workaround for REMOTE_ADDR Handling of IPv6 */
if(in_array($_SERVER['REMOTE_ADDR'],array('fe80::1','::1'))) {
	$_SERVER['REMOTE_ADDR'] = '127.0.0.1';
}
/* Workaround for iconv-absent environment. (contributed by Papacha) */
if (!function_exists('iconv')) {
	if (function_exists('mb_convert_encoding')) {
		function iconv($in, $out, $str) {
			return mb_convert_encoding($str, $out, $in);
		}
	} else {
		include_once(ROOT . '/library/function/iconv.php');
	}
}
/* Workaround for NCR treatment. (contributed by Laziel) */
if (!function_exists('mb_decode_numericentity')) { 
	function mb_decode_numericentity($str, $dumb = null, $dumber = null) {
		if (!function_exists('_mb_decode_numericentity_callback') ) {
			function _mb_decode_numericentity_callback($t) {
				$decode = $t[1];
					if ($decode < 128) {
						$str = chr($decode);
					} else if ($decode < 2048) {
						$str = chr(192 + (($decode - ($decode % 64)) / 64));
						$str .= chr(128 + ($decode % 64));
					} else {
						$str = chr(224 + (($decode - ($decode % 4096)) / 4096));
						$str .= chr(128 + ((($decode % 4096) - ($decode % 64)) / 64));
						$str .= chr(128 + ($decode % 64));
					}
					return $str;
			}
		}
		return preg_replace_callback('/&#([0-9]{1,});/', '_mb_decode_numericentity_callback', $str);
	}
}
?>
