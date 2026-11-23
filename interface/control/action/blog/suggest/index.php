<?php
/// Copyright (c) 2004-2016, Needlworks  / Tatter Network Foundation
/// All rights reserved. Licensed under the GPL.
/// See the GNU General Public License for more details. (/documents/LICENSE, /documents/COPYRIGHT)
// Textcube-NG: 도메인 라우팅 설정(control/server)의 블로그 ID 칸에 쓰는 블로그 제안. GET 요청, 서비스 관리자(group.creators)만(v3.220)
// input 은 블로그 구분자(BlogSettings name='name')의 앞부분(영문·숫자·-, 1~32자, 대소문자 구분 없음). DB 에서 LOWER(value) LIKE '앞부분%' 로
// 블로그 번호 순 20개까지 먼저 거르고, PHP 에서 대소문자 무관 앞부분 비교로 다시 확인해 최대 5개를 JSON 으로 내보낸다.
// 값(LIKE 패턴)은 준비 문장으로 SQL 과 따로 보낸다(v3.211 POD::prepare·bindAndExecute 관례). 입력은 위 정규식을 통과한 것만 쓰므로
// LIKE 의 % _ 나 escape 문자가 들어가지 않는다. HTML 특수문자는 JSON_HEX_* 로 바꾼다(v3.220)
define('__TEXTCUBE_CUSTOM_HEADER__', true);
$IV = array(
	'GET' => array(
		'input' => array('string', 'default' => '')
	)
);
require ROOT . '/library/preprocessor.php';
requireStrictRoute();
requirePrivilege('group.creators');
// Textcube-NG: 읽기 전용 제안이므로 GET·HEAD 만 받는다 — 그 밖의 메서드는 405(requirePostMethod 와 같은 응답). REQUEST_METHOD 가 없으면 GET 으로 본다(r3-3)
$requestMethod = isset($_SERVER['REQUEST_METHOD']) ? strtoupper((string)$_SERVER['REQUEST_METHOD']) : 'GET';
if (!in_array($requestMethod, array('GET', 'HEAD'), true)) {
	header('HTTP/1.1 405 Method Not Allowed');
	header('Allow: GET, HEAD');
	exit;
}

$prefix = strtolower((string)$_GET['input']);
$blogs = array();
if (preg_match('/^[-a-z0-9]{1,32}$/D', $prefix)) {
	$reserved = POD::reservedFieldNames();
	$valueField = (is_array($reserved) && in_array('value', $reserved)) ? '"value"' : 'value';	// Cubrid 는 value 가 예약어라 " 로 감싼다(DBModel 과 같음)
	$rows = null;
	$stmt = POD::prepare('SELECT blogid, ' . $valueField . ' FROM ' . $database['prefix'] . 'BlogSettings'
		. " WHERE name = 'name' AND LOWER(" . $valueField . ') LIKE ? ORDER BY blogid ASC LIMIT 20');
	if ($stmt) {
		$rows = POD::bindAndExecute($stmt, 's', $prefix . '%') ? POD::fetchAllStmt($stmt) : null;
		$stmt->close();
	}
	if (is_array($rows)) {
		$length = strlen($prefix);
		foreach ($rows as $row) {
			if (!isset($row['blogid'], $row['value']) || !is_string($row['value']) || (strtolower(substr($row['value'], 0, $length)) !== $prefix))
				continue;
			$blogs[] = array('id' => intval($row['blogid']), 'name' => $row['value'], 'title' => '');
			if (count($blogs) >= 5)
				break;
		}
	}
	foreach ($blogs as $index => $blog) {
		$title = Setting::getBlogSettingGlobal('title', '', $blog['id'], true);
		$blogs[$index]['title'] = is_string($title) ? $title : '';
	}
}
header('Content-Type: application/json; charset=utf-8');
$json = jsonEncodeCompat(array('error' => 0, 'blogs' => $blogs), JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_UNESCAPED_UNICODE);	// Textcube-NG: JSON_INVALID_UTF8_SUBSTITUTE(7.2) 대신 jsonEncodeCompat()(00-Compat.php, r4-4)
echo ($json === false) ? '{"error":1,"blogs":[]}' : $json;
?>
