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
		'id' => array('string', 'default' => false),
		'cursor' => array('int', 'default' => false),
		'filter' => array('string', 'default' => '1')
	)
);
require ROOT . '/library/preprocessor.php';
header('Content-Type: text/xml; charset=utf-8');
$id = isset($_GET['id']) ? $_GET['id'] : false;
$cursor = isset($_GET['cursor']) ? $_GET['cursor'] : false;
$filter = isset($_GET['filter']) ? $_GET['filter'] : '1';
echo "<?xml version=\"1.0\" encoding=\"utf-8\"?>\r\n";
echo "<response";
if ($id !== false)
	echo ' id="' . htmlspecialchars($id, ENT_QUOTES) . '"';	// Textcube-NG: 요청 값을 XML 속성에 그대로 출력하지 않는다(v3.135)
if ($cursor !== false)
	echo " cursor=\"$cursor\"";
echo ">\r\n";
$tags = array();
foreach (suggestLocatives($blogid, $filter) as $tag)
	echo "<location>" . htmlspecialchars(ltrim($tag, '/')) . "</location>\r\n";
echo "</response>\r\n";
?>
