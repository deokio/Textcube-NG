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

$IV = array(
	'GET' => array(
		'id' => array('string'),
		'input' => array('string','default' => ''),
		'cursor' => array('number', 'min' => 1)
	) 
);
require ROOT . '/library/preprocessor.php';
requireStrictRoute();
requirePrivilege('group.creators');

header('Content-type: text/javascript');

// SQL Injection 대응 (upstream refs #747, commit 9c73a64): raw 쿼리 → DBModel 빌더 전환.
// upstream의 init()은 본 포트에 없는 신규 별칭이므로 reset()으로 적응. 본 포트의
// getQualifierModel은 escape=null일 때 escape하지 않으므로 두 qualifier 모두 escape=true 지정.
// 방어 검증: _sectest/suggest_sqli_test.php (악성 입력 7종 ALL PASS) — SECURITY.md 참조.
$pool = DBModel::getInstance();
$pool->reset("Users");
$pool->setQualifierSet(array("name","like",'%'.escapeSearchString($_GET['input']).'%',false),
	"OR",
	array("loginid","like",'%'.escapeSearchString($_GET['input']).'%',false));
$pool->setLimit(5);
$result = $pool->getAll("loginid, name");
if ($result) {
	echo 'ctlUserSuggestFunction_showSuggestion("'.escapeJSInCData($_GET['id']).'","'.escapeJSInCData($_GET['cursor']).'",';
	echo '"0"'; //TODO : clear
	foreach($result as $row) {
		// XSS 대응: 결과 행은 control.js showSuggestion 의 innerHTML sink 으로 삽입됨.
		// htmlspecialchars(HTML escape) 후 JS 문자열 리터럴 안전화(백슬래시·개행 escape).
		// control.js가 &quot;를 되돌리는 설계 전제에 부합. 검증: _sectest/run_xss_innerhtml_node.js
		$suggestRow = htmlspecialchars($row['loginid'] . " - " . $row['name'], ENT_QUOTES);
		$suggestRow = str_replace(array("\\", "\r", "\n"), array("\\\\", "\\r", "\\n"), $suggestRow);
		echo ',"' . $suggestRow . '"';
	}
	echo ');';
}
else {
	echo 'ctlUserSuggestFunction_showSuggestion("'.escapeJSInCData($_GET['id']).'","'.escapeJSInCData($_GET['cursor']).'",';
	echo '"-1"'; //TODO : clear
	echo ');';
}
?>
