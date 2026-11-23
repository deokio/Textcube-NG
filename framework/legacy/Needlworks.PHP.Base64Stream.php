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
class Base64Stream {
	/*@static@*/
	static function encode($src = null, $dest = null) {
		if (!is_string($src) || (!is_null($dest) && !is_a($dest, 'OutputWriter')))
			return false;
		if (!$fp = fopen($src, 'rb'))
			return false;
		// 3바이트 배수 단위로만 인코딩하여 짧은 읽기가 있어도 중간 패딩('=')이 생기지 않도록 한다.
		$remains = '';
		while (!feof($fp)) {
			if (($data = fread($fp, 3 * 1024)) === false)
				break;
			$data = $remains . $data;
			$length = strlen($data) - strlen($data) % 3;
			$remains = substr($data, $length);
			if ($length > 0)
				Base64Stream::_write(base64_encode(substr($data, 0, $length)), $dest);
		}
		if (strlen($remains) > 0)
			Base64Stream::_write(base64_encode($remains), $dest);
		fclose($fp);
		return true;
	}

	/*@static@*/
	static function decode($src = null, $dest = null) {
		if (!is_resource($src) || (get_resource_type($src) != 'stream') || !is_string($dest))
			return false;
		if (!$fp = fopen($dest, 'wb'))
			return false;
		$result = rewind($src);
		$buffer = '';
		while ($result && !feof($src)) {
			if (($data = fread($src, 4 * 1024)) === false) {
				$result = false;
				break;
			}
			// base64 알파벳 외 문자(줄바꿈·공백 등)를 제거하고 4문자 단위로만 디코딩한다.
			$buffer .= preg_replace('/[^A-Za-z0-9+\/=]/', '', $data);
			$length = strlen($buffer) - strlen($buffer) % 4;
			if ($length > 0) {
				if (fwrite($fp, Base64Stream::_decodeQuanta(substr($buffer, 0, $length))) === false)
					$result = false;
				$buffer = substr($buffer, $length);
			}
		}
		if ($result && (strlen($buffer) > 0) && (fwrite($fp, base64_decode($buffer)) === false))
			$result = false;
		fclose($fp);
		return $result;
	}

	private static function _decodeQuanta($data) {
		// 중간 패딩이 있는 4문자 단위에서 끊어 디코딩한다. (비엄격 base64_decode 는 중간 패딩 이후를 어긋나게 해석)
		$output = '';
		$start = 0;
		while (($position = strpos($data, '=', $start)) !== false) {
			$end = $position - $position % 4 + 4;
			$output .= base64_decode(substr($data, $start, $end - $start));
			$start = $end;
		}
		return $output . base64_decode(substr($data, $start));
	}

	private static function _write($data, $dest) {
		if (is_null($dest))
			echo $data;
		else
			$dest->write($data);
	}
}
?>
