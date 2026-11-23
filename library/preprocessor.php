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

/** Pre-processor
    -------------
    * Performs Variable validation
    * Loads components and models
    * Initialization
    * Checks privilege
*/
$bootFiles = array();	// From PHP 5.3, DirectoryIterator does not gurantee the order.
foreach (new DirectoryIterator(ROOT.'/framework/boot') as $fileInfo) {
	if($fileInfo->isFile() && substr($fileInfo->getBasename(),-3) == 'php') array_push($bootFiles, $fileInfo->getPathname());
}
sort($bootFiles);
foreach ($bootFiles as $bf) {
	require_once($bf);
}
unset($bootFiles);

/** CHECK : 요청 본문 형식·입력 변수 개수 (Textcube-NG r3-20)
    -------------------------------------------
    Textcube-NG: 본문이 있는 POST 는 폼 형식(urlencoded·multipart)만 받는다. XML-RPC(/api)는 text/xml·application/xml 도 받는다.
    본문 없는 POST(Transfer-Encoding 에 chunked 가 없고 Content-Length 가 0 이거나 없음)는 Content-Type 과 관계없이 통과한다.
    PHP 는 max_input_vars 를 넘는 입력을 경고만 내고 버리므로, 개수가 한도에 이르면(같은 개수와 잘린 경우를 구별할 수 없다) 요청을 거부한다.
    경고는 rewrite.php 첫 줄에서 보관한 값($__tcStartupError)을 쓰고, 없을 때만 이 자리의 error_get_last() 를 쓴다.
*/
$__requestMethod = isset($_SERVER['REQUEST_METHOD']) ? strtoupper((string)$_SERVER['REQUEST_METHOD']) : 'GET';
if ($__requestMethod === 'POST') {
	$__contentType = isset($_SERVER['CONTENT_TYPE']) ? strtolower(trim(explode(';', (string)$_SERVER['CONTENT_TYPE'], 2)[0])) : '';
	$__chunked = isset($_SERVER['HTTP_TRANSFER_ENCODING']) && stripos((string)$_SERVER['HTTP_TRANSFER_ENCODING'], 'chunked') !== false;
	$__contentLength = isset($_SERVER['CONTENT_LENGTH']) ? trim((string)$_SERVER['CONTENT_LENGTH']) : '';
	$__noBody = !$__chunked && ($__contentLength === '' || preg_match('/^0+$/', $__contentLength) === 1);
	$__allowedType = ($__noBody || $__contentType === 'application/x-www-form-urlencoded' || $__contentType === 'multipart/form-data'
		|| (defined('__TEXTCUBE_XMLRPC__') && ($__contentType === 'text/xml' || $__contentType === 'application/xml')));
	if (!$__allowedType) { header('HTTP/1.1 415 Unsupported Media Type'); exit; }
}
$__maxInputVars = (int) ini_get('max_input_vars');
if ($__maxInputVars > 0) {
	$__lastError = array_key_exists('__tcStartupError', $GLOBALS) ? $GLOBALS['__tcStartupError'] : error_get_last();
	$__inputTruncated = is_array($__lastError) && isset($__lastError['message'])
		&& (strpos($__lastError['message'], 'Input variables exceeded') !== false || strpos($__lastError['message'], 'Multipart body parts limit exceeded') !== false);
	$__overSource = '';
	foreach (array('_POST' => $_POST, '_GET' => $_GET, '_COOKIE' => $_COOKIE) as $__sg => $__set) {
		$__n = 0;
		if (is_array($__set)) array_walk_recursive($__set, function () use (&$__n) { $__n++; });
		if ($__n >= $__maxInputVars) { $__overSource = $__sg; break; }
	}
	if ($__inputTruncated && $__overSource === '') $__overSource = ($__requestMethod === 'POST') ? '_POST' : '_GET';
	if ($__overSource === '_POST') { header('HTTP/1.1 413 Payload Too Large'); exit; }
	if ($__overSource !== '') { header('HTTP/1.1 400 Bad Request'); exit; }
}
unset($GLOBALS['__tcStartupError']);
unset($__requestMethod, $__contentType, $__chunked, $__contentLength, $__noBody, $__allowedType, $__maxInputVars, $__lastError, $__inputTruncated, $__overSource, $__sg, $__set, $__n);

/** CHECK : CSRF token (Textcube-NG v3.147)
    -------------------------------------------
    Textcube-NG: 요청이 보낸 CSRF 토큰(XHR 은 X-CSRF-Token 헤더, 폼은 POST csrfToken)을 아래 입력 검사가
    $IV 밖 키를 지우기 전에 보관하고 핸들러에는 넘기지 않는다($_POST 전체를 저장하는 핸들러 보호).
    URL(GET) 토큰은 받지 않는다. 판정은 library/auth.php requireStrictRoute()·requireCSRFToken().
*/
$GLOBALS['__csrfTokenSubmitted'] = '';
if (isset($_SERVER['HTTP_X_CSRF_TOKEN']) && is_string($_SERVER['HTTP_X_CSRF_TOKEN']))
	$GLOBALS['__csrfTokenSubmitted'] = $_SERVER['HTTP_X_CSRF_TOKEN'];
else if (isset($_POST['csrfToken']) && is_string($_POST['csrfToken']))
	$GLOBALS['__csrfTokenSubmitted'] = $_POST['csrfToken'];
unset($_POST['csrfToken'], $_REQUEST['csrfToken']);

/** CHECK : Basic POST/GET variable validation.
    -------------------------------------------
    Drops not allowed variables.
*/
$valid = true;
if (isset($IV)) $valid = $valid && Validator::validate($IV);

/// Basic SERVER variable validation to prevent hijacking possibility.
$basicIV = array(
	'SCRIPT_NAME' => array('string'),
	'REQUEST_URI' => array('string'),
	'REDIRECT_URL' => array('string', 'mandatory' => false)
);
$valid = $valid && Validator::validateArray($_SERVER, $basicIV);

/// Basic URI information validation. (you can skip this part.)
if(isset($URLInfo)) {
	$URLInfo['fullpath'] = urldecode($URLInfo['fullpath']);
	$basicIV = array(
		'fullpath' => array('string'),
		'input'    => array('string'),
		'position' => array('string'),
		'root'     => array('string'),
		'input'    => array('string', 'mandatory' => false)
	);
	$valid = $valid && Validator::validateArray($URLInfo, $basicIV);
}

/// Basic URI information validation.
if (!$valid) {
	header('HTTP/1.1 404 Not Found');
	exit;
}

/** LOAD : Basic Components
    --------------------
    Loads singleton base class and autoloader.
*/

/** LOAD : Configuration and Debug module (if necessary)
    --------------------
*/
global $context, $uri;
//global $config, $context, $uri;

/// Loading configuration
$context = Model_Context::getInstance(); // automatic initialization via first instanciation
$config  = Model_Config::getInstance();
$uri     = Model_URIHandler::getInstance();
/// Loading debug module
if($context->getProperty('service.debugmode') == true) {
	if(!is_null($context->getProperty('database.dbms'))) {
		require_once(ROOT. "/framework/data/".$context->getProperty('database.dbms')."/Debug.php");
	} else require_once(ROOT. "/framework/data/MySQL/Debug.php");
} else {
	if(!function_exists('dumpAsFile')) {function dumpAsFile($dummy){return true;}}
}

/** INITIALIZE : Sending header
    ---------------------------
*/
if(!defined('__TEXTCUBE_CUSTOM_HEADER__')) {
	if(defined('__TEXTCUBE_HEADER_XML__')) {
		header('Content-Type: text/xml; charset=utf-8');
	} else {
		header('Content-Type: text/html; charset=utf-8');
	}
}

/** INITIALIZE : Database I/O
    -------------------------
    Performs database connection.
*/
if(!is_null($context->getProperty('database.database'))) {
	$context->useNamespace('database');
	$db['database'] = $context->getProperty('database');
	$db['server']   = $context->getProperty('server');
	$db['port']     = $context->getProperty('port');
	$db['username'] = $context->getProperty('username');
	$db['password'] = $context->getProperty('password');
	$context->useNamespace();
	// DB SSL/TLS 옵션 전달 (config.php 의 $database['ssl'] 설정 시에만. MySQL·MariaDB·PostgreSQL·Cubrid 어댑터가 사용)
	global $database;
	if(!empty($database['ssl'])) {
		$db['ssl']        = true;
		if(!empty($database['ssl_ca']))   $db['ssl_ca']   = $database['ssl_ca'];
		if(!empty($database['ssl_cert'])) $db['ssl_cert'] = $database['ssl_cert'];
		if(!empty($database['ssl_key']))  $db['ssl_key']  = $database['ssl_key'];
		$db['ssl_verify'] = !empty($database['ssl_verify']);
	}
	if(!empty($database['driver'])) $db['driver'] = $database['driver'];	// DB 드라이버 선택(native/pdo) — Cubrid·MySQL·MySQLi·PostgreSQL·SQLite3 어댑터가 따른다
	if(POD::bind($db) === false) {
		Respond::MessagePage('Problem with connecting database.<br /><br />Please re-visit later.');
		exit;
	}
	if (in_array(POD::dbms(), array('MySQL', 'MySQLi'), true)) POD::applyStoredCharset($context->getProperty('database.prefix'));	// Textcube-NG: r4-10 — 표 변환 완료 표시(ServiceSettings databaseCharset)가 있으면 연결을 utf8mb4 로 바꾼다(캐시 불러오기 전, 사용자 결정 11-23)
	POD::cacheLoad();
	register_shutdown_function( array('POD','cacheSave') );
	$context->setProperty('database.connected',true);
	//register_shutdown_function( array('POD','unbind') );
}
$database['utf8'] = in_array(POD::charset(), array('utf8', 'utf8mb4'), true) ? true : false;	// Textcube-NG: r4-10 — utf8mb4 연결도 UTF-8
/// Memcache module bind (if possible)
global $memcache;
$memcache = null;
if($context->getProperty('service.memcached') == true):
	$memcache = new Memcache;
	$memcacheServer = !is_null($context->getProperty('memcached.server')) ? $context->getProperty('memcached.server') : 'localhost';
	$memcachePort   = !is_null($context->getProperty('memcached.port'))   ? (int) $context->getProperty('memcached.port')   : 11211;
	if (strncmp((string)$memcacheServer, 'unix://', 7) === 0) $memcachePort = 0;	// Textcube-NG: 유닉스 소켓(unix:///경로)은 포트 0(r3-20)
	// Textcube-NG: 연결에 실패하면 원인을 로그에 한 줄 남기고 $memcache 를 null 로 둔다. 이 요청은 DB 세션을 쓴다
	// (Needlworks.PHP.Loader.php 의 Session 분기). $service['memcached'] 는 바꾸지 않는다(서버 설정 저장 때 끔으로 기록되지 않게)(r3-21, R-444)
	if (!$memcache->connect($memcacheServer, $memcachePort)) {
		error_log("Memcached connection error : cannot connect to the configured memcached server; this request uses the DB session store");
		$memcache = null;
	}
	unset($memcacheServer, $memcachePort);
endif;

/** INITIALIZE : URI Parsing and specify parameters
    -----------------------------------------------
    Textcube judges blogid from its URI.
    After parsing URI-specific variables, fetch global variables (legacy support till Textcube 2)
*/
$__requireComponent = array(
	'Textcube.Core',
	'Needlworks.Cache.PageCache');
foreach($__requireComponent as $lib) {
	require ROOT .'/framework/legacy/'.$lib.'.php';
}

/* Textcube-NG: 신뢰 프록시·로드밸런서(plugins/ST_TrustedProxies, 서비스 관리자 전용). 접속 주소가 등록한 신뢰 장치·Cloudflare
   대역일 때만 그 장치의 헤더로 방문자 IP(REMOTE_ADDR)·스킴(proxy.scheme)을 정한다. 꺼짐·비활성이면 아무것도 바꾸지 않는다(v3.151) */
if (file_exists(ROOT . '/plugins/ST_TrustedProxies/lib/TrustedProxies.php')) {
	require_once ROOT . '/plugins/ST_TrustedProxies/lib/TrustedProxies.php';
	ST_TrustedProxies::apply();
}
$uri = Model_URIHandler::getInstance();
/* Textcube-NG: HSTS — config.php $service['hsts'] 가 true 이고 이 요청이 https(신뢰 프록시 규칙, URIHandler::isSecureRequest)일 때만
   Strict-Transport-Security 를 보낸다. http 응답의 HSTS 는 브라우저가 무시하므로 보내지 않는다(RFC 6797 7.2)(r3-20) */
if ($context->getProperty('service.hsts', false) === true && $uri->isSecureRequest()) {
	$hstsMaxAge = (int) $context->getProperty('service.hstsMaxAge', 31536000);
	if ($hstsMaxAge < 0) $hstsMaxAge = 0;
	$hstsValue = 'max-age=' . $hstsMaxAge;
	if ($context->getProperty('service.hstsIncludeSubDomains', false) === true) $hstsValue .= '; includeSubDomains';
	if ($context->getProperty('service.hstsPreload', false) === true) $hstsValue .= '; preload';
	header('Strict-Transport-Security: ' . $hstsValue);
	unset($hstsMaxAge, $hstsValue);
}
$uri->URIParser();
$uri->VariableParser(); // Now DB-stored variables are loaded.


/** LOAD : Required components / models / views
	-------------------------------------------
	include.XXXX contains necessary file list. (XXXX : blog, owner, reader, feeder, icon)
	Loading files from the file list.
*/
/// Override mobile mode call
$browserUtil = Utils_Browser::getInstance();
/* Textcube-NG: r3-45 R-584 — 모바일 기기의 화면 선택(?mode=desktop|mobile)을 브라우저 세션 쿠키 TC_DISPLAYMODE 로 이어 간다.
   원본은 이 판정 때 세션이 아직 시작되지 않아(아래 INITIALIZE : Session) $_SESSION 을 읽을 수 없었고, 쓰는 곳 없는 displayMode 키를 읽어 선택이 이어지지 않았다.
   블로그 화면에서만 쓰고, 값은 'desktop'·'mobile' 만 받으며 쿠키에는 상수만 쓴다. */
$_tcDisplayChoice = null;
if ($uri->uri['interfaceType'] == 'blog' && $context->getProperty('blog.useiPhoneUI',true) && ($browserUtil->isMobile() == true)) {
	if (isset($_GET['mode']) && is_string($_GET['mode']) && in_array($_GET['mode'], array('desktop','mobile'), true)) {
		$_tcDisplayChoice = $_GET['mode'];
		if (!headers_sent()) {
			setCookieCompat('TC_DISPLAYMODE', ($_tcDisplayChoice == 'desktop' ? 'desktop' : ''), array('expires' => ($_tcDisplayChoice == 'desktop' ? 0 : time() - 3600), 'path' => $context->getProperty('service.path') . '/', 'secure' => (bool)$context->getProperty('service.useSSL', false), 'httponly' => true, 'samesite' => 'Lax'));
		}
	} else if (isset($_COOKIE['TC_DISPLAYMODE']) && $_COOKIE['TC_DISPLAYMODE'] === 'desktop') {
		$_tcDisplayChoice = 'desktop';
	}
}
if($context->getProperty('blog.useiPhoneUI',true) && ($browserUtil->isMobile() == true)
		&& (!isset($_GET['mode']) || $_GET['mode'] != 'desktop')
		&& ($_tcDisplayChoice !== 'desktop')	// Textcube-NG: r3-45 R-584 — 쿠키로 이어 온 데스크탑 선택
		&& (!isset($_SESSION['mode']) || !in_array((isset($_SESSION['displayMode']) ? $_SESSION['displayMode'] : null),array('desktop')))) {	// Textcube-NG: displayMode 가 없으면 원본과 같이 null 로 본다(PHP 8 Undefined array key 경고만 없앤다)(r3-5)
	$context->setProperty('blog.displaymode','mobile');
	if ($uri->uri['interfaceType'] == 'blog') {
		$uri->uri['interfaceType'] = 'mobile';
	}
	define('__TEXTCUBE_IPHONE__',true);	// Legacy flag for plugins
	$_SESSION['displaymode'] = 'mobile';
	define('__TEXTCUBE_SKIN_DIR__',ROOT.'/skin/default');
	define('__TEXTCUBE_SKIN_CUSTOM_DIR__',__TEXTCUBE_SKIN_DIR__.'/customize');
} else {
	$_SESSION['displaymode'] = 'desktop';
	define('__TEXTCUBE_SKIN_DIR__',ROOT.'/skin/blog');
	define('__TEXTCUBE_SKIN_CUSTOM_DIR__',__TEXTCUBE_SKIN_DIR__.'/customize');
	$context->setProperty('blog.displaymode','desktop');
}
/// Reading necessary file list
require_once (ROOT.'/library/include.'.$uri->uri['interfaceType'].'.php');
/// Loading files.
require_once (ROOT.'/library/include.php');

/// Delayed default skin change. (after including necessary modules.)
if ($context->getProperty('blog.displaymode','desktop')=='mobile') {
	$context->setProperty('skin.skin','lucid');
}
if ($browserUtil->isMobile() == true) {
	$context->setProperty('blog.workmode','standard');
	if ($_tcDisplayChoice !== 'desktop') {	// Textcube-NG: r3-45 R-584 — 데스크탑을 고른 블로그 요청은 위 판정대로 desktop 을 둔다(원본은 판정과 상관없이 mobile 로 덮어써 본문·사이드바·전환 링크가 모바일로 동작)
		$context->setProperty('blog.displaymode','mobile');
	}
} else {
	$context->setProperty('blog.workmode','enhanced');
}
unset($_tcDisplayChoice);	// Textcube-NG: r3-45 R-584
/// Setting global variables
//if($context->getProperty('service.legacyMode') == true) {
	$legacy = Model_LegacySupport::getInstance();
	$legacy->addSupport('URLglobals');
//}

/** INITIALIZE : Session (if necessary)
    -----------------------------------
*/
if (!defined('NO_SESSION')) {
	session_name(Session::getName());
	Session::set();
	session_set_save_handler( array('Session','open'), array('Session','close'), array('Session','read'), array('Session','write'), array('Session','destroy'), array('Session','gc') );
	session_cache_expire(1);
	setSessionCookieParamsCompat(array('lifetime' => 0, 'path' => '/', 'domain' => $context->getProperty('service.session_cookie_domain'), 'secure' => (bool)$context->getProperty('service.useSSL', false), 'httponly' => true, 'samesite' => 'Lax'));
	// Workaround for servers that modifies session cookie to its own way
	$sess_cookie_params = session_get_cookie_params();
	$context->setProperty('service.session_cookie_domain',$sess_cookie_params['domain']);
	register_shutdown_function('session_write_close');
	if (session_start() !== true) {
		header('HTTP/1.1 503 Service Unavailable');
		exit;
	}
}

/** INITIALIZE
    ----------
*/
if (!defined('NO_INITIALIZAION')) {
/** User information
    ----------------
    If connection is authenticated, load user information.
*/
	if (doesHaveMembership()) {
		$user = array('id' => getUserId());
		$user['name'] = User::getName(getUserId());
		$user['homepage'] = User::getHomePage();
	} else {
		$user = null;
	}

/** Timezone
    --------
    Blog-specific Timezone setting.
*/
	if(!is_null($context->getProperty('database.database'))) {
		$timezone = new Timezone;
		// [보안] 저장된 블로그 시간대가 유효한 식별자가 아니면(이전 버전에서 검사 없이 저장된 값 등) 서비스 기본 시간대를 쓴다
		$timezoneName = $context->getProperty('blog.timezone');
		if ($timezoneName === null || !Timezone::isValid($timezoneName))
			$timezoneName = $context->getProperty('service.timezone');
		$timezone->set($timezoneName);
		POD::setTimezone($timezoneName);
	}
/** Locale Resources
    ----------------
    Loads necessary locale resource.
    (TODO : Reduce the capacity of i18n resource by dividing blog / adminpanel setting.
*/

/// Load administration panel locale.
	if(!defined('NO_LOCALE')) {
		if($context->getProperty('uri.interfaceType') == 'reader') { $languageDomain = 'owner'; }
		else $languageDomain = $context->getProperty('uri.interfaceType');
		if($languageDomain == 'owner') {
			$language = $context->getProperty('blog.language') !== null ?  $context->getProperty('blog.language') : $context->getProperty('service.language');
		} else {
			$language = $context->getProperty('blog.blogLanguage') !== null ?  $context->getProperty('blog.blogLanguage') : $context->getProperty('service.language');
		}
		$locale = Locales::getInstance();
		$locale->setDirectory(ROOT . '/resources/locale/'.$languageDomain);
		$locale->set($language,$languageDomain);
		$locale->setDomain($languageDomain);
		$locale->setDefaultLanguage($language);
		unset($languageDomain);
		unset($language);
	}

/** Administration panel skin / editor template
    -------------------------------------------
    When necessary, loads admin panel skin information.
*/
	if(in_array($context->getProperty('uri.interfaceType'), array('owner','reader')) || defined('__TEXTCUBE_ADMINPANEL__')) {
		$adminSkinSetting = array();

		/// TODO : This is a test routine. we should abstract this.
		$browser = Utils_Browser::getInstance();
		if($browser->isMobile()) {
			$adminSkinSetting['skin'] = "/skin/admin/mobile";
		} else {
			if(!is_null($context->getProperty('service.adminskin'))) {
				$adminSkinSetting['skin'] = "/skin/admin/".$context->getProperty('service.adminskin');
			} else {
				$adminSkinSetting['skin'] = "/skin/admin/".Setting::getBlogSettingGlobal("adminSkin", "canon");
			}
		}
		// content 본문에 removeAllTags()가 적용되는 것을 방지하기 위한 프로세스를 위한 변수.
		$contentContainer = array();

		if (file_exists(__TEXTCUBE_SKIN_DIR__."/{$skinSetting['skin']}/wysiwyg.css"))
			$adminSkinSetting['editorTemplate'] = "/skin/blog/{$skinSetting['skin']}/wysiwyg.css";
		else
			$adminSkinSetting['editorTemplate'] = "/resources/style/default-wysiwyg.css";
	}
}

/** INITIALIZE : Plugin module (if necessary)
    -------------------------------------------
    Load and bind specific plugin codes and initialze them.
*/
if(in_array($context->getProperty('uri.interfaceType'), array('blog','owner','reader'))) {
	require_once(ROOT.'/library/plugins.php');
}

/** INITIALIZE : Access privilege Check
    -----------------------------------
    Checks privilege setting and block user (or connection).
*/
if($context->getProperty('uri.interfaceType') == 'blog' && !defined('__TEXTCUBE_LOGIN__')) {
	$blogVisibility = $context->getProperty('blog.visibility',2);
//	$blogVisibility = Setting::getBlogSettingGlobal('visibility',2);
	if($context->getProperty('service.requirelogin',false) == true) {
		if($blogVisibility == 0) requireOwnership();
		else requireMembership();
	} else {
		if($blogVisibility == 0) requireOwnership();
		else if($blogVisibility == 1) requireMembership();
	}
}

if(in_array($context->getProperty('uri.interfaceType'), array('owner','reader'))) {
	// Textcube-NG: 관리 화면(owner·control·reader, 팝업 포함)은 같은 출처 frame 에만 표시 — 클릭재킹 방지(v3.147)
	header('X-Frame-Options: SAMEORIGIN');
	requireOwnership();     // Check access control list
	if(!empty($_SESSION['acl'])) {
		// [보안] 원시 URL 디렉티브('/./', '//' 등 비정규 경로면 ACL 패턴 불일치 → 검사 누락) 대신
		//        Dispatcher 가 실제 로드 파일 경로로 확정한 디렉티브(uri.interfaceDirective)로 판정
		$requiredPriv = Aco::getRequiredPrivFromUrl( $context->getProperty('uri.interfaceDirective', $suri['directive']) );
		if( !empty($requiredPriv) && !Acl::check($requiredPriv) ) {
			if( in_array( 'group.administrators', $requiredPriv ) ) {
				header("location:".$blogURL ."/owner/center/dashboard"); exit;
			} else {
				header("location:".$blogURL ."/owner/entry"); exit;
			}
		}

	}
	// Textcube-NG: 상태 변경 요청(GET·HEAD 외)은 CSRF 토큰 필수 — owner/reader 전체(B안). ACL 검사 뒤에 두어 로그인 전
	// 사용자는 requireOwnership() 의 로그인 이동을 먼저 받는다. 판정은 library/auth.php requireStrictRoute()(v3.147)
	if (!in_array(isset($_SERVER['REQUEST_METHOD']) ? strtoupper((string)$_SERVER['REQUEST_METHOD']) : 'GET', array('GET', 'HEAD'), true))
		requireStrictRoute();
}

/** INITIALIZE : Cookie prefix
    -----------------------------------
    Determines cookie prefix.
 */
if ($context->getProperty('service.cookie_prefix','') == '') {
	$context->setProperty('service.cookie_prefix','Textcube'.str_replace('.','',TEXTCUBE_VERSION_ID));
}

// DBMS unbind should work after session close.
if(	$context->getProperty('database.connected') == true) {
	register_shutdown_function( array('POD','unbind') );
}
?>
