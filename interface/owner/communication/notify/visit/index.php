<?php
/// Copyright (c) 2004-2016, Needlworks  / Tatter Network Foundation
/// All rights reserved. Licensed under the GPL.
/// See the GNU General Public License for more details. (/documents/LICENSE, /documents/COPYRIGHT)
$IV = array(
	'POST' => array()
);
require ROOT . '/library/preprocessor.php';
requirePostMethod();	// Textcube-NG: 상태 변경은 POST 만 받는다(그 밖은 405) — 링크·<img> 로 실행되던 GET CSRF 차단(v3.148)
requireStrictRoute();

// Textcube-NG: 알리미 화면 방문 시각 기록 — GET 화면(notify/index.php)에서 옮김. 원본에 없는 새 파일(r3-8)
setBlogSetting('lastVisitNotifiedPage', time());
Respond::ResultPage(0);
?>
