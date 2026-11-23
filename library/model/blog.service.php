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

// for Global Cache
$__gCacheBlogSettings = array();

function getBlogidByName($name) {
	$query = DBModel::getInstance();
	$query->reset('BlogSettings');
	$query->setQualifier('name','equals', 'name',true);
	$query->setQualifier('value', 'equals', $name, true);
	return $query->getCell('blogid');
	return false;
}

function getBlogidBySecondaryDomain($domain) {
	$query = DBModel::getInstance();
	$query->reset('BlogSettings');
	$query->setQualifier('name', 'equals', 'secondaryDomain', true);
	$query->setQualifier('value', 'hasoneof', array($domain, (substr($domain, 0, 4) == 'www.' ? substr($domain, 4) : 'www.' . $domain)), true);
	return $query->getCell('blogid');
}

function getBlogSettingGlobals($blogid) {
	return Setting::getBlogSettingsGlobal($blogid);
}

function getSkinSettings($blogid, $forceReload = false) {
	return Setting::getSkinSettings($blogid, $forceReload);
}

function getDefaultURL($blogid) {
	$context = Model_Context::getInstance();
	$blog = Setting::getBlogSettingsGlobal($blogid);	// Load specific blog's setting
	$prefix = $context->getProperty('service.useSSL',false) ? 'https://' : 'http://';

	switch ($context->getProperty('service.type')) {
		case 'domain':
			if (!empty($blog['defaultDomain']) && !empty($blog['secondaryDomain']))
				return ($prefix . $blog['secondaryDomain'] . ($context->getProperty('service.port') ? ':' . $context->getProperty('service.port') : '') . $context->getProperty('service.path'));
			else
				return ($prefix . $blog['name'] . '.' . $context->getProperty('service.domain') . ($context->getProperty('service.port') ? ':' . $context->getProperty('service.port') : '') . $context->getProperty('service.path'));
		case 'path':
			return ($prefix . $context->getProperty('service.domain') . ($context->getProperty('service.port') ? ':' . $context->getProperty('service.port') : '') . $context->getProperty('service.path') . '/' . $blog['name']);
		case 'single':
		default:
			return ($prefix . $context->getProperty('service.domain') . ($context->getProperty('service.port') ? ':' . $context->getProperty('service.port') : '') . $context->getProperty('service.path'));
	}
}

function getBlogURL($name = null, $domain = null, $path = null, $type = null) {
	$context = Model_Context::getInstance();
	$context->useNamespace('service');
	$prefix = $context->getProperty('service.useSSL',false) ? 'https://' : 'http://';
	if ($type === null)
		$type = $context->getProperty('type');
	if ($path === null)
		$path = $context->getProperty('path');
	if ($domain === null)
		$domain = $context->getProperty('domain') . ($context->getProperty('port') ? ':'.$context->getProperty('port') : '');
	if ($name === null)
		$name = $context->getProperty('blog.name');
	switch ($type) {
		case 'domain':
			return $prefix . ($name !== '' ? $name . '.' : '') . $domain . $path;
		case 'path':
			return $prefix.$domain.$path.'/'.$name;
		case 'single':default:
			return $prefix.$domain.$path.getFancyURLpostfix();
	}
}

function getBlogURLById($id = null, $domain = null, $path = null, $type = null) {
	$name = getBlogName($id);
	return getBlogURL($name, $domain, $path, $type);
}

function getFancyURLpostfix() {
	$context = Model_Context::getInstance();
	switch($context->getProperty('service.fancyURL')) {
		case 0: return '/index.php?';
		case 1: return '/?';
		case 2:default: return '';
	}
}

function getBlogURLRule($domain = null, $path = null, $type = null) {
	$context = Model_Context::getInstance();
	$context->useNamespace('service');
	$prefix = $context->getProperty('service.useSSL',false) ? 'https://' : 'http://';
	if ($type === null)
		$type = $context->getProperty('type');
	if ($path === null)
		$path = $context->getProperty('path');
	if ($domain === null)
		$domain = $context->getProperty('domain') . ($context->getProperty('port') ? ':'.$context->getProperty('port') : '');
	switch ($type) {
		case 'domain':
			return array($prefix, ".$domain$path");
		case 'path':
			return array($prefix.$domain.$path."/", '');
		case 'single':default:
			return array($prefix.$domain.$path, '');
	}
}

function getHelpURL($path) {
	$context = Model_Context::getInstance();
	// Textcube-NG: 앞부분은 config.php 의 $service['helpURL'] 로 바꿀 수 있다({lang} = 블로그 언어). 없거나 빈 값이면 원본 주소(v3.220)
	$helpBaseURL = $context->getProperty('service.helpURL');
	if (!is_string($helpBaseURL) || $helpBaseURL === '') $helpBaseURL = 'http://help.tattertools.com/{lang}/Textcube/Menu/';
	return str_replace('{lang}', ((string)$context->getProperty('blog.language') ?: 'ko'), $helpBaseURL).$path;
}

function writeHtaccess($contents) {
	if (!is_writable(ROOT . "/.htaccess"))
		return ROOT . _f('파일 쓰기 권한이 없습니다. 웹서버가 %1 파일의 쓰기 권한을 가지고 있는지 확인하세요.','.htaccess');
	$handler = fopen(ROOT . "/.htaccess", 'w');
	if (fwrite($handler, $contents) === false) {
		fclose($handler);
		return _t('실패했습니다.');
	} else {
		fclose($handler);
		@chmod(ROOT . "/.htaccess", 0666);
		return true;
	}
}

// config.php 에 기록할 설정 값의 PHP 리터럴.
// 문자열은 var_export() 리터럴로 기록해 값 안의 따옴표·역슬래시가 PHP 코드가 되지 않게 한다.
// true/false·정수·소수, 그리고 정수·소수 표기 그대로인 숫자 문자열(폼이 보내는 '7200'·'1'·'0' 등 — Validator 'int' 는 형 변환을 하지 않음)은
// 원래처럼 따옴표 없이 숫자로 기록한다. 그 밖의 숫자 모양 문자열('08' 등)은 문자열 그대로 기록한다(따옴표 없이 쓰면 문법 오류·값 변형).
function getConfigFileLiteral($value) {
	if ($value === true) return 'true';
	if ($value === false) return 'false';
	if (is_int($value) || is_float($value)) return var_export($value, true);
	$value = (string)$value;
	if (is_numeric($value) && (string)($value + 0) === $value) return var_export($value + 0, true);
	return var_export($value, true);
}

// 기존 config.php 원문($source)에서 $settings 의 키만 새 값으로 바꾼 원문을 돌려준다. 원문·결과가 PHP 문법에 맞지 않으면 false.
//  - 최상위의 단순 대입문(`$service['키'] = 리터럴;`, `$serviceURL = 리터럴;`)은 그 자리에서 값 부분만 바꾼다(줄 끝 주석 등은 그대로).
//  - $database·$memcached, 여러 줄 배열, 하위 키, 폼에 없는 키, 주석, 조건문, 식(getenv() 등)은 원문 그대로 둔다.
//  - 파일에 없는 키, 또는 마지막 대입이 조건문 안·식·연쇄 대입 등이라 그 자리에서 바꿀 수 없는 키는 파일 끝(마지막 PHP 닫는 태그 앞)에
//    대입문을 덧붙여 폼 값이 적용되게 한다(다음 저장부터는 덧붙인 대입문이 그 자리에서 바뀌므로 반복 저장해도 줄이 늘지 않는다).
//  - 'serviceURL' 은 Config 로더가 읽는 $serviceURL 변수로 기록한다(원래와 같음).
//  - 원문에 문법 오류가 있으면 $error 에 PHP 파서의 오류 줄·내용(array('line' => 줄, 'message' => 내용))을 넣는다.
function updateConfigFileSource($source, $settings, &$error = null) {
	$error = null;
	try {
		$raw = token_get_all($source, TOKEN_PARSE);
	} catch (ParseError $e) {
		$error = array('line' => $e->getLine(), 'message' => $e->getMessage());
		return false;
	}
	$tokens = array();
	foreach ($raw as $t) $tokens[] = is_array($t) ? array($t[0], $t[1]) : array($t, $t);
	$count = count($tokens);
	$eol = (strpos($source, "\r\n") !== false) ? "\r\n" : "\n";
	$skip = array(T_WHITESPACE, T_COMMENT, T_DOC_COMMENT);
	// 그 자리에서 바꿀 수 있는 값(부작용 없는 리터럴): 문자열·숫자·true/false/null·array()/[]·단항 부호·문자열 연결
	$literal = array(T_CONSTANT_ENCAPSED_STRING, T_LNUMBER, T_DNUMBER, T_ARRAY, T_DOUBLE_ARROW, T_WHITESPACE, T_COMMENT, T_DOC_COMMENT,
		'[', ']', '(', ')', ',', '-', '+', '.');
	$openers = array('(', '[', '{', T_CURLY_OPEN, T_DOLLAR_OPEN_CURLY_BRACES);
	if (defined('T_ATTRIBUTE')) $openers[] = T_ATTRIBUTE;
	$altOwners = array(T_IF, T_WHILE, T_FOR, T_FOREACH, T_SWITCH, T_DECLARE);
	$altEnds = array(T_ENDIF, T_ENDWHILE, T_ENDFOR, T_ENDFOREACH, T_ENDSWITCH, T_ENDDECLARE);
	$next = function ($i) use ($tokens, $count, $skip) {
		for ($i++; $i < $count; $i++)
			if (!in_array($tokens[$i][0], $skip, true)) return $i;
		return $count;
	};

	// 1) $service[...] / $serviceURL 이 나오는 곳을 모두 찾는다(대상: 's:키' = $service['키'], 'v:serviceURL' = $serviceURL,
	//    's:*' = 키를 알 수 없는 $service 사용 — 모든 $service 키에 영향 가능). 바꿀 수 있는 대입문이면 값 범위(첫·끝 토큰)를 함께 기록.
	$found = array();
	$depth = 0; $alt = 0; $inString = false; $prev = null; $parenOwners = array(); $lastParenOwner = null;
	for ($i = 0; $i < $count; $i++) {
		$id = $tokens[$i][0];
		if (in_array($id, $skip, true)) continue;
		if ($id === '"' || $id === '`' || $id === T_START_HEREDOC || $id === T_END_HEREDOC) {
			$inString = ($id === '"' || $id === '`') ? !$inString : ($id === T_START_HEREDOC);
		} else if ($id === T_VARIABLE && !$inString && ($tokens[$i][1] === '$service' || $tokens[$i][1] === '$serviceURL')) {
			$target = 'v:serviceURL';
			$eq = $next($i);
			if ($tokens[$i][1] === '$service') {
				$target = 's:*';
				$k = $next($eq);
				$close = $next($k);
				if ($eq < $count && $tokens[$eq][0] === '[' && $k < $count && $tokens[$k][0] === T_CONSTANT_ENCAPSED_STRING
					&& $close < $count && $tokens[$close][0] === ']' && strpbrk(substr($tokens[$k][1], 1, -1), '\\$') === false) {
					$target = 's:' . substr($tokens[$k][1], 1, -1);
					$eq = $next($close);
				}
			}
			$range = null;
			$atStart = ($depth == 0 && $alt == 0 && ($prev === null || in_array($prev, array(T_OPEN_TAG, ';', '}', T_CLOSE_TAG), true)));
			if ($target !== 's:*' && $atStart && $eq < $count && $tokens[$eq][0] === '=') {
				$first = $next($eq);
				$last = null;
				$level = 0;
				for ($j = $first; $j < $count; $j++) {
					$tid = $tokens[$j][0];
					if ($level == 0 && ($tid === ';' || $tid === T_CLOSE_TAG)) break;
					if ($tid === '(' || $tid === '[') $level++;
					else if ($tid === ')' || $tid === ']') $level--;
					if ($tid === T_STRING) {
						if (!in_array(strtolower($tokens[$j][1]), array('true', 'false', 'null'), true)) break;
					} else if (!in_array($tid, $literal, true)) break;
					if (!in_array($tid, $skip, true)) $last = $j;
				}
				if ($j < $count && $level == 0 && ($tokens[$j][0] === ';' || $tokens[$j][0] === T_CLOSE_TAG) && $last !== null)
					$range = array($first, $last);
			}
			$found[] = array($i, $target, $range);
		}
		// 괄호·대체 문법(if (...): ... endif;) 깊이 — 최상위 문장인지 판단용
		if (in_array($id, $openers, true)) {
			$depth++;
			if ($id === '(') $parenOwners[] = $prev;
		} else if ($id === ')' || $id === ']' || $id === '}') {
			$depth--;
			if ($id === ')') $lastParenOwner = array_pop($parenOwners);
		} else if ($id === ':' && $prev === ')' && in_array($lastParenOwner, $altOwners, true)) {
			$alt++;
		} else if (in_array($id, $altEnds, true)) {
			$alt--;
		}
		$prev = $id;
	}

	// 2) 키마다: 바꿀 수 있는 대입문은 모두 새 값으로, 마지막으로 나오는 곳이 바꿀 수 없는 형태이거나 없으면 끝에 덧붙인다.
	$replace = array();
	$append = '';
	foreach ($settings as $item => $value) {
		$item = (string)$item;
		$target = ($item === 'serviceURL') ? 'v:serviceURL' : 's:' . $item;
		$literalValue = getConfigFileLiteral($value);
		$lastReplaceable = null;
		foreach ($found as $f) {
			if ($f[1] !== $target && !($f[1] === 's:*' && $target !== 'v:serviceURL')) continue;
			$lastReplaceable = ($f[1] === $target && $f[2] !== null);
			if ($lastReplaceable) $replace[$f[2][0]] = array($f[2][1], $literalValue);
		}
		if ($lastReplaceable !== true)
			$append .= (($item === 'serviceURL') ? '$serviceURL' : '$service[' . var_export($item, true) . ']') . ' = ' . $literalValue . ';' . $eol;
	}

	// 3) 덧붙일 위치: 마지막 PHP 구간의 닫는 태그 앞, 없으면 파일 끝
	$insertAt = $count;
	$hasOpenTag = false;
	for ($i = 0; $i < $count; $i++) {
		if ($tokens[$i][0] === T_OPEN_TAG || $tokens[$i][0] === T_OPEN_TAG_WITH_ECHO) { $hasOpenTag = true; $insertAt = $count; }
		else if ($tokens[$i][0] === T_CLOSE_TAG) $insertAt = $i;
	}
	$contents = '';
	$lastSignificant = null;
	for ($i = 0; $i <= $count; $i++) {
		if ($i === $insertAt && $append !== '') {
			if (!$hasOpenTag) $contents .= '<?php' . $eol;
			else {
				// 닫는 태그가 문장 끝을 대신하던 경우(세미콜론 없이 닫힌 마지막 문장)에는 ; 를 보충, 줄 끝 주석과 같은 줄이 되지 않게 줄을 바꾼다
				if (!in_array($lastSignificant, array(';', '}', T_OPEN_TAG), true)) $contents .= ';';
				if (substr($contents, -1) !== "\n") $contents .= $eol;
			}
			$contents .= $append;
		}
		if ($i === $count) break;
		if (isset($replace[$i])) {
			$contents .= $replace[$i][1];
			$i = $replace[$i][0];
			$lastSignificant = T_CONSTANT_ENCAPSED_STRING;
			continue;
		}
		$contents .= $tokens[$i][1];
		if (!in_array($tokens[$i][0], $skip, true)) $lastSignificant = $tokens[$i][0];
	}
	try {
		token_get_all($contents, TOKEN_PARSE);
	} catch (ParseError $e) {
		return false;
	}
	return $contents;
}

function writeConfigFile($settings) {
	$writer = new OutputWriter;
	// 기존 config.php 는 그대로 두고 폼 설정($settings) 키의 값만 바꿔 쓴다(updateConfigFileSource() 참조).
	// 예전에는 컨텍스트 값과 정규식으로 파일 전체를 다시 만들어, 줄 끝 주석이 붙은 키(setup.php 가 쓰는 useSSL·직접 넣은 port 등)·
	// 여러 줄 값·$memcached·하위 키가 사라지고 주석 처리된 예시 줄이 활성화될 수 있었으며, 값이 escape 없이 작은따옴표 안에 기록돼
	// 따옴표·역슬래시가 든 값(DB 비밀번호 등)이 config.php 를 깨뜨리거나 PHP 코드로 실행될 수 있었다.
	if (!is_writable(ROOT . "/config.php"))
		return _f('파일 쓰기 권한이 없습니다. 웹서버가 %1 파일의 쓰기 권한을 가지고 있는지 확인하세요.','config.php');
	$current = @file_get_contents(ROOT . "/config.php");
	if ($current === false)
		return _t('실패했습니다.');
	$contents = updateConfigFileSource($current, $settings, $parseError);
	if ($contents === false) {
		// 기존 config.php 에 문법 오류가 있으면 저장하지 않고 PHP 파서의 오류 줄·내용을 알린다(서비스 관리자의 서버 설정 화면, XML CDATA 로 전달).
		// 제어 문자는 빼고, UTF-8 이 아닌 바이트는 '?' 로 바꾼다(응답 XML 이 깨지지 않게).
		if (is_array($parseError)) {
			$detail = preg_replace('/[\x00-\x08\x0B\x0C\x0E-\x1F\x7F]/', '', (string)$parseError['message']);
			if (!preg_match('//u', $detail)) $detail = preg_replace('/[\x80-\xFF]/', '?', $detail);
			return _f('config.php 에 문법 오류가 있어 저장하지 않았습니다. 파일을 고친 뒤 다시 저장하세요. (%1행: %2)', (int)$parseError['line'], $detail);
		}
		return _t('실패했습니다.');
	}
	if (!$writer->openFile(ROOT . "/config.php"))
		return _t('실패했습니다.');
	if ($writer->write($contents) === false) {
		$writer->close();
		return _t('실패했습니다.');
	}
	$writer->close();
	// 저장된 설정을 즉시 반영한다.
	// (1) config.php 의 opcache 엔트리 무효화 — opcache.validate_timestamps=0 환경에서는 이게 없으면
	//     새 설정이 다음 php-fpm reload 까지 반영되지 않는다(전체 opcache_reset 보다 가볍고 정확).
	if (function_exists('opcache_invalidate')) @opcache_invalidate(ROOT . "/config.php", true);
	elseif (function_exists('opcache_reset')) @opcache_reset();
	// (2) 설정 변경으로 stale 될 수 있는 메모리/페이지 캐시 비움.
	//     memcache 전체 flush 는 세션 키(sessions/·authorizedSession/ 등)까지 지워 모든 사용자를 로그아웃시키므로
	//     memcached 를 끄는 저장(다음 요청부터 DB 세션을 씀)과 pagecache 를 다시 켜는 저장(꺼진 동안 갱신되지 않은
	//     쿼리 캐시가 남음)에서만 부른다. 그 밖의 설정 값은 memcache 내용(DB 행·DB 설정)에 들어 있지 않다(r3-21, R-447)
	global $memcache, $database;
	$context = Model_Context::getInstance();
	$memcachedOff = is_array($settings) && array_key_exists('memcached', $settings) && !$settings['memcached'];
	$pageCacheOn  = is_array($settings) && array_key_exists('pagecache', $settings) && $settings['pagecache'] && !$context->getProperty('service.pagecache');
	if (isset($memcache) && is_object($memcache) && method_exists($memcache, 'flush')) {
		if ($memcachedOff || $pageCacheOn) @$memcache->flush();
	}
	if (class_exists('CacheControl') && method_exists('CacheControl', 'flushAll')) {
		@CacheControl::flushAll();
		// Textcube-NG: pagecache 를 끔에서 켬으로 바꾸는 저장에서는 꺼진 동안 갱신되지 않은 다른 블로그의 페이지·쿼리 캐시도 비운다 —
		//   flushAll() 은 현재 블로그만 비우므로 PageCacheLog 에 기록된 모든 blogid 를 돈다(interface/blog/checkup.php clearCache 와 같은 방식).
		//   이미 켜진 상태에서 다른 설정을 저장할 때는 위 현재 블로그 flush 만 한다(r3-25, R-458)
		if ($pageCacheOn && !empty($database['prefix'])) {
			$flushIds = POD::queryColumn("SELECT DISTINCT blogid FROM {$database['prefix']}PageCacheLog");
			if (is_array($flushIds)) {
				foreach ($flushIds as $flushId) {
					if (is_numeric($flushId) && (int)$flushId > 0) @CacheControl::flushAll((int)$flushId);
				}
			}
		}
	}
	return true;
}

function getDefaultHtaccess($jsPrint = false) {
	$rootURL = substr($_SERVER['PHP_SELF'], 0, strlen($_SERVER['PHP_SELF']) - 12);
	// Textcube-NG: 루트 .htaccess 와 맞춤 — .user.ini 차단(Apache 2.2·2.4 겸용, R-6), 스킨 index.xml 차단(5330)(v3.196). 스킨 index.xml 은 깊이와 관계없이 막는다(skin/blog/customize/<id>/index.xml 포함, r3-3)
	// Textcube-NG: r4-6 — .user.ini 차단을 <Files> 블록 대신 plugins·attach 규칙과 같은 꼴의 RewriteRule 로 바꿈(AllowOverride FileInfo 만으로 동작, PATH_INFO 가 붙은 경로도 막음)
	// Textcube-NG: plugins/ 아래 PHP 파일 직접 요청 차단 규칙 추가(r3-6, R-111)
	// Textcube-NG: attach/ 아래 PHP 파일 실행 차단 규칙을 plugins 차단 줄 뒤에 추가(r3-20)
	$source = '#<IfModule mod_url.c>
#CheckURL Off
#</IfModule>
#SetEnv PRELOAD_CONFIG 1

RewriteEngine On
RewriteBase '.$rootURL.'/
RewriteRule ^(thumbnail)/([0-9]+/.+)$ cache/$1/$2 [L]
RewriteCond %{REQUEST_FILENAME} -f
RewriteRule ^(cache)+/+(.*[^/])\.(cache|xml|txt|log)$ - [NC,F,L]
RewriteCond %{REQUEST_FILENAME} -f
RewriteRule ^(plugins/+[^/]+/+(locale/+.+|index\.xml)|resources/+locale/+.+|skin/+.+/+index\.xml)$ - [NC,F,L]
RewriteRule ^plugins/+.+\.(php[0-9]*|phps|phtml|phar)(/|$) - [NC,F,L]
# Textcube-NG: 첨부 폴더(attach/) 안 PHP 실행 차단 — 올린 파일은 PHP 로 실행되면 안 된다(r3-20)
# Textcube-NG: r3-27 R-448 — 가운데 확장자(a.php.jpg)도 막는다: 확장자 뒤에 . 이 이어지는 경우를 더함
RewriteRule ^attach/+.+\.(php[0-9]*|phps|phtml|pht|phar)(\.|/|$) - [NC,F,L]
# Textcube-NG: r4-6 — PHP 사용자 설정 파일(.user.ini, 어느 폴더든) 직접 접근 차단을 <Files> 블록에서 RewriteRule 로 옮김(plugins·attach 규칙과 같이 끝을 슬래시 또는 경로 끝으로 맞춰 PATH_INFO 가 붙은 경로 .user.ini/x 도 막음). <Files> 안 Require·Order 는 AllowOverride AuthConfig·Limit 이 있어야 해서 FileInfo 만 허용한 서버에서 모든 요청이 500 이었다
RewriteRule ^(.+/+)?\.user\.ini(/|$) - [NC,F,L]
RewriteCond %{REQUEST_FILENAME} -d
RewriteRule ^(.+[^/])$ $1/ [L]
RewriteCond %{REQUEST_FILENAME} !-f
RewriteRule ^(.*)$ rewrite.php [L,QSA]';
	if($jsPrint == true) $source = str_replace(array("\\","'","\n"),array("\\\\","\'",'\n'),$source);	// Textcube-NG: 백슬래시를 먼저 겹쳐 JS 작은따옴표 문자열에서 \. 가 . 로 바뀌지 않게 함(R-11)(v3.196)
	return $source;
}
?>
