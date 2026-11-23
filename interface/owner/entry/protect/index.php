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
		'password' => array('string', 'mandatory' => false)
	)
);
require ROOT . '/library/preprocessor.php';
requireModel("blog.entry");

requireStrictRoute();
/* [보안] 원본은 작성자를 확인하지 않아 팀블로그 글쓴이가 다른 사람의 글 보호 설정·비밀번호를 바꿀 수 있었다 →
   편집자가 아니면 대상 글의 작성자인지 확인 */
if(!Acl::check('group.editors')) {
	if(empty($suri['id']) || getUserIdOfEntry(getBlogId(), (int)$suri['id']) != getUserId()) {
		Respond::ResultPage(-1);
	}
}
Respond::ResultPage(protectEntry($suri['id'], isset($_POST['password']) ? $_POST['password'] : ''));
?>
