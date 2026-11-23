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
/* Textcube-NG: 실행 차단(r3-14) — 저장된 글을 고치는 핸들러였으나 부르는 곳이 없다(편집기 저장은 add·draft·finish).
   원본에서부터 남아 있던 경로이고, 원본은 입력 검사 키 오타('starred   ')로 모든 요청이 404 였다(v3.186 에서 키를 고쳐
   실행되게 됨). 권한 검사(preprocessor)만 거친 뒤 DB 작업 없이 404 를 돌려준다. framework/boot/30-Auth.php 의
   '/owner/entry/update*' 항목도 지웠다(표에 없는 /owner 경로는 관리자 이상만 통과). */
require ROOT . '/library/preprocessor.php';
Respond::NotFoundPage();
?>
