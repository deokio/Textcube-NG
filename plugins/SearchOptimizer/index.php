<?php
/// Copyright (c) 2004-2016, Needlworks  / Tatter Network Foundation
/// All rights reserved. Licensed under the GPL.
/// See the GNU General Public License for more details. (/documents/LICENSE, /documents/COPYRIGHT)
///
/// SearchOptimizer — 검색 엔진·AI 에이전트용 head 메타(description·Open Graph·Twitter 카드·JSON-LD·robots)와
/// 글 본문의 markdown 대체 표현(/plugin/seo/md?id=<n>, text/markdown)을 낸다.
/// head 는 SKIN_head_end 태그 핸들러(handleTags), markdown 은 /plugin/seo/md 이벤트 리스너(interface/blog/plugin.php)로 받는다.
/// 본문 요약·이미지·markdown 은 공개 글(visibility>=2, draft 0, 비공개 분류 아님, 예약 아님)에만 낸다.
/// 출력은 removeAllTags(library/blog.skin.php)를 거치므로 '#' 을 속성에서는 &#35;, JSON 에서는 \u0023 으로 낸다.

if (!defined('ROOT')) {
	header('HTTP/1.1 403 Forbidden');
	header("Connection: close");
	exit;
}

/// 설정 기본값. select 는 'on'/'off'(selectTreat 가 value "0" 을 빼므로), 검색 제외는 checkbox(켬 true)
function SearchOptimizer_defaults() {
	return array(
		'seoDescription' => 'on',
		'seoDescLength' => '160',
		'seoOpenGraph' => 'on',
		'seoTwitterCard' => 'on',
		'seoJsonLd' => 'on',
		'seoAlternate' => 'on',
		'seoMdCanonical' => 'on',
		'seoMdNoindex' => 'on',
		'seoNoindexSearch' => true,
		'seoNoindexTag' => false,
		'seoNoindexArchive' => false,
		'seoNoindexCategory' => false
	);
}

/// 저장된 설정(문맥 plugin.config)을 허용값으로만 읽는다. 키가 없으면 기본값
function SearchOptimizer_config() {
	$context = Model_Context::getInstance();
	$saved = $context->getProperty('plugin.config');
	if (!is_array($saved)) $saved = array();
	$config = SearchOptimizer_defaults();
	$allowed = array(
		'seoDescription' => array('on', 'off'),
		'seoDescLength' => array('80', '120', '160', '255'),
		'seoOpenGraph' => array('on', 'off'),
		'seoTwitterCard' => array('on', 'off'),
		'seoJsonLd' => array('on', 'off'),
		'seoAlternate' => array('on', 'off'),
		'seoMdCanonical' => array('on', 'off'),
		'seoMdNoindex' => array('on', 'off')
	);
	foreach ($allowed as $key => $values) {
		if (isset($saved[$key]) && is_string($saved[$key]) && in_array($saved[$key], $values, true)) $config[$key] = $saved[$key];
	}
	// checkbox 는 해제하면 '' 로 저장된다(pluginconfig.js). 키가 있으면 빈 문자열이 아닐 때만 켬
	foreach (array('seoNoindexSearch', 'seoNoindexTag', 'seoNoindexArchive', 'seoNoindexCategory') as $key) {
		if (array_key_exists($key, $saved)) $config[$key] = (is_string($saved[$key]) && $saved[$key] !== '');
	}
	return $config;
}

/// preg_replace 실패(null) 때 빈 문자열
function SearchOptimizer_replace($pattern, $replacement, $subject) {
	$result = preg_replace($pattern, $replacement, (string)$subject);
	return is_string($result) ? $result : '';
}

/// 속성값 escape. ENT_HTML5 로 ' 를 &apos; 로 내어 '#' 이 남지 않게 한 뒤 '#' 을 &#35; 로 바꾼다
function SearchOptimizer_h($value) {
	return str_replace('#', '&#35;', htmlspecialchars((string)$value, ENT_QUOTES | ENT_SUBSTITUTE | ENT_HTML5, 'UTF-8'));
}

/// 제어 문자를 지우고 공백을 하나로 줄인다
function SearchOptimizer_clean($text) {
	$text = SearchOptimizer_replace('/[\x00-\x08\x0B\x0C\x0E-\x1F\x7F]/', '', $text);
	return trim(SearchOptimizer_replace('/[ \t\n\r\f]+/', ' ', $text));
}

/// script·style(닫히지 않은 것은 끝까지)과 주석을 지운다
function SearchOptimizer_stripBlocks($html) {
	return SearchOptimizer_replace(array(
		'@<script\b[^>]*>.*?(?:</script[^>]*>|\z)@is',
		'@<style\b[^>]*>.*?(?:</style[^>]*>|\z)@is',
		'@<!--.*?(?:-->|\z)@s'
	), ' ', $html);
}

/// stripHTML 결과 등을 평문으로: 치환자 제거, 엔티티 해석, 제어 문자 제거, 공백 정리
function SearchOptimizer_plainText($text) {
	$text = SearchOptimizer_replace(array('/\[##_.*?_##\]/s', '/\[#M_[^|]*\|[^|]*\|/s'), ' ', $text);
	$text = str_replace('_M#]', ' ', $text);
	$text = html_entity_decode($text, ENT_QUOTES | ENT_HTML5, 'UTF-8');
	$text = str_replace("\xC2\xA0", ' ', $text);
	return SearchOptimizer_clean($text);
}

/// 주소에서 scheme://host[:port] 만. http·https 가 아니면 ''
function SearchOptimizer_origin($url) {
	$parts = parse_url((string)$url);
	if (!is_array($parts) || empty($parts['scheme']) || empty($parts['host'])) return '';
	$scheme = strtolower($parts['scheme']);
	if ($scheme !== 'http' && $scheme !== 'https') return '';
	return $scheme . '://' . $parts['host'] . (isset($parts['port']) ? ':' . (int)$parts['port'] : '');
}

/// JSON-LD script. </script>·따옴표·& 는 \u 로, '#' 은 \u0023 으로 낸다. 인코딩 실패면 ''
function SearchOptimizer_jsonLd($data) {
	$json = json_encode($data, JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_INVALID_UTF8_SUBSTITUTE);
	if (!is_string($json)) return '';
	return '<script type="application/ld+json">' . str_replace('#', '\\u0023', $json) . '</script>';
}

function SearchOptimizer_meta($attribute, $key, $value) {
	return '<meta ' . $attribute . '="' . $key . '" content="' . SearchOptimizer_h($value) . '" />';
}

/// 빈 값('' 이나 빈 배열)을 뺀다
function SearchOptimizer_compact($data) {
	foreach ($data as $key => $value) {
		if ((is_string($value) && $value === '') || (is_array($value) && count($value) === 0)) unset($data[$key]);
	}
	return $data;
}

/// 공개 글: visibility>=2(공개·발행), draft 0, 공지(category -2)이거나 비공개 분류가 아닌 일반 분류(>=0). 예약(음수)·보호(1)·비공개(0)는 아님
function SearchOptimizer_isPublicEntry($entry, $privateCategories) {
	if (!is_array($entry) || !isset($entry['id'], $entry['visibility'], $entry['category'])) return false;
	if (isset($entry['draft']) && (int)$entry['draft'] !== 0) return false;
	if ((int)$entry['visibility'] < 2) return false;
	$category = (int)$entry['category'];
	if ($category === -2) return true;
	if ($category < 0) return false;
	if (!is_array($privateCategories)) $privateCategories = array();
	return !in_array($category, array_map('intval', $privateCategories), true);
}

/// markdown 대체 표현을 내는 글: 공개 글 가운데 일반 분류(공지·페이지·키로그·서식 제외)
function SearchOptimizer_isMarkdownEntry($entry, $privateCategories) {
	return SearchOptimizer_isPublicEntry($entry, $privateCategories) && (int)$entry['category'] >= 0;
}

/// 소유자(doesHaveOwnership: 관리자·글쓴이)에게만 내는 글: 비공개·보호·예약 글과 비공개 분류의 글. 일반 분류(>=0)·draft 0 만.
/// 남의 비공개 글을 읽을 수 있는지는 getEntry 가 정한다(글관리 권한이 없는 구성원은 받지 못함, v3.145)
function SearchOptimizer_isOwnerMarkdownEntry($entry) {
	if (!is_array($entry) || !isset($entry['id'], $entry['visibility'], $entry['category'])) return false;
	if (isset($entry['draft']) && (int)$entry['draft'] !== 0) return false;
	return (int)$entry['category'] >= 0;
}

/// id: 1~2147483647 의 10진 문자열만(앞자리 0·부호·지수·공백·배열 거부)
function SearchOptimizer_validId($raw) {
	if (!is_string($raw) || !preg_match('/^[1-9][0-9]{0,9}\z/', $raw)) return false;
	if (strlen($raw) === 10 && strcmp($raw, '2147483647') > 0) return false;
	return true;
}

function SearchOptimizer_privateCategories($blogid) {
	$list = getCategoryVisibilityList($blogid, 'private');
	return is_array($list) ? $list : array();
}

/// 글 주소(view.php 의 글 주소 관례: useSloganOnPost 이면 entry/<slogan>, 아니면 <id>)
function SearchOptimizer_entryUrl($entry) {
	$context = Model_Context::getInstance();
	$base = rtrim((string)$context->getProperty('uri.default', ''), '/');
	if ($context->getProperty('blog.useSloganOnPost') && isset($entry['slogan']) && (string)$entry['slogan'] !== '')
		return $base . '/entry/' . URL::encode((string)$entry['slogan'], $context->getProperty('service.useEncodedURL'));
	return $base . '/' . (int)$entry['id'];
}

/// 본문을 포매터로 바꾼 HTML
function SearchOptimizer_entryHtml($blogid, $entry) {
	$content = isset($entry['content']) ? (string)$entry['content'] : '';
	$formatter = isset($entry['contentformatter']) ? (string)$entry['contentformatter'] : '';
	$html = formatContent($blogid, (int)$entry['id'], $content, $formatter, array(), false);
	return is_string($html) ? $html : '';
}

/// 본문 평문(설명용)
function SearchOptimizer_entryText($blogid, $entry) {
	return SearchOptimizer_plainText((string)stripHTML(SearchOptimizer_stripBlocks(SearchOptimizer_entryHtml($blogid, $entry))));
}

function SearchOptimizer_lessen($text, $config) {
	return $text === '' ? '' : (string)UTF8::lessen($text, (int)$config['seoDescLength']);
}

/// 공개 글의 첫 첨부 이미지 주소. 없으면 ''
function SearchOptimizer_image($blogid, $entry) {
	$context = Model_Context::getInstance();
	$origin = SearchOptimizer_origin($context->getProperty('uri.default', ''));
	if ($origin === '' || !isset($entry['content'])) return '';
	$names = Utils_Image::getInstance()->getAttachmentExtracts((string)$entry['content'], $blogid);
	if (!is_array($names)) return '';
	foreach ($names as $name) {
		$name = (string)$name;
		if ($name === '' || $name === '.' || $name === '..' || preg_match('/[\x00-\x1F\x7F\/\\\\]/', $name)) continue;
		return $origin . rtrim((string)$context->getProperty('service.path', ''), '/') . '/attach/' . (int)$blogid . '/' . rawurlencode($name);
	}
	return '';
}

/// 글쓴이 주소. 스킨 글쓴이 링크 관례(interface/common/blog/entries.php: <블로그 주소>/author/<rawurlencode(User::getName)>)를 따르고,
/// 기준은 글 주소(SearchOptimizer_entryUrl)와 같은 uri.default. /author 는 이름이 그대로 같아야 찾으므로(interface/blog/author.php) 정리 전 이름을 받는다. 없으면 ''(v3.220)
function SearchOptimizer_authorUrl($name) {
	$name = is_scalar($name) ? (string)$name : '';
	$base = rtrim((string)Model_Context::getInstance()->getProperty('uri.default', ''), '/');
	if ($base === '' || $name === '') return '';
	return $base . '/author/' . rawurlencode($name);
}

/// 블로그 로고 주소(문맥 blog.logo, 스킨 [##_image_##] 관례 library/blog.skin.php). 이름 검사·주소 모양은 SearchOptimizer_image 와 같다. 없으면 ''(v3.220)
function SearchOptimizer_logo($blogid) {
	$context = Model_Context::getInstance();
	$origin = SearchOptimizer_origin($context->getProperty('uri.default', ''));
	$name = $context->getProperty('blog.logo', '');
	$name = is_scalar($name) ? (string)$name : '';
	if ($origin === '' || $name === '' || $name === '.' || $name === '..' || preg_match('/[\x00-\x1F\x7F\/\\\\]/', $name)) return '';
	return $origin . rtrim((string)$context->getProperty('service.path', ''), '/') . '/attach/' . (int)$blogid . '/' . rawurlencode($name);
}

/// 단일 글 화면이면 그 글(interface/index.php·entry.php·notice.php 의 단일 글 조건), 아니면 null
function SearchOptimizer_singleEntry($entries) {
	if (!is_array($entries) || count($entries) !== 1 || !isset($entries[0]) || !is_array($entries[0])) return null;
	$context = Model_Context::getInstance();
	$directive = (string)$context->getProperty('suri.directive', '');
	$id = $context->getProperty('suri.id');
	$value = $context->getProperty('suri.value');
	$value = is_string($value) ? $value : '';
	switch ($directive) {
		case '/':
			if (!empty($id)) return $entries[0];
			// 등록 페이지(interface/freeSlogan.php)가 글 하나를 보여 주면 그 글(ST_Pages entry 종류). 주소는 uri.permalink(등록 페이지 주소)(r3-5)
			return ((string)$context->getProperty('uri.interfacePath', '') === 'interface/freeSlogan.php') ? $entries[0] : null;
		case '/entry':
			return empty($value) ? null : $entries[0];
		case '/notice':
		case '/page':
			return ($id !== null || strlen($value) > 0) ? $entries[0] : null;
	}
	return null;
}

/// 첫 화면: '/' 에 글 번호·값이 없고 1쪽
function SearchOptimizer_isFrontPage() {
	$context = Model_Context::getInstance();
	if ((string)$context->getProperty('suri.directive', '') !== '/') return false;
	$id = $context->getProperty('suri.id');
	$value = $context->getProperty('suri.value');
	if (!empty($id) || (is_string($value) && $value !== '')) return false;
	$page = $context->getProperty('suri.page');
	return $page === true || $page === 1 || $page === '1';
}

/// 목록 화면: 첫 화면이 아니고 글 목록을 내는 directive. '/'·'/entry'·'/notice'·'/page' 는 글 번호·값이 없을 때만
/// ('/' 에 값이 있으면 등록 페이지 interface/freeSlogan.php 다)(v3.217)
function SearchOptimizer_isListPage() {
	if (SearchOptimizer_isFrontPage()) return false;
	$context = Model_Context::getInstance();
	switch ((string)$context->getProperty('suri.directive', '')) {
		case '/category':
		case '/tag':
		case '/archive':
		case '/search':
		case '/author':
			return true;
		case '/':
		case '/entry':
		case '/notice':
		case '/page':
			$id = $context->getProperty('suri.id');
			$value = $context->getProperty('suri.value');
			return empty($id) && !(is_string($value) && $value !== '');
	}
	return false;
}

/// 목록 화면에 글이 하나만 표시되면 그 글, 아니면 null(v3.217)
function SearchOptimizer_listEntry($entries) {
	if (!is_array($entries) || count($entries) !== 1 || !isset($entries[0]) || !is_array($entries[0]) || !isset($entries[0]['id'])) return null;
	return $entries[0];
}

/// 목록 쪽 번호. 표시한 쪽(paging.page), 없으면 suri.page. 1~2147483647 의 10진 값이 아니면 1(v3.217)
function SearchOptimizer_pageNumber() {
	global $paging;
	$page = (is_array($paging) && isset($paging['page'])) ? $paging['page'] : Model_Context::getInstance()->getProperty('suri.page');
	if (is_int($page)) $page = (string)$page;
	// '02' 처럼 앞에 0 이 붙은 쪽 번호도 Paging::fetch(is_numeric)는 2쪽으로 보이므로 0 을 떼고 본다(r3-5)
	if (is_string($page) && preg_match('/^0+[1-9][0-9]*\z/', $page)) $page = ltrim($page, '0');
	return SearchOptimizer_validId($page) ? (int)$page : 1;
}

/// 목록 이름: 분류(손님에게 감춘 분류는 ''), 태그(핸들러가 바꾼 suri 값), 글쓴이(문맥 suri.value. 전역 $author 는 entries.php 가 글마다 덮어씀),
/// 기간(getPeriodLabel), 검색어. 그 밖은 ''(v3.217)
function SearchOptimizer_listName($blogid) {
	global $suri, $category, $period, $search;
	$context = Model_Context::getInstance();
	switch ((string)$context->getProperty('suri.directive', '')) {
		case '/category':
			return is_numeric($category) ? SearchOptimizer_clean(getCategoryLabelById($blogid, (int)$category)) : '';
		case '/tag':
			return (isset($suri['value']) && is_string($suri['value'])) ? SearchOptimizer_clean($suri['value']) : '';
		case '/author':
			$value = $context->getProperty('suri.value');
			return is_string($value) ? SearchOptimizer_clean($value) : '';
		case '/archive':
			return (isset($period) && is_scalar($period)) ? SearchOptimizer_clean(getPeriodLabel($period)) : '';
		case '/search':
			return is_string($search) ? SearchOptimizer_clean($search) : '';
	}
	return '';
}

/// 목록 화면 og:title: '<목록 이름> - <블로그명>'(한쪽이 비면 다른 쪽), 2쪽부터 ' (N쪽)'. 둘 다 비면 ''(v3.217)
function SearchOptimizer_listTitle($blogid, $siteName) {
	$name = SearchOptimizer_listName($blogid);
	$title = ($name !== '' && $siteName !== '') ? $name . ' - ' . $siteName : $name . $siteName;
	if ($title === '') return '';
	$page = SearchOptimizer_pageNumber();
	return $page > 1 ? $title . ' (' . _f('%1쪽', $page) . ')' : $title;
}

/// 목록에 표시한 공개 글 제목을 ' · ' 로 잇는다. 없으면 ''(v3.217)
function SearchOptimizer_listTitles($blogid, $entries) {
	if (!is_array($entries)) return '';
	$privates = SearchOptimizer_privateCategories($blogid);
	$titles = array();
	foreach ($entries as $entry) {
		if (!SearchOptimizer_isPublicEntry($entry, $privates)) continue;
		$title = SearchOptimizer_clean(isset($entry['title']) ? $entry['title'] : '');
		if ($title !== '') $titles[] = $title;
	}
	return implode(' · ', $titles);
}

/// 이 플러그인이 head 에 낸 description 메타. 인자가 있으면 기록하고, 기록한 값을 돌려준다(SearchOptimizer_obEnd 의 중복 제거 기준)(r3-5)
function SearchOptimizer_emittedDescription($meta = null) {
	static $emitted = null;
	if ($meta !== null) $emitted = (string)$meta;
	return $emitted;
}

/// name="description" 메타 태그인가. 속성은 따옴표 값까지 한 덩어리로 읽고, 겹친 name 속성은 HTML 과 같이 앞의 것만 본다(r3-5)
function SearchOptimizer_isDescriptionMeta($tag) {
	if (!preg_match_all('@\s([^\s"\'<>/=]+)(?:\s*=\s*(?:"([^"]*)"|\'([^\']*)\'|([^\s"\'=<>`]+)))?@', (string)$tag, $matches, PREG_SET_ORDER)) return false;
	foreach ($matches as $match) {
		if (strtolower($match[1]) !== 'name') continue;
		$value = '';
		for ($i = 2; $i <= 4; $i++) {
			if (isset($match[$i]) && $match[$i] !== '') $value = $match[$i];
		}
		return strtolower(trim($value)) === 'description';
	}
	return false;
}

/// head 문자열에서 script 요소·주석·meta 태그를 앞에서부터 차례로 찾아, 찾은 문자열을 SearchOptimizer_dedupeTag(찾은 문자열, $ours, $kept)의 값으로 바꾼 문자열을 돌려준다(r3-5)
/// 변수 함수 호출($callback(...))은 플러그인 안전 검사가 '판정 불가 호출'로 세어 PDO 모드에서 이 플러그인을 막으므로, 이름 있는 함수를 바로 부른다(r3-14)
/// 찾는 규칙은 정규식 @<script\b[^>]*>.*?(?:</script\s*>|\z)|<!--.*?(?:-->|\z)|<meta\b(?:[^>"']++|"[^"]*+"|'[^']*+')*+>@is 와 같다
/// (ASCII 대소문자 무시. 닫히지 않은 script·주석은 끝까지. meta 는 따옴표 값을 한 덩어리로 읽어 > 에서 끝나고, 따옴표가 닫히지 않거나 > 가 없으면 태그로 보지 않는다).
/// 정규식은 닫히지 않은 <meta 가 많으면 처리 시간이 길이의 제곱으로 늘어(1MB 에 수 분) head 를 한 번 훑는 코드로 바꿨다. 시간은 길이에 비례한다(r3-5)
function SearchOptimizer_replaceHeadTags($head, $ours, &$kept) {
	$head = (string)$head;
	$n = strlen($head);
	$lower = strtr($head, 'ABCDEFGHIJKLMNOPQRSTUVWXYZ', 'abcdefghijklmnopqrstuvwxyz');
	$lastGt = strrpos($head, '>');
	$word = 'abcdefghijklmnopqrstuvwxyz0123456789_';	// \b 판정용 낱말 글자(소문자 사본에서 본다)
	$seen = null;	// meta 를 읽다가 따옴표 밖에서 지난 따옴표 자리. 뒤의 meta 가 같은 자리에 오면 앞의 meta 와 같은 길로 실패하므로 바로 실패로 본다
	$runFrom = -1;
	$runTo = -1;	// [$runFrom, $runTo) 에는 > " ' 가 없다($runTo 는 그 뒤 첫 > " ' 자리나 끝)
	$out = '';
	$done = 0;
	$p = 0;
	while ($p < $n) {
		$p = strpos($head, '<', $p);
		if ($p === false) break;
		$end = -1;
		if (substr($lower, $p, 7) === '<script' && ($p + 7 >= $n || strspn($lower, $word, $p + 7, 1) === 0)) {
			// 여는 태그의 > 뒤 처음 나오는 </script(공백)> 까지, 없으면 끝까지. 여는 태그의 > 가 없으면 태그가 아니다
			if ($lastGt !== false && $lastGt >= $p + 7) {
				$i = strpos($head, '>', $p + 7) + 1;
				$end = $n;
				while (($q = strpos($lower, '</script', $i)) !== false) {
					$k = $q + 8;
					if ($k < $n) $k += strspn($head, " \t\n\x0B\f\r", $k);
					if ($k < $n && $head[$k] === '>') {
						$end = $k + 1;
						break;
					}
					$i = $q + 1;
				}
			}
		} else if (substr($head, $p, 4) === '<!--') {
			$q = strpos($head, '-->', $p + 4);
			$end = ($q === false) ? $n : $q + 3;
		} else if (substr($lower, $p, 5) === '<meta' && ($p + 5 >= $n || strspn($lower, $word, $p + 5, 1) === 0)) {
			$i = $p + 5;
			if ($i < $runFrom || $i > $runTo) {
				$runFrom = $i;
				$runTo = ($i < $n) ? $i + strcspn($head, ">\"'", $i) : $n;
			}
			$i = $runTo;
			while ($i < $n) {
				$c = $head[$i];
				if ($c === '>') {
					$end = $i + 1;
					break;
				}
				if ($seen === null) $seen = str_repeat("\0", $n);
				else if ($seen[$i] === "\1") break;
				$seen[$i] = "\1";
				$j = strpos($head, $c, $i + 1);
				if ($j === false) break;
				$i = $j + 1;
				if ($i < $n) $i += strcspn($head, ">\"'", $i);
			}
		}
		if ($end < 0) {
			$p++;
			continue;
		}
		$out .= substr($head, $done, $p - $done) . SearchOptimizer_dedupeTag(substr($head, $p, $end - $p), $ours, $kept);
		$done = $p = $end;
	}
	return $out . substr($head, $done);
}

/// SearchOptimizer_replaceHeadTags 가 찾은 문자열 하나를 처리한다. description 메타가 아니면 그대로 두고, 이 플러그인이 낸 메타와 같은 첫 것은
/// 남기며($kept 를 참으로), 나머지 description 메타는 지운다(r3-14, r3-5 의 익명 함수 본문을 이름 있는 함수로 옮김)
function SearchOptimizer_dedupeTag($tag, $ours, &$kept) {
	if (strncasecmp($tag, '<meta', 5) !== 0 || !SearchOptimizer_isDescriptionMeta($tag)) return $tag;
	if (!$kept && $tag === $ours) {
		$kept = true;
		return $tag;
	}
	return '';
}

/// 스킨이 넣은 description 메타를 뺀다. 이 플러그인이 낸 메타가 </head> 앞에 그대로 있을 때만 그것 하나를 남기고
/// </head> 앞의 나머지 name="description" 메타를 지운다(script·주석 안은 건드리지 않는다). 처리하지 못하면 받은 그대로 돌려준다(r3-5)
function SearchOptimizer_dedupeDescription($html) {
	$ours = SearchOptimizer_emittedDescription();
	if (!is_string($html) || !is_string($ours) || $ours === '') return $html;
	$headEnd = stripos($html, '</head>');
	if ($headEnd === false) return $html;
	$head = substr($html, 0, $headEnd);
	if (strpos($head, $ours) === false) return $html;
	$kept = false;
	$result = SearchOptimizer_replaceHeadTags($head, $ours, $kept);
	if (!is_string($result) || !$kept) return $html;
	return $result . substr($html, $headEnd);
}

/// OBStart·OBEnd 짝마다 연 출력 버퍼 깊이(열지 않았으면 0). 인자가 있으면 넣고, 없으면 마지막 것을 꺼낸다(겹쳐 불려도 짝을 맞춘다)(r3-5)
function SearchOptimizer_obLevel($level = null) {
	static $levels = array();
	if ($level === null) return array_pop($levels);
	$levels[] = (int)$level;
	return (int)$level;
}

/// OBStart 리스너(블로그 처리기가 화면 출력 앞에서 부른다): description 을 넣는 설정이면 출력 버퍼를 연다. 관리 화면(/owner/)은 열지 않는다(r3-5)
function SearchOptimizer_obStart($target = null, $mother = null) {
	$directive = (string)Model_Context::getInstance()->getProperty('suri.directive', '');
	$config = SearchOptimizer_config();
	if (strncmp($directive, '/owner/', 7) !== 0 && $config['seoDescription'] === 'on' && ob_start()) SearchOptimizer_obLevel(ob_get_level());
	else SearchOptimizer_obLevel(0);
	return $target;
}

/// OBEnd 리스너: 짝인 OBStart 가 연 버퍼가 맨 위에 있을 때만 닫고, 스킨의 description 메타 중복을 뺀 화면을 낸다. 그 밖에는 손대지 않는다(r3-5)
function SearchOptimizer_obEnd($target = null, $mother = null) {
	$level = SearchOptimizer_obLevel();
	if (!is_int($level) || $level < 1 || ob_get_level() !== $level) return $target;
	$html = ob_get_clean();
	if (is_string($html)) echo SearchOptimizer_dedupeDescription($html);
	return $target;
}

/// SKIN_head_end 태그 핸들러
function SearchOptimizer_head($target) {
	global $entries;
	$context = Model_Context::getInstance();
	$directive = (string)$context->getProperty('suri.directive', '');
	if (strncmp($directive, '/owner/', 7) === 0) return $target;	// 미리보기 등 관리 화면
	$config = SearchOptimizer_config();
	$blogid = getBlogId();
	$siteName = SearchOptimizer_clean($context->getProperty('blog.title', ''));
	$baseUrl = (string)$context->getProperty('uri.default', '');
	$lines = array();
	$entry = SearchOptimizer_singleEntry(isset($entries) ? $entries : null);
	$isList = !is_array($entry) && SearchOptimizer_isListPage();
	// 목록 화면에 글이 하나만 표시되면 그 글의 메타를 낸다. 주소는 목록 주소가 아니라 글 주소(v3.217)
	$listEntry = $isList ? SearchOptimizer_listEntry(isset($entries) ? $entries : null) : null;
	if (is_array($listEntry)) $entry = $listEntry;
	if (is_array($entry)) {
		$title = SearchOptimizer_clean(isset($entry['title']) ? $entry['title'] : '');
		$privates = SearchOptimizer_privateCategories($blogid);
		if (SearchOptimizer_isPublicEntry($entry, $privates)) {
			$permalink = is_array($listEntry) ? SearchOptimizer_entryUrl($entry) : (string)$context->getProperty('uri.permalink', '');
			$description = SearchOptimizer_lessen(SearchOptimizer_entryText($blogid, $entry), $config);
			$image = SearchOptimizer_image($blogid, $entry);
			$published = isset($entry['published']) ? (int)$entry['published'] : 0;
			$modified = isset($entry['modified']) ? (int)$entry['modified'] : 0;
			if ($config['seoDescription'] === 'on' && $description !== '') $lines[] = SearchOptimizer_emittedDescription(SearchOptimizer_meta('name', 'description', $description));	// 스킨 description 메타 중복 제거(SearchOptimizer_obEnd)의 기준으로 기록(r3-5)
			if ($config['seoOpenGraph'] === 'on') {
				$lines[] = SearchOptimizer_meta('property', 'og:type', 'article');
				if ($title !== '') $lines[] = SearchOptimizer_meta('property', 'og:title', $title);
				if ($permalink !== '') $lines[] = SearchOptimizer_meta('property', 'og:url', $permalink);
				if ($siteName !== '') $lines[] = SearchOptimizer_meta('property', 'og:site_name', $siteName);
				if ($description !== '') $lines[] = SearchOptimizer_meta('property', 'og:description', $description);
				if ($image !== '') $lines[] = SearchOptimizer_meta('property', 'og:image', $image);
				if ($published > 0) $lines[] = SearchOptimizer_meta('property', 'article:published_time', date('c', $published));
			}
			if ($config['seoTwitterCard'] === 'on') $lines[] = SearchOptimizer_meta('name', 'twitter:card', $image !== '' ? 'summary_large_image' : 'summary');
			if ($config['seoJsonLd'] === 'on') {
				$author = array();
				if (isset($entry['userid']) && (int)$entry['userid'] > 0) {
					// 글쓴이 주소(url)는 스킨 글쓴이 링크와 같은 /author/<이름>. 이름이 있을 때만 내고, 주소에는 정리 전 이름을 쓴다(v3.220)
					$rawName = User::getName((int)$entry['userid']);
					$name = SearchOptimizer_clean($rawName);
					if ($name !== '') $author = SearchOptimizer_compact(array('@type' => 'Person', 'name' => $name, 'url' => SearchOptimizer_authorUrl($rawName)));
				}
				// 발행처 로고(publisher.logo)는 블로그 로고가 있을 때만 ImageObject 로 낸다. 없으면 compact 가 빼므로 전과 같다(v3.220)
				$logo = SearchOptimizer_logo($blogid);
				$publisher = SearchOptimizer_compact(array('@type' => 'Organization', 'name' => $siteName, 'url' => $baseUrl, 'logo' => $logo !== '' ? array('@type' => 'ImageObject', 'url' => $logo) : array()));
				$script = SearchOptimizer_jsonLd(SearchOptimizer_compact(array(
					'@context' => 'https://schema.org',
					'@type' => 'BlogPosting',
					'headline' => $title,
					'url' => $permalink,
					'mainEntityOfPage' => $permalink,
					'datePublished' => $published > 0 ? date('c', $published) : '',
					'dateModified' => $modified > 0 ? date('c', $modified) : '',
					'author' => $author,
					'publisher' => count($publisher) > 1 ? $publisher : array(),
					'description' => $description,
					'image' => $image
				)));
				if ($script !== '') $lines[] = $script;
			}
			// markdown 대체 표현: 주소 재작성(fancyURL 2)일 때만. mode=desktop 은 begin.php 의 모드 전환 주소 관례
			if ($config['seoAlternate'] === 'on' && SearchOptimizer_isMarkdownEntry($entry, $privates) && (int)$context->getProperty('service.fancyURL') === 2)
				$lines[] = '<link rel="alternate" type="text/markdown" href="' . SearchOptimizer_h(rtrim($baseUrl, '/') . '/plugin/seo/md?id=' . (int)$entry['id'] . '&mode=desktop') . '" />';
		} else if ($config['seoOpenGraph'] === 'on') {
			// 보호·비공개 글: 본문에서 나온 값은 내지 않는다
			if ($title !== '') $lines[] = SearchOptimizer_meta('property', 'og:title', $title);
			if ($siteName !== '') $lines[] = SearchOptimizer_meta('property', 'og:site_name', $siteName);
		}
	} else {
		// 목록 화면에 글이 여럿이면 제목은 '<목록 이름> - <블로그명>'(2쪽부터 쪽 번호), 설명은 표시한 공개 글 제목(없으면 블로그 설명)(v3.217)
		// 글을 표시하지 않은 목록(목록만 보기·빈 결과 등)도 이름(분류·태그·기간·검색어·글쓴이)이 있으면 같은 제목을 내고, 설명은 블로그 설명이다.
		// 이름이 없으면(태그 구름, cover·line 첫 화면의 '/?page=2') 지금처럼 블로그명이다(v3.217)
		$isMulti = $isList && isset($entries) && is_array($entries) && count($entries) >= 2;
		$titles = $isMulti ? SearchOptimizer_listTitles($blogid, $entries) : '';
		$description = SearchOptimizer_lessen($titles !== '' ? $titles : SearchOptimizer_plainText($context->getProperty('blog.description', '')), $config);
		$pageTitle = ($isMulti || ($isList && SearchOptimizer_listName($blogid) !== '')) ? SearchOptimizer_listTitle($blogid, $siteName) : $siteName;
		$front = SearchOptimizer_isFrontPage();
		if ($config['seoDescription'] === 'on' && $description !== '') $lines[] = SearchOptimizer_emittedDescription(SearchOptimizer_meta('name', 'description', $description));	// 스킨 description 메타 중복 제거(SearchOptimizer_obEnd)의 기준으로 기록(r3-5)
		if ($config['seoOpenGraph'] === 'on') {
			$lines[] = SearchOptimizer_meta('property', 'og:type', 'website');
			if ($pageTitle !== '') $lines[] = SearchOptimizer_meta('property', 'og:title', $pageTitle);
			if ($siteName !== '') $lines[] = SearchOptimizer_meta('property', 'og:site_name', $siteName);
			if ($front && $baseUrl !== '') $lines[] = SearchOptimizer_meta('property', 'og:url', $baseUrl);
			if ($description !== '') $lines[] = SearchOptimizer_meta('property', 'og:description', $description);
		}
		if ($front && $config['seoJsonLd'] === 'on') {
			$script = SearchOptimizer_jsonLd(SearchOptimizer_compact(array(
				'@context' => 'https://schema.org',
				'@type' => 'WebSite',
				'name' => $siteName,
				'url' => $baseUrl,
				'description' => $description
			)));
			if ($script !== '') $lines[] = $script;
		}
	}
	// 목록 화면에 글이 하나여서 글 메타를 낸 때도 목록 directive 의 noindex 는 그대로 낸다(v3.217)
	$noindex = array('/search' => 'seoNoindexSearch', '/tag' => 'seoNoindexTag', '/archive' => 'seoNoindexArchive', '/category' => 'seoNoindexCategory');
	if (isset($noindex[$directive]) && $config[$noindex[$directive]]) $lines[] = SearchOptimizer_meta('name', 'robots', 'noindex,follow');
	if (count($lines) === 0) return $target;
	return $target . implode("\n", $lines) . "\n";
}

function SearchOptimizer_notFound() {
	Respond::NotFoundPage();
	exit;
}

/// /plugin/seo/md?id=<n> 리스너: 공개 일반 글은 text/markdown 으로 낸다. 비공개·보호·예약 글과 비공개 분류의 글은
/// 소유자에게만 X-Robots-Tag: noindex 를 붙여 내고(캐시 금지 헤더는 session_start 기본값), 손님에게는 404
function SearchOptimizer_markdown($target = null, $mother = null) {
	$config = SearchOptimizer_config();
	if ($config['seoAlternate'] !== 'on') SearchOptimizer_notFound();
	$raw = isset($_GET['id']) ? $_GET['id'] : null;
	if (!SearchOptimizer_validId($raw)) SearchOptimizer_notFound();
	$blogid = getBlogId();
	$entry = getEntry($blogid, (int)$raw);
	$public = is_array($entry) && SearchOptimizer_isMarkdownEntry($entry, SearchOptimizer_privateCategories($blogid));
	$ownerOnly = !$public && doesHaveOwnership() && SearchOptimizer_isOwnerMarkdownEntry($entry);
	if (!$public && !$ownerOnly) SearchOptimizer_notFound();
	$context = Model_Context::getInstance();
	$permalink = SearchOptimizer_replace('/[\x00-\x20\x7F<>]/', '', SearchOptimizer_entryUrl($entry));
	$origin = SearchOptimizer_origin($context->getProperty('uri.default', ''));
	$title = SearchOptimizer_mdHeading(SearchOptimizer_clean(isset($entry['title']) ? $entry['title'] : ''));
	$published = isset($entry['published']) ? (int)$entry['published'] : 0;
	$body = SearchOptimizer_htmlToMarkdown(SearchOptimizer_entryHtml($blogid, $entry), $origin);
	$markdown = rtrim('# ' . $title) . "\n\n";
	if ($permalink !== '') $markdown .= 'Source: <' . $permalink . ">  \n";
	if ($published > 0) $markdown .= 'Published: ' . date('c', $published) . "\n";
	$markdown .= "\n" . $body;
	header('Content-Type: text/markdown; charset=utf-8');
	header('X-Content-Type-Options: nosniff');
	if ($config['seoMdCanonical'] === 'on' && $permalink !== '') header('Link: <' . $permalink . '>; rel="canonical"');
	if ($ownerOnly || $config['seoMdNoindex'] === 'on') header('X-Robots-Tag: noindex');
	print $markdown;
	exit;
}

/* ---- HTML → markdown (외부 의존 없음) ----
 * h1~h6, p, br, a, img, ul/ol/li, strong/b, em/i, code/pre, blockquote 만 바꾸고 나머지 태그는 내용만 낸다.
 * 흐름 문자열 안에서 "\x01" 은 블록 경계, "\x02" 는 줄바꿈(br), "\x03N\x03" 은 완성된 블록 N 의 자리다.
 * 글 본문의 제어 문자는 읽을 때 지우므로 이 표지와 섞이지 않는다.
 */

function SearchOptimizer_voidTags() {
	return array('area', 'base', 'br', 'col', 'embed', 'hr', 'img', 'input', 'link', 'meta', 'param', 'source', 'track', 'wbr');
}

/// 열리면 열린 p 를 닫는 블록 태그
function SearchOptimizer_blockTags() {
	return array('address', 'article', 'aside', 'blockquote', 'caption', 'center', 'dd', 'details', 'div', 'dl', 'dt', 'fieldset', 'figcaption', 'figure', 'footer', 'form', 'h1', 'h2', 'h3', 'h4', 'h5', 'h6', 'header', 'hr', 'li', 'main', 'nav', 'ol', 'p', 'pre', 'section', 'summary', 'table', 'tbody', 'td', 'tfoot', 'th', 'thead', 'tr', 'ul');
}

/// 엔티티를 풀고 제어 문자(\t \n \r 제외)를 지운다
function SearchOptimizer_decode($text) {
	return SearchOptimizer_replace('/[\x00-\x08\x0B\x0C\x0E-\x1F\x7F]/', '', html_entity_decode((string)$text, ENT_QUOTES | ENT_HTML5, 'UTF-8'));
}

function SearchOptimizer_parseAttributes($raw) {
	$attributes = array();
	if (preg_match_all('/([^ \t\n\r\f\/>"\'=]+)(?:[ \t\n\r\f]*=[ \t\n\r\f]*(?:"([^"]*)"|\'([^\']*)\'|([^ \t\n\r\f>"\']+)))?/', (string)$raw, $matches, PREG_SET_ORDER)) {
		foreach ($matches as $match) {
			$key = strtolower($match[1]);
			if (isset($attributes[$key])) continue;
			$value = '';
			if (isset($match[4]) && $match[4] !== '') $value = $match[4];
			else if (isset($match[3]) && $match[3] !== '') $value = $match[3];
			else if (isset($match[2])) $value = $match[2];
			$attributes[$key] = SearchOptimizer_decode($value);
		}
	}
	return $attributes;
}

function SearchOptimizer_addText(&$nodes, $stack, $raw) {
	$text = SearchOptimizer_decode($raw);
	if ($text === '') return;
	$index = count($nodes);
	$nodes[$index] = array('text' => $text);
	$nodes[$stack[count($stack) - 1]]['children'][] = $index;
}

function SearchOptimizer_openTag(&$nodes, &$stack, $name, $attributes) {
	if (in_array($name, SearchOptimizer_blockTags(), true)) {
		for ($k = count($stack) - 1; $k > 0; $k--) {
			if ($nodes[$stack[$k]]['tag'] === 'p') {
				array_splice($stack, $k);
				break;
			}
		}
	}
	if ($name === 'li') {
		for ($k = count($stack) - 1; $k > 0; $k--) {
			$tag = $nodes[$stack[$k]]['tag'];
			if ($tag === 'li') {
				array_splice($stack, $k);
				break;
			}
			if ($tag === 'ul' || $tag === 'ol') break;
		}
	}
	if (count($stack) >= 64) return;	// 깊이 상한: 더 깊은 태그는 버리고 내용만 둔다
	$index = count($nodes);
	$nodes[$index] = array('tag' => $name, 'attributes' => $attributes, 'children' => array());
	$nodes[$stack[count($stack) - 1]]['children'][] = $index;
	if (!in_array($name, SearchOptimizer_voidTags(), true)) $stack[] = $index;
}

function SearchOptimizer_closeTag($nodes, &$stack, $name) {
	for ($k = count($stack) - 1; $k > 0; $k--) {
		if ($nodes[$stack[$k]]['tag'] === $name) {
			array_splice($stack, $k);
			return;
		}
	}
}

/// 노드 배열(0 은 뿌리). 원소: array('tag','attributes','children') 또는 array('text')
function SearchOptimizer_parseHtml($html) {
	$html = (string)$html;
	$nodes = array(array('tag' => '#root', 'attributes' => array(), 'children' => array()));
	$stack = array(0);
	if (!preg_match_all('/<(\/?)([A-Za-z][A-Za-z0-9]*)((?:[^>"\']|"[^"]*"|\'[^\']*\')*)>/', $html, $matches, PREG_OFFSET_CAPTURE | PREG_SET_ORDER)) $matches = array();
	$position = 0;
	foreach ($matches as $match) {
		$offset = $match[0][1];
		if ($offset > $position) SearchOptimizer_addText($nodes, $stack, substr($html, $position, $offset - $position));
		$position = $offset + strlen($match[0][0]);
		$name = strtolower($match[2][0]);
		if ($match[1][0] === '/') SearchOptimizer_closeTag($nodes, $stack, $name);
		else SearchOptimizer_openTag($nodes, $stack, $name, SearchOptimizer_parseAttributes($match[3][0]));
	}
	if ($position < strlen($html)) SearchOptimizer_addText($nodes, $stack, substr($html, $position));
	return $nodes;
}

/// markdown 본문 글자 escape
function SearchOptimizer_mdText($text) {
	$text = str_replace("\xC2\xA0", ' ', (string)$text);
	$text = SearchOptimizer_replace('/[ \t\n\r\f]+/', ' ', $text);
	$text = str_replace('\\', '\\\\', $text);
	$text = SearchOptimizer_replace('/([`*_\[\]])/', '\\\\$1', $text);
	$text = SearchOptimizer_replace('/&(?=#?[A-Za-z0-9]+;)/', '&amp;', $text);
	return str_replace('<', '&lt;', $text);
}

/// 제목 글: escape 하고 끝의 '#' 묶음(닫는 표지로 읽힘)을 escape
function SearchOptimizer_mdHeading($text) {
	$text = SearchOptimizer_mdText($text);
	return SearchOptimizer_replace('/(^| )(#+)$/', '$1\\\\$2', $text);
}

/// 줄 머리에서 블록 문법으로 읽히는 글자를 escape
function SearchOptimizer_lineEscape($line) {
	return SearchOptimizer_replace(array(
		'/^(#{1,6})(?= |$)/',
		'/^>/',
		'/^([-+])(?= |$)/',
		'/^(=+|-+) *$/',
		'/^(~{3,})/',
		'/^([0-9]{1,9})([.)])(?= |$)/'
	), array('\\\\$1', '\\\\>', '\\\\$1', '\\\\$1', '\\\\$1', '$1\\\\$2'), $line);
}

/// 주소 정화: http·https·mailto 와 '/' 로 시작하는 주소만(origin 을 붙임). 나머지는 ''
function SearchOptimizer_mdUrl($url, $origin) {
	$url = trim((string)$url, "\x00..\x20\x7F");
	$url = SearchOptimizer_replace('/[\x00-\x1F\x7F]/', '', $url);
	if ($url === '') return '';
	$origin = (string)$origin;
	if (substr($url, 0, 2) === '//') {
		if ($origin === '') return '';
		$url = substr($origin, 0, strpos($origin, ':')) . ':' . $url;
	} else if (preg_match('/^([A-Za-z][A-Za-z0-9+.\-]*):/', $url, $match)) {
		$scheme = strtolower($match[1]);
		if (!in_array($scheme, array('http', 'https', 'mailto'), true)) return '';
		$url = $scheme . substr($url, strlen($match[1]));
	} else if ($url[0] === '/') {
		if ($origin === '') return '';
		$url = $origin . $url;
	} else {
		return '';
	}
	$url = str_replace(array(' ', '(', ')', '<', '>', '"', '\\'), array('%20', '%28', '%29', '%3C', '%3E', '%22', '%5C'), $url);
	return SearchOptimizer_replace('/&(?=#?[A-Za-z0-9]+;)/', '&amp;', $url);
}

/// 완성된 블록을 맡기고 자리 표지를 돌려준다
function SearchOptimizer_block($text, &$context) {
	$index = count($context['blocks']);
	$context['blocks'][$index] = $text;
	return "\x01\x03" . $index . "\x03\x01";
}

/// 문단 하나: 공백 정리, br 정리, 줄 머리 escape. br 이 둘 이상 이어지면 문단을 나눈다
function SearchOptimizer_paragraph($text) {
	$text = str_replace("\x03", '', $text);
	$text = SearchOptimizer_replace('/[ \t\n\r\f]+/', ' ', $text);
	$text = SearchOptimizer_replace('/ *\x02 */', "\x02", $text);
	$text = trim($text, " \x02");
	if ($text === '') return '';
	$paragraphs = array();
	foreach (preg_split('/\x02{2,}/', $text) as $part) {
		$lines = array();
		foreach (explode("\x02", $part) as $line) $lines[] = SearchOptimizer_lineEscape($line);
		$paragraphs[] = implode("  \n", $lines);
	}
	return implode("\n\n", $paragraphs);
}

/// 흐름 문자열을 블록 경계로 나눠 문단·완성 블록을 빈 줄로 잇는다
function SearchOptimizer_container($flow, $context) {
	$out = array();
	foreach (explode("\x01", $flow) as $part) {
		$trimmed = trim($part, " \t\n\r\f\x02");
		if ($trimmed === '') continue;
		if (preg_match('/^\x03([0-9]+)\x03$/', $trimmed, $match)) {
			$index = (int)$match[1];
			if (isset($context['blocks'][$index]) && $context['blocks'][$index] !== '') $out[] = $context['blocks'][$index];
			continue;
		}
		$paragraph = SearchOptimizer_paragraph($part);
		if ($paragraph !== '') $out[] = $paragraph;
	}
	return implode("\n\n", $out);
}

/// 하위 글자를 그대로 모은다(pre·code). br 은 pre 에서 줄바꿈, code 에서 공백
function SearchOptimizer_rawText($nodes, $index, $pre) {
	$out = '';
	foreach ($nodes[$index]['children'] as $child) {
		if (isset($nodes[$child]['text'])) $out .= $nodes[$child]['text'];
		else if ($nodes[$child]['tag'] === 'br') $out .= $pre ? "\n" : ' ';
		else $out .= SearchOptimizer_rawText($nodes, $child, $pre);
	}
	return $out;
}

function SearchOptimizer_longestRun($text, $character) {
	$longest = 0;
	if (preg_match_all('/' . preg_quote($character, '/') . '+/', $text, $matches)) {
		foreach ($matches[0] as $run) $longest = max($longest, strlen($run));
	}
	return $longest;
}

/// 안쪽 앞뒤 공백을 밖으로 빼고 표지로 감싼다. 블록이 섞이면 감싸지 않는다
function SearchOptimizer_wrap($inner, $marker) {
	if (strpos($inner, "\x01") !== false || strpos($inner, "\x03") !== false) return $inner;
	if (!preg_match('/^([ \x02]*)(.*?)([ \x02]*)$/s', $inner, $match) || $match[2] === '') return $inner;
	return $match[1] . $marker . $match[2] . $marker . $match[3];
}

function SearchOptimizer_renderChildren($nodes, $index, &$context) {
	$out = '';
	foreach ($nodes[$index]['children'] as $child) $out .= SearchOptimizer_renderNode($nodes, $child, $context);
	return $out;
}

function SearchOptimizer_renderList($nodes, $index, &$context) {
	$node = $nodes[$index];
	$ordered = ($node['tag'] === 'ol');
	$flows = array();
	foreach ($node['children'] as $child) {
		if (isset($nodes[$child]['tag']) && $nodes[$child]['tag'] === 'li') {
			$flows[] = SearchOptimizer_renderChildren($nodes, $child, $context);
			continue;
		}
		$rendered = SearchOptimizer_renderNode($nodes, $child, $context);
		if (trim($rendered, " \t\n\r\f\x01\x02") === '') continue;
		if (count($flows) === 0) $flows[] = $rendered;	// li 없이 시작한 내용은 항목 하나로
		else $flows[count($flows) - 1] .= "\x01" . $rendered . "\x01";	// li 밖의 중첩 목록 등은 앞 항목에 붙인다
	}
	$items = array();
	foreach ($flows as $flow) {
		$text = SearchOptimizer_container($flow, $context);
		if ($text !== '') $items[] = $text;
	}
	if (count($items) === 0) return "\x01";
	$number = 1;
	if ($ordered && isset($node['attributes']['start']) && preg_match('/^[0-9]{1,9}$/', $node['attributes']['start'])) $number = (int)$node['attributes']['start'];
	if ($number + count($items) > 999999999) $number = 1;
	$out = array();
	foreach ($items as $item) {
		$marker = $ordered ? ($number++) . '. ' : '- ';
		$indent = str_repeat(' ', strlen($marker));
		$lines = explode("\n", $item);
		foreach ($lines as $k => $line) $lines[$k] = ($k === 0) ? $marker . $line : ($line === '' ? '' : $indent . $line);
		$out[] = implode("\n", $lines);
	}
	return SearchOptimizer_block(implode("\n", $out), $context);
}

function SearchOptimizer_renderNode($nodes, $index, &$context) {
	$node = $nodes[$index];
	if (isset($node['text'])) return SearchOptimizer_mdText($node['text']);
	$tag = $node['tag'];
	switch ($tag) {
		case 'br':
			return "\x02";
		case 'hr':
			return "\x01";
		case 'h1':
		case 'h2':
		case 'h3':
		case 'h4':
		case 'h5':
		case 'h6':
			$inner = SearchOptimizer_renderChildren($nodes, $index, $context);
			if (strpos($inner, "\x03") !== false) return "\x01" . $inner . "\x01";
			$text = trim(SearchOptimizer_replace('/[ \x01\x02]+/', ' ', $inner));
			if ($text === '') return "\x01";
			return SearchOptimizer_block(str_repeat('#', (int)substr($tag, 1)) . ' ' . SearchOptimizer_replace('/(^| )(#+)$/', '$1\\\\$2', $text), $context);
		case 'ul':
		case 'ol':
			return SearchOptimizer_renderList($nodes, $index, $context);
		case 'blockquote':
			$inner = SearchOptimizer_container(SearchOptimizer_renderChildren($nodes, $index, $context), $context);
			if ($inner === '') return "\x01";
			$lines = explode("\n", $inner);
			foreach ($lines as $k => $line) $lines[$k] = ($line === '') ? '>' : '> ' . $line;
			return SearchOptimizer_block(implode("\n", $lines), $context);
		case 'pre':
			$text = str_replace(array("\r\n", "\r"), "\n", SearchOptimizer_rawText($nodes, $index, true));
			if (substr($text, 0, 1) === "\n") $text = substr($text, 1);
			$text = rtrim($text, " \t\n");
			if ($text === '') return "\x01";
			$fence = str_repeat('`', max(3, SearchOptimizer_longestRun($text, '`') + 1));
			return SearchOptimizer_block($fence . "\n" . $text . "\n" . $fence, $context);
		case 'code':
			$text = trim(SearchOptimizer_replace('/[ \t\n\r\f]+/', ' ', str_replace("\xC2\xA0", ' ', SearchOptimizer_rawText($nodes, $index, false))));
			if ($text === '') return '';
			$ticks = str_repeat('`', SearchOptimizer_longestRun($text, '`') + 1);
			$pad = (substr($text, 0, 1) === '`' || substr($text, -1) === '`') ? ' ' : '';
			return $ticks . $pad . $text . $pad . $ticks;
		case 'strong':
		case 'b':
			return SearchOptimizer_wrap(SearchOptimizer_renderChildren($nodes, $index, $context), '**');
		case 'em':
		case 'i':
			return SearchOptimizer_wrap(SearchOptimizer_renderChildren($nodes, $index, $context), '*');
		case 'a':
			$url = SearchOptimizer_mdUrl(isset($node['attributes']['href']) ? $node['attributes']['href'] : '', $context['origin']);
			if ($url === '' || $context['link'] > 0) return SearchOptimizer_renderChildren($nodes, $index, $context);
			$context['link']++;
			$inner = SearchOptimizer_renderChildren($nodes, $index, $context);
			$context['link']--;
			if (strpos($inner, "\x01") !== false || strpos($inner, "\x03") !== false) return $inner;
			if (!preg_match('/^([ \x02]*)(.*?)([ \x02]*)$/s', $inner, $match)) return $inner;
			$label = ($match[2] === '') ? SearchOptimizer_mdText($url) : $match[2];
			return $match[1] . '[' . $label . '](' . $url . ')' . $match[3];
		case 'img':
			$alt = isset($node['attributes']['alt']) ? SearchOptimizer_mdText($node['attributes']['alt']) : '';
			$url = SearchOptimizer_mdUrl(isset($node['attributes']['src']) ? $node['attributes']['src'] : '', $context['origin']);
			if ($url === '') return $alt;
			return '![' . trim($alt) . '](' . $url . ')';
		case 'td':
		case 'th':
			return SearchOptimizer_renderChildren($nodes, $index, $context) . ' ';
		case 'address':
		case 'article':
		case 'aside':
		case 'caption':
		case 'center':
		case 'dd':
		case 'details':
		case 'div':
		case 'dl':
		case 'dt':
		case 'fieldset':
		case 'figcaption':
		case 'figure':
		case 'footer':
		case 'form':
		case 'header':
		case 'li':
		case 'main':
		case 'nav':
		case 'p':
		case 'section':
		case 'summary':
		case 'table':
		case 'tbody':
		case 'tfoot':
		case 'thead':
		case 'tr':
			return "\x01" . SearchOptimizer_renderChildren($nodes, $index, $context) . "\x01";
	}
	return SearchOptimizer_renderChildren($nodes, $index, $context);
}

/// HTML 을 markdown 으로. $origin(scheme://host[:port])은 '/' 로 시작하는 주소에 붙인다
function SearchOptimizer_htmlToMarkdown($html, $origin) {
	$html = SearchOptimizer_stripBlocks($html);
	$html = SearchOptimizer_replace(array('/<!\[CDATA\[.*?(?:\]\]>|\z)/s', '/<![^>]*>/', '/<\?.*?(?:\?>|\z)/s'), ' ', $html);
	$nodes = SearchOptimizer_parseHtml($html);
	$context = array('origin' => (string)$origin, 'link' => 0, 'blocks' => array());
	$markdown = SearchOptimizer_container(SearchOptimizer_renderChildren($nodes, 0, $context), $context);
	return $markdown === '' ? '' : $markdown . "\n";
}
