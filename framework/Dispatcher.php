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

final class Dispatcher {
	private static $instances = array();
	
	public $uri, $interfacePath;
	
	protected function __construct() {
		$this->pathSelector();
		$this->URIinterpreter();
	}
	
	final protected static function _getInstance($className) {
		if (!array_key_exists($className, self::$instances)) {
			self::$instances[$className] = new $className();
		}
		return self::$instances[$className];
	}
	
	public static function getInstance() {
		return self::_getInstance(__CLASS__);
    }
    
    private function pathSelector() {
    	define('__TEXTCUBE_CONFIG_FILE__',ROOT.'/config.php');
    	define('__TEXTCUBE_CACHE_DIR__',ROOT.'/cache');
    	define('__TEXTCUBE_ATTACH_DIR__',ROOT.'/attach');
    	//define('__TEXTCUBE_SKIN_DIR__',ROOT.'/skin/blog');
    	//define('__TEXTCUBE_SKIN_CUSTOM_DIR__',__TEXTCUBE_SKIN_DIR__.'/customize');
    }
	/* [보안] interfacePath 정규성 검사: 'interface/' 로 시작하지 않거나 NUL 바이트, '.' / '..' 세그먼트('/' 및 '\' 구분자 모두)를 포함하면 false */
	private static function isCanonicalInterfacePath($path) {
		if (!is_string($path) || strpos($path, "\0") !== false || strpos($path, 'interface/') !== 0) return false;
		foreach (preg_split('@[\\\\/]@', $path) as $segment) {
			if ($segment === '.' || $segment === '..') return false;
		}
		return true;
	}
	/* Textcube-NG: 등록 페이지(interface/freeSlogan.php → AccessFreeSlogan) 경로 판정. 아래 URIinterpreter 와 페이지 플러그인(저장·출력)이 같은 판정을 쓰도록 공개 정적 메서드로 둔다(v3.212) */
	// 형식: 디코드 1회 뒤 끝 '/' 하나를 떼고 255바이트·8단계 이하. 단계마다 글자·숫자·_·~·- 와 그 사이의 '.' 만 쓰고, 마지막 단계는 숫자만이 아니며, PHP 실행 확장자·'index.php' 가 없어야 한다. 마지막 단계의 확장자나 첫 단계 이름이 아래 탐색 목록에 있으면 받지 않는다
	public static function isFreeSloganPath($raw) {
		if (!is_string($raw) || $raw === '' || strlen($raw) > 2048) return false;
		$path = rawurldecode($raw);
		if (substr($path, -1) === '/') $path = substr($path, 0, -1);
		if ($path === '' || strlen($path) > 255 || preg_match('//u', $path) !== 1) return false;
		if (preg_match('/[%\\\\:\x00-\x1F\x7F]/', $path) || stripos($path, 'index.php') !== false) return false;
		$segments = explode('/', $path);
		if (count($segments) > 8 || is_numeric(end($segments))) return false;
		foreach ($segments as $index => $segment) {
			if ($index === 0 && $segment === '.well-known' && count($segments) > 1) continue;	// Textcube-NG: RFC 8615 의 '/.well-known/…'(security.txt 등)만 '.' 로 시작하는 첫 단계로 받는다. 단독 '.well-known' 은 받지 않는다(r3-5)
			if (!preg_match('/^[\p{L}\p{N}_~-]+(?:\.[\p{L}\p{N}_~-]+)*$/Du', $segment) || preg_match('/\.ph(?:p[0-9]*|tml|ar|ps)$/i', $segment)) return false;
		}
		// 탐색 경로: 마지막 단계의 마지막 '.' 뒤 확장자가 이미지·묶음·백업·다른 서버 스크립트·설정·기록이거나, 첫 단계(또는 그 첫 '.' 앞 이름)가 흔한 탐색 이름이면 받지 않는다. html·htm·txt·xml·json 은 받는다(v3.212)
		$last = strtr($segments[count($segments) - 1], 'ABCDEFGHIJKLMNOPQRSTUVWXYZ', 'abcdefghijklmnopqrstuvwxyz');
		$dot = strrpos($last, '.');
		// Textcube-NG: avif 도 이미지 확장자로 본다(r3-20)
		if ($dot !== false && in_array(substr($last, $dot + 1), array('png', 'jpg', 'jpeg', 'gif', 'ico', 'svg', 'webp', 'avif', 'bmp',
			'zip', 'gz', 'tgz', 'tar', 'rar', '7z', 'bz2', 'bak', 'old', 'orig', 'swp', 'sql',
			'asp', 'aspx', 'jsp', 'jspx', 'cgi', 'pl', 'py', 'sh', 'exe', 'dll', 'do', 'action',
			'env', 'ini', 'conf', 'cfg', 'yml', 'yaml', 'toml', 'log'), true)) return false;
		$first = strtr($segments[0], 'ABCDEFGHIJKLMNOPQRSTUVWXYZ', 'abcdefghijklmnopqrstuvwxyz');
		$dot = strpos($first, '.');
		$probes = array('wp-admin', 'wp-content', 'wp-includes', 'wp-json', 'phpmyadmin', 'pma', 'cgi-bin', 'boaform', 'hnap1', 'actuator',
			'vendor', 'node_modules', 'server-status', 'server-info');
		if (in_array($first, $probes, true) || ($dot !== false && in_array(substr($first, 0, $dot), $probes, true))) return false;
		return true;
	}
	// 비교용 정규화: 바뀌지 않을 때까지(최대 4회) 디코드, '\' → '/', 빈 단계·'.' 제거, '..' 는 앞 단계 제거(루트 밖이면 null), ASCII 소문자. 남은 '%'·제어 문자가 있으면 null
	public static function canonicalFreeSloganSegments($path) {
		if (!is_string($path)) return null;
		for ($i = 0; $i < 4; $i++) {
			$decoded = rawurldecode($path);
			if ($decoded === $path) break;
			$path = $decoded;
		}
		if (strpos($path, '%') !== false || preg_match('/[\x00-\x1F\x7F]/', $path)) return null;
		$segments = array();
		foreach (explode('/', str_replace('\\', '/', $path)) as $segment) {
			if ($segment === '' || $segment === '.') continue;
			if ($segment === '..') {
				if (empty($segments)) return null;
				array_pop($segments);
				continue;
			}
			$segments[] = strtr($segment, 'ABCDEFGHIJKLMNOPQRSTUVWXYZ', 'abcdefghijklmnopqrstuvwxyz');
		}
		return $segments;
	}
	// 예약 경로: 정규화한 첫 단계(끝 ' '·'.' 뗀 값, 첫 '.' 앞 이름 포함)가 예약 이름이면 true. path 모드는 블로그 기준과 설치 기준($prefix/경로)을 둘 다 본다. 정규화할 수 없으면 true
	public static function isReservedFreeSloganPath($path, $prefix = '') {
		if (!is_string($path)) return true;
		$candidates = array($path);
		if (is_string($prefix) && $prefix !== '') $candidates[] = $prefix . '/' . $path;
		$reserved = self::freeSloganReservedNames();
		foreach ($candidates as $candidate) {
			$segments = self::canonicalFreeSloganSegments($candidate);
			if (empty($segments)) return true;
			if ($segments[0] === '.well-known' && count($segments) > 1) continue;	// Textcube-NG: '/.well-known/…' 는 예약 이름 판정에서 뺀다(isFreeSloganPath 와 같은 예외. path 모드의 설치 기준 후보는 첫 단계가 블로그 이름이라 그대로 본다)(r3-5)
			$first = rtrim($segments[0], ' .');
			$dot = strpos($first, '.');
			$base = ($dot === false) ? $first : substr($first, 0, $dot);
			if ($first === '' || $base === '' || isset($reserved[$first]) || isset($reserved[$base]) || isset($reserved[$segments[0]])) return true;
		}
		return false;
	}
	private static function freeSloganReservedNames() {
		static $names = null;
		if ($names !== null) return $names;
		$list = array(
			// 정적 경로(.htaccess 규칙·아래 URIinterpreter 정적 블록)
			'resources', 'plugins', 'cache', 'skin', 'attach', 'thumbnail',
			// URIinterpreter: setup.php 분기, interfaceType 판정(feeder·owner·control·icon·checkup), 모바일 접두(i·m)
			'setup.php', 'setup', 'feeder', 'owner', 'control', 'favicon.ico', 'index.gif', 'checkup', 'i', 'm',
			// URIinterpreter case 목록(interface/blog/*), rss·atom, login, comment·trackback
			'api', 'archive', 'attachment', 'author', 'category', 'cfeed', 'cover', 'cron', 'entry', 'foaf', 'guestbook', 'imazing',
			// Textcube-NG: sitemap = interface/sitemap(XML 사이트맵)(r3-20)
			'keylog', 'line', 'location', 'locationsuggest', 'logout', 'notice', 'page', 'plugin', 'pluginforowner', 'search', 'sitemap', 'stream',
			'suggest', 'tag', 'ttxml', 'imageresizer', 'rss', 'atom', 'login', 'comment', 'trackback',
			// 진입점·처리기
			'interface', 'freeslogan', 'index', 'rewrite',
			// 설치 루트·interface 에 있는 이름(5트리 합집합의 첫 '.' 앞 이름, 소문자). 파일·폴더를 새로 두면 여기에 더한다(고정 목록과 5트리 대조 시험으로 확인)(v3.212)
			'documents', 'framework', 'library', 'config', 'chkport', 'opcache_flush', 'cleanup_legacy_assets', 'bower', 'manifest', 'parameters',
			'readme', 'license', 'notice', 'version', 'contributing', 'changelog', 'changelog5to7', 'changelog7', 'changelog8', 'migration', 'security',
			'pagecacheandmemcached', 'web', 'wpi-init-install', 'nul', 'blog', 'common', 'favicon'
		);
		$names = array();
		foreach ($list as $name) $names[$name] = true;
		return $names;
	}
	// 없는 경로를 등록 페이지 처리기로 넘길지: '?' 앞 경로만 보고, path 모드는 첫 단계(블로그 이름)를 떼어 설치 기준 후보로 쓴다
	private static function freeSloganAllowed($input, $type) {
		if (!is_string($input)) return false;
		$query = strpos($input, '?');
		if ($query !== false) $input = substr($input, 0, $query);
		$prefix = '';
		if ($type === 'path') {
			$slash = strpos($input, '/');
			if ($slash === false) return false;
			$prefix = rawurldecode(substr($input, 0, $slash));
			$input = substr($input, $slash + 1);
		}
		return self::isFreeSloganPath($input) && !self::isReservedFreeSloganPath($input, $prefix);
	}
	// Textcube-NG: path 모드 호스트 루트에서 등록 페이지 처리기로 넘길 파일. '?' 앞 경로가 정확히 robots.txt·llms.txt 일 때만 true(아래 URIinterpreter·URIHandler·freeSlogan.php 가 같은 판정을 쓴다)(r3-5)
	public static function isHostRootFreeSloganFile($input) {
		if (!is_string($input)) return false;
		$query = strpos($input, '?');
		if ($query !== false) $input = substr($input, 0, $query);
		return $input === 'robots.txt' || $input === 'llms.txt';
	}
	// Host 불일치: hostMismatchBehavior 가 '404' 일 때만 URIHandler(v3.205)와 같은 판정(single·path, domain 이 있을 때, hostAliases 는 host 와 host:port)으로 DB 연결 전에 끊는다. 그 밖의 설정은 지금처럼 URIHandler 가 처리한다.
	// 여기는 boot(00-UnifiedEnvironment) 이전이라 Host 헤더를 boot 와 같이 이름과 포트로 나누고(IPv6 '[…]' 포함), 포트는 Config 와 같은 규칙(요청 포트가 80·443 이 아니면 요청 포트, useConfigPort 이고 config.php 에 포트가 있으면 그 포트)으로 여기서만 계산한다($_SERVER·$service 는 바꾸지 않음).
	// boot 가 400 으로 끝낼 Host 는 이름이 domain·hostAliases 와 같으면 그대로 넘겨 boot 가 끊고, 다르면 여기서 404 로 끊는다(v3.212)
	private static function freeSloganHostRejected($service) {
		$behavior = isset($service['hostMismatchBehavior']) ? $service['hostMismatchBehavior'] : '';
		if ($behavior !== '404') return false;
		$type = isset($service['type']) ? $service['type'] : '';
		if ($type !== 'single' && $type !== 'path') return false;
		$domain = isset($service['domain']) ? (string)$service['domain'] : '';
		if ($domain === '') return false;
		$aliases = (isset($service['hostAliases']) && is_array($service['hostAliases'])) ? $service['hostAliases'] : array();
		$host = isset($_SERVER['HTTP_HOST']) ? (string)$_SERVER['HTTP_HOST'] : '';
		$bracket = ($host !== '' && $host[0] === '[');
		$end = $bracket ? strpos($host, ']') : strpos($host, ':');
		if ($end !== false && $bracket) $end++;
		$name = ($end === false) ? $host : substr($host, 0, $end);
		$rest = ($end === false) ? '' : substr($host, $end);
		$requestPort = ($rest !== '' && $rest[0] === ':') ? substr($rest, 1) : (isset($_SERVER['SERVER_PORT']) ? $_SERVER['SERVER_PORT'] : null);
		$port = isset($service['port']) ? $service['port'] : null;
		if (is_numeric($requestPort) && $requestPort != 80 && $requestPort != 443 && !(!empty($service['useConfigPort']) && !empty($port))) $port = $requestPort;
		$port = (string)$port;
		return $name !== $domain && !in_array($name, $aliases, true) && !in_array($name . ($port !== '' ? ':' . $port : ''), $aliases, true);
	}
	private function URIinterpreter() {
		global $service;
		/* Workaround for IIS environment */
		if(!isset($_SERVER['REQUEST_URI']) && isset($_SERVER['SCRIPT_NAME'])) {
			$_SERVER['REQUEST_URI'] = $_SERVER['SCRIPT_NAME'];
			if(isset($_SERVER['QUERY_STRING']) && !empty($_SERVER['QUERY_STRING'])) $_SERVER['REQUEST_URI'] .= '?'.$_SERVER['QUERY_STRING'];
		}
		if (!empty($_SERVER['PRELOAD_CONFIG']) && file_exists(__TEXTCUBE_CONFIG_FILE__)) require_once __TEXTCUBE_CONFIG_FILE__;
		// IIS 7.0 and URL Rewrite Module CTP, but non-ASCII URLs are NOT supported.
		// [보안] X-Original-URL / X-Rewrite-URL 은 IIS 리라이트 모듈이 설정하는 헤더 → IIS 에서만 신뢰
		//        (그 외 서버에서는 클라이언트가 보낸 헤더로 라우팅 경로를 임의 지정해 웹서버 경로 제한을 우회할 수 있음)
		$isIIS = (isset($_SERVER['SERVER_SOFTWARE']) && stripos($_SERVER['SERVER_SOFTWARE'], 'IIS') !== false);
		if ($isIIS && isset($_SERVER['HTTP_X_ORIGINAL_URL'])) {
			$_SERVER['REQUEST_URI'] = $_SERVER['HTTP_X_ORIGINAL_URL'];
		} // IIS 5.x/6.0/7.0 and Ionics ISAPI Rewrite Filter
		else if ($isIIS && isset($_SERVER['HTTP_X_REWRITE_URL']) && strpos($_SERVER['REQUEST_URI'], 'rewrite.php') !== FALSE) {
			$_SERVER['REQUEST_URI'] = urldecode($_SERVER['HTTP_X_REWRITE_URL']);
		}
		/* Retrieve Access Parameter Information. */
		$uri = array(
			'host'     => isset($_SERVER['HTTP_HOST']) ? $_SERVER['HTTP_HOST'] : '',	// Textcube-NG: Host 헤더 없는 요청의 PHP 8 경고. boot(00-UnifiedEnvironment) 이전이라 따로 대체(auth.php 와 같은 방식)(v3.140)
			'fullpath' => str_replace('index.php', '', $_SERVER["REQUEST_URI"]),
			'position' => $_SERVER["SCRIPT_NAME"],
			'root'     => preg_replace('/index\.php\z/', '', str_replace('rewrite.php', '', $_SERVER["SCRIPT_NAME"]))	// Textcube-NG: rtrim(…, 'index.php') 는 글자 집합으로 잘라 내므로 끝의 'index.php' 한 번만 뗀다(v3.195)
			);
		if (strpos($uri['fullpath'],$uri['root']) !== 0)
			$uri['fullpath'] = $uri['root'].substr($uri['fullpath'], strlen($uri['root']) - 1);
		// Workaround for compartibility with fastCGI / Other environment
		$uri['input'] = ltrim(substr($uri['fullpath'],
			strlen($uri['root']) + (defined('__TEXTCUBE_NO_FANCY_URL__') ? 1 : 0)),'/');
		// Support Tattertools 0.9x legacy address (for upgrade users)
		if (array_key_exists('pl', $_GET) && strval(intval($_GET['pl'])) == $_GET['pl']) { header("Location: ".$uri['root'].$_GET['pl']); exit;}
		$part = strtok($uri['input'], '/');
		if (in_array($part, array('resources','plugins','cache','skin','attach','thumbnail'))) {
			$part = ltrim(rtrim($part == 'thumbnail' ?
				  preg_replace('/thumbnail/', 'cache/thumbnail', $uri['input'], 1) :
				  $uri['input']), '/');
			$part = (($qpos = strpos($part, '?')) !== false) ? substr($part, 0, $qpos) : $part;
			/* [보안] cache/ 아래에서 브라우저가 직접 받는 파일은 썸네일(cache/thumbnail/, framework/utils/Image.php 가 만드는 주소)뿐.
			   .htaccess 의 cache/*.(cache|xml|txt|log) 거부는 index.php/cache/… 처럼 PHP 를 거치는 요청이나 모든 요청을
			   rewrite.php 로 보내는 설정(documents/setup_nginx.txt)에는 적용되지 않아 서버 백업(cache/backup/{blogid}.xml)·
			   페이지 캐시 등이 이 분기로 그대로 출력됐다 → thumbnail 외 cache 경로는 거부 */
			if (strpos($part . '/', 'cache/') === 0 && strpos($part, 'cache/thumbnail/') !== 0) {
				header("HTTP/1.0 403 Forbidden");exit;
			}
			// Textcube-NG: NUL 바이트가 든 경로는 file_exists() 전에 404(php7.4 의 file_exists() 는 E_WARNING 을 내고 8.x 는 경고 없이 false, 응답은 5트리 모두 404)(v3.195)
			if (strpos($part, "\0") !== false) {
				header("HTTP/1.0 404 Not Found");exit;
			}
			if(file_exists($part)) {
				/* [보안] v3.171: 플러그인·리소스 locale 파일(plugins/{이름}/locale/, resources/locale/)과 플러그인 index.xml(plugins/{이름}/index.xml)은
				   브라우저가 직접 받는 파일이 아님(HTTP 로 부르는 곳 없음). .htaccess 의 같은 규칙은 index.php/… 처럼 PHP 를 거치는 요청이나
				   모든 요청을 rewrite.php 로 보내는 설정(documents/setup_nginx.txt)에는 적용되지 않으므로 여기서도 거부.
				   검사 대상은 판정용으로만 정규화한 경로(출력하는 $part 는 그대로): '\' -> '/', 연속 '/' 합침, 조각마다 끝의 '.'·공백 제거('.' 조각은 빈 조각이 되어 사라지고 '..' 는 그대로 둠), 대소문자 무시(i). 스킨 index.xml·JS·CSS 는 해당 없음.
				   file_exists($part) 가 참인 문자열만 여기까지 오며, 이후 dumpWithEtag 가 urldecode 를 다시 하지만 plugins/·resources/ 안에 '%'·'+' 가 든 파일명이 없어 영향 없음.
				   조각에 ':' 가 있으면 NTFS 대체 데이터 스트림(index.xml::$DATA 등) 표기일 수 있어 403(Linux 트리에는 ':' 가 든 경로가 없음).
				   Textcube-NG: 스킨 index.xml(skin/{종류}/{스킨}/index.xml)도 거부. .htaccess 의 skin/+.+/+index\.xml 과 같이 깊이와 관계없이(skin/blog/customize/<id>/index.xml 포함, r3-3) 막고, 위 '스킨 index.xml … 해당 없음' 은 v3.171 때 기준(v3.196) */
				$denyPath = implode('/', array_filter(array_map(function ($seg) { return $seg === '..' ? $seg : rtrim($seg, '. '); }, preg_split('~/+~', str_replace('\\', '/', $part))), function ($seg) { return $seg !== ''; }));
				if (preg_match('~^(?:plugins/[^/]+/(?:locale(?:/|$)|index\.xml$)|resources/locale(?:/|$)|skin/.+/index\.xml$)~iD', ltrim($denyPath, '/'))) {
					header("HTTP/1.0 403 Forbidden");exit;
				}
				unset($denyPath);
				if (strpos($part, ':') !== false) {
					header("HTTP/1.0 403 Forbidden");exit;
				}
				require_once ROOT.'/library/function/file.php';
				dumpWithEtag($part);
				exit;
			} else {
				header("HTTP/1.0 404 Not Found");exit;
			}
		}
		if (strtok($part, '?') == 'setup.php') {require 'setup.php'; exit;}
		$uri['fragment'] = explode('/',strtok($uri['input'],'?'));
		unset($part);
	
		/* Check the existence of config.php (whether installed or not) */
		if (!file_exists(ROOT.'/config.php')) {
			if (file_exists('.htaccess')) {print "<html><body>Remove '.htaccess' file first!</body></html>";exit;}
			header("Location: " . rtrim($_SERVER["REQUEST_URI"],"/") . "/setup.php");
		}
		/* Determine that which interface should be loaded. */
		require_once ROOT.'/config.php';
		if(defined('__TEXTCUBE_NO_FANCY_URL__')) $service['type'] = 'single';
		switch ($service['type']) {
			case 'path': // For path-based multi blog.
				array_splice($uri['fragment'],0,1); 
				$pathPart = ltrim(rtrim(strtok(strstr($uri['input'],'/'), '?'), '/'), '/');
				break;
			case 'single':
				$pathPart = (strpos($uri['input'],'?') !== 0 ? ltrim(rtrim(strtok($uri['input'], '?'), '/'), '/') : '');
				break;
			case 'domain': default: 
				$pathPart = ltrim(rtrim(strtok($uri['fullpath'], '?'), '/'), '/');
				// [보안] ltrim() 두 번째 인자는 문자 집합 → 경로 접두어($service['path'])만 정확히 제거
				if(!empty($service['path'])) {
					$servicePathPrefix = trim($service['path'], '/');
					if ($servicePathPrefix !== '' && ($pathPart === $servicePathPrefix || strpos($pathPart, $servicePathPrefix.'/') === 0))
						$pathPart = ltrim(substr($pathPart, strlen($servicePathPrefix)), '/');
				}
				break;
		}
		$pathPart = strtok($pathPart,'&');
		// Determine interface Type
		if (isset($uri['fragment'][0])) {
			if (isset($uri['fragment'][1]) &&
			($uri['fragment'][0] == 'owner') &&
			($uri['fragment'][1] == 'reader' || ($uri['fragment'][1] == 'network' && isset($uri['fragment'][2]) && $uri['fragment'][2] == 'reader'))) {
				$uri['interfaceType'] = 'reader';
			} else {
				switch($uri['fragment'][0]) {
					case 'feeder':
						$uri['interfaceType'] = 'feeder';
						break;
					case 'owner': case 'control':
						$uri['interfaceType'] = 'owner';
						break;
					case 'favicon.ico':
					case 'index.gif':
						$uri['interfaceType'] = 'icon';
						break;
					//case 'i':case 'm':  -> overload at preprocessor.php
					case 'checkup':
						$uri['interfaceType'] = 'checkup';
						break;
					default:
						$uri['interfaceType'] = 'blog';
						break;
				}
			}	
		} else {
			$uri['interfaceType'] = 'blog';
		}
		/* Load interface. */
		$interfacePath = null;
		if ($uri['interfaceType'] == 'icon') {
			// [보안] pathPart 가 첫 세그먼트(favicon.ico / index.gif)와 정확히 일치할 때만 허용 (URL 유래 임의 경로 include 방지)
			if ($pathPart !== $uri['fragment'][0]) {
				header("HTTP/1.0 404 Not Found");exit;
			}
			$uri['interfacePath'] = $this->interfacePath = 'interface/'.$pathPart.'.php';
		} else {
			if (!empty($uri['fragment'])) {
				if (is_numeric(strtok(end($uri['fragment']), '&'))) {
					array_pop($uri['fragment']);	
					$pathPart = count($uri['fragment'])==1 ? null : implode('/', $uri['fragment']);
				}
				if(isset($uri['fragment'][0])) {
					switch($uri['fragment'][0]) {
						case 'api': case 'archive': case 'attachment': case 'author':
						case 'category':  case 'cfeed': case 'checkup': case 'cover': case 'cron': 
						case 'entry': case 'feeder': case 'foaf': case 'guestbook': case 'iMazing': 
						case 'keylog': case 'line': case 'location': case 'locationSuggest': 
						case 'logout': case 'notice': case 'page': case 'plugin': case 'pluginForOwner': 
						case 'search': case 'stream': case 'suggest': case 'tag': case 'ttxml':
						case 'imageResizer':	
							$pathPart = $uri['fragment'][0];
							$interfacePath = 'interface/blog/'.$pathPart.'.php';
							break;
						case 'rss': case 'atom':
							if(isset($uri['fragment'][1]) && in_array($uri['fragment'][1],array('archive','author','category','comment','line','notifyComment','response','search','tag','trackback'))) {
								$pathPart = $uri['fragment'][0].'/'.$uri['fragment'][1];
								$interfacePath = 'interface/'.$pathPart.'/index.php';							
							}
							break;
						case 'login': case 'owner': case 'control':
							break;
						case 'comment': case 'trackback':
							$pathPart = implode("/",$uri['fragment']);
							$interfacePath = 'interface/blog/'.$pathPart.'/index.php';
							break;
						default:
					}
				}
				
			}
			if (empty($interfacePath)) $interfacePath = 'interface/'.(empty($pathPart) ? '' : $pathPart.'/').'index.php';
			define('PATH', 'interface/'.(empty($pathPart) ? '' : $pathPart.'/'));
			unset($pathPart);
			// Textcube-NG: path 모드 호스트 루트의 robots.txt·llms.txt 는 블로그 이름이 없어 interface/index.php 로 정해진다. GET/HEAD 이고 Host 불일치 404 대상이 아니면 등록 페이지 처리기로 넘긴다(URIHandler 가 기본 블로그로 본다)(r3-5)
			if ($interfacePath === 'interface/index.php' && $uri['interfaceType'] == 'blog' && isset($service['type']) && $service['type'] === 'path' && self::isHostRootFreeSloganFile($uri['input'])
				&& isset($_SERVER['REQUEST_METHOD']) && ($_SERVER['REQUEST_METHOD'] === 'GET' || $_SERVER['REQUEST_METHOD'] === 'HEAD') && !self::freeSloganHostRejected($service) && file_exists('interface/freeSlogan.php')) {
				$interfacePath = 'interface/freeSlogan.php';
			}
			// [보안] '.' / '..' 세그먼트·NUL 이 포함된 비정규 경로 거부 (fragment 기반 타입 판정과 실제 로드 파일 불일치 방지)
			if (!self::isCanonicalInterfacePath($interfacePath) || !file_exists($interfacePath)) { 
				// Textcube-NG: 없는 경로는 blog 형식·GET/HEAD·경로 사전 검사(예약 경로·탐색 경로 포함)를 통과하고 Host 불일치 404 대상(hostMismatchBehavior '404')이 아닐 때만 등록 페이지 처리기(interface/freeSlogan.php)로 넘긴다. 그 밖은 지금처럼 404(v3.212)
				if ($uri['interfaceType'] == 'blog' && isset($_SERVER['REQUEST_METHOD']) && ($_SERVER['REQUEST_METHOD'] === 'GET' || $_SERVER['REQUEST_METHOD'] === 'HEAD')
					&& self::freeSloganAllowed($uri['input'], isset($service['type']) ? $service['type'] : '') && !self::freeSloganHostRejected($service) && file_exists('interface/freeSlogan.php')) {
					$interfacePath = 'interface/freeSlogan.php';
				} else {
					header("HTTP/1.0 404 Not Found");exit;
				}
			}
			$uri['interfacePath'] = $this->interfacePath = $interfacePath;
			$uri['interfaceRoute'] = preg_replace('/index\.php\z/', '', $this->interfacePath);	// Textcube-NG: root 줄과 같이 끝의 'index.php' 한 번만 뗀다(rtrim 은 글자 집합으로 잘라 냄)(v3.195)
			// [보안] 실제 로드되는 interface 파일 경로 기준으로 인터페이스 타입·ACL 디렉티브 확정
			//        빈 세그먼트·선행 '&'·대소문자 차이 등으로 fragment 판정을 벗어나 owner/control 핸들러에 도달하면 owner(reader) 타입으로 승격
			$canonicalRoute = substr(preg_replace('@[\\\\/]+@', '/', $interfacePath), strlen('interface/'));
			if (preg_match('@^(owner|control)(/|$)@i', $canonicalRoute) && !in_array($uri['interfaceType'], array('owner', 'reader'))) {
				$uri['interfaceType'] = preg_match('@^owner/(network/)?reader(/|$)@i', $canonicalRoute) ? 'reader' : 'owner';
			}
			$uri['interfaceDirective'] = '/'.preg_replace('@\.php$@', '', preg_replace('@(^|/)index\.php$@', '', $canonicalRoute));
		}
		$this->uri = $uri;
	}
}
?>
