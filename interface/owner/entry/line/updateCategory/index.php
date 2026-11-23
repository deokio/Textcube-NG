<?php
/// Copyright (c) 2004-2016, Needlworks  / Tatter Network Foundation
/// All rights reserved. Licensed under the GPL.
/// See the GNU General Public License for more details. (/documents/LICENSE, /documents/COPYRIGHT)
$IV = array(
	'POST' => array(
		'id'       => array('int', 'min' => 1),
		'category' => array('string')
	)
);
require ROOT . '/library/preprocessor.php';
requireStrictRoute();

if (!in_array($_POST['category'], array('public', 'private'))) {
	Respond::ResultPage(-1);
	exit;
}

$line = Model_Line::getInstance();
$line->reset();
$line->setFilter(array('blogid', 'equals', getBlogId()));
$line->setFilter(array('id', 'equals', $_POST['id']));
$line->category = $_POST['category'];

if ($line->updateCategory()) {
	$cache = pageCache::getInstance();
	$cache->name = 'linesATOM';
	$cache->purge();
	$cache->reset();
	$cache->name = 'linesRSS';
	$cache->purge();
	Respond::ResultPage(0);
} else {
	Respond::ResultPage(-1);
}
?>
