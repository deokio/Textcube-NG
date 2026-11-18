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
'userid'=>array('id')
	)
);
require ROOT . '/library/preprocessor.php';
requireStrictRoute();

/* [보안] 원본은 권한·대상 확인 없이 구성원을 삭제했다(대상이 다른 블로그에 속하지 않으면 계정까지 영구 삭제) →
   팀블로그 화면의 삭제 기능 노출 조건과 같게 블로그 소유자(group.owners)만, 이 블로그의 소유자가 아닌 구성원만 삭제 */
if (!Acl::check('group.owners')) {
	Respond::ResultPage(-1);
}
$targetAcl = POD::queryCell("SELECT acl FROM {$database['prefix']}Privileges WHERE blogid = ".getBlogId()." AND userid = ".intval($_POST['userid']));
if (!is_numeric($targetAcl) || ((int)$targetAcl & BITWISE_OWNER)) {
	Respond::ResultPage(-1);
}

if (deleteTeamblogUser($_POST['userid'])) {
	Respond::ResultPage(0);
}
Respond::ResultPage(-1);
?>
