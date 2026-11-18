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

function xml_parser($url, $mdate) {
	if ($mdate == "0")
		$mdate = "";
	$remote = @get_remotefile($url, $mdate);
	// Textcube-NG: 받기에 실패하면 get_remotefile 은 false — PHP 8.5 는 bool 을 list() 로 나누면 경고(Cannot use bool as array)를 내므로
	// 배열일 때만 나누고, 아니면 이전(false 분해)과 같은 null 값을 쓴다(r3-8 보충, 493v)
	if (is_array($remote))
		list($header, $body, $lmdate) = $remote;
	else
		$header = $body = $lmdate = null;
	$st = get_sock_status($header);
	$vals = null; // Textcube-NG: 200이 아닐 때 $vals 미정의 방지(r3-8)
	if ($st == 200) {
		$vals = tt_xml_parser_into_struct($body);
	}
	return array($st, $header, $body, $lmdate, $vals);
}

function tt_xml_parser_into_struct($body) {
	$h_set = array();
	$r_set = array();
	while ($n1 = strpos($body, "<![CDATA[")) {
		$n2 = strpos($body, "]]>");
		if (!$n2 || $n1 > $n2)
			break;
		$stamp = get_timestamp2();
		$cval = substr($body, $n1 + 9, $n2 - $n1 - 9);
		$body = str_replace("<![CDATA[" . $cval . "]]>", $stamp, $body);
		$h_set[$stamp] = trim($cval);
	}
	$b_set = explode("<", $body);
	foreach ($b_set as $k => $row) {
		$inp = array();
		$att_set = array();
		$row = trim($row);
		if (!$row)
			continue;
		if (substr($row, 0, 1) == "/") {
			if (strpos($row, "item")) {
				$inp['tag'] = "ITEM";
				$inp['type'] = "close";
				array_push($r_set, $inp);
			}
			continue;
		}
		if( strpos( $row, ">" ) === false ) {
			continue;
		}
		list($tag_inf, $val) = explode(">", $row);
		if ($tag_nid = strpos($tag_inf, " ")) {
			$tag = substr($tag_inf, 0, $tag_nid);
			$tag_inf = substr($tag_inf, $tag_nid);
			while ($tag_n1 = strpos($tag_inf, "=")) {
				$tag_n2 = strpos($tag_inf, "\"", $tag_n1 + 2);
				$att_inf = substr($tag_inf, 1, $tag_n2);
				$tag_inf = substr($tag_inf, $tag_n2 + 1);
				$att_var = trim(strtoupper(substr($att_inf, 0, $tag_n1 - 1)));
				$att_val = trim(str_tag_on(str_replace("\"", "", substr($att_inf, $tag_n1 + 1))));
				$att_set[$att_var] = $att_val;
			}
			$inp['attributes'] = $att_set;
		} else {
			$tag = $tag_inf;
		}
		if (!$tag || $tag == "?XML")
			continue;
		$tag = strtoupper($tag);
		$inp['tag'] = $tag;
		$val = trim($val);
		if (isset($h_set[$val]))
			$val = $h_set[$val];
		else if ($val)
			$val = str_tag_on($val);
		$inp['value'] = str_replace("document.cookie", "document.&#99;ookie", $val);
		array_push($r_set, $inp);
	}
	return $r_set;
}

function get_remotefile($url, $mdate, $redirects = 0, $deadline = null) {
	// Textcube-NG: 링크 사이트 정보(/owner/network/link/site — 블로그 관리자, 멀티블로그에서는 자기 블로그를 만든 가입자 누구나)가 주소를 검사하지 않고
	// 연결해 서버 안쪽(루프백·사설망·링크로컬 — 클라우드 메타데이터 169.254.169.254 등) 서비스 응답의 제목을 읽어 갈 수 있었고(SSRF), Location 을
	// 횟수·대상 제한 없이 따라갔으며 응답 크기·전체 시간 제한이 없었다(fgets 는 줄이 끝날 때까지 기다려 조금씩 보내는 응답에 오래 묶임).
	// 사용자가 준 주소를 서버가 가져오는 다른 곳(v3.79 HTTPRequest::$publicOnly — 리더 이미지 프록시·백업 웹 가져오기)과 같은 기준으로
	// http·https 의 80/443 포트, 공인 IPv4 주소(HTTPRequest::getPublicAddress)만 확인한 IP 로 연결하고(DNS 를 다시 묻지 않아 재해석으로 우회 불가),
	// 리다이렉트 3회, 응답 1MB(넘는 부분은 원본의 item 15개 제한처럼 잘라 냄), 전체 10초로 제한한다(v3.141)
	if ($deadline === null)
		$deadline = microtime(true) + 10;
	$url_stuff = parse_url($url);
	if (!is_array($url_stuff) || empty($url_stuff['host']) || !isset($url_stuff['scheme']))
		return false;
	// Textcube-NG: https 주소는 TLS(ssl://, 인증서 검증은 PHP 기본값 그대로)로 연결하고 포트가 없으면 443 을 쓴다. 원본은 scheme 을 보지 않고
	// 평문으로 80(또는 지정 포트)에 연결해 https 피드를 읽지 못했다(pat.im 1336)(v3.140)
	$secure = (strtolower($url_stuff['scheme']) == 'https');
	if (!$secure && strtolower($url_stuff['scheme']) != 'http')
		return false;
	$port = isset($url_stuff['port']) ? $url_stuff['port'] : ($secure ? 443 : 80);
	if (!in_array((int)$port, array(80, 443), true) || ($address = HTTPRequest::getPublicAddress($url_stuff['host'])) === false)
		return false;
	$context = stream_context_create(array('ssl' => array('peer_name' => $url_stuff['host'], 'SNI_enabled' => true)));	// 인증서 이름 확인·SNI 는 확인한 IP 가 아니라 원래 호스트 이름으로
	$wait = min(2, $deadline - microtime(true));
	if ($wait <= 0)
		return false;
	if (!$fp = @stream_socket_client(($secure ? 'ssl://' : 'tcp://') . $address . ':' . $port, $errno, $errstr, $wait, STREAM_CLIENT_CONNECT, $context))
		return false;
	else {
		if (empty($url_stuff['path'])) {
			$url_stuff['path'] = "/";
		}
		if (!empty($url_stuff['query'])) {
			$url_stuff['path'] .= "?";
		} else {
			$url_stuff['query'] = "";
		}
		$header = "GET " . $url_stuff['path'] . $url_stuff['query'] . " HTTP/1.0";
		$header .= "\r\nHost: " . $url_stuff['host'];
		$header .= "\r\nIf-Modified-Since: $mdate";
		$header .= "\r\n\r\n";
		fputs($fp, $header);
		$header = '';
		$body = '';
		$lmdate = '';
		$act = false;
		$cnt = 0;
		$buffer = '';
		$received = 0;
		$headCont = false; // Textcube-NG: 앞 조각이 1023바이트에서 잘렸는지(r3-8)
		while (true) {
			if ($cnt == 15)
				break;
			// Textcube-NG: 원본 fgets($fp, 1024) 와 같은 한 줄(줄바꿈까지, 최대 1023 바이트)을 버퍼에서 꺼낸다. 읽기는 받은 만큼 바로 돌아오는 fread 로
			// 하고 한 번에 4초(원본과 같음)와 남은 시간 중 짧은 쪽까지만 기다린다. 시간이 다 되면 원본의 timed_out 과 같이 false, 1MB 를 받으면 그만 읽음(v3.141)
			while (($n = strpos($buffer, "\n")) === false && strlen($buffer) < 1023 && $received < 1048576 && !feof($fp)) {
				$wait = min(4, $deadline - microtime(true));
				if ($wait <= 0) {
					fclose($fp);
					return false;
				}
				stream_set_timeout($fp, (int)$wait, (int)(($wait - (int)$wait) * 1000000));
				$data = fread($fp, min(8192, 1048576 - $received));
				$ss = socket_get_status($fp);
				if ($ss['timed_out']) {
					fclose($fp);
					return false;
				}
				if ($data === false)
					break;
				$buffer .= $data;
				$received += strlen($data);
			}
			if ($buffer === '')
				break;
			$line = substr($buffer, 0, ($n !== false && $n < 1023) ? $n + 1 : 1023);
			$buffer = (string)substr($buffer, strlen($line));
			if (!$act) {
				// Textcube-NG: 빈 줄만 헤더 끝으로 본다(원본은 \r\n 이 없는 줄도 false == 0 으로 끝으로 봄). 1023바이트에서 잘린 줄의 이어지는 조각은
				// 헤더 판정에서 빼고, 헤더 이름은 줄 처음에서 대소문자 없이 보며, Location 은 상태가 30x 일 때만 따른다(r3-8)
				$isCont = $headCont;
				$headCont = (substr($line, -1) !== "\n");
				if (!$isCont && ($line === "\r\n" || $line === "\n"))
					$act = true;
				if (!$isCont && strncasecmp($line, "Last-Modified:", 14) === 0)
					$lmdate = trim(substr($line, 14));
				if (!$isCont && strncasecmp($line, "Location:", 9) === 0 && preg_match('#^HTTP/\d+(?:\.\d+)?[ \t]+30[12378](?:[ \t\r\n]|$)#', $header)) {
					$loc = trim(substr($line, 9));
					break;
				}
				$header .= $line;
			} else {
				$body .= $line;
				if (strpos($line, "</item>") !== false)
					$cnt++;
			}
		}
		fclose($fp);
	}
	// Textcube-NG: 리다이렉트는 3회까지 따라가고 대상도 같은 검사(scheme·포트·주소·남은 시간)를 거친다. 대상을 읽지 못하면 false(원본은 null 배열 —
	// 유일한 호출부 xml_parser 의 list() 결과는 같음)(v3.141)
	if (!empty($loc)) {
		if ($redirects >= 3)
			return false;
		return get_remotefile($loc, $mdate, $redirects + 1, $deadline);
	}
	return array($header, $body, $lmdate);
}

function get_sock_status($str) {
	$str = (string)$str; // Textcube-NG: 받기 실패(null) 때 strpos 인자 형식 오류 방지(r3-8)
	// Textcube-NG: 첫 줄(상태 줄)의 상태 코드로 판정한다. 원본은 헤더 어디든 "200 OK" 가 있으면 200, 이유 문구가 다른 200 은 404 로 봤다(r3-8)
	if (preg_match('#^HTTP/\d+(?:\.\d+)?[ \t]+(\d{3})(?:[ \t\r\n]|$)#', $str, $m)) {
		if ($m[1] === '200')
			return 200;
		if ($m[1] === '304')
			return 304;
	}
	return 404;
}

function get_timestamp2() {
	list($usec) = explode(" ", microtime());
	return date("ymdHis", time()) . substr($usec, 2, 6);
}

function str_dbi_check($array) {
	if (count($array)) {
		foreach ($array as $key => $val) {
			$array[$key] = str_replace("'", "&#39;", $val);
		}
	}
	return $array;
}

function get_siteinfo($xmlinfo) {
	$st = $sl = $sd = '';
	foreach ($xmlinfo as $k => $row) {
		if (!$st && $row['tag'] == "TITLE")
			$st = $row['value'];
		else if (!$sl && $row['tag'] == "LINK")
			$sl = $row['value'];
		else if (!$sd && $row['tag'] == "DESCRIPTION")
			$sd = $row['value'];
		else if ($row['tag'] == "ITEM")
			break;
	}
	return array($st, $sl, $sd);
}

function correctTTForXmlText($text) {
	return str_replace('&quot;', '"', str_replace('&#39;', '\'', $text));
}

function checkResponseXML($responseText) {
	global $service;

	$xmls = new XMLStruct();
	if(!$xmls->open(trim($responseText), $service['encoding']))
		return false;
	if(($error = $xmls->getValue('/response/error')) !== null)
		return intval($error);
	else
		return false;
}
?>
