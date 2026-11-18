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
define('__TEXTCUBE_CUSTOM_HEADER__', true);
$IV = array();
require ROOT . '/library/preprocessor.php';
requireStrictRoute();
requireModel('blog.attachment');
session_write_close();

/* [보안] 포트에서 추가한 이 핸들러는 이 블로그의 새 글 임시 첨부(parent=0)를 모두 지웠다 → 현재 사용자의 임시 첨부(parent = -(userid+1))만.
   업그레이드 전 임시 첨부(parent=0)는 관리자(group.administrators)만 지운다. getAttachments() 의 요청 안 캐시는 값을 느슨하게 찾으므로
   (0 이 다른 행의 '0' 값과 맞음) parent=0 을 먼저 지운다 */
if (Acl::check('group.administrators'))
	deleteAttachments(intval($blogid), 0);
$temporaryParent = getTemporaryAttachmentParent(getUserId());
if ($temporaryParent !== false)
	deleteAttachments(intval($blogid), $temporaryParent);

header('Content-Type: application/json; charset=utf-8');
echo json_encode(array('result' => 'ok'));
?>
