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
require ROOT . '/library/preprocessor.php';
if (empty($suri['value']))
	Respond::NotFoundPage();
if (!$attachment = getAttachmentByOnlyName($blogid, $suri['value']))
	Respond::NotFoundPage();
// Textcube-NG: 글에 딸린 첨부(parent > 0)는 그 글을 볼 수 있을 때만 내준다 — 글 보기(interface/common/blog/entries.php)와 같은 판정(r3-3).
// 비공개·예약 글(getEntry() 가 null)과 비공개 분류의 글은 404, 보호글(visibility 1)은 GUEST_PASSWORD 쿠키가 글 비밀번호와 같을 때만(키로그·공지는 비밀번호 확인 없음, entries.php 와 같음).
// 블로그 로고(parent -1)와 저장 전 임시 첨부(parent 0, -2 이하)는 원본 동작 그대로.
if (!doesHaveOwnership() && ($attachment['parent'] > 0)) {
	$attachmentEntry = getEntry($blogid, (int)$attachment['parent']);
	if (empty($attachmentEntry))
		Respond::NotFoundPage();
	if (in_array($attachmentEntry['category'], getCategoryVisibilityList($blogid, 'private')))
		Respond::NotFoundPage();
	if (!in_array((int)$attachmentEntry['category'], array(-1, -2), true) && ($attachmentEntry['visibility'] < 2)) {
		// PHP port(Textcube-NG): r3-10 R-283 보호글 비밀번호 쿠키 비교를 library/auth.php checkGuestPasswordCookie() 로(문자열만, hash_equals)
		if (!checkGuestPasswordCookie($attachmentEntry['password']))
			Respond::NotFoundPage();
	}
} else if (($attachment['parent'] > 0) && !Acl::check('group.editors') && empty(getEntry($blogid, (int)$attachment['parent']))) {
	// Textcube-NG: 글관리 권한(group.editors)이 없는 구성원은 남의 비공개·예약 글 첨부를 받지 못한다 — getEntry() 의 작성자 조건(v3.145)을 그대로 쓴다(r3-15, R-263)
	Respond::NotFoundPage();
}
$fp = fopen(__TEXTCUBE_ATTACH_DIR__."/$blogid/{$attachment['name']}", 'rb');
if (!$fp)
	Respond::NotFoundPage();
$fstat = fstat($fp);
if (!empty($_SERVER['HTTP_IF_MODIFIED_SINCE'])) {
	$modifiedSince = strtotime($_SERVER['HTTP_IF_MODIFIED_SINCE']);
	if ($modifiedSince && ($modifiedSince >= $fstat['mtime'])) {
		fclose($fp);
		header('HTTP/1.1 304 Not Modified');
		header('Connection: close');
		exit;
	}
}

/* PHP port: 부분 요청(RFC 9110 14장)·UTF-8 파일 이름(RFC 6266·RFC 8187).
   - GET 의 Range 는 단일 바이트 범위(bytes=시작-끝, 시작-, -끝에서 n)만 처리: 206 + Content-Range, 범위가 파일 밖이면
     416 + Content-Range: bytes 크기. 여러 범위·형식 오류·다른 단위·If-Range 불일치(Last-Modified 와 정확히 같은
     날짜만 일치, ETag 는 보내지 않음)·빈 파일은 Range 를 무시하고 전체(200)를 보낸다(RFC 9110 허용).
   - HEAD 는 파일을 읽지 않는다. 다운로드 수는 HEAD 가 아니고 0 바이트부터 보내는 응답에서만 센다(이어받기·탐색 요청 중복 방지).
   - Content-Disposition: 원본의 filename="퍼센트 인코딩 이름" 은 그대로 두고(filename* 미지원 클라이언트) filename*=UTF-8''… 를 더한다. */
function getAttachmentRangeOffset($digits) {
	$digits = ltrim($digits, '0');
	if ($digits === '')
		return 0;
	return (strlen($digits) > 15) ? PHP_INT_MAX : intval($digits);	// 15자리 초과는 파일 크기보다 큼 → 상한
}

$method = isset($_SERVER['REQUEST_METHOD']) ? strtoupper($_SERVER['REQUEST_METHOD']) : 'GET';
$size = $fstat['size'];
$lastModified = Timestamp::getRFC1123GMT($fstat['mtime']);
$rangeFirst = 0;
$rangeLast = $size - 1;
$partial = false;
if (($method == 'GET') && ($size > 0) && isset($_SERVER['HTTP_RANGE']) && is_string($_SERVER['HTTP_RANGE'])
	&& (!isset($_SERVER['HTTP_IF_RANGE']) || (trim($_SERVER['HTTP_IF_RANGE']) === $lastModified))
	&& preg_match('/^bytes=(\d*)-(\d*)$/i', trim($_SERVER['HTTP_RANGE']), $range)
	&& (($range[1] !== '') || ($range[2] !== ''))) {
	if ($range[1] === '') {	// 끝에서 n 바이트(n 이 0 이면 범위 밖)
		$suffix = getAttachmentRangeOffset($range[2]);
		$rangeFirst = ($suffix > 0) ? max(0, $size - $suffix) : $size;
		$validRange = true;
	} else {
		$rangeFirst = getAttachmentRangeOffset($range[1]);
		$rangeEnd = ($range[2] === '') ? null : getAttachmentRangeOffset($range[2]);
		$validRange = (($rangeEnd === null) || ($rangeEnd >= $rangeFirst));	// 끝 < 시작 은 형식 오류 → Range 무시
		if ($validRange && ($rangeEnd !== null))
			$rangeLast = min($rangeLast, $rangeEnd);
	}
	if ($validRange) {
		if ($rangeFirst >= $size) {
			fclose($fp);
			header('HTTP/1.1 416 Range Not Satisfiable');
			header('Content-Range: bytes */' . $size);
			header('Connection: close');
			exit;
		}
		$partial = true;
	} else
		$rangeFirst = 0;
}

ini_set('zlib.output_compression', 'off');
$label = (string)UTF8::bring($attachment['label']);
header('Content-Disposition: attachment; filename="' . rawurlencode($label) . '"' . (($label !== '') ? "; filename*=UTF-8''" . rawurlencode($label) : ''));
header('Content-Transfer-Encoding: binary');
header('Last-Modified: ' . $lastModified);
header('Accept-Ranges: bytes');
if ($partial) {
	header('HTTP/1.1 206 Partial Content');
	header('Content-Range: bytes ' . $rangeFirst . '-' . $rangeLast . '/' . $size);
}
header('Content-Length: ' . ($rangeLast - $rangeFirst + 1));
// Textcube-NG: 첨부 응답 헤더(r3-20) — 모든 첨부에 nosniff, svg(mime 이나 이름)는 CSP sandbox(문서로 열려도 스크립트·외부 불러오기 차단),
// mime 이 비어 있으면 application/octet-stream
$attachmentMime = (string)$attachment['mime'];
header('Content-Type: ' . (($attachmentMime !== '') ? $attachmentMime : 'application/octet-stream'));
header('X-Content-Type-Options: nosniff');
if ((strtolower($attachmentMime) === 'image/svg+xml') || preg_match('/\.svgz?$/i', (string)$attachment['name']))
	header("Content-Security-Policy: default-src 'none'; style-src 'unsafe-inline'; img-src data:; sandbox");
header('Cache-Control: private');
header('Pragma: no-cache');
header('Connection: close');
if ($method != 'HEAD') {
	if ($partial) {
		if (($rangeFirst == 0) || (fseek($fp, $rangeFirst) === 0)) {
			$output = fopen('php://output', 'wb');
			stream_copy_to_stream($fp, $output, $rangeLast - $rangeFirst + 1);
			fclose($output);
		}
	} else
		fpassthru($fp);
}
fclose($fp);
if (($method != 'HEAD') && ($rangeFirst == 0))
	downloadAttachment($attachment['name']);
?>
