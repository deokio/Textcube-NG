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
#[AllowDynamicProperties]
final class HTTPRequest {
	var $method = 'GET', $url, $async = false, $contentType = 'application/x-www-form-urlencoded', $content = '', $eTag, $lastModified, $referer, $timeout = 10, $responseText, $pathToSave;
	/* [보안] true 이면 공인 IPv4 주소의 80/443 포트로만 연결한다(리다이렉트마다 다시 확인, 확인한 IP 로 직접 연결해 확인 뒤 DNS 가
	   다른 주소를 돌려주는 경우도 막음). 사용자가 준 URL 을 서버가 가져오는 곳(리더 이미지 프록시, 백업 웹 가져오기)에서 켠다.
	   기본값 false = 기존 동작(다른 호출부 영향 없음) */
	var $publicOnly = false;
	
	function __construct() {
		switch (func_num_args()) {
			case 0:
				break;
			case 1:
				$this->url = func_get_arg(0);
				break;
			default:
			case 2:
				$this->method = func_get_arg(0);
				$this->url = func_get_arg(1);
				break;
		}
	}
	function send($content = null) {
		if (empty($this->url))
			return false;
		if (!is_null($content))
			$this->content = $content;
		$request = @parse_url($this->url);
		for ($trial = 0; $trial < 5; $trial++) {
			unset($this->_response);
			// http/https 모두 허용. https 는 ssl:// 프리픽스로 PHP OpenSSL 확장을 통해 처리.
			// 자체 서명 인증서(self-signed)는 기본 설정에서 연결 거부됨 — stream_socket_client 기반
			// 컨텍스트 옵션 지원이 필요한 경우 별도 구현 필요 (장기 과제).
			if (empty($request['scheme']) || !in_array($request['scheme'], array('http', 'https')) || empty($request['host']))
				return false;
			if (empty($request['port']))
				$request['port'] = ($request['scheme'] === 'https') ? 443 : 80;
			if (empty($request['path']))
				$request['path'] = '/';
			if ($this->publicOnly) {
				// [보안] 내부 주소(사설·루프백·링크로컬=클라우드 메타데이터 등)와 80/443 외 포트 거부. 확인한 IP 로 연결하고 https 는 원래 호스트 이름으로 인증서 확인
				if (!in_array((int)$request['port'], array(80, 443), true) || ($address = self::getPublicAddress($request['host'])) === false)
					return false;
				$context = stream_context_create(array('ssl' => array('peer_name' => $request['host'], 'SNI_enabled' => true)));
				if (!$socket = @stream_socket_client((($request['scheme'] === 'https') ? 'ssl://' : 'tcp://') . $address . ':' . $request['port'], $errno, $errstr, $this->timeout, STREAM_CLIENT_CONNECT, $context))
					return false;
			} else {
				$socketHost = ($request['scheme'] === 'https') ? 'ssl://' . $request['host'] : $request['host'];
				if (!$socket = @fsockopen($socketHost, $request['port'], $errno, $errstr, $this->timeout))
					return false;
			}
				
			$path = empty($request['query']) ? $request['path'] : $request['path'] . '?' . $request['query'];
			fwrite($socket, $this->method . ' ' . $path . " HTTP/1.1\r\n");
			fwrite($socket, 'Host: ' . $request['host'] . "\r\n");
			fwrite($socket, "User-Agent: Mozilla/4.0 (compatible; Textcube ".TEXTCUBE_VERSION.")\r\n");
			fwrite($socket, "Accept-Encoding: identity\r\n");
			if (!is_null($this->referer))
				fwrite($socket, "Referer: {$this->referer}\r\n");
			if (!is_null($this->eTag))
				fwrite($socket, "If-None-Match: {$this->eTag}\r\n");
			if (!is_null($this->lastModified)) {
				if (is_numeric($this->lastModified))
					$this->lastModified = Timestamp::getRFC1123GMT($this->lastModified);
				fwrite($socket, "If-Modified-Since: {$this->lastModified}\r\n");
			}
			if (!empty($this->content)) {
				fwrite($socket, "Content-Type: {$this->contentType}\r\n");
				fwrite($socket, "Content-Length: " . strlen($this->content) . "\r\n");
			}
			fwrite($socket, "Connection: close\r\n");
			fwrite($socket, "\r\n");
			if ($this->content !== false)
				fwrite($socket, $this->content);
			if($this->async)
				return true;
			
			/* [보안] Textcube-NG: 응답 헤더도 _readLine() 으로 읽는다 — 헤더 한 줄 65536 바이트, 헤더 전체 크기와 기한은 본문과 같은 설정(httpRequestMaxSize·httpRequestMaxTime, 파일 저장은 MaxSave*). 원본은 헤더 줄 길이·시간 한도가 없었다(r3-7, 446a·결정 446b) */
			list($headerMaxSize, $headerMaxTime) = $this->_bodyLimit((bool)$this->pathToSave);
			$headerDeadline = ($headerMaxTime > 0) ? time() + $headerMaxTime : 0;
			$headerTotal = 0;
			for (; $trial < 5; $trial++) {
				$line = $this->_readLine($socket, $headerDeadline, 65536, $headerMaxSize, $headerTotal);
				if ($line === false || $line === '') {
					fclose($socket);
					return false;
				}
				if (!preg_match('@^HTTP/([0-9\.]+)[ \t]+([0-9]+)[ \t]+@', $line, $match)) {
					fclose($socket);
					return false;
				}
				$this->_response['version'] = $match[1];
				$this->_response['status'] = $match[2];
				while (($line = $this->_readLine($socket, $headerDeadline, 65536, $headerMaxSize, $headerTotal)) !== '') {
					if ($line === false) {
						fclose($socket);
						return false;
					}
					$line = rtrim($line);
					if (empty($line))
						break;
					$header = explode(': ', $line, 2);
					if (count($header) != 2)
						continue;
					$header[0] = strtolower($header[0]);
					switch ($header[0]) {
						case 'connection':
						case 'content-length':
						case 'content-type':
						case 'date':
						case 'etag':
						case 'last-modified':
						case 'location':
						case 'transfer-encoding':
						case 'X-Pingback':
						case 'X-CommunicationFeed':
							$this->_response[$header[0]] = trim($header[1]);
							break;
					}
				}
				if ($this->_response['status'] != 100)
					break;
				unset($this->_response);
			}
			if (!isset($this->_response['status'])) {	// Textcube-NG: 100 응답만 이어져 시도 5번을 다 쓰면 상태 줄이 없다 — 정의 안 된 속성을 읽지 않고 실패로 돌려준다(원본은 경고 뒤 false, r3-15)
				fclose($socket);
				return false;
			}
			if (in_array($this->_response['status'], array(300, 301, 302, 307))) {
				fclose($socket);
				$socket = null;	// Textcube-NG: 닫은 소켓을 아래 switch 에서 다시 닫지 않게 비운다(리다이렉트 5회 소진, r3-7 보충)
				if (empty($this->_response['location']))
					return false;
				/* [보안] Textcube-NG: 리다이렉트 주소로 요청 값을 새로 만든다 — 원본은 parse_url 결과를 이전 요청 값에 섞어 이전 query·port·user 가 남았고, parse_url 이 실패하면 foreach 경고가 났다. 상대 주소는 _resolveLocation() 이 RFC 3986 5.2 규칙으로 지금 주소 기준으로 해석하고, 결과는 루프 처음의 scheme·host·포트·publicOnly(v3.157) 검사를 다시 거친다(r3-7, 446c) */
				$location = @parse_url($this->_response['location']);
				if (!is_array($location))
					return false;
				$request = self::_resolveLocation($request, $location);
				$this->url = $this->_response['location'];
				continue;
			}
			break;
		}
		
		switch ($this->_response['status']) {
			case 200: // OK
				break;
			case 300: // Multiple Choices
			case 301: // Moved Permanently
			case 302: // Found
			default:
				// Textcube-NG: 리다이렉트를 5회 다 쓰면 소켓은 위에서 이미 닫혔다 — PHP 8 은 닫힌 자원에 fclose 하면 TypeError(원본 PHP 5 는 경고)(r3-7 보충)
				if (is_resource($socket))
					fclose($socket);
				return false;
			case 304: // Not Modified
				if (is_null($this->eTag) && is_null($this->lastModified)) {
					fclose($socket);
					return false;
				}
				break;
		}

		/* [보안] Textcube-NG: 응답 본문 읽기를 _readBody() 하나로 모은다 — 크기·시간 상한($service['httpRequestMax*'], config.default.php)을 넘거나 읽기 실패·시간 초과·잘린 응답이면 false. 원본은 상한이 없었고 fread 가 false·'' 를 돌려줘도 같은 자리를 계속 돌았다(r3-7, 446a·결정 446b) */
		$fp = null;
		if ($this->pathToSave) {
			if (!$fp = fopen($this->pathToSave, 'wb+')) {
				fclose($socket);
				return false;
			}
		} else {
			$this->responseText = '';
		}
		/* Textcube-NG: HEAD 요청과 204·304 응답에는 본문이 없다(RFC 9110 9.3.2·15.3.5·15.4.5) — Content-Length 가 있어도 읽지 않는다. 원본은 304 에 Content-Length 가 있으면 오지 않는 본문을 끝없이 기다렸다(r3-7, 446a) */
		if (strtoupper((string)$this->method) === 'HEAD' || in_array((int)$this->_response['status'], array(204, 304), true))
			$ok = true;
		else
			$ok = $this->_readBody($socket, $fp);
		if (!is_null($fp))
			fclose($fp);
		fclose($socket);
		return $ok;
	}
	
	/* Textcube-NG: 다음 읽기가 기다릴 시간을 남은 기한으로 줄인다($deadline 0 이면 그대로). 기한이 지났으면 false(r3-7, 446a). fsockopen·stream_socket_client 의 시간 제한은 연결에만 쓰이고, 읽기는 default_socket_timeout 만큼 기다리기 때문 */
	private function _waitLimit($socket, $deadline) {
		if ($deadline <= 0)
			return true;
		$left = $deadline - time();
		if ($left < 0)
			return false;
		stream_set_timeout($socket, max(1, $left));
		return true;
	}

	/* Textcube-NG: 응답의 한 줄(헤더 줄·청크 크기 줄)을 1바이트씩 읽는다(r3-7, 446a) — 연결이 끝나면 그때까지 읽은 것(없으면 '', 원본 fgets 와 같음). 줄바꿈 전에 $maxLength 바이트에 닿거나, 합계 $total 이 $maxSize 를 넘거나(0 이면 상한 없음), 기한을 넘거나 읽기 시간 초과면 false. 기한은 바이트마다 보고, 읽기마다 남은 기한을 _waitLimit() 로 넣어 한 줄 안에서 천천히 보내도 기한에서 끝난다 */
	private function _readLine($socket, $deadline, $maxLength, $maxSize, &$total) {
		$line = '';
		while (true) {
			if (!$this->_waitLimit($socket, $deadline))
				return false;
			$c = fgetc($socket);
			if ($c === false) {
				$meta = stream_get_meta_data($socket);
				return empty($meta['timed_out']) ? $line : false;
			}
			$line .= $c;
			$total++;
			if ($maxSize > 0 && $total > $maxSize)
				return false;
			if ($c === "\n")
				return $line;
			if (strlen($line) >= $maxLength)
				return false;
		}
	}

	/* Textcube-NG: 리다이렉트 Location(parse_url 배열)을 지금 요청 주소 기준으로 RFC 3986 5.2.2 규칙대로 해석한다 — 경로 상대(x·../x), 네트워크 경로(//host), query 만(?q), fragment 만(#f). 결과 경로가 비면 '/'(r3-7, 446c) */
	private static function _resolveLocation($base, $ref) {
		if (!empty($ref['scheme']) || isset($ref['host'])) {
			$target = $ref;
			if (empty($target['scheme']))
				$target['scheme'] = $base['scheme'];
			$target['path'] = self::_removeDotSegments(isset($ref['path']) ? $ref['path'] : '');
		} else {
			$target = array('scheme' => $base['scheme']);
			foreach (array('user', 'pass', 'host', 'port') as $key) {
				if (isset($base[$key]))
					$target[$key] = $base[$key];
			}
			$path = isset($ref['path']) ? $ref['path'] : '';
			if ($path === '') {
				$target['path'] = isset($base['path']) ? $base['path'] : '';
				if (isset($ref['query']))
					$target['query'] = $ref['query'];
				else if (isset($base['query']))
					$target['query'] = $base['query'];
			} else {
				if ($path[0] !== '/') {
					$basePath = isset($base['path']) ? $base['path'] : '';
					$slash = strrpos($basePath, '/');
					$path = ($slash === false) ? '/' . $path : substr($basePath, 0, $slash + 1) . $path;
				}
				$target['path'] = self::_removeDotSegments($path);
				if (isset($ref['query']))
					$target['query'] = $ref['query'];
			}
			if (isset($ref['fragment']))
				$target['fragment'] = $ref['fragment'];
		}
		if (!isset($target['path']) || $target['path'] === '')
			$target['path'] = '/';
		return $target;
	}

	/* Textcube-NG: RFC 3986 5.2.4 remove_dot_segments — '.'·'..' 경로 조각을 지운다(r3-7, 446c) */
	private static function _removeDotSegments($path) {
		$output = '';
		while ($path !== '') {
			if (strncmp($path, '../', 3) === 0) {
				$path = (string)substr($path, 3);
			} else if (strncmp($path, './', 2) === 0 || strncmp($path, '/./', 3) === 0) {
				$path = (string)substr($path, 2);
			} else if ($path === '/.') {
				$path = '/';
			} else if (strncmp($path, '/../', 4) === 0 || $path === '/..') {
				$path = ($path === '/..') ? '/' : (string)substr($path, 3);
				$slash = strrpos($output, '/');
				$output = ($slash === false) ? '' : (string)substr($output, 0, $slash);
			} else if ($path === '.' || $path === '..') {
				$path = '';
			} else {
				$next = strpos($path, '/', 1);
				if ($next === false)
					$next = strlen($path);
				$output .= substr($path, 0, $next);
				$path = (string)substr($path, $next);
			}
		}
		return $output;
	}

	/* [보안] Textcube-NG: 응답 본문 상한(r3-7, 결정 446b) — 파일 저장(pathToSave)은 httpRequestMaxSaveSize·httpRequestMaxSaveTime, 그 밖은 httpRequestMaxSize·httpRequestMaxTime(바이트·초, 0 이면 상한 없음). 설정이 없거나 0 이상의 숫자가 아니면 기본값 */
	private function _bodyLimit($save) {
		global $service;
		$keys = $save ? array('httpRequestMaxSaveSize', 'httpRequestMaxSaveTime') : array('httpRequestMaxSize', 'httpRequestMaxTime');
		$defaults = $save ? array(536870912, 600) : array(10485760, 60);
		$limit = array();
		for ($i = 0; $i < 2; $i++)
			$limit[$i] = (isset($service[$keys[$i]]) && is_numeric($service[$keys[$i]]) && $service[$keys[$i]] >= 0) ? (int)$service[$keys[$i]] : $defaults[$i];
		return $limit;
	}

	/* Textcube-NG: 읽은 조각을 둔다 — $fp 가 false 면 버리고(청크 끝 CRLF), null 이면 responseText 에, 그 밖은 파일에(r3-7) */
	private function _append($fp, $data) {
		if ($fp === false)
			return true;
		if (is_null($fp)) {
			$this->responseText .= $data;
			return true;
		}
		return (fwrite($fp, $data) === strlen($data));
	}

	/* Textcube-NG: 정확히 $length 바이트를 읽는다. 연결이 끝나거나 시간을 넘거나 읽기 시간 초과면 false(r3-7) */
	private function _readExact($socket, $fp, $length, $deadline) {
		while ($length > 0) {
			if (!$this->_waitLimit($socket, $deadline) || feof($socket))
				return false;
			$data = fread($socket, min(10240, $length));
			if ($data === false)
				return false;
			if ($data === '') {
				$meta = stream_get_meta_data($socket);
				if (!empty($meta['timed_out']))
					return false;
				continue;
			}
			if (!$this->_append($fp, $data))
				return false;
			$length -= strlen($data);
		}
		return true;
	}

	/* Textcube-NG: 응답 본문을 읽는다(chunked·content-length·content-type 순서는 원본과 같음). 청크 크기 줄은 ';' 앞의 16진수만 받는다(r3-7) */
	private function _readBody($socket, $fp) {
		list($maxSize, $maxTime) = $this->_bodyLimit(!is_null($fp));
		$deadline = ($maxTime > 0) ? time() + $maxTime : 0;
		$total = 0;
		if ($this->getResponseHeader('Transfer-Encoding') == 'chunked') {
			$lineTotal = 0;
			/* [보안] Textcube-NG: 청크 크기 줄도 _readLine() 으로 — 청크 크기 줄 한도는 헤더 줄 한도와 같은 65536 바이트, 기한은 본문 기한. 줄 합계는 본문 상한과 섞지 않는다(0). 원본 fgets 는 줄 길이·시간 한도가 없었다(r3-7, 446a) */
			while (($line = $this->_readLine($socket, $deadline, 65536, 0, $lineTotal)) !== '') {
				if ($line === false)
					return false;
				$parts = explode(';', $line, 2);
				$sizeText = trim($parts[0]);
				if (!preg_match('/\A[0-9a-fA-F]{1,15}\z/', $sizeText))
					return false;
				$chunkSize = hexdec($sizeText);
				if ($chunkSize == 0)
					break;
				$total += $chunkSize;
				if ($maxSize > 0 && $total > $maxSize)
					return false;
				if (!$this->_readExact($socket, $fp, $chunkSize, $deadline) || !$this->_readExact($socket, false, 2, $deadline))
					return false;
			}
		} else if (!empty($this->_response['content-length'])) {
			$length = (int)$this->_response['content-length'];
			if ($length < 0 || ($maxSize > 0 && $length > $maxSize))
				return false;
			return $this->_readExact($socket, $fp, $length, $deadline);
		} else if (!empty($this->_response['content-type'])) {
			while (true) {
				if (!$this->_waitLimit($socket, $deadline))
					return false;
				if (feof($socket))
					break;
				$data = fread($socket, 10240);
				if ($data === false)
					return false;
				if ($data === '') {
					$meta = stream_get_meta_data($socket);
					if (!empty($meta['timed_out']))
						return false;
					continue;
				}
				$total += strlen($data);
				if ($maxSize > 0 && $total > $maxSize)
					return false;
				if (!$this->_append($fp, $data))
					return false;
			}
		}
		return true;
	}

	function getResponseHeader($name) {
		$name = strtolower($name);
		return (isset($this->_response[$name]) ? $this->_response[$name] : null);
	}
	
	/* [보안] 호스트를 IPv4 로 해석해 모든 주소가 공인 대역이면 첫 주소, 아니면 false(해석 실패·IPv6 리터럴 포함).
	   숫자·16진 표기 호스트도 해석 결과로 판정. PHP 버전마다 다른 filter_var 예약 대역 플래그 대신 대역을 직접 비교 */
	static function getPublicAddress($host) {
		if (!is_string($host) || $host === '' || strpbrk($host, "[]:%/\\@ \t\r\n\0") !== false)
			return false;
		$addresses = (filter_var($host, FILTER_VALIDATE_IP, FILTER_FLAG_IPV4) !== false) ? array($host) : @gethostbynamel($host);
		if (empty($addresses))
			return false;
		$blocked = array('0.0.0.0/8', '10.0.0.0/8', '100.64.0.0/10', '127.0.0.0/8', '169.254.0.0/16', '172.16.0.0/12',
			'192.0.0.0/24', '192.0.2.0/24', '192.88.99.0/24', '192.168.0.0/16', '198.18.0.0/15', '198.51.100.0/24',
			'203.0.113.0/24', '224.0.0.0/4', '240.0.0.0/4');
		foreach ($addresses as $address) {
			if (($long = ip2long($address)) === false)
				return false;
			foreach ($blocked as $cidr) {
				list($network, $bits) = explode('/', $cidr);
				$mask = -1 << (32 - (int)$bits);
				if ((ip2long($network) & $mask) === ($long & $mask))
					return false;
			}
		}
		return $addresses[0];
	}
}
?>
