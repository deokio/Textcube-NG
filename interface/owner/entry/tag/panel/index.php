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
		'tagId' => array ('int','min'=>1)	
	)
);

require ROOT . '/library/preprocessor.php';
requireStrictRoute();
requireModel('blog.entry');

/// Loads entry list.
$listWithPaging = getEntryListWithPagingByTag(getBlogId(), $_POST['tagId'], 1, 1);
$list = array('title' => $suri['value'], 'items' => $listWithPaging[0], 'count' => $listWithPaging[1]['total']);

$tagName = getTagById(getBlogId(),$_POST['tagId']);
$numberOfPosts = $list['count'];
$entryList = '<a href="'.$blogURL.'/owner/entry/?tagId='.$_POST['tagId'].'">'._f('"%1" 태그를 갖는 모든 글의 목록을 봅니다',htmlspecialchars((string)$tagName, ENT_QUOTES)).'</a>';	// Textcube-NG: entryList 는 화면에 HTML 로 들어가므로 태그 이름 escape(v3.135). tagName 은 입력란 값용이라 그대로
$result = array('error'=>0,
	'tagName'=> $tagName,
	'numberOfPosts' => $numberOfPosts,
	'entryList' => $entryList
	);
Respond::PrintResult($result);
?>
