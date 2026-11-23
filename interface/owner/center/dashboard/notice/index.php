<?php
/// Copyright (c) 2004-2016, Needlworks  / Tatter Network Foundation
/// All rights reserved. Licensed under the GPL.
/// See the GNU General Public License for more details. (/documents/LICENSE, /documents/COPYRIGHT)
/* Textcube-NG: 대시보드 플러그인 알림 확인(v3.219). POST 만, 관리자(group.administrators)만. key(32자리 16진수) 하나 또는 all=1 */
$IV = array(
	'POST' => array(
		'key' => array('string', 'default' => ''),
		'all' => array('int', 0, 1, 'default' => 0)
	)
);
require ROOT . '/library/preprocessor.php';
requirePostMethod();
requireStrictRoute();
if (!Acl::check('group.administrators'))
	Respond::ResultPage(false);
requireModel('common.plugin');
$key = (string)$_POST['key'];
$all = (intval($_POST['all']) === 1);
if ($all === ($key !== '') || (!$all && !preg_match('/^[0-9a-f]{32}$/D', $key)))
	Respond::ResultPage(-1);
Respond::ResultPage(pluginNoticeAck($all ? 'all' : $key) ? 0 : -1);
?>
