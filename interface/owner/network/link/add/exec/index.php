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
	'POST' => array(
		'name' => array('string'),
		'rss' => array('string', 'default' => ''),
		'url' => array('string'),
		'category' => array('int','mandatory'=>false),
		'newCategory' => array('string','mandatory'=>false)
	)
);
require ROOT . '/library/preprocessor.php';
requireModel("blog.link");

requireStrictRoute();
if(!preg_match('#^https?://#i', $_POST['rss'])) $_POST['rss'] = 'http://'.$_POST['rss']; // Textcube-NG: https 주소 앞에 http://를 덧붙이지 않음(r3-8)
if(!preg_match('#^https?://#i', $_POST['url'])) $_POST['url'] = 'http://'.$_POST['url']; // Textcube-NG: https 주소 앞에 http://를 덧붙이지 않음(r3-8)
Respond::ResultPage(addLink($blogid, $_POST));
?>
