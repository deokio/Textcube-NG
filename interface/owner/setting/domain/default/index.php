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
// [보안] Textcube-NG: 입력값 규칙($IV) — 빈 값은 원본처럼 아래에서 실패(-1) 처리(r3-7, 결정 472b)
$IV = array(
	'POST' => array(
		'confirmativePassword' => array('string', 'mandatory' => false)
	)
);
require ROOT . '/library/preprocessor.php';
requirePostMethod();	// Textcube-NG: 상태 변경은 POST 만 받는다(그 밖은 405) — 링크·<img> 로 실행되던 GET CSRF 차단(v3.155)
requireStrictRoute();	// Textcube-NG: 원본은 토큰·Referer 확인이 없어 다른 사이트 링크로도 기본 주소가 바뀌었다 — 같은 블로그 화면의 POST 만(v3.155)
if (!isReauthenticated(isset($_POST['confirmativePassword']) ? $_POST['confirmativePassword'] : null))	// Textcube-NG: 블로그 주소(도메인) 변경 전 비밀번호 재확인 — domain/set 과 같게, 틀리면 9(v3.155)
	Respond::ResultPage(9);
if (setDefaultDomain($blogid, $suri['id'])) {
	Respond::ResultPage(0);
}
Respond::ResultPage( - 1);
?>
