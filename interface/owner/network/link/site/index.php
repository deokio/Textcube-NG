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
	'GET' => array(
		'rss' => array('url')
	)
);
require ROOT . '/library/preprocessor.php';
requireModel('blog.link');

if (!empty($_GET['rss'])) {
	list($st, $header, $body, $lmdate, $rval) = @xml_parser($_GET['rss'], '');
	$result = array();
	if ($rval) {
		list($title, $link) = str_dbi_check(@get_siteinfo($rval));
		if (UTF8::validate($title, true))
			$result['name'] = correctTTForXmlText(UTF8::correct(htmlspecialchars(trim($title), ENT_QUOTES | ENT_SUBSTITUTE)));
		else
			$result['name'] = correctTTForXmlText(UTF8::bring(htmlspecialchars(trim($title), ENT_QUOTES | ENT_SUBSTITUTE)));
		if (UTF8::validate($link, true))
			$result['url'] = correctTTForXmlText(UTF8::correct(htmlspecialchars(trim($link), ENT_QUOTES | ENT_SUBSTITUTE)));
		else
			$result['url'] = correctTTForXmlText(UTF8::bring(htmlspecialchars(trim($link), ENT_QUOTES | ENT_SUBSTITUTE)));
		Respond::PrintResult($result);
	} else {
		$result['url'] = $_GET['rss'];
		$result['name'] = '';
		Respond::PrintResult($result);
	}
	exit;
} else {
	Respond::ResultPage(-1);
}
?>
