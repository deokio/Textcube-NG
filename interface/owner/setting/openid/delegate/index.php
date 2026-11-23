<?php
/// Copyright (c) 2004-2007, Needlworks / Tatter Network Foundation
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
///
/// ---- Modification Notice ----
/// OpenID 2.0 위임(delegation)은 프로토콜 EOL 로 제거되었다. OIDC 는 위임 개념이 없으므로
/// 이 엔드포인트는 더 이상 위임 설정을 저장하지 않는다(과거 OpenIDConsumer::setDelegate 대체).

$IV = array(
	'POST' => array(
		'openid_identifier' => array('string', 'mandatory' => false )
	)
);

require ROOT . '/library/preprocessor.php';
requirePostMethod();	// Textcube-NG: 상태 변경은 POST 만 받는다(그 밖은 405) — 링크·<img> 로 실행되던 GET CSRF 차단(v3.148)
requireStrictRoute();

// 위임 기능 제거 — 미지원.
Respond::ResultPage(-1);
?>
