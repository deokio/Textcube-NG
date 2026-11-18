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
require ROOT . '/library/preprocessor.php';
requireModel("blog.entry");

/* [보안] 원본은 작성자를 확인하지 않아 팀블로그 글쓴이가 다른 사람의 보호글 비밀번호를 읽을 수 있었고,
   $suri['id'](숫자일 때만 설정됨)를 확인 없이 SQL 에 넣었다 → 편집자가 아니면 작성자 확인, id 는 정수로만 사용 */
if(!isset($suri['id']) || (!Acl::check('group.editors') && getUserIdOfEntry(getBlogId(), (int)$suri['id']) != getUserId())) {
	Respond::PrintResult(array('error' => 1));
}
$password = POD::queryCell("SELECT password
		FROM {$database['prefix']}Entries
		WHERE blogid = ".getBlogId()." AND id = ".(int)$suri['id']." AND draft = 0");
if (is_null($password)) $password = '';
Respond::PrintResult(array('error' => 0, 'password' => $password));
?>
