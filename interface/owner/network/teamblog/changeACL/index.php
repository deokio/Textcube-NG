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
		'acltype'=>array('string'),
		'userid'=>array('int'),
		'switch'=>array('int')
	)
);
require ROOT . '/library/preprocessor.php';
requireStrictRoute();
/* [보안] 원본은 권한·대상 확인 없이 구성원 권한을 바꿨다(구성원이 아닌 사용자는 구성원으로 추가, 소유자 행도 변경) →
   팀블로그 화면의 권한 변경 노출 조건과 같게 관리자(group.administrators) 이상만, 이 블로그의 소유자가 아닌
   기존 구성원만 변경 */
if (!Acl::check('group.administrators')) {
	Respond::ResultPage(false);
}
$targetAcl = POD::queryCell("SELECT acl FROM {$database['prefix']}Privileges WHERE blogid = ".getBlogId()." AND userid = ".intval($_POST['userid']));
if (!is_numeric($targetAcl) || ((int)$targetAcl & BITWISE_OWNER)) {
	Respond::ResultPage(false);
}
if (changeACLonBlog(getBlogId(),$_POST['acltype'],$_POST['userid'],$_POST['switch'])) {
	return Respond::ResultPage(true);
}
Respond::ResultPage(false);
?>
