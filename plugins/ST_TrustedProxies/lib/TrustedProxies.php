<?php
/// Copyright (c) 2004-2016, Needlworks  / Tatter Network Foundation
/// All rights reserved. Licensed under the GPL.
/// See the GNU General Public License for more details. (/documents/LICENSE, /documents/COPYRIGHT)
///
/// ST_TrustedProxies 판정 코어(v3.151) — 접속 주소(REMOTE_ADDR)가 관리자가 등록한 신뢰 장치(로드밸런서·리버스 프록시)나
/// Cloudflare 대역에 있을 때만 그 장치가 붙인 헤더로 방문자 IP 와 스킴을 정한다. 그 밖의 연결은 현행대로 둔다.
/// library/preprocessor.php 가 DB 연결 뒤·URI 해석 전에 apply() 를 부른다. 설정은 ServiceSettings(전역 이름, 값은 JSON).
///
/// 주소는 normalizeAddress() 한 곳에서만 해석한다(IPv4·IPv6 128비트, 압축 표기·IPv4-mapped 정규화, zone id 거부).
/// REMOTE_ADDR 반영 여부는 canReplaceRemoteAddress() 한 곳에서만 정한다 — IP 컬럼이 varchar(15) 라 지금은 IPv4 만 반영하고,
/// IPv6 방문자는 context(proxy.client·proxy.status='ipv6')에만 남긴다(IPv6 저장은 추후 지원 예정).

if (!defined('ROOT')) {
	header('HTTP/1.1 403 Forbidden');
	header("Connection: close");
	exit;
}

class ST_TrustedProxies {
	const PLUGIN = 'ST_TrustedProxies';
	const HEADER_BYTES = 4096;		// 헤더 하나에서 읽는 최대 바이트(오른쪽부터)
	const HEADER_ITEMS = 32;		// 헤더 하나에서 읽는 최대 항목 수(오른쪽부터)
	const MAX_DEVICES = 16;
	const MAX_CIDRS = 64;			// 모든 장치의 CIDR 합계
	const LABEL_CHARS = 64;
	const CIDRS_CHARS = 2048;
	const CF_HOST = 'www.cloudflare.com';
	const CF_INTERVAL = 86400;		// 대역 받아 오기는 롤링 24시간에 한 번(실패한 시도 포함)
	const CF_HISTORY = 10;
	const CF_RESPONSE_BYTES = 65536;
	const CF_BODY_BYTES = 16384;
	const CF_MAX_ITEMS = 100;

	/// 내장 Cloudflare 대역(2026-10 기준 https://www.cloudflare.com/ips-v4 · ips-v6). 받아 온 목록이 없거나 손상되면 쓴다.
	public static function builtinCloudflareRanges() {
		return array(
			'v4' => array('173.245.48.0/20', '103.21.244.0/22', '103.22.200.0/22', '103.31.4.0/22', '141.101.64.0/18',
				'108.162.192.0/18', '190.93.240.0/20', '188.114.96.0/20', '197.234.240.0/22', '198.41.128.0/17',
				'162.158.0.0/15', '104.16.0.0/13', '104.24.0.0/14', '172.64.0.0/13', '131.0.72.0/22'),
			'v6' => array('2400:cb00::/32', '2606:4700::/32', '2803:f800::/32', '2405:b500::/32', '2405:8100::/32',
				'2a06:98c0::/29', '2c0f:f248::/32'));
	}

	/// 주소 문자열 → array('bin' => 4/16바이트, 'text' => 정규형) 또는 null. 앞뒤 공백은 호출자가 지운다.
	/// zone id(%) 는 거부하고, IPv4-mapped(앞 10바이트 0 + ff ff)는 IPv4 로 바꾼다. IPv4-compatible(::a.b.c.d)은 IPv6 그대로.
	public static function normalizeAddress($address) {
		if (!is_string($address) || $address === '' || strlen($address) > 45 || strpos($address, '%') !== false)
			return null;
		if (filter_var($address, FILTER_VALIDATE_IP) === false) return null;
		$bin = @inet_pton($address);
		if ($bin === false || (strlen($bin) !== 4 && strlen($bin) !== 16)) return null;
		if (strlen($bin) === 16 && substr($bin, 0, 12) === str_repeat("\0", 10) . "\xff\xff")
			$bin = substr($bin, 12);
		$text = @inet_ntop($bin);
		if ($text === false) return null;
		return array('bin' => $bin, 'text' => $text);
	}

	/// REMOTE_ADDR 에 넣어도 되는 주소인지 — 이 함수 하나에서만 정한다. 지금은 IPv4 만(IP 컬럼 varchar(15)).
	/// IPv6 저장을 지원하게 되면(스키마 확장 뒤) 이 함수만 바꾼다.
	public static function canReplaceRemoteAddress($address) {
		$normalized = self::normalizeAddress($address);
		return $normalized !== null && strlen($normalized['bin']) === 4;
	}

	/// CIDR(또는 단일 주소) → array('bin', 'bits', 'text') 또는 null. 호스트 비트가 있거나 v4 /8·v6 /16 보다 넓으면 거부,
	/// IPv4-mapped 대역(::ffff:)은 거부.
	public static function parseCidr($cidr) {
		if (!is_string($cidr) || $cidr === '' || strlen($cidr) > 49) return null;
		$parts = explode('/', $cidr, 2);
		$address = self::normalizeAddress($parts[0]);
		if ($address === null) return null;
		$raw = @inet_pton($parts[0]);
		if ($raw === false || strlen($raw) !== strlen($address['bin'])) return null;
		$max = strlen($address['bin']) * 8;
		$bits = $max;
		if (isset($parts[1])) {
			if (!preg_match('/^(0|[1-9][0-9]{0,2})$/', $parts[1])) return null;
			$bits = intval($parts[1]);
			if ($bits > $max) return null;
		}
		if ($bits < ($max === 32 ? 8 : 16)) return null;
		if (self::maskBinary($address['bin'], $bits) !== $address['bin']) return null;
		return array('bin' => $address['bin'], 'bits' => $bits, 'text' => $address['text'] . '/' . $bits);
	}

	private static function maskBinary($bin, $bits) {
		$bytes = intdiv($bits, 8);
		$masked = substr($bin, 0, $bytes);
		if ($bits % 8) {
			$masked .= chr(ord($bin[$bytes]) & (0xFF << (8 - $bits % 8)) & 0xFF);
		}
		return str_pad($masked, strlen($bin), "\0");
	}

	private static function inRanges($bin, $ranges) {
		foreach ($ranges as $range) {
			if (strlen($range['bin']) !== strlen($bin)) continue;
			if (self::maskBinary($bin, $range['bits']) === $range['bin']) return true;
		}
		return false;
	}

	/// 체인 항목 분류: Cloudflare 대역 → 'cf', 장치 CIDR·config trustedProxies → 'trusted', 그 밖 → 'visitor'
	private static function classify($address, $devices, $cfRanges, $config) {
		if ($cfRanges !== null && self::inRanges($address['bin'], $cfRanges)) return 'cf';
		foreach ($devices as $device) {
			if (self::inRanges($address['bin'], $device['ranges'])) return 'trusted';
		}
		if (!empty($config) && function_exists('isTrustedProxyAddress') && isTrustedProxyAddress($address['text'], $config))
			return 'trusted';
		return 'visitor';
	}

	/// 판정(부수 효과 없음). $peer 는 boot 처리 뒤의 REMOTE_ADDR, $server 는 $_SERVER, $cfRanges 는 Cloudflare 가 꺼져 있으면 null.
	/// 반환: trusted(bool), status(untrusted/ok/ipv6/undetermined), peer, device, client, scheme, source
	public static function evaluate($peer, $server, $devices, $cfRanges, $config) {
		$result = array('trusted' => false, 'status' => 'untrusted', 'peer' => null, 'device' => null,
			'client' => null, 'scheme' => null, 'source' => null);
		$address = self::normalizeAddress($peer);
		if ($address === null) return $result;
		$result['peer'] = $address['text'];
		$device = null;
		if ($cfRanges === null || !self::inRanges($address['bin'], $cfRanges)) {
			foreach ($devices as $candidate) {
				if (self::inRanges($address['bin'], $candidate['ranges'])) {
					$device = $candidate;
					break;
				}
			}
			if ($device === null) return $result;
		}
		$result['trusted'] = true;
		if ($device === null) {
			// 접속 주소가 Cloudflare — CF-Connecting-IP·CF-Visitor 만 본다(고정)
			$result['device'] = 'Cloudflare';
			$client = self::cloudflareClient($server);
			$result['scheme'] = self::cloudflareScheme($server);
			$source = 'cf';
		} else {
			$result['device'] = $device['label'];
			$hop = self::deviceHop($server, $device, $devices, $cfRanges, $config);
			$client = $hop['client'];
			$result['scheme'] = $hop['scheme'];
			$source = $hop['source'];
		}
		if ($client === null) {
			$result['status'] = 'undetermined';
			return $result;
		}
		$result['client'] = $client;
		$result['source'] = $source;
		$result['status'] = self::canReplaceRemoteAddress($client) ? 'ok' : 'ipv6';
		return $result;
	}

	private static function headerValue($server, $name) {
		return (isset($server[$name]) && is_string($server[$name])) ? $server[$name] : null;
	}

	private static function cloudflareClient($server) {
		$value = self::headerValue($server, 'HTTP_CF_CONNECTING_IP');
		if ($value === null) return null;
		$address = self::normalizeAddress(trim($value));
		return $address === null ? null : $address['text'];
	}

	private static function cloudflareScheme($server) {
		$value = self::headerValue($server, 'HTTP_CF_VISITOR');
		if ($value === null || strlen($value) > self::HEADER_BYTES) return null;
		$visitor = json_decode($value, true);
		if (!is_array($visitor) || !isset($visitor['scheme']) || !is_string($visitor['scheme'])) return null;
		return ($visitor['scheme'] === 'http' || $visitor['scheme'] === 'https') ? $visitor['scheme'] : null;
	}

	/// 일반 장치: 스위치가 켜진 Forwarded·XFF 를 각각 오른쪽부터 읽고, 둘 다 없을 때만 X-Real-IP. 스위치가 꺼진 헤더는 없는 것으로 본다.
	private static function deviceHop($server, $device, $devices, $cfRanges, $config) {
		$switch = $device['h'];
		$walks = array();
		$value = self::headerValue($server, 'HTTP_FORWARDED');
		if ($switch['fwd'] && $value !== null)
			$walks['forwarded'] = self::walk(self::forwardedItems($value), $devices, $cfRanges, $config);
		$value = self::headerValue($server, 'HTTP_X_FORWARDED_FOR');
		if ($switch['xff'] && $value !== null)
			$walks['xff'] = self::walk(self::forwardedForItems($value), $devices, $cfRanges, $config);
		if (empty($walks)) {
			$value = self::headerValue($server, 'HTTP_X_REAL_IP');
			if ($switch['xri'] && $value !== null) {
				$address = (strpos($value, ',') === false) ? self::normalizeAddress(trim($value)) : null;
				$walks['x-real-ip'] = self::walk(array($address === null ? null : array('address' => $address, 'proto' => null)),
					$devices, $cfRanges, $config);
			}
		}
		$client = null;
		$reachedCloudflare = false;
		$determined = !empty($walks);
		foreach ($walks as $walk) {
			if ($walk === null) {
				$determined = false;
				continue;
			}
			if ($walk['cf']) {
				$reachedCloudflare = true;
				$walkClient = self::cloudflareClient($server);
			} else {
				$walkClient = $walk['client'];
			}
			if ($walkClient === null || ($client !== null && $client !== $walkClient)) $determined = false;
			if ($client === null) $client = $walkClient;
		}
		if ($reachedCloudflare) {
			$scheme = self::cloudflareScheme($server);
			$source = 'cf';
		} else {
			$forwardedProto = (isset($walks['forwarded']) && $walks['forwarded'] !== null) ? $walks['forwarded']['item']['proto'] : null;
			$forwardedProtoHeader = $switch['xfp'] ? self::forwardedProtoHeader($server) : null;
			if ($forwardedProto !== null && $forwardedProtoHeader !== null && $forwardedProto !== $forwardedProtoHeader) $scheme = null;
			else $scheme = ($forwardedProto !== null) ? $forwardedProto : $forwardedProtoHeader;
			$source = implode(',', array_keys($walks));
		}
		return array('client' => $determined ? $client : null, 'scheme' => $scheme, 'source' => $source);
	}

	/// 오른쪽(가장 가까운 홉)부터 읽는다. 신뢰 항목은 건너뛰고, Cloudflare 를 만나면 CF 홉, 그 밖의 첫 주소가 방문자.
	/// 모두 신뢰면 가장 왼쪽. 불량·판단 불가 항목(null)이나 HEADER_ITEMS 를 넘는 항목에 닿으면 null(판단 불가).
	private static function walk($items, $devices, $cfRanges, $config) {
		$last = null;
		foreach ($items as $index => $item) {
			if ($index >= self::HEADER_ITEMS || $item === null) return null;
			$class = self::classify($item['address'], $devices, $cfRanges, $config);
			if ($class === 'cf') return array('cf' => true, 'client' => null, 'item' => $item);
			if ($class === 'visitor') return array('cf' => false, 'client' => $item['address']['text'], 'item' => $item);
			$last = $item;
		}
		if ($last === null) return null;
		return array('cf' => false, 'client' => $last['address']['text'], 'item' => $last);
	}

	/// X-Forwarded-For → 오른쪽부터의 항목 목록. HEADER_BYTES 를 넘으면 오른쪽 HEADER_BYTES 만 읽고 잘린 맨 왼쪽 조각은 불량(null) —
	/// 방문자가 왼쪽에 덧붙인 패딩이 오른쪽(장치가 붙인) 판정을 막지 못하게 한다. 포트·대괄호·빈 항목은 불량.
	private static function forwardedForItems($value) {
		$truncated = strlen($value) > self::HEADER_BYTES;
		if ($truncated) $value = substr($value, -self::HEADER_BYTES);
		$parts = array_reverse(explode(',', $value));
		$last = count($parts) - 1;
		$items = array();
		foreach ($parts as $index => $part) {
			if ($index > self::HEADER_ITEMS) break;
			if ($truncated && $index === $last) {
				$items[] = null;
				break;
			}
			$address = self::normalizeAddress(trim($part));
			$items[] = ($address === null) ? null : array('address' => $address, 'proto' => null);
		}
		return $items;
	}

	/// Forwarded(RFC 7239) → 오른쪽부터의 항목 목록. 요소는 따옴표 밖의 ',' 로 나눈다(오른쪽 끝은 늘 요소 경계라 오른쪽부터 센다).
	/// 잘린 맨 왼쪽 조각·짝이 맞지 않는 따옴표가 든 조각은 불량(null).
	private static function forwardedItems($value) {
		$truncated = strlen($value) > self::HEADER_BYTES;
		if ($truncated) $value = substr($value, -self::HEADER_BYTES);
		$items = array();
		$end = strlen($value);
		$quoted = false;
		for ($i = $end - 1; $i >= 0; $i--) {
			if ($value[$i] === '"') {
				$quoted = !$quoted;
			} else if ($value[$i] === ',' && !$quoted) {
				$items[] = self::forwardedElement(substr($value, $i + 1, $end - $i - 1));
				$end = $i;
				if (count($items) > self::HEADER_ITEMS) return $items;
			}
		}
		$items[] = ($truncated || $quoted) ? null : self::forwardedElement(substr($value, 0, $end));
		return $items;
	}

	/// Forwarded 요소 하나 → array('address', 'proto') 또는 null(불량, 또는 for 가 unknown·obfuscated·없음 — 신뢰 여부를 알 수 없음)
	private static function forwardedElement($element) {
		$element = trim($element, " \t");
		$pairs = array();
		$start = 0;
		$quoted = false;
		$length = strlen($element);
		for ($i = 0; $i < $length; $i++) {
			if ($element[$i] === '"') {
				$quoted = !$quoted;
			} else if ($element[$i] === ';' && !$quoted) {
				$pairs[] = substr($element, $start, $i - $start);
				$start = $i + 1;
			}
		}
		if ($quoted) return null;
		$pairs[] = (string)substr($element, $start);
		$values = array();
		foreach ($pairs as $pair) {
			$pair = trim($pair, " \t");
			if ($pair === '') continue;
			$pair = explode('=', $pair, 2);
			if (count($pair) !== 2 || !preg_match('/^[!#$%&\'*+.^_`|~0-9A-Za-z-]+$/', $pair[0])) return null;
			$name = strtolower($pair[0]);
			if (isset($values[$name])) return null;
			if (preg_match('/^"([^"\\\\\x00-\x1f\x7f]*)"$/', $pair[1], $match)) $values[$name] = $match[1];
			else if (preg_match('/^[\x21\x23-\x2b\x2d-\x3a\x3c\x3e-\x7e]+$/', $pair[1])) $values[$name] = $pair[1];
			else return null;
		}
		if (!isset($values['for'])) return null;
		$port = '(?::(?:[0-9]{1,5}|_[A-Za-z0-9._-]+))?';
		if (preg_match('/^\[([0-9A-Fa-f:.]+)\]' . $port . '$/', $values['for'], $match)) $family = 16;
		else if (preg_match('/^([0-9.]+)' . $port . '$/', $values['for'], $match)) $family = 4;
		else return null;
		$raw = @inet_pton($match[1]);
		if ($raw === false || strlen($raw) !== $family) return null;
		$address = self::normalizeAddress($match[1]);
		if ($address === null) return null;
		$proto = isset($values['proto']) ? strtolower($values['proto']) : null;
		if ($proto !== 'http' && $proto !== 'https') $proto = null;
		return array('address' => $address, 'proto' => $proto);
	}

	/// X-Forwarded-Proto: http/https 만, 쉼표 목록이면 모든 값이 같을 때만
	private static function forwardedProtoHeader($server) {
		$value = self::headerValue($server, 'HTTP_X_FORWARDED_PROTO');
		if ($value === null || strlen($value) > self::HEADER_BYTES) return null;
		$parts = explode(',', $value);
		if (count($parts) > self::HEADER_ITEMS) return null;
		$scheme = null;
		foreach ($parts as $part) {
			$part = strtolower(trim($part));
			if (($part !== 'http' && $part !== 'https') || ($scheme !== null && $scheme !== $part)) return null;
			$scheme = $part;
		}
		return $scheme;
	}

	/// 판정 결과를 context 에 남기고, 반영할 수 있는 주소(canReplaceRemoteAddress)일 때만 REMOTE_ADDR 를 바꾼다.
	public static function commit($result) {
		$context = Model_Context::getInstance();
		$context->setProperty('proxy.status', $result['status']);
		$context->setProperty('proxy.peer', $result['peer']);	// Textcube-NG: untrusted 판정에도 접속 주소를 화면 표시용으로 남긴다(r3-36 R-565)
		if (!$result['trusted']) return;
		$context->setProperty('proxy.trusted', true);
		foreach (array('peer', 'device', 'client', 'scheme', 'source') as $key)
			$context->setProperty('proxy.' . $key, $result[$key]);
		if ($result['status'] === 'ok' && self::canReplaceRemoteAddress($result['client']))
			$_SERVER['REMOTE_ADDR'] = $result['client'];
	}

	/// preprocessor hook. 동작 조건(DB 연결·사용 켜짐·장치 1개 이상 또는 Cloudflare 켜짐·플러그인 활성 행) 중 하나라도 거짓이면
	/// $_SERVER·context 를 바꾸지 않고 shutdown 함수도 등록하지 않는다(현행과 같음).
	public static function apply() {
		$context = Model_Context::getInstance();
		if ($context->getProperty('database.connected') !== true) return;
		$settings = self::loadSettings();
		if (!$settings['enabled']) return;
		if (count($settings['devices']) === 0 && !$settings['cloudflare']['enabled']) return;
		if (!self::isActivated()) return;
		$config = (isset($GLOBALS['service']['trustedProxies']) && is_array($GLOBALS['service']['trustedProxies']))
			? $GLOBALS['service']['trustedProxies'] : array();
		$cfRanges = $settings['cloudflare']['enabled'] ? self::cloudflareRanges($settings) : null;
		self::commit(self::evaluate(isset($_SERVER['REMOTE_ADDR']) ? $_SERVER['REMOTE_ADDR'] : null, $_SERVER,
			$settings['devices'], $cfRanges, $config));
		/* shutdown 도중 다시 등록하면 목록 맨 끝(preprocessor 의 POD::unbind 뒤)으로 가서 닫힌 DB 를 쓴다(R-411).
		   이 자리(preprocessor apply 훅)에서 바로 등록해 session_write_close·POD::unbind 보다 먼저 실행한다 */
		if ($settings['cloudflare']['enabled'] && self::isRefreshDue($settings, Timestamp::getUNIXtime()))
			register_shutdown_function(array('ST_TrustedProxies', 'refreshAfterResponse'));
	}

	private static function isActivated() {
		$query = DBModel::getInstance();
		$query->reset('Plugins');
		$query->setQualifier('name', 'equals', self::PLUGIN, true);
		return $query->doesExist('name');
	}

	private static function flag($value) {
		return (is_int($value) || is_string($value) || is_bool($value)) && (string)$value === '1';
	}

	private static function epoch($value) {
		return (is_int($value) && $value >= 0) ? $value : 0;
	}

	private static function decode($name, $default) {
		$value = Setting::getServiceSettingGlobal($name, null);
		if (!is_string($value)) return $default;
		$decoded = json_decode($value, true);
		return is_array($decoded) ? $decoded : $default;
	}

	/// 장치 하나 검사 → array('label', 'cidrs', 'ranges', 'h') 또는 null(불량 장치는 그 장치만 무시)
	public static function normalizeDevice($entry) {
		if (!is_array($entry) || !isset($entry['label'], $entry['cidrs']) || !is_string($entry['label']) || !is_array($entry['cidrs']))
			return null;
		if (strlen($entry['label']) > self::LABEL_CHARS * 4 || preg_match('/[\x00-\x1f\x7f]/', $entry['label'])) return null;
		$cidrs = array();
		$ranges = array();
		foreach ($entry['cidrs'] as $cidr) {
			$range = self::parseCidr($cidr);
			if ($range === null) return null;
			if (isset($cidrs[$range['text']])) continue;
			$cidrs[$range['text']] = true;
			$ranges[] = $range;
		}
		if (empty($ranges)) return null;
		$switch = array();
		foreach (array('xff', 'xfp', 'fwd', 'xri') as $key)
			$switch[$key] = (isset($entry['h']) && is_array($entry['h']) && isset($entry['h'][$key])) ? self::flag($entry['h'][$key]) : false;
		return array('label' => $entry['label'], 'cidrs' => array_keys($cidrs), 'ranges' => $ranges, 'h' => $switch);
	}

	/// 저장된 설정을 읽는다. JSON 이 손상되면 기본값, 상한(장치 16개·CIDR 합계 64개)을 넘는 장치는 버린다.
	public static function loadSettings() {
		$devices = array();
		$total = 0;
		foreach (self::decode('trustedProxyDevices', array()) as $entry) {
			if (count($devices) >= self::MAX_DEVICES) break;
			$device = self::normalizeDevice($entry);
			if ($device === null || $total + count($device['ranges']) > self::MAX_CIDRS) continue;
			$total += count($device['ranges']);
			$devices[] = $device;
		}
		$raw = self::decode('trustedProxyCloudflare', array());
		$cloudflare = array(
			'enabled' => isset($raw['enabled']) && self::flag($raw['enabled']),
			'days' => (isset($raw['days']) && is_int($raw['days']) && $raw['days'] >= 1 && $raw['days'] <= 30) ? $raw['days'] : 7,
			'hour' => (isset($raw['hour']) && is_int($raw['hour']) && $raw['hour'] >= 0 && $raw['hour'] <= 23) ? $raw['hour'] : 4,
			'since' => isset($raw['since']) ? self::epoch($raw['since']) : 0);
		$raw = self::decode('trustedProxyCfState', array());
		$state = array();
		foreach (array('lastSuccess', 'lastAttempt', 'v4', 'v6', 'next') as $key)
			$state[$key] = isset($raw[$key]) ? self::epoch($raw[$key]) : 0;
		$state['lastError'] = (isset($raw['lastError']) && is_string($raw['lastError'])) ? substr($raw['lastError'], 0, 64) : '';
		$state['history'] = array();
		if (isset($raw['history']) && is_array($raw['history'])) {
			foreach ($raw['history'] as $entry) {
				if (count($state['history']) >= self::CF_HISTORY) break;
				if (!is_array($entry)) continue;
				$state['history'][] = array(
					't' => isset($entry['t']) ? self::epoch($entry['t']) : 0,
					'ok' => isset($entry['ok']) && self::flag($entry['ok']),
					'error' => (isset($entry['error']) && is_string($entry['error'])) ? substr($entry['error'], 0, 64) : '',
					'v4' => isset($entry['v4']) ? self::epoch($entry['v4']) : 0,
					'v6' => isset($entry['v6']) ? self::epoch($entry['v6']) : 0,
					'manual' => isset($entry['manual']) && self::flag($entry['manual']));
			}
		}
		$attempt = Setting::getServiceSettingGlobal('trustedProxyCfAttempt', null);
		return array(
			'enabled' => self::flag(Setting::getServiceSettingGlobal('trustedProxyEnabled', '0')),
			'devices' => $devices,
			'cloudflare' => $cloudflare,
			'ranges' => self::decode('trustedProxyCfRanges', null),
			'state' => $state,
			'attempt' => (is_string($attempt) && preg_match('/^[0-9]{1,10}$/', $attempt)) ? intval($attempt) : 0);
	}

	/// CIDR 문자열 목록 → 해석된 목록. 개수 1~CF_MAX_ITEMS 이고 모두 해당 계열(4/16바이트)일 때만, 아니면 null.
	public static function parseRangeList($list, $bytes) {
		if (!is_array($list) || count($list) < 1 || count($list) > self::CF_MAX_ITEMS) return null;
		$ranges = array();
		foreach ($list as $cidr) {
			$range = self::parseCidr($cidr);
			if ($range === null || strlen($range['bin']) !== $bytes) return null;
			$ranges[] = $range;
		}
		return $ranges;
	}

	/// 마지막으로 받아 온 목록(v4·v6 모두 정상)이 있으면 그것, 아니면 내장 목록
	public static function cloudflareRanges($settings) {
		$cache = $settings['ranges'];
		if (is_array($cache) && isset($cache['v4'], $cache['v6'])) {
			$v4 = self::parseRangeList($cache['v4'], 4);
			$v6 = self::parseRangeList($cache['v6'], 16);
			if ($v4 !== null && $v6 !== null) return array_merge($v4, $v6);
		}
		$builtin = self::builtinCloudflareRanges();
		return array_merge(self::parseRangeList($builtin['v4'], 4), self::parseRangeList($builtin['v6'], 16));
	}

	/// 다음 예정 시각 계산의 시간대: config service.timezone → ini date.timezone → UTC (요청 중 블로그 시간대와 무관)
	public static function timezone() {
		$candidates = array(Model_Context::getInstance()->getProperty('service.timezone'), ini_get('date.timezone'));
		foreach ($candidates as $candidate) {
			if (is_string($candidate) && Timezone::isValid($candidate)) return $candidate;
		}
		return 'UTC';
	}

	/// 다음 예정: 마지막 시도가 성공이면 그날 + days 일의 hour 시, 실패면 + 1일의 hour 시, 시도한 적이 없으면 since 이후 처음 오는 hour 시
	public static function nextSchedule($cloudflare, $state, $now) {
		if ($state['lastAttempt'] > 0) {
			$base = $state['lastAttempt'];
			$days = ($state['lastSuccess'] === $state['lastAttempt']) ? $cloudflare['days'] : 1;
		} else {
			$base = $cloudflare['since'] > 0 ? $cloudflare['since'] : $now;
			$days = 0;
		}
		$date = new DateTime('@' . $base);
		$date->setTimezone(new DateTimeZone(self::timezone()));
		if ($days > 0) $date->modify('+' . $days . ' day');
		$date->setTime($cloudflare['hour'], 0, 0);
		if ($days === 0 && $date->getTimestamp() < $base) {
			$date->modify('+1 day');
			$date->setTime($cloudflare['hour'], 0, 0);
		}
		return $date->getTimestamp();
	}

	public static function isRefreshDue($settings, $now) {
		$state = $settings['state'];
		$next = $state['next'] > 0 ? $state['next'] : self::nextSchedule($settings['cloudflare'], $state, $now);
		if ($now < $next) return false;
		return self::canAttempt($settings['attempt'], $now);
	}

	/// 롤링 24시간 제한(실패한 시도 포함). 기록이 미래(now + 24시간 초과 — 시계 되돌림)면 허용.
	public static function canAttempt($attempt, $now) {
		return $attempt > $now + self::CF_INTERVAL || $now - $attempt >= self::CF_INTERVAL;
	}

	/// shutdown 함수. 세션을 먼저 저장(DB 세션 핸들러, DB 가 열려 있을 때)하고, FPM 이면 응답을 끝낸 뒤 CAS·fetch 를 한다.
	/// preprocessor 의 session_write_close·POD::unbind 보다 먼저 등록되므로 DB 연결이 살아 있다(R-411)
	public static function refreshAfterResponse() {
		if (session_status() === PHP_SESSION_ACTIVE) session_write_close();
		if (function_exists('fastcgi_finish_request')) fastcgi_finish_request();
		self::refresh(false);
	}

	/// 시도 기록 잠금(CAS): 행이 없으면 INSERT(동시 INSERT 는 PK 중복으로 하나만 성공), 있으면 이전 값 조건 UPDATE(영향 1행이어야 획득)
	/// 반환: array('acquired' => bool, 'attempt' => 마지막 시도 시각)
	public static function acquireAttempt($now) {
		global $__serviceSetting;
		$query = DBModel::getInstance();
		$query->reset('ServiceSettings');
		$query->setQualifier('name', 'equals', 'trustedProxyCfAttempt', true);
		$old = $query->getCell('value');
		if ($old === null || $old === false) {
			$query->reset('ServiceSettings');
			$query->setAttribute('name', 'trustedProxyCfAttempt', true);
			$query->setAttribute('value', (string)$now, true);
			// Textcube-NG: 경합에서 진 INSERT 의 PK 중복을 PostgreSQL 어댑터의 pg_query() 가 E_WARNING 으로 내는데, 락 결과는 영향 행 수로 판정하므로
			//   그 경고('pg_query()' 로 시작하는 E_WARNING)만 삼킨다. 다른 경고는 그대로 둔다. 처리기는 finally 로 반드시 복원한다(r3-24, R-466)
			//   SQLite 도 같은 PK 중복을 E_WARNING 으로 낼 수 있어('UNIQUE constraint failed: <접두사>ServiceSettings.name') 접두에 의존하지 않고
			//   메시지에 'UNIQUE constraint failed' 와 'ServiceSettings' 가 모두 있는 E_WARNING 도 삼킨다. 승자 판정(영향 행 1)은 그대로다(r3-25, R-473)
			set_error_handler(function ($errno, $errstr) {
				return strpos($errstr, 'pg_query()') === 0
					|| (strpos($errstr, 'UNIQUE constraint failed') !== false && strpos($errstr, 'ServiceSettings') !== false);
			}, E_WARNING);
			try {
				$acquired = (intval($query->insert('count')) === 1);
			} finally {
				restore_error_handler();
			}
		} else {
			$old = (string)$old;
			$previous = preg_match('/^[0-9]{1,10}$/', $old) ? intval($old) : 0;
			if (!self::canAttempt($previous, $now)) return array('acquired' => false, 'attempt' => $previous);
			$query->reset('ServiceSettings');
			$query->setQualifier('name', 'equals', 'trustedProxyCfAttempt', true);
			$query->setQualifier('value', 'equals', $old, true);
			$query->setAttribute('value', (string)$now, true);
			$acquired = (intval($query->update('count')) === 1);
		}
		if (!$acquired) {
			// 다른 요청이 먼저 잠갔다 — 그 기록을 다시 읽는다(쓰기 질의가 질의 캐시를 비웠다)
			$query->reset('ServiceSettings');
			$query->setQualifier('name', 'equals', 'trustedProxyCfAttempt', true);
			$current = (string)$query->getCell('value');
			return array('acquired' => false, 'attempt' => preg_match('/^[0-9]{1,10}$/', $current) ? intval($current) : $now);
		}
		if (!empty($__serviceSetting)) $__serviceSetting['trustedProxyCfAttempt'] = (string)$now;
		return array('acquired' => true, 'attempt' => $now);
	}

	/// 대역 받아 오기(자동·수동 공통). 잠금을 못 얻으면 아무것도 하지 않는다. v4·v6 가 모두 통과해야 목록을 바꾼다.
	/// 반환: array('acquired', 'attempt', 'ok', 'error')
	public static function refresh($manual) {
		$now = Timestamp::getUNIXtime();
		$lock = self::acquireAttempt($now);
		if (!$lock['acquired']) return array('acquired' => false, 'attempt' => $lock['attempt'], 'ok' => false, 'error' => '');
		$v4 = self::fetchList('/ips-v4', 4);
		$v6 = $v4['ok'] ? self::fetchList('/ips-v6', 16) : array('ok' => false, 'error' => '');
		$ok = $v4['ok'] && $v6['ok'];
		$error = $ok ? '' : ($v4['ok'] ? $v6['error'] : $v4['error']);
		$settings = self::loadSettings();
		$state = $settings['state'];
		if ($ok) {
			Setting::setServiceSettingGlobal('trustedProxyCfRanges', json_encode(
				array('v4' => $v4['list'], 'v6' => $v6['list'], 'fetched' => $now), JSON_UNESCAPED_SLASHES));
			$state['lastSuccess'] = $now;
			$state['v4'] = count($v4['list']);
			$state['v6'] = count($v6['list']);
		}
		$state['lastAttempt'] = $now;
		$state['lastError'] = $error;
		array_unshift($state['history'], array('t' => $now, 'ok' => $ok ? 1 : 0, 'error' => $error,
			'v4' => $ok ? count($v4['list']) : 0, 'v6' => $ok ? count($v6['list']) : 0, 'manual' => $manual ? 1 : 0));
		$state['history'] = array_slice($state['history'], 0, self::CF_HISTORY);
		foreach ($state['history'] as $index => $entry) {
			$state['history'][$index]['ok'] = $entry['ok'] ? 1 : 0;
			$state['history'][$index]['manual'] = $entry['manual'] ? 1 : 0;
		}
		$state['next'] = self::nextSchedule($settings['cloudflare'], $state, $now);
		Setting::setServiceSettingGlobal('trustedProxyCfState', json_encode($state, JSON_UNESCAPED_SLASHES));
		return array('acquired' => true, 'attempt' => $now, 'ok' => $ok, 'error' => $error);
	}

	/// https://www.cloudflare.com{$path} 를 받아 해당 계열 CIDR 목록으로 검사. 인증서 검증·SNI, 연결 5초·목록당 10초, 응답 64KB 상한.
	private static function fetchList($path, $bytes) {
		if (!extension_loaded('openssl')) return array('ok' => false, 'error' => 'openssl');
		$deadline = microtime(true) + 10;
		$context = stream_context_create(array('ssl' => getTlsStreamOptions(self::CF_HOST)));	// Textcube-NG(r4-4)
		$errno = 0;
		$errstr = '';
		$socket = @stream_socket_client('ssl://' . self::CF_HOST . ':443', $errno, $errstr, 5, STREAM_CLIENT_CONNECT, $context);
		if ($socket === false) return array('ok' => false, 'error' => 'connect');
		$request = "GET " . $path . " HTTP/1.0\r\n" .
			"Host: " . self::CF_HOST . "\r\n" .
			"User-Agent: Mozilla/4.0 (compatible; Textcube " . TEXTCUBE_VERSION . ")\r\n" .
			"Connection: close\r\n\r\n";
		if (@fwrite($socket, $request) === false) {
			fclose($socket);
			return array('ok' => false, 'error' => 'connect');
		}
		$response = '';
		while (!feof($socket)) {
			$remaining = $deadline - microtime(true);
			if ($remaining <= 0) {
				fclose($socket);
				return array('ok' => false, 'error' => 'timeout');
			}
			stream_set_timeout($socket, (int)floor($remaining), (int)(($remaining - floor($remaining)) * 1000000));
			$chunk = @fread($socket, 8192);
			$meta = stream_get_meta_data($socket);
			if (!empty($meta['timed_out'])) {
				fclose($socket);
				return array('ok' => false, 'error' => 'timeout');
			}
			if ($chunk === false) break;
			$response .= $chunk;
			if (strlen($response) > self::CF_RESPONSE_BYTES) {
				fclose($socket);
				return array('ok' => false, 'error' => 'too_large');
			}
		}
		fclose($socket);
		return self::parseListResponse($response, $bytes);
	}

	/// 응답 검사: 상태 200 만(리다이렉트 따르지 않음), Transfer-Encoding·Content-Encoding 거부, Content-Length 가 있으면 본문 길이와
	/// 같아야 함, 본문 16KB 상한, 줄마다 해당 계열 CIDR, 1~100개. 반환: array('ok', 'error', 'list')
	public static function parseListResponse($response, $bytes) {
		$split = strpos($response, "\r\n\r\n");
		if ($split === false) return array('ok' => false, 'error' => 'format');
		$head = explode("\r\n", substr($response, 0, $split));
		$body = (string)substr($response, $split + 4);
		if (!preg_match('/^HTTP\/1\.[01] ([0-9]{3})(?: |$)/', $head[0], $match)) return array('ok' => false, 'error' => 'format');
		if ($match[1] !== '200') return array('ok' => false, 'error' => 'status:' . $match[1]);
		$length = null;
		for ($i = 1; $i < count($head); $i++) {
			$field = explode(':', $head[$i], 2);
			if (count($field) !== 2) return array('ok' => false, 'error' => 'format');
			$name = strtolower(trim($field[0]));
			$value = trim($field[1]);
			if ($name === 'transfer-encoding' || $name === 'content-encoding') return array('ok' => false, 'error' => 'encoding');
			if ($name === 'content-length') {
				if ($length !== null || !preg_match('/^[0-9]{1,6}$/', $value)) return array('ok' => false, 'error' => 'length');
				$length = intval($value);
			}
		}
		if ($length !== null && $length !== strlen($body)) return array('ok' => false, 'error' => 'length');
		if (strlen($body) > self::CF_BODY_BYTES) return array('ok' => false, 'error' => 'too_large');
		$body = trim($body);
		$lines = ($body === '') ? array() : preg_split('/\r?\n/', $body);
		if (count($lines) < 1 || count($lines) > self::CF_MAX_ITEMS) return array('ok' => false, 'error' => 'count');
		$list = array();
		foreach ($lines as $line) {
			$range = self::parseCidr(trim($line));
			if ($range === null || strlen($range['bin']) !== $bytes) return array('ok' => false, 'error' => 'format');
			$list[] = $range['text'];
		}
		return array('ok' => true, 'error' => '', 'list' => $list);
	}
}
