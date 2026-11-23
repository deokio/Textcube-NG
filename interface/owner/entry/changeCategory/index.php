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
		'targets' => array('list'),
		'category' => array('int')
	)
);
require ROOT . '/library/preprocessor.php';
requireStrictRoute();
requireModel("blog.entry");

/* [보안] 원본은 대상 글의 작성자를 확인하지 않아 팀블로그 글쓴이가 다른 사람의 글 분류를 바꿀 수 있었다 →
   편집자가 아니면 모든 대상 글(changeCategoryOfEntries 와 같은 분해 규칙)의 작성자인지 확인 */
if(!Acl::check('group.editors')) {
	foreach(preg_split('/,/', $_POST['targets'], -1, PREG_SPLIT_NO_EMPTY) as $target) {
		if(getUserIdOfEntry(getBlogId(), (int)$target) != getUserId()) {
			Respond::ResultPage(1);
		}
	}
}

if(changeCategoryOfEntries($blogid, $_POST['targets'], $_POST['category'])) {
	Respond::ResultPage(0);
} else {
	Respond::ResultPage(1);
}
?>
