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

define('__TEXTCUBE_LINE__',true);

$IV = array(
	'GET' => array(
		'key' => array('string','default'=>''),
		'mode' => array('string','default'=>'url'),
		'content' => array('string','default'=>''),
		'category' => array('string','default'=>'public'),
		'page' => array('int',1,'default'=>'')
	),
	'POST' => array(
		'key' => array('string','default'=>''),
		'mode' => array('string','default'=>'url'),
		'category' => array('string','default'=>'public'),
		'content' => array('string','default'=>'')
	)
);

require ROOT . '/library/preprocessor.php';

if(!empty($_POST['content'])) {
	if(!empty($_POST['key'])) {
		$key = $_POST['key'];
	} else {
		$key = null;
	}
	$content = $_POST['content'];
	$category = $_POST['category'];
	$mode = $_POST['mode'];
} else {
	$key = $_GET['key'];
	$content = $_GET['content'];
	$category = $_GET['category'];
	$mode = $_GET['mode'];
}

$lineobj = Model_Line::getInstance();
$lineobj->reset();
// If line comes.
if(!empty($content)) {
	$password = Setting::getBlogSetting('LinePassword', null, true);
	/* [보안] Line 키가 아직 생성되지 않은 블로그(LinePassword 미설정 → null)에서는 key 없는 POST 요청이
	   원본 비교(null === null)를 통과해 비로그인 사용자도 Line 글을 쓸 수 있었다.
	   설정된 키와 비어 있지 않은 key 를 hash_equals 로 비교한다(블로그 관리자·작성자는 기존과 같이 허용). */
	$keyMatched = (is_string($password) && $password !== '' && is_string($key) && hash_equals($password, $key));
	// Textcube-NG: 키 없이 소유권으로 쓰는 경우(대시보드 CT_LinePost 위젯)는 POST·같은 블로그 Referer·CSRF 토큰을 요구한다.
	// 원본은 소유자를 /line/?content=… 로 보내기만 해도 글이 써졌다(GET CSRF). 키가 맞는 외부 도구 요청은 그대로(v3.147)
	if(!$keyMatched && doesHaveOwnership()) {
		requirePostMethod();
		requireStrictRoute();
		requireCSRFToken();
	}
	if($keyMatched || doesHaveOwnership()) {
		$lineobj->content = $content;
		$lineobj->category = $category;
		$result = $lineobj->add();
		fireEvent('AddLine',$result, $lineobj);
		$cache = pageCache::getInstance();
		$cache->name = 'linesATOM';
		$cache->purge();
		$cache->reset();
		$cache->name = 'linesRSS';
		$cache->purge();
		if($mode == 'url') $lineobj->showResult($result);
		else {
			Respond::ResultPage(0);
		}
	}
} else {
	/// Prints lines: all lines for owner, public-only for visitors
	if (doesHaveOwnership()) {
		$lineobj->setFilter(array('blogid','equals',getBlogId()));
	} else {
		$lineobj->setFilter(array('blogid','equals',getBlogId()));	// Textcube-NG: 이 블로그 라인만(멀티블로그에서 다른 블로그 공개 라인이 섞이던 원본 결함, v3.163)
		$lineobj->setFilter(array('category','equals','public',true));
	}
	$lineobj->setLimit(20);
	$lineobj->setOrder('created','desc');
	$lines = $lineobj->get();
	fireEvent('OBStart');
	require ROOT . '/interface/common/blog/begin.php';
	require ROOT . '/interface/common/blog/line.php';
	require ROOT . '/interface/common/blog/end.php';
	fireEvent('OBEnd');
}
exit;
?>
