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
		'url' => array('url', 'default'=> null)
	)
);
require ROOT . '/library/preprocessor.php';
requirePostMethod();	// Textcube-NG: 상태 변경은 POST 만 받는다(그 밖은 405) — 링크·<img> 로 실행되던 GET CSRF 차단(v3.148)
requireModel("blog.response.remote");

requireStrictRoute();
requireModel('blog.entry');
/* [보안] Textcube-NG: 트랙백 보내기는 글관리 권한(group.editors)이 있거나 그 글의 작성자만 — 원본은 구성원이면 남의 글로 트랙백을 보낼 수 있었다(r3-7, 455a) */
if (!Acl::check('group.editors')) {
	if (empty($suri['id']) || getUserIdOfEntry(getBlogId(), (int)$suri['id']) != getUserId())
		Respond::ResultPage(-1);
}
Respond::ResultPage(!empty($_POST['url']) && sendTrackback($blogid, $suri['id'], trim($_POST['url'])));
?>
