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
		'identify' => array('string', 'min' => 1),
		'owner' => array('email')
	) 
);
require ROOT . '/library/preprocessor.php';
requirePostMethod();	// Textcube-NG: 상태 변경은 POST 만 받는다(그 밖은 405) — 링크·<img> 로 실행되던 GET CSRF 차단(v3.148)

requireStrictRoute();
requirePrivilege('group.creators');
if ($uid = User::getUserIdByEmail($_POST['owner'])) {
	$result = addBlog('',$uid, $_POST['identify']);
	if ($result===true) {
		if($_POST['owner'] == User::getEmail(getUserId())) {	/// Update current user's access list.
			$priv = array();
			array_push($priv, "group.administrators");
			Acl::setAcl( getBlogidByName(UTF8::lessenAsEncoding(strtolower($_POST['identify']), 32)), $priv, true ); // Textcube-NG: 정의되지 않은 $rec 대신 addBlog가 저장한 식별자로 새 블로그 id를 찾아 권한 설정(r3-8)
		}
		Respond::PrintResult(array('error' => 0));
	}
	else {
		Respond::PrintResult(array('error' => -1 , 'result' =>$result));
	}
} else {
	Respond::PrintResult(array('error' => -2 , 'result' => _t('등록되지 않은 소유자 E-mail 입니다.')));
}
?>
