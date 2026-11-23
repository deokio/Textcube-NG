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
		'item' => array('string')
	) 
);
require ROOT . '/library/preprocessor.php';
requirePostMethod();	// Textcube-NG: 상태 변경은 POST 만 받는다(그 밖은 405) — 링크·<img> 로 실행되던 GET CSRF 차단(v3.148)

requireStrictRoute();
requirePrivilege('group.creators');

$items = explode(",",$_POST['item']);

if (in_array(getServiceSetting("defaultBlogId",1),$items)) {
	$result = _t('대표 블로그는 삭제할 수 없습니다.');
	Respond::PrintResult(array('error' => -1 , 'result' =>$result));
}

foreach ($items as $item) {
	$result = removeBlog($item);
	if ($result!==true) {
		Respond::PrintResult(array('error' => -1 , 'result' =>$result));
	}
}
Respond::PrintResult(array('error' => 0));
?>
