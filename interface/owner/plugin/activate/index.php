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
	'POST' => array(
		'name' => array('directory', 'default' => null)
	)
);
require ROOT . '/library/preprocessor.php';
// Textcube-NG: r3-43 R-574 POST 만 받는다(recheck·resetSetting 과 같이, 다른 메서드는 405)
requirePostMethod();
requireStrictRoute();
// Textcube-NG: r3-42 다른 플러그인 작업이 처리 중이면(블로그별 잠금) 기다리지 않고 거절하고(켜기 실패와 같은 응답 형식), 잠근 뒤에는
// 켜진 플러그인 목록을 DB 에서 다시 읽는다(요청 처음에 읽은 목록은 앞선 요청이 끝나기 전 것일 수 있다). 잠금은 응답(exit) 때 풀린다
if (pluginOpLock(false) === false)
	Respond::ResultPage(array(1, pluginOpBusyMessage()));
pluginOpRefreshActive();
// Textcube-NG: native 경고·거부 사유를 결과 메시지로 낸다(v3.211)
if (!empty($_POST['name']) && activatePlugin($_POST['name']))
	Respond::ResultPage(pluginGuardResultCode(0));
Respond::ResultPage(pluginGuardResultCode(1));
?>
