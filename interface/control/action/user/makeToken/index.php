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
		'userid' => array('id'),
		'confirmativePassword' => array('string', 'mandatory' => false)
	) 
);
require ROOT . '/library/preprocessor.php';
requirePostMethod();	// Textcube-NG: 상태 변경은 POST 만 받는다(그 밖은 405) — 링크·<img> 로 실행되던 GET CSRF 차단(v3.148)

requireStrictRoute();
requirePrivilege('group.creators');
if (!isReauthenticated(isset($_POST['confirmativePassword']) ? $_POST['confirmativePassword'] : null))	// Textcube-NG: 임시 암호 발급 전 (창설자 자신의) 비밀번호 재확인, 틀리면 9(v3.149)
	Respond::PrintResult(array('error' => 9));

/* [보안] 인증 토큰(임시 암호)은 CSPRNG 128비트(원본 md5(약한 8자)와 같은 32자리 16진 형식)로 발급 시각과 함께 저장
   (Auth::issueAuthToken — 만료·1회용). 원본의 REPLACE INTO `...` 은 MySQL 전용 문법이라 PostgreSQL 등에서는 발급되지 않았다. */
$result = Auth::issueAuthToken($_POST['userid']);
if ($result) {
	Respond::PrintResult(array('error' => 0)); // Textcube-NG: PrintResult 뒤 응답을 깨뜨리던 echo "s" 정리(r3-8)
}
else {
	$result = _t('임시 암호 발급에 실패하였습니다.');
	Respond::PrintResult(array('error' => -1 , 'result' =>$result));
}
?>
