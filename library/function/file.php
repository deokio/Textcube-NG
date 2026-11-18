<?php
/// Copyright (c) 2004-2016, Needlworks  / Tatter Network Foundation
/// All rights reserved. Licensed under the GPL.
/// See the GNU General Public License for more details. (/documents/LICENSE, /documents/COPYRIGHT)

function getFileExtension($path) {
	for ($i = strlen($path) - 1; $i >= 0; $i--) {
		if ($path[$i] == '.')
			return strtolower(substr($path, $i + 1));
		if (($path[$i] == '/') || ($path[$i] == '\\'))
			break;
	}
	return '';
}

function getMIMEType($ext, $filename = null) {
	if ($filename) {
		return getMIMEType(getFileExtension($filename));
	} else {
		switch (strtolower($ext)) {
			// Image
			case 'gif':
				return 'image/gif';
			case 'jpeg':case 'jpg':case 'jpe':
				return 'image/jpeg';
			case 'png':
				return 'image/png';
			case 'tiff':case 'tif':
				return 'image/tiff';
			case 'bmp':
				return 'image/bmp';
			// Textcube-NG: webp·avif·svg(r3-20)
			case 'webp':
				return 'image/webp';
			case 'avif':
				return 'image/avif';
			case 'svg':
				return 'image/svg+xml';
			// Sound
			case 'wav':
				return 'audio/x-wav';
			case 'mpga':case 'mp2':case 'mp3':
				return 'audio/mpeg';
			case 'm3u':
				return 'audio/x-mpegurl';
			case 'wma':
				return 'audio/x-msaudio';
			case 'ra':
				return 'audio/x-realaudio';
			// Document
			case 'css':
				return 'text/css';
			case 'html':case 'htm':case 'xhtml':
				return 'text/html';
			case 'rtf':
				return 'text/rtf';
			case 'sgml':case 'sgm':
				return 'text/sgml';
			case 'xml':case 'xsl':
				return 'text/xml';
			case 'hwp':case 'hwpx':case 'hwpml':
				return 'application/x-hwp';
			case 'pdf':
				return 'application/pdf';
			case 'js':
				return 'application/javascript';
			case 'odt':case 'ott':
				return 'application/vnd.oasis.opendocument.text';
			case 'ods':case 'ots':
				return 'application/vnd.oasis.opendocument.spreadsheet';	
			case 'odp':case 'otp':
				return 'application/vnd.oasis.opendocument.presentation';
			case 'sxw':case 'stw':
				return 'application/vnd.sun.xml.writer';
			case 'sxc':case 'stc':
				return 'application/vnd.sun.xml.calc';
			case 'sxi':case 'sti':
				return 'application/vnd.sun.xml.impress';
			case 'doc':
				return 'application/vnd.ms-word';
			case 'xls':case 'xla':case 'xlt':
			case 'xlb':
				return 'application/vnd.ms-excel';			
			case 'ppt':case 'ppa':case 'pot':case 'pps':
				return 'application/vnd.mspowerpoint';
			case 'vsd':case 'vss':case 'vsw':
				return 'application/vnd.visio';
			case 'docx':case 'docm':
			case 'pptx':case 'pptm':
			case 'xlsx':case 'xlsm':	
				return 'application/vnd.openxmlformats';
			case 'csv':
				return 'text/comma-separated-values'; 
			// Multimedia
			case 'mpeg':case 'mpg':case 'mpe':
				return 'video/mpeg';
			case 'qt':case 'mov':
				return 'video/quicktime';
			case 'avi':case 'wmv':
				return 'video/x-msvideo';
			// Compression
			case 'bz2':
				return 'application/x-bzip2';
			case 'gz':case 'tgz':
				return 'application/x-gzip';
			case 'tar':
				return 'application/x-tar';
			case 'zip':
				return 'application/zip';
			case 'rar':
				return 'application/x-rar-compressed';
			case '7z':
				return 'application/x-7z-compressed';
			case 'alz':
				return 'application/x-alzip';				
		}
	}
	return '';
}

function headerEtag($etag,$length,$lastmodified) {
	$cache_hit = false;
	if( isset( $_SERVER['HTTP_IF_NONE_MATCH'] ) || isset( $_SERVER['HTTP_IF_MODIFIED_SINCE'] ) ) { 
		$cache_hit = true; 
		if( isset( $_SERVER['HTTP_IF_NONE_MATCH'] ) && $etag != $_SERVER['HTTP_IF_NONE_MATCH'] ) { 
			$cache_hit = false; 
		} else if( isset( $_SERVER['HTTP_IF_MODIFIED_SINCE'] ) && $lastmodified != $_SERVER['HTTP_IF_MODIFIED_SINCE'] ) {
			$cache_hit = false;
		}   
	}   

	if( $cache_hit ) { header("HTTP/1.1 304 Not Modified", true, 304); } 
	else {
		header("HTTP/1.1 200 Ok", true, 200);
		header("Last-Modified: " . $lastmodified);
		header("Date: " . gmdate("D, j M Y H:i:s ", time()) . " GMT");
		header("Expires: " . gmdate("D, j M Y H:i:s", time() + 3600*24) . " GMT");
		header("Cache-Control: max-age=" . 3600*24);
		header("Pragma: cache");        // HTTP/1.0
		header("Content-Length: $length");
	}   
	header("ETag: $etag");

	return $cache_hit;
}

function dumpWithEtag($path) {
	$path = urldecode($path);
	$qIndex = strpos($path,'?');
	if( $qIndex !== false ) {
		$path = substr($path,0,$qIndex);
	}
	/* I think, it is a bad idea to check '..' and skip.
	   but this is an annoyance to solve gracefully about whole HTTP request */
	/* Kill them all requests with referencing parent directory */
	if( strpos( $path, "/.." ) !== false || 
		strpos( $path, "\\.." ) !== false ||
		strcasecmp( substr( $path, -3 ), "php" ) == 0 ||
		!file_exists( $path ) ||
		is_dir( $path ) ) {	// Textcube-NG: 디렉터리는 404(fopen·fread 가 실패해 200 + 빈 본문이 되던 것)(v3.195)
		header("HTTP/1.0 404 Not found");
		exit;
	}
		
	$fs = stat( $path );
	if( !$fs || !$fs['size'] ) { header('HTTP/1.1 404 Not Found');exit; }
	$etag = sprintf( "textcube-%x", (0x1234*$fs['size'])^$fs['mtime'] );
	$lastmodified = gmdate("D, j M Y H:i:s ", $fs['mtime']) . "GMT";
	$length = $fs['size'];

	if( !headerEtag($etag,$length,$lastmodified) ) {
		// Textcube-NG: attach 아래 파일만 nosniff, MIME 을 모르면 application/octet-stream. 그 밖 경로(resources·plugins·skin·썸네일·기본 favicon)는
		// 원본처럼 getMIMEType 결과 그대로(.js 처럼 MIME 표에 없는 파일이 nosniff 로 막히지 않게). svg 는 경로와 상관없이 CSP sandbox.
		// attach 판정: 호출처는 Dispatcher 직접 전달 분기(현재 폴더 기준 상대 경로 'attach/…')와 interface/favicon.ico.php(__TEXTCUBE_ATTACH_DIR__ 절대 경로)이므로
		// realpath 로 바꿔 attach 폴더 realpath 의 하위인지 본다(decisions D11·헤더 범위, 사용자 11-12 "attach 경로에만 적용")(r3-20)
		$mime = getMIMEType(null, $path);
		$attachDir = defined('__TEXTCUBE_ATTACH_DIR__') ? __TEXTCUBE_ATTACH_DIR__ : (defined('ROOT') ? ROOT . '/attach' : '');
		$realFile = realpath($path);
		$realAttach = ($attachDir !== '') ? realpath($attachDir) : false;
		if ($realFile !== false && $realAttach !== false && strncmp($realFile, $realAttach . DIRECTORY_SEPARATOR, strlen($realAttach) + 1) === 0) {
			header('Content-type: ' . ($mime !== '' ? $mime : 'application/octet-stream'));
			header('X-Content-Type-Options: nosniff');
			// Textcube-NG: r3-27 R-434 — attach 아래 .html·.htm·.xhtml 은 문서로 열리지 않고 내려받게 한다(.htaccess·nginx 설정과 같은 규칙. PHP 로 전달되는 경로용)
			if (preg_match('/\.x?html?$/i', $path) === 1) header('Content-Disposition: attachment');
		} else {
			header('Content-type: ' . $mime);
		}
		if ($mime === 'image/svg+xml') header("Content-Security-Policy: default-src 'none'; style-src 'unsafe-inline'; img-src data:; sandbox");
		$f =  fopen($path,"r");
		if( !$f ) {
			header("HTTP/1.0 404 Not found");
			exit;
		}
		while( ($content=fread($f,8192)) ){
			echo $content;
		}
		fclose($f);
	}
}

function copyRecusive($source, $target, $chkPrint = false) {
	if (Path::getBaseName($source) == "." || Path::getBaseName($source) == "..") {
		return;
	}
	if (!is_dir($source)) {
		copy($source, $target);
		return;
	}
	if (!file_exists($target) || !is_dir($target)) {
		mkdir($target);
		@chmod($target, 0777);
	}
	$d = dir($source);
	while ($entry = $d->read()) {
		copyRecusive("$source/$entry", "$target/$entry", $chkPrint);
	}
	$d->close();
}

function deltree($dir) {
	$d = dir($dir);
	while ($f = $d->read()) {
		if ($f != "." && $f != "..") {
			if (is_dir($dir . $f)) {
				deltree($dir . $f . "/");
				rmdir($dir . $f);
			}
			if (is_file($dir . $f))
				unlink($dir . $f);
		}
	}
	$d->close();
}

function deleteFilesByRegExp($path, $regexp) {
	$path = rtrim($path, '/') . '/';
	
	if (!file_exists($path))
		return false;
	
	$handle = opendir($path);
	while ($tempFile = readdir($handle)) {
		if ($tempFile == '.' || $tempFile != '..') {
			continue;
		}
		if ($regexp == '*') {
			@unlink($path.$tempFile);
		} elseif (preg_match($regexp, $tempFile, $temp)) {
			@unlink($path.$tempFile);
		}
	}
	return true;
}
?>
