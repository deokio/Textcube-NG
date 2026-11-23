<?php
/// Copyright (c) 2006-2009, Needlworks / Tatter Network Foundation
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

class Pop3 {
	function __construct()
	{
		$this->ctx = null;
		$this->logger = null;
		$this->uidl_filter = null;
		$this->size_filter = null;
		$this->stat_callback = null;
		$this->retr_callback = null;
		$this->mails = array();
		$this->uids = array();
		$this->filterred = array();
		$this->clearStatus();
	}

	function log($msg)
	{
		if( !$this->logger ) {
			return;
		}
		call_user_func( $this->logger, $msg );
	}

	function connect( $server, $port = 110, $encryption = 'none' )
	{
		$this->clearStatus();
		// backward compat: true/1 → 'ssl', false/0/null → 'none'
		if ($encryption === true  || $encryption === 1)  $encryption = 'ssl';
		elseif ($encryption === false || $encryption === 0 || $encryption === null) $encryption = 'none';

		// verify_peer: 인증서 체인 유효성 검사
		// verify_peer_name: 인증서 이름(subjectAltName/CN)과 접속 호스트 일치 검사
		/* [보안] 이름 검사를 끄면 공인 CA 가 발급한 다른 도메인 인증서도 통과해, 경로 중간에서 접속을 가로챈 쪽이
		   메일함 계정·비밀번호를 받는다 → 켜고 peer_name 에 접속 호스트를 명시(PHP 기본 검사와 같음).
		   공유 인증서를 쓰는 서버는 인증서에 적힌 호스트 이름으로 서버 주소를 설정해야 한다. */
		$sslCtx = stream_context_create(array('ssl' => getTlsStreamOptions($server)));	// Textcube-NG: 5.5 는 CN_match·CA 를 명시(00-Compat.php, r4-4)
		$addr = ($encryption === 'ssl') ? "ssl://$server:$port" : "tcp://$server:$port";
		// 접속 중 경고를 모두 모아 오류 설명에 쓴다 — error_get_last() 는 마지막 경고("Unable to connect")만 남아
		// 인증서 이름 불일치·검증 실패 같은 실제 원인이 빠지기 때문(경고는 출력하지 않음, 기존 @ 와 같음)
		$warns = array();
		set_error_handler(function ($no, $str) use (&$warns) {
			$warns[] = trim(preg_replace('/\s+/', ' ', preg_replace('/^.*stream_socket_client\(\):\s*/i', '', $str)));
			return true;
		});
		$this->ctx = stream_socket_client($addr, $errno, $errstr, 30, STREAM_CLIENT_CONNECT, $sslCtx);
		restore_error_handler();
		if (!$this->ctx) {
			$detail = $warns ? implode(' / ', array_unique($warns)) : ($errstr ?: 'connection failed');
			$this->error = "$detail ($errno)";
			return false;
		}
		$line = fgets($this->ctx, 1024);
		if (!$this->checkStatus($line)) return false;

		if ($encryption === 'starttls') {
			$this->log("Send: STLS");
			if (!fputs($this->ctx, "STLS\r\n")) return false;
			$line = fgets($this->ctx, 1024);
			if (!$this->checkStatus($line)) return false;
			if (!@stream_socket_enable_crypto($this->ctx, true, getTlsCryptoClientMethod())) {
				$sslErr = '';
				while (($e = openssl_error_string()) !== false) $sslErr .= ' ' . trim($e);
				if (!$sslErr) { $last = error_get_last(); if ($last) $sslErr = $last['message']; }
				$this->error = 'STARTTLS negotiation failed' . ($sslErr ? ": $sslErr" : '');
				return false;
			}
		}
		return true;
	}

	function authorize( $username, $password )
	{
		$this->clearStatus();
		// [보안] 메일함 계정 ID 는 로그(cache/moblog.txt)에 남기지 않음 — PASS 와 같은 표기
		$this->log( "Send: USER [......]" );
		if( !fputs($this->ctx, "USER $username\r\n") ) {
			return false;
		}
		if( !$this->receiveResult() ) {
			return false;
		}
		$this->log( "Send: PASS [......]" );
		if( !fputs($this->ctx, "PASS $password\r\n") ) {
			return false;
		}
		return $this->receiveResult();
	}

	function retr($nr=false)
	{
		$this->clearStatus();
		if( $nr != false ) {
			$numbers = array( $nr );
		} else {
			$numbers = array_values( $this->mails );
		}

		foreach( $numbers as $nr ) {
			$this->log( "Send: RETR $nr" );
			if( !fputs($this->ctx, "RETR $nr\r\n") ) {
				return false;
			}
			if( !$this->receiveResult(true) ) {
				return false;
			}
			if( $this->retr_callback ) {
				call_user_func( $this->retr_callback, $this->results, $this->uids[$nr] );
			}
		}

		return true;
	}

	function dele($nr=false)
	{
		$this->clearStatus();
		if( $nr != false ) {
			$numbers = array( $nr );
		} else {
			$numbers = array_values( $this->mails );
		}
		foreach( $numbers as $nr ) {
			$this->log( "Send: DELE $nr" );
			if( !fputs($this->ctx, "DELE $nr\r\n") ) {
				return false;
			}
			if( !$this->receiveResult(false) ) {
				return false;
			}
		}
		return true;
	}

	function quit()
	{
		$this->clearStatus();
		$this->log( "Send: QUIT" );
		if( !fputs($this->ctx, "QUIT\r\n") ) {
			return false;
		}
		return true;
	}

	function setLogger( $func )
	{
		$this->logger =& $func;
	}

	function setUidFilter( $func )
	{
		$this->uidl_filter =& $func;
	}

	function setSizeFilter( $func )
	{
		$this->size_filter =& $func;
	}

	function setStatCallback( $func )
	{
		$this->stat_callback =& $func;
	}

	function setRetrCallback( $func )
	{
		$this->retr_callback =& $func;
	}

	function setFallbackCharset( $charset )
	{
		$this->fallback_charset = $charset;
	}

	function uidl()
	{
		$this->clearStatus();
		$this->log( "Send: UIDL" );
		if( !fputs($this->ctx, "UIDL\r\n") ) {
			return false;
		}
		if( !$this->receiveResult(true) ) {
			return false;
		}
		$this->uids = array();
		$this->mails = array();
		foreach( $this->results as $line ) {
			list( $number, $uid ) = explode( " ", $line );
			if( !empty($this->filterred[$number]) ) {
				continue;
			}
			if( $this->uidl_filter && call_user_func($this->uidl_filter, $uid, $number) ) {
				$this->filterred[$number] = true;
				continue;
			}
			$this->uids[$number] = $uid;
			$this->mails[$uid] = $number;
		}
		return true;
	}

	function list_size()
	{
		$this->clearStatus();
		$this->log( "Send: STAT" );
		if( !fputs($this->ctx, "STAT\r\n") ) {
			return false;
		}
		if( !$this->receiveResult(false) ) {
			return false;
		}
		list( $total, $totalsize ) = explode( " ", $this->status );
		if( $this->stat_callback ) {
			if( !call_user_func( $this->stat_callback, $total, $totalsize ) ) {
				return false;
			}
		}

		if( $total == 0 ) {
			return false;
		}

		$this->log( "Send: LIST" );
		if( !fputs($this->ctx, "LIST\r\n") ) {
			return false;
		}
		if( !$this->receiveResult(true) ) {
			return false;
		}
		foreach( $this->results as $line ) {
			list( $number, $size ) = explode( " ", $line );
			if( !empty($this->filterred[$number]) ) {
				continue;
			}
			if( $this->size_filter && call_user_func($this->size_filter, $size, $number, $total) ) {
				if( isset( $this->uids[$number] ) ) {
					unset( $this->mails[$this->uids[$number]] );
					unset( $this->uids[$number] );
				}
				$this->filterred[$number] = true;
			}
		}
		return true;
	}

	function run()
	{
		if( !$this->list_size() ) { return; }
		if( !$this->uidl() ) { return; }
		if( !$this->retr() ) { return; }
	}

	function clearStatus()
	{
		$this->status = '';
		$this->error = '';
		$this->results = array();
	}

	function receiveResult($checkPeriod=false)
	{
		$count = 1000000; /* Maximum 1000000 lines! it's enough to handle 6MB bytes */
		$line = fgets($this->ctx, 1024); 
		$this->log( "Recv: ".trim($line) );
		if( !$line ) {
			return false;
		}
		if( !$this->checkStatus( $line ) ) {
			return false;
		}
		if( !$checkPeriod ) {
			return true;
		}
		$this->results = array();
		while($count--) {
			$line = fgets($this->ctx, 1024); 
			$line = trim($line);
			if( $line == '.' ) { break; }
			array_push($this->results, $line);
			//echo $line."<br/>";
		}
		return true;
	}

	function checkStatus( $line )
	{
		$this->status = substr( $line, 4 );
		if( substr($line,0,3) == '+OK' ) {
			return true;
		}
		$this->error = $this->status;
		return false;
	}

	function getLastError()
	{
		return $this->error;
	}

	function _decode_header_core( $matches )
	{
		static $rewrite_charset = array(
			'ks_c_5601-1987' => 'euc-kr'
		);
		switch( strtolower($matches[2]) ) {
		case 'b':
			$content = base64_decode( $matches[3] );
			break;
		case 'q':
			$content = quoted_printable_decode( $matches[3] );
			break;
		default:
			return '';
		}
		$charset = strtolower($matches[1]);

		if( $charset != 'utf-8' && $charset != 'utf8' ) {
			if( isset($rewrite_charset[$charset]) ) {
				$charset = $rewrite_charset[$charset];
			}
			$content = @iconv( $charset, 'utf-8', $content );
		}
		return $content;
	}

	function decode_header($hdr)
	{
		if( strstr( $hdr, "=?" ) ) {
			$hdr = preg_replace_callback( "/=\?([^?]+)\?([BbQq])\?([^?]+)\?=/",  array( &$this, '_decode_header_core' ), $hdr );
		} else {
			if( !empty($this->fallback_charset) ) {
				$hdr = iconv( $this->fallback_charset, 'utf-8', $hdr );
			}
		}
		return $hdr;
	}

	function parse( & $data, $begin = 0, $end = 0 )
	{
		if( $end == 0 ) { $end = count($data); }

		$mail = array();

		/* Header explorer */
		for( $i=$begin; isset($data[$i]); $i++ ) {
			$line =& $data[$i];
			if( empty($line) ) {
				$i++; /* Skip the blank line */
				break;
			}
			/* treat multilined header, with keeping the $data indices */
			if( $line == "__SKIP__\n" ) {
				continue;
			}
			for( $j=$i+1; isset($data[$j]); $j++ ) {
				if( empty($data[$j]) || $data[$j][0] != ' ' ) { break; }
				$line = $line . ltrim( $data[$j] );
				$data[$j] = "__SKIP__\n";
			}
			
			if( preg_match( '/.*boundary=["\']?([^"\']+)["\']?/i', $line, $match ) ) {
				$mail['boundary'] = "--{$match[1]}";
			}
			if( !isset( $mail['filename'] ) && preg_match( '/.*(file)?name=["\']?([^"\']+)["\']?/i', $line, $match ) ) {
				$mail['filename'] = $this->decode_header($match[2]);
			}
			if( preg_match( '/^(X-MMS(?:[^:]+)):\s*([a-zA-Z\/.-]+);?.*/i', $line, $match ) ) {
				if( !isset($mail['mms']) ) {
					$mail['mms'] = array();
				}
				$mail['mms'][$match[1]] = $match[2];
			}
			if( preg_match( '/^Content-Type:\s*([a-zA-Z\/.-]+);?.*/i', $line, $match ) ) {
				$mail['content_type'] = strtolower($match[1]);
				if( preg_match( '/^charset=["\']?([^"\']+)["\']?/i', $line, $match ) ) {
					$mail['content_charset'] = $match[1];
				}
			}
			if( !isset( $mail['return_path'] ) && preg_match( '/^Return-Path:\s*(.*)/i', $line, $match ) ) {
				$mail['return_path'] = $match[1];
			}
			if( !isset( $mail['subject'] ) && preg_match( '/^Subject:\s*(.*)/i', $line, $match ) ) {
				$mail['subject'] = $this->decode_header($match[1]);
			}
			if( !isset( $mail['date'] ) && preg_match( '/^Date:\s*(.*)/i', $line, $match ) ) {
				$match[1] = str_replace( "Wen", "Wed", $match[1] ); /* SKT date header bug, #1036 */
				$mail['date'] = strtotime( $match[1] );
				$mail['date_string'] = strftime( "%Y-%m-%d", $mail['date'] );
				$mail['time_string'] = strftime( "%H:%M:%S", $mail['date'] );
				$mail['date_year'] =   strftime( "%Y", $mail['date'] );
				$mail['date_month'] =  strftime( "%m", $mail['date'] );
				$mail['date_day'] =    strftime( "%d", $mail['date'] );
			}
			if( !isset( $mail['from'] ) && preg_match( '/^From:([^<]*)<(.*)>/i', $line, $match ) ) {
				$mail['sender'] = trim($this->decode_header($match[1]));
				$mail['from'] = $match[2];
			}
			if( !isset( $mail['content_transfer_encoding'] ) && preg_match( '/^Content-Transfer-Encoding:\s*(\S+)/i', $line, $match ) ) {
				$mail['content_transfer_encoding'] = strtolower($match[1]);
			}
		}

		/* Body explorer */
		if( empty($mail['boundary']) ) {
			$mail['content'] = join( "\r\n", array_slice( $data, $i, $end-$i ) );
			if( isset( $mail['content_transfer_encoding'] ) && $mail['content_transfer_encoding'] ==  'base64' ) {
				$mail['decoded_content'] = base64_decode( $mail['content'] );
			} else {
				$mail['decoded_content'] = $mail['content'];
			}
			$mail['length'] = strlen($mail['decoded_content']);
			if( isset($mail['content_type']) && substr($mail['content_type'],0,4) == 'text' ) {
				$mail['text_type'] = $mail['content_type'];
				$mail['text'] = $mail['decoded_content'];
				if( !empty($mail['content_charset']) ) {
					if(strtolower($mail['content_charset']) != 'utf-8') {
						$mail['text'] = iconv( $mail['content_charset'], 'utf-8', $mail['text'] );
					}
				} elseif( isset($this->fallback_charset) ) {
					if(strtolower($mail['content_charset']) != 'utf-8') {
						$mail['text'] = iconv( $this->fallback_charset, 'utf-8//IGNORE', $mail['text'] );
					}
				}
			}
		} else {
			$parts = array();
			$part_begin = $i;
			for( ; $i<$end; $i++ )
			{
				if( $data[$i] == $mail['boundary'] || $data[$i] == "{$mail['boundary']}--" ) {
					array_push( $parts, array( $part_begin, $i ) );
					$part_begin = $i+1;
				}
			}
			array_shift( $parts ); // Skip the preamble before body
			$mail['parts'] = array();
			$mail['attachments'] = array();
			foreach( $parts as $slice ) {
				$part = $this->parse( $data, $slice[0], $slice[1] );
				if( strstr( $part['content_type'], 'image' ) ) {
					array_push( $mail['attachments'], $part );
				}
				if( isset($mail['text']) && strstr($mail['text_type'],'plain') &&
					isset( $part['text_type'] ) && strstr($part['text_type'],'html') ) {
					$mail['text'] = $part['text'];
					$mail['text_type'] = $part['text_type'];
				}
				if( !isset($mail['text']) && isset( $part['text'] ) ) {
					$mail['text'] = $part['text'];
					$mail['text_type'] = $part['text_type'];
				}
				array_push( $mail['parts'], $part );
			}
		}
		return $mail;
	}
}
