<?php
/// Copyright (c) 2004-2016, Needlworks  / Tatter Network Foundation
/// All rights reserved. Licensed under the GPL.
/// See the GNU General Public License for more details. (/documents/LICENSE, /documents/COPYRIGHT)
///
/// ST_Pages — 블로그 주소 아래의 정해진 경로에 페이지를 둔다(v3.212).
/// 코어 화면이 없는 경로는 Dispatcher 가 blog 형식·GET/HEAD·경로 사전 검사(예약 경로 포함)를 통과할 때만 interface/freeSlogan.php 로 넘기고,
/// freeSlogan.php 가 AccessFreeSlogan 이벤트로 ST_Pages_access 를 부른다. 경로 형식·예약 경로 판정은 Dispatcher::isFreeSloganPath ·
/// Dispatcher::isReservedFreeSloganPath 를 그대로 쓰며, 저장할 때와 출력할 때(DB 에 직접 넣은 행·옛 데이터 포함) 모두 검사한다.
/// 종류: entry(기존 글, 스킨 포함) · private(비공개 글, 소유자만) · skin(스킨에 맞춘 내용) · standalone(완전히 새 페이지) ·
/// AddPageType 이벤트로 다른 플러그인이 등록한 종류(출력은 ViewPageContent 이벤트로 받는다). 작성·수정·삭제는 블로그 관리자(group.administrators)만 한다.
/// skin·standalone 본문은 관리자가 쓴 HTML 을 글 본문처럼 거르지 않고 그대로 낸다.
/// 출력 때의 조회는 블로그 설정 힌트(등록 페이지 수)가 정확히 0 이면 건너뛴다(ST_Pages_hintSkips).
/// 관리 화면(v3.218): 종류마다 쓰는 칸만 보이고(AddPageType 선택 키 'fields', JS 가 꺼져 있으면 모든 칸) 저장할 때는 그 종류가 쓰지 않는 글 번호·내용을 버린다(저장하지 못해 폼을 다시 보일 때는 넣은 값 그대로).
/// 종류별 설명·칸 도움말을 두고, 스킨에 맞춘 내용에는 HTML 도구줄과 미리보기(sandbox iframe + CSP meta — 스크립트 실행·같은 출처 접근·외부 자원 요청 없음)를 둔다.
/// 수정·삭제는 링크 목록(/owner/network/link)처럼 칸을 나누고 관리 스킨 아이콘을 쓴다(수정 그림이 없는 관리 스킨(mobile)에서는 코어 mobile 링크 목록처럼 둘 다 글자로 낸다).
/// 저장·삭제 결과(Textcube-NG: r3-13, 사용자 결정 11-08 12:19): 본문 위에 끼워 넣던 문구 대신 관리 화면 알림(EAF4.js 의 PM.showMessage 초록·PM.showErrorMessage 빨강,
/// "center","bottom")으로 띄우고, 칸에 딸린 오류는 그 칸 아래에도 붉게 남긴다. 스크립트가 꺼져 있으면 예전처럼 본문 위에 낸다(noscript).

if (!defined('ROOT')) {
	header('HTTP/1.1 403 Forbidden');
	header("Connection: close");
	exit;
}

final class ST_Pages {
	const TABLE = 'ST_Pages';
	const MAX_PATH_BYTES = 255;	// Dispatcher::isFreeSloganPath 와 같은 값(표시용)
	const MAX_DEPTH = 8;	// Dispatcher::isFreeSloganPath 와 같은 값(표시용)
	const MAX_PAGES = 100;
	const MAX_TITLE_BYTES = 255;
	const MAX_CONTENT_BYTES = 65535;
	const HINT_SETTING = 'ST_PagesCount';	// 블로그 설정 힌트 이름. Setting 플러그인 API 가 앞에 plugin_ 을 붙여 저장한다
}

function ST_Pages_escape($value) {
	return htmlspecialchars((string)$value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
}

/// 요청 값(adminMenu 가 선언한 params 만 남김). 문자열이 아니면 ''
function ST_Pages_input($source, $name) {
	return (isset($source[$name]) && is_string($source[$name])) ? $source[$name] : '';
}

/// 경로 검사 기능(Dispatcher 공개 정적 메서드)이 있는지. 없으면 저장·출력 모두 거부한다(fail closed)
function ST_Pages_canCheckPath() {
	return class_exists('Dispatcher', false) && method_exists('Dispatcher', 'isFreeSloganPath') && method_exists('Dispatcher', 'isReservedFreeSloganPath');
}

/// path 모드(블로그 이름이 주소의 첫 단계)이면 블로그 이름, 아니면 ''. 예약 경로 판정의 설치 기준 후보에 쓴다
function ST_Pages_prefix() {
	$context = Model_Context::getInstance();
	if ($context->getProperty('service.type') !== 'path') return '';
	$name = $context->getProperty('blog.name');
	return is_string($name) ? $name : '';
}

/// 저장 형식(앞뒤 '/' 없음, 디코드된 글자)으로 정리하고 검사한 경로. 받을 수 없으면 null 이고 $error 에 이유
function ST_Pages_normalizePath($input, &$error) {
	$error = '';
	$path = is_string($input) ? trim(trim($input), '/') : '';
	if ($path === '') {
		$error = _t('경로를 입력해 주십시오.');
		return null;
	}
	if (!ST_Pages_canCheckPath()) {
		$error = _t('경로 검사 기능이 없어 저장할 수 없습니다.');
		return null;
	}
	// 관리 화면은 디코드된 글자로 받는다('%' 는 받지 않음)
	if (strpos($path, '%') !== false || !Dispatcher::isFreeSloganPath($path)) {
		$error = _f('쓸 수 없는 경로입니다. 글자·숫자·_·~·-·. 로 된 %1단계 이하, %2바이트 이하 경로만 쓸 수 있고, 마지막 단계는 숫자만으로 쓸 수 없습니다. 이미지·묶음·백업·스크립트·설정 파일 확장자로 끝나거나 흔한 탐색 경로(wp-admin, phpmyadmin 등)로 시작하는 경로도 쓸 수 없습니다.', ST_Pages::MAX_DEPTH, ST_Pages::MAX_PATH_BYTES);
		return null;
	}
	if (Dispatcher::isReservedFreeSloganPath($path, ST_Pages_prefix())) {
		$error = _t('예약된 경로입니다. 관리·서비스 경로(/owner, /control, /api 등)와 Textcube 가 쓰는 경로 아래에는 페이지를 만들 수 없습니다.');
		return null;
	}
	return $path;
}

/// 출력할 행: 요청 경로와 정확히 같은 저장 경로의 행 하나. 요청 경로·저장 경로를 출력 직전에 다시 검사하고 예약 경로이면 null
function ST_Pages_resolve($rows, $path, $prefix) {
	if (!is_array($rows) || !is_string($path) || $path === '' || strpos($path, '%') !== false) return null;
	if (!ST_Pages_canCheckPath()) return null;
	$prefix = is_string($prefix) ? $prefix : '';
	if (!Dispatcher::isFreeSloganPath($path) || Dispatcher::isReservedFreeSloganPath($path, $prefix)) return null;
	foreach ($rows as $row) {
		if (!is_array($row) || !isset($row['pagepath']) || !is_string($row['pagepath'])) continue;
		if ($row['pagepath'] !== $path) continue;
		if (strpos($row['pagepath'], '%') !== false || !Dispatcher::isFreeSloganPath($row['pagepath'])
			|| Dispatcher::isReservedFreeSloganPath($row['pagepath'], $prefix)) return null;
		return $row;
	}
	return null;
}

/// 같은 블로그에 대소문자만 다른 경로까지 같은 경로로 본다
function ST_Pages_isDuplicate($rows, $path, $exceptId) {
	if (!is_array($rows)) return false;
	$lower = strtr($path, 'ABCDEFGHIJKLMNOPQRSTUVWXYZ', 'abcdefghijklmnopqrstuvwxyz');
	foreach ($rows as $row) {
		if (!is_array($row) || !isset($row['pagepath']) || !is_string($row['pagepath'])) continue;
		if (isset($row['pageid']) && (int)$row['pageid'] === (int)$exceptId) continue;
		if (strtr($row['pagepath'], 'ABCDEFGHIJKLMNOPQRSTUVWXYZ', 'abcdefghijklmnopqrstuvwxyz') === $lower) return true;
	}
	return false;
}

/// 페이지 종류: 내장 4종 + AddPageType 이벤트로 다른 플러그인이 등록한 종류.
/// AddPageType 리스너는 받은 배열에 array('id' => 종류 이름, 'title' => 표시 이름) 을 더해 돌려준다. 내장 종류와 먼저 등록된 종류는 덮어쓰지 않는다.
/// 선택 키 'fields'(v3.218): 관리 화면에서 그 종류가 쓰는 칸 목록. 'title'·'content'·'target' 만 받고 그 밖의 값은 버린다(ST_Pages_typeFields).
/// 제목은 모든 종류에서 보이고 필수다(목록 이름). 'fields' 가 배열이 아니면 키가 없는 것으로 보고, 키가 없으면 모든 칸을 보인다.
/// 쓰지 않는 칸(글 번호·내용)은 관리 화면에서 숨기고(JS) 저장할 때 비운다(ST_Pages_check — 저장하지 못해 폼을 다시 보일 때는 넣은 값 그대로). 예: array('id' => 'medialog', 'title' => …, 'fields' => array('title'))
/// 등록한 종류의 출력은 ST_Pages_render 가 ViewPageContent 이벤트로 받는다(함수 이름을 받아 부르지 않는다 — 활성 플러그인의 리스너만 코어 fireEvent 가 부른다).
/// ViewPageContent 리스너는 target(앞 리스너의 답, 처음은 null)과 mother = array('kind' => 종류 이름, 'page' => 페이지 행, 'mother' => AccessFreeSlogan 의 mother) 를 받아,
/// target 이 null 이 아니거나(앞 리스너가 이미 답했다) 자기 종류가 아니면 target 을 그대로 돌려주고, 자기 종류이면 ST_Pages_access 와 같은 계약으로 답한다:
/// 정수 글 번호(코어 가시성) / array('title' => …, 'body' => …)(스킨으로 쌈) / 직접 출력한 뒤 exit. 그 밖의 값은 404
function ST_Pages_pageTypes() {
	$types = array(
		'entry' => array('title' => _t('기존 글'), 'fields' => array('title', 'target')),
		'private' => array('title' => _t('비공개 글(소유자만)'), 'fields' => array('title', 'target')),
		'skin' => array('title' => _t('스킨에 맞춘 내용'), 'fields' => array('title', 'content')),
		'standalone' => array('title' => _t('완전히 새 페이지'), 'fields' => array('title', 'content'))
	);
	$added = fireEvent('AddPageType', array());
	if (!is_array($added) || !eventExists('ViewPageContent')) return $types;	// 출력할 리스너가 하나도 없으면 등록한 종류를 받지 않는다(옛 render 함수 존재 검사에 대응)
	foreach ($added as $item) {
		if (!is_array($item) || !isset($item['id'], $item['title'])) continue;
		if (!is_string($item['id']) || !preg_match('/^[a-z][a-z0-9_-]{0,31}$/D', $item['id'])) continue;
		if (isset($types[$item['id']])) continue;
		if (!is_string($item['title']) || $item['title'] === '') continue;
		$types[$item['id']] = array('title' => $item['title'], 'fields' => ST_Pages_typeFields($item));	// Textcube-NG: 선택 키 'fields'(v3.218)
	}
	return $types;
}

/// AddPageType 항목의 선택 키 'fields' 정리(v3.218). 키가 없거나 배열이 아니면 null(모든 칸).
/// 배열이면 제목에 더해 값 가운데 'target'·'content'(같은 문자열만, in_array strict)를 받고 그 밖의 값은 버린다
function ST_Pages_typeFields($item) {
	if (!is_array($item) || !isset($item['fields']) || !is_array($item['fields'])) return null;
	$fields = array('title');
	foreach (array('target', 'content') as $name) {
		if (in_array($name, $item['fields'], true)) $fields[] = $name;
	}
	return $fields;
}

/// 종류가 쓰는 칸 목록(v3.218). 모르는 종류이거나 칸 목록이 없으면 null(모든 칸)
function ST_Pages_kindFields($types, $kind) {
	return (is_string($kind) && isset($types[$kind]['fields']) && is_array($types[$kind]['fields'])) ? $types[$kind]['fields'] : null;
}

/// 종류 설명(v3.218): 그 종류의 쓰임과 제목의 역할. 등록한 종류는 화면을 그 플러그인이 만든다는 설명
function ST_Pages_kindHelp($kind) {
	switch ($kind) {
		case 'entry':
			return _t('기존 글(공지 포함)을 블로그 스킨에 맞춰 보여 줍니다. 글의 공개 설정을 그대로 따릅니다. 제목은 이 목록에서 쓰는 관리용 이름이고, 화면에는 글 제목이 나옵니다.');
		case 'private':
			return _t('비공개 글을 블로그 소유자에게만 보여 주고, 다른 방문자에게는 없는 페이지(404)로 답합니다. 제목은 관리용 이름이고, 화면에는 글 제목이 나옵니다.');
		case 'skin':
			return _t('입력한 HTML 을 블로그 스킨의 본문 자리에 넣어 보여 줍니다. 제목은 페이지 제목으로 쓰입니다.');
		case 'standalone':
			return _t('입력한 HTML 문서(<html> 부터 끝까지)를 스킨 없이 그대로 내보냅니다. 제목은 이 목록에서 쓰는 이름이고 화면에는 나오지 않습니다.');
	}
	return _t('다른 플러그인이 등록한 종류입니다. 화면은 그 플러그인이 만들므로 이 종류가 쓰는 칸만 입력합니다. 제목을 화면에 어떻게 쓰는지는 그 플러그인을 따릅니다.');
}

/// 수정 아이콘 주소(v3.218). 관리 화면 머리(interface/common/owner/header.php)가 싣는 관리 스킨($adminSkinSetting['skin'], library/preprocessor.php)에
/// image/icon_modify.gif 가 있고 주소가 경로 글자로만 되어 있을 때만 돌려준다. 그렇지 않으면 ''(수정·삭제 모두 글자로 낸다 — mobile 관리 스킨에는 이 그림이 없고, 코어 mobile 링크 목록도 둘 다 글자다)
function ST_Pages_editIcon() {
	global $service, $adminSkinSetting;
	if (!isset($adminSkinSetting['skin']) || !is_string($adminSkinSetting['skin'])) return '';
	$skin = $adminSkinSetting['skin'];
	if (!preg_match('#^/skin/admin/[A-Za-z0-9_-]+$#D', $skin)) return '';
	$url = ((isset($service['path']) && is_string($service['path'])) ? $service['path'] : '') . $skin . '/image/icon_modify.gif';
	if (!preg_match('#^[A-Za-z0-9_~./-]*$#D', $url) || !file_exists(ROOT . $skin . '/image/icon_modify.gif')) return '';
	return $url;
}

/// 저장소(전용 테이블 ST_Pages, index.xml <storage>). 사용자 문자열은 escape=true, 정수는 (int) 로 바꿔 따옴표 없이 넣는다(코어 blogid 와 같은 꼴)
function ST_Pages_store($op, $blogid, $payload = array()) {
	$pool = DBModel::getInstance();
	$pool->reset(ST_Pages::TABLE);
	$pool->setQualifier('blogid', 'eq', (int)$blogid);
	switch ($op) {
		case 'load':	// 출력: 경로가 같은 행. 대소문자·끝 공백 비교는 DBMS 마다 다르므로 ST_Pages_resolve 가 PHP 에서 다시 정확히 비교한다
			$pool->setQualifier('pagepath', 'eq', (string)$payload['path'], true);
			$rows = $pool->getAll('pageid,pagepath,pagekind,targetid,title,content');
			return is_array($rows) ? $rows : array();
		case 'list':
			$pool->setOrder('pagepath', 'ASC');
			$rows = $pool->getAll('pageid,pagepath,pagekind,targetid,title');
			return is_array($rows) ? $rows : array();
		case 'get':
			$pool->setQualifier('pageid', 'eq', (int)$payload['pageid']);
			$row = $pool->getRow('pageid,pagepath,pagekind,targetid,title,content');
			return is_array($row) ? $row : null;
		case 'nextId':
			return (int)$pool->getCell('MAX(pageid)') + 1;
		case 'count':	// 블로그 설정 힌트용 행 수. 셀 수 없으면(질의 실패) null
			$count = $pool->getCount();
			return is_numeric($count) ? (int)$count : null;
		case 'insert':
		case 'update':
			$now = Timestamp::getUNIXtime();
			if ($op === 'update') {
				$pool->setQualifier('pageid', 'eq', (int)$payload['pageid']);
			} else {
				$pool->setAttribute('pageid', (int)$payload['pageid']);
				$pool->setAttribute('created', (int)$now);
			}
			$pool->setAttribute('pagepath', (string)$payload['path'], true);
			$pool->setAttribute('pagekind', (string)$payload['kind'], true);
			$pool->setAttribute('targetid', (int)$payload['target']);
			$pool->setAttribute('title', (string)$payload['title'], true);
			$pool->setAttribute('content', (string)$payload['content'], true);
			$pool->setAttribute('modified', (int)$now);
			return ($op === 'update') ? $pool->update() : $pool->insert();
		case 'delete':
			$pool->setQualifier('pageid', 'eq', (int)$payload['pageid']);
			return $pool->delete();
	}
	return null;
}

/// 블로그 설정 힌트: 등록 페이지 수를 블로그 설정(Setting 플러그인 API, plugin_ST_PagesCount)에 둔다. 정확히 0 일 때만 출력 때 조회를 건너뛰고,
/// 힌트가 없거나 그 밖의 값(음수·문자열·큰 수 등)이면 조회한다. 저장·삭제 뒤와 관리 화면을 열 때 맞춘다
function ST_Pages_hintSkips() {
	$hint = Setting::getBlogSetting(ST_Pages::HINT_SETTING, null);
	return $hint === 0 || $hint === '0';
}

function ST_Pages_updateHint($blogid) {
	$count = ST_Pages_store('count', $blogid);
	if ($count === null) return Setting::removeBlogSetting(ST_Pages::HINT_SETTING);	// 셀 수 없으면 힌트를 지워 조회로 돌아간다
	return Setting::setBlogSetting(ST_Pages::HINT_SETTING, (string)$count);
}

function ST_Pages_dropHint() {
	return Setting::removeBlogSetting(ST_Pages::HINT_SETTING);
}

/// AccessFreeSlogan 리스너. 요청 경로($mother['path'], 블로그 기준·디코드된 값)에 맞는 페이지가 있으면 종류에 따라 답하고, 없거나 예약 경로이면 받은 값을 그대로 돌려준다(→ 404)
function ST_Pages_access($target, $mother) {
	if (!is_string($target)) return $target;	// 앞선 리스너가 이미 답했다
	$path = (is_array($mother) && isset($mother['path']) && is_string($mother['path'])) ? $mother['path'] : '';
	if ($path === '' || strpos($path, '%') !== false || !ST_Pages_canCheckPath()) return $target;
	$prefix = ST_Pages_prefix();
	if (!Dispatcher::isFreeSloganPath($path) || Dispatcher::isReservedFreeSloganPath($path, $prefix)) return $target;
	if (ST_Pages_hintSkips()) return $target;	// 등록 페이지가 없다(힌트 0) — 조회하지 않는다
	$page = ST_Pages_resolve(ST_Pages_store('load', getBlogId(), array('path' => $path)), $path, $prefix);
	if ($page === null) return $target;
	return ST_Pages_render($page, $mother, $target);
}

function ST_Pages_render($page, $mother, $target) {
	$kind = isset($page['pagekind']) ? (string)$page['pagekind'] : '';
	$targetid = isset($page['targetid']) ? (int)$page['targetid'] : 0;
	$title = isset($page['title']) ? (string)$page['title'] : '';
	$content = isset($page['content']) ? (string)$page['content'] : '';
	switch ($kind) {
		case 'entry':	// 기존 글: 글 번호를 돌려주면 freeSlogan.php 가 코어 가시성(getEntryWithPaging)으로 스킨에 싸서 낸다
			return Validator::id($targetid) ? $targetid : $target;
		case 'private':	// 비공개 글: 소유자(doesHaveOwnership)에게만 글 번호를 준다. 손님은 404
			return (Validator::id($targetid) && doesHaveOwnership()) ? $targetid : $target;
		case 'skin':	// ⓐ 스킨에 맞춘 내용: freeSlogan.php 가 begin/end 로 싸서 본문 자리에 낸다
			return array('title' => $title, 'body' => $content);
		case 'standalone':	// ⓑ 완전히 새 페이지: 저장한 그대로 직접 낸 뒤 끝낸다
			header('Content-Type: text/html; charset=utf-8');
			header('X-Content-Type-Options: nosniff');
			echo $content;
			exit;
	}
	$types = ST_Pages_pageTypes();
	if (!isset($types[$kind])) return $target;	// 등록되지 않은 종류(옛 행·끈 플러그인의 종류): 404
	// 등록한 종류: ViewPageContent 리스너의 답(처음 target 은 null — 답한 리스너가 없으면 null 이라 404)
	$result = fireEvent('ViewPageContent', null, array('kind' => $kind, 'page' => $page, 'mother' => $mother));
	if (Validator::id($result)) return (int)$result;
	if (is_array($result) && isset($result['title'], $result['body']) && is_string($result['title']) && is_string($result['body'])) {
		$answer = array('title' => $result['title'], 'body' => $result['body']);
		// Textcube-NG: 선택 키 'body_id'·'skin'·'media_body' 는 문자열일 때만 넘긴다(사용자 결정 11-10). 쓰임과 형식 검사는 interface/freeSlogan.php(r3-13)
		foreach (array('body_id', 'skin', 'media_body') as $key) {
			if (isset($result[$key]) && is_string($result[$key])) $answer[$key] = $result[$key];
		}
		return $answer;
	}
	return $target;
}

/// Textcube-NG: r3-13 저장 검사 오류 하나 — 칸 이름과 문구(사용자 결정 11-08 12:19). 칸 이름은 'path'·'kind'·'target'·'title'·'content' 이고,
/// 칸에 딸리지 않은 오류(없는 페이지·개수 제한·저장 실패·알 수 없는 요청)는 ''. 문구는 고정 문구(_t·_f)만 쓰고, 표시할 때(알림·칸 아래·noscript) escape 한다
function ST_Pages_error($field, $message) {
	return array('field' => (string)$field, 'message' => (string)$message);
}

/// Textcube-NG: r3-13 그 칸의 오류 문구를 칸 아래에 붉게 낸다(escape). 알림(PM)에 띄우는 문구와 같다
function ST_Pages_fieldErrors($errors, $field) {
	if (!is_array($errors)) return;
	foreach ($errors as $error) {
		if (is_array($error) && isset($error['field'], $error['message']) && $error['field'] === $field)
			echo '<p class="ng-pages-error">' . ST_Pages_escape($error['message']) . '</p>' . "\n";
	}
}

/// 저장 전 검사(하나라도 틀리면 저장하지 않음). 반환: 오류 목록 — 항목마다 ST_Pages_error 의 array('field' => 칸 이름, 'message' => 문구)(Textcube-NG: r3-13. 표시 전 escape)
function ST_Pages_check(&$form, $types, $blogid, $exceptId) {
	$errors = array();
	$error = '';
	$path = ST_Pages_normalizePath($form['path'], $error);
	if ($path === null) {
		$errors[] = ST_Pages_error('path', $error);
	} else {
		$form['path'] = $path;
		if (ST_Pages_isDuplicate(ST_Pages_store('list', $blogid), $path, $exceptId))
			$errors[] = ST_Pages_error('path', _t('같은 경로의 페이지가 이미 있습니다.'));
	}
	// Textcube-NG: 그 종류가 쓰지 않는 칸(내장 종류의 칸 목록·AddPageType 'fields')은 검사·저장 전에 비운다 — 관리 화면이 숨긴 칸, JS 가 꺼져 있을 때나 직접 보낸 값.
	// 저장하지 못해 폼을 다시 보일 때는 ST_Pages_manage 가 넣은 값으로 되돌린다(v3.218)
	$fields = isset($types[$form['kind']]) ? ST_Pages_kindFields($types, $form['kind']) : null;
	if ($fields !== null && !in_array('target', $fields, true)) $form['target'] = '';
	if ($fields !== null && !in_array('content', $fields, true)) $form['content'] = '';
	if (!isset($types[$form['kind']])) {
		$errors[] = ST_Pages_error('kind', _t('알 수 없는 페이지 종류입니다.'));
	} else if ($form['kind'] === 'entry' || $form['kind'] === 'private') {
		if (!Validator::id($form['target']) || !(Notice::doesExist((int)$form['target']) || (new Post())->doesExist((int)$form['target'])))
			$errors[] = ST_Pages_error('target', _t('연결할 글 번호가 없거나 올바르지 않습니다.'));
		$form['content'] = '';
	} else if ($form['target'] !== '' && !Validator::id($form['target'])) {
		$errors[] = ST_Pages_error('target', _t('글 번호가 올바르지 않습니다.'));
	}
	if (trim($form['title']) === '' || strlen($form['title']) > ST_Pages::MAX_TITLE_BYTES || preg_match('/[\x00-\x1f\x7f]/', $form['title']))
		$errors[] = ST_Pages_error('title', _f('제목은 제어 문자 없이 %1바이트 이하로 입력해 주십시오.', ST_Pages::MAX_TITLE_BYTES));
	if (strlen($form['content']) > ST_Pages::MAX_CONTENT_BYTES)
		$errors[] = ST_Pages_error('content', _f('내용은 %1바이트 이하여야 합니다.', ST_Pages::MAX_CONTENT_BYTES));
	else if (($form['kind'] === 'skin' || $form['kind'] === 'standalone') && trim($form['content']) === '')
		$errors[] = ST_Pages_error('content', _t('내용을 입력해 주십시오.'));
	return $errors;
}

// Textcube-NG: 글 번호 찾기(v3.220). 이 블로그의 글·공지를 제목 일부나 글 번호로 최대 10개 찾는다(임시 저장 글 제외).
// 범위는 저장 검사(Post::doesExist: category >= 0 AND draft = 0, Notice::doesExist: category = -2 AND draft = 0)와 같다.
// 값(블로그 번호·LIKE 패턴)은 준비 문장으로 SQL 과 따로 보낸다(v3.211 POD::prepare·bindAndExecute 관례). LIKE 의 % _ 와 escape 문자 ! 는 ! 로 escape 한다.
// 글 번호는 숫자이고 Validator::id 를 통과할 때만 정수로 바꿔 문장에 넣는다
function ST_Pages_findEntries($blogid, $query) {
	global $database;
	$where = "title LIKE ? ESCAPE '!'";
	$order = 'published DESC';
	if (preg_match('/^[0-9]{1,10}$/D', $query) && Validator::id($query)) {
		$id = (int)$query;
		$where = '(' . $where . ' OR id = ' . $id . ')';
		$order = 'CASE WHEN id = ' . $id . ' THEN 0 ELSE 1 END, published DESC';	// 번호가 같은 글을 맨 앞에
	}
	$stmt = POD::prepare('SELECT id, title, visibility, category FROM ' . $database['prefix'] . 'Entries'
		. ' WHERE blogid = ? AND draft = 0 AND (category >= 0 OR category = -2) AND ' . $where
		. ' ORDER BY ' . $order . ' LIMIT 10');
	if (!$stmt) return array();
	$pattern = '%' . strtr($query, array('!' => '!!', '%' => '!%', '_' => '!_')) . '%';
	$rows = POD::bindAndExecute($stmt, 'is', (int)$blogid, $pattern) ? POD::fetchAllStmt($stmt) : null;
	$stmt->close();
	if (!is_array($rows)) return array();
	$names = array(0 => 'private', 1 => 'protected', 2 => 'public', 3 => 'published');	// visibility. 0 보다 작으면 예약
	$items = array();
	foreach ($rows as $row) {
		if (!is_array($row) || !isset($row['id'])) continue;
		$visibility = isset($row['visibility']) ? (int)$row['visibility'] : 0;
		$items[] = array(
			'id' => (int)$row['id'],
			'title' => isset($row['title']) ? (string)$row['title'] : '',
			'status' => $visibility < 0 ? 'reserved' : (isset($names[$visibility]) ? $names[$visibility] : ''),
			'notice' => (isset($row['category']) && (int)$row['category'] === -2)
		);
	}
	return $items;
}

// Textcube-NG: 글 번호 찾기 adminHandler(v3.220, index.xml <adminMenu><methods>). adminHandler 가 경로·CSRF(X-CSRF-Token 헤더)를 검사한 뒤 부른다.
// POST 만 받고, ST_Pages_manage 와 같이 블로그 관리자만 쓴다. 답은 JSON(관리 화면과 같은 json_encode 플래그)
function ST_Pages_searchEntries() {
	requirePostMethod();
	header('Content-Type: application/json; charset=utf-8');
	header('X-Content-Type-Options: nosniff');
	header('Cache-Control: no-store');
	if (!Acl::check('group.administrators')) {
		header('HTTP/1.1 403 Forbidden');
		echo '{"items":[]}';
		return;
	}
	$jsonFlags = JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_UNESCAPED_UNICODE | JSON_INVALID_UTF8_SUBSTITUTE;
	$query = trim(ST_Pages_input($_REQUEST, 'q'));	// adminHandler 는 검사한 값을 $_REQUEST 에만 남긴다
	$items = array();
	if ($query !== '' && strlen($query) <= ST_Pages::MAX_TITLE_BYTES && !preg_match('/[\x00-\x1f\x7f]/', $query))
		$items = ST_Pages_findEntries(getBlogId(), $query);
	$json = jsonEncodeCompat(array('items' => $items), $jsonFlags);	// Textcube-NG: JSON_INVALID_UTF8_SUBSTITUTE(7.2) 대신 jsonEncodeCompat()(00-Compat.php, r4-4)
	echo is_string($json) ? $json : '{"items":[]}';
}

// Textcube-NG: r3-11 편집 구역 스타일 설정 skinstyle(index.xml 설정 화면, 사용자 결정 11-08 16:4x). 저장된 값(문맥 plugin.config)을 none·local·cdn 셋으로만 읽고,
// 없거나 목록 밖이면 none(기본값, 편집기 기본 스타일 = v3.220 동작 그대로)이다(ST_MediaLog_config 와 같은 방식)
function ST_Pages_skinStyle() {
	$saved = Model_Context::getInstance()->getProperty('plugin.config');
	if (is_array($saved) && isset($saved['skinstyle']) && is_string($saved['skinstyle']) && in_array($saved['skinstyle'], array('none', 'local', 'cdn'), true))
		return $saved['skinstyle'];
	return 'none';
}

// Textcube-NG: 스킨에 맞춘 내용의 편집기 설정(v3.220). ED_tinyMCE 에 들어 있는 TinyMCE 7.9.3(+bd1ab5e 백포트, R-173) 파일을 빌려 쓴다(첨부·TTML·자동 저장 없음).
// 스크립트 주소·언어·content_css 는 ED_tinyMCE/index.php 와 같은 규칙이다. 파일이 없으면 null(기존 HTML 도구줄만 쓴다)
function ST_Pages_editorConfig() {
	if (!file_exists(ROOT . '/plugins/ED_tinyMCE/tinymce/tinymce.min.js')) return null;
	$context = Model_Context::getInstance();
	$base = $context->getProperty('service.path') . '/plugins/ED_tinyMCE/tinymce';
	$service = $context->getProperty('uri.service');
	$config = array(
		'selector' => '#pages-content',
		'theme' => 'silver',	// TinyMCE 7(R-173): 테마 silver·스킨 oxide 는 6.x 이후 기본값
		'skin' => 'oxide',
		'license_key' => 'gpl',	// TinyMCE 7 자체 호스팅 GPLv2+ 사용 표시(ED_tinyMCE 와 같음)
		'cache_suffix' => '?v=7.9.3-bd1ab5e',
		'menubar' => false,
		'branding' => false,
		'promotion' => false,	// 6.x 이후 "Upgrade" 버튼 끔
		'relative_urls' => false,
		'convert_urls' => false,
		'add_form_submit_trigger' => false,	// 저장은 textarea 그대로. 편집기에서 고쳤을 때만 submit 직전에 옮긴다(JS)
		'submit_patch' => false,
		'add_unload_trigger' => false,
		'plugins' => 'advlist anchor autolink charmap image link lists media table visualblocks',	// hr·paste·textcolor 는 6.0 에서 core 로 들어감(R-173)
		// Textcube-NG: r3-10 R-284 툴바 배치를 글쓰기 편집기(ED_tinyMCE)와 같은 방식으로 함(사용자 결정 11-08): 1줄 = 글자 모양·실행취소·삽입, 2줄 = 문단 모양·색·정렬·목록. 버튼은 같음
		'toolbar1' => 'bold italic underline strikethrough | undo redo | image media charmap | hr link unlink anchor | table | removeformat visualblocks',
		'toolbar2' => 'blocks | forecolor backcolor | alignleft aligncenter alignright alignjustify | bullist numlist outdent indent blockquote',
		'height' => 360,
		'width' => '100%',
		'content_css' => $service . '/resources/style/default-wysiwyg.css'
	);
	// 언어 파일 이름은 ED_tinyMCE/index.php 와 같은 고정 목록으로 바꾼다(TinyMCE 7 은 ko_KR 등, R-173). 목록 밖 값이면 언어를 지정하지 않는다
	$languages = array('ko' => 'ko_KR', 'ja' => 'ja', 'vi' => 'vi', 'zh-CN' => 'zh_CN', 'zh-TW' => 'zh_TW');
	$language = $context->getProperty('blog.language');
	if (is_string($language) && isset($languages[$language]) && file_exists(ROOT . '/plugins/ED_tinyMCE/tinymce/langs/' . $languages[$language] . '.js'))
		$config['language'] = $languages[$language];
	$skin = $context->getProperty('skin.skin');
	if (defined('__TEXTCUBE_SKIN_DIR__') && is_string($skin) && preg_match('#^[A-Za-z0-9_./-]+$#D', $skin) && strpos($skin, '..') === false
		&& file_exists(__TEXTCUBE_SKIN_DIR__ . '/' . $skin . '/wysiwyg.css'))
		$config['content_css'] = $service . '/skin/blog/' . $skin . '/wysiwyg.css';
	// Textcube-NG: r3-11 skinstyle 이 local·cdn 이면 편집기 안에 블로그 화면용 CSS 를 싣는다(사용자 결정 11-08 B1~B3·16:4x). ED_tinyMCE/index.php 와 같은 공용 함수
	// getSkinEditorStyles()(library/model/blog.skin.php)를 쓴다: content_css = 블로그 화면용 CSS → 되돌림 CSS → wysiwyg.css, body_class = 본문 자리 class,
	// cdn(cdn 일 때만 키를 둠) = integrity 있는 https CDN(JS 가 편집기 iframe 에 직접 넣음). none 이면 위 v3.220 규칙 그대로 돌려준다.
	// 함수가 없으면 wysiwyg.css 하나이되, 스킨 이름은 공용 함수와 같이 Skin 생성자 방식(customize/ 는 현재 블로그, 그 밖은 마지막 이름만,
	// ''·'.'·'..' 은 버림)으로 정리하고 주소 조각은 rawurlencode 한다(사용자 결정 11-08 (f))
	$skinStyle = ST_Pages_skinStyle();
	if ($skinStyle !== 'none') {
		$fit = null;	// Textcube-NG: r3-11 B2 글자 크기 맞춤 JS 에 줄 값(아래)
		$gen = null;	// PHP port(Textcube-NG): r3-12 편집기 전용 CSS 생성기 결과(아래, JS 가 <style> 로 넣음)
		$skinStyles = function_exists('getSkinEditorStyles') ? getSkinEditorStyles($context->getProperty('skin.skin'), $service, $skinStyle) : null;
		if (is_array($skinStyles)) {
			$config['content_css'] = $skinStyles['css'];
			if (is_string($skinStyles['bodyClass']) && preg_match('/^[A-Za-z0-9_\- ]+$/D', $skinStyles['bodyClass']))
				$config['body_class'] = $skinStyles['bodyClass'];
			// Textcube-NG: r3-11 B2 글자 크기 맞춤 JS 에 줄 값: 본문 자리를 감싼 요소들(bodyChain)과 잴 때 잠시 끌 편집기 전용 CSS(editorOnly). ED_tinyMCE/index.php 와 같다(사용자 결정 11-08 18:4x)
			if (!empty($skinStyles['bodyChain']) && !empty($skinStyles['editorOnly']))
				$fit = array('chain' => $skinStyles['bodyChain'], 'off' => $skinStyles['editorOnly']);
			// PHP port(Textcube-NG): r3-12 편집기 전용 CSS 생성기 결과가 있으면 content_css 는 로컬 CSS 를 뺀 목록, 만든 CSS 는 JS 가 <style> 로 넣고(gencss),
			// body_class·r3-11 B2 글자 크기 맞춤은 쓰지 않는다. 없으면 B2 그대로. ED_tinyMCE/index.php 와 같다(사용자 결정 11-08 "생성기 = r3-12")
			$genCSS = function_exists('getSkinEditorGeneratedCSS') ? getSkinEditorGeneratedCSS(getBlogId(), $context->getProperty('skin.skin'), $service, $skinStyles) : null;
			if (is_array($genCSS)) {
				$config['content_css'] = $genCSS['css'];
				unset($config['body_class']);
				$fit = null;
				$gen = array('style' => $genCSS['style'], 'before' => $genCSS['before']);
			}
		} else {
			$config['content_css'] = $service . '/resources/style/default-wysiwyg.css';
			$skin = $context->getProperty('skin.skin');
			$skin = is_string($skin) ? ((strncmp($skin, 'customize/', 10) == 0) ? 'customize/' . getBlogId() : Path::getBaseName($skin)) : '';
			if (defined('__TEXTCUBE_SKIN_DIR__') && $skin !== '' && $skin !== '.' && $skin !== '..'
				&& file_exists(__TEXTCUBE_SKIN_DIR__ . '/' . $skin . '/wysiwyg.css'))
				$config['content_css'] = $service . '/skin/blog/' . implode('/', array_map('rawurlencode', explode('/', $skin))) . '/wysiwyg.css';
		}
		if ($skinStyle === 'cdn')
			return array('base' => $base, 'script' => $base . '/tinymce.min.js?v=7.9.3-bd1ab5e', 'config' => $config, 'cdn' => (is_array($skinStyles) ? $skinStyles['cdn'] : array()), 'fit' => $fit, 'gencss' => $gen);
		return array('base' => $base, 'script' => $base . '/tinymce.min.js?v=7.9.3-bd1ab5e', 'config' => $config, 'fit' => $fit, 'gencss' => $gen);
	}
	return array('base' => $base, 'script' => $base . '/tinymce.min.js?v=7.9.3-bd1ab5e', 'config' => $config);
}

function ST_Pages_manage() {
	global $blogURL;
	// 블로그 관리자만 — 저장하는 HTML 을 거르지 않고 그대로 내므로 필자에게는 열지 않는다
	if (!Acl::check('group.administrators')) {
		echo '<p class="message">' . ST_Pages_escape(_t('접근권한이 없습니다')) . '</p>';
		return;
	}
	$blogid = getBlogId();
	$types = ST_Pages_pageTypes();
	$errors = array();
	$notices = array();
	$form = array('pageid' => 0, 'path' => '', 'kind' => 'entry', 'target' => '', 'title' => '', 'content' => '');
	if (isset($_SERVER['REQUEST_METHOD']) && $_SERVER['REQUEST_METHOD'] == 'POST') {
		$action = ST_Pages_input($_POST, 'action');
		$pageid = ST_Pages_input($_POST, 'pageid');
		if ($action === 'delete') {
			if (!Validator::id($pageid) || ST_Pages_store('get', $blogid, array('pageid' => (int)$pageid)) === null) {
				$errors[] = ST_Pages_error('', _t('없는 페이지입니다.'));
			} else if (ST_Pages_store('delete', $blogid, array('pageid' => (int)$pageid))) {
				$notices[] = _t('지웠습니다.');
				ST_Pages_updateHint($blogid);
			} else {
				$errors[] = ST_Pages_error('', _t('지우지 못했습니다.'));
			}
		} else if ($action === 'add' || $action === 'update') {
			$form = array(
				'pageid' => ($action === 'update' && Validator::id($pageid)) ? (int)$pageid : 0,
				'path' => ST_Pages_input($_POST, 'path'),
				'kind' => ST_Pages_input($_POST, 'kind'),
				'target' => trim(ST_Pages_input($_POST, 'target')),
				'title' => ST_Pages_input($_POST, 'title'),
				'content' => ST_Pages_input($_POST, 'content')
			);
			$entered = $form;	// Textcube-NG: 넣은 값 — 저장하지 못하면 글 번호·내용을 이 값으로 다시 보인다(v3.218)
			if ($action === 'update' && ($form['pageid'] === 0 || ST_Pages_store('get', $blogid, array('pageid' => $form['pageid'])) === null)) {
				$errors[] = ST_Pages_error('', _t('없는 페이지입니다.'));
			} else {
				$errors = ST_Pages_check($form, $types, $blogid, $form['pageid']);
				if (empty($errors) && $action === 'add' && count(ST_Pages_store('list', $blogid)) >= ST_Pages::MAX_PAGES)
					$errors[] = ST_Pages_error('', _f('페이지는 %1개까지 만들 수 있습니다.', ST_Pages::MAX_PAGES));
			}
			if (empty($errors)) {
				if ($action === 'add') ST_Pages_dropHint();	// 넣기 전에 힌트를 지운다(넣은 뒤 힌트 갱신이 실패해도 새 페이지가 가려지지 않게)
				if ($action === 'add') $form['pageid'] = ST_Pages_store('nextId', $blogid);
				$payload = $form;
				$payload['target'] = ($form['target'] === '') ? 0 : (int)$form['target'];
				if (ST_Pages_store($action === 'add' ? 'insert' : 'update', $blogid, $payload)) {
					$notices[] = _t('저장했습니다.');
					ST_Pages_updateHint($blogid);
					$form = array('pageid' => 0, 'path' => '', 'kind' => 'entry', 'target' => '', 'title' => '', 'content' => '');
				} else {
					$errors[] = ST_Pages_error('', _t('저장하지 못했습니다.'));
				}
			}
			// Textcube-NG: 저장하지 못했으면(검사 오류·개수 제한·저장 실패·없는 페이지) 글 번호·내용을 넣은 그대로 다시 보인다.
			// ST_Pages_check 가 그 종류가 쓰지 않는 칸을 비운 값은 검사와 저장에만 쓴다(v3.218)
			if (!empty($errors)) {
				$form['target'] = $entered['target'];
				$form['content'] = $entered['content'];
			}
		} else {
			$errors[] = ST_Pages_error('', _t('알 수 없는 요청입니다.'));
		}
	} else if (Validator::id(ST_Pages_input($_GET, 'pageid'))) {
		$row = ST_Pages_store('get', $blogid, array('pageid' => (int)ST_Pages_input($_GET, 'pageid')));
		if ($row !== null) {
			$form = array('pageid' => (int)$row['pageid'], 'path' => (string)$row['pagepath'], 'kind' => (string)$row['pagekind'],
				'target' => ((int)$row['targetid'] > 0) ? (string)(int)$row['targetid'] : '', 'title' => (string)$row['title'],
				'content' => isset($row['content']) ? (string)$row['content'] : '');
		}
	}

	$rows = ST_Pages_store('list', $blogid);
	// 힌트가 목록 수와 다르면(옛 데이터·DB 에 직접 넣은 행·갱신 실패) 관리 화면을 열 때 맞춘다
	if (Setting::getBlogSetting(ST_Pages::HINT_SETTING, null) !== (string)count($rows)) ST_Pages_updateHint($blogid);
	$prefix = ST_Pages_prefix();
	$token = getCSRFToken();
	$action = $blogURL . '/owner/plugin/adminMenu?name=ST_Pages/ST_Pages_manage';
	// Textcube-NG: 종류별 칸 표·설명·수정 아이콘(v3.218). JS 로 넘기는 값은 json_encode HEX 플래그로 <script> 안에서 태그·따옴표·& 가 그대로 나오지 않게 한다
	$kindFields = array();
	foreach ($types as $id => $type) $kindFields[$id] = ST_Pages_kindFields($types, $id);
	$fieldLabels = array('title' => _t('제목'), 'target' => _t('글 번호'), 'content' => _t('내용'));
	$jsonFlags = JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_UNESCAPED_UNICODE | JSON_INVALID_UTF8_SUBSTITUTE;
	$kindFieldsJson = jsonEncodeCompat($kindFields, $jsonFlags);
	$messagesJson = jsonEncodeCompat(array(
		'confirmDelete' => _t('이 페이지를 지울까요?'),
		// Textcube-NG: 글 번호 찾기·편집기 전환 문구(v3.220)
		'searchEmpty' => _t('찾을 제목 일부나 글 번호를 입력해 주십시오.'),
		'searching' => _t('찾는 중입니다.'),
		'searchNone' => _t('맞는 글이 없습니다.'),
		'searchFailed' => _t('찾지 못했습니다. 다시 시도해 주십시오.'),
		'status' => array('reserved' => _t('예약'), 'private' => _t('비공개'), 'protected' => _t('보호'), 'public' => _t('공개'), 'published' => _t('발행')),
		'notice' => _t('공지'),
		'sourceView' => _t('HTML 보기'),
		'editorView' => _t('편집기 보기')
	), $jsonFlags);
	// Textcube-NG: 글 번호 찾기 주소·CSRF 토큰(X-CSRF-Token 헤더로 보낸다)·편집기 설정(v3.220)
	$pagesJson = jsonEncodeCompat(array('searchURL' => $blogURL . '/owner/plugin/adminHandler?name=ST_Pages/ST_Pages_searchEntries', 'token' => $token, 'editor' => ST_Pages_editorConfig()), $jsonFlags);
	if (!is_string($kindFieldsJson)) $kindFieldsJson = '{}';
	if (!is_string($messagesJson)) $messagesJson = '{}';
	if (!is_string($pagesJson)) $pagesJson = '{}';
	// Textcube-NG: r3-13 저장·삭제 결과 알림에 넘길 문구(사용자 결정 11-08 12:19). EAF4.js 의 PM 은 문구를 innerHTML 로 넣으므로 문구마다 여기서 한 번 escape 하고,
	// <script> 안에서 태그·따옴표·& 가 그대로 나오지 않게 위와 같은 json_encode HEX 플래그로 넘긴다(JS 는 escape 한 문구를 '<br />' 로만 잇는다)
	$alertErrors = array();
	foreach ($errors as $error) {
		if (is_array($error) && isset($error['message'])) $alertErrors[] = ST_Pages_escape($error['message']);
	}
	$alertNotices = array();
	foreach ($notices as $message) $alertNotices[] = ST_Pages_escape($message);
	$alertsJson = jsonEncodeCompat(array('errors' => $alertErrors, 'notices' => $alertNotices), $jsonFlags);
	if (!is_string($alertsJson)) $alertsJson = '{}';
	$editIcon = ST_Pages_editIcon();
?>
						<div id="part-entry-pages" class="part">
							<style type="text/css">
								#part-entry-pages .ng-pages-help{margin:4px 0 0;color:#666}
								#part-entry-pages ul.ng-pages-kinds{margin:6px 0 0;padding:0;list-style:none}
								#part-entry-pages ul.ng-pages-kinds li{margin:2px 0;color:#555}
								#part-entry-pages .ng-pages-toolbar{margin:0 0 4px}
								#part-entry-pages .ng-pages-toolbar .input-button{margin:0 4px 0 0}
								#part-entry-pages .ng-pages-preview{display:block;width:100%;height:20em;margin:6px 0 0;border:1px solid #ccc;background-color:#fff}
								#part-entry-pages table.data-inbox th.edit,#part-entry-pages table.data-inbox th.delete{width:50px}
								#part-entry-pages table.data-inbox tbody tr td.edit,#part-entry-pages table.data-inbox tbody tr td.delete{text-align:center}
								#part-entry-pages table.data-inbox tbody tr td.delete form{margin:0;padding:0}
								#part-entry-pages table.data-inbox tbody tr td.delete .delete-button{border:0;cursor:pointer}
								/* Textcube-NG: 페이지 추가 칸 배치(v3.220). 관리 스킨 setting.css·network.css 공통 규칙과 같은 값을 이 폼에만 쓴다(fieldset 배경 띠 없앰, dt 170px 오른쪽 정렬, dd 190px) */
								#part-entry-pages #pages-form fieldset{background-image:none;border-bottom:none !important;border-top:none !important;margin:0}
								#part-entry-pages #pages-form dl{border-top:1px solid #FFFFFF;border-bottom:1px solid #FFFFFF}
								#part-entry-pages #pages-form dl dt{padding:5px 3px 0 3px;text-align:right;width:170px}
								#part-entry-pages #pages-form dl dd{line-height:1.2em;margin-left:190px;padding:7px 3px 4px 3px}
								/* 목록 아래 둘째 제목(페이지 수정·추가) 위 여백: 관리 스킨 basic.css 의 fieldset 아래 여백(30px)과 같은 값 */
								#part-entry-pages div.data-inbox + h2.caption{margin-top:30px}
								/* 글 번호 찾기(v3.220) */
								#part-entry-pages .ng-pages-search{margin:6px 0 0}
								#part-entry-pages ul.ng-pages-results{margin:4px 0 0;padding:0;list-style:none}
								#part-entry-pages ul.ng-pages-results li{margin:2px 0}
								#part-entry-pages ul.ng-pages-results button{background:none;border:0;padding:0;color:inherit;font:inherit;text-decoration:underline;cursor:pointer;text-align:left}
								/* Textcube-NG: r3-13 칸에 딸린 저장 오류(칸 아래에 붉게, 알림과 같은 문구) */
								#part-entry-pages .ng-pages-error{margin:4px 0 0;color:#c00;font-weight:bold}
								/* Textcube-NG: r3-10 R-284 편집기 문단(blocks) 메뉴의 제목 1~3 미리보기(실제 h1~h3 태그)가 관리 스킨 basic.css 의 h1, h2 { display:none } 등에 가려지지 않게 함(ED_tinyMCE override.css 와 같은 규칙. 메뉴는 body 아래에 붙으므로 #part-entry-pages 로 묶지 않음) */
								.tox .tox-collection__item-label>h1,.tox .tox-collection__item-label>h2,.tox .tox-collection__item-label>h3{display:block!important}
<?php
	if ($editIcon !== '') {	// 링크 목록과 같은 값(관리 스킨 network.css 의 #part-link-list … td.edit .edit-button 은 그 화면 한정이라 이 화면 범위에 둔다)
?>
								#part-entry-pages table.data-inbox tbody tr td.edit .edit-button{background-color:transparent;background-image:url("<?php echo ST_Pages_escape($editIcon);?>");background-position:left top;display:block;height:11px;margin:0 auto;padding:0;width:11px}
								#part-entry-pages table.data-inbox tbody tr td.edit .edit-button span{display:none}
<?php
	} else {	// 수정 그림이 없는 관리 스킨(mobile): 삭제도 글자로 낸다(코어 mobile 링크 목록처럼 수정·삭제를 같은 모양으로 — post.css 의 삭제 아이콘 규칙을 이 화면에서 되돌린다)
?>
								#part-entry-pages table.data-inbox tbody tr td.delete .delete-button{background:none;display:inline;width:auto;height:auto;margin:0;padding:0;color:inherit;font:inherit;text-decoration:underline}
								#part-entry-pages table.data-inbox tbody tr td.delete .delete-button span{display:inline}
<?php
	}
?>
							</style>
							<h2 class="caption"><span class="main-text"><?php echo ST_Pages_escape(_t('페이지'));?></span></h2>
<?php
	// Textcube-NG: r3-13 저장·삭제 결과는 아래 스크립트가 관리 화면 알림(PM)으로 띄우고 칸에 딸린 오류는 그 칸 아래에도 낸다(사용자 결정 11-08 12:19).
	// 스크립트가 꺼져 있을 때만 예전처럼 이 자리에 문구를 낸다(noscript)
	if (!empty($errors) || !empty($notices)) {
		echo '<noscript>' . "\n";
		foreach ($errors as $error) {
			if (is_array($error) && isset($error['message'])) echo '<p class="message">' . ST_Pages_escape($error['message']) . '</p>' . "\n";
		}
		foreach ($notices as $message) echo '<p class="message">' . ST_Pages_escape($message) . '</p>' . "\n";
		echo '</noscript>' . "\n";
	}
?>
							<div class="main-explain-box">
								<p class="explain"><?php echo ST_Pages_escape(_t('블로그 주소 아래의 정해진 경로에 페이지를 둡니다. 경로는 블로그 주소 기준이며 앞뒤 / 는 쓰지 않아도 됩니다(예: about, info/contact).'));?></p>
								<p class="explain"><?php echo ST_Pages_escape(_t('관리·서비스 경로(/owner, /control, /api 등)와 Textcube 가 쓰는 경로 아래에는 만들 수 없고, 그런 경로의 페이지는 저장되어 있어도 내보내지 않습니다.'));?></p>
								<p class="explain"><?php echo ST_Pages_escape(_t('스킨에 맞춘 내용과 완전히 새 페이지의 내용은 입력한 HTML 을 거르지 않고 그대로 냅니다.'));?></p>
							</div>
							<div class="data-inbox">
								<table class="data-inbox" cellspacing="0" cellpadding="0">
									<thead>
										<tr>
											<th><?php echo ST_Pages_escape(_t('경로'));?></th>
											<th><?php echo ST_Pages_escape(_t('종류'));?></th>
											<th><?php echo ST_Pages_escape(_t('제목'));?></th>
											<th><?php echo ST_Pages_escape(_t('상태'));?></th>
											<th class="edit"><span class="text"><?php echo ST_Pages_escape(_t('수정'));?></span></th>
											<th class="delete"><span class="text"><?php echo ST_Pages_escape(_t('삭제'));?></span></th>
										</tr>
									</thead>
									<tbody>
<?php
	if (empty($rows)) {
?>
										<tr><td colspan="6"><?php echo ST_Pages_escape(_t('만든 페이지가 없습니다.'));?></td></tr>
<?php
	}
	foreach ($rows as $row) {
		$rowPath = isset($row['pagepath']) ? (string)$row['pagepath'] : '';
		$rowKind = isset($row['pagekind']) ? (string)$row['pagekind'] : '';
		$refused = ST_Pages_resolve(array($row), $rowPath, $prefix) === null;
?>
										<tr>
											<td>/<?php echo ST_Pages_escape($rowPath);?></td>
											<td><?php echo ST_Pages_escape(isset($types[$rowKind]) ? $types[$rowKind]['title'] : $rowKind);?></td>
											<td><?php echo ST_Pages_escape(isset($row['title']) ? $row['title'] : '');?></td>
											<td><?php echo ST_Pages_escape($refused ? _t('출력 거부됨(예약·형식 오류 경로)') : (isset($types[$rowKind]) ? _t('사용') : _t('알 수 없는 종류(404)')));?></td>
											<td class="edit"><a class="edit-button button" href="<?php echo ST_Pages_escape($action . '&pageid=' . (int)$row['pageid']);?>" title="<?php echo ST_Pages_escape(_t('수정'));?>"><span><?php echo ST_Pages_escape(_t('수정'));?></span></a></td>
											<td class="delete">
												<form class="pages-delete-form" method="post" action="<?php echo ST_Pages_escape($action);?>">
													<input type="hidden" name="csrfToken" value="<?php echo ST_Pages_escape($token);?>" />
													<input type="hidden" name="action" value="delete" />
													<input type="hidden" name="pageid" value="<?php echo (int)$row['pageid'];?>" />
													<button type="submit" class="delete-button button" title="<?php echo ST_Pages_escape(_t('삭제'));?>"><span class="text"><?php echo ST_Pages_escape(_t('삭제'));?></span></button>
												</form>
											</td>
										</tr>
<?php
	}
?>
									</tbody>
								</table>
							</div>
							<h2 class="caption"><span class="main-text"><?php echo ST_Pages_escape($form['pageid'] > 0 ? _t('페이지 수정') : _t('페이지 추가'));?></span></h2>
							<div class="data-inbox">
								<form id="pages-form" class="section" method="post" action="<?php echo ST_Pages_escape($action);?>">
									<input type="hidden" name="csrfToken" value="<?php echo ST_Pages_escape($token);?>" />
									<input type="hidden" name="action" value="<?php echo $form['pageid'] > 0 ? 'update' : 'add';?>" />
									<input type="hidden" name="pageid" value="<?php echo (int)$form['pageid'];?>" />
									<fieldset class="container">
										<legend><?php echo ST_Pages_escape(_t('페이지'));?></legend>
										<dl class="line">
											<dt><span class="label"><?php echo ST_Pages_escape(_t('경로'));?></span></dt>
											<dd>/<input type="text" class="input-text" style="width:24em" name="path" maxlength="<?php echo ST_Pages::MAX_PATH_BYTES;?>" value="<?php echo ST_Pages_escape($form['path']);?>" />
												<p class="ng-pages-help"><?php echo ST_Pages_escape(_t('블로그 주소 뒤에 붙는 경로입니다. 예: about 이면 블로그 주소/about 에서 보입니다.'));?></p>
												<?php ST_Pages_fieldErrors($errors, 'path');?>
											</dd>
										</dl>
										<dl class="line">
											<dt><span class="label"><?php echo ST_Pages_escape(_t('종류'));?></span></dt>
											<dd>
												<select id="pages-kind" name="kind">
<?php
	foreach ($types as $id => $type) {
?>
													<option value="<?php echo ST_Pages_escape($id);?>"<?php echo $form['kind'] === $id ? ' selected="selected"' : '';?>><?php echo ST_Pages_escape($type['title']);?></option>
<?php
	}
?>
												</select>
												<ul id="pages-kind-help" class="ng-pages-kinds">
<?php
	foreach ($types as $id => $type) {
		$used = array();
		if ($kindFields[$id] !== null) foreach ($kindFields[$id] as $name) $used[] = $fieldLabels[$name];
?>
													<li id="pages-kind-help-<?php echo ST_Pages_escape($id);?>"><strong><?php echo ST_Pages_escape($type['title']);?></strong>: <?php echo ST_Pages_escape(ST_Pages_kindHelp($id));?> <?php echo ST_Pages_escape($kindFields[$id] === null ? _t('쓰는 칸을 밝히지 않은 종류라 모든 칸을 보입니다.') : _f('쓰는 칸: %1', implode(', ', $used)));?></li>
<?php
	}
?>
												</ul>
												<p id="pages-hidden-note" class="ng-pages-help" style="display:none"><?php echo ST_Pages_escape(_t('고른 종류가 쓰지 않는 칸은 숨겼습니다. 저장할 때도 그 칸의 값은 버립니다.'));?></p>
												<?php ST_Pages_fieldErrors($errors, 'kind');?>
											</dd>
										</dl>
										<dl id="pages-field-target" class="line">
											<dt><span class="label"><?php echo ST_Pages_escape(_t('글 번호'));?></span></dt>
											<dd><input type="text" class="input-text" style="width:8em" id="pages-target" name="target" value="<?php echo ST_Pages_escape($form['target']);?>" /> <?php echo ST_Pages_escape(_t('기존 글·비공개 글일 때 연결할 글(공지 포함)의 번호'));?>
												<?php ST_Pages_fieldErrors($errors, 'target');?>
												<?php /* Textcube-NG: 글 번호 찾기(v3.220). JS 가 찾기 주소를 받았을 때만 보인다. 찾기 칸은 name 이 없어 폼과 함께 보내지 않는다 */ ?>
												<div id="pages-target-search" class="ng-pages-search" style="display:none">
													<input type="text" class="input-text" style="width:16em" id="pages-target-query" maxlength="<?php echo ST_Pages::MAX_TITLE_BYTES;?>" title="<?php echo ST_Pages_escape(_t('찾을 제목 일부나 글 번호'));?>" placeholder="<?php echo ST_Pages_escape(_t('찾을 제목 일부나 글 번호'));?>" />
													<button type="button" id="pages-target-search-button" class="input-button"><?php echo ST_Pages_escape(_t('찾기'));?></button>
													<p id="pages-target-status" class="ng-pages-help" style="display:none"></p>
													<ul id="pages-target-results" class="ng-pages-results"></ul>
													<p class="ng-pages-help"><?php echo ST_Pages_escape(_t('제목 일부나 글 번호로 이 블로그의 글을 찾아 고르면 글 번호 칸에 넣습니다(공지·비공개 글 포함, 임시 저장 글 제외, 최대 10개).'));?></p>
												</div>
											</dd>
										</dl>
										<dl id="pages-field-title" class="line">
											<dt><span class="label"><?php echo ST_Pages_escape(_t('제목'));?></span></dt>
											<dd><input type="text" class="input-text" style="width:24em" name="title" maxlength="<?php echo ST_Pages::MAX_TITLE_BYTES;?>" value="<?php echo ST_Pages_escape($form['title']);?>" />
												<p class="ng-pages-help"><?php echo ST_Pages_escape(_t('모든 종류에 필요합니다(목록에 쓰는 이름). 화면에서 어떻게 쓰이는지는 위 종류 설명을 보십시오.'));?></p>
												<?php ST_Pages_fieldErrors($errors, 'title');?>
											</dd>
										</dl>
										<dl id="pages-field-content" class="line">
											<dt><span class="label"><?php echo ST_Pages_escape(_t('내용'));?></span></dt>
											<dd>
												<div id="pages-toolbar" class="ng-pages-toolbar" style="display:none">
													<button type="button" id="pages-tool-bold" class="input-button"><?php echo ST_Pages_escape(_t('굵게'));?></button>
													<button type="button" id="pages-tool-link" class="input-button"><?php echo ST_Pages_escape(_t('링크'));?></button>
													<button type="button" id="pages-tool-image" class="input-button"><?php echo ST_Pages_escape(_t('이미지'));?></button>
													<button type="button" id="pages-tool-paragraph" class="input-button"><?php echo ST_Pages_escape(_t('문단'));?></button>
													<button type="button" id="pages-preview-button" class="input-button"><?php echo ST_Pages_escape(_t('미리보기'));?></button>
													<button type="button" id="pages-tool-source" class="input-button" style="display:none"><?php echo ST_Pages_escape(_t('HTML 보기'));?></button>
												</div>
												<textarea id="pages-content" name="content" cols="80" rows="16"><?php echo ST_Pages_escape($form['content']);?></textarea>
												<iframe id="pages-preview" class="ng-pages-preview" sandbox="" title="<?php echo ST_Pages_escape(_t('내용 미리보기'));?>" style="display:none"></iframe>
												<p class="ng-pages-help"><?php echo ST_Pages_escape(_t('스킨에 맞춘 내용은 본문 자리에 넣을 HTML 조각을, 완전히 새 페이지는 HTML 문서 전체를 씁니다. 입력한 HTML 은 거르지 않고 그대로 냅니다.'));?></p>
												<p id="pages-tool-help" class="ng-pages-help"><?php echo ST_Pages_escape(_t('도구줄(굵게·링크·이미지·문단·미리보기)은 스킨에 맞춘 내용에서 보입니다. 미리보기는 스킨 없이 보여 주며, 스크립트를 실행하지 않고 외부 주소의 이미지·스타일 파일·글꼴은 불러오지 않습니다.'));?></p>
												<?php /* Textcube-NG: 편집기(v3.220) 안내. 편집기가 보이면 위 도구줄 안내 대신 보이고, 불러오지 못하면 실패 안내를 보인다 */ ?>
												<p id="pages-editor-help" class="ng-pages-help" style="display:none"><?php echo ST_Pages_escape(_t('편집기에서 고치면 편집기가 HTML 을 다시 쓰므로 편집기가 받지 않는 태그·속성은 빠지거나 바뀔 수 있습니다. 입력한 HTML 을 그대로 두려면 HTML 보기에서 고치십시오. 이미지는 주소로 넣습니다(파일 올리기 없음).'));?></p>
												<p id="pages-editor-failed" class="ng-pages-help" style="display:none"><?php echo ST_Pages_escape(_t('편집기를 불러오지 못해 HTML 도구줄을 씁니다.'));?></p>
												<?php ST_Pages_fieldErrors($errors, 'content');?>
											</dd>
										</dl>
									</fieldset>
									<div class="button-box">
										<input type="submit" class="save-button input-button wide-button" value="<?php echo ST_Pages_escape(_t('저장하기'));?>" />
									</div>
								</form>
							</div>
							<script type="text/javascript">
							//<![CDATA[
							(function () {
								// Textcube-NG: 종류별 칸 보이기·HTML 도구줄·미리보기·삭제 확인(v3.218). JS 가 꺼져 있거나 오래된 브라우저이면 모든 칸이 그대로 보이고 삭제는 확인 없이 보낸다
								var kindFields = <?php echo $kindFieldsJson;?>;
								var messages = <?php echo $messagesJson;?>;
								var pages = <?php echo $pagesJson;?>;	// Textcube-NG: 글 번호 찾기 주소·토큰·편집기 설정(v3.220)
								// Textcube-NG: r3-13 저장·삭제 결과 알림(사용자 결정 11-08 12:19). 서버가 문구마다 escape 한 목록을 받아 EAF4.js 의 PM 으로 띄운다(PM 은 문구를 innerHTML 로 넣음).
								// 오류는 빨강(PM.showErrorMessage)으로 긴 문구도 읽게 10초, 결과는 초록(PM.showMessage)으로 기본 3초 동안 아래 가운데("center","bottom")에 띄운다.
								// 여러 문구는 겹치지 않게 한 알림에 줄을 바꿔 잇는다. 페이지를 다 읽은 뒤(load) 띄우고, PM 이 없으면 띄우지 않는다(칸에 딸린 오류는 칸 아래 문구로 남음)
								var alerts = <?php echo $alertsJson;?>;
								function alertText(list) {
									var out = [], j;
									if (!list || typeof list.length !== 'number') return '';
									for (j = 0; j < list.length; j++) if (typeof list[j] === 'string' && list[j] !== '') out.push(list[j]);
									return out.join('<br />');
								}
								function showAlerts() {
									var pm = window.PM, text;
									if (!alerts || typeof alerts !== 'object' || !pm) return;
									text = alertText(alerts.errors);
									if (text !== '') {
										if (typeof pm.showErrorMessage === 'function') pm.showErrorMessage(text, 'center', 'bottom', 10000);
										return;
									}
									text = alertText(alerts.notices);
									if (text !== '' && typeof pm.showMessage === 'function') pm.showMessage(text, 'center', 'bottom');
								}
								if (window.addEventListener) {
									if (document.readyState === 'complete') showAlerts();
									else window.addEventListener('load', showAlerts, false);
								}
								var form = document.getElementById('pages-form');
								if (!form || !window.addEventListener || !document.getElementsByTagName) return;
								var kind = document.getElementById('pages-kind');
								var content = document.getElementById('pages-content');
								var toolbar = document.getElementById('pages-toolbar');
								var preview = document.getElementById('pages-preview');
								var previewButton = document.getElementById('pages-preview-button');
								var help = document.getElementById('pages-kind-help');
								var note = document.getElementById('pages-hidden-note');
								var tags = {bold: ['<strong>', '<\/strong>'], link: ['<a href="https://">', '<\/a>'], image: ['<img src="https://" alt="" />', ''], paragraph: ['<p>', '<\/p>']};
								// 미리보기 문서 맨 앞에만 붙이는 CSP meta(저장되는 내용은 바꾸지 않는다). 첫 토큰인 meta 는 HTML 파서가 암시적 head 안에 넣으므로 내용이 조각이어도
								// 그 뒤의 모든 요청에 적용된다. 외부 주소의 이미지·스타일 파일·글꼴·프레임·미디어 등 요청을 막고, 문서 안 스타일과 data: 이미지·글꼴만 쓴다
								var previewHead = '<meta http-equiv="Content-Security-Policy" content="default-src \'none\'; style-src \'unsafe-inline\'; img-src data:; font-src data:" />';
								var i, name, button;
								if (!kind || !content) return;
								if (!kindFields || typeof kindFields !== 'object') kindFields = {};
								if (!pages || typeof pages !== 'object') pages = {};
								// Textcube-NG: 스킨에 맞춘 내용의 편집기(ED_tinyMCE 의 TinyMCE 7.9.3, v3.220·R-173). 그 종류를 처음 고를 때 한 번만 불러온다.
								// 편집기에서 고치지 않으면 입력한 HTML 을 그대로 두고(숨길 때 되돌림), 고쳤으면 저장 직전에만 textarea 로 옮긴다. 못 불러오면 기존 HTML 도구줄을 쓴다
								var sourceButton = document.getElementById('pages-tool-source');
								var toolHelp = document.getElementById('pages-tool-help');
								var editorHelp = document.getElementById('pages-editor-help');
								var failedNote = document.getElementById('pages-editor-failed');
								var toolNames = ['bold', 'link', 'image', 'paragraph'];
								var editorConfig = (pages.editor && typeof pages.editor === 'object' && typeof pages.editor.script === 'string' && typeof pages.editor.base === 'string'
									&& pages.editor.config && typeof pages.editor.config === 'object') ? pages.editor : null;
								var ed = null, editorState = 0, wantEditor = true;	// editorState: 0 안 불러옴, 1 불러오는 중, 2 준비됨, -1 실패. wantEditor: HTML 보기를 고르면 false
								function uses(field) {
									var fields = Object.prototype.hasOwnProperty.call(kindFields, kind.value) ? kindFields[kind.value] : null;
									if (!fields || typeof fields.length !== 'number') return true;	// 칸 목록이 없는 종류: 모든 칸
									for (var j = 0; j < fields.length; j++) if (fields[j] === field) return true;
									return false;
								}
								function show(element, on) {
									if (element) element.style.display = on ? '' : 'none';
								}
								function previewOpen() {
									return !!preview && preview.style.display !== 'none';
								}
								function refresh() {
									if (previewOpen()) preview.srcdoc = previewHead + currentHTML();	// sandbox="" iframe: 스크립트·폼·팝업·같은 출처 접근 없음, CSP meta: 외부 자원 요청 없음
								}
								function editorShown() {	// Textcube-NG: 편집기(v3.220)가 준비되어 보이는지
									return !!ed && !ed.isHidden();
								}
								function currentHTML() {	// 편집기에서 고친 내용은 저장 전까지 textarea 에 없으므로 편집기에서 읽는다
									return (editorShown() && ed.isDirty()) ? ed.getContent() : content.value;
								}
								function tools() {	// 편집기가 보이면 textarea 에 넣는 태그 단추와 그 안내를 숨긴다
									var on = editorShown();
									for (var j = 0; j < toolNames.length; j++) show(document.getElementById('pages-tool-' + toolNames[j]), !on);
									show(toolHelp, !on);
									show(editorHelp, on);
									if (sourceButton) {
										show(sourceButton, !!ed);
										sourceButton.textContent = (on ? messages.sourceView : messages.editorView) || '';
									}
									show(failedNote, editorState === -1 && kind.value === 'skin' && uses('content'));
								}
								function hideEditor() {
									if (!editorShown()) return;
									var dirty = ed.isDirty(), before = content.value;
									ed.hide();	// 편집기 내용을 textarea 로 옮긴다
									if (!dirty) content.value = before;	// 고치지 않았으면 입력한 HTML 을 그대로 둔다(편집기 직렬화로 바뀌지 않게)
									tools();
									refresh();
								}
								function showEditor() {
									if (!ed || editorShown()) return;
									ed.show();	// textarea 내용을 다시 읽는다
									ed.undoManager.clear();
									ed.undoManager.add();
									ed.setDirty(false);
									tools();
									refresh();
								}
								function editorFailed() {	// 불러오지 못함: 기존 HTML 도구줄을 쓴다
									editorState = -1;
									ed = null;
									tools();
								}
								function initEditor() {
									var config = {}, key, started;
									for (key in editorConfig.config) {
										if (Object.prototype.hasOwnProperty.call(editorConfig.config, key)) config[key] = editorConfig.config[key];
									}
									config.setup = function (editor) {
										editor.on('init', function () {
											ed = editor;
											editorState = 2;
											if (!(wantEditor && kind.value === 'skin' && uses('content'))) hideEditor();	// 불러오는 동안 종류를 바꿨거나 HTML 보기를 골랐으면 숨긴다
											tools();
											refresh();
										});
										editor.on('input change keyup ExecCommand SetContent Undo Redo', refresh);
<?php if (ST_Pages_skinStyle() !== 'none') { ?>
										// PHP port(Textcube-NG): r3-12 편집기 전용 CSS 생성기가 만든 CSS(블로그 화면용 로컬 CSS·skin.html <style> 의 선택자를 편집기 본문에 맞게 바꾼 것)를 편집기 iframe <head> 에 <style> 로 넣는다
										// (사용자 결정 11-08 "생성기 = r3-12", 원본에 없는 새 기능). textContent 로만 넣고(innerHTML 안 씀), 로컬 CSS 가 있던 자리(content_css 첫 링크 = 되돌림 CSS 앞)에 둔다.
										// 그 링크를 못 찾으면 <head> 끝에 둔다. 편집 구역 본문·저장되는 글은 바꾸지 않는다
										editor.on('init', function () {
											var gen = editorConfig.gencss, doc = editor.getDoc(), links, el, at = null, before, j;
											if (!doc || !doc.head || !gen || typeof gen !== 'object' || typeof gen.style !== 'string') return;
											el = doc.createElement('style');
											el.setAttribute('data-textcube', 'skin-editor-css');
											el.textContent = gen.style;
											if (typeof gen.before === 'string') {
												try { before = new URL(gen.before, doc.baseURI).href.split('?')[0]; } catch (e) { before = null; }
												links = doc.head.getElementsByTagName('link');
												for (j = 0; before && j < links.length; j++) if (links[j].href.split('?')[0] === before) { at = links[j]; break; }
											}
											doc.head.insertBefore(el, at);
											if (editor.plugins && editor.plugins.autoresize) { try { editor.execCommand('mceAutoResize'); } catch (e) {} }
										});
<?php } ?>
<?php if (ST_Pages_skinStyle() === 'cdn') { ?>
										// Textcube-NG: r3-11 skinstyle 이 cdn 일 때만, skin.html 의 https CDN 스타일(integrity 있는 것만)을 편집기 iframe <head> 에 integrity·crossorigin 과 함께 넣는다(ED_tinyMCE/index.php 와 같은 방식, 사용자 결정 11-08 B2·B3·16:4x)
										editor.on('init', function () {
											var list = (editorConfig.cdn && typeof editorConfig.cdn.length === 'number') ? editorConfig.cdn : [], doc = editor.getDoc(), links, item, el, at, before, i, j;
											if (!doc || !doc.head) return;
											links = doc.head.getElementsByTagName('link');
											for (i = 0; i < list.length; i++) {
												item = list[i];
												if (!item || typeof item.href !== 'string' || !/^https:\/\//.test(item.href) || typeof item.integrity !== 'string' || item.integrity === '') continue;
												el = doc.createElement('link');
												el.setAttribute('rel', 'stylesheet');
												el.setAttribute('integrity', item.integrity);
												if (item.crossorigin === 'anonymous' || item.crossorigin === 'use-credentials') el.setAttribute('crossorigin', item.crossorigin);
												el.setAttribute('href', item.href);
												at = null;
												if (typeof item.before === 'string') {
													try { before = new URL(item.before, doc.baseURI).href.split('?')[0]; } catch (e) { before = null; }
													for (j = 0; before && j < links.length; j++) if (links[j].href.split('?')[0] === before) { at = links[j]; break; }
												}
												doc.head.insertBefore(el, at);
											}
										});
<?php } ?>
<?php if (ST_Pages_skinStyle() !== 'none') { ?>
										// Textcube-NG: r3-11 skinstyle 이 local·cdn 일 때 편집 구역 글자 크기를 블로그 화면에 맞춘다(사용자 결정 11-08 18:4x "편집기 안에서 재서 맞춤", 원본에 없는 새 기능).
										// 편집기는 본문 자리를 감싼 요소들의 class 를 body 한 곳에 붙이고 되돌림 CSS 가 html 글자 크기를 100% 로 두므로, 바깥 요소의 em·% 글자 크기가 블로그 화면
										// (예: periwinkle html 62.5% → body 14px → div.container 0.938em = 13.13px)과 달라진다. 모든 content CSS 가 실린 뒤(TinyMCE 는 content_css 를 다 읽은 뒤 init 을 냄.
										// cdn 이면 위에서 넣은 CDN 링크의 load·error 까지 기다리고, 10초가 지나면 그대로 잰다) 한 번 돈다.
										//  1) 편집기 전용 CSS(서버가 준 off: 되돌림 CSS·wysiwyg.css. skin.html 이 블로그 화면용으로 실은 스킨 wysiwyg.css 는 서버가 빼므로 켠 채로 둠)를 잠시 끄고, 편집 문서 html 아래에 보이지 않는 body 요소를 따로 만들어 그 안에 감싼 요소들
										//     (서버가 준 [tag, class], 바깥 → 안쪽)을 겹겹이 넣어 가장 안쪽 요소의 글자 크기(블로그 화면에서 본문이 받는 크기)를 잰 뒤, 지우고 다시 켠다.
										//  2) 편집 문서 html 에 inline font-size(!important. 되돌림 CSS 의 100%!important 보다 셈)를 10px·20px 로 주어 편집 구역 body 크기가 바뀌는 비율을 구하고,
										//     body 크기가 1) 의 값이 되는 html 크기를 준 뒤 다시 재어 0.1px 안에서 맞을 때만 둔다.
										// 잰 값이 숫자가 아니거나 0 이하이거나 72px(편집기 글자 크기 목록의 가장 큰 값 36pt = 48px 의 1.5배)를 넘으면, 끌 CSS 를 다 찾지 못하면, body 크기가 html 크기를 따르지
										// 않으면(px 고정) html style 을 원래대로 되돌려 되돌림 CSS 의 100% 상태로 둔다. 예외는 잡아서 편집을 막지 않는다. 편집 구역 본문·body 의 class·저장되는 글은 바꾸지 않는다
										// (바꾸는 것은 html 요소의 style 뿐이며 TinyMCE 는 body 안만 저장한다). 한계: rem(html 글자 크기 기준)으로 정한 글자 크기, #id·속성·가상 클래스로만 걸리는 글자 크기는 맞추지 못한다
										editor.on('init', function () {
											var fit = (editorConfig.fit && typeof editorConfig.fit === 'object') ? editorConfig.fit : null, doc = editor.getDoc(), win = doc ? doc.defaultView : null, max = 72, waits = 0, done = false, timer = null, links, i;
											if (!doc || !win || !doc.head || !doc.documentElement || !fit || typeof fit !== 'object' || !fit.chain || typeof fit.chain.length !== 'number'
												|| fit.chain.length < 1 || fit.chain.length > 64 || !fit.off || typeof fit.off.length !== 'number' || fit.off.length < 1) return;
											function size(el) {
												var v = parseFloat(win.getComputedStyle(el).fontSize);
												return (isFinite(v) && v > 0 && v <= max) ? v : NaN;
											}
											function run() {
												var root = doc.documentElement, body = editor.getBody(), hadStyle, oldValue, oldPriority, off = [], sheets = [], probe = null, parent, el, item, target, b1, b2, a, h, ok = false, j;
												if (done) return;
												done = true;
												if (timer) clearTimeout(timer);
												if (editor.removed || !body) return;
												hadStyle = root.hasAttribute('style');
												oldValue = root.style.getPropertyValue('font-size');
												oldPriority = root.style.getPropertyPriority('font-size');
												try {
													for (j = 0; j < fit.off.length; j++) {
														if (typeof fit.off[j] !== 'string') return;
														off.push(new URL(fit.off[j], doc.baseURI).href.split('?')[0]);
													}
													for (j = 0; j < links.length; j++) {
														if (links[j].sheet && !links[j].sheet.disabled && off.indexOf(links[j].href.split('?')[0]) !== -1) {
															links[j].sheet.disabled = true;
															sheets.push(links[j].sheet);
														}
													}
													if (sheets.length !== off.length) return;
													probe = doc.createElement('body');
													probe.style.setProperty('display', 'none', 'important');
													root.appendChild(probe);
													parent = probe;
													for (j = 0; j < fit.chain.length; j++) {
														item = fit.chain[j];
														if (!item || typeof item.tag !== 'string' || !/^[a-z][a-z0-9]{0,9}$/.test(item.tag) || typeof item['class'] !== 'string' || !/^[A-Za-z0-9_ -]*$/.test(item['class'])) return;
														el = doc.createElement(item.tag);
														if (item['class'] !== '') el.className = item['class'];
														parent.appendChild(el);
														parent = el;
													}
													target = size(parent);
													root.removeChild(probe);
													probe = null;
													for (j = 0; j < sheets.length; j++) sheets[j].disabled = false;
													sheets = [];
													if (!(target > 0)) return;
													root.style.setProperty('font-size', '10px', 'important');
													b1 = size(body);
													root.style.setProperty('font-size', '20px', 'important');
													b2 = size(body);
													a = (b2 - b1) / 10;
													if (!(a > 0.01)) return;
													h = 10 + (target - b1) / a;
													if (!(isFinite(h) && h > 0 && h <= max)) return;
													root.style.setProperty('font-size', h.toFixed(3) + 'px', 'important');
													ok = Math.abs(size(body) - target) <= 0.1;
												} catch (e) {
													ok = false;
												} finally {
													if (probe && probe.parentNode) probe.parentNode.removeChild(probe);
													for (j = 0; j < sheets.length; j++) sheets[j].disabled = false;
													if (!ok) {
														root.style.removeProperty('font-size');
														if (oldValue) root.style.setProperty('font-size', oldValue, oldPriority);
														if (!hadStyle && root.getAttribute('style') === '') root.removeAttribute('style');
													}
												}
											}
											links = doc.head.getElementsByTagName('link');
											for (i = 0; i < links.length; i++) {
												if (links[i].hasAttribute('integrity') && !links[i].sheet) {
													waits++;
													links[i].addEventListener('load', function () { if (--waits <= 0) run(); });
													links[i].addEventListener('error', function () { if (--waits <= 0) run(); });
												}
											}
											if (waits > 0) timer = setTimeout(run, 10000);
											else run();
										});
<?php } ?>
									};
									try {
										started = window.tinymce.init(config);
									} catch (e) {
										editorFailed();
										return;
									}
									if (started && typeof started.then === 'function') started.then(function (editors) {
										if (!editors || !editors.length) editorFailed();
									});
								}
								function startEditor() {	// 스킨에 맞춘 내용을 처음 고를 때 한 번만 불러온다
									if (editorState !== 0 || !editorConfig) return;
									editorState = 1;
									if (window.tinymce && typeof window.tinymce.init === 'function') {
										initEditor();
										return;
									}
									if (typeof window.tinyMCEPreInit === 'undefined') window.tinyMCEPreInit = {base: editorConfig.base, suffix: '.min'};	// 테마·플러그인·언어 파일 주소의 기준
									var script = document.createElement('script');
									script.type = 'text/javascript';
									script.onload = function () {
										if (window.tinymce && typeof window.tinymce.init === 'function') initEditor();
										else editorFailed();
									};
									script.onerror = function () { editorFailed(); };
									script.src = editorConfig.script;
									(document.getElementsByTagName('head')[0] || document.body).appendChild(script);
								}
								function editorFor(on) {	// update() 가 부른다: 스킨에 맞춘 내용이고 내용 칸을 쓰면 편집기, 아니면 숨김(HTML 그대로)
									if (on && wantEditor && editorConfig) {
										if (ed) showEditor();
										else startEditor();
									} else if (editorShown()) {
										hideEditor();
									}
									tools();
								}
								function update() {
									var target = uses('target'), body = uses('content');
									show(document.getElementById('pages-field-target'), target);
									show(document.getElementById('pages-field-content'), body);
									show(note, !target || !body);
									show(toolbar, body && kind.value === 'skin');
									if (!body || kind.value !== 'skin') show(preview, false);
									editorFor(body && kind.value === 'skin');	// Textcube-NG: 편집기 보이기·숨기기(v3.220)
									if (help) {
										var items = help.getElementsByTagName('li');
										for (var j = 0; j < items.length; j++) show(items[j], items[j].id === 'pages-kind-help-' + kind.value);
									}
								}
								function insert(prefix, postfix) {	// ownerView.php insertTag 와 같은 방식: 고른 글자를 태그로 감싼다
									content.focus();
									if (typeof content.selectionStart === 'number') {
										var start = content.selectionStart, end = content.selectionEnd, value = content.value;
										content.value = value.substring(0, start) + prefix + value.substring(start, end) + postfix + value.substring(end);
										content.selectionStart = start + prefix.length;
										content.selectionEnd = end + prefix.length;
									} else {
										content.value += prefix + postfix;
									}
									refresh();
								}
								for (name in tags) {
									if (!Object.prototype.hasOwnProperty.call(tags, name)) continue;
									button = document.getElementById('pages-tool-' + name);
									if (button) button.addEventListener('click', (function (pair) {
										return function () { insert(pair[0], pair[1]); };
									})(tags[name]), false);
								}
								if (preview && previewButton && ('srcdoc' in preview)) {
									previewButton.addEventListener('click', function () { show(preview, !previewOpen()); refresh(); }, false);
									content.addEventListener('input', refresh, false);
								} else {
									show(previewButton, false);	// srcdoc 를 모르는 브라우저: 미리보기를 쓰지 않는다
								}
								kind.addEventListener('change', update, false);
								if (sourceButton) sourceButton.addEventListener('click', function () {	// Textcube-NG: 편집기 ↔ HTML 보기(v3.220)
									if (editorShown()) {
										wantEditor = false;
										hideEditor();
									} else {
										wantEditor = true;
										if (ed) showEditor();
										else startEditor();
									}
									tools();
								}, false);
								form.addEventListener('submit', function () {	// 편집기에서 고쳤을 때만 textarea 로 옮긴다(고치지 않았으면 입력한 HTML 그대로 보낸다)
									if (editorShown() && ed.isDirty()) ed.save();
								}, false);
								// Textcube-NG: 글 번호 찾기(v3.220). POST·X-CSRF-Token 으로 adminHandler 에 묻고, 결과는 textContent 로만 넣는다. 고르면 글 번호 칸을 채운다
								var targetInput = document.getElementById('pages-target');
								var searchBox = document.getElementById('pages-target-search');
								var queryInput = document.getElementById('pages-target-query');
								var searchButton = document.getElementById('pages-target-search-button');
								var searchStatus = document.getElementById('pages-target-status');
								var results = document.getElementById('pages-target-results');
								var searchRequest = null;
								function searchSay(text) {
									searchStatus.textContent = text || '';
									show(searchStatus, !!text);
								}
								function addResult(item) {
									var id = (item && typeof item === 'object') ? parseInt(item.id, 10) : 0;
									if (!(id > 0)) return;
									var label = String(id) + ' · ' + (typeof item.title === 'string' ? item.title : '');
									if (messages.status && typeof item.status === 'string' && Object.prototype.hasOwnProperty.call(messages.status, item.status)) label += ' · ' + messages.status[item.status];
									if (item.notice === true && messages.notice) label += ' · ' + messages.notice;
									var li = document.createElement('li'), pick = document.createElement('button');
									pick.type = 'button';
									pick.textContent = label;
									pick.addEventListener('click', function () {
										targetInput.value = String(id);
										targetInput.focus();
									}, false);
									li.appendChild(pick);
									results.appendChild(li);
								}
								function search() {
									var q = queryInput.value.replace(/^\s+|\s+$/g, ''), old = searchRequest;
									searchRequest = null;
									if (old) old.abort();
									while (results.firstChild) results.removeChild(results.firstChild);
									if (q === '') {
										searchSay(messages.searchEmpty);
										return;
									}
									var request = new XMLHttpRequest();
									searchRequest = request;
									request.open('POST', pages.searchURL, true);
									request.setRequestHeader('Content-Type', 'application/x-www-form-urlencoded; charset=UTF-8');
									request.setRequestHeader('X-CSRF-Token', typeof pages.token === 'string' ? pages.token : '');
									request.onreadystatechange = function () {
										if (request.readyState !== 4 || searchRequest !== request) return;
										searchRequest = null;
										var data = null;
										if (request.status === 200) {
											try { data = JSON.parse(request.responseText); } catch (e) { data = null; }
										}
										if (!data || typeof data !== 'object' || !data.items || typeof data.items.length !== 'number') {
											searchSay(messages.searchFailed);
											return;
										}
										if (data.items.length === 0) {
											searchSay(messages.searchNone);
											return;
										}
										searchSay('');
										for (var j = 0; j < data.items.length; j++) addResult(data.items[j]);
									};
									searchSay(messages.searching);
									request.send('q=' + encodeURIComponent(q));
								}
								if (targetInput && searchBox && queryInput && searchButton && searchStatus && results && window.XMLHttpRequest && window.JSON
									&& typeof pages.searchURL === 'string' && ('textContent' in searchStatus)) {
									searchButton.addEventListener('click', search, false);
									queryInput.addEventListener('keydown', function (event) {
										if (event.keyCode === 13) {	// Enter: 페이지 폼을 보내지 않고 찾는다
											event.preventDefault();
											search();
										}
									}, false);
									show(searchBox, true);
								}
								var forms = document.getElementsByTagName('form');
								for (i = 0; i < forms.length; i++) {
									if ((' ' + forms[i].className + ' ').indexOf(' pages-delete-form ') < 0) continue;
									forms[i].addEventListener('submit', function (event) {
										event.preventDefault();	// Textcube-NG: 확인창을 페이지 위 안내 줄의 [확인]/[취소]로 바꿈(r3-17, R-396)
										var form = this;
										tcConfirm(messages.confirmDelete, function () { form.submit(); });
									}, false);
								}
								update();
							})();
							//]]>
							</script>
						</div>
<?php
}
?>
