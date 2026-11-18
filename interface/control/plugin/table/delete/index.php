<?php
/// Copyright (c) 2004-2016, Needlworks  / Tatter Network Foundation
/// All rights reserved. Licensed under the GPL.
/// See the GNU General Public License for more details. (/documents/LICENSE, /documents/COPYRIGHT)
/* Textcube-NG: 플러그인 데이터 삭제(v3.210). 서비스 관리자(group.creators)만, POST·CSRF 토큰(requireStrictRoute). 옛 /owner/plugin/tableSetting/delete 를 옮겼다.
   테이블 이름은 bind 할 수 없으므로 요청 값을 SQL 에 넣지 않고, getPluginTableList() 결과(실제 테이블 목록·코어 테이블 제외)와 정확히 같을 때만 그 이름으로 지운다.
   type 1(플러그인): 그 플러그인의 테이블을 모두 지운 뒤 등록 행(removeServiceSetting, escape)을 지우고 현재 블로그에서 플러그인을 끈다 — 테이블 없이 등록만 남은 행도 지운다.
   type 2(알 수 없음): 등록과 이어지지 않은 테이블만. 두 경우 모두 다른 설치로 보이는 테이블(isOtherInstallTableName, 화면과 같은 판정)은 지우지 않는다.
   원본의 WHERE value = …(Cubrid 예약어)와 GET 쿼리 이름 전달은 쓰지 않는다 */
$IV = array(
	'POST' => array(
		'name' => array('string'),
		'type' => array('int', 'min' => 1, 'max' => 2)
	)
);
require ROOT . '/library/preprocessor.php';
requirePostMethod();
requireStrictRoute();
requirePrivilege('group.creators');
requireModel('common.setting');

$list = getPluginTableList();
$result = 1;
if ($_POST['type'] == 1) {
	if (array_key_exists($_POST['name'], $list['plugins'])) {
		$entry = $list['plugins'][$_POST['name']];
		$dropped = true;
		foreach ($entry['tables'] as $table) {
			if (isOtherInstallTableName($table, $list['caseInsensitive'])) {
				$dropped = false;
			}
		}
		if ($dropped) {
			foreach ($entry['tables'] as $table) {
				if (!POD::execute('DROP TABLE ' . $table)) {
					$dropped = false;
				}
			}
		}
		if ($dropped) {
			foreach ($entry['settings'] as $setting) {
				removeServiceSetting($setting);
			}
			deactivatePlugin($entry['plugin']);
			pluginKeptSettingsRemove($entry['plugin']);	// Textcube-NG: r3-42 끌 때 보관한 설정도 지운다(현재 블로그, deactivatePlugin 과 같은 범위)
			$result = 0;
		}
	}
} else {
	$index = array_search($_POST['name'], $list['unknown'], true);
	if ($index !== false && !isOtherInstallTableName($list['unknown'][$index], $list['caseInsensitive'])
		&& POD::execute('DROP TABLE ' . $list['unknown'][$index])) {
		$result = 0;
	}
}
Respond::ResultPage($result);
?>
