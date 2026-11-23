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

$IV = array (
		'POST' => array(
			'type' => array('int'),
			'ajaxcall' => array ('any', 'mandatory' => false)
			)
		);

require ROOT . '/library/preprocessor.php';
requirePostMethod();	// Textcube-NG: 상태 변경은 POST 만 받는다(그 밖은 405) — 링크·<img> 로 실행되던 GET CSRF 차단(v3.148)
requireModel("blog.trash");

requireStrictRoute();

if ($_POST['type'] == 1) {
	emptyTrash(true);
} else if ($_POST['type'] == 2) {
	emptyTrash(false);
} else {
	Respond::NotFoundPage();
}

if (array_key_exists('ajaxcall', $_POST)) Respond::ResultPage(0);
else {
	if ($_POST['type'] == 1) header("Location: " . $blogURL  . '/owner/communication/trash/comment' );
	else if ($_POST['type'] == 2) header("Location: " . $blogURL  . '/owner/communication/trash/trackback' );
}
?>
