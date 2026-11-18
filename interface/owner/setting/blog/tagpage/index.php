<?php
/// Copyright (c) 2004-2016, Needlworks  / Tatter Network Foundation
/// All rights reserved. Licensed under the GPL.
/// See the GNU General Public License for more details. (/documents/LICENSE, /documents/COPYRIGHT)
/* Textcube-NG: 태그 목록 개수(블로그 설정 tagsOnTagPage) 저장(v3.136). 기존 setting/blog/tag/ 는 블로그 태그(setBlogTags) 저장용이라
   이름을 tagpage 로 한다. 권한은 /owner/setting*(관리자 이상, framework/boot/30-Auth.php). 값은 1 ~ 서비스 상한(setTagsOnTagPage) */
$IV = array(
	'POST' => array(
		'tagsOnTagPage' => array('int', 1, 100000)
	)
);
require ROOT . '/library/preprocessor.php';
requireStrictRoute();
requireModel('blog.tag');
Respond::ResultPage(setTagsOnTagPage($blogid, $_POST['tagsOnTagPage']));
?>
