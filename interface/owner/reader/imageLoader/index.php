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
	'GET' => array(
		'url' => array('url')
	)
);
require ROOT . '/library/preprocessor.php';
/* [보안] 원본은 주어진 URL(내부 주소, 내부로 가는 리다이렉트 포함)을 그대로 가져와 응답 본문을 출력했고, 형식은 URL 확장자로만
   정해 그 외에는 HTML 로 출력됐다 → 공인 주소만 가져오고(HTTPRequest::$publicOnly) 응답이 래스터 이미지일 때만 그 형식으로 출력 */
$request = new HTTPRequest($_GET['url']);
$request->publicOnly = true;
if ($request->send() && is_string($request->responseText) && strlen($request->responseText) > 0
	&& ($info = @getimagesizefromstring($request->responseText)) !== false
	&& in_array($info[2], array(IMAGETYPE_GIF, IMAGETYPE_JPEG, IMAGETYPE_PNG, IMAGETYPE_BMP, IMAGETYPE_WEBP, IMAGETYPE_ICO), true)) {
	header('Content-type: ' . $info['mime']);
	header('X-Content-Type-Options: nosniff');
	echo $request->responseText;
} else
	Respond::NotFoundPage();
?>
