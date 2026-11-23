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
require ROOT . '/library/preprocessor.php';
requireModel('blog.entry');
requireModel('blog.response.remote');
requireModel('blog.sidebar');
define('__TEXTCUBE_NO_ENTRY_CACHE__', true);
/* [보안] Textcube-NG: 글 미리보기는 글관리 권한(group.editors)이 있거나 그 글의 작성자만 — 원본은 블로그 구성원이면 남의 비공개·임시 글도 볼 수 있었다(r3-7, 453) */
if (!Acl::check('group.editors')) {
	if (empty($suri['id']) || getUserIdOfEntry(getBlogId(), (int)$suri['id']) != getUserId())
		Respond::NotFoundPage();
}

$entries = array();
if (is_null($entry = getEntry($blogid, $suri['id'], true)))
	$entry = getEntry($blogid, $suri['id'], false);
if (!is_null($entry)) {
	if (isset($entry['appointed']))
		$entry['published'] = $entry['appointed'];
	if(isset($entry['category']) && $entry['category'] >= 0) {
		$entry['categoryLabel'] = getCategoryLabelById($blogid, $entry['category']);
	}
	$entries[0] = $entry;
}
unset($entry);
require ROOT . '/interface/common/blog/begin.php';
require ROOT . '/interface/common/blog/entries.php';

$pageTitle = _t('미리보기') . ' - ' . $pageTitle;
require ROOT . '/interface/common/blog/end.php';
?>
