<?php
/// Copyright (c) 2004-2016, Needlworks  / Tatter Network Foundation
/// All rights reserved. Licensed under the GPL.
/// See the GNU General Public License for more details. (/documents/LICENSE, /documents/COPYRIGHT)
/* Textcube-NG: 태그 목록 개수의 서비스 상한(서비스 설정 maxTagsOnTagPage) 저장(v3.136). 서비스 관리자(group.creators)만.
   rootredirect/·mailhost/ 와 같은 형식. 블로그 값은 읽을 때 이 상한으로 다시 제한된다(getTagsOnTagPage) */
$IV = array(
	'POST' => array(
		'maxTagsOnTagPage' => array('int', 1, 100000)
	)
);
require ROOT . '/library/preprocessor.php';
requireStrictRoute();
if (!acl::check('group.creators'))
	Respond::ResultPage(false);

$result = Setting::setServiceSettingGlobal('maxTagsOnTagPage', intval($_POST['maxTagsOnTagPage']));
Respond::ResultPage($result);
?>
