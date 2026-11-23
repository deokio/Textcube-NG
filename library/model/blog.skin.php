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

function setTreeSetting($blogid, $setting) {
	requireLibrary('blog.skin');

	if(empty($setting['showValueOnTree'])) $setting['showValueOnTree'] = 0;
	else $setting['showValueOnTree'] = 1;
	foreach ($setting as $key => $value) {
		Setting::setSkinSetting($key, $value, $blogid);
	}
	CacheControl::flushSkin();
	Setting::getSkinSettings($blogid, true); // refresh skin cache
	return true;
}

function reloadSkin($blogid)
{
	$context = Model_Context::getInstance();
	$skinName = $context->getProperty('skin.skin');
	if (file_exists(ROOT . "/skin/$skinName/index.xml")) {
		$xml = file_get_contents(ROOT . "/skin/$skinName/index.xml");
		$xmls = new XMLStruct();
		if (!$xmls->open($xml, $context->getProperty('service.encoding')))
			return;
		$value = $xmls->getValue('/skin/default/commentMessage/none');
		if (is_null($value))
			Setting::setBlogSetting('noneCommentMessage', NULL, true);
		else
			Setting::setBlogSetting('noneCommentMessage', $value, true);

		$value = $xmls->getValue('/skin/default/commentMessage/single');
		if (is_null($value))
			Setting::setBlogSetting('singleCommentMessage', NULL, true);
		else
			Setting::setBlogSetting('singleCommentMessage', $value, true);

		$value = $xmls->getValue('/skin/default/trackbackMessage/none');
		if (is_null($value))
			Setting::setBlogSetting('noneTrackbackMessage', NULL, true);
		else
			Setting::setBlogSetting('noneTrackbackMessage', $value, true);

		$value = $xmls->getValue('/skin/default/trackbackMessage/single');
		if (is_null($value))
			Setting::setBlogSetting('singleTrackbackMessage', NULL, true);
		else
			Setting::setBlogSetting('singleTrackbackMessage', $value, true);
	}
}

function selectSkin($blogid, $skinName) {
	global $database, $service;
	requireComponent('Needlworks.Cache.PageCache');
	requireLibrary('blog.skin');
	$blogid = getBlogId();
	if (empty($skinName))
		return _t('실패했습니다.');

	if (strncmp($skinName, 'customize/', 10) == 0) {
		if (strcmp($skinName, "customize/$blogid") != 0)
			return _t('실패 했습니다');
	} else {
		$skinName = Path::getBaseName($skinName);
		if (($skinName === '.') || ($skinName ==='..'))
			return _t('실패 했습니다');
	}

	// Textcube-NG: skin.html 이 없는 스킨(없는 이름 포함)은 적용하지 않는다 — 스킨 목록(interface/owner/skin/index.php)과 같은 기준. 원본은 없는 이름도 저장해 블로그 화면이 깨졌다(r3-15)
	if (!file_exists(__TEXTCUBE_SKIN_DIR__."/$skinName/skin.html"))
		return _t('실패했습니다.');
	if (file_exists(__TEXTCUBE_SKIN_DIR__."/$skinName/index.xml")) {
		$xml = file_get_contents(__TEXTCUBE_SKIN_DIR__."/$skinName/index.xml");
		$xmls = new XMLStruct();
		if (!$xmls->open($xml, $service['encoding']))
			return _t('실패했습니다.');
		$assignments = array('skin'=>$skinName);
		$value = $xmls->getValue('/skin/default/recentEntries');
		if (!empty($value) || is_numeric($value))
			$assignments['entriesOnRecent'] = $value;

		$value = $xmls->getValue('/skin/default/recentComments');
		if (!empty($value) || is_numeric($value))
			$assignments['commentsOnRecent'] = $value;

		$value = $xmls->getValue('/skin/default/itemsOnGuestbook');
		if (!empty($value) || is_numeric($value))
			$assignments['commentsOnGuestbook'] = $value;

		$value = $xmls->getValue('/skin/default/tagsInCloud');
		if (!empty($value) || is_numeric($value))
			$assignments['tagsOnTagbox'] = $value;

		$value = $xmls->getValue('/skin/default/sortInCloud');
		if (!empty($value) || is_numeric($value))
			$assignments['tagboxAlign'] = $value;

		$value = $xmls->getValue('/skin/default/recentTrackbacks');
		if (!empty($value) || is_numeric($value))
			$assignments['trackbacksOnRecent'] = $value;

		$value = $xmls->getValue('/skin/default/expandComment');
		if (isset($value))
			$assignments['expandComment'] = ($value ? '1' : '0');

		$value = $xmls->getValue('/skin/default/expandTrackback');
		if (isset($value))
			$assignments['expandTrackback'] = ($value ? '1' : '0');

		$value = $xmls->getValue('/skin/default/lengthOfRecentNotice');
		if (!empty($value) || is_numeric($value))
			$assignments['recentNoticeLength'] = $value;

		$value = $xmls->getValue('/skin/default/lengthOfRecentEntry');
		if (!empty($value) || is_numeric($value))
			$assignments['recentEntryLength'] = $value;

		$value = $xmls->getValue('/skin/default/lengthOfRecentComment');
		if (!empty($value) || is_numeric($value))
			$assignments['recentCommentLength'] = $value;

		$value = $xmls->getValue('/skin/default/lengthOfRecentTrackback');
		if (!empty($value) || is_numeric($value))
			$assignments['recentTrackbackLength'] = $value;

		$value = $xmls->getValue('/skin/default/lengthOfLink');
		if (!empty($value) || is_numeric($value))
			$assignments['linkLength'] = $value;

		$value = $xmls->getValue('/skin/default/contentWidth');
		if (!empty($value) || is_numeric($value))
			$assignments['contentWidth'] = $value;

		$value = $xmls->getValue('/skin/default/showListOnCategory');
		if (isset($value))
			$assignments['showListOnCategory'] = $value;

		$value = $xmls->getValue('/skin/default/showListOnArchive');
		if (isset($value))
			$assignments['showListOnArchive'] = $value;

		$value = $xmls->getValue('/skin/default/showListOnTag');
		if (isset($value))
			$assignments['showListOnTag'] = $value;

		$value = $xmls->getValue('/skin/default/showListOnSearch');
		if (isset($value))
			$assignments['showListOnSearch'] = $value;

		$value = $xmls->getValue('/skin/default/showListOnAuthor');
		if (isset($value))
			$assignments['showListOnAuthor'] = $value;

		$value = $xmls->getValue('/skin/default/tree/color');
		if (isset($value))
			$assignments['colorOnTree'] = $value;

		$value = $xmls->getValue('/skin/default/tree/bgColor');
		if (isset($value))
			$assignments['bgcolorOnTree'] = $value;

		$value = $xmls->getValue('/skin/default/tree/activeColor');
		if (isset($value))
			$assignments['activecolorOnTree'] = $value;

		$value = $xmls->getValue('/skin/default/tree/activeBgColor');
		if (isset($value))
			$assignments['activebgcolorOnTree'] = $value;

		$value = $xmls->getValue('/skin/default/tree/labelLength');
		if (!empty($value) || is_numeric($value))
			$assignments['labelLengthOnTree'] = $value;

		$value = $xmls->getValue('/skin/default/tree/showValue');
		if (isset($value))
			$assignments['showValueOnTree'] = ($value ? '1' : '0');

		foreach($assignments as $name => $value) {
			Setting::setSkinSetting($name, $value, $blogid);
		}
		if ($xmls->doesExist('/skin/support')) {
			foreach ($xmls->selectNodes('/skin/support') as $support) {
				if (!empty($support['.attributes']['mobile']) && $support['.attributes']['mobile'] == "yes") {
					/// Main skin supports mobile, too.
					Setting::setBlogSetting('useiPhoneUI',0,true);
				} else {
					Setting::setBlogSetting('useiPhoneUI',1,true);
				}
			}
		} else {
			Setting::setBlogSetting('useiPhoneUI',1,true);
		}
		// none/single/multiple
		$value = $xmls->getValue('/skin/default/commentMessage/none');
		if (is_null($value))
			Setting::setBlogSetting('noneCommentMessage', NULL, true);
		else
			Setting::setBlogSetting('noneCommentMessage', $value, true);
		$value = $xmls->getValue('/skin/default/commentMessage/single');
		if (is_null($value))
			Setting::setBlogSetting('singleCommentMessage', NULL, true);
		else
			Setting::setBlogSetting('singleCommentMessage', $value, true);
		$value = $xmls->getValue('/skin/default/trackbackMessage/none');
		if (is_null($value))
			Setting::setBlogSetting('noneTrackbackMessage', NULL, true);
		else
			Setting::setBlogSetting('noneTrackbackMessage', $value, true);
		$value = $xmls->getValue('/skin/default/trackbackMessage/single');
		if (is_null($value))
			Setting::setBlogSetting('singleTrackbackMessage', NULL, true);
		else
			Setting::setBlogSetting('singleTrackbackMessage', $value, true);
	} else {
		Setting::setBlogSetting('noneCommentMessage', NULL, true);
		Setting::setBlogSetting('singleCommentMessage', NULL, true);
		Setting::setBlogSetting('noneTrackbackMessage', NULL, true);
		Setting::setBlogSetting('singleTrackbackMessage', NULL, true);
		Setting::setSkinSetting('skin',$skinName, $blogid);
	}

	Setting::removeBlogSetting("sidebarOrder",true);
	CacheControl::flushAll();
	CacheControl::flushSkin();
	Path::removeFiles(__TEXTCUBE_SKIN_CUSTOM_DIR__."/".getBlogId()."/");
	Setting::getSkinSettings($blogid, true); // refresh skin cache
	// PHP port(Textcube-NG): r3-12 스킨을 바꾸면 편집기 전용 CSS 를 다시 만든다(그 블로그가 생성기를 쓴 적이 있을 때만, 같은 지문·10초 안이면 건너뜀, 사용자 결정 11-08 ①·②).
	// 실패해도 스킨 바꾸기는 성공으로 둔다(편집기는 지문이 다르면 B2 를 씀)
	if (function_exists('refreshSkinEditorCSS')) {
		try {
			refreshSkinEditorCSS($blogid, $skinName);
		} catch (Throwable $e) {
		}
	}
	return true;
}

function writeSkinHtml($blogid, $contents, $mode, $file) {
	global $database;
	global $skinSetting;
	requireComponent('Needlworks.Cache.PageCache');
	requireLibrary('blog.skin');
	if ($mode != 'skin' && $mode != 'skin_keyword' && $mode != 'style')
		return _t('실패했습니다.');
	if ($skinSetting['skin'] != "customize/$blogid") {
		if (!@file_exists(__TEXTCUBE_SKIN_CUSTOM_DIR__."/$blogid")) {
			if (!@mkdir(__TEXTCUBE_SKIN_CUSTOM_DIR__."/$blogid"))
				return _t('권한이 없습니다.');
			@chmod(__TEXTCUBE_SKIN_CUSTOM_DIR__."/$blogid", 0777);
		}
		deltree(__TEXTCUBE_SKIN_CUSTOM_DIR__."/$blogid");
		copyRecusive(__TEXTCUBE_SKIN_DIR__."/{$skinSetting['skin']}", __TEXTCUBE_SKIN_CUSTOM_DIR__."/$blogid");
	}
	$file = basename($file);
	$skinSetting['skin'] = "customize/$blogid";
	Setting::setSkinSetting('skin',$skinSetting['skin'],$blogid);
	if (!Setting::setSkinSetting('skin',$skinSetting['skin'],$blogid))
		return _t('실패했습니다.');
	//if ($mode == 'style')
	//	$file = $mode . '.css';
	//else
	//	$file = $mode . '.html';
	if (!is_writable(__TEXTCUBE_SKIN_CUSTOM_DIR__."/$blogid/$file"))
		return ROOT . _t('권한이 없습니다.') . " -> ".__TEXTCUBE_SKIN_CUSTOM_DIR__."/$blogid/$file";
	$handler = fopen(__TEXTCUBE_SKIN_CUSTOM_DIR__."/$blogid/$file", 'w');
	if (fwrite($handler, $contents) === false) {
		fclose($handler);
		return _t('실패했습니다.');
	} else {
		fclose($handler);
		@chmod(__TEXTCUBE_SKIN_CUSTOM_DIR__."/$blogid/$file", 0666);
		CacheControl::flushAll();
		CacheControl::flushSkin();
		// PHP port(Textcube-NG): r3-12 스킨 편집을 저장하면 편집기 전용 CSS 를 다시 만든다(selectSkin 과 같음, 사용자 결정 11-08 ①·②)
		if (function_exists('refreshSkinEditorCSS')) {
			try {
				refreshSkinEditorCSS($blogid, "customize/$blogid");
			} catch (Throwable $e) {
			}
		}
		return true;
	}
}

function getCSSContent($blogid, $file) {
	global $skinSetting;
	// Path traversal 방어 — realpath() 로 정규화 후 스킨 디렉토리 내부·CSS 확장자 검증
	$skinBase = realpath(__TEXTCUBE_SKIN_DIR__ . '/' . $skinSetting['skin']);
	if ($skinBase === false) return false;
	$resolved = realpath($skinBase . '/' . $file);
	if ($resolved === false
		|| strncmp($resolved, $skinBase . DIRECTORY_SEPARATOR, strlen($skinBase) + 1) !== 0
		|| !preg_match('/\.css$/i', $resolved)
	) {
		return false;
	}
	return @file_get_contents($resolved);
}

// PHP port(Textcube-NG): r3-11 편집기 안에 블로그 화면용 CSS(skin.html 이 거는 스타일시트)를 싣는 공용 함수(사용자 결정 11-08 B1~B3·16:4x, 원본에 없는 새 기능).
// ED_tinyMCE·ST_Pages 편집기가 플러그인 설정 skinstyle 이 local·cdn 일 때만 부른다(none·기본값은 이 함수를 부르지 않고 지금 동작 그대로). $level 은 'local' 또는 'cdn'.
// 스킨 skin.html 에서 주석(<!-- -->)을 뺀 <head> 의 <link rel="stylesheet">(rel 에 alternate 가 있으면 뺌)를 차례대로 읽는다.
//  media 는 없거나 비었거나, 쉼표로 나눈 값(앞의 only 하나는 뗌) 가운데 all 또는 screen 이 있을 때만 넣는다(print·speech 등만 있거나
//  'screen and (max-width:600px)' 처럼 조건이 붙은 값만 있으면 뺌, 사용자 결정 11-08 (c)·16:4x 4)).
//  - 로컬 CSS("./" 가 있든 없든 상대 경로): ?·# 뒤를 떼고 앞의 "./" 하나를 뺀 경로가 [A-Za-z0-9_./-] 로만 된 .css 이고 ".."·"//" 가 없으며
//    realpath 가 스킨 폴더 아래의 파일일 때만 css 에 넣는다(사용자 결정 11-08 (b)). 주소는 Skin 생성자(library/blog.skin.php)처럼 스킨 폴더 주소 + 경로(조각은 rawurlencode, ?·# 없음).
//  - https CDN: $level 이 'cdn' 일 때만 본다. integrity(sha256·sha384·sha512)가 있고 주소가 정해진 글자로만 된 것만 cdn 에 넣는다. content_css 에는 넣지 않고
//    편집기 iframe 에 integrity·crossorigin 과 함께 직접 넣는다(B2). before 는 skin.html 에서 그 CDN 다음에 오는 로컬 CSS(없으면 되돌림 CSS·wysiwyg.css) 주소로,
//    편집기에서 그 링크 앞에 넣어 순서를 지킨다.
//  - 그 밖(http:·javascript:·data: 등 scheme·//·/ 로 시작하는 절대 경로·../ 탈출·% 인코딩·치환자 [##_..._##] 등)은 버린다.
// 순서는 블로그 화면용 CSS들 → 편집기 되돌림 CSS(resources/style/editor-skin-reset.css) → 스킨 wysiwyg.css(없으면 resources/style/default-wysiwyg.css)이다(사용자 결정 11-08 (a)).
// skin.html 이 wysiwyg.css 를 직접 걸었으면 그 자리에서는 빼고 맨 뒤에 한 번만 둔다.
// bodyClass 는 [##_article_rep_desc_##] 를 감싼 요소들(body 안쪽)의 class 중 [A-Za-z0-9_-] 로만 된 64자 이하 낱말을 바깥 요소부터 안쪽 요소 순서로 공백으로 이은 것이다(사용자 결정 11-08 B2·19:1x).
//  같은 낱말은 한 번만 넣고, 안쪽 요소부터 세어 32개까지만 넣는다. body·html 의 class 는 넣지 않는다. 바깥 #id 조상에 묶인 스킨 규칙은 편집기에 걸리지 않는다(사용자 결정).
//  class 는 태그의 속성을 차례로 나눠 읽어 이름이 class 인 첫 속성 값에서만 가져온다(다른 속성 값 안의 class= 는 쓰지 않음, 사용자 결정 11-08 19:1x).
// bodyChain 은 같은 감싼 요소들을 바깥 → 안쪽 순서로 적은 [tag, class] 목록이다. 편집기 JS 가 편집 구역 글자 크기를 블로그 화면에 맞출 때 잰다(사용자 결정 11-08 18:4x).
// editorOnly 는 css 가운데 블로그 화면에는 없는 편집기 전용 CSS 다. 그 JS 가 잴 때 잠시 끈다. 되돌림 CSS 는 늘 넣는다. wysiwyg.css 는 넣되,
//  skin.html 이 블로그 화면용으로 실은 link 가운데 스킨 wysiwyg.css 가 있으면 블로그 화면에도 있는 CSS 이므로 넣지 않는다(켠 채로 잼, 사용자 결정 11-08 19:3x).
//  default-wysiwyg.css(스킨 wysiwyg.css 가 없을 때)는 늘 넣는다.
// 스킨 이름은 Skin 생성자처럼 customize/ 는 현재 블로그, 그 밖은 마지막 이름만 쓴다. 스킨 폴더·skin.html 이 없거나 읽지 못하면 wysiwyg.css 하나만 돌려준다
// PHP port(Textcube-NG): r3-12 local 은 css 에 넣은 로컬 CSS 의 [스킨 폴더 기준 경로(file), 주소(url)] 목록, outer 는 본문 자리를 감싼 요소들(html → body → 바깥 → 안쪽)의
//  [tag, id, classes, unsure, idq, clq, anyq, attrs, font] 목록이다(skinEditorCSS_outer). 편집기 전용 CSS 생성기(아래 skinEditorCSS_*·getSkinEditorGeneratedCSS)가 쓴다(사용자 결정 11-08 "생성기 = r3-12")
function getSkinEditorStyles($skin, $serviceURL, $level = 'local') {
	$serviceURL = is_string($serviceURL) ? $serviceURL : '';
	$withCDN = ($level === 'cdn');
	$defaultCSS = $serviceURL . '/resources/style/default-wysiwyg.css';
	$result = array('css' => array($defaultCSS), 'cdn' => array(), 'bodyClass' => '', 'bodyChain' => array(), 'editorOnly' => array(), 'local' => array(), 'outer' => array());
	if (!defined('__TEXTCUBE_SKIN_DIR__') || !is_string($skin) || $skin === '')
		return $result;
	if (strncmp($skin, 'customize/', 10) == 0)
		$skin = 'customize/' . getBlogId();
	else
		$skin = Path::getBaseName($skin);
	if ($skin === '' || $skin === '.' || $skin === '..')
		return $result;
	$skinRoot = realpath(__TEXTCUBE_SKIN_DIR__);
	$skinBase = realpath(__TEXTCUBE_SKIN_DIR__ . '/' . $skin);
	if ($skinRoot === false || $skinBase === false || !is_dir($skinBase)
		|| strncmp($skinBase, $skinRoot . DIRECTORY_SEPARATOR, strlen($skinRoot) + 1) !== 0)
		return $result;
	// 스킨 폴더 주소: Skin 생성자처럼 __TEXTCUBE_SKIN_DIR__ 에서 ROOT 를 뺀 경로(데스크톱 /skin/blog)
	$skinDirPath = (defined('ROOT') && strncmp(__TEXTCUBE_SKIN_DIR__, ROOT . '/', strlen(ROOT) + 1) === 0) ? substr(__TEXTCUBE_SKIN_DIR__, strlen(ROOT)) : '/skin/blog';
	$skinURL = $serviceURL . $skinDirPath . '/' . implode('/', array_map('rawurlencode', explode('/', $skin))) . '/';
	$wysiwygCSS = is_file($skinBase . '/wysiwyg.css') ? $skinURL . 'wysiwyg.css' : $defaultCSS;
	$result['css'] = array($wysiwygCSS);
	$html = is_file($skinBase . '/skin.html') ? @file_get_contents($skinBase . '/skin.html') : false;
	if (!is_string($html) || $html === '')
		return $result;
	$html = preg_replace('/<!--.*?-->/s', '', $html);
	if (!is_string($html))
		return $result;
	$head = preg_match('/<head\b[^>]*>(.*?)<\/head\s*>/is', $html, $match) ? $match[1] : $html;
	if (preg_match_all('/<link\b([^>]*)>/i', $head, $links, PREG_SET_ORDER) === false)
		return $result;
	$css = array();
	$cdn = array();
	$local = array();	// PHP port(Textcube-NG): r3-12 생성기가 읽을 로컬 CSS([file, url], 사용자 결정 11-08)
	$wysiwygLinked = false;	// skin.html 이 블로그 화면용으로 스킨 wysiwyg.css 를 실었는지(실었으면 editorOnly 에 넣지 않음)
	foreach ($links as $link) {
		$attr = array();
		if (preg_match_all('/([A-Za-z][-A-Za-z0-9_:.]*)(?:\s*=\s*(?:"([^"]*)"|\'([^\']*)\'|([^\s"\'>]+)))?/', $link[1], $pairs, PREG_SET_ORDER)) {
			foreach ($pairs as $pair) {
				$name = strtolower($pair[1]);
				if (!isset($attr[$name]))	// 같은 이름이 거듭되면 처음 값(HTML 규칙)
					$attr[$name] = isset($pair[4]) ? $pair[4] : (isset($pair[3]) ? $pair[3] : (isset($pair[2]) ? $pair[2] : ''));
			}
		}
		$rel = isset($attr['rel']) ? preg_split('/\s+/', strtolower(trim($attr['rel']))) : array();
		if (!in_array('stylesheet', $rel, true) || in_array('alternate', $rel, true))
			continue;
		if (isset($attr['media']) && trim($attr['media']) !== '') {
			$media = array();
			foreach (explode(',', strtolower($attr['media'])) as $query)
				$media[] = preg_replace('/^only\s+/', '', trim($query));	// 'only screen'·'only all' 은 screen·all 과 같음
			if (!in_array('all', $media, true) && !in_array('screen', $media, true))
				continue;
		}
		$href = isset($attr['href']) ? trim($attr['href']) : '';
		if (strncmp($href, 'https://', 8) !== 0) {
			$file = preg_replace('/[?#].*$/s', '', $href);
			if (strncmp($file, './', 2) === 0)
				$file = substr($file, 2);
			if (!is_string($file) || !preg_match('#^[A-Za-z0-9_][A-Za-z0-9_./-]*\.css$#iD', $file) || strpos($file, '..') !== false || strpos($file, '//') !== false)
				continue;
			$resolved = realpath($skinBase . '/' . $file);
			if ($resolved === false || !is_file($resolved) || !preg_match('/\.css$/iD', $resolved)
				|| strncmp($resolved, $skinBase . DIRECTORY_SEPARATOR, strlen($skinBase) + 1) !== 0)
				continue;
			$url = $skinURL . implode('/', array_map('rawurlencode', explode('/', $file)));
			if ($url === $wysiwygCSS)	// 스킨 wysiwyg.css(같은 정규화 경로): 이 자리에서는 빼고 맨 뒤에 한 번만 둔다
				$wysiwygLinked = true;
			else if (!in_array($url, $css, true)) {
				$css[] = $url;
				$local[] = array('file' => $file, 'url' => $url);	// PHP port(Textcube-NG): r3-12 생성기가 읽을 로컬 CSS(스킨 폴더 기준 경로·주소, 사용자 결정 11-08)
			}
		} else if ($withCDN) {
			$integrity = isset($attr['integrity']) ? trim($attr['integrity']) : '';
			if (!preg_match('#^sha(256|384|512)-[A-Za-z0-9+/]+={0,2}(\s+sha(256|384|512)-[A-Za-z0-9+/]+={0,2})*$#D', $integrity)
				|| !preg_match('#^https://[A-Za-z0-9.-]+(:[0-9]{1,5})?/[A-Za-z0-9._~!$&()*+,;=:@%/-]*(\?[A-Za-z0-9._~!$&()*+,;=:@%/?-]*)?$#D', $href))
				continue;
			$crossorigin = null;
			if (isset($attr['crossorigin']))	// HTML 규칙: 빈 값·모르는 값은 anonymous
				$crossorigin = (strtolower(trim($attr['crossorigin'])) === 'use-credentials') ? 'use-credentials' : 'anonymous';
			$cdn[] = array('href' => $href, 'integrity' => $integrity, 'crossorigin' => $crossorigin, 'before' => count($css));
		}
	}
	if (defined('ROOT') && is_file(ROOT . '/resources/style/editor-skin-reset.css'))
		$css[] = $serviceURL . '/resources/style/editor-skin-reset.css';
	$css[] = $wysiwygCSS;
	foreach ($cdn as $i => $item)
		$cdn[$i]['before'] = isset($css[$item['before']]) ? $css[$item['before']] : null;
	// 본문 자리를 감싼 요소들(사용자 결정 11-08 B2): [##_article_rep_desc_##] 앞의 태그를 앞에서부터 읽어 열린 요소 목록을 만든다(치환 블록 <s_...>·빈 요소·script·style 안은 뺌).
	// 태그는 따옴표로 싼 속성 값 안의 > 를 태그 끝으로 보지 않는다(사용자 결정 11-08 19:1x).
	// 브라우저처럼 닫는 태그는 같은 이름의 가장 가까운 열린 요소까지 닫고(그 안의 닫지 않은 요소도 함께 닫힘) 짝이 없는 닫는 태그는 버린다. 빈 요소가 아닌 태그 끝의 / 는
	// HTML 처럼 무시한다(예: periwinkle 의 <button ... />OK</button>). 열린 p 바로 다음에 블록 요소가 오면 p 를, 열린 li·dd·dt 바로 다음에 같은 종류가 오면 앞의 것을 닫는다.
	// 끝까지 열려 있는 요소 가운데 마지막 body·html 의 안쪽 요소들이 본문 자리를 감싼 요소들이다(body·html 의 class 는 넣지 않음).
	// 요소의 class 는 태그의 속성을 앞에서부터 차례로 나눠 읽어(이름, = 뒤 "값"·'값'·따옴표 없는 값) 이름이 class(대소문자 무시)인 첫 속성 값에서만 가져온다.
	// 다른 속성 값 안의 class= 는 쓰지 않는다(사용자 결정 11-08 19:1x). 낱말은 [A-Za-z0-9_-] 로만 된 64자 이하만 쓰고 64자가 넘으면 버린다(사용자 결정 11-08 19:1x).
	// bodyClass = 그 요소들의 class 낱말을 안쪽 요소부터 모아 같은 낱말은 가장 안쪽 것 한 번만, 32개까지 넣고 바깥 → 안쪽 순서로 이은 것.
	// bodyChain = 그 요소들을 바깥 → 안쪽 순서로 적은 [tag, class](tag 는 아래 목록 밖이면 div, class 는 그 요소의 낱말 32개까지). 64겹이 넘으면 비운다(글자 크기를 맞추지 않음).
	// 값은 호출하는 쪽이 json_encode(HEX 플래그)로만 낸다
	$bodyClass = '';
	$bodyChain = array();
	$outer = array();	// PHP port(Textcube-NG): r3-12 본문 자리를 감싼 요소들(html·body 포함, skinEditorCSS_outer)
	$pos = strpos($html, '[##_article_rep_desc_##]');
	$before = ($pos === false) ? false : preg_replace('#<(script|style)\b[^>]*>.*?</\1\s*>#is', '', substr($html, 0, $pos));
	if (is_string($before) && preg_match_all('#<(/?)([A-Za-z][A-Za-z0-9_:-]*)\b((?:[^>"\']++|"[^"]*+"|\'[^\']*+\')*+)>#', $before, $tags, PREG_SET_ORDER)) {
		$void = array('area', 'base', 'br', 'col', 'embed', 'hr', 'img', 'input', 'link', 'meta', 'param', 'source', 'track', 'wbr');
		$blocks = array('address', 'article', 'aside', 'blockquote', 'details', 'div', 'dl', 'fieldset', 'figcaption', 'figure', 'footer', 'form', 'h1', 'h2', 'h3', 'h4', 'h5', 'h6',
			'header', 'hgroup', 'main', 'menu', 'nav', 'ol', 'p', 'pre', 'section', 'table', 'ul');
		$known = array('a', 'address', 'article', 'aside', 'b', 'big', 'blockquote', 'center', 'cite', 'dd', 'details', 'div', 'dl', 'dt', 'em', 'fieldset', 'figcaption', 'figure',
			'footer', 'form', 'h1', 'h2', 'h3', 'h4', 'h5', 'h6', 'header', 'hgroup', 'i', 'label', 'li', 'main', 'menu', 'nav', 'ol', 'p', 'pre', 'section', 'small', 'span',
			'strong', 'table', 'tbody', 'td', 'tfoot', 'th', 'thead', 'tr', 'u', 'ul');
		$open = array();
		foreach ($tags as $tag) {
			$name = strtolower($tag[2]);
			if (strncmp($name, 's_', 2) === 0 || in_array($name, $void, true))
				continue;
			if ($tag[1] === '/') {
				for ($j = count($open) - 1; $j >= 0; $j--) {
					if ($open[$j][0] === $name) {
						array_splice($open, $j);
						break;
					}
				}
				continue;
			}
			$top = empty($open) ? '' : $open[count($open) - 1][0];
			if (($top === 'p' && in_array($name, $blocks, true)) || ($top === 'li' && $name === 'li')
				|| (($top === 'dd' || $top === 'dt') && ($name === 'dd' || $name === 'dt')))
				array_pop($open);
			$open[] = array($name, $tag[3]);
		}
		$outer = skinEditorCSS_outer($open);	// PHP port(Textcube-NG): r3-12 html·body 를 잘라내기 전 목록으로 만든다(사용자 결정 11-08)
		$start = 0;
		foreach ($open as $j => $item) {
			if ($item[0] === 'body' || $item[0] === 'html')
				$start = $j + 1;
		}
		$open = array_slice($open, $start);
		$groups = array();	// 요소마다 bodyClass 에 넣을 낱말(안쪽 요소부터)
		$seen = array();
		$limit = 32;
		for ($j = count($open) - 1; $j >= 0; $j--) {
			$tokens = array();
			if (preg_match_all('#([^\s/>][^\s/>=]*)(?:\s*=\s*(?:"([^"]*)"|\'([^\']*)\'|([^\s>]*)))?#', $open[$j][1], $attrs, PREG_SET_ORDER)) {
				foreach ($attrs as $attr) {
					if (strtolower($attr[1]) !== 'class')
						continue;
					$value = isset($attr[4]) ? $attr[4] : (isset($attr[3]) ? $attr[3] : (isset($attr[2]) ? $attr[2] : ''));
					foreach (preg_split('/\s+/', trim($value)) as $token) {
						if (count($tokens) >= $limit)
							break;
						if (preg_match('/^[A-Za-z0-9_-]{1,64}$/D', $token) && !in_array($token, $tokens, true))
							$tokens[] = $token;
					}
					break;
				}
			}
			$fresh = array();
			foreach ($tokens as $token) {
				if (count($seen) < $limit && !in_array($token, $seen, true)) {
					$seen[] = $token;
					$fresh[] = $token;
				}
			}
			if (!empty($fresh))
				$groups[] = implode(' ', $fresh);
			$open[$j] = array('tag' => in_array($open[$j][0], $known, true) ? $open[$j][0] : 'div', 'class' => implode(' ', $tokens));
		}
		$bodyClass = implode(' ', array_reverse($groups));
		$bodyChain = (count($open) <= 64) ? $open : array();
	}
	$result['css'] = $css;
	$result['cdn'] = $cdn;
	$result['bodyClass'] = $bodyClass;
	$result['bodyChain'] = $bodyChain;
	$result['local'] = $local;	// PHP port(Textcube-NG): r3-12
	$result['outer'] = $outer;
	$editorOnly = array($serviceURL . '/resources/style/editor-skin-reset.css');
	if (!$wysiwygLinked)	// skin.html 이 실은 스킨 wysiwyg.css 는 블로그 화면에도 있으므로 끄지 않는다(사용자 결정 11-08 19:3x)
		$editorOnly[] = $wysiwygCSS;
	$result['editorOnly'] = array_values(array_intersect($css, $editorOnly));
	return $result;
}

// PHP port(Textcube-NG): r3-12 편집기 전용 CSS 생성기(사용자 결정 11-08 "생성기 = r3-12"·①·②, 원본에 없는 새 기능). ED_tinyMCE·ST_Pages 편집기 설정 skinstyle 이 local·cdn 일 때만 쓴다
// (none·기본값은 부르지 않음, 사용자 결정 11-08 4). getSkinEditorStyles() 의 local(skin.html 이 실은 로컬 CSS)과 skin.html <head> 의 <style>(media 규칙은 link 와 같음, link CSS 뒤)을 읽어
// 선택자에서 본문 자리를 감싼 요소들(outer = html → body → 바깥 → 안쪽)에 걸리는 앞부분을 편집기 본문(body.mce-content-body)으로 바꾼 CSS 를 만든다.
//  - 본문 안 요소에 걸리는 규칙: ':where(.mce-content-body) 선택자'. 앞부분이 감싼 요소에 확실히 걸리면 ':where(.mce-content-body):is(앞부분,*) 뒷부분'도 둔다(선택자 세기 유지).
//    html·body·head·:root 가 든 선택자는 첫 꼴을 두지 않는다.
//  - 감싼 요소 자체에 확실히 걸리는 규칙: html 은 ':where(html):is(선택자,*)', 그 밖은 ':where(.mce-content-body):is(선택자,*)'. 배치·글자 크기 속성(위치·float·display·너비·높이·
//    바깥 여백·overflow·transform·flex·grid·z-index·font-size 등)은 빼고 font 묶음 속성의 !important 는 뗀다.
//  - 블로그 화면에서 html 과 본문 자리가 받는 글자 크기를 CSS 로 셈해 끝에 html·편집기 본문 글자 크기(!important)로 둔다. 셈할 수 없으면(조건부 규칙·확실하지 않은 일치·calc·var·
//    vw 등, style 속성의 글자 설정) 만들지 않는다.
//  - url() 은 스킨 폴더 안 상대 경로만 스킨 폴더 주소로 바꾸고(밖으로 나가는 경로·scheme·// 는 선언째 버림), base64 이미지·글꼴 data: 와 #조각만 둔다. @import·@charset·@namespace·
//    @page·@layer·@container 와 모르는 at-rule, 안에 블록이 있는 규칙, expression·javascript:·behavior·-moz-binding·image-set 등이 든 선언, 따옴표 밖 역슬래시가 든 선언·규칙은 버린다.
//    선택자 가운데 하나라도 읽지 못하면 그 규칙을 버린다(브라우저 규칙과 같음). 끝으로 < 는 CSS escape(\3c)로 바꾼다.
// 결과는 cache/editorcss/<blogid>.css.cache(스킨 폴더 주소 자리는 \x01S\x01), 지문은 <blogid>.meta.cache(JSON: 스킨 이름·skin.html 과 로컬 CSS 의 [경로, mtime, size]·성공 여부·시각·sha1).
// cache/ 는 .htaccess·Dispatcher 가 웹에서 막고, 파일 이름은 정수 blogid 로만 만든다. 블로그마다 flock 으로 한 번에 하나만 만들고 임시 파일에 쓴 뒤 rename 한다.
// 같은 지문은 다시 만들지 않고(실패도 기록), 같은 블로그는 10초 안에 다시 만들지 않는다(사용자 결정 11-08 ①·②). 상한: 파일 32개·입력 1MB·출력 1MB·규칙 10000개·블록 깊이 16·
// 선택자 하나 2048자·복합 선택자 32개·선택자당 변형 8개·2초(선택자 읽기·매칭 안에서도 skinEditorCSS_tick 이 시각을 봄)·낱말 모든 시트 합계 100000개·메모리(낱말 나누는 동안과
// 선택자 읽기·매칭·글자 셈 동안(skinEditorCSS_tick) memory_get_usage 가 min(64MB, memory_limit/2) 를 넘으면 멈춤, 사용자 결정 11-09 "토큰 수 상한 추가").
// 넘으면 만들지 않는다. 편집기는 지문이 맞는 결과가 있을 때만 쓰고,
// 없거나 다르거나 만들지 못하면 r3-11 B2 를 쓴다. CDN 항목이 있으면 늘 B2 다(사용자 결정 11-08 2).
// 실패 기록은 지문이 바뀔 때까지 다시 만들지 않는다. 다만 캐시 파일 쓰기·rename 실패('io')만 기록 시각부터 600초 뒤 같은 지문이어도 다시 만든다(사용자 결정 11-08 23:1x, skinEditorCSS_retryable)
function skinEditorCSS_limits() {
	return array('version' => 1, 'files' => 32, 'input' => 1048576, 'output' => 1048576, 'rules' => 10000, 'depth' => 16, 'selector' => 2048,
		'compounds' => 32, 'variants' => 8, 'seconds' => 2.0, 'interval' => 10, 'meta' => 65536, 'chain' => 66, 'retry' => 600, 'tokens' => 100000,
		'memory' => 67108864);
}

// 메모리 상한(바이트): min('memory' 한도, memory_limit 의 절반). memory_limit 가 -1(무제한)이거나 읽을 수 없으면 'memory' 한도만 쓴다. 단위 K·M·G 를 셈한다
// PHP port(Textcube-NG): r3-12 사용자 결정 11-09 "토큰 수 상한 추가"(낱말이 많은 시트가 memory_limit 를 넘겨 치명 오류로 끝나던 것)
function skinEditorCSS_memCap($lim) {
	$cap = (float)$lim['memory'];
	$v = trim((string)ini_get('memory_limit'));
	if (preg_match('/^(\d+)\s*([KMG]?)$/iD', $v, $m)) {
		$n = (float)$m[1];
		$u = strtoupper($m[2]);
		if ($u === 'K')
			$n *= 1024;
		else if ($u === 'M')
			$n *= 1048576;
		else if ($u === 'G')
			$n *= 1073741824;
		if ($n > 0)
			$cap = min($cap, $n / 2);
	}
	return (int)$cap;
}

// 시간·메모리 검사(선택자 읽기·매칭처럼 한 규칙 안에서 오래 돌거나 결과가 쌓이는 곳이 부름): $end 를 주면 마감 시각과 메모리 상한($memCap, 바이트)을 정하고
// (skinEditorCSS_build 시작), 주지 않으면 부를 때마다 세어 256번마다 마감 시각을 넘었으면 'time', memory_get_usage 가 상한을 넘었으면 'memory' 로 멈춘다(B2).
// 선택자 하나의 매칭이 길어도 전체 2초 상한이 지켜지고, 읽은 선택자가 쌓여도 memory_limit 치명 오류 전에 멈춘다
// PHP port(Textcube-NG): r3-12 리뷰 D1 반영(구조 가상 클래스 + 66겹 사슬 선택자에서 규칙 하나가 수십만 단계를 돌던 것)
// PHP port(Textcube-NG): r3-12 v4 사용자 결정 11-09 "토큰 수 상한 추가"(규칙이 많은 시트에서 읽은 선택자가 쌓여 memory_limit 를 넘던 것)
function skinEditorCSS_tick($end = null, $memCap = null) {
	static $deadline = null;
	static $cap = null;
	static $count = 0;
	if ($end !== null) {
		$deadline = (float)$end;
		$cap = ($memCap !== null) ? (int)$memCap : null;
		$count = 0;
		return;
	}
	$count++;
	if ($count % 256 !== 0)
		return;
	if ($deadline !== null && microtime(true) > $deadline)
		throw new RuntimeException('time');
	if ($cap !== null && memory_get_usage() > $cap)
		throw new RuntimeException('memory');
}

// 실패 사유 나누기(사용자 결정 11-08 23:1x "쓰기 오류만 10분 뒤 재시도"): 'io' = 만든 CSS 를 캐시 파일에 쓰거나 rename 하지 못함. 이것만 기록 시각부터 600초가 지나면
// 같은 지문이어도 다시 만든다. 그 밖('time'·'pcre'·한도 초과('tokens'·'memory' 포함)·문법·읽기·글자 셈·'running'(진행 중 표식이 남음 = 만들다 치명 오류로 끝남) 등)은 지문이 바뀔 때까지 다시 만들지 않는다
function skinEditorCSS_retryable($meta) {
	return isset($meta['reason']) && $meta['reason'] === 'io';
}

function skinEditorCSS_metaText($lim, $fp, $ok, $reason, $tried, $sha1) {
	$data = json_encode(array('v' => $lim['version'], 'fp' => $fp, 'ok' => $ok, 'reason' => (string)substr((string)$reason, 0, 64), 'tried' => $tried, 'sha1' => $sha1), JSON_UNESCAPED_SLASHES);
	return (is_string($data) && strlen($data) <= $lim['meta']) ? $data : false;
}

// 스킨 이름 정리: getSkinEditorStyles() 와 같다(customize/ 는 현재 블로그, 그 밖은 마지막 이름만, 스킨 폴더 아래 폴더만)
function skinEditorCSS_paths($skin) {
	if (!defined('__TEXTCUBE_SKIN_DIR__') || !is_string($skin) || $skin === '')
		return false;
	if (strncmp($skin, 'customize/', 10) == 0)
		$skin = 'customize/' . getBlogId();
	else
		$skin = Path::getBaseName($skin);
	if (!is_string($skin) || $skin === '' || $skin === '.' || $skin === '..')
		return false;
	$skinRoot = realpath(__TEXTCUBE_SKIN_DIR__);
	$skinBase = realpath(__TEXTCUBE_SKIN_DIR__ . '/' . $skin);
	if ($skinRoot === false || $skinBase === false || !is_dir($skinBase)
		|| strncmp($skinBase, $skinRoot . DIRECTORY_SEPARATOR, strlen($skinRoot) + 1) !== 0)
		return false;
	$skinDirPath = (defined('ROOT') && strncmp(__TEXTCUBE_SKIN_DIR__, ROOT . '/', strlen(ROOT) + 1) === 0) ? substr(__TEXTCUBE_SKIN_DIR__, strlen(ROOT)) : '/skin/blog';
	return array('name' => $skin, 'base' => $skinBase, 'dirPath' => $skinDirPath);
}

function skinEditorCSS_cacheDir($create) {
	if (!defined('__TEXTCUBE_CACHE_DIR__'))
		return false;
	$dir = __TEXTCUBE_CACHE_DIR__ . '/editorcss';
	if (is_dir($dir))
		return $dir;
	if (!$create)
		return false;
	if (!@mkdir($dir, 0700) && !is_dir($dir))
		return false;
	return $dir;
}

// 지문: 스킨 이름·스킨 폴더 주소 경로·[skin.html, 로컬 CSS…] 의 [경로, mtime, size]. 읽지 못하면 false
function skinEditorCSS_fingerprint($paths, $local) {
	$lim = skinEditorCSS_limits();
	if (!is_array($local) || count($local) > $lim['files'])
		return false;
	$list = array('skin.html');
	foreach ($local as $item) {
		if (!is_array($item) || !isset($item['file']) || !is_string($item['file']))
			return false;
		$list[] = $item['file'];
	}
	clearstatcache();
	$files = array();
	foreach ($list as $rel) {
		$stat = @stat($paths['base'] . '/' . $rel);
		if ($stat === false)
			return false;
		$files[] = array($rel, (int)$stat['mtime'], (int)$stat['size']);
	}
	return array('skin' => $paths['name'], 'dir' => $paths['dirPath'], 'files' => $files);
}

function skinEditorCSS_readMeta($dir, $blogid) {
	$lim = skinEditorCSS_limits();
	$path = $dir . '/' . $blogid . '.meta.cache';
	if (!is_file($path))
		return null;
	$size = @filesize($path);
	if ($size === false || $size > $lim['meta'])
		return null;
	$raw = @file_get_contents($path);
	$meta = is_string($raw) ? json_decode($raw, true) : null;
	if (!is_array($meta) || !isset($meta['v'], $meta['fp'], $meta['ok'], $meta['reason'], $meta['tried'], $meta['sha1']) || $meta['v'] !== $lim['version']
		|| !is_bool($meta['ok']) || !is_string($meta['reason']) || !is_int($meta['tried']) || !is_string($meta['sha1']))
		return null;
	return $meta;
}

// 돌려줌: 만든 CSS(문자열) | false(같은 지문으로 실패를 기록함) | null(없음·지문이 다름·파일이 맞지 않음·다시 만들 'io' 실패)
function skinEditorCSS_load($dir, $blogid, $fp) {
	$lim = skinEditorCSS_limits();
	$meta = skinEditorCSS_readMeta($dir, $blogid);
	if ($meta === null || $meta['fp'] !== $fp)
		return null;
	if ($meta['ok'] !== true) {
		$now = time();
		if (skinEditorCSS_retryable($meta) && ($meta['tried'] > $now || $now - $meta['tried'] >= $lim['retry']))	// 쓰기 오류만 600초 뒤 다시(사용자 결정 11-08 23:1x)
			return null;
		return false;
	}
	$path = $dir . '/' . $blogid . '.css.cache';
	$size = is_file($path) ? @filesize($path) : false;
	if ($size === false || $size > $lim['output'])
		return null;
	$css = @file_get_contents($path);
	if (!is_string($css) || !hash_equals($meta['sha1'], sha1($css)))
		return null;
	return $css;
}

function skinEditorCSS_put($path, $data) {
	if (!is_string($data))
		return false;
	$tmp = $path . '.tmp.cache';
	if (@file_put_contents($tmp, $data) !== strlen($data)) {
		@unlink($tmp);
		return false;
	}
	@chmod($tmp, 0600);
	if (!@rename($tmp, $path)) {
		@unlink($tmp);
		return false;
	}
	return true;
}

// 만들기(사용자 결정 11-08 ①·②): 블로그마다 잠금(기다리지 않음) → 같은 지문 결과가 있으면 그것 → 10초 안이면 건너뜀 → 진행 중 표식 → 만든 뒤 지문이 그대로일 때만 CSS → 지문 차례로 쓴다.
// 진행 중 표식(reason 'running', ok false): 만들기 전에 지문 파일에 먼저 쓴다. 만드는 중 PHP 치명 오류(max_execution_time·memory_limit)로 끝나면 이 표식이 같은 지문의 실패로 남아
// 편집기를 열 때마다 다시 만들다 죽는 일을 막는다. 표식을 쓰지 못하면(쓰기 오류) 만들지 않는다. 만든 사이에 지문이 바뀌면 표식(옛 지문)을 그대로 두어 10초 간격만 걸린다.
// CSS 를 쓰지 못하면 'io' 로 기록한다(600초 뒤 다시, 사용자 결정 11-08 23:1x). 마지막 지문 기록을 쓰지 못하면 표식을 지운다(쓰기 오류가 치명 오류 기록으로 굳지 않게).
// 돌려줌: 만든 CSS | false(실패를 기록함) | null(잠금·간격·쓰기 실패 등으로 건너뜀)
// PHP port(Textcube-NG): r3-12 리뷰 D2(쓰기 오류만 10분 뒤 재시도)·D3(잠금 파일 0600)·진행 중 표식 반영
function skinEditorCSS_generate($blogid, $paths, $styles) {
	$lim = skinEditorCSS_limits();
	$dir = skinEditorCSS_cacheDir(true);
	if ($dir === false)
		return null;
	$fp = skinEditorCSS_fingerprint($paths, $styles['local']);
	if ($fp === false)
		return null;
	$lockPath = $dir . '/' . $blogid . '.lock.cache';
	$lock = @fopen($lockPath, 'c');
	if ($lock === false)
		return null;
	@chmod($lockPath, 0600);	// 다른 캐시 파일과 같은 권한(실패는 무시)
	if (!@flock($lock, LOCK_EX | LOCK_NB)) {
		fclose($lock);
		return null;
	}
	$metaPath = $dir . '/' . $blogid . '.meta.cache';
	$result = null;
	try {
		$found = skinEditorCSS_load($dir, $blogid, $fp);
		if ($found !== null) {
			$result = $found;
		} else {
			$meta = skinEditorCSS_readMeta($dir, $blogid);
			$now = time();
			if (($meta === null || $meta['tried'] > $now || $now - $meta['tried'] >= $lim['interval'])
				&& skinEditorCSS_put($metaPath, skinEditorCSS_metaText($lim, $fp, false, 'running', $now, ''))) {
				$css = false;
				$reason = '';
				try {
					$css = skinEditorCSS_build($paths, $styles, $lim);
				} catch (RuntimeException $e) {
					$reason = $e->getMessage();
				} catch (Throwable $e) {
					$reason = 'error';
				}
				if (skinEditorCSS_fingerprint($paths, $styles['local']) === $fp) {
					$ok = is_string($css);
					if ($ok && !skinEditorCSS_put($dir . '/' . $blogid . '.css.cache', $css)) {
						$ok = false;
						$reason = 'io';
					}
					if (skinEditorCSS_put($metaPath, skinEditorCSS_metaText($lim, $fp, $ok, $ok ? '' : $reason, $now, $ok ? sha1($css) : '')))
						$result = $ok ? $css : false;
					else
						@unlink($metaPath);
				}
			}
		}
	} catch (Throwable $e) {
		$result = null;
	} finally {
		@flock($lock, LOCK_UN);
		@fclose($lock);
	}
	return $result;
}

// 편집기(ED_tinyMCE·ST_Pages)가 부른다. $styles = getSkinEditorStyles() 결과. 쓸 수 있는 결과가 있으면 array('style' => 넣을 CSS(스킨 폴더 주소 채움),
// 'css' => content_css(로컬 CSS 를 뺀 되돌림 CSS·wysiwyg.css), 'before' => <style> 을 넣을 자리(그 첫 링크)), 없으면 null(B2)
function getSkinEditorGeneratedCSS($blogid, $skin, $serviceURL, $styles) {
	try {
		$blogid = is_numeric($blogid) ? (int)$blogid : 0;
		if ($blogid <= 0 || !is_array($styles) || !isset($styles['css'], $styles['local'], $styles['outer']) || !is_array($styles['css'])
			|| !empty($styles['cdn']) || empty($styles['local']) || empty($styles['outer']))
			return null;
		$paths = skinEditorCSS_paths($skin);
		if ($paths === false)
			return null;
		$fp = skinEditorCSS_fingerprint($paths, $styles['local']);
		if ($fp === false)
			return null;
		$dir = skinEditorCSS_cacheDir(false);
		$css = ($dir === false) ? null : skinEditorCSS_load($dir, $blogid, $fp);
		if ($css === null)
			$css = skinEditorCSS_generate($blogid, $paths, $styles);
		if (!is_string($css))
			return null;
		$localURL = array();
		foreach ($styles['local'] as $item)
			$localURL[] = $item['url'];
		$rest = array();
		foreach ($styles['css'] as $url) {
			if (is_string($url) && !in_array($url, $localURL, true))
				$rest[] = $url;
		}
		if (empty($rest))
			return null;
		$serviceURL = is_string($serviceURL) ? $serviceURL : '';
		$skinURL = $serviceURL . $paths['dirPath'] . '/' . implode('/', array_map('rawurlencode', explode('/', $paths['name']))) . '/';
		// CSS 문자열 url("…") 안에 들어가므로 따옴표·역슬래시·꺾쇠·공백·제어 문자는 % 로 바꾼다
		$skinURL = preg_replace_callback('/["\\\\<>\s\x00-\x1F\x7F]/', function ($m) {
			return rawurlencode($m[0]);
		}, $skinURL);
		if (!is_string($skinURL))
			return null;
		return array('style' => str_replace("\x01S\x01", $skinURL, $css), 'css' => $rest, 'before' => $rest[0]);
	} catch (Throwable $e) {
		return null;
	}
}

// 스킨을 바꾸거나(selectSkin) 스킨 편집을 저장한(writeSkinHtml) 뒤 부른다. 그 블로그가 생성기를 쓴 적이 있을 때(지문 파일이 있을 때)만, 지문이 달라졌으면 다시 만든다
function refreshSkinEditorCSS($blogid, $skin) {
	$blogid = is_numeric($blogid) ? (int)$blogid : 0;
	if ($blogid <= 0)
		return false;
	$dir = skinEditorCSS_cacheDir(false);
	if ($dir === false || !is_file($dir . '/' . $blogid . '.meta.cache'))
		return false;
	$paths = skinEditorCSS_paths($skin);
	if ($paths === false)
		return false;
	$styles = getSkinEditorStyles($skin, '', 'local');
	if (!is_array($styles) || empty($styles['local']) || empty($styles['outer']))
		return false;
	return is_string(skinEditorCSS_generate($blogid, $paths, $styles));
}

// 본문 자리를 감싼 요소들: getSkinEditorStyles() 가 만든 열린 요소 목록([이름, 속성 문자열], body·html 포함)에서 마지막 html·body 와 그 안쪽 요소들을 [html, body, 바깥 … 안쪽] 으로 만든다.
// html·body 태그가 없으면 속성 없는 것으로 채운다. id·class 낱말은 [A-Za-z0-9_-] 64자 이하만 쓴다. 치환자([##_)가 든 id 값·class 낱말·속성 이름이 있으면 idq·clq·anyq 를 세우고
// unsure(규칙 고르기에서는 일치를 확실히 정하지 않음)로 둔다. 치환자가 아닌 잘못된 값(예: id="#LN-content")은 그 값 그대로라서 어떤 선택자와도 맞지 않는 것으로 본다(unsure 아님).
// attrs 는 속성 이름 => 처음 값(속성 선택자 비교용). font 는 style 속성에 글자 설정이 있는지다(있으면 글자 크기를 셈하지 않음). 66겹이 넘거나 태그 이름이 [a-z][a-z0-9-]* 가 아니면 빈 목록(생성기를 쓰지 않음)
// PHP port(Textcube-NG): r3-12 치환자 처리(사용자 결정 11-08 22:0x "없는 것으로 봄" — 글자 크기 셈에서는 치환자 값만 없는 것으로 보고 나머지는 실제 값으로 비교, r3-11 B2 bodyClass 와 같은 방식)
function skinEditorCSS_outer($open) {
	$lim = skinEditorCSS_limits();
	if (!is_array($open))
		return array();
	$html = '';
	$body = '';
	$start = 0;
	foreach ($open as $j => $item) {
		if (!is_array($item) || !isset($item[0], $item[1]) || !is_string($item[0]) || !is_string($item[1]))
			return array();
		if ($item[0] === 'html') {
			$html = $item[1];
			$start = $j + 1;
		} else if ($item[0] === 'body') {
			$body = $item[1];
			$start = $j + 1;
		}
	}
	$list = array_merge(array(array('html', $html), array('body', $body)), array_slice($open, $start));
	if (count($list) > $lim['chain'])
		return array();
	$chain = array();
	foreach ($list as $item) {
		if (!preg_match('/^[a-z][a-z0-9-]*$/D', $item[0]))
			return array();
		$el = array('tag' => $item[0], 'id' => null, 'classes' => array(), 'unsure' => false, 'idq' => false, 'clq' => false, 'anyq' => false, 'attrs' => array(), 'font' => false);
		if (preg_match_all('#([^\s/>][^\s/>=]*)(?:\s*=\s*(?:"([^"]*)"|\'([^\']*)\'|([^\s>]*)))?#', $item[1], $attrs, PREG_SET_ORDER)) {
			foreach ($attrs as $attr) {
				$name = strtolower($attr[1]);
				if (strpos($name, '[##_') !== false) {	// 치환자로 속성 이름이 정해짐: 어떤 속성이든 있을 수 있음
					$el['anyq'] = $el['idq'] = $el['clq'] = true;
					continue;
				}
				if (array_key_exists($name, $el['attrs']))	// 같은 이름이 거듭되면 처음 값(HTML 규칙)
					continue;
				$value = isset($attr[4]) ? $attr[4] : (isset($attr[3]) ? $attr[3] : (isset($attr[2]) ? $attr[2] : ''));
				$el['attrs'][$name] = $value;
				if ($name === 'id') {
					if (strpos($value, '[##_') !== false)
						$el['idq'] = true;
					else if (preg_match('/^[A-Za-z0-9_-]{1,64}$/D', $value))
						$el['id'] = $value;
				} else if ($name === 'class') {
					foreach (preg_split('/\s+/', trim($value)) as $token) {
						if ($token === '')
							continue;
						if (strpos($token, '[##_') !== false)
							$el['clq'] = true;
						else if (preg_match('/^[A-Za-z0-9_-]{1,64}$/D', $token))
							$el['classes'][] = $token;
					}
				} else if ($name === 'style' && stripos($value, 'font') !== false) {
					$el['font'] = true;
				}
			}
		}
		$el['unsure'] = $el['idq'] || $el['clq'] || $el['anyq'];
		$chain[] = $el;
	}
	return $chain;
}

// 태그 속성 읽기: getSkinEditorStyles() 의 link 속성 읽기와 같다(같은 이름이 거듭되면 처음 값)
function skinEditorCSS_attrs($text) {
	$attr = array();
	if (preg_match_all('/([A-Za-z][-A-Za-z0-9_:.]*)(?:\s*=\s*(?:"([^"]*)"|\'([^\']*)\'|([^\s"\'>]+)))?/', $text, $pairs, PREG_SET_ORDER)) {
		foreach ($pairs as $pair) {
			$name = strtolower($pair[1]);
			if (!isset($attr[$name]))
				$attr[$name] = isset($pair[4]) ? $pair[4] : (isset($pair[3]) ? $pair[3] : (isset($pair[2]) ? $pair[2] : ''));
		}
	}
	return $attr;
}

// media 종류: 0 = 늘 걸림(all·screen, 앞의 only 는 뗌), 1 = 조건에 따라 걸림(조건이 붙음·not), 2 = 화면에는 걸리지 않음(print 등 다른 매체만)
function skinEditorCSS_mediaKind($media) {
	$kind = 2;
	foreach (explode(',', strtolower($media)) as $query) {
		$query = preg_replace('/^only /', '', trim(preg_replace('/\s+/', ' ', $query)));
		if ($query === '')
			continue;
		if ($query === 'all' || $query === 'screen')
			return 0;
		if (strncmp($query, 'not ', 4) === 0 || strncmp($query, '(', 1) === 0 || preg_match('/^(all|screen) and /', $query))
			$kind = 1;
	}
	return $kind;
}

function skinEditorCSS_build($paths, $styles, $lim) {
	$ctx = array('lim' => $lim, 'end' => microtime(true) + $lim['seconds'], 'rules' => 0, 'paths' => $paths, 'outer' => $styles['outer'], 'items' => array());
	skinEditorCSS_tick($ctx['end'], skinEditorCSS_memCap($lim));	// 선택자 읽기·매칭 안의 시간·메모리 검사도 같은 마감 시각·상한(리뷰 D1, v4)
	if (count($styles['local']) > $lim['files'])
		throw new RuntimeException('files');
	$sheets = array();
	$total = 0;
	foreach ($styles['local'] as $item) {
		$file = $item['file'];
		if (!is_string($file) || !preg_match('#^[A-Za-z0-9_][A-Za-z0-9_./-]*\.css$#iD', $file) || strpos($file, '..') !== false || strpos($file, '//') !== false)
			throw new RuntimeException('path');
		$resolved = realpath($paths['base'] . '/' . $file);
		if ($resolved === false || !is_file($resolved) || strncmp($resolved, $paths['base'] . DIRECTORY_SEPARATOR, strlen($paths['base']) + 1) !== 0)
			throw new RuntimeException('path');
		$size = @filesize($resolved);
		if ($size === false || $size > $lim['input'] - $total)
			throw new RuntimeException('input-size');
		$text = @file_get_contents($resolved);
		if (!is_string($text))
			throw new RuntimeException('read');
		$total += strlen($text);
		$slash = strrpos($file, '/');
		$sheets[] = array($text, ($slash === false) ? '' : substr($file, 0, $slash));
	}
	// skin.html <head> 의 <style>: HTML 주석·script 를 뺀 뒤 찾는다(<style> 안의 <!-- --> 는 CSS 의 CDO·CDC 로 두고 아래에서 뗌). 기준 폴더는 스킨 폴더
	$html = @file_get_contents($paths['base'] . '/skin.html');
	if (!is_string($html) || strlen($html) > $lim['input'])
		throw new RuntimeException('read');
	$html = preg_replace_callback('#(<style\b[^>]*>.*?</style\s*>)|<script\b[^>]*>.*?</script\s*>|<!--.*?-->#is', function ($m) {
		return (isset($m[1]) && $m[1] !== '') ? $m[1] : '';
	}, $html);
	if (!is_string($html))
		throw new RuntimeException('pcre');
	$head = preg_match('/<head\b[^>]*>(.*?)<\/head\s*>/is', $html, $match) ? $match[1] : $html;
	if (preg_match_all('#<style\b([^>]*)>(.*?)</style\s*>#is', $head, $blocks, PREG_SET_ORDER) === false)
		throw new RuntimeException('pcre');
	foreach ($blocks as $block) {
		$attr = skinEditorCSS_attrs($block[1]);
		if (isset($attr['type']) && !in_array(strtolower(trim($attr['type'])), array('', 'text/css'), true))
			continue;
		if (isset($attr['media']) && trim($attr['media']) !== '' && skinEditorCSS_mediaKind($attr['media']) !== 0)
			continue;
		$total += strlen($block[2]);
		if ($total > $lim['input'])
			throw new RuntimeException('input-size');
		$sheets[] = array($block[2], '');
	}
	$left = $lim['tokens'];
	foreach ($sheets as $sheet) {
		$tokens = skinEditorCSS_tokens($sheet[0], $left);
		$left -= count($tokens);
		$i = 0;
		skinEditorCSS_parse($ctx, $tokens, $i, 0, array(), false, $sheet[1]);
		unset($tokens);
	}
	$font = skinEditorCSS_font($ctx);
	$out = str_replace('<', '\\3c ', skinEditorCSS_emit($ctx) . $font);
	if (strlen($out) > $lim['output'])
		throw new RuntimeException('output-size');
	return $out;
}

// 낱말 나누기: BOM 을 떼고, UTF-8 이 아니면 @charset 이 euc-kr·cp949 일 때만 바꾼다. 제어 문자를 지우고 줄바꿈을 LF 로 맞춘 뒤 주석을 지우고
// 따옴표 없는 url(…) 을 url("…") 로 바꾼다(끝나지 않은 주석·문자열은 실패). 낱말: 's' 문자열, 'p' { } ; ( ), 'o' 그 밖
// $left = 이 시트에 남은 낱말 수(모든 시트 합계 'tokens' 한도에서 앞 시트 몫을 뺀 값, 주지 않으면 한도 전체). 나누기 전에 따옴표 밖 { } ; ( ) 개수(낱말 수의
// 하한)로 넘을 것을 먼저 거절하고, 결과 배열 없이 낱말 수를 정확히 센 뒤 넘으면 거절한다. 배열을 만든 뒤와 낱말 256개마다 메모리(skinEditorCSS_memCap)를 본다.
// 넘으면 'tokens'·'memory' 로 멈춘다(B2). preg_match_all 결과($parts)는 낱말로 옮긴 칸부터 풀어 두 배열이 함께 커지지 않게 한다
// PHP port(Textcube-NG): r3-12 사용자 결정 11-09 "토큰 수 상한 추가"
// PHP port(Textcube-NG): r3-12 v5 리뷰 m1(결과 배열을 반환할 때까지 풀지 않아 최대 메모리가 두 배열 합에 가깝던 것)
function skinEditorCSS_tokens($text, $left = null) {
	$lim = skinEditorCSS_limits();
	if ($left === null)
		$left = $lim['tokens'];
	$memCap = skinEditorCSS_memCap($lim);
	if (strncmp($text, "\xEF\xBB\xBF", 3) === 0)
		$text = substr($text, 3);
	if (!preg_match('//u', $text)) {
		if (!preg_match('/^@charset\s+["\'](euc-kr|cp949|uhc|ks_c_5601-1987)["\']/i', $text) || !function_exists('mb_convert_encoding'))
			throw new RuntimeException('encoding');
		$text = mb_convert_encoding($text, 'UTF-8', 'CP949');
		if (!is_string($text) || !preg_match('//u', $text))
			throw new RuntimeException('encoding');
	}
	$text = preg_replace('/[\x00-\x08\x0B\x0E-\x1F\x7F]/', '', $text);
	if (!is_string($text))
		throw new RuntimeException('pcre');
	$text = str_replace(array("\r\n", "\r", "\f"), "\n", $text);
	$text = preg_replace_callback('~("(?:[^"\\\\\n]++|\\\\[\s\S])*+"|\'(?:[^\'\\\\\n]++|\\\\[\s\S])*+\')|(/\*.*?\*/)|(/\*)|(?<![A-Za-z0-9_-])url\(\s*+([^"\'()\\\\\s]*+)\s*+\)~is', function ($m) {
		if (isset($m[4]))
			return 'url("' . $m[4] . '")';
		if (isset($m[3]) && $m[3] !== '')
			throw new RuntimeException('comment');
		if (isset($m[2]) && $m[2] !== '')
			return '';
		return $m[0];
	}, $text);
	if (!is_string($text))
		throw new RuntimeException('pcre');
	$bare = preg_replace('~"(?:[^"\\\\\n]++|\\\\[\s\S])*+"|\'(?:[^\'\\\\\n]++|\\\\[\s\S])*+\'~', '', $text);
	if (!is_string($bare))
		throw new RuntimeException('pcre');
	$chars = count_chars($bare, 1);
	unset($bare);
	$least = 0;
	foreach (array(123, 125, 59, 40, 41) as $c) {	// { } ; ( )
		if (isset($chars[$c]))
			$least += $chars[$c];
	}
	if ($least > $left)
		throw new RuntimeException('tokens');
	$re = '~("(?:[^"\\\\\n]++|\\\\[\s\S])*+"|\'(?:[^\'\\\\\n]++|\\\\[\s\S])*+\')|([{};()])|([^"\'{};()]++)|(["\'])~';
	$count = preg_match_all($re, $text);	// 결과 배열 없이 개수만 셈(큰 배열을 만들기 전에 정확한 낱말 수로 거절)
	if ($count === false)
		throw new RuntimeException('pcre');
	if ($count > $left)
		throw new RuntimeException('tokens');
	if (preg_match_all($re, $text, $parts, PREG_SET_ORDER) === false)
		throw new RuntimeException('pcre');
	if (memory_get_usage() > $memCap)
		throw new RuntimeException('memory');
	$tokens = array();
	$n = count($parts);
	for ($k = 0; $k < $n; $k++) {	// foreach(값)는 루프 안 unset 에서 배열을 복사하므로 번호로 돈다(PREG_SET_ORDER 열쇠는 0 부터 차례)
		$part = $parts[$k];
		unset($parts[$k]);
		if (count($tokens) % 256 === 255 && memory_get_usage() > $memCap)
			throw new RuntimeException('memory');
		if (isset($part[4]) && $part[4] !== '')
			throw new RuntimeException('string');
		if (isset($part[3]) && $part[3] !== '') {
			if ((strlen($part[3]) - strlen(rtrim($part[3], '\\'))) % 2 === 1)	// { } ; ( ) 따옴표를 escape 함
				throw new RuntimeException('escape');
			$tokens[] = array('o', $part[3]);
		} else if (isset($part[2]) && $part[2] !== '') {
			$tokens[] = array('p', $part[2]);
		} else {
			$tokens[] = array('s', $part[1]);
		}
	}
	unset($parts, $part);
	return $tokens;
}

// 블록 안(맨 위 또는 @media·@supports 안)을 읽는다. $wrap = 감싼 at-rule 머리 목록, $cond = 조건에 따라 걸리는 블록 안인지
function skinEditorCSS_parse(&$ctx, $tokens, &$i, $depth, $wrap, $cond, $dirRel) {
	$n = count($tokens);
	$prelude = '';
	$paren = 0;
	while ($i < $n) {
		$type = $tokens[$i][0];
		$text = $tokens[$i][1];
		$i++;
		if ($type !== 'p') {
			$prelude .= $text;
			continue;
		}
		if ($text === '(' || $text === ')') {
			$paren += ($text === '(') ? 1 : -1;
			$prelude .= $text;
			continue;
		}
		if ($paren !== 0)
			throw new RuntimeException('syntax');
		if ($text === ';') {	// @charset·@import·@namespace 등 문장 at-rule 과 깨진 조각은 버린다
			$prelude = '';
			continue;
		}
		if ($text === '}') {
			if ($depth > 0)
				return;
			$prelude = '';
			continue;
		}
		skinEditorCSS_block($ctx, $tokens, $i, $depth, $wrap, $cond, $dirRel, trim(str_replace(array('<!--', '-->'), '', $prelude)));
		$prelude = '';
	}
}

function skinEditorCSS_skip($tokens, &$i) {
	$n = count($tokens);
	$level = 1;
	while ($i < $n) {
		$token = $tokens[$i];
		$i++;
		if ($token[0] !== 'p')
			continue;
		if ($token[1] === '{') {
			$level++;
		} else if ($token[1] === '}') {
			$level--;
			if ($level === 0)
				return;
		}
	}
}

function skinEditorCSS_block(&$ctx, $tokens, &$i, $depth, $wrap, $cond, $dirRel, $prelude) {
	if (microtime(true) > $ctx['end'])
		throw new RuntimeException('time');
	if ($depth + 1 > $ctx['lim']['depth'])
		throw new RuntimeException('depth');
	if (strncmp($prelude, '@', 1) === 0) {
		$name = preg_match('/^@([-A-Za-z0-9_]+)/', $prelude, $m) ? strtolower($m[1]) : '';
		$rest = trim(substr($prelude, strlen($name) + 1));
		if ($name === 'media' || $name === 'supports') {
			if ($rest === '' || !preg_match('#^[A-Za-z0-9 (),:.\-_/\n\t]+$#D', $rest)) {
				skinEditorCSS_skip($tokens, $i);
				return;
			}
			$rest = preg_replace('/\s+/', ' ', $rest);
			$kind = ($name === 'media') ? skinEditorCSS_mediaKind($rest) : 1;
			if ($kind === 2) {	// 화면에는 걸리지 않는 매체(print 등)
				skinEditorCSS_skip($tokens, $i);
				return;
			}
			$wrap[] = '@' . $name . ' ' . $rest;
			skinEditorCSS_parse($ctx, $tokens, $i, $depth + 1, $wrap, $cond || $kind === 1, $dirRel);
			return;
		}
		if ($name === 'font-face') {
			$decls = skinEditorCSS_decls($ctx, $tokens, $i, $dirRel);
			if ($decls === false)
				return;
			$allowed = array('font-family', 'src', 'font-weight', 'font-style', 'font-display', 'unicode-range', 'font-stretch', 'font-variant', 'font-feature-settings',
				'ascent-override', 'descent-override', 'line-gap-override', 'size-adjust');
			$kept = array();
			$has = array();
			foreach ($decls as $decl) {
				if (in_array($decl['name'], $allowed, true)) {
					$kept[] = $decl;
					$has[$decl['name']] = true;
				}
			}
			if (isset($has['src'], $has['font-family']))
				$ctx['items'][] = array('raw' => '@font-face{' . skinEditorCSS_declText($kept) . '}', 'wrap' => $wrap);
			return;
		}
		if (in_array($name, array('keyframes', '-webkit-keyframes', '-moz-keyframes', '-o-keyframes'), true)) {
			if (!preg_match('/^-?[A-Za-z_][A-Za-z0-9_-]*$/D', $rest)) {
				skinEditorCSS_skip($tokens, $i);
				return;
			}
			$frames = skinEditorCSS_frames($ctx, $tokens, $i, $dirRel);
			if ($frames !== '')
				$ctx['items'][] = array('raw' => '@' . $name . ' ' . $rest . '{' . $frames . '}', 'wrap' => $wrap);
			return;
		}
		skinEditorCSS_skip($tokens, $i);	// @page·@layer·@container·@namespace·모르는 at-rule
		return;
	}
	$decls = skinEditorCSS_decls($ctx, $tokens, $i, $dirRel);
	$ctx['rules']++;
	if ($ctx['rules'] > $ctx['lim']['rules'])
		throw new RuntimeException('rules');
	if ($decls === false || empty($decls) || $prelude === '')
		return;
	$sels = skinEditorCSS_selectors($prelude, $ctx['lim']);
	if (!empty($sels))
		$ctx['items'][] = array('sels' => $sels, 'decls' => $decls, 'wrap' => $wrap, 'cond' => $cond);
}

// 선언 블록을 } 까지 읽는다. 안에 { 가 있으면(중첩 규칙) 블록째 버리고 false
function skinEditorCSS_decls(&$ctx, $tokens, &$i, $dirRel) {
	$n = count($tokens);
	$list = array();
	$cur = array();
	while ($i < $n) {
		$token = $tokens[$i];
		$i++;
		if ($token[0] === 'p' && $token[1] === '{') {
			skinEditorCSS_skip($tokens, $i);
			skinEditorCSS_skip($tokens, $i);
			return false;
		}
		if ($token[0] === 'p' && ($token[1] === ';' || $token[1] === '}')) {
			if (!empty($cur)) {
				$decl = skinEditorCSS_decl($cur, $dirRel, $ctx['paths']);
				if ($decl !== null)
					$list[] = $decl;
			}
			$cur = array();
			if ($token[1] === '}')
				return $list;
			continue;
		}
		$cur[] = $token;
	}
	if (!empty($cur)) {
		$decl = skinEditorCSS_decl($cur, $dirRel, $ctx['paths']);
		if ($decl !== null)
			$list[] = $decl;
	}
	return $list;
}

// 선언 하나: array('name', 'value'(url 을 바꾸고 공백을 줄인 값), 'important', 'bare'(문자열을 "" 로 바꾸고 소문자로 한 값)). 버릴 것이면 null
function skinEditorCSS_decl($toks, $dirRel, $paths) {
	$raw = '';
	$bare = '';
	$out = '';
	$paren = 0;
	$count = count($toks);
	for ($k = 0; $k < $count; $k++) {
		$type = $toks[$k][0];
		$text = $toks[$k][1];
		if ($type === 's') {
			$raw .= $text;
			$bare .= '""';
			$out .= $text;
			continue;
		}
		if ($type === 'p') {
			$paren += ($text === '(') ? 1 : -1;
			if ($paren < 0)
				return null;
		} else {
			$text = preg_replace('/\s+/', ' ', $text);
			if ($k + 1 < $count && $toks[$k + 1][0] === 'p' && $toks[$k + 1][1] === '(' && preg_match('/(?:^|[^A-Za-z0-9_-])url$/iD', $text)) {
				if ($k + 3 >= $count || $toks[$k + 2][0] !== 's' || $toks[$k + 3][0] !== 'p' || $toks[$k + 3][1] !== ')')
					return null;
				$url = skinEditorCSS_url(substr($toks[$k + 2][1], 1, -1), $dirRel, $paths);
				if ($url === null)
					return null;
				$raw .= $text . '(' . $toks[$k + 2][1] . ')';
				$bare .= $text . '("")';
				$out .= $text . '("' . $url . '")';
				$k += 3;
				continue;
			}
		}
		$raw .= $text;
		$bare .= $text;
		$out .= $text;
	}
	if ($paren !== 0 || strpos($bare, '\\') !== false || strpos($raw, '[##_') !== false)
		return null;
	if (!preg_match('/^\s*(--[A-Za-z0-9_-]+|-?[A-Za-z][A-Za-z0-9-]*)\s*:/', $bare, $m))
		return null;
	$name = (strncmp($m[1], '--', 2) === 0) ? $m[1] : strtolower($m[1]);
	if (strpos($name, 'binding') !== false || $name === 'behavior')
		return null;
	$value = substr($out, strlen($m[0]));
	$bareValue = substr($bare, strlen($m[0]));
	$important = false;
	if (preg_match('/!\s*important\s*$/iD', $bareValue)) {
		$important = true;
		$value = preg_replace('/!\s*important\s*$/iD', '', $value);
		$bareValue = preg_replace('/!\s*important\s*$/iD', '', $bareValue);
	}
	$value = trim($value);
	$bareValue = strtolower(trim($bareValue));
	if ($value === '' || strpos($bareValue, '!') !== false)
		return null;
	$check = preg_replace('/\s+/', '', $bareValue);
	foreach (array('expression(', 'javascript:', 'vbscript:', 'progid:', '-moz-binding', 'behavior', 'image-set(', 'cross-fade(', 'element(', 'src(', 'image(', '<') as $bad) {
		if (strpos($check, $bad) !== false)
			return null;
	}
	return array('name' => $name, 'value' => $value, 'important' => $important, 'bare' => $bareValue);
}

// url() 안 값: 스킨 폴더 안 상대 경로(이 CSS 파일 폴더 기준, / 로 시작하면 스킨 폴더 주소 경로로 시작할 때만)를 "\x01S\x01" + 조각마다 rawurlencode 한 경로로 바꾼다.
// #조각과 base64 이미지·글꼴 data: 는 그대로, 그 밖(scheme·//·스킨 폴더 밖·역슬래시·제어 문자·잘못된 UTF-8)은 null(선언째 버림)
function skinEditorCSS_url($value, $dirRel, $paths) {
	$value = trim($value);
	if ($value === '' || strpos($value, '\\') !== false || preg_match('/[\x00-\x20\x7F"\'<>]/', $value))
		return null;
	if (preg_match('/^#[A-Za-z0-9_-]+$/D', $value))
		return $value;
	if (preg_match('#^data:(?:image/(?:png|gif|jpe?g|webp|avif|bmp|x-icon|vnd\.microsoft\.icon|svg\+xml)|font/(?:woff2?|ttf|otf|sfnt|collection)|application/(?:font-woff2?|x-font-woff|x-font-ttf|x-font-opentype|font-sfnt|vnd\.ms-fontobject|octet-stream))(?:;charset=[A-Za-z0-9_-]+)?;base64,[A-Za-z0-9+/]*={0,2}$#iD', $value))
		return $value;
	if (preg_match('/^[A-Za-z][A-Za-z0-9+.-]*:/', $value) || strncmp($value, '//', 2) === 0)
		return null;
	$suffix = '';
	$cut = strcspn($value, '?#');
	if ($cut < strlen($value)) {
		$suffix = substr($value, $cut);
		$value = substr($value, 0, $cut);
		if (!preg_match('/^[?#][A-Za-z0-9._~=&%\/?#:+-]*$/D', $suffix))
			return null;
	}
	$path = rawurldecode($value);
	if ($path === '' || strpos($path, '\\') !== false || preg_match('/[\x00-\x1F\x7F]/', $path) || !preg_match('//u', $path))
		return null;
	if (strncmp($path, '/', 1) === 0) {
		$prefix = $paths['dirPath'] . '/' . $paths['name'] . '/';
		if (strncmp($path, $prefix, strlen($prefix)) !== 0)
			return null;
		$path = substr($path, strlen($prefix));
	} else if ($dirRel !== '') {
		$path = $dirRel . '/' . $path;
	}
	$segments = array();
	foreach (explode('/', $path) as $segment) {
		if ($segment === '' || $segment === '.')
			continue;
		if ($segment === '..') {
			if (empty($segments))
				return null;
			array_pop($segments);
			continue;
		}
		$segments[] = $segment;
	}
	if (empty($segments))
		return null;
	return "\x01S\x01" . implode('/', array_map('rawurlencode', $segments)) . $suffix;
}

function skinEditorCSS_declText($decls) {
	$parts = array();
	foreach ($decls as $decl)
		$parts[] = $decl['name'] . ':' . $decl['value'] . ($decl['important'] ? '!important' : '');
	return implode(';', $parts);
}

// @keyframes 안: from·to·N% 프레임만 두고 선언은 같은 거르기를 거친다
function skinEditorCSS_frames(&$ctx, $tokens, &$i, $dirRel) {
	$n = count($tokens);
	$out = '';
	$prelude = '';
	while ($i < $n) {
		$token = $tokens[$i];
		$i++;
		if ($token[0] === 'p' && $token[1] === '}')
			return $out;
		if ($token[0] === 'p' && $token[1] === '{') {
			$select = strtolower(preg_replace('/\s+/', '', $prelude));
			$prelude = '';
			$decls = skinEditorCSS_decls($ctx, $tokens, $i, $dirRel);
			if ($decls === false || empty($decls) || !preg_match('/^(from|to|\d+(\.\d+)?%)(,(from|to|\d+(\.\d+)?%))*$/D', $select))
				continue;
			$out .= $select . '{' . skinEditorCSS_declText($decls) . '}';
			continue;
		}
		if ($token[0] === 'p' && $token[1] === ';') {
			$prelude = '';
			continue;
		}
		$prelude .= $token[1];
	}
	return $out;
}

// 선택자 목록: 맨 위 쉼표로 나눠 하나씩 읽는다. 하나라도 읽지 못하면 빈 목록(규칙째 버림)
function skinEditorCSS_selectors($prelude, $lim) {
	if (strpos($prelude, '\\') !== false || strpos($prelude, '&') !== false || strlen($prelude) > $lim['selector'] * $lim['variants'])
		return array();
	$pieces = array();
	$quote = '';
	$paren = 0;
	$bracket = 0;
	$start = 0;
	$len = strlen($prelude);
	for ($k = 0; $k < $len; $k++) {
		$c = $prelude[$k];
		if ($quote !== '') {
			if ($c === $quote)
				$quote = '';
			continue;
		}
		if ($c === '"' || $c === "'")
			$quote = $c;
		else if ($c === '(')
			$paren++;
		else if ($c === ')')
			$paren--;
		else if ($c === '[')
			$bracket++;
		else if ($c === ']')
			$bracket--;
		else if ($c === ',' && $paren === 0 && $bracket === 0) {
			$pieces[] = substr($prelude, $start, $k - $start);
			$start = $k + 1;
		}
	}
	$pieces[] = substr($prelude, $start);
	$list = array();
	foreach ($pieces as $piece) {
		$sel = skinEditorCSS_selector(trim($piece), $lim);
		if ($sel === null)
			return array();
		$list[] = $sel;
	}
	return $list;
}

// 선택자 하나를 복합 선택자들로 나눈다: c = [tag(소문자, * 는 null), ids, classes, attrs([...] 글자들), pcs([이름, 괄호 값]), pe(가상 요소)], comb = 앞과의 결합자(' ' > + ~, 첫 것은 null)
// 조각 하나를 읽을 때마다 skinEditorCSS_tick 으로 세어 시간·메모리 상한을 본다(규칙이 많으면 읽은 선택자가 $ctx['items'] 에 쌓임)
// PHP port(Textcube-NG): r3-12 v4 사용자 결정 11-09 "토큰 수 상한 추가"(규칙이 많은 시트에서 읽은 선택자가 쌓여 memory_limit 를 넘던 것)
function skinEditorCSS_selector($text, $lim) {
	if ($text === '' || strlen($text) > $lim['selector'])
		return null;
	$re = '~\G(?:(\s*([>+\~])\s*|\s+)|(\*|[A-Za-z_\x80-\xFF-][A-Za-z0-9_\x80-\xFF-]*)|#([A-Za-z0-9_\x80-\xFF-]+)|\.([A-Za-z0-9_\x80-\xFF-]+)|(\[(?:[^\]"\']++|"[^"]*+"|\'[^\']*+\')*+\])|(::?)([A-Za-z_-][A-Za-z0-9_-]*)(\((?:[^()"\']++|"[^"]*+"|\'[^\']*+\'|(?9))*+\))?)~';
	$pos = 0;
	$len = strlen($text);
	$compounds = array();
	$combs = array();
	$cur = null;
	$pending = null;
	while ($pos < $len) {
		skinEditorCSS_tick();
		if (!preg_match($re, $text, $m, 0, $pos) || $m[0] === '')
			return null;
		$pos += strlen($m[0]);
		if (isset($m[1]) && $m[1] !== '') {
			if ($cur === null)
				return null;
			$compounds[] = $cur;
			$cur = null;
			$pending = (isset($m[2]) && $m[2] !== '') ? $m[2] : ' ';
			continue;
		}
		if ($cur === null) {
			$cur = array('text' => '', 'tag' => null, 'ids' => array(), 'classes' => array(), 'attrs' => array(), 'pcs' => array(), 'pe' => false);
			$combs[] = $pending;
			$pending = null;
		}
		if ($cur['pe'])
			return null;
		$first = ($cur['text'] === '');
		$cur['text'] .= $m[0];
		if (isset($m[3]) && $m[3] !== '') {
			if (!$first)
				return null;
			if ($m[3] !== '*')
				$cur['tag'] = strtolower($m[3]);
		} else if (isset($m[4]) && $m[4] !== '') {
			$cur['ids'][] = $m[4];
		} else if (isset($m[5]) && $m[5] !== '') {
			$cur['classes'][] = $m[5];
		} else if (isset($m[6]) && $m[6] !== '') {
			$cur['attrs'][] = $m[6];
		} else {
			$pname = strtolower($m[8]);
			$arg = isset($m[9]) ? $m[9] : '';
			if ($m[7] === '::' || ($arg === '' && in_array($pname, array('before', 'after', 'first-line', 'first-letter'), true)))
				$cur['pe'] = true;
			else
				$cur['pcs'][] = array($pname, $arg);
		}
	}
	if ($cur === null || count($compounds) + 1 > $lim['compounds'])
		return null;
	$compounds[] = $cur;
	return array('c' => $compounds, 'comb' => $combs);
}

function skinEditorCSS_join($sel, $from, $to) {
	$s = '';
	for ($k = $from; $k < $to; $k++) {
		if ($k > $from)
			$s .= ($sel['comb'][$k] === ' ') ? ' ' : ' ' . $sel['comb'][$k] . ' ';
		$s .= $sel['c'][$k]['text'];
	}
	return $s;
}

// 속성 선택자 하나(cmatch 의 attrs 낱개, [ ] 포함)가 감싼 요소 하나에 걸리는지: 0 아님, 1 모름, 2 걸림.
// 요소의 속성 값은 skin.html 에 적힌 글자 그대로라서 실제 값으로 비교한다. class·id 는 글자 크기를 가려 비교하고(i 표시가 있으면 가리지 않음),
// 그 밖의 속성은 HTML 에서 값의 대소문자를 가리지 않는 것이 있어 가린 결과와 가리지 않은 결과가 같을 때만 정하고 다르면 모름으로 둔다.
// 치환자([##_)가 든 값: 규칙 고르기(loose 아님)는 모름. 글자 크기 셈(loose)은 class·id 값에서 치환자를 뺀 실제 값으로 비교하고, 그 밖의 속성은 모름.
// 치환자로 이름이 정해진 속성(anyq)이 있으면 규칙 고르기에서 없는 속성도 모름, 글자 크기 셈에서는 없는 것으로 본다.
// 이름공간(ns|attr)·역슬래시 escape 등 이 형태를 벗어나는 것은 모름
// PHP port(Textcube-NG): r3-12 치환자 처리(사용자 결정 11-08 22:0x "없는 것으로 봄", r3-11 B2 bodyClass 와 같은 방식)
function skinEditorCSS_amatch($a, $el, $loose) {
	if (!preg_match('/^\[\s*([A-Za-z_][A-Za-z0-9_-]*)\s*(?:([~|^$*]?=)\s*(?:"([^"\\\\]*)"|\'([^\'\\\\]*)\'|([A-Za-z0-9_-]+))\s*([iIsS])?\s*)?\]$/D', $a, $m))
		return 1;
	$name = strtolower($m[1]);
	if (!array_key_exists($name, $el['attrs']))
		return ($el['anyq'] && !$loose) ? 1 : 0;
	if (!isset($m[2]) || $m[2] === '')
		return 2;
	$op = $m[2];
	$want = (isset($m[5]) && $m[5] !== '') ? $m[5] : ((isset($m[4]) && $m[4] !== '') ? $m[4] : (isset($m[3]) ? $m[3] : ''));
	$flag = isset($m[6]) ? strtolower($m[6]) : '';
	$have = $el['attrs'][$name];
	$classid = ($name === 'class' || $name === 'id');
	if (strpos($have, '[##_') !== false) {
		if (!$loose || !$classid)
			return 1;
		$have = preg_replace('/\[##_.*?_##\]/s', '', $have);
		if (strpos($have, '[##_') !== false)	// 닫히지 않은 치환자 조각
			return 1;
	}
	if ($classid)
		return skinEditorCSS_avalue($op, $want, $have, $flag === 'i');
	if ($flag === 'i' || $flag === 's')
		return skinEditorCSS_avalue($op, $want, $have, $flag === 'i');
	$cs = skinEditorCSS_avalue($op, $want, $have, false);
	$ci = skinEditorCSS_avalue($op, $want, $have, true);
	return ($cs === $ci) ? $cs : 1;
}

// 속성 값 비교(= ~= |= ^= $= *=): 2 걸림, 0 아님
function skinEditorCSS_avalue($op, $want, $have, $ci) {
	if ($ci) {
		$want = strtolower($want);
		$have = strtolower($have);
	}
	if ($op === '=')
		return ($have === $want) ? 2 : 0;
	if ($op === '~=') {
		if ($want === '' || preg_match('/\s/', $want))
			return 0;
		return in_array($want, preg_split('/\s+/', $have, -1, PREG_SPLIT_NO_EMPTY), true) ? 2 : 0;
	}
	if ($op === '|=')
		return ($have === $want || strncmp($have, $want . '-', strlen($want) + 1) === 0) ? 2 : 0;
	if ($want === '')
		return 0;
	if ($op === '^=')
		return (strncmp($have, $want, strlen($want)) === 0) ? 2 : 0;
	if ($op === '$=')
		return (strlen($have) >= strlen($want) && substr($have, -strlen($want)) === $want) ? 2 : 0;
	return (strpos($have, $want) !== false) ? 2 : 0;	// *=
}

// 복합 선택자 하나가 감싼 요소 하나에 걸리는지: 0 아님, 1 모름, 2 걸림.
// 상태 가상 클래스(:hover 등·접두어 붙은 것)는 아님, :root 는 html 일 때만, 속성 선택자는 skinEditorCSS_amatch(), 구조·:not·:is·:has 등은 모름.
// id·class 가 맞지 않을 때: 그 요소의 id(class) 에 치환자가 있으면 규칙 고르기(loose 아님)는 모름, 글자 크기 셈(loose)은 치환자를 없는 것으로 보아 아님
// PHP port(Textcube-NG): r3-12 치환자 처리(사용자 결정 11-08 22:0x "없는 것으로 봄", r3-11 B2 bodyClass 와 같은 방식)
function skinEditorCSS_cmatch($c, $el, $loose = false) {
	$state = array('hover', 'active', 'focus', 'focus-within', 'focus-visible', 'visited', 'link', 'any-link', 'local-link', 'target', 'target-within', 'checked',
		'disabled', 'enabled', 'indeterminate', 'default', 'required', 'optional', 'valid', 'invalid', 'user-valid', 'user-invalid', 'in-range', 'out-of-range',
		'read-only', 'read-write', 'placeholder-shown', 'autofill', 'fullscreen', 'modal', 'popover-open', 'playing', 'paused', 'picture-in-picture', 'current',
		'past', 'future', 'open', 'closed', 'blank', 'defined');
	$r = 2;
	if ($c['tag'] !== null && $c['tag'] !== $el['tag'])
		return 0;
	foreach ($c['ids'] as $id) {
		if ($el['id'] === $id)
			continue;
		if (!$el['idq'] || $loose)
			return 0;
		$r = 1;
	}
	foreach ($c['classes'] as $class) {
		if (in_array($class, $el['classes'], true))
			continue;
		if (!$el['clq'] || $loose)
			return 0;
		$r = 1;
	}
	foreach ($c['attrs'] as $a) {
		$v = skinEditorCSS_amatch($a, $el, $loose);
		if ($v === 0)
			return 0;
		$r = min($r, $v);
	}
	foreach ($c['pcs'] as $pc) {
		if ($pc[0] === 'root') {
			if ($el['tag'] !== 'html')
				return 0;
			continue;
		}
		if (in_array($pc[0], $state, true) || strncmp($pc[0], '-', 1) === 0)
			return 0;
		$r = 1;
	}
	return $r;
}

// 복합 선택자 0..$k 가, $k 번째가 감싼 요소 $j 에 놓이며 걸리는지(0·1·2). 형제 결합자(+ ~)는 모름. $loose 는 cmatch() 와 같음.
// 부를 때마다(memo 로 바로 돌아가는 것 포함) skinEditorCSS_tick 으로 세어 마감 시각을 넘으면 'time'(리뷰 D1), 메모리 상한을 넘으면 'memory'(v4).
// $memo 는 선택자 하나마다 새로 만들고 열쇠가 복합 선택자(32) × 감싼 요소(66+1) × loose(2) 이하라 따로 개수 상한을 두지 않는다
function skinEditorCSS_xmatch($sel, $k, $j, $chain, &$memo, $loose = false) {
	skinEditorCSS_tick();
	$key = $k . ',' . $j . ($loose ? ',l' : '');
	if (isset($memo[$key]))
		return $memo[$key];
	$r = skinEditorCSS_cmatch($sel['c'][$k], $chain[$j], $loose);
	if ($r !== 0 && $k > 0) {
		$comb = $sel['comb'][$k];
		if ($comb === '>') {
			$p = ($j > 0) ? skinEditorCSS_xmatch($sel, $k - 1, $j - 1, $chain, $memo, $loose) : 0;
		} else if ($comb === ' ') {
			$p = 0;
			for ($a = $j - 1; $a >= 0 && $p < 2; $a--)
				$p = max($p, skinEditorCSS_xmatch($sel, $k - 1, $a, $chain, $memo, $loose));
		} else {
			$p = 1;
		}
		$r = min($r, $p);
	}
	$memo[$key] = $r;
	return $r;
}

// 감싼 요소에 줄 때 빼는 속성(배치·글자 크기, 접두어는 떼고 봄)
function skinEditorCSS_layoutProp($name) {
	$name = preg_replace('/^-(webkit|moz|ms|o)-/', '', $name);
	if (in_array($name, array('position', 'inset', 'top', 'right', 'bottom', 'left', 'float', 'clear', 'display', 'width', 'height', 'min-width', 'max-width',
		'min-height', 'max-height', 'inline-size', 'block-size', 'min-inline-size', 'max-inline-size', 'min-block-size', 'max-block-size', 'z-index', 'box-sizing',
		'columns', 'order', 'gap', 'row-gap', 'column-gap', 'zoom', 'font-size', 'translate', 'rotate', 'scale', 'aspect-ratio', 'overflow', 'overflow-x', 'overflow-y'), true))
		return true;
	return (bool)preg_match('/^(margin|transform|flex|grid|align-|justify-|place-|inset-|column-)/', $name);
}

function skinEditorCSS_rule(&$ctx, $item) {
	$chain = $ctx['outer'];
	$last = count($chain) - 1;
	$max = $ctx['lim']['variants'];
	$content = array();
	$onChain = array();
	foreach ($item['sels'] as $sel) {
		skinEditorCSS_tick();
		$memo = array();
		$n = count($sel['c']);
		$all = skinEditorCSS_join($sel, 0, $n);
		$variants = array();
		$page = false;	// html·body·head·:root 가 든 선택자(본문 안에서는 걸리지 않음)
		foreach ($sel['c'] as $c) {
			if (in_array($c['tag'], array('html', 'body', 'head'), true))
				$page = true;
			foreach ($c['pcs'] as $pc) {
				if ($pc[0] === 'root')
					$page = true;
			}
		}
		if (!$page)
			$variants[] = ':where(.mce-content-body) ' . $all;
		for ($m = 1; $m < $n && count($variants) < $max; $m++) {
			$comb = $sel['comb'][$m];
			if ($comb !== ' ' && $comb !== '>')
				continue;
			$yes = false;
			if ($comb === '>')
				$yes = (skinEditorCSS_xmatch($sel, $m - 1, $last, $chain, $memo) === 2);
			else {
				for ($j = 0; $j <= $last && !$yes; $j++)
					$yes = (skinEditorCSS_xmatch($sel, $m - 1, $j, $chain, $memo) === 2);
			}
			if ($yes)
				$variants[] = ':where(.mce-content-body):is(' . skinEditorCSS_join($sel, 0, $m) . ',*)' . (($comb === '>') ? ' > ' : ' ') . skinEditorCSS_join($sel, $m, $n);
		}
		foreach ($variants as $v) {
			if (!in_array($v, $content, true))
				$content[] = $v;
		}
		if (!$sel['c'][$n - 1]['pe']) {
			if (skinEditorCSS_xmatch($sel, $n - 1, 0, $chain, $memo) === 2)
				$onChain[] = ':where(html):is(' . $all . ',*)';
			for ($j = 1; $j <= $last; $j++) {
				if (skinEditorCSS_xmatch($sel, $n - 1, $j, $chain, $memo) === 2) {
					$onChain[] = ':where(.mce-content-body):is(' . $all . ',*)';
					break;
				}
			}
		}
	}
	$out = '';
	if (!empty($content))
		$out .= implode(',', $content) . '{' . skinEditorCSS_declText($item['decls']) . '}';
	if (!empty($onChain)) {
		$kept = array();
		foreach ($item['decls'] as $decl) {
			if (skinEditorCSS_layoutProp($decl['name']))
				continue;
			if ($decl['name'] === 'font')
				$decl['important'] = false;
			$kept[] = $decl;
		}
		if (!empty($kept))
			$out .= implode(',', array_values(array_unique($onChain))) . '{' . skinEditorCSS_declText($kept) . '}';
	}
	return $out;
}

function skinEditorCSS_emit(&$ctx) {
	$out = '';
	$open = array();
	foreach ($ctx['items'] as $o => $item) {
		if ($o % 200 === 0 && microtime(true) > $ctx['end'])
			throw new RuntimeException('time');
		$text = isset($item['raw']) ? $item['raw'] : skinEditorCSS_rule($ctx, $item);
		if ($text === '')
			continue;
		if ($item['wrap'] !== $open) {
			$out .= str_repeat('}', count($open));
			foreach ($item['wrap'] as $w)
				$out .= $w . '{';
			$open = $item['wrap'];
		}
		$out .= $text . "\n";
		if (strlen($out) > $ctx['lim']['output'])
			throw new RuntimeException('output-size');
	}
	return $out . str_repeat('}', count($open));
}

function skinEditorCSS_spec($sel) {
	$a = 0;
	$b = 0;
	$c = 0;
	foreach ($sel['c'] as $cp) {
		$a += count($cp['ids']);
		$b += count($cp['classes']) + count($cp['attrs']) + count($cp['pcs']);
		if ($cp['tag'] !== null)
			$c++;
		if ($cp['pe'])
			$c++;
	}
	return array($a, $b, $c);
}

// 글자 크기 값 하나(px). em·% 는 부모, rem 은 html 기준. calc·var·vw·ex·ch·smaller·larger 등은 실패
function skinEditorCSS_size($v, $parent, $root) {
	$keywords = array('xx-small' => 9, 'x-small' => 10, 'small' => 13, 'medium' => 16, 'large' => 18, 'x-large' => 24, 'xx-large' => 32, 'xxx-large' => 48);
	if (isset($keywords[$v]))
		return (float)$keywords[$v];
	if ($v === 'inherit' || $v === 'unset')
		return $parent;
	if ($v === 'initial')
		return 16.0;
	if (!preg_match('/^(\d*\.?\d+)(px|pt|pc|in|cm|mm|q|em|rem|%)$/D', $v, $m))
		throw new RuntimeException('font-value');
	$n = (float)$m[1];
	switch ($m[2]) {
		case 'px':
			return $n;
		case 'pt':
			return $n * 4 / 3;
		case 'pc':
			return $n * 16;
		case 'in':
			return $n * 96;
		case 'cm':
			return $n * 96 / 2.54;
		case 'mm':
			return $n * 96 / 25.4;
		case 'q':
			return $n * 96 / 101.6;
		case 'em':
			return $n * $parent;
		case 'rem':
			return $n * $root;
	}
	return $n * $parent / 100;
}

// font 묶음 속성에서 글자 크기 낱말을 찾는다(앞의 style·variant·weight·stretch 낱말은 건너뜀, 크기/줄 높이)
function skinEditorCSS_fontValue($decl, $parent, $root) {
	$v = $decl['bare'];
	if ($decl['name'] === 'font') {
		if ($v === 'inherit' || $v === 'unset')
			return $parent;
		if ($v === 'initial')
			return 16.0;
		if (preg_match('/^(caption|icon|menu|message-box|small-caption|status-bar)$/D', $v))
			throw new RuntimeException('font-system');
		$size = null;
		foreach (preg_split('/\s+/', $v) as $word) {
			$word = preg_replace('#/.*$#s', '', $word);
			if ($word === '' || preg_match('/^(normal|italic|oblique|bold|bolder|lighter|small-caps|[1-9]00|condensed|expanded|semi-condensed|semi-expanded|extra-condensed|extra-expanded|ultra-condensed|ultra-expanded)$/D', $word))
				continue;
			$size = $word;
			break;
		}
		if ($size === null)
			throw new RuntimeException('font-value');
		$v = $size;
	}
	return skinEditorCSS_size($v, $parent, $root);
}

// 블로그 화면에서 감싼 요소들(html → … → 본문 자리)이 받는 글자 크기를 셈해(조건 없는 규칙만, 중요도·세기·순서) html 과 편집기 본문 글자 크기 규칙을 만든다.
// 확실하지 않은 일치·조건부 규칙·style 속성·고정 크기 태그(pre·code·small 등)가 글자 크기에 걸리면 실패(B2).
// 일치는 loose 로 본다: 치환자([##_)가 든 class·id 값은 없는 것으로 보고, 나머지 class·id 와 class·id 속성 선택자는 실제 값으로 비교한다.
// 그래서 치환자 값에 걸린 스킨 글자 크기 규칙은 편집기에 반영되지 않는다(사용자 결정 11-08 22:0x "없는 것으로 봄", r3-11 B2 bodyClass 와 같은 방식)
function skinEditorCSS_font(&$ctx) {
	$chain = $ctx['outer'];
	$factor = array('h1' => 2.0, 'h2' => 1.5, 'h3' => 1.17, 'h4' => 1.0, 'h5' => 0.83, 'h6' => 0.67);
	$fontItems = array();
	foreach ($ctx['items'] as $o => $item) {
		if (!isset($item['sels']))
			continue;
		$fd = array();
		foreach ($item['decls'] as $decl) {
			if ($decl['name'] === 'font-size' || $decl['name'] === 'font')
				$fd[] = $decl;
		}
		if (!empty($fd))
			$fontItems[$o] = $fd;
	}
	$sizes = array();
	foreach ($chain as $j => $el) {
		if (microtime(true) > $ctx['end'])
			throw new RuntimeException('time');
		if ($el['font'])
			throw new RuntimeException('font-inline');
		if (in_array($el['tag'], array('pre', 'code', 'kbd', 'samp', 'tt', 'small', 'big', 'sub', 'sup', 'button', 'input', 'select', 'textarea', 'font'), true))
			throw new RuntimeException('font-tag');
		$best = null;
		foreach ($fontItems as $o => $fd) {
			$item = $ctx['items'][$o];
			foreach ($item['sels'] as $sel) {
				skinEditorCSS_tick();	// 감싼 요소 × 글자 규칙 × 선택자 반복도 세어 2초 상한을 지킨다(리뷰 D1)
				$n = count($sel['c']);
				if ($sel['c'][$n - 1]['pe'])
					continue;
				$memo = array();
				$r = skinEditorCSS_xmatch($sel, $n - 1, $j, $chain, $memo, true);	// 치환자 값은 없는 것으로 봄(사용자 결정 11-08 22:0x)
				if ($r === 0)
					continue;
				if ($r === 1)
					throw new RuntimeException('font-maybe');
				if ($item['cond'])
					throw new RuntimeException('font-media');
				$spec = skinEditorCSS_spec($sel);
				foreach ($fd as $d => $decl) {
					$key = array($decl['important'] ? 1 : 0, $spec[0], $spec[1], $spec[2], $o, $d);
					if ($best === null || $key > $best[0])
						$best = array($key, $decl);
				}
			}
		}
		$parent = ($j === 0) ? 16.0 : $sizes[$j - 1];
		$root = ($j === 0) ? 16.0 : $sizes[0];
		if ($best === null)
			$v = isset($factor[$el['tag']]) ? $parent * $factor[$el['tag']] : $parent;
		else
			$v = skinEditorCSS_fontValue($best[1], $parent, $root);
		if (!is_float($v) || !($v > 0) || $v > 72)
			throw new RuntimeException('font-range');
		$sizes[$j] = $v;
	}
	$format = function ($v) {
		return rtrim(rtrim(sprintf('%.4F', $v), '0'), '.');
	};
	return 'html:root{font-size:' . $format($sizes[0]) . 'px!important}html body.mce-content-body{font-size:' . $format($sizes[count($sizes) - 1]) . 'px!important}' . "\n";
}

function setSkinSetting($blogid, $setting) {
	global $database;
	global $skinSetting;

	requireLibrary('blog.skin');
	$blogid = getBlogId();
	if (strncmp($skinSetting['skin'], 'customize/', 10) == 0) {
		if (strcmp($skinSetting['skin'], "customize/$blogid") != 0)
			return false;
	} else {
		$skinSetting['skin'] = Path::getBaseName($skinSetting['skin']);
		if (($skinSetting['skin'] === '.') || ($skinSetting['skin'] ==='..'))
			return _t('실패 했습니다');
	}

	$skinpath = __TEXTCUBE_SKIN_DIR__.'/' . $skinSetting['skin'];
	if (!is_dir($skinpath))
		return _t('실패 했습니다');

	foreach ($setting as $key => $value) {
		Setting::setSkinSetting($key, $value, $blogid);
	}
	Setting::setSkinSetting('skin', $skinSetting['skin'], $blogid);
	Setting::setBlogSetting('useMicroformat',$setting['useMicroformat'],true);
	Setting::setBlogSetting('useAjaxComment',$setting['useAjaxComment'],true);
	Setting::setBlogSetting('useFOAF',(($setting['useFOAF'] == 1) ? 1: 0),true);
	Setting::setBlogSetting('entriesOnPage',$setting['entriesOnPage'],true);
	Setting::setBlogSetting('entriesOnList',$setting['entriesOnList'],true);
	CacheControl::flushCategory();
	CacheControl::flushTag();
	CacheControl::flushSkin();
	Setting::getSkinSettings($blogid, true); // refresh skin cache
	return true;
}
?>
