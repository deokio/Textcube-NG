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
$IV = array(
	'REQUEST' => array(
		'name' => array('string'),
		'type' => array('int', 'min' => 1, 'max' => 2),
	)
);
require ROOT . '/library/preprocessor.php';
// Textcube-NG: 삭제는 /control/plugin/table/delete(POST)로 옮겼다. 옛 주소는 아무것도 지우지 않고 실패로 끝낸다(v3.210)
Respond::ResultPage(1);
exit;
requireStrictRoute();
/* [보안] 원본은 블로그 번호만 확인해 1번 블로그의 구성원이면 플러그인 데이터 테이블을 삭제할 수 있었다 →
   목록 화면(tableSetting)과 같게 서비스 관리자(group.creators)만 */
if ($blogid != 1 || !Acl::check('group.creators')) {
	Respond::ResultPage(1);
	exit;
}

requireModel('common.setting');
$definedTables = getDefinedTableNames();

if ($_REQUEST['type'] == 1) { // type 1, plugin

	$values = explode('/', $_REQUEST['name'], 2);
	$pluginname = $values[0];
	$version = $values[1];

	$query = "select name from {$database['prefix']}ServiceSettings WHERE value = '" . POD::escapeString($_REQUEST['name']) . "'";
	
	$plugintablesraw = POD::queryColumn($query);
	// Textcube-NG: POD::queryColumn() 은 쿼리가 실패하면 null 을 돌려준다 — PHP 8 에서 count(null) 은 TypeError(7.x 는 경고). 배열이 아니면 원본 5.6 과 같이 실패로 끝낸다(v3.222)
	if (!is_array($plugintablesraw) || count($plugintablesraw) <= 0) {
		Respond::ResultPage(1);
		exit;
	}
	$plugintables = array();
	foreach($plugintablesraw as $dbname) {
		$origname = $dbname;
		$dbname = $database['prefix'] . substr($dbname, 9);
		if (!doesExistTable($dbname)) {
			Respond::ResultPage(1);
			exit;
		}
		if (in_array($dbname , $definedTables)) {
			Respond::ResultPage(1);
			exit;
		}
		array_push($plugintables, $dbname);
		$query = "delete from {$database['prefix']}ServiceSettings WHERE name = '$origname' AND value = '" . POD::escapeString($_REQUEST['name']) . "'";
		POD::execute($query);
	}
	$result = true;
	foreach($plugintables as $dbname) {
		$query = "DROP TABLE {$dbname}";
		$result = POD::execute($query) && $result;
	}
	
	deactivatePlugin($pluginname);
	pluginKeptSettingsRemove($pluginname);	// Textcube-NG: r3-42 끌 때 보관한 설정도 지운다(플러그인 데이터 삭제, control/plugin/table/delete 와 같은 방식)
	
	if ($result == false) {
		Respond::ResultPage(1);
		exit;
	}
} else { // type 2, table

	$dbname = $_REQUEST['name'];
	if (strncmp($dbname, $database['prefix'], strlen($database['prefix'])) != 0) {
		Respond::ResultPage(1);
		exit;
	}
	if (in_array($dbname , $definedTables)) {
		Respond::ResultPage(1);
		exit;
	}
	if (!doesExistTable($dbname)) {
		Respond::ResultPage(1);
		exit;
	}
	
	$query = "DROP TABLE {$dbname}";
	POD::execute($query);
}

Respond::ResultPage(0);
?>
