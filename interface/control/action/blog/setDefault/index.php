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
		'blogid' => array('id')
	) 
);
require ROOT . '/library/preprocessor.php';
requirePostMethod();	// Textcube-NG: 상태 변경은 POST 만 받는다(그 밖은 405) — 링크·<img> 로 실행되던 GET CSRF 차단(v3.148)

requireStrictRoute();
requirePrivilege('group.creators');

if ( setDefaultBlog($_POST['blogid'])) {
	Respond::PrintResult(array('error' => 0));
}
else {
	$result = _t('블로그가 존재하지 않거나, 블로그의 소유자가 전체 관리자가 아닙니다.');
	Respond::PrintResult(array('error' => -1 , 'result' =>$result));
}
?>
