<?php
/// Copyright (c) 2004-2016, Needlworks  / Tatter Network Foundation
/// All rights reserved. Licensed under the GPL.
/// See the GNU General Public License for more details. (/documents/LICENSE, /documents/COPYRIGHT)
/* [보안] 원본은 이름·값 검사 없이 블로그 설정(BlogSettings)에 기록해, 이 경로를 쓸 수 있는 구성원이 시간대·사이드바 배치 등
   임의 설정을 덮어쓸 수 있었다 → 기본 제공 스크립트(reader.js, FM_Modern·ED_tinyMCE 편집기)가 쓰는 화면 설정 3개만 받고
   값은 정수로 정규화한다(readerPannelVisibility·editorPropertyPositionFix: 0/1, readerPannelHeight: 1 이상). */
$IV = array(
	'POST' => array(
		'name'  => array(array('readerPannelVisibility', 'readerPannelHeight', 'editorPropertyPositionFix')),
		'value' => array('int', 0)
	)
);
require ROOT . '/library/preprocessor.php';
requireStrictRoute();
$userSettingRange = array(
	'readerPannelVisibility'    => array(0, 1),
	'readerPannelHeight'        => array(1, 2147483647),
	'editorPropertyPositionFix' => array(0, 1)
);
$name = $_POST['name'];
$value = (int)$_POST['value'];
if (!isset($userSettingRange[$name]) || ($value < $userSettingRange[$name][0]) || ($value > $userSettingRange[$name][1]))
	Respond::ResultPage(-1);
Respond::ResultPage(Setting::setBlogSettingGlobal($name, $value));
?>
