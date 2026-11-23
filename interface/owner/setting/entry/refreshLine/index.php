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
requireStrictRoute();
// [보안] 원본은 어디에서도 쓰지 않는 'LineSetting' 을 삭제해 "새로 고침"으로 Line 키가 바뀌지 않았다.
//        실제 키(LinePassword)를 삭제한다 → 다음 검색 표시줄 추가(addline) 때 새 키 생성.
if(Setting::removeBlogSetting('LinePassword',true)) Respond::ResultPage(0);
else Respond::ResultPage(-1);
?>
