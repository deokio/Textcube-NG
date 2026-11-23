<?php
/// Copyright (c) 2004-2016, Needlworks  / Tatter Network Foundation
/// All rights reserved. Licensed under the GPL.
/// See the GNU General Public License for more details. (/documents/LICENSE, /documents/COPYRIGHT)
///
/// ST_MediaLog — 글에 넣은 첨부 가운데 이미지·음성을 모아 보는 미디어로그 (v3.213)
/// 페이지 플러그인(ST_Pages)의 AddPageType 이벤트로 페이지 종류 'medialog' 를 등록하고, 출력은 ViewPageContent 이벤트로 낸다(주소는 페이지 플러그인 화면에서 정한다).
/// ST_Pages 가 꺼져 있으면 두 이벤트가 불리지 않으므로 응답하지 않는다.
/// 공개 글(visibility>=2, draft 0, 분류 0 또는 공개 분류 — 상위 분류도 공개)의 첨부만 낸다. 보호·비공개·예약 글은 넣지 않고, 소유자가 볼 때도 손님 기준으로 낸다.
/// 종류·보기·분류·쪽은 쿼리스트링(type·view·category·page)으로 받아 허용 목록·정수로만 읽는다(사진을 눌러 글을 본 뒤 뒤로 가면 같은 목록으로 돌아온다).
/// 질의는 DBModel 로만 하고 정수는 (int) 로 바꿔 넣는다. 출력은 removeAllTags(library/blog.skin.php)를 거치므로 '#' 을 &#35; 로 낸다(SearchOptimizer 와 같은 방식).
/// Textcube-NG(r3-13): 치환자 [##_medialog_link_##](index.xml <tag>)는 종류가 medialog 인 첫 페이지(pageid 순, 경로 검사를 통과한 첫 행)의 주소로 바뀐다(없으면 빈 값).
/// 스킨에 <s_media> 블록(안에 [##_media_content_##])이 있으면 미디어로그 본문을 그 블록에 넣어 낸다(기본 <style> 은 넣지 않음). 없으면 지금처럼 글 자리에 낸다. body id 는 tt-body-media.

if (!defined('ROOT')) {
	header('HTTP/1.1 403 Forbidden');
	header("Connection: close");
	exit;
}

class ST_MediaLog {
	const KIND = 'medialog';	// ST_Pages 페이지 종류 이름
	const MAX_ENTRIES = 1000;	// 후보 글 상한(최근 공개 글)
	const GAP = 6;	// 칸 사이 간격(px)
	const CELL = 150;	// 열 수 자동 계산의 최소 칸 폭(px)
	const MAX_PAGES = 100;	// Textcube-NG: 치환자 [##_medialog_link_##] 가 볼 medialog 페이지 행 상한(ST_Pages::MAX_PAGES 와 같은 값)(r3-13)
}

/// 확장자 → 종류·설정 키·MIME. 표에 없는 확장자(php 등)는 MIME 이 맞아도 내지 않는다(첨부 주소는 웹 서버가 확장자로 처리한다)
function ST_MediaLog_kinds() {
	return array(
		'jpg' => array('kind' => 'image', 'key' => 'mlExtJpg', 'mime' => array('image/jpeg', 'image/pjpeg')),
		'jpeg' => array('kind' => 'image', 'key' => 'mlExtJpg', 'mime' => array('image/jpeg', 'image/pjpeg')),
		'png' => array('kind' => 'image', 'key' => 'mlExtPng', 'mime' => array('image/png', 'image/x-png')),
		'gif' => array('kind' => 'image', 'key' => 'mlExtGif', 'mime' => array('image/gif')),
		'webp' => array('kind' => 'image', 'key' => 'mlExtWebp', 'mime' => array('image/webp')),
		'avif' => array('kind' => 'image', 'key' => 'mlExtAvif', 'mime' => array('image/avif')),	// Textcube-NG: avif(r3-20)
		'mp3' => array('kind' => 'audio', 'key' => 'mlExtMp3', 'mime' => array('audio/mpeg', 'audio/mp3', 'audio/x-mpeg', 'audio/mpeg3', 'audio/x-mpeg-3')),
		'm4a' => array('kind' => 'audio', 'key' => 'mlExtM4a', 'mime' => array('audio/mp4', 'audio/x-m4a', 'audio/m4a')),
		'ogg' => array('kind' => 'audio', 'key' => 'mlExtOgg', 'mime' => array('audio/ogg', 'application/ogg')),
		'wav' => array('kind' => 'audio', 'key' => 'mlExtWav', 'mime' => array('audio/wav', 'audio/x-wav', 'audio/wave'))
	);
}

function ST_MediaLog_defaults() {
	return array(
		'mlView' => 'post',
		'mlType' => 'all',
		'mlMatch' => 'ext',
		'mlColumns' => 'auto',
		'mlPerPage' => '20',
		'mlThumb' => 'crop',
		'mlExtJpg' => true,
		'mlExtPng' => true,
		'mlExtGif' => true,
		'mlExtWebp' => true,
		'mlExtAvif' => true,
		'mlExtMp3' => true,
		'mlExtM4a' => false,
		'mlExtOgg' => false,
		'mlExtWav' => false,
		'mlUnlinked' => false
	);
}

/// 저장된 설정(문맥 plugin.config)을 허용값으로만 읽는다. 키가 없으면 기본값
function ST_MediaLog_config() {
	$context = Model_Context::getInstance();
	$saved = $context->getProperty('plugin.config');
	if (!is_array($saved)) $saved = array();
	$config = ST_MediaLog_defaults();
	$allowed = array(
		'mlView' => array('post', 'media'),
		'mlType' => array('all', 'image', 'audio'),
		'mlMatch' => array('ext', 'mime', 'both'),
		'mlColumns' => array('auto', '2', '3', '4', '5', '6'),
		'mlPerPage' => array('12', '20', '30', '40', '60'),
		'mlThumb' => array('crop', 'original')
	);
	foreach ($allowed as $key => $values) {
		if (isset($saved[$key]) && is_string($saved[$key]) && in_array($saved[$key], $values, true)) $config[$key] = $saved[$key];
	}
	// checkbox 는 해제하면 '' 로 저장된다(pluginconfig.js). 키가 있으면 빈 문자열이 아닐 때만 켬
	foreach (array('mlExtJpg', 'mlExtPng', 'mlExtGif', 'mlExtWebp', 'mlExtAvif', 'mlExtMp3', 'mlExtM4a', 'mlExtOgg', 'mlExtWav', 'mlUnlinked') as $key) {
		if (array_key_exists($key, $saved)) $config[$key] = (is_string($saved[$key]) && $saved[$key] !== '');
	}
	return $config;
}

/// 출력 escape. ENT_HTML5 로 ' 를 &apos; 로 내어 '#' 이 남지 않게 한 뒤 '#' 을 &#35; 로 바꾼다(SearchOptimizer_h 와 같음)
function ST_MediaLog_h($value) {
	return str_replace('#', '&#35;', htmlspecialchars((string)$value, ENT_QUOTES | ENT_SUBSTITUTE | ENT_HTML5, 'UTF-8'));
}

/// 첨부 이름: 빈 값·'.'·'..'·제어 문자·경로 구분자('/', '\')가 있으면 거부(첨부 폴더 밖 경로 차단)
function ST_MediaLog_safeName($name) {
	if (!is_string($name)) return false;
	return !($name === '' || $name === '.' || $name === '..' || preg_match('/[\x00-\x1F\x7F\/\\\\]/', $name));
}

/// 첨부 행의 종류('image'·'audio', 내지 않으면 '').
/// ext: 켜진 확장자 / mime: 확장자가 표에 있고, 같은 종류의 켜진 확장자 가운데 MIME 이 맞는 것이 있음 / both: 켜진 확장자이고 그 확장자의 MIME 과 맞음
function ST_MediaLog_mediaKind($row, $config) {
	$name = (is_array($row) && isset($row['name']) && is_string($row['name'])) ? $row['name'] : '';
	if (!ST_MediaLog_safeName($name)) return '';
	$dot = strrpos($name, '.');
	if ($dot === false) return '';
	$ext = strtolower(substr($name, $dot + 1));
	$kinds = ST_MediaLog_kinds();
	if (!isset($kinds[$ext])) return '';
	$kind = $kinds[$ext]['kind'];
	$mime = (isset($row['mime']) && is_string($row['mime'])) ? strtolower(trim($row['mime'])) : '';
	$extOn = $config[$kinds[$ext]['key']];
	switch ($config['mlMatch']) {
		case 'mime':
			foreach ($kinds as $item) {
				if ($item['kind'] === $kind && $config[$item['key']] && in_array($mime, $item['mime'], true)) return $kind;
			}
			return '';
		case 'both':
			return ($extOn && in_array($mime, $kinds[$ext]['mime'], true)) ? $kind : '';
	}
	return $extOn ? $kind : '';
}

/// 고를 수 있는 종류: 전체 + 켜진 확장자가 있는 종류
function ST_MediaLog_types($config) {
	$types = array('all');
	foreach (ST_MediaLog_kinds() as $item) {
		if ($config[$item['key']] && !in_array($item['kind'], $types, true)) $types[] = $item['kind'];
	}
	return $types;
}

/// 쿼리스트링 값(문자열만, 배열 등은 ''). 같은 이름의 키가 없으면 경로와 합쳐진 첫 키('<경로>?<이름>')에서 읽는다
function ST_MediaLog_query($name) {
	if (isset($_GET[$name])) return is_string($_GET[$name]) ? $_GET[$name] : '';
	// Textcube-NG: fancyURL 0·1(uri.blog 가 '…/index.php?'·'…/?')이면 첫 쿼리 키가 경로와 합쳐져 '<경로>?type' 이 되므로(interface/common/owner/header.php 의 fancyURL<2 관례), 마지막 '?' 뒤 이름이 허용 키(type·view·category·page)일 때만 그 값을 쓴다. 경로 부분은 PHP 가 '.'·' ' 를 '_' 로 바꾸므로 보지 않고, 같은 이름의 일반 키가 우선하며 값 검증은 state() 에서 그대로 한다(v3.213)
	if (!in_array($name, array('type', 'view', 'category', 'page'), true)) return '';
	foreach ($_GET as $key => $value) {
		if (is_string($key) && ($cut = strrpos($key, '?')) !== false && substr($key, $cut + 1) === $name) return is_string($value) ? $value : '';
	}
	return '';
}

/// 화면 상태. type·view 는 허용 목록, category 는 공개 분류 번호, page 는 1~999999 의 10진 문자열만(그 밖은 기본값)
function ST_MediaLog_state($config, $categories, $types) {
	$type = ST_MediaLog_query('type');
	if (!in_array($type, $types, true)) $type = in_array($config['mlType'], $types, true) ? $config['mlType'] : 'all';
	$view = ST_MediaLog_query('view');
	if (!in_array($view, array('post', 'media'), true)) $view = $config['mlView'];
	$category = ST_MediaLog_query('category');
	$category = (preg_match('/^[1-9][0-9]{0,9}\z/', $category) && isset($categories[(int)$category])) ? (int)$category : 0;
	$page = ST_MediaLog_query('page');
	$page = preg_match('/^[1-9][0-9]{0,5}\z/', $page) ? (int)$page : 1;
	return array('type' => $type, 'view' => $view, 'category' => $category, 'page' => $page);
}

/// 공개 분류(id 키, 상위 → 하위 순). visibility 2 이고 하위 분류는 상위 분류도 공개일 때만. 같은 부모 안에서는 priority 순
function ST_MediaLog_publicCategories($blogid) {
	$rows = getCategories($blogid, 'raw');
	if (!is_array($rows)) return array();
	// Textcube-NG: R-592 — 깊이와 관계없이(자손 전체) 공개 분류를 상위부터 깊이 우선으로. 조상이 모두 공개인 분류만(비공개 분류 아래는 빠짐)
	$byParent = array();
	foreach ($rows as $row) {
		if (!is_array($row) || !isset($row['id'], $row['visibility'])) continue;
		$id = (int)$row['id'];
		if ($id <= 0 || (int)$row['visibility'] < 2) continue;
		$parent = isset($row['parent']) ? (int)$row['parent'] : 0;
		$key = sprintf('%011d.%011d', max(0, isset($row['priority']) ? (int)$row['priority'] : 0), $id);
		$byParent[$parent][$key] = array('id' => $id, 'name' => isset($row['name']) ? (string)$row['name'] : '', 'parent' => $parent);
	}
	$list = array();
	ST_MediaLog_appendCategories($list, $byParent, 0, 1);
	return $list;
}

/// 부모 $parent 아래 공개 분류를 priority 순·깊이 우선으로 $list 에 더한다(순환·64단 상한)
function ST_MediaLog_appendCategories(&$list, &$byParent, $parent, $depth) {
	if ($depth > 64 || !isset($byParent[$parent])) return;
	ksort($byParent[$parent], SORT_STRING);
	foreach ($byParent[$parent] as $item) {
		if (isset($list[$item['id']])) continue;
		$item['depth'] = $depth;
		$list[$item['id']] = $item;
		ST_MediaLog_appendCategories($list, $byParent, $item['id'], $depth + 1);
	}
}

/// 고른 분류와 그 하위 분류. 고르지 않았으면 분류 0 과 모든 공개 분류
function ST_MediaLog_categoryIds($categories, $selected) {
	$ids = array();
	if ($selected > 0) {
		$ids[] = (int)$selected;
		// Textcube-NG: R-592 — 직계 자식뿐 아니라 자손 전체(공개 분류 목록 안에서)
		for ($i = 0; $i < count($ids); $i++) {
			foreach ($categories as $item) {
				if ($item['parent'] === $ids[$i] && !in_array((int)$item['id'], $ids, true)) $ids[] = (int)$item['id'];
			}
		}
		return $ids;
	}
	$ids[] = 0;
	foreach ($categories as $item) $ids[] = (int)$item['id'];
	return $ids;
}

/// 후보 글: 공개(visibility>=2)·draft 0·분류 목록 안. 최근 발행 순으로 MAX_ENTRIES 개까지
function ST_MediaLog_entries($blogid, $categoryIds) {
	if (empty($categoryIds)) return array();
	$pool = DBModel::getInstance();
	$pool->reset('Entries');
	$pool->setQualifier('blogid', 'eq', (int)$blogid);
	$pool->setQualifier('draft', 'eq', 0);
	$pool->setQualifier('visibility', '>=', 2);
	$pool->setQualifier('category', 'hasoneof', $categoryIds);
	$pool->setOrder('published', 'DESC');
	$pool->setLimit(ST_MediaLog::MAX_ENTRIES, 0);
	$rows = $pool->getAll('id,title,slogan,category,published');
	return is_array($rows) ? $rows : array();
}

/// 후보 글의 첨부(첨부한 순)
function ST_MediaLog_attachments($blogid, $entryIds) {
	if (empty($entryIds)) return array();
	$pool = DBModel::getInstance();
	$pool->reset('Attachments');
	$pool->setQualifier('blogid', 'eq', (int)$blogid);
	$pool->setQualifier('parent', 'hasoneof', $entryIds);
	$pool->setOrder('attached', 'ASC');
	$rows = $pool->getAll('parent,name,label,mime,width,height,attached');
	return is_array($rows) ? $rows : array();
}

/// 글 본문(같은 id 의 draft 1 행이 따로 있으므로 draft 0 을 꼭 건다)
function ST_MediaLog_contents($blogid, $entryIds) {
	if (empty($entryIds)) return array();
	$pool = DBModel::getInstance();
	$pool->reset('Entries');
	$pool->setQualifier('blogid', 'eq', (int)$blogid);
	$pool->setQualifier('id', 'hasoneof', $entryIds);
	$pool->setQualifier('draft', 'eq', 0);
	$pool->setQualifier('visibility', '>=', 2);
	$rows = $pool->getAll('id,content');
	return is_array($rows) ? $rows : array();
}

/// 본문에 넣은 첨부 이름: [##_…_##] 치환자의 '|' 칸과 /attach/<blogid>/이름·[##_ATTACH_PATH_##]/이름 (원문·디코드 둘 다)
function ST_MediaLog_linkedNames($content, $blogid) {
	$names = array();
	if (preg_match_all('/\[##_(.+?)_##\]/s', $content, $blocks)) {
		foreach ($blocks[1] as $block) {
			foreach (explode('|', $block) as $part) $names[trim($part)] = true;
		}
	}
	if (preg_match_all('/(?:\/attach\/' . (int)$blogid . '|\[##_ATTACH_PATH_##\])\/([^"\'\s<>?#|\\\\\/]+)/', $content, $paths)) {
		foreach ($paths[1] as $path) {
			$names[$path] = true;
			$names[rawurldecode($path)] = true;
		}
	}
	return $names;
}

/// 칸 수·칸 폭: 스킨 본문 폭(Misc::getContentWidth, skin.contentWidth → 스킨 index.xml → 550)
function ST_MediaLog_grid($config) {
	$width = (int)Misc::getContentWidth();
	if ($width < 200 || $width > 2000) $width = 550;
	if ($config['mlColumns'] === 'auto')
		$columns = (int)floor(($width + ST_MediaLog::GAP) / (ST_MediaLog::CELL + ST_MediaLog::GAP));
	else
		$columns = (int)$config['mlColumns'];
	$columns = max(2, min(8, $columns));
	return array('columns' => $columns, 'cell' => (int)floor(($width - ST_MediaLog::GAP * ($columns - 1)) / $columns));
}

/// 블로그 기준 주소(view.php 의 uri.blog 관례)
function ST_MediaLog_blogBase() {
	return rtrim((string)Model_Context::getInstance()->getProperty('uri.blog', ''), '/');
}

/// 목록 주소: 페이지 경로(단계마다 rawurlencode) + 쿼리스트링(허용값·정수만)
function ST_MediaLog_listUrl($pagePath, $state, $change) {
	$state = array_merge($state, $change);
	$segments = array();
	foreach (explode('/', $pagePath) as $segment) $segments[] = rawurlencode($segment);
	$query = array('type=' . $state['type'], 'view=' . $state['view']);
	if ($state['category'] > 0) $query[] = 'category=' . (int)$state['category'];
	if ($state['page'] > 1) $query[] = 'page=' . (int)$state['page'];
	return ST_MediaLog_blogBase() . '/' . implode('/', $segments) . '?' . implode('&', $query);
}

/// 글 주소(view.php 의 글 주소 관례: useSloganOnPost 이면 entry/<slogan>, 아니면 <id>)
function ST_MediaLog_entryUrl($entry) {
	$context = Model_Context::getInstance();
	$base = ST_MediaLog_blogBase();
	if ($context->getProperty('blog.useSloganOnPost') && isset($entry['slogan']) && (string)$entry['slogan'] !== '')
		return $base . '/entry/' . URL::encode((string)$entry['slogan'], $context->getProperty('service.useEncodedURL'));
	return $base . '/' . (int)$entry['id'];
}

/// 첨부 원본 주소
function ST_MediaLog_fileUrl($blogid, $name) {
	$context = Model_Context::getInstance();
	return rtrim((string)$context->getProperty('service.path', ''), '/') . '/attach/' . (int)$blogid . '/' . rawurlencode($name);
}

/// 썸네일 주소: crop 이면 정사각 썸네일(Utils_Image::getImageResizer, cache/thumbnail/<blogid>/ 에 둠, GD 가 없으면 원본). 만들지 못하면 원본.
/// getImageResizer 는 이름을 주소에 그대로 붙이므로 이름이 영문·숫자·'.'·'_'·'-' 로만 되어 있을 때만 쓴다(첨부 이름은 보통 숫자.확장자)
function ST_MediaLog_thumbUrl($blogid, $name, $cell, $config) {
	if ($config['mlThumb'] === 'crop' && preg_match('/^[A-Za-z0-9._-]+\z/', $name) && class_exists('Utils_Image')) {
		// Textcube-NG: absolute 는 넘기지 않고 기본값(true, uri.service 기준 주소)을 쓴다(library/view/view.php 의 getImageResizer 호출 관례). false 면 uri.path 기준인데 path형 블로그는 uri.path 에 블로그 이름이 붙어 cache·attach 주소가 깨진다(v3.213)
		$result = Utils_Image::getInstance()->getImageResizer($name, array('size' => (int)$cell), (int)$blogid);
		if (is_array($result) && isset($result[0]) && is_string($result[0]) && $result[0] !== '') return $result[0];
	}
	return ST_MediaLog_fileUrl($blogid, $name);
}

/// 글 단위 보기의 대표 미디어: 첫 이미지, 없으면 첫 음성
function ST_MediaLog_cover($items) {
	foreach ($items as $item) {
		if ($item['kind'] === 'image') return $item;
	}
	return $items[0];
}

/// 칸 하나
function ST_MediaLog_cell($blogid, $unit, $grid, $config, $view) {
	$entry = $unit['entry'];
	$media = $unit['media'];
	$name = (string)$media['name'];
	$label = isset($media['label']) ? (string)$media['label'] : '';
	$title = isset($entry['title']) ? (string)$entry['title'] : '';
	$entryUrl = ST_MediaLog_h(ST_MediaLog_entryUrl($entry));
	$cell = (int)$grid['cell'];
	$html = '<li class="ml-cell ml-' . $media['kind'] . '">';
	if ($media['kind'] === 'image') {
		$html .= '<a class="ml-thumb" href="' . $entryUrl . '"><img src="' . ST_MediaLog_h(ST_MediaLog_thumbUrl($blogid, $name, $cell, $config)) . '" alt="' . ST_MediaLog_h($label) . '" width="' . $cell . '" height="' . $cell . '" loading="lazy" style="width:' . $cell . 'px;height:' . $cell . 'px" /></a>';
	} else {
		$html .= '<div class="ml-sound" style="width:' . $cell . 'px"><span class="ml-label">' . ST_MediaLog_h($label) . '</span><audio controls="controls" preload="none" src="' . ST_MediaLog_h(ST_MediaLog_fileUrl($blogid, $name)) . '"></audio></div>';
	}
	$html .= '<a class="ml-title" href="' . $entryUrl . '">' . ST_MediaLog_h($title) . '</a>';
	if ($view === 'post' && $unit['count'] > 1) $html .= ' <span class="ml-count">' . (int)$unit['count'] . '</span>';
	return $html . '</li>';
}

/// 고르는 줄(종류·보기·분류)
function ST_MediaLog_nav($pagePath, $state, $types, $categories) {
	$typeNames = array('all' => _t('전체'), 'image' => _t('이미지'), 'audio' => _t('음성'));
	$items = array();
	foreach ($types as $type) $items[] = array(ST_MediaLog_listUrl($pagePath, $state, array('type' => $type, 'page' => 1)), $typeNames[$type], $state['type'] === $type, '');
	$html = ST_MediaLog_navLine(_t('종류'), $items);
	$items = array();
	foreach (array('post' => _t('글 단위'), 'media' => _t('미디어만')) as $view => $text) $items[] = array(ST_MediaLog_listUrl($pagePath, $state, array('view' => $view, 'page' => 1)), $text, $state['view'] === $view, '');
	$html .= ST_MediaLog_navLine(_t('보기'), $items);
	$items = array(array(ST_MediaLog_listUrl($pagePath, $state, array('category' => 0, 'page' => 1)), _t('전체'), $state['category'] === 0, ''));
	foreach ($categories as $item) $items[] = array(ST_MediaLog_listUrl($pagePath, $state, array('category' => $item['id'], 'page' => 1)), $item['name'], $state['category'] === $item['id'], $item['parent'] > 0 ? ' ml-sub' : '');
	return $html . ST_MediaLog_navLine(_t('분류'), $items);
}

function ST_MediaLog_navLine($caption, $items) {
	$html = '<div class="ml-nav"><span class="ml-caption">' . ST_MediaLog_h($caption) . '</span>';
	foreach ($items as $item) {
		$class = 'ml-item' . $item[3] . ($item[2] ? ' ml-on' : '');
		$html .= ' <a class="' . $class . '" href="' . ST_MediaLog_h($item[0]) . '"' . ($item[2] ? ' aria-current="page"' : '') . '>' . ST_MediaLog_h($item[1]) . '</a>';
	}
	return $html . '</div>';
}

/// 쪽 번호(앞뒤 4쪽까지)
function ST_MediaLog_pager($pagePath, $state, $pages) {
	if ($pages < 2) return '';
	$page = $state['page'];
	$html = '<div class="ml-pager">';
	if ($page > 1) $html .= '<a class="ml-prev" href="' . ST_MediaLog_h(ST_MediaLog_listUrl($pagePath, $state, array('page' => $page - 1))) . '">' . ST_MediaLog_h(_t('이전')) . '</a> ';
	for ($i = max(1, $page - 4); $i <= min($pages, $page + 4); $i++) {
		if ($i === $page) $html .= '<strong class="ml-on" aria-current="page">' . $i . '</strong> ';
		else $html .= '<a href="' . ST_MediaLog_h(ST_MediaLog_listUrl($pagePath, $state, array('page' => $i))) . '">' . $i . '</a> ';
	}
	if ($page < $pages) $html .= '<a class="ml-next" href="' . ST_MediaLog_h(ST_MediaLog_listUrl($pagePath, $state, array('page' => $page + 1))) . '">' . ST_MediaLog_h(_t('다음')) . '</a>';
	return $html . '</div>';
}

/// 기본 모양(<style>). ST_MediaLog_view 의 'body' 앞에만 붙는다(<s_media> 블록에 넣는 'media_body' 에는 넣지 않음)(Textcube-NG: r3-13 에서 ST_MediaLog_body 에서 뗌, 내용은 그대로)
function ST_MediaLog_style() {
	return '<style>.ST_MediaLog .ml-nav{margin:0 0 6px}.ST_MediaLog .ml-caption{font-weight:bold;margin-right:4px}.ST_MediaLog .ml-on{font-weight:bold;text-decoration:none}.ST_MediaLog .ml-sub{font-size:90%}'
		. '.ST_MediaLog .ml-grid{list-style:none;margin:10px 0;padding:0}.ST_MediaLog .ml-cell{margin:0;padding:0;overflow:hidden;font-size:90%}.ST_MediaLog .ml-thumb img{display:block;object-fit:cover;border:0}'
		. '.ST_MediaLog .ml-sound audio{display:block;width:100%}.ST_MediaLog .ml-label{display:block;overflow:hidden;white-space:nowrap;text-overflow:ellipsis}.ST_MediaLog .ml-title{display:block;overflow:hidden;white-space:nowrap;text-overflow:ellipsis}'
		. '.ST_MediaLog .ml-pager{margin:10px 0;text-align:center}.ST_MediaLog .ml-empty{margin:20px 0;text-align:center}</style>';
}

/// 본문 HTML(기본 모양 없음 — ST_MediaLog_style, r3-13)
function ST_MediaLog_body($blogid, $page) {
	$config = ST_MediaLog_config();
	$categories = ST_MediaLog_publicCategories($blogid);
	$types = ST_MediaLog_types($config);
	$state = ST_MediaLog_state($config, $categories, $types);
	$pagePath = isset($page['pagepath']) ? (string)$page['pagepath'] : '';
	$entries = ST_MediaLog_entries($blogid, ST_MediaLog_categoryIds($categories, $state['category']));
	$entryIds = array();
	foreach ($entries as $entry) $entryIds[] = (int)$entry['id'];
	// 글마다 고른 종류의 미디어
	$byEntry = array();
	foreach (ST_MediaLog_attachments($blogid, $entryIds) as $row) {
		$kind = ST_MediaLog_mediaKind($row, $config);
		if ($kind === '' || ($state['type'] !== 'all' && $kind !== $state['type'])) continue;
		$row['kind'] = $kind;
		$byEntry[(int)$row['parent']][] = $row;
	}
	// 본문에 넣지 않은 첨부는 빼고(설정 mlUnlinked 를 켜면 넣음)
	if (!$config['mlUnlinked'] && !empty($byEntry)) {
		$linked = array();
		foreach (ST_MediaLog_contents($blogid, array_keys($byEntry)) as $row) $linked[(int)$row['id']] = ST_MediaLog_linkedNames((string)$row['content'], $blogid);
		foreach ($byEntry as $id => $items) {
			$kept = array();
			foreach ($items as $item) {
				if (isset($linked[$id][(string)$item['name']])) $kept[] = $item;
			}
			if (empty($kept)) unset($byEntry[$id]);
			else $byEntry[$id] = $kept;
		}
	}
	// 칸 목록(글 발행 순): 글 단위는 글 하나에 한 칸, 미디어만은 미디어 하나에 한 칸
	$units = array();
	foreach ($entries as $entry) {
		$id = (int)$entry['id'];
		if (!isset($byEntry[$id])) continue;
		if ($state['view'] === 'post') {
			$units[] = array('entry' => $entry, 'media' => ST_MediaLog_cover($byEntry[$id]), 'count' => count($byEntry[$id]));
		} else {
			foreach ($byEntry[$id] as $item) $units[] = array('entry' => $entry, 'media' => $item, 'count' => 1);
		}
	}
	$perPage = (int)$config['mlPerPage'];
	$pages = max(1, (int)ceil(count($units) / $perPage));
	if ($state['page'] > $pages) $state['page'] = $pages;
	$grid = ST_MediaLog_grid($config);
	$html = '<div class="ST_MediaLog">' . ST_MediaLog_nav($pagePath, $state, $types, $categories);
	if (empty($units)) return $html . '<p class="ml-empty">' . ST_MediaLog_h(_t('표시할 미디어가 없습니다.')) . '</p></div>';
	$html .= '<ul class="ml-grid" style="display:grid;grid-template-columns:repeat(' . (int)$grid['columns'] . ',' . (int)$grid['cell'] . 'px);gap:' . ST_MediaLog::GAP . 'px">';
	foreach (array_slice($units, ($state['page'] - 1) * $perPage, $perPage) as $unit) $html .= ST_MediaLog_cell($blogid, $unit, $grid, $config, $state['view']);
	return $html . '</ul>' . ST_MediaLog_pager($pagePath, $state, $pages) . '</div>';
}

/// AddPageType 리스너: 페이지 종류 'medialog' 를 더한다(ST_Pages_pageTypes 가 이름·표시 이름을 검사한다)
/// 선택 키 'fields' => array('title')(v3.218): 관리 화면에 제목 칸만 보이고 글 번호·내용은 받지 않는다(출력 ST_MediaLog_view 는 제목·경로만 쓴다)
function ST_MediaLog_addType($target = null, $mother = null) {
	if (!is_array($target)) $target = array();
	$target[] = array('id' => ST_MediaLog::KIND, 'title' => _t('미디어로그'), 'fields' => array('title'));	// Textcube-NG: 제목 칸만 쓴다(v3.218)
	return $target;
}

/// ViewPageContent 리스너: 앞 리스너가 답했거나 자기 종류가 아니면 target 을 그대로 돌려준다.
/// 자기 종류이면 array('title' => 페이지 제목 원문(end.php 가 escape), 'body' => 본문 HTML) — freeSlogan.php 가 스킨으로 싸서 낸다
function ST_MediaLog_view($target = null, $mother = null) {
	if ($target !== null) return $target;
	if (!is_array($mother) || !isset($mother['kind'], $mother['page']) || $mother['kind'] !== ST_MediaLog::KIND || !is_array($mother['page'])) return $target;
	$page = $mother['page'];
	$title = (isset($page['title']) && is_string($page['title']) && trim($page['title']) !== '') ? $page['title'] : _t('미디어로그');
	// Textcube-NG: 'body' 는 지금과 같은 바이트(기본 모양 <style> + 본문). 선택 키(interface/freeSlogan.php, r3-13): 'skin' => 'media' 이면 스킨에 <s_media> 블록이 있을 때
	// 'media_body'(기본 모양 없는 본문)를 그 블록에 넣고, 'body_id' 는 미디어로그 페이지의 body id. 이 키들은 ST_Pages_render 가 문자열일 때만 넘긴다(r3-13)
	$html = ST_MediaLog_body(getBlogId(), $page);
	return array('title' => $title, 'body' => ST_MediaLog_style() . $html, 'media_body' => $html, 'skin' => 'media', 'body_id' => 'tt-body-media');
}

/// path 모드(블로그 이름이 주소의 첫 단계)이면 블로그 이름, 아니면 ''. 예약 경로 판정의 설치 기준 후보(ST_Pages_prefix 와 같은 규칙. ST_Pages 파일은 이 화면에서 읽혀 있지 않을 수 있어 따로 둔다)(r3-13)
function ST_MediaLog_prefix() {
	$context = Model_Context::getInstance();
	if ($context->getProperty('service.type') !== 'path') return '';
	$name = $context->getProperty('blog.name');
	return is_string($name) ? $name : '';
}

/// 미디어로그 페이지 경로(ST_Pages 저장 형식: 앞뒤 '/' 없음, 디코드된 글자). 종류가 medialog 인 행을 pageid 순으로 보아 경로 검사
/// (Dispatcher::isFreeSloganPath·isReservedFreeSloganPath, ST_Pages_resolve 와 같은 판정)를 통과한 첫 행. 없거나 페이지 플러그인(ST_Pages)을 쓸 수 없으면 ''.
/// 질의는 DBModel 로만 하고(blogid 는 (int), 종류 문자열은 escape=true) 요청마다 한 번만 한다(r3-13)
function ST_MediaLog_linkPath($blogid) {
	static $memo = array();
	$blogid = (int)$blogid;
	if (isset($memo[$blogid])) return $memo[$blogid];
	$memo[$blogid] = '';
	if ($blogid <= 0 || !function_exists('pluginGuardIsLoadable') || !pluginGuardIsLoadable('ST_Pages')) return '';
	if (!class_exists('Dispatcher', false) || !method_exists('Dispatcher', 'isFreeSloganPath') || !method_exists('Dispatcher', 'isReservedFreeSloganPath')) return '';
	$pool = DBModel::getInstance();
	$pool->reset('ST_Pages');
	$pool->setQualifier('blogid', 'eq', $blogid);
	$pool->setQualifier('pagekind', 'eq', ST_MediaLog::KIND, true);
	$pool->setOrder('pageid', 'ASC');
	$pool->setLimit(ST_MediaLog::MAX_PAGES, 0);
	$rows = $pool->getAll('pageid,pagepath');
	if (!is_array($rows)) return '';
	$prefix = ST_MediaLog_prefix();
	foreach ($rows as $row) {
		if (!is_array($row) || !isset($row['pagepath']) || !is_string($row['pagepath'])) continue;
		$path = $row['pagepath'];
		if ($path === '' || strpos($path, '%') !== false || !Dispatcher::isFreeSloganPath($path) || Dispatcher::isReservedFreeSloganPath($path, $prefix)) continue;
		$memo[$blogid] = $path;
		return $path;
	}
	return '';
}

/// 치환자 [##_medialog_link_##] 처리기(index.xml <tag>, handleTags 가 앞 처리기의 값을 target 으로 넘긴다): 미디어로그 페이지 주소를 덧붙인다. 없으면 target 그대로(빈 값).
/// 주소는 블로그 기준 주소 + 경로 단계마다 rawurlencode(ST_MediaLog_listUrl 과 같음)이고, 출력 직전에 ST_MediaLog_h 로 escape 한다(r3-13)
function ST_MediaLog_linkTag($target = '') {
	$target = is_string($target) ? $target : '';
	$path = ST_MediaLog_linkPath(getBlogId());
	if ($path === '') return $target;
	$segments = array();
	foreach (explode('/', $path) as $segment) $segments[] = rawurlencode($segment);
	return $target . ST_MediaLog_h(ST_MediaLog_blogBase() . '/' . implode('/', $segments));
}
?>
