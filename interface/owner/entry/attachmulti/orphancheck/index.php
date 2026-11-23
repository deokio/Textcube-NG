<?php
/// Copyright (c) 2004-2016, Needlworks  / Tatter Network Foundation
/// All rights reserved. Licensed under the GPL.
/// See the GNU General Public License for more details. (/documents/LICENSE, /documents/COPYRIGHT)
define('__TEXTCUBE_CUSTOM_HEADER__', true);
$IV = array();
require ROOT . '/library/preprocessor.php';
requireModel('blog.attachment');
session_write_close();

/* [보안] 포트에서 추가한 이 핸들러는 이 블로그의 새 글 임시 첨부(parent=0)를 모두 셌다 → 현재 사용자의 임시 첨부(parent = -(userid+1))만.
   업그레이드 전 임시 첨부(parent=0)는 관리자(group.administrators)에게만 함께 센다 */
$count = 0;
$pool = DBModel::getInstance();
$temporaryParent = getTemporaryAttachmentParent(getUserId());
if ($temporaryParent !== false) {
	$pool->reset('Attachments');
	$pool->setQualifier('blogid', 'equals', intval($blogid));
	$pool->setQualifier('parent', 'equals', $temporaryParent);
	$count += intval($pool->getCount());
}
if (Acl::check('group.administrators')) {
	$pool->reset('Attachments');
	$pool->setQualifier('blogid', 'equals', intval($blogid));
	$pool->setQualifier('parent', 'equals', 0);
	$count += intval($pool->getCount());
}

header('Content-Type: application/json; charset=utf-8');
echo json_encode(array('orphanCount' => intval($count)));
?>
