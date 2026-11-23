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
// [보안] Textcube-NG: 입력값 규칙($IV) — 빈 값은 원본처럼 아래에서 실패(-1) 처리(r3-7, 결정 472b)
$IV = array(
	'POST' => array(
		'domain' => array('string', 'mandatory' => false),
		'confirmativePassword' => array('string', 'mandatory' => false)
	)
);
require ROOT . '/library/preprocessor.php';
requirePostMethod();	// Textcube-NG: 상태 변경은 POST 만 받는다(그 밖은 405) — 링크·<img> 로 실행되던 GET CSRF 차단(v3.155)
requireStrictRoute();
if (!isReauthenticated(isset($_POST['confirmativePassword']) ? $_POST['confirmativePassword'] : null))	// Textcube-NG: 블로그 주소(도메인) 변경 전 비밀번호 재확인 — domain/set 과 같게, 틀리면 9(v3.155)
	Respond::ResultPage(9);
if (!empty($_POST['domain']) && !(setSecondaryDomain($blogid, $_POST['domain']) > 0)) {	// Textcube-NG: 성공은 0, 실패는 1·2 — domain/set 과 같은 '> 0' 실패 판정(원본은 성공·실패 응답이 반대)(v3.199)
	Respond::ResultPage(0);
}
Respond::ResultPage( - 1);
?>
