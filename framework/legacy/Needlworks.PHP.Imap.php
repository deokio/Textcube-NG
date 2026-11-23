<?php
/// Copyright (c) 2004-2016, Needlworks  / Tatter Network Foundation
/// All rights reserved. Licensed under the GPL.
/// See the GNU General Public License for more details. (/documents/LICENSE, /documents/COPYRIGHT)

/**
 * Minimal IMAP4rev1 (RFC 3501) client.
 *
 * Implements the same public interface as the Pop3 class so that CL_Moblog
 * can swap protocols without any changes to the plugin logic.
 *
 * Inherited from Pop3 (no override needed):
 *   parse(), decode_header(), _decode_header_core(), log(),
 *   setLogger(), setUidFilter(), setSizeFilter(), setStatCallback(),
 *   setRetrCallback(), setFallbackCharset(), getLastError(), clearStatus().
 */
class Imap extends Pop3
{
	/** @var int  Monotonically increasing IMAP command tag counter */
	private $tagSeq = 1;

	/** @var string  IMAP mailbox folder to SELECT */
	private $imapFolder = 'INBOX';

	/**
	 * Set the IMAP mailbox folder to monitor (default: INBOX).
	 * Call before run().
	 */
	public function setFolder($folder)
	{
		$this->imapFolder = (string)$folder;
	}

	// ── Protocol methods (override Pop3) ─────────────────────────────────

	/**
	 * Open a TCP connection to the IMAP server and verify the greeting.
	 *
	 * $encryption: 'none' (plain), 'ssl' (implicit SSL/TLS, default port 993),
	 *              'starttls' (STARTTLS upgrade, RFC 2595/3501).
	 * Backward compat: true/1 → 'ssl', false/0/null → 'none'.
	 * Default port: 143 (none/starttls) or 993 (ssl).
	 */
	public function connect($server, $port = 143, $encryption = 'none')
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
		// IMAP greeting is an untagged response: "* OK [...] Ready"
		$line = fgets($this->ctx, 1024);
		if (strncmp(ltrim((string)$line), '* OK', 4) !== 0) {
			$this->error = 'unexpected greeting: ' . trim((string)$line);
			return false;
		}

		if ($encryption === 'starttls') {
			$tag = $this->nextTag();
			$this->log("Send: $tag STARTTLS");
			if (!@fputs($this->ctx, "$tag STARTTLS\r\n")) return false;
			if (!$this->awaitTaggedOk($tag)) {
				$this->error = 'STARTTLS rejected by server';
				return false;
			}
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

	/**
	 * Authenticate using the IMAP LOGIN command.
	 * Both username and password are IMAP-quoted (RFC 3501 §4.3).
	 */
	public function authorize($username, $password)
	{
		$this->clearStatus();
		$tag = $this->nextTag();
		$u   = $this->imapQuote($username);
		$p   = $this->imapQuote($password);
		// [보안] 메일함 계정 ID 는 로그(cache/moblog.txt)에 남기지 않음
		$this->log("Send: $tag LOGIN [USERNAME] [PASSWORD]");
		if (!@fputs($this->ctx, "$tag LOGIN $u $p\r\n")) return false;
		return $this->awaitTaggedOk($tag);
	}

	/**
	 * Main retrieval loop — mirrors Pop3::run().
	 *
	 * 1. SELECT the configured mailbox folder.
	 * 2. FETCH all message UIDs and sizes in a single round-trip.
	 * 3. Fire stat_callback(total, totalsize) — return false aborts.
	 * 4. Apply uidl_filter and size_filter callbacks per message.
	 * 5. FETCH and deliver each qualifying message via retr_callback.
	 *
	 * Callback signatures are identical to Pop3's to allow shared Moblog logic.
	 */
	public function run()
	{
		// ── 1. SELECT mailbox ─────────────────────────────────────────────
		$tag = $this->nextTag();
		$this->log("Send: $tag SELECT " . $this->imapQuote($this->imapFolder));
		if (!@fputs($this->ctx, "$tag SELECT " . $this->imapQuote($this->imapFolder) . "\r\n")) return;
		$this->readTaggedResponse($tag, $ok);
		if (!$ok) {
			$this->log("IMAP SELECT failed: {$this->imapFolder}");
			return;
		}

		// ── 2. Enumerate all UIDs and sizes ───────────────────────────────
		$tag = $this->nextTag();
		$this->log("Send: $tag FETCH 1:* (UID RFC822.SIZE)");
		if (!@fputs($this->ctx, "$tag FETCH 1:* (UID RFC822.SIZE)\r\n")) return;
		$fetchLines = $this->readTaggedResponse($tag, $ok);

		// Parse "* seq FETCH (UID nnn RFC822.SIZE nnn)" — attributes may appear in any order
		$msgInfo = [];   // seq(int) => ['uid'=>string, 'size'=>int]
		$curSeq  = null;
		foreach ($fetchLines as $l) {
			if (preg_match('/^\* (\d+) FETCH/i', $l, $m)) {
				$curSeq = (int)$m[1];
				if (!isset($msgInfo[$curSeq])) $msgInfo[$curSeq] = ['uid' => '', 'size' => 0];
			}
			if ($curSeq !== null) {
				if (preg_match('/\bUID\s+(\d+)/i',          $l, $m)) $msgInfo[$curSeq]['uid']  = (string)(int)$m[1];
				if (preg_match('/\bRFC822\.SIZE\s+(\d+)/i', $l, $m)) $msgInfo[$curSeq]['size'] = (int)$m[1];
			}
		}

		$total     = count($msgInfo);
		$totalsize = (int)array_sum(array_column($msgInfo, 'size'));

		// ── 3. stat_callback (mirrors Pop3::list_size stat branch) ────────
		if ($this->stat_callback) {
			if (!call_user_func($this->stat_callback, $total, $totalsize)) return;
		}
		if ($total === 0) return;

		// ── 4. Apply UID and size filters ─────────────────────────────────
		$filtered = [];
		foreach ($msgInfo as $seq => $info) {
			if ($this->uidl_filter && call_user_func($this->uidl_filter, $info['uid'], $seq)) {
				$filtered[$seq] = true;
				continue;
			}
			if ($this->size_filter && call_user_func($this->size_filter, $info['size'], $seq, $total)) {
				$filtered[$seq] = true;
			}
		}

		// ── 5. Fetch and deliver qualifying messages (mirrors Pop3::retr) ──
		foreach ($msgInfo as $seq => $info) {
			if (!empty($filtered[$seq])) continue;
			$tag = $this->nextTag();
			$this->log("Send: $tag FETCH $seq (RFC822)");
			if (!@fputs($this->ctx, "$tag FETCH $seq (RFC822)\r\n")) continue;
			$msgLines = $this->readLiteralFetch($tag);
			if ($msgLines === false) continue;
			if ($this->retr_callback) {
				call_user_func($this->retr_callback, $msgLines, $info['uid']);
			}
		}
	}

	/**
	 * Send LOGOUT and close the socket.
	 */
	public function quit()
	{
		if ($this->ctx) {
			$tag = $this->nextTag();
			$this->log("Send: $tag LOGOUT");
			@fputs($this->ctx, "$tag LOGOUT\r\n");
			// Drain BYE untagged + tagged OK before closing
			$deadline = time() + 3;
			while (time() < $deadline) {
				$l = @fgets($this->ctx, 256);
				if ($l === false || strncmp(ltrim((string)$l), "$tag OK", strlen($tag) + 3) === 0) break;
			}
			@fclose($this->ctx);
			$this->ctx = null;
		}
		return true;
	}

	// ── Private helpers ────────────────────────────────────────────────────

	/** Returns the next IMAP command tag (TC0001, TC0002, …). */
	private function nextTag()
	{
		return 'TC' . str_pad($this->tagSeq++, 4, '0', STR_PAD_LEFT);
	}

	/**
	 * Read untagged response lines until the server sends the tagged completion.
	 * Sets $ok (by reference) to true on OK, false on NO/BAD/error.
	 * Returns the array of collected untagged lines.
	 */
	private function readTaggedResponse($tag, &$ok)
	{
		$ok    = false;
		$lines = [];
		$pfx   = strlen($tag);
		while (true) {
			$line = @fgets($this->ctx, 8192);
			if ($line === false) return $lines;
			$line = rtrim($line, "\r\n");
			if (strncasecmp($line, "$tag OK",  $pfx + 3) === 0) { $ok = true;  return $lines; }
			if (strncasecmp($line, "$tag NO",  $pfx + 3) === 0) { $ok = false; return $lines; }
			if (strncasecmp($line, "$tag BAD", $pfx + 4) === 0) { $ok = false; return $lines; }
			$lines[] = $line;
		}
	}

	/**
	 * Read lines until the given tag's completion line; return true on OK.
	 * Used where the untagged lines are not needed (e.g. LOGIN response).
	 */
	private function awaitTaggedOk($tag)
	{
		$pfx = strlen($tag);
		while (true) {
			$line = @fgets($this->ctx, 8192);
			if ($line === false) return false;
			$line = rtrim($line, "\r\n");
			if (strncasecmp($line, "$tag OK",  $pfx + 3) === 0) return true;
			if (strncasecmp($line, "$tag NO",  $pfx + 3) === 0) return false;
			if (strncasecmp($line, "$tag BAD", $pfx + 4) === 0) return false;
		}
	}

	/**
	 * Read an IMAP FETCH RFC822 response containing a literal-format message body.
	 *
	 * IMAP literal protocol: "* N FETCH (RFC822 {size}\r\n<size bytes>)\r\n<tag> OK"
	 * Uses fread() for the literal body so binary-safe and boundary-exact.
	 *
	 * Returns an array of trimmed message lines compatible with Pop3::parse(),
	 * or false on protocol error / missing literal.
	 */
	private function readLiteralFetch($tag)
	{
		$rawMsg = null;
		$pfx    = strlen($tag);
		while (true) {
			$line = @fgets($this->ctx, 8192);
			if ($line === false) return false;
			$line = rtrim($line, "\r\n");
			if (strncasecmp($line, "$tag OK",  $pfx + 3) === 0) break;
			if (strncasecmp($line, "$tag NO",  $pfx + 3) === 0) return false;
			if (strncasecmp($line, "$tag BAD", $pfx + 4) === 0) return false;
			// Detect IMAP literal: "* N FETCH (RFC822 {12345}"
			if ($rawMsg === null && preg_match('/\{(\d+)\}\s*$/', $line, $m)) {
				$remaining = (int)$m[1];
				$rawMsg    = '';
				while ($remaining > 0) {
					$chunk = @fread($this->ctx, min($remaining, 8192));
					if ($chunk === false || $chunk === '') break;
					$rawMsg    .= $chunk;
					$remaining -= strlen($chunk);
				}
				// The closing ")" line and tagged OK are consumed in subsequent loop iterations.
			}
		}
		if ($rawMsg === null) return false;
		// Split into trimmed lines — mirrors Pop3::receiveResult(true) which trims each line.
		$lines = explode("\n", str_replace("\r\n", "\n", $rawMsg));
		return array_map('trim', $lines);
	}

	/**
	 * Wrap $str in an IMAP double-quoted string (RFC 3501 §4.3).
	 * Escapes backslash and double-quote; valid for all printable ASCII credentials.
	 */
	private function imapQuote($str)
	{
		return '"' . str_replace(['\\', '"'], ['\\\\', '\\"'], (string)$str) . '"';
	}
}
