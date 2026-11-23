<?php
/// Copyright (c) 2004-2016, Needlworks  / Tatter Network Foundation
/// All rights reserved. Licensed under the GPL.
/// See the GNU General Public License for more details. (/documents/LICENSE, /documents/COPYRIGHT)

/// @brief PHP 5.5~7.3 compatibility layer (Textcube-NG php5.5 port, r4-4).
/* Textcube-NG: php5.5 이식본은 PHP 5.5~7.3 에서 같은 트리로 돈다. 이 파일은 framework/boot/ 에서 가장 먼저 적재된다
   (library/preprocessor.php·setup.php 가 boot 파일 경로를 정렬해 require — '00-C' < '00-U').
   - PHP 5.5~7.1 에 없는 함수·상수를 같은 동작으로 채운다. 이미 있으면 정의하지 않으므로 7.x 에서는 내장 함수를 그대로 쓴다.
   - 판에 따라 동작이 달라지는 곳은 공통 함수로 모은다: safeUnserialize()·jsonEncodeCompat()·getTlsStreamOptions()·
     getTlsCurlOptions()·getTlsCryptoClientMethod().
   - PHP 5.5 문법만 쓴다(??·가변 인자·스칼라/반환 타입·익명 클래스 없음). 7.4/8 에서 deprecated 인 문법도 쓰지 않는다. (r4-4) */

/// 문자열 바이트 길이·부분 문자열 — mbstring.func_overload 가 strlen/substr 를 바꿔 놓은 서버에서도 바이트로 센다
function __compatStrlen8($s) {
	static $overload = null;
	if ($overload === null) $overload = function_exists('mb_strlen') && ((int)ini_get('mbstring.func_overload') & 2);
	return $overload ? mb_strlen($s, '8bit') : strlen($s);
}
function __compatSubstr8($s, $start, $length) {
	static $overload = null;
	if ($overload === null) $overload = function_exists('mb_substr') && ((int)ini_get('mbstring.func_overload') & 2);
	return $overload ? mb_substr($s, $start, $length, '8bit') : substr($s, $start, $length);
}

/***** 함수 폴리필(5.6·7.0) *****/
if (!function_exists('random_bytes')) {
	/* Textcube-NG: PHP 7.0 random_bytes() — 세션·CSRF·비밀번호 재설정 토큰에 쓰는 CSPRNG. 난수원 순서(사용자 결정 2026-11-22):
	   /dev/urandom(문자 장치일 때만) → mcrypt_create_iv(MCRYPT_DEV_URANDOM) → 둘 다 안 되면 예외(토큰 발급 거부, 약한 난수로 내려가지 않음).
	   openssl_random_pseudo_bytes 는 쓰지 않는다(random_compat 2.x 와 같음). PHP 7 의 Error/TypeError 대신 Exception 을 던진다. (r4-4) */
	function random_bytes($length) {
		$length = (int)$length;
		if ($length < 1) throw new Exception('Length must be greater than 0');
		static $fp = null;
		if ($fp === null) {
			$fp = false;
			if (DIRECTORY_SEPARATOR === '/' && @is_readable('/dev/urandom')) {
				$h = @fopen('/dev/urandom', 'rb');
				if ($h !== false) {
					$st = @fstat($h);
					if (is_array($st) && ($st['mode'] & 0170000) === 0020000) {	// 문자 장치(바꿔치기한 일반 파일 거부)
						if (function_exists('stream_set_read_buffer')) @stream_set_read_buffer($h, 0);
						$fp = $h;
					} else {
						@fclose($h);
					}
				}
			}
		}
		if ($fp !== false) {
			$buf = '';
			$remaining = $length;
			while ($remaining > 0) {
				$chunk = @fread($fp, $remaining);
				if (!is_string($chunk) || $chunk === '') break;
				$buf .= $chunk;
				$remaining = $length - __compatStrlen8($buf);
			}
			if (__compatStrlen8($buf) === $length) return $buf;
		}
		if (function_exists('mcrypt_create_iv') && defined('MCRYPT_DEV_URANDOM')) {
			$buf = @mcrypt_create_iv($length, MCRYPT_DEV_URANDOM);
			if (is_string($buf) && __compatStrlen8($buf) === $length) return $buf;
		}
		throw new Exception('Could not gather sufficient random data');
	}
}

if (!function_exists('random_int')) {
	/* Textcube-NG: PHP 7.0 random_int() — random_bytes() 위에서 거부 표본추출(편향 없음). (r4-4) */
	function random_int($min, $max) {
		$min = (int)$min;
		$max = (int)$max;
		if ($min > $max) throw new Exception('Minimum value must be less than or equal to the maximum value');
		if ($min === $max) return $min;
		$range = $max - $min;
		if (!is_int($range)) {	// 범위가 PHP_INT_MAX 를 넘음(정수 전체 범위에 가까움) — 정수 전체에서 뽑아 범위 안일 때만
			do {
				$r = random_bytes(PHP_INT_SIZE);
				$v = 0;
				for ($i = 0; $i < PHP_INT_SIZE; $i++) $v = ($v << 8) | ord($r[$i]);
			} while ($v < $min || $v > $max);
			return $v;
		}
		$bits = 0;
		for ($t = $range; $t > 0; $t >>= 1) $bits++;
		$bytes = (int)(($bits + 7) / 8);
		$mask = ($bits >= PHP_INT_SIZE * 8 - 1) ? PHP_INT_MAX : ((1 << $bits) - 1);
		do {
			$r = random_bytes($bytes);
			$v = 0;
			for ($i = 0; $i < $bytes; $i++) $v = ($v << 8) | ord($r[$i]);
			$v &= $mask;
		} while ($v > $range);
		return $min + $v;
	}
}

if (!function_exists('hash_equals')) {
	/* Textcube-NG: PHP 5.6 hash_equals() — 길이가 같을 때 XOR 누적으로 비교(타이밍 공격 방어). 문자열이 아니면 7.x 처럼 경고와 false
	   (사용자 공간에서는 E_WARNING 을 낼 수 없어 E_USER_WARNING). (r4-4) */
	function hash_equals($known_string, $user_string) {
		if (!is_string($known_string)) {
			trigger_error('hash_equals(): Expected known_string to be a string, ' . gettype($known_string) . ' given', E_USER_WARNING);
			return false;
		}
		if (!is_string($user_string)) {
			trigger_error('hash_equals(): Expected user_string to be a string, ' . gettype($user_string) . ' given', E_USER_WARNING);
			return false;
		}
		$len = __compatStrlen8($known_string);
		if ($len !== __compatStrlen8($user_string)) return false;
		$r = 0;
		for ($i = 0; $i < $len; $i++) $r |= ord($known_string[$i]) ^ ord($user_string[$i]);
		return $r === 0;
	}
}

if (!function_exists('intdiv')) {
	/* Textcube-NG: PHP 7.0 intdiv() — 0 으로 나누기·PHP_INT_MIN/-1 은 예외(7.x 의 DivisionByZeroError·ArithmeticError 대신 Exception). (r4-4) */
	function intdiv($dividend, $divisor) {
		$dividend = (int)$dividend;
		$divisor = (int)$divisor;
		if ($divisor === 0) throw new Exception('Division by zero');
		if ($divisor === -1 && $dividend === ~PHP_INT_MAX) throw new Exception('Division of PHP_INT_MIN by -1 is not an integer');
		return (int)(($dividend - $dividend % $divisor) / $divisor);
	}
}

/***** 상수(7.0·7.1·7.2) *****/
if (!defined('PHP_INT_MIN')) define('PHP_INT_MIN', ~PHP_INT_MAX);	// 7.0
if (!defined('IMAGETYPE_WEBP')) define('IMAGETYPE_WEBP', 18);	// 7.1 (5.x getimagesize() 는 WebP 를 알아보지 못한다)
if (!defined('JSON_INVALID_UTF8_SUBSTITUTE')) define('JSON_INVALID_UTF8_SUBSTITUTE', 0);	// 7.2 — 7.2 미만은 jsonEncodeCompat() 가 미리 치환
/* TOKEN_PARSE 는 정의하지 않는다 — 5.x token_get_all() 은 인자를 하나만 받으므로 상수가 있으면 잘못 넘기게 된다
   (서버 설정 저장 updateConfigFileSource() 는 PHP_VERSION_ID 로 나눈다). */

/// 5.5 는 default_charset 기본값이 비어 있다(5.6+ 는 UTF-8) — 응답 charset 누락을 막는다
if ((string)ini_get('default_charset') === '') @ini_set('default_charset', 'UTF-8');
/// 5.x 는 date.timezone 이 비어 있으면 date() 등마다 E_WARNING("It is not safe to rely on the system's timezone settings")을 낸다.
/// 7.0+ 는 경고 없이 UTC 를 쓴다 — 같은 동작으로 맞춘다(블로그 시간대는 뒤에서 Timezone::set() 이 정한다, r4-4 F1)
if (PHP_VERSION_ID < 70000 && (string)ini_get('date.timezone') === '') @date_default_timezone_set('UTC');

/***** unserialize 객체 주입 방어 *****/
if (!function_exists('safeUnserialize')) {
	/* Textcube-NG: PHP 7.0 의 unserialize($s, array('allowed_classes' => false)) 대신(r3-43 R-577 객체 주입 방어).
	   7.0+ 는 그대로 부른다. 5.x 는 직렬화 문법을 따라 걸으며(문자열은 길이로 건너뜀) 객체(O:·C:)·enum(E:)·이스케이프 문자열(S:)이
	   하나라도 있거나 형식이 틀리면 false 를 돌려주고, 없을 때만 unserialize() 한다.
	   차이: 7.x 는 객체를 __PHP_Incomplete_Class 로 바꿔 돌려주고 5.x 는 false 다(설정·캐시에는 객체가 없다). (r4-4) */
	function safeUnserialize($s) {
		if (PHP_VERSION_ID >= 70000) return unserialize($s, array('allowed_classes' => false));
		if (is_array($s) || is_object($s)) return false;
		$s = (string)$s;
		$len = __compatStrlen8($s);
		$p = 0;
		if (!__compatSerializedNoObject($s, $p, $len, 0, false) || $p !== $len) return false;
		return unserialize($s);
	}
	function __compatSerializedNoObject($s, &$p, $len, $depth, $isKey) {
		if ($p >= $len || $depth > 512) return false;
		$c = $s[$p];
		if ($isKey && $c !== 'i' && $c !== 's') return false;
		$m = null;
		switch ($c) {
			case 'N':
				if (__compatSubstr8($s, $p, 2) !== 'N;') return false;
				$p += 2;
				return true;
			case 'b':
				if (!preg_match('/\Gb:[01];/', $s, $m, 0, $p)) return false;
				$p += 4;
				return true;
			case 'i':
				if (!preg_match('/\Gi:[+-]?[0-9]+;/', $s, $m, 0, $p)) return false;
				$p += __compatStrlen8($m[0]);
				return true;
			case 'd':
				if (!preg_match('/\Gd:(?:[+-]?(?:[0-9]+(?:\.[0-9]*)?|\.[0-9]+)(?:[eE][+-]?[0-9]+)?|NAN|-?INF);/', $s, $m, 0, $p)) return false;
				$p += __compatStrlen8($m[0]);
				return true;
			case 's':
				if (!preg_match('/\Gs:([0-9]+):"/', $s, $m, 0, $p)) return false;
				if (__compatStrlen8($m[1]) > 18) return false;	// 길이 숫자가 정수 범위를 넘으면 형식 오류
				$n = (int)$m[1];
				$q = $p + __compatStrlen8($m[0]);
				if ($n > $len - $q - 2) return false;
				if (__compatSubstr8($s, $q + $n, 2) !== '";') return false;
				$p = $q + $n + 2;
				return true;
			case 'r':
			case 'R':
				if (!preg_match('/\G[rR]:[0-9]+;/', $s, $m, 0, $p)) return false;
				$p += __compatStrlen8($m[0]);
				return true;
			case 'a':
				if (!preg_match('/\Ga:([0-9]+):\{/', $s, $m, 0, $p)) return false;
				if (__compatStrlen8($m[1]) > 18) return false;
				$n = (int)$m[1];
				$p += __compatStrlen8($m[0]);
				for ($k = 0; $k < $n; $k++) {
					if (!__compatSerializedNoObject($s, $p, $len, $depth + 1, true)) return false;
					if (!__compatSerializedNoObject($s, $p, $len, $depth + 1, false)) return false;
				}
				if ($p >= $len || $s[$p] !== '}') return false;
				$p++;
				return true;
		}
		return false;	// O:·C:·E:·S:·o: 와 그 밖의 형식
	}
}

/***** json_encode 의 잘못된 UTF-8 처리 *****/
if (!function_exists('jsonEncodeCompat')) {
	/* Textcube-NG: json_encode($v, $flags | JSON_INVALID_UTF8_SUBSTITUTE)(7.2+) 와 같은 결과. 7.2 미만은 문자열(배열 키·stdClass 속성 이름
	   포함)의 잘못된 UTF-8 을 PHP 7.2 json 인코더와 같은 규칙(php_next_utf8_char 의 최대 부분열 단위)으로 U+FFFD 로 바꾼 뒤
	   json_encode 한다(사용자 결정 2026-11-22 "공통 함수로 감쌈"). (r4-4) */
	function jsonEncodeCompat($value, $flags = 0) {
		if (PHP_VERSION_ID >= 70200) return json_encode($value, $flags | JSON_INVALID_UTF8_SUBSTITUTE);
		return json_encode(__compatUtf8Substitute($value, 0), $flags);
	}
	function __compatUtf8Substitute($v, $depth) {
		if (is_string($v)) return __compatUtf8SubstituteString($v);
		if ($depth > 512) return $v;
		if (is_array($v)) {
			$out = array();
			foreach ($v as $k => $x) $out[is_string($k) ? __compatUtf8SubstituteString($k) : $k] = __compatUtf8Substitute($x, $depth + 1);
			return $out;
		}
		if ($v instanceof stdClass) {
			$out = array();
			foreach (get_object_vars($v) as $k => $x) $out[__compatUtf8SubstituteString((string)$k)] = __compatUtf8Substitute($x, $depth + 1);
			return (object)$out;
		}
		return $v;
	}
	function __compatUtf8SubstituteString($s) {
		if ($s === '' || preg_match('//u', $s)) return $s;	// 올바른 UTF-8
		$len = __compatStrlen8($s);
		$out = '';
		$p = 0;
		while ($p < $len) {
			$c = ord($s[$p]);
			if ($c < 0x80) { $out .= $s[$p]; $p++; continue; }
			$n = __compatUtf8Next($s, $len, $p, $c);
			if ($n > 0) { $out .= __compatSubstr8($s, $p, $n); $p += $n; }
			else { $out .= "\xEF\xBF\xBD"; $p += -$n; }
		}
		return $out;
	}
	/// php_next_utf8_char()(ext/standard/html.c) 와 같은 판정 — 올바르면 바이트 수(양수), 아니면 건너뛸 바이트 수(음수)
	function __compatUtf8Next($s, $len, $p, $c) {
		$avail = $len - $p;
		$b1 = ($avail >= 2) ? ord($s[$p + 1]) : -1;
		$b2 = ($avail >= 3) ? ord($s[$p + 2]) : -1;
		$b3 = ($avail >= 4) ? ord($s[$p + 3]) : -1;
		if ($c < 0xC2) return -1;
		if ($c < 0xE0) {
			if ($avail < 2 || !__compatUtf8Trail($b1)) return ($avail < 2 || __compatUtf8Lead($b1)) ? -1 : -2;
			return 2;
		}
		if ($c < 0xF0) {
			if ($avail < 3 || !__compatUtf8Trail($b1) || !__compatUtf8Trail($b2)) {
				if ($avail < 2 || __compatUtf8Lead($b1)) return -1;
				if ($avail < 3 || __compatUtf8Lead($b2)) return -2;
				return -3;
			}
			$cp = (($c & 0x0F) << 12) | (($b1 & 0x3F) << 6) | ($b2 & 0x3F);
			if ($cp < 0x800 || ($cp >= 0xD800 && $cp <= 0xDFFF)) return -3;
			return 3;
		}
		if ($c < 0xF5) {
			if ($avail < 4 || !__compatUtf8Trail($b1) || !__compatUtf8Trail($b2) || !__compatUtf8Trail($b3)) {
				if ($avail < 2 || __compatUtf8Lead($b1)) return -1;
				if ($avail < 3 || __compatUtf8Lead($b2)) return -2;
				if ($avail < 4 || __compatUtf8Lead($b3)) return -3;
				return -4;
			}
			$cp = (($c & 0x07) << 18) | (($b1 & 0x3F) << 12) | (($b2 & 0x3F) << 6) | ($b3 & 0x3F);
			if ($cp < 0x10000 || $cp > 0x10FFFF) return -4;
			return 4;
		}
		return -1;
	}
	function __compatUtf8Lead($b) { return $b < 0x80 || ($b >= 0xC2 && $b <= 0xF4); }
	function __compatUtf8Trail($b) { return $b >= 0x80 && $b <= 0xBF; }
}

/***** TLS(인증서 검증) *****/
if (!function_exists('getTlsStreamOptions')) {
	/* Textcube-NG: 스트림 ssl 컨텍스트 옵션. 5.6+ 는 7.2 트리와 같은 값(verify_peer·verify_peer_name·peer_name·SNI — PHP 기본 CA).
	   5.5 는 기본값이 '검증 안 함'·이름 검사 옵션 없음·시스템 CA 기본값 없음이라(RL1 4절 실측) verify_peer + CN_match + SNI_server_name +
	   CA(config tls_cafile/tls_capath → 흔한 시스템 경로)를 넣는다. CA 를 못 찾으면 CA 없이 검증하므로 연결이 실패한다(안전한 쪽,
	   사용자 결정 2026-11-22 "시스템 경로 탐지+설정"). 5.5 의 CN_match 는 인증서 CN 만 보고 subjectAltName 은 보지 않는다. (r4-4) */
	function getTlsStreamOptions($host, $extra = array()) {
		$o = array('verify_peer' => true, 'SNI_enabled' => true);
		if (PHP_VERSION_ID >= 50600) {
			$o['verify_peer_name'] = true;
			$o['peer_name'] = $host;
		} else {
			$o['CN_match'] = $host;
			$o['SNI_server_name'] = $host;
		}
		$ca = getTlsCaLocation();
		if ($ca !== null) $o[$ca[0]] = $ca[1];
		foreach ((array)$extra as $k => $v) $o[$k] = $v;
		return $o;
	}
	/// CA 위치: array('cafile'|'capath', 경로) 또는 null(5.6+ 는 PHP·OpenSSL 기본값을 쓴다)
	function getTlsCaLocation() {
		global $service;
		if (isset($service['tls_cafile']) && is_string($service['tls_cafile']) && $service['tls_cafile'] !== '') return array('cafile', $service['tls_cafile']);
		if (isset($service['tls_capath']) && is_string($service['tls_capath']) && $service['tls_capath'] !== '') return array('capath', $service['tls_capath']);
		if (PHP_VERSION_ID >= 50600) return null;
		static $found = false;
		if ($found !== false) return $found;
		$files = array('/etc/ssl/certs/ca-certificates.crt', '/etc/pki/tls/certs/ca-bundle.crt', '/etc/pki/ca-trust/extracted/pem/tls-ca-bundle.pem',
			'/etc/ssl/ca-bundle.pem', '/etc/pki/tls/cacert.pem', '/etc/ssl/cert.pem', '/usr/local/share/certs/ca-root-nss.crt',
			'/usr/local/etc/openssl/cert.pem', '/usr/share/ssl/certs/ca-bundle.crt');
		foreach ($files as $f) {
			if (@is_file($f) && @is_readable($f)) return $found = array('cafile', $f);
		}
		foreach (array('/etc/ssl/certs', '/etc/pki/tls/certs') as $d) {
			if (@is_dir($d) && @is_readable($d)) return $found = array('capath', $d);
		}
		error_log("Textcube-NG: TLS CA bundle not found - set \$service['tls_cafile'] in config.php (HTTPS certificate verification will fail)");
		return $found = null;
	}
	/// curl: config 의 CA 를 지정했을 때만 넘긴다(지정하지 않으면 libcurl 기본 CA — 7.2 트리와 같음)
	function getTlsCurlOptions() {
		global $service;
		$o = array();
		if (isset($service['tls_cafile']) && is_string($service['tls_cafile']) && $service['tls_cafile'] !== '') $o[CURLOPT_CAINFO] = $service['tls_cafile'];
		if (isset($service['tls_capath']) && is_string($service['tls_capath']) && $service['tls_capath'] !== '') $o[CURLOPT_CAPATH] = $service['tls_capath'];
		return $o;
	}
	/// STARTTLS(stream_socket_enable_crypto) 방식 — 7.2+ 는 TLS_CLIENT(1.0~1.2) 그대로, 5.6~7.1 은 TLS_CLIENT 가 1.0 만이라 1.0|1.1|1.2,
	/// 5.5 는 TLSv1.x 개별 상수가 없어 SSLv23_CLIENT(OpenSSL 이 TLS 1.2 까지 협상)
	function getTlsCryptoClientMethod() {
		if (PHP_VERSION_ID >= 70200) return STREAM_CRYPTO_METHOD_TLS_CLIENT;
		if (defined('STREAM_CRYPTO_METHOD_TLSv1_2_CLIENT'))
			return STREAM_CRYPTO_METHOD_TLSv1_0_CLIENT | STREAM_CRYPTO_METHOD_TLSv1_1_CLIENT | STREAM_CRYPTO_METHOD_TLSv1_2_CLIENT;
		return STREAM_CRYPTO_METHOD_SSLv23_CLIENT;
	}
}
?>
