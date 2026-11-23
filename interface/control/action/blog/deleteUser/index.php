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
	'POST' => array(
		'userid'=>array('id'),
		'blogid'=>array('id')
	)
);
require ROOT . '/library/preprocessor.php';
requirePostMethod();	// Textcube-NG: 상태 변경은 POST 만 받는다(그 밖은 405) — 링크·<img> 로 실행되던 GET CSRF 차단(v3.148)
requireStrictRoute();
requirePrivilege('group.creators');

// Textcube-NG: r3-31 R-514 — 블로그 소유자(acl 에 BITWISE_OWNER)는 빼지 않는다. 원본은 대상 확인이 없어 직접 POST 로 소유자를 뺄 수 있었고,
// 소유자 없는 블로그에서 다른 구성원을 빼면 Blog::deleteUser 의 UPDATE 가 문법 오류가 났다(owner/network/teamblog/deleteUser 와 같은 규칙)
$targetAcl = POD::queryCell("SELECT acl FROM {$database['prefix']}Privileges WHERE blogid = ".intval($_POST['blogid'])." AND userid = ".intval($_POST['userid']));
if (is_numeric($targetAcl) && ((int)$targetAcl & BITWISE_OWNER)) {
	Respond::ResultPage(-1);
}

if (deleteTeamblogUser($_POST['userid'],$_POST['blogid'],false)) {
	Respond::ResultPage(0);
}
Respond::ResultPage(-1);
?>
