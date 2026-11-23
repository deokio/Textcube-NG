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

define('__TEXTCUBE_LOGIN__',true);

require ROOT . '/library/preprocessor.php';
/* PHP port(Textcube-NG): r3-11 점검 키·다시 점검.
   ① 일회용 점검 키: 서버 관리자가 config.php 와 같은 폴더(__TEXTCUBE_CONFIG_FILE__ 의 폴더)에 checkup.key.php 를 두 줄로 만든다.
      1행은 <?php exit; ?>(웹에서 직접 열려도 키가 나오지 않게), 2행은 16자 이상 임의 문자열(평문 키). 3행부터는 보지 않는다.
      점검은 이 파일을 include·require 하지 않고 file_get_contents 로 텍스트로만 읽는다(UTF-8 BOM 은 떼고, 1행 뒤 공백·CR 과 2행 앞뒤 공백·CR 은 무시).
      1행이 다르거나, 2행이 비었거나, 16자보다 짧거나, 심볼릭 링크(is_link)이면 키를 쓰지 않고 안내만 한다.
      파일을 저장한 때(mtime)부터 30분 안에 이 화면에서 키를 넣어(POST) 로그인하지 않고도 이 요청 하나를 블로그 소유자 점검으로 실행한다.
      키는 is_string 확인 뒤 hash_equals 로만 비교하고(해시·암호화·파생값을 어디에도 두지 않는다), 받으면 작업 전에 잠금(cache/CHECKUP_KEY_LOCK) 안에서 파일을 지운다.
      지우지 못하면 cache/CHECKUP_KEY_USED 에 받은 시각을 남기고, 키 파일을 그보다 뒤에 저장하기 전까지 키를 받지 않는다. 그 기록도 못 남기면 키를 받지 않는다.
      키 값은 화면에 내지 않는다. 키가 맞을 때만 데이터베이스 테이블 복구(REPAIR, checkupRepairTables())를 고를 수 있다.
   ② 다시 점검: 점검 기록(cache/CHECKUP)이 현재 버전과 맞으면(Checkup::isCurrent()) 아무 작업도 하지 않고 다시 점검할지 묻는다.
      다시 점검은 소유자의 POST(CSRF 토큰 확인)나 맞는 점검 키로만 한다.
   원본의 소유자 판정 doesHaveOwnership() 14곳은 $checkupAuthorized(소유자이거나 맞는 키, 그리고 ②로 건너뛰지 않음)로 바꿨다. */
/* PHP port(Textcube-NG): r4-10 데이터베이스 문자셋 utf8mb4 수동 변환(사용자 결정 11-23 — 진입점 checkup, 자동 실행 없음).
   MySQL·MySQLi 에서 서비스 관리자(group.creators, 로그인 세션)에게만 '데이터베이스 문자셋(이모지)' 절을 보인다. 점검 키로 들어온 요청은 변환하지 않는다.
   변환은 POST dbcharsetConvert + CSRF 토큰 + 점검 기록이 현재 판 + 표시(ServiceSettings databaseCharset) 없음 + 서버가 기준(MySQL 5.6+·MariaDB 10.0+)일 때만,
   요청 하나에 테이블 하나씩. 진행은 카탈로그로 판정하고 모두 끝나면 표시를 켠다(다음 요청부터 utf8mb4 연결). 되돌리는 화면은 없다(백업 복원). */
// 점검 키 파일: config.php(__TEXTCUBE_CONFIG_FILE__)와 같은 폴더의 checkup.key.php
function checkupKeyFile() {
	return dirname(__TEXTCUBE_CONFIG_FILE__) . '/checkup.key.php';
}

// 키 파일을 텍스트로만 읽는다(include·require 하지 않는다). array(상태, 키)
// 상태: missing(없음), link(심볼릭 링크), unreadable(일반 파일이 아니거나 읽지 못함), format(1행이 다름, 빈 파일 포함), empty(2행이 빔), short(16자 미만), ok
function checkupKeyRead($file) {
	clearstatcache();
	if (is_link($file))
		return array('link', null);
	if (!file_exists($file))
		return array('missing', null);
	if (!is_file($file))
		return array('unreadable', null);
	$content = @file_get_contents($file);
	if (!is_string($content))
		return array('unreadable', null);
	if (strncmp($content, "\xEF\xBB\xBF", 3) === 0)
		$content = (string)substr($content, 3);
	$lines = explode("\n", $content, 3);
	if (rtrim($lines[0], " \t\r") !== '<?php exit; ?>')
		return array('format', null);
	$key = isset($lines[1]) ? trim($lines[1], " \t\r") : '';
	if ($key === '')
		return array('empty', null);
	if ((function_exists('mb_strlen') ? mb_strlen($key, 'UTF-8') : strlen($key)) < 16)
		return array('short', null);
	return array('ok', $key);
}

// 상태(ok·missing 밖)에 맞는 안내
function checkupKeyStateText($state) {
	if ($state === 'link')
		return _text('점검 키 파일(checkup.key.php)이 심볼릭 링크여서 쓰지 않습니다. 일반 파일로 만들어 주십시오.');
	if ($state === 'format')
		return _textf('점검 키 파일(checkup.key.php)의 1행이 %1 이 아니어서 키를 쓰지 않습니다. 1행에 %1, 2행에 키를 적어 주십시오.', '<?php exit; ?>');
	if ($state === 'empty')
		return _text('점검 키 파일(checkup.key.php)의 2행에 키가 없습니다.');
	if ($state === 'short')
		return _text('점검 키가 16자보다 짧아 쓰지 않습니다. checkup.key.php 의 2행을 더 긴 임의 문자열로 바꿔 주십시오.');
	return _text('점검 키 파일(checkup.key.php)을 읽을 수 없습니다. 파일 권한을 확인해 주십시오.');
}

// 받은 기록(cache/CHECKUP_KEY_USED)이 있고 키 파일을 그 뒤에 저장하지 않았으면 true(기록을 읽을 수 없어도 true)
function checkupKeyIsBlocked($file, $marker) {
	clearstatcache();
	if (!file_exists($marker))
		return false;
	$used = @file_get_contents($marker);
	if (!is_string($used) || !preg_match('/\A\s*(\d{1,12})\s*\z/', $used, $match))
		return true;
	$modified = @filemtime($file);
	return ($modified === false) || ($modified <= intval($match[1]));
}

// 키 파일을 저장한 때부터 30분(1800초)이 지났으면 true
function checkupKeyIsExpired($file) {
	clearstatcache();
	$modified = @filemtime($file);
	return ($modified === false) || (($modified + 1800) < time());
}

// 맞는 키를 쓴다: 잠금 → 파일·기록·만료·키 다시 확인 → 키 파일을 지움(못 지우면 받은 기록).
// array(받았으면 true, array(array(안내, 성공 여부), ...))
function checkupKeyUse($file, $submitted) {
	$lock = @fopen(__TEXTCUBE_CACHE_DIR__ . '/CHECKUP_KEY_LOCK', 'c');
	if ($lock === false)
		return array(false, array(array(_text('점검 키 사용을 기록할 수 없어 키를 받지 않았습니다. cache 폴더의 쓰기 권한을 확인해 주십시오.'), false)));
	if (!flock($lock, LOCK_EX | LOCK_NB)) {
		fclose($lock);
		return array(false, array(array(_text('다른 요청이 점검 키를 처리하고 있습니다. 잠시 뒤에 다시 시도해 주십시오.'), false)));
	}
	$accepted = false;
	$notices = array();
	$marker = __TEXTCUBE_CACHE_DIR__ . '/CHECKUP_KEY_USED';
	list($state, $key) = checkupKeyRead($file);
	if ($state === 'missing') {
		$notices[] = array(_text('이미 쓴 점검 키입니다. checkup.key.php 를 지워 주십시오. 다시 쓰려면 새 키로 checkup.key.php 를 다시 저장해 주십시오.'), false);
	} else if ($state !== 'ok') {
		$notices[] = array(checkupKeyStateText($state), false);
	} else if (checkupKeyIsBlocked($file, $marker)) {
		$notices[] = array(_text('이미 쓴 점검 키입니다. checkup.key.php 를 지워 주십시오. 다시 쓰려면 새 키로 checkup.key.php 를 다시 저장해 주십시오.'), false);
	} else if (checkupKeyIsExpired($file)) {
		$notices[] = array(_text('점검 키가 만료되었습니다(checkup.key.php 를 저장한 때부터 30분). checkup.key.php 를 지우고, 필요하면 다시 만들어 주십시오.'), false);
	} else if (!is_string($submitted) || !hash_equals($key, $submitted)) {
		$notices[] = array(_text('점검 키가 맞지 않습니다.'), false);
	} else {
		clearstatcache();
		$modified = @filemtime($file);
		$removed = @unlink($file);
		clearstatcache();
		if ($removed && !file_exists($file) && !is_link($file)) {
			$accepted = true;
			$notices[] = array(_text('점검 키 파일(checkup.key.php)을 지웠습니다.'), true);
		} else {
			// 받은 시각은 지금과 키 파일 mtime 가운데 늦은 쪽(파일 서버 시계가 앞서도 같은 파일이 다시 통하지 않게)
			$stamp = (string)max(time(), ($modified === false) ? 0 : intval($modified));
			if (@file_put_contents($marker, $stamp, LOCK_EX) === strlen($stamp)) {
				$accepted = true;
				$notices[] = array(_text('점검 키 파일(checkup.key.php)을 지우지 못했습니다. 직접 지워 주십시오. 새 키로 파일을 다시 저장하기 전까지 이 키는 다시 받지 않습니다.'), false);
			} else {
				$notices[] = array(_text('점검 키 사용을 기록할 수 없어 키를 받지 않았습니다. cache 폴더의 쓰기 권한을 확인해 주십시오.'), false);
			}
		}
	}
	unset($key);
	flock($lock, LOCK_UN);
	fclose($lock);
	return array($accepted, $notices);
}

$checkupKeyAccepted = false;
$checkupKeyFormVisible = false;
$checkupKeyGuide = false;
$checkupKeyNotices = array();
$checkupIsPost = isset($_SERVER['REQUEST_METHOD']) && ($_SERVER['REQUEST_METHOD'] === 'POST');
$checkupKeyFile = checkupKeyFile();
list($checkupKeyState, $checkupKeyValue) = checkupKeyRead($checkupKeyFile);
if ($checkupKeyState === 'missing') {
	$checkupKeyGuide = true;
} else if ($checkupKeyState !== 'ok') {
	$checkupKeyNotices[] = array(checkupKeyStateText($checkupKeyState), false);
} else if (checkupKeyIsBlocked($checkupKeyFile, __TEXTCUBE_CACHE_DIR__ . '/CHECKUP_KEY_USED')) {
	$checkupKeyNotices[] = array(_text('이미 쓴 점검 키입니다. checkup.key.php 를 지워 주십시오. 다시 쓰려면 새 키로 checkup.key.php 를 다시 저장해 주십시오.'), false);
} else if (checkupKeyIsExpired($checkupKeyFile)) {
	$checkupKeyNotices[] = array(_text('점검 키가 만료되었습니다(checkup.key.php 를 저장한 때부터 30분). checkup.key.php 를 지우고, 필요하면 다시 만들어 주십시오.'), false);
} else {
	$checkupKeyFormVisible = true;
	if ($checkupIsPost && isset($_POST['checkupKey']) && ($_POST['checkupKey'] !== '')) {
		if (!is_string($_POST['checkupKey']) || !hash_equals($checkupKeyValue, $_POST['checkupKey'])) {
			$checkupKeyNotices[] = array(_text('점검 키가 맞지 않습니다.'), false);
		} else {
			list($checkupKeyAccepted, $checkupKeyUseNotices) = checkupKeyUse($checkupKeyFile, $_POST['checkupKey']);
			if ($checkupKeyAccepted) {
				$checkupKeyFormVisible = false;
				$checkupKeyNotices[] = array(_text('점검 키를 받았습니다. 이 요청 하나만 블로그 소유자 점검으로 실행합니다.'), true);
			}
			$checkupKeyNotices = array_merge($checkupKeyNotices, $checkupKeyUseNotices);
		}
	}
}
unset($_POST['checkupKey'], $_REQUEST['checkupKey'], $checkupKeyFile, $checkupKeyState, $checkupKeyValue, $checkupKeyUseNotices);

$checkupIsCurrent = Checkup::isCurrent();
$checkupRerun = $checkupKeyAccepted;
$checkupRerunRejected = false;
if (!$checkupRerun && $checkupIsPost && isset($_POST['rerun']) && doesHaveOwnership()) {
	if (__isCSRFTokenValid())
		$checkupRerun = true;
	else
		$checkupRerunRejected = true;
}
$checkupSkip = $checkupIsCurrent && !$checkupRerun;
$checkupAuthorized = (doesHaveOwnership() || $checkupKeyAccepted) && !$checkupSkip;
$checkupRepair = $checkupKeyAccepted && isset($_POST['repair']) && ($_POST['repair'] === '1');
// PHP port(Textcube-NG): r4-10 데이터베이스 문자셋(이모지) 절·변환 조건(위 머리 주석). 절은 MySQL·MySQLi·서비스 관리자·점검 키 요청 아님일 때만
$dbcharsetVisible = in_array(POD::dbms(), array('MySQL', 'MySQLi'), true) && !$checkupKeyAccepted && Acl::check('group.creators');
$dbcharsetStored = null;
$dbcharsetCollation = null;
$dbcharsetFormVisible = false;
$dbcharsetRun = false;
$dbcharsetRejected = false;
$dbcharsetStep = null;
$dbcharsetDuplicates = null;	// 바꾸면 같은 값이 되는 키 묶음(checkupDbCharsetDuplicates(), 변환 요청에서 이미 읽었으면 화면에 그대로 쓴다)
$dbcharsetAuto = $checkupIsPost && isset($_POST['dbcharsetAuto']) && ($_POST['dbcharsetAuto'] === '1');
$checkupTokenPrinted = $checkupSkip && doesHaveOwnership();	// 아래 '다시 점검' 버튼이 이미 csrfToken 을 찍는 조건
if ($dbcharsetVisible) {
	$dbcharsetStored = POD::queryCell("SELECT value FROM {$database['prefix']}ServiceSettings WHERE name = 'databaseCharset'", 0, false);
	if (!is_string($dbcharsetStored) || ($dbcharsetStored === ''))
		$dbcharsetStored = null;
	if ($dbcharsetStored === null)
		$dbcharsetCollation = POD::utf8mb4Collation();
	$dbcharsetFormVisible = ($dbcharsetStored === null) && ($dbcharsetCollation !== null) && $checkupIsCurrent;
	if ($checkupIsPost && isset($_POST['dbcharsetConvert']) && is_string($_POST['dbcharsetConvert'])) {
		if (!__isCSRFTokenValid())
			$dbcharsetRejected = true;
		else if ($dbcharsetFormVisible)
			$dbcharsetRun = true;
	}
}
if ($checkupAuthorized) {
	$codeCache = new CodeCache();
	$codeCache->flush();
}

require ROOT . '/library/model/blog.skin.php';

requireModel('common.setting');
requireModel('blog.entry');
requireModel('blog.trash');
requireModel('common.plugin');	// Textcube-NG: r4-10 — 데이터베이스 문자셋 변환 대상(등록된 플러그인 테이블, getPluginTableList())

if(!file_exists(__TEXTCUBE_CACHE_DIR__.'/CHECKUP')) $currentVersion = _text('첫번째 점검');
else $currentVersion = Checkup::version(file_get_contents(__TEXTCUBE_CACHE_DIR__.'/CHECKUP'));	// PHP port: 1행 = 원본 버전(2행은 NG 점검 표시, Checkup)
// PHP port(Textcube-NG): r3-11 '기존 버전' 표시는 '현재 버전'과 같은 꼴로 1행 뒤에 3행의 NG 버전을 붙인다(3행이 없으면 1행만). 판정에 쓰는 $currentVersion 은 1행 그대로
$currentVersionLabel = $currentVersion;
if (file_exists(__TEXTCUBE_CACHE_DIR__.'/CHECKUP') && (($checkupNgVersion = Checkup::ngVersion(@file_get_contents(__TEXTCUBE_CACHE_DIR__.'/CHECKUP'))) !== null))
	$currentVersionLabel .= ' (NG ' . $checkupNgVersion . ')';

function setSkinSettingForMigration($blogid, $name, $value, $mig = null) {
	global $database;
	$name = POD::escapeString($name);
	$value = POD::escapeString($value);
	if($mig === null)
		return POD::execute("REPLACE INTO {$database['prefix']}SkinSettingsMig VALUES('$blogid', '$name', '$value')");
	else
		return POD::execute("REPLACE INTO {$database['prefix']}SkinSettings VALUES('$blogid', '$name', '$value')");
}

function getSkinSettingForMigration($blogid, $name, $default = null) {
	global $database;
	$reserved = POD::reservedFieldNames();
	$valueField = (is_array($reserved) && in_array('value', $reserved)) ? '"value"' : 'value';
	$value = POD::queryCell("SELECT $valueField
		FROM {$database['prefix']}SkinSettingsMig
		WHERE blogid = '$blogid'
		AND name = '".POD::escapeString($name)."'");
	return ($value === null) ? $default : $value;
}

function showCheckupMessage($stat = true) {
	global $succeed;
	if($stat) {
		echo '<span class="result success">', _text('성공'), '</span></li>';
	} else {
		$succeed = false;
		echo '<span class="result fail">', _text('실패'), '</span></li>';
	}
}

function clearCache() {
	global $database, $changed, $errorlog, $memcache;
	static $isCleared = false;
	$context = Model_Context::getInstance();
	if($isCleared == true) return;
	if(!is_null($blogids = POD::queryColumn("SELECT blogid FROM {$database['prefix']}PageCacheLog"))) {
		$changed = true;
		$errorlog = false;
		echo '<li>', _textf('페이지 캐시를 초기화합니다.'), ': ';
		foreach($blogids as $ids) {
			if(CacheControl::flushAll($ids) == false) $errorlog = true;
		}
		if($errorlog == false) echo '<span class="result success">', _text('성공'), '</span></li>';
		else echo '<span class="result fail">', _text('실패'), '</span></li>';
	}
	if($context->getProperty('service.codecache',false)) {
		$changed = true;
		$errorlog = false;
		echo '<li>', _textf('코드 캐시를 초기화합니다.'), ': ';
		$code = new CodeCache();
		$code->flush();
		if($errorlog == false) echo '<span class="result success">', _text('성공'), '</span></li>';
		else echo '<span class="result fail">', _text('실패'), '</span></li>';
	}
	if(!is_null($memcache)) {
		echo '<li>', _textf('Memcached 캐시를 초기화합니다.'), ': ';
		if($memcache->flush())  echo '<span class="result success">', _text('성공'), '</span></li>';
		else echo '<span class="result fail">', _text('실패'), '</span></li>';
	}
	echo '<li>', _textf('공지사항 캐시를 초기화합니다.'), ': ';
	if(POD::execute("DELETE FROM {$database['prefix']}ServiceSettings WHERE name like 'plugin_TextcubeNotice%'"))
		echo '<span class="result success">', _text('성공'), '</span></li>';
	else echo '<span class="result fail">', _text('실패'), '</span></li>';
	/* Textcube-NG: 원본은 점검할 때마다 모든 블로그의 댓글·트랙백 휴지통(isfiltered > 0)을 보관 기간과 관계없이 비웠다(emptyTrash).
	   휴지통은 보관 기간(service.trashtimelimit)이 지난 것만 trashVan()(library/model/blog.trash.php)이 지우게 맡기고, 점검에서는 비우지 않는다(r3-9, 500). */
	$isCleared = true;
}

/* PHP port(Textcube-NG): r3-11 데이터베이스 테이블 복구(REPAIR). 맞는 점검 키로 복구를 골랐을 때만 부른다($checkupRepair).
   대상: 정의된 테이블(getDefinedTableNames())과 등록된 플러그인 테이블(getPluginTableList() 의 plugins[*]['tables'], 플러그인 데이터 관리와 같은 기준)만.
   등록과 이어지지 않은 테이블(unknown)·다른 설치로 보이는 테이블(others, isOtherInstallTableName())은 넣지 않는다.
   그 가운데 카탈로그(BASE TABLE)에 실제로 있고 이름이 [A-Za-z0-9_] 1~64자인 것만 쓴다(교집합, 대소문자 무시).
   MySQL·MySQLi: CHECK TABLE 이 손상을 알리면 엔진이 MyISAM·Aria·ARCHIVE·CSV 일 때만 REPAIR TABLE, 그 밖(InnoDB 등)은 결과만 보인다.
   PostgreSQL: REINDEX TABLE. SQLite: PRAGMA integrity_check 결과(20줄까지)를 보이고 REINDEX. 그 밖(Cubrid 등): 지원하지 않는다고 안내.
   결과는 점검 성공 여부($succeed)에 넣지 않는다. DB 가 돌려준 글은 모두 escape 해서 보인다. */
function checkupRepairTables() {
	$dbms = POD::dbms();
	if (in_array($dbms, array('MySQL', 'MySQLi'), true))
		$catalog = POD::queryColumn("SHOW FULL TABLES WHERE Table_type = 'BASE TABLE'", false);
	else if ($dbms == 'PostgreSQL')
		$catalog = POD::queryColumn("SELECT table_name FROM information_schema.tables WHERE table_schema = 'public' AND table_type = 'BASE TABLE'", false);
	else if ($dbms == 'SQLite3')
		$catalog = POD::queryColumn("SELECT name FROM sqlite_master WHERE type = 'table'", false);
	else {
		echo '<li>', htmlspecialchars(_text('데이터베이스 테이블 복구(REPAIR)'), ENT_QUOTES, 'UTF-8'), ': ', htmlspecialchars(_text('이 데이터베이스에서는 지원하지 않습니다.'), ENT_QUOTES, 'UTF-8'), ' <span class="result fail">', htmlspecialchars(_text('확인 필요'), ENT_QUOTES, 'UTF-8'), '</span></li>';
		return true;
	}
	$names = array();
	if (is_array($catalog)) {
		foreach ($catalog as $name) {
			if (is_string($name) && preg_match('/\A[A-Za-z0-9_]{1,64}\z/', $name))
				$names[strtolower($name)] = $name;
		}
	}
	$tables = array();
	foreach (getDefinedTableNames() as $name) {
		if (isset($names[strtolower($name)]))
			$tables[strtolower($name)] = $names[strtolower($name)];
	}
	// 등록된 플러그인 테이블만 더한다(getPluginTableList() 의 plugins[*]['tables']). unknown(등록과 이어지지 않음)·others(다른 설치로 보임)는 넣지 않는다
	requireModel('common.plugin');
	$pluginTables = getPluginTableList();
	if (is_array($pluginTables) && isset($pluginTables['plugins']) && is_array($pluginTables['plugins'])) {
		$caseInsensitive = !empty($pluginTables['caseInsensitive']);
		foreach ($pluginTables['plugins'] as $plugin) {
			if (!isset($plugin['tables']) || !is_array($plugin['tables']))
				continue;
			foreach ($plugin['tables'] as $name) {
				if (is_string($name) && isset($names[strtolower($name)]) && !isOtherInstallTableName($name, $caseInsensitive))
					$tables[strtolower($name)] = $names[strtolower($name)];
			}
		}
	}
	ksort($tables);
	if (empty($tables)) {
		echo '<li>', htmlspecialchars(_text('데이터베이스 테이블 복구(REPAIR)'), ENT_QUOTES, 'UTF-8'), ': ', htmlspecialchars(_text('복구할 테이블을 찾지 못했습니다.'), ENT_QUOTES, 'UTF-8'), ' <span class="result fail">', htmlspecialchars(_text('확인 필요'), ENT_QUOTES, 'UTF-8'), '</span></li>';
		return true;
	}
	$details = array();	// array(테이블, 안내, 덧붙임, 성공 여부)
	if (($dbms == 'PostgreSQL') || ($dbms == 'SQLite3')) {
		$integrityOk = true;
		if ($dbms == 'SQLite3') {
			$integrity = POD::queryColumn('PRAGMA integrity_check', false);
			$integrityOk = is_array($integrity) && (count($integrity) == 1) && ($integrity[0] === 'ok');
			$details[] = array(_text('무결성 검사(integrity_check) 결과'), is_array($integrity) ? implode("\n", array_slice($integrity, 0, 20)) : _text('테이블을 확인하지 못했습니다.'), '', $integrityOk);
		}
		$done = 0;
		$failed = 0;
		foreach ($tables as $table) {
			if (POD::execute((($dbms == 'PostgreSQL') ? 'REINDEX TABLE "' : 'REINDEX "') . $table . '"')) {
				$done++;
			} else {
				$failed++;
				$details[] = array($table, _text('색인을 다시 만들지 못했습니다.'), '', false);
			}
		}
		$summary = _textf('테이블 %1개의 색인을 다시 만들었습니다(REINDEX). 실패 %2개.', $done, $failed);
		$summaryOk = ($failed == 0) && $integrityOk;
	} else {
		$repaired = 0;
		$pending = 0;
		foreach ($tables as $table) {
			$rows = POD::queryAllWithoutCache('CHECK TABLE `' . $table . '`', 'assoc');
			if (!is_array($rows) || empty($rows)) {
				$pending++;
				$details[] = array($table, _text('테이블을 확인하지 못했습니다.'), '', false);
				continue;
			}
			$corrupt = false;
			$detail = '';
			foreach ($rows as $row) {
				$type = (isset($row['Msg_type']) && is_string($row['Msg_type'])) ? strtolower($row['Msg_type']) : '';
				$text = (isset($row['Msg_text']) && is_string($row['Msg_text'])) ? $row['Msg_text'] : '';
				if (($type === 'error') || (($type === 'status') && ($text !== 'OK') && ($text !== 'Table is already up to date'))) {
					if (!$corrupt) $detail = $text;
					$corrupt = true;
				}
			}
			if (!$corrupt)
				continue;
			$engine = POD::queryColumn("SELECT ENGINE FROM information_schema.TABLES WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = '" . POD::escapeString($table) . "'", false);
			$engine = (is_array($engine) && isset($engine[0]) && is_string($engine[0])) ? strtoupper($engine[0]) : '';
			if (!in_array($engine, array('MYISAM', 'ARIA', 'ARCHIVE', 'CSV'), true)) {
				$pending++;
				$details[] = array($table, _textf('이 테이블의 엔진(%1)은 복구하지 않고 확인 결과만 보입니다.', ($engine === '') ? '?' : $engine), $detail, false);
				continue;
			}
			$result = POD::queryAllWithoutCache('REPAIR TABLE `' . $table . '`', 'assoc');
			$ok = false;
			$error = false;
			if (is_array($result)) {
				foreach ($result as $row) {
					$type = (isset($row['Msg_type']) && is_string($row['Msg_type'])) ? strtolower($row['Msg_type']) : '';
					$text = (isset($row['Msg_text']) && is_string($row['Msg_text'])) ? $row['Msg_text'] : '';
					if ($type === 'error')
						$error = true;
					else if (($type === 'status') && ($text === 'OK'))
						$ok = true;
				}
			}
			if ($ok && !$error) {
				$repaired++;
				$details[] = array($table, _text('복구했습니다.'), $detail, true);
			} else {
				$pending++;
				$details[] = array($table, _text('복구하지 못했습니다.'), $detail, false);
			}
		}
		$summary = _textf('테이블 %1개를 확인했습니다. 복구 %2개, 확인 필요 %3개.', count($tables), $repaired, $pending);
		$summaryOk = ($pending == 0);
	}
	echo '<li>', htmlspecialchars(_text('데이터베이스 테이블 복구(REPAIR)'), ENT_QUOTES, 'UTF-8'), ': ', htmlspecialchars($summary, ENT_QUOTES, 'UTF-8'), ' <span class="result ', ($summaryOk ? 'success' : 'fail'), '">', htmlspecialchars($summaryOk ? _text('성공') : _text('확인 필요'), ENT_QUOTES, 'UTF-8'), '</span></li>';
	foreach ($details as $item) {
		echo '<li>', htmlspecialchars($item[0] . ': ', ENT_QUOTES, 'UTF-8'), nl2br(htmlspecialchars($item[1], ENT_QUOTES, 'UTF-8')),
			(($item[2] !== '') ? ' (' . htmlspecialchars($item[2], ENT_QUOTES, 'UTF-8') . ')' : ''),
			' <span class="result ', ($item[3] ? 'success' : 'fail'), '">', htmlspecialchars($item[3] ? _text('성공') : _text('확인 필요'), ENT_QUOTES, 'UTF-8'), '</span></li>';
	}
	return true;
}

/* PHP port(Textcube-NG): r4-10 데이터베이스 문자셋 utf8mb4 수동 변환(사용자 결정 11-23 — 진입점 checkup, 진행은 카탈로그로 판정, 대상은 코어 + 등록 플러그인 테이블).
   대상: 정의된 테이블(getDefinedTableNames() — r4-13 부터 휴지통 두 테이블 TrashComments·TrashRemoteResponses 포함),
   등록된 플러그인 테이블(checkupRepairTables() 와 같은 기준) 가운데 카탈로그(BASE TABLE)에 있고 이름이 [A-Za-z0-9_] 1~64자인 것. 등록되지 않은 테이블은 바꾸지 않고 목록만 보인다.
   끝난 테이블 = 테이블 콜레이션이 고른 콜레이션이고 문자 열이 모두 utf8mb4(information_schema) — 따로 기록하지 않아 끊겨도 다음 요청이 남은 테이블부터 잇는다.
   요청 하나에 테이블 하나를 ALTER 한 문장(191자를 넘는 BTREE 색인 열은 앞부분 191자로 다시 만들고 CONVERT TO CHARACTER SET)으로 바꾸고, 모두 끝나면 표시(ServiceSettings databaseCharset)를 켠다.
   ALTER 는 사본을 만든 뒤 바꿔치기라 실패·연결 끊김에도 테이블이 중간 상태로 남지 않는다. 테이블·색인·열 이름은 카탈로그 값만 쓰고, DB 가 돌려준 글은 escape 해서 보인다. */
function checkupDbCharsetTargets() {
	global $database;
	$names = array();
	$catalog = POD::queryColumn("SHOW FULL TABLES WHERE Table_type = 'BASE TABLE'", false);
	if (is_array($catalog)) {
		foreach ($catalog as $name) {
			if (is_string($name) && preg_match('/\A[A-Za-z0-9_]{1,64}\z/', $name))
				$names[strtolower($name)] = $name;
		}
	}
	$tables = array();
	$defined = getDefinedTableNames();	// Textcube-NG: r4-13 — 휴지통 두 테이블은 getDefinedTableNames() 에 들어가 따로 더하지 않는다
	foreach ($defined as $name) {
		if (isset($names[strtolower($name)]))
			$tables[strtolower($name)] = $names[strtolower($name)];
	}
	$unknown = array();
	$pluginTables = getPluginTableList();
	if (is_array($pluginTables) && isset($pluginTables['plugins']) && is_array($pluginTables['plugins'])) {
		$caseInsensitive = !empty($pluginTables['caseInsensitive']);
		foreach ($pluginTables['plugins'] as $plugin) {
			if (!isset($plugin['tables']) || !is_array($plugin['tables']))
				continue;
			foreach ($plugin['tables'] as $name) {
				if (is_string($name) && isset($names[strtolower($name)]) && !isOtherInstallTableName($name, $caseInsensitive))
					$tables[strtolower($name)] = $names[strtolower($name)];
			}
		}
	}
	if (is_array($pluginTables) && isset($pluginTables['unknown']) && is_array($pluginTables['unknown'])) {
		foreach ($pluginTables['unknown'] as $name) {
			if (is_string($name) && isset($names[strtolower($name)]) && !isset($tables[strtolower($name)]))
				$unknown[strtolower($name)] = $names[strtolower($name)];
		}
	}
	ksort($tables);
	ksort($unknown);
	return array(array_values($tables), array_values($unknown));
}

// 아직 끝나지 않은 테이블(이름 순). 카탈로그를 읽지 못하면 null
function checkupDbCharsetPending($tables, $collation) {
	$collations = array();
	$rows = POD::queryAllWithoutCache("SELECT TABLE_NAME, TABLE_COLLATION FROM information_schema.TABLES WHERE TABLE_SCHEMA = DATABASE()", 'num');
	if (!is_array($rows))
		return null;
	foreach ($rows as $row)
		$collations[strtolower((string)$row[0])] = (string)$row[1];
	$mixed = array();
	$rows = POD::queryAllWithoutCache("SELECT TABLE_NAME FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND CHARACTER_SET_NAME IS NOT NULL AND CHARACTER_SET_NAME <> 'utf8mb4' GROUP BY TABLE_NAME", 'num');
	if (!is_array($rows))
		return null;
	foreach ($rows as $row)
		$mixed[strtolower((string)$row[0])] = true;
	$pending = array();
	foreach ($tables as $table) {
		$key = strtolower($table);
		if (!isset($collations[$key]) || ($collations[$key] !== $collation) || isset($mixed[$key]))
			$pending[] = $table;
	}
	return $pending;
}

// 테이블의 색인과 열(information_schema) — array(색인 이름 => array('unique', 'type', 'long', 'columns' => array(array(열, 앞부분 길이 또는 null, 문자 열 여부), ...))).
// 'long' = 191자를 넘는 문자 열이 앞부분 없이(또는 191자보다 길게) 든 색인이고, 그 열의 앞부분 길이는 191 로 바꿔 둔다(변환 ALTER 와 중복 검사가 함께 쓴다). 읽지 못하면 null
function checkupDbCharsetIndexes($table) {
	if (!is_string($table) || !preg_match('/\A[A-Za-z0-9_]{1,64}\z/', $table))
		return null;
	$rows = POD::queryAllWithoutCache("SELECT s.INDEX_NAME, s.COLUMN_NAME, s.SUB_PART, s.NON_UNIQUE, s.INDEX_TYPE, c.DATA_TYPE, c.CHARACTER_MAXIMUM_LENGTH"
		. " FROM information_schema.STATISTICS s JOIN information_schema.COLUMNS c ON c.TABLE_SCHEMA = s.TABLE_SCHEMA AND c.TABLE_NAME = s.TABLE_NAME AND c.COLUMN_NAME = s.COLUMN_NAME"
		. " WHERE s.TABLE_SCHEMA = DATABASE() AND s.TABLE_NAME = '" . POD::escapeString($table) . "' ORDER BY s.INDEX_NAME, s.SEQ_IN_INDEX", 'num');
	if (!is_array($rows))
		return null;
	$indexes = array();
	foreach ($rows as $row) {
		$index = (string)$row[0];
		if (!isset($indexes[$index]))
			$indexes[$index] = array('unique' => ((string)$row[3] === '0'), 'type' => strtoupper((string)$row[4]), 'long' => false, 'columns' => array());
		$isChar = in_array(strtolower((string)$row[5]), array('char', 'varchar', 'tinytext', 'text', 'mediumtext', 'longtext'), true);
		$long = $isChar && ((int)$row[6] > 191) && (($row[2] === null) || ((int)$row[2] > 191));
		if ($long)
			$indexes[$index]['long'] = true;
		$indexes[$index]['columns'][] = array((string)$row[1], $long ? 191 : (($row[2] === null) ? null : (int)$row[2]), $isChar);
	}
	return $indexes;
}

/* PHP port(Textcube-NG): r4-10 변환 전 중복 검사(사용자 결정 11-23 '미리 검사·목록 안내', '검사 범위 넓힘').
   아직 끝나지 않은 테이블($tables)의 UNIQUE·PRIMARY 키 가운데 문자 열이 든 키 전부(비고유 색인·FULLTEXT·SPATIAL 제외 — 191자로 줄이지 않는 키도 콜레이션이 바뀌어 같은 값이 될 수 있다)를,
   바꾼 뒤의 문자셋·콜레이션(문자 열은 CONVERT(… USING utf8mb4) COLLATE <콜레이션>, 변환 ALTER 가 줄이는 열과 이미 앞부분 색인인 열은 그 길이의 LEFT(열, n), 숫자 열은 그대로)으로 묶어 2건 이상인 묶음을 찾는다.
   미리 보기 값은 키의 첫 문자 열.
   테이블·키·열 이름은 카탈로그 값을 [A-Za-z0-9_] 1~64자로 확인한 뒤 역따옴표로만 쓴다. 키마다 묶음 20개까지(21개째가 있으면 '20+').
   반환 array(array(테이블, 키, 묶음 수(최대 21), array(array(건수, 값 앞부분 60자), ...)), ...) — 중복이 없으면 빈 배열, 읽지 못하면 null */
function checkupDbCharsetDuplicates($tables, $collation) {
	if (!is_array($tables) || !in_array($collation, array('utf8mb4_unicode_520_ci', 'utf8mb4_0900_ai_ci'), true))
		return null;
	$found = array();
	foreach ($tables as $table) {
		$indexes = checkupDbCharsetIndexes($table);
		if (!is_array($indexes))
			return null;
		foreach ($indexes as $index => $info) {
			$index = (string)$index;
			if (!$info['unique'] || in_array($info['type'], array('FULLTEXT', 'SPATIAL'), true))
				continue;
			if (!preg_match('/\A[A-Za-z0-9_]{1,64}\z/', $index))
				return null;
			$groups = array();
			$preview = null;
			foreach ($info['columns'] as $column) {
				if (!preg_match('/\A[A-Za-z0-9_]{1,64}\z/', $column[0]))
					return null;
				$expr = '`' . $column[0] . '`';
				if ($column[1] !== null)
					$expr = 'LEFT(' . $expr . ', ' . (int)$column[1] . ')';
				if ($column[2])
					$expr = 'CONVERT(' . $expr . ' USING utf8mb4) COLLATE ' . $collation;
				$groups[] = $expr;
				if (($preview === null) && $column[2])
					$preview = $column[0];
			}
			if ($preview === null)	// 문자 열이 없는 키(숫자만)는 콜레이션과 관계없다
				continue;
			$rows = POD::queryAllWithoutCache('SELECT COUNT(*), MIN(LEFT(`' . $preview . '`, 60)) FROM `' . $table . '` GROUP BY ' . implode(', ', $groups) . ' HAVING COUNT(*) > 1 LIMIT 21', 'num');
			if (!is_array($rows))
				return null;
			if (empty($rows))
				continue;
			$items = array();
			foreach (array_slice($rows, 0, 20) as $row)
				$items[] = array((int)$row[0], (string)$row[1]);
			$found[] = array($table, $index, count($rows), $items);
		}
	}
	return $found;
}

// 테이블 하나를 바꾸는 ALTER 한 문장. 191자를 넘는 문자 열이 든 BTREE 색인은 그 열만 (191) 로 다시 만든다(다른 열의 앞부분 길이는 그대로). 만들 수 없으면 null
function checkupDbCharsetAlterSql($table, $collation) {
	if (!is_string($table) || !preg_match('/\A[A-Za-z0-9_]{1,64}\z/', $table) || !in_array($collation, array('utf8mb4_unicode_520_ci', 'utf8mb4_0900_ai_ci'), true))
		return null;
	$indexes = checkupDbCharsetIndexes($table);	// Textcube-NG: r4-10 — 색인 읽기는 중복 검사(checkupDbCharsetDuplicates())와 같은 함수
	if (!is_array($indexes))
		return null;
	$parts = array();
	foreach ($indexes as $index => $info) {
		$index = (string)$index;
		if (!$info['long'] || in_array($info['type'], array('FULLTEXT', 'SPATIAL'), true))
			continue;
		if (!preg_match('/\A[A-Za-z0-9_]{1,64}\z/', $index))
			return null;
		$columns = array();
		foreach ($info['columns'] as $column) {
			if (!preg_match('/\A[A-Za-z0-9_]{1,64}\z/', $column[0]))
				return null;
			$columns[] = '`' . $column[0] . '`' . (($column[1] === null) ? '' : '(' . $column[1] . ')');
		}
		if ($index === 'PRIMARY') {
			$parts[] = 'DROP PRIMARY KEY';
			$parts[] = 'ADD PRIMARY KEY (' . implode(', ', $columns) . ')';
		} else {
			$parts[] = 'DROP INDEX `' . $index . '`';
			$parts[] = 'ADD ' . ($info['unique'] ? 'UNIQUE KEY' : 'KEY') . ' `' . $index . '` (' . implode(', ', $columns) . ')';
		}
	}
	$parts[] = 'CONVERT TO CHARACTER SET utf8mb4 COLLATE ' . $collation;
	return 'ALTER TABLE `' . $table . '` ' . implode(', ', $parts);
}

function checkupDbCharsetLine($message, $ok) {
	echo '<li>', htmlspecialchars(_text('데이터베이스 문자셋(이모지)'), ENT_QUOTES, 'UTF-8'), ': ', htmlspecialchars($message, ENT_QUOTES, 'UTF-8'),
		' <span class="result ', ($ok ? 'success' : 'fail'), '">', htmlspecialchars($ok ? _text('성공') : _text('확인 필요'), ENT_QUOTES, 'UTF-8'), '</span></li>';
}

// 테이블 하나를 바꾼다. 반환: next(하나 바꿈, 남음)·done(모두 끝나 표시를 켬)·dup(바꾸면 같은 값이 되는 중복이 있거나 확인하지 못해 거부 — 테이블 변화 없음)·fail·lock.
// $duplicates 에는 남은 테이블의 중복 검사 결과(checkupDbCharsetDuplicates())를 돌려준다
function checkupDbCharsetStep($collation, &$duplicates) {
	global $database;
	if (!in_array($collation, array('utf8mb4_unicode_520_ci', 'utf8mb4_0900_ai_ci'), true))
		return 'fail';
	$lockName = POD::escapeString($database['prefix'] . 'dbcharset');
	if ((string)POD::queryCell("SELECT GET_LOCK('$lockName', 0)", 0, false) !== '1') {
		checkupDbCharsetLine(_text('다른 요청이 테이블을 바꾸는 중입니다. 잠시 뒤 다시 시도해 주십시오.'), false);
		return 'lock';
	}
	$status = 'fail';
	list($tables, $unknown) = checkupDbCharsetTargets();
	$pending = checkupDbCharsetPending($tables, $collation);
	if (!is_array($pending) || empty($tables)) {
		checkupDbCharsetLine(_text('바꿀 테이블을 확인하지 못했습니다.'), false);
	} else {
		if (!empty($pending)) {
			// Textcube-NG: r4-10 — 남은 테이블 어디든 바꾸면 같은 값이 되는 중복이 있으면(또는 확인하지 못하면) 이번 요청에서는 아무 테이블도 바꾸지 않는다(사용자 결정 11-23)
			$duplicates = checkupDbCharsetDuplicates($pending, $collation);
			if (!is_array($duplicates) || !empty($duplicates)) {
				checkupDbCharsetLine(is_array($duplicates) ? _text('바꾸면 같은 값이 되는 중복이 있어 바꾸지 않았습니다. 아래 "변환 전에 정리할 중복" 목록을 확인해 정리한 뒤 다시 눌러 주십시오.') : _text('바꾸면 같은 값이 되는 중복이 있는지 확인하지 못해 바꾸지 않았습니다. 잠시 뒤 다시 시도해 주십시오.'), false);
				POD::queryCell("SELECT RELEASE_LOCK('$lockName')", 0, false);
				return 'dup';
			}
			$table = $pending[0];
			$position = count($tables) - count($pending) + 1;
			$sql = checkupDbCharsetAlterSql($table, $collation);
			$error = null;
			if ($sql === null) {
				$error = _text('색인 정보를 읽지 못했거나 이름에 쓸 수 없는 문자가 있습니다');
			} else {
				ignore_user_abort(true);
				@set_time_limit(0);
				if (!POD::execute($sql)) {
					$errorCode = (int)POD::__lastErrorCode();
					$error = $errorCode . ' ' . POD::error();
					if ($errorCode == 1062)	// 중복 키: 검사 뒤에 생긴 값 등
						$error .= ' — ' . _text('바꾸면 같은 값이 되는 중복입니다. 아래 "변환 전에 정리할 중복" 목록을 확인해 주십시오');
				}
			}
			if ($error !== null) {
				checkupDbCharsetLine(_textf('데이터베이스 테이블 %1 을 utf8mb4 로 바꾸지 못했습니다(%2). 테이블은 바뀌기 전 그대로입니다. 원인을 해결한 뒤 다시 누르면 남은 테이블부터 이어서 바꿉니다.', $table, $error), false);
				POD::queryCell("SELECT RELEASE_LOCK('$lockName')", 0, false);
				return 'fail';
			}
			checkupDbCharsetLine(_textf('데이터베이스 테이블 %1 을 utf8mb4 로 바꿉니다(%2/%3)', $table, $position, count($tables)), true);
			$pending = checkupDbCharsetPending($tables, $collation);
		}
		if (is_array($pending) && empty($pending)) {
			if (Setting::setServiceSettingGlobal('databaseCharset', $collation)) {
				checkupDbCharsetLine(_text('모든 테이블을 바꿨습니다. 다음 요청부터 이모지를 저장할 수 있습니다.'), true);
				$status = 'done';
			} else {
				checkupDbCharsetLine(_text('모든 테이블을 바꿨지만 표시를 저장하지 못했습니다. 다시 누르면 표시만 다시 저장합니다.'), false);
			}
		} else if (is_array($pending)) {
			$status = 'next';
		}
	}
	POD::queryCell("SELECT RELEASE_LOCK('$lockName')", 0, false);
	return $status;
}

?>
<!DOCTYPE html PUBLIC "-//W3C//XHTML 1.1//EN" "http://www.w3.org/TR/xhtml11/DTD/xhtml11.dtd">
<html xmlns="http://www.w3.org/1999/xhtml" xml:lang="ko">
<head>
	<meta http-equiv="Content-Type" content="text/html; charset=utf-8" />
	<title><?php echo _text('텍스트큐브를 점검합니다.');?></title>
	<link rel="stylesheet" media="screen" type="text/css" href="<?php echo $service['path']?>/resources/style/setup/style.css" />
</head>
<body>
	<div id="container">
		<form id="checkup"<?php /* PHP port(Textcube-NG): r3-11 점검 키·다시 점검은 POST 로 보낸다(키가 주소에 남지 않게) */ if ($checkupKeyFormVisible || ($checkupSkip && doesHaveOwnership()) || $dbcharsetFormVisible) echo ' method="post"';?>>
			<div id="title">
				<h1><img src="<?php echo $service['path']?>/resources/style/setup/image/title.gif" width="253" height="44" alt="<?php echo _text('텍스트큐브를 점검합니다.');?>" /></h1>
			</div>

			<div id="inner">
				<h2><?php echo _text('텍스트큐브 점검을 시작합니다.');?></h2>

				<div id="content">
					<h3><?php echo _text('버전 검사');?></h3>

					<ul class="version">
						<li><?php echo _textf('기존 버전 - %1',htmlspecialchars($currentVersionLabel, ENT_QUOTES, 'UTF-8'));	/* PHP port(Textcube-NG): r3-11 3행 NG 버전 표시, escape */?></li>
						<li><?php echo _textf('현재 버전 - %1',TEXTCUBE_VERSION.' (NG '.TEXTCUBE_NG_VERSION.')');?></li>
					</ul>
<?php
	if(version_compare($currentVersion,'1.8.0','<')) {
?>
					<h3><?php echo _text('업그레이드 안내');?></h3>
					<ul id="upgradeInstruction">
						<li class="instruction"><?php echo _text('텍스트큐브 1.8 미만에서 텍스트큐브로 이전할 경우, 기본 스킨의 저장 위치가 아래와 같이 변경됩니다.').' '._text('기존의 스킨들을 새 디렉토리로 옮겨 주시기 바랍니다.');?></li>
						<li class="detail"><?php echo _textf('스킨 디렉토리 : %1 에서 %2로 변경','/skin/','/skin/blog/');?></li>
						<li class="detail"><?php echo _textf('관리 패널 스킨 디렉토리 : %1 에서 %2로 변경','/style/admin/','/skin/admin/');?></li>
					</ul>

<?php
	}
?>
<?php
	// PHP port(Textcube-NG): r3-11 점검 키 입력(키가 설정되어 쓸 수 있을 때만)·안내. 키 값은 화면에 내지 않는다
	if ($checkupKeyFormVisible || $checkupKeyGuide || !empty($checkupKeyNotices)) {
?>
					<h3><?php echo htmlspecialchars(_text('점검 키'), ENT_QUOTES, 'UTF-8');?></h3>
					<ul id="checkupKey">
<?php
		foreach ($checkupKeyNotices as $checkupKeyNotice)
			echo '<li>', htmlspecialchars($checkupKeyNotice[0], ENT_QUOTES, 'UTF-8'), ' <span class="result ', ($checkupKeyNotice[1] ? 'success' : 'fail'), '">', htmlspecialchars($checkupKeyNotice[1] ? _text('성공') : _text('확인 필요'), ENT_QUOTES, 'UTF-8'), '</span></li>';
		if ($checkupKeyGuide)
			echo '<li>', htmlspecialchars(_textf('점검 키를 쓰려면 config.php 와 같은 폴더에 checkup.key.php 파일을 두 줄로 만듭니다. 1행은 %1, 2행은 16자 이상 임의 문자열(키)입니다. 파일을 저장한 때부터 30분 안에 한 번만 쓸 수 있고, 쓰면 파일을 지웁니다.', '<?php exit; ?>'), ENT_QUOTES, 'UTF-8'), '</li>';
		if ($checkupKeyFormVisible)
			echo '<li>', htmlspecialchars(_text('점검 키를 넣으면 로그인하지 않고도 이 점검을 블로그 소유자 점검으로 한 번 실행합니다. 키를 받으면 checkup.key.php 파일을 지워 다시 쓸 수 없게 합니다.'), ENT_QUOTES, 'UTF-8'), '<br />',
				'<input type="password" name="checkupKey" value="" size="40" autocomplete="off" /> ',
				'<label><input type="checkbox" name="repair" value="1" /> ', htmlspecialchars(_text('데이터베이스 테이블 복구(REPAIR)도 실행합니다.'), ENT_QUOTES, 'UTF-8'), '</label> ',
				'<input type="submit" name="useKey" value="', htmlspecialchars(_text('키로 점검'), ENT_QUOTES, 'UTF-8'), '" /></li>';
		unset($checkupKeyNotice);
?>
					</ul>

<?php
	}
?>
					<h3><?php echo ($checkupSkip ? _text('점검 상태') : _text('변경 중'));?></h3>

					<ul id="processList">
<?php
$changed = false;
global $succeed;
$succeed = true;
// PHP port(Textcube-NG): r3-11 점검 기록이 현재 버전과 맞으면($checkupSkip) 아무 작업도 하지 않고 다시 점검할지 묻는다
if ($checkupSkip) {
	echo '<li>', htmlspecialchars(_text('이미 점검이 끝난 상태입니다. 다시 점검할까요?'), ENT_QUOTES, 'UTF-8'), '<br />', htmlspecialchars(_text('점검 기록이 현재 버전과 맞아 아무 작업도 하지 않았습니다. 다시 점검하면 점검 항목을 처음부터 다시 확인합니다.'), ENT_QUOTES, 'UTF-8');
	if ($checkupRerunRejected)
		echo '<br />', htmlspecialchars(_text('요청을 확인하지 못했습니다(보안 토큰). 화면을 새로 고친 뒤 다시 눌러 주십시오.'), ENT_QUOTES, 'UTF-8'), ' <span class="result fail">', htmlspecialchars(_text('확인 필요'), ENT_QUOTES, 'UTF-8'), '</span>';
	if (doesHaveOwnership())
		echo '<br /><input type="hidden" name="csrfToken" value="', htmlspecialchars(getCSRFToken(), ENT_QUOTES, 'UTF-8'), '" /><input type="submit" name="rerun" value="', htmlspecialchars(_text('다시 점검'), ENT_QUOTES, 'UTF-8'), '" />';
	else if ($checkupKeyFormVisible)
		echo '<br />', htmlspecialchars(_text('다시 점검하려면 위의 점검 키를 넣어 주십시오.'), ENT_QUOTES, 'UTF-8');
	else
		echo '<br />', htmlspecialchars(_text('다시 점검하려면 블로그 소유자로 로그인해 주십시오.'), ENT_QUOTES, 'UTF-8');
	echo '</li>';
}
// PHP port(Textcube-NG): r3-11 맞는 점검 키로 복구를 골랐을 때만 테이블 복구(REPAIR)
if ($checkupRepair && checkupRepairTables())
	$changed = true;
// PHP port(Textcube-NG): r4-10 서비스 관리자가 누른 데이터베이스 문자셋 변환(POST·토큰·점검 기록이 현재 판일 때만, 요청 하나에 테이블 하나)
if ($dbcharsetRejected)
	checkupDbCharsetLine(_text('요청을 확인하지 못했습니다(보안 토큰). 화면을 새로 고친 뒤 다시 눌러 주십시오.'), false);
if ($dbcharsetRun) {
	$dbcharsetStep = checkupDbCharsetStep($dbcharsetCollation, $dbcharsetDuplicates);
	if (($dbcharsetStep === 'next') || ($dbcharsetStep === 'done'))
		$changed = true;
	if ($dbcharsetStep === 'done')
		$dbcharsetStored = $dbcharsetCollation;
}
if($checkupAuthorized && $currentVersion != TEXTCUBE_VERSION && in_array(POD::dbms(),array('MySQL','MySQLi'))) {
	// From 1.6
	if (POD::queryCell("DESC {$database['prefix']}CommentsNotified id", 'Extra') == 'auto_increment') {
		$changed = true;
		echo '<li>', _text('데이터베이스 호환성을 위하여 댓글 테이블의 자동 증가 설정을 제거합니다.'), ': ';
		if (POD::execute("ALTER TABLE {$database['prefix']}Comments CHANGE id id int(11) NOT NULL")
			&& POD::execute("ALTER TABLE {$database['prefix']}CommentsNotified CHANGE id id int(11) NOT NULL")
			&& POD::execute("ALTER TABLE {$database['prefix']}CommentsNotifiedQueue CHANGE id id int(11) NOT NULL")
			&& POD::execute("ALTER TABLE {$database['prefix']}CommentsNotifiedSiteInfo CHANGE id id int(11) NOT NULL"))
			showCheckupMessage(true);
		else {
			showCheckupMessage(false);
		}
	}

	if (POD::queryCell("DESC {$database['prefix']}Trackbacks id", 'Extra') == 'auto_increment') {
		$changed = true;
		echo '<li>', _text('데이터베이스 호환성을 위하여 트랙백 테이블의 자동 증가 설정을 제거합니다.'), ': ';
		if (POD::execute("ALTER TABLE {$database['prefix']}Trackbacks CHANGE id id int(11) NOT NULL")
			&& POD::execute("ALTER TABLE {$database['prefix']}TrackbackLogs CHANGE id id int(11) NOT NULL"))
			showCheckupMessage(true);
		else {
			showCheckupMessage(false);
		}
	}

	if (POD::queryCell("DESC {$database['prefix']}Comments blogid", 'Key') != 'PRI') {
		$changed = true;
		echo '<li>', _text('데이터베이스 호환성을 위하여 댓글 테이블의 인덱스 설정을 변경합니다.'), ': ';
		if (POD::execute("ALTER TABLE {$database['prefix']}Comments DROP PRIMARY KEY, ADD PRIMARY KEY (blogid, id)")
			&& POD::execute("ALTER TABLE {$database['prefix']}CommentsNotified DROP PRIMARY KEY, ADD PRIMARY KEY (blogid, id)")
			&& POD::execute("ALTER TABLE {$database['prefix']}CommentsNotifiedQueue DROP PRIMARY KEY, ADD PRIMARY KEY (blogid, id)")
			&& POD::execute("ALTER TABLE {$database['prefix']}CommentsNotifiedSiteInfo DROP INDEX id"))
			showCheckupMessage(true);
		else {
			showCheckupMessage(false);
		}
	}

	if (!doesExistTable($database['prefix'] . 'EntriesArchive')) {
		$changed = true;
		echo '<li>', _text('글 버전 관리및 비교를 위한 테이블을 추가합니다.'), ': ';
		$query = "
		CREATE TABLE {$database['prefix']}EntriesArchive (
			blogid int(11) NOT NULL default '0',
			userid int(11) NOT NULL default '0',
			id int(11) NOT NULL,
			visibility tinyint(4) NOT NULL default '0',
			category int(11) NOT NULL default '0',
			title varchar(255) NOT NULL default '',
			slogan varchar(255) NOT NULL default '',
			content mediumtext NOT NULL,
			contentFormatter varchar(32) DEFAULT '' NOT NULL,
			contentEditor varchar(32) DEFAULT '' NOT NULL,
			location varchar(255) NOT NULL default '/',
			password varchar(32) default NULL,
			created int(11) NOT NULL default '0',
			PRIMARY KEY (blogid, id, created),
			KEY visibility (visibility),
			KEY blogid (blogid, id),
			KEY userid (userid, blogid)
			)
		";
		if (POD::execute($query . ((POD::charset() === 'utf8mb4') ? ' ' . POD::tableCharsetClause() : ' DEFAULT CHARSET=utf8')) || POD::execute($query))	// Textcube-NG: r4-10 — 표시가 있는(utf8mb4) 사이트는 utf8mb4·그 콜레이션으로 만든다. utf8 사이트는 그대로
			showCheckupMessage(true);
		else {
			showCheckupMessage(false);
		}
	}

	if (!doesExistTable($database['prefix'] . 'OpenIDUsers')) {
		$changed = true;
		echo '<li>', _text('오픈아이디 사용자 테이블을 만듭니다'), ': ';
		$query = "
		CREATE TABLE `{$database['prefix']}OpenIDUsers` (
		  blogid int(11) NOT NULL default '0',
		  openid varchar(128) NOT NULL,
		  delegatedid varchar(128) default NULL,
		  firstLogin int(11) default NULL,
		  lastLogin int(11) default NULL,
		  loginCount int(11) default NULL,
		  data text,
		  PRIMARY KEY  (blogid,openid)
		)
		";
		if (POD::execute($query . ((POD::charset() === 'utf8mb4') ? ' ' . POD::tableCharsetClause() : ' DEFAULT CHARSET=utf8')) || POD::execute($query))	// Textcube-NG: r4-10 — 표시가 있는(utf8mb4) 사이트는 utf8mb4·그 콜레이션으로 만든다. utf8 사이트는 그대로
			showCheckupMessage(true);
		else {
			showCheckupMessage(false);
		}
	}

	if (!POD::queryExistence("DESC {$database['prefix']}Comments openid")) {
		$changed = true;
		echo '<li>', _text('Comments 테이블에 openid 필드를 추가합니다.'), ': ';
		if (POD::execute("ALTER TABLE {$database['prefix']}Comments ADD openid varchar(128) NOT NULL DEFAULT '' AFTER id"))
			showCheckupMessage(true);
		else {
			showCheckupMessage(false);
		}
	}

	if (doesExistTable($database['prefix'] . 'OpenIDComments')) {
		$changed = true;
		echo '<li>', _text('오픈아이디 댓글 테이블을 기존 댓글 테이블에 병합합니다'), ': ';
		if (POD::execute("UPDATE `{$database['prefix']}Comments` AS A,`{$database['prefix']}OpenIDComments` AS B SET `A`.`openid` = `B`.`openid` WHERE `A`.`id` = `B`.`id`" )) {
		} else {
			$openids = POD::queryAll( "SELECT * from `{$database['prefix']}OpenIDComments`" );
			foreach( $openids as $rec ) {
				$_oid = POD::escapeString( $rec['openid'] );
				POD::execute( "UPDATE `{$database['prefix']}Comments` SET `openid`='$_oid' WHERE `id`={$rec['id']}" );
			}
		}
		if (POD::execute("DROP TABLE `{$database['prefix']}OpenIDComments`" ) )
			showCheckupMessage(true);
		else {
			showCheckupMessage(false);
		}
	}

	if (POD::queryExistence("DESC {$database['prefix']}Links visible")) {
		$changed = true;
		echo '<li>', _text('Links 테이블의 공개 여부 설정 필드의 속성을 변경합니다.'), ': ';
		if (POD::execute("ALTER TABLE {$database['prefix']}Links CHANGE visible visibility tinyint(4) NOT NULL DEFAULT 2"))
			showCheckupMessage(true);
		else {
			showCheckupMessage(false);
		}
	}

	if (!POD::queryExistence("DESC {$database['prefix']}Links visibility")) {
		$changed = true;
		echo '<li>', _text('Links 테이블에 공개 여부 설정 필드와 XFN 마이크로포맷을 위한 필드를 추가합니다.'), ': ';
		if (POD::execute("ALTER TABLE {$database['prefix']}Links ADD visibility tinyint(4) NOT NULL DEFAULT 2") &&
		   POD::execute("ALTER TABLE {$database['prefix']}Links ADD xfn varchar(128) NOT NULL DEFAULT ''"))
			showCheckupMessage(true);
		else
			showCheckupMessage(false);
	}

	if (POD::queryCell("DESC {$database['prefix']}Sessions updated", 'Key') != 'MUL') {
		$changed = true;
		echo '<li>', _text('동시 접속자 관리를 위하여 세션 테이블의 인덱스 설정을 변경합니다.'), ': ';
		if (POD::execute("ALTER TABLE {$database['prefix']}Sessions ADD INDEX updated (updated)"))
			showCheckupMessage(true);
		else
			showCheckupMessage(false);
	}

	if (!POD::queryExistence("DESC {$database['prefix']}RemoteResponses blogid") &&
		POD::queryCell("DESC {$database['prefix']}Trackbacks blogid", 'Key') != 'PRI') {
		$changed = true;
		echo '<li>', _text('트랙백 불러오기 속도를 개선하기 위하여 트랙백 테이블의 인덱스 설정을 변경합니다.'), ': ';
		POD::execute("ALTER TABLE {$database['prefix']}Trackbacks DROP INDEX written");
		POD::execute("ALTER TABLE {$database['prefix']}Trackbacks DROP INDEX blogid");
		POD::execute("ALTER TABLE {$database['prefix']}TrackbackLogs DROP INDEX id");
		if (POD::execute("ALTER TABLE {$database['prefix']}Trackbacks
				DROP PRIMARY KEY, ADD PRIMARY KEY (blogid, id),
				ADD INDEX blogid (blogid, isFiltered, written)")
			&&POD::execute("ALTER TABLE {$database['prefix']}TrackbackLogs
				DROP PRIMARY KEY, ADD PRIMARY KEY (blogid, entry, id), ADD UNIQUE id (blogid, id)"))
			showCheckupMessage(true);
		else
			showCheckupMessage(false);
	}

	if (POD::queryExistence("DESC {$database['prefix']}SessionVisits blog")) {
		$changed = true;
		echo '<li>', _text('SessionVisits 테이블의 블로그 정보 필드 이름을 변경합니다.'), ': ';
		if (POD::execute("ALTER TABLE {$database['prefix']}SessionVisits CHANGE blog blogid int(11) NOT NULL DEFAULT 0") &&
		(POD::execute("ALTER TABLE {$database['prefix']}SessionVisits DROP PRIMARY KEY, ADD PRIMARY KEY (id,address,blogid)")))
			showCheckupMessage(true);
		else
			showCheckupMessage(false);
	}

	if (POD::queryCell("DESC {$database['prefix']}BlogSettings name", 'Key') != 'PRI') {
		$changed = true;
		echo '<li>', _text('블로그 설정 불러오기 속도를 개선하기 위하여 블로그 설정 테이블의 인덱스 설정을 변경합니다.'), ': ';
		if (POD::execute("ALTER TABLE {$database['prefix']}BlogSettings ADD INDEX name (name,value (32))"))
			showCheckupMessage(true);
		else
			showCheckupMessage(false);
	}

	if (!POD::queryExistence("DESC {$database['prefix']}SkinSettings value") &&
	!POD::queryExistence("DESC {$database['prefix']}SkinSettings showListOnAuthor")) {
		$changed = true;
		echo '<li>', _text('스킨 설정 테이블에 저자별 페이지 출력 설정을 위한 필드를 추가합니다.'), ': ';
		if (POD::execute("ALTER TABLE {$database['prefix']}SkinSettings ADD showListOnAuthor TINYINT(4) DEFAULT 1 NOT NULL AFTER showListOnTag"))
			showCheckupMessage(true);
		else
			showCheckupMessage(false);
	}

	if (POD::queryCell("DESC {$database['prefix']}Entries draft", 'Key') != 'PRI') {
		$changed = true;
		echo '<li>', _text('엔트리 테이블의 주 인덱스에 draft를 추가합니다.'), ': ';
		if (POD::execute("ALTER TABLE {$database['prefix']}Entries DROP PRIMARY KEY, ADD PRIMARY KEY (`blogid`,`id`,`draft`,`category`,`published`)"))
			showCheckupMessage(true);
		else
			showCheckupMessage(false);
	}
	/* FROM Textcube 1.7 */
	if (!POD::queryExistence("DESC {$database['prefix']}Entries starred")) {
		$changed = true;
		echo '<li>', _text('본문 테이블에 별표 및 작성 중 글 표시를 위한 필드를 추가합니다.'), ': ';
		if (POD::execute("ALTER TABLE {$database['prefix']}Entries ADD starred TINYINT(4) DEFAULT 1 NOT NULL AFTER visibility"))
			showCheckupMessage(true);
		else
			showCheckupMessage(false);
	}

	if (POD::queryCell("DESC {$database['prefix']}Users name", 'Key') != 'UNI') {
		$changed = true;
		echo '<li>', _text('id의 도용을 막기 위하여 같은 사용자 id를 사용할 수 없도록 합니다.'), ': ';
		if(!is_null($users = POD::queryAll("SELECT userid, name FROM {$database['prefix']}Users"))) {
			// 1 : rename duplicate names.
			foreach($users as $userRow) {
				$duplicates = POD::queryAll("SELECT userid, name FROM {$database['prefix']}Users WHERE name = '".POD::escapeString($userRow['name'])."' AND userid != {$userRow['userid']}");
				if(!empty($duplicates)) {
					$count = 1;
					foreach($duplicates as $dup) {
						POD::query("UPDATE {$database['prefix']}Users SET name = '".POD::escapeString($userRow['name'])."-".$count."' WHERE userid = {$userRow['userid']}");
						$count++;
					}
				}
				unset($duplicates);
			}
			// 2: set name as unique field
			if (POD::execute("ALTER TABLE {$database['prefix']}Users ADD UNIQUE name (name)"))
				showCheckupMessage(true);
			else
				showCheckupMessage(false);
		} else {
			showCheckupMessage(false);
		}
	}
	if (!doesExistTable($database['prefix'] . 'LinkCategories')) {
		$changed = true;
		echo '<li>', _text('링크 카테고리 테이블을 만듭니다'), ': ';
		$query = "
		CREATE TABLE `{$database['prefix']}LinkCategories` (
		  pid int(11) NOT NULL default '0',
		  blogid int(11) NOT NULL default '0',
		  id int(11) NOT NULL default '0',
		  name varchar(128) NOT NULL,
		  priority int(11) NOT NULL default '0',
		  visibility tinyint(4) NOT NULL default '2',
		  PRIMARY KEY (pid),
		  UNIQUE KEY blogid (blogid, id)
		)
		";
		if (POD::execute($query . ((POD::charset() === 'utf8mb4') ? ' ' . POD::tableCharsetClause() : ' DEFAULT CHARSET=utf8')) || POD::execute($query)) {	// Textcube-NG: r4-10 — 표시가 있는(utf8mb4) 사이트는 utf8mb4·그 콜레이션으로 만든다. utf8 사이트는 그대로
			if (POD::execute("ALTER TABLE {$database['prefix']}Links
					ADD category int(11) NOT NULL DEFAULT 0 AFTER id,
					ADD pid int(11) NOT NULL DEFAULT 0 FIRST,
					CHANGE id id int(11) NOT NULL default '0'") &&
				POD::execute("UPDATE {$database['prefix']}Links
					SET pid = id") &&
				POD::execute("ALTER TABLE {$database['prefix']}Links
					DROP PRIMARY KEY,
					ADD PRIMARY KEY (pid)")) {
				showCheckupMessage(true);
			} else {
				@POD::execute("DROP TABLE {$database['prefix']}LinkCategories");
				showCheckupMessage(false);
			}
		} else {
			showCheckupMessage(false);
		}
	}
	/* FROM Textcube 1.7.3 */
	if (!is_null($notices = POD::queryAll("SELECT blogid, id, title, slogan
		FROM {$database['prefix']}Entries
		WHERE category = -2
			AND slogan = ''")) && !empty($notices)) {
		$changed = true;
		echo '<li>', _text('fancyURL이 적용되지 않는 공지 글에 슬로건을 추가합니다.'), ': ';
		foreach($notices as $notice) :
			$notice['slogan'] = getSlogan($notice['title']);
			$succeed = POD::execute("UPDATE {$database['prefix']}Entries
				SET slogan = '".POD::escapeString($notice['slogan'])."'
				WHERE blogid = {$notice['blogid']}
				AND id = {$notice['id']}");
		endforeach;
		if($succeed)
			showCheckupMessage(true);
		else
			showCheckupMessage(false);
	}
	/* From Textcube 1.7.6 */
	if (!POD::queryExistence("DESC {$database['prefix']}Filters filtertype") &&
		!strpos(POD::queryCell("DESC {$database['prefix']}Filters type", 'Type'),'whiteurl')) {
		$changed = true;
		echo '<li>', _text('필터 테이블에 예외 목록을 추가하기 위하여 필드 속성을 변경합니다.'), ': ';
		if (POD::execute("ALTER TABLE {$database['prefix']}Filters CHANGE type type ENUM('content','ip','name','url','whiteurl') NOT NULL DEFAULT 'content'"))
			showCheckupMessage(true);
		else
			showCheckupMessage(false);
	}
	/* From Textcube 1.7.7 (or 1.8) */
	if (!POD::queryExistence("DESC {$database['prefix']}RemoteResponses blogid")) {
		$changed = true;
		echo '<li>', _text('원격 댓글 지원 기능을 위해 트랙백 테이블의 이름을 변경합니다.'), ': ';
		if (
			POD::execute("RENAME TABLE {$database['prefix']}Trackbacks TO {$database['prefix']}RemoteResponses") &&
			POD::execute("RENAME TABLE {$database['prefix']}TrackbackLogs TO {$database['prefix']}RemoteResponseLogs")
		) {
			showCheckupMessage(true);
		} else
			showCheckupMessage(false);
	}

	if(!POD::queryExistence("DESC {$database['prefix']}RemoteResponses responsetype")) {
		$changed = true;
		echo '<li>', _text('트랙백 테이블에 컨텐츠 종류를 판단하기 위한 필드를 추가합니다.'), ': ';
		if (POD::execute("ALTER TABLE {$database['prefix']}RemoteResponses ADD responsetype enum('trackback','pingback') NOT NULL default 'trackback' AFTER entry") &&
			POD::execute("ALTER TABLE {$database['prefix']}RemoteResponseLogs ADD responsetype enum('trackback','pingback') NOT NULL default 'trackback' AFTER entry")
		) {
			showCheckupMessage(true);
		} else
			showCheckupMessage(false);
	}

	if (!POD::queryExistence("DESC {$database['prefix']}Entries pingbacks")) {
		$changed = true;
		echo '<li>', _text('핑백 기능을 위해 글 테이블에 핑백 필드를 추가합니다.'), ': ';
		if (POD::execute("ALTER TABLE {$database['prefix']}Entries ADD pingbacks int(11) NOT NULL default 0 AFTER trackbacks")) {
			showCheckupMessage(true);
		} else
			showCheckupMessage(false);
	}
	if (!POD::queryExistence("DESC {$database['prefix']}Privileges acl")) {
		$changed = true;
		echo '<li>', _text('권한 관리 테이블의 이름을 변경합니다.'), ': ';
		if (POD::execute("RENAME TABLE {$database['prefix']}Teamblog TO {$database['prefix']}Privileges")) {
			showCheckupMessage(true);
		} else
			showCheckupMessage(false);
	}

	// Since 1.8
	if (!DBAdapter::queryExistence("DESC {$database['prefix']}Entries longitude")) {
		$changed = true;
		echo '<li>', _text('글과 위경도 좌표 연동을 위한 필드를 추가합니다.'), ': ';
		if (DBAdapter::execute("ALTER TABLE {$database['prefix']}Entries ADD longitude FLOAT(10) NULL AFTER location") &&
			DBAdapter::execute("ALTER TABLE {$database['prefix']}Entries ADD latitude FLOAT(10) NULL AFTER longitude") &&
			DBAdapter::execute("ALTER TABLE {$database['prefix']}EntriesArchive ADD longitude FLOAT(10) NULL AFTER location") &&
			DBAdapter::execute("ALTER TABLE {$database['prefix']}EntriesArchive ADD latitude FLOAT(10) NULL AFTER longitude"))
			showCheckupMessage(true);
		else
			showCheckupMessage(false);
	}

	if (!DBAdapter::queryExistence("DESC {$database['prefix']}Comments longitude")) {
		$changed = true;
		echo '<li>', _text('댓글과 위경도 좌표 연동을 위한 필드를 추가합니다.'), ': ';
		if (DBAdapter::execute("ALTER TABLE {$database['prefix']}Comments ADD longitude FLOAT(10) NULL AFTER secret") &&
			DBAdapter::execute("ALTER TABLE {$database['prefix']}Comments ADD latitude FLOAT(10) NULL AFTER longitude"))
			showCheckupMessage(true);
		else
			showCheckupMessage(false);
	}

	if (!POD::queryExistence("DESC {$database['prefix']}RemoteResponses responsetype")) {
		$changed = true;
		echo '<li>', _text('트랙백 테이블의 호환성을 위하여 필드 이름을 변경합니다.'), ': ';
		if (POD::execute("ALTER TABLE {$database['prefix']}RemoteResponses CHANGE type responsetype ENUM('trackback','pingback') NOT NULL DEFAULT 'trackback'") &&
		(POD::execute("ALTER TABLE {$database['prefix']}RemoteResponseLogs CHANGE type responsetype ENUM('trackback','pingback') NOT NULL DEFAULT 'trackback'"))) {
			showCheckupMessage(true);
		} else {
			showCheckupMessage(false);
		}
	}
	/* From Textcube 1.7.9 */
	if (POD::queryCell("DESC {$database['prefix']}Attachments name", 'Type') == 'varchar(32)') {
		$changed = true;
		echo '<li>', _text('티스토리 데이터 백업 호환성을 위하여 첨부파일 이름 필드 크기를 확장합니다.'), ': ';
		if (POD::execute("ALTER TABLE {$database['prefix']}Attachments CHANGE name name varchar(64) NOT NULL DEFAULT ''"))
			showCheckupMessage(true);
		else {
			showCheckupMessage(false);
		}
	}
	/* From Textcube 1.7.9 (backport branch) */
	if (POD::queryCell("DESC {$database['prefix']}FeedItems id", 'Extra') == 'auto_increment') {
		$changed = true;
		echo '<li>', _text('다양한 데이터베이스 엔진 호환성을 위하여 자동 증가 설정을 제거합니다.'), ': ';
		if (
			POD::execute("ALTER TABLE {$database['prefix']}FeedItems CHANGE id id int(11) NOT NULL")
			&& POD::execute("ALTER TABLE {$database['prefix']}Feeds CHANGE id id int(11) NOT NULL")
			&& POD::execute("ALTER TABLE {$database['prefix']}Filters CHANGE id id int(11) NOT NULL")
			&& POD::execute("ALTER TABLE {$database['prefix']}Tags CHANGE id id int(11) NOT NULL")
			&& POD::execute("ALTER TABLE {$database['prefix']}Users CHANGE userid userid int(11) NOT NULL")
			)
			showCheckupMessage(true);
		else {
			showCheckupMessage(false);
		}
	}

	if (POD::queryExistence("DESC {$database['prefix']}SkinSettings recentCommentLength")) {
		$changed = true;
		echo '<li>', _text('스킨 설정 테이블의 구조를 변경합니다.'), ': ';
		if(POD::queryExistence("DESC {$database['prefix']}SkinSettings recentCommentLength")) {
			$lowerChar = true;
		} else $lowerChar = false;
		$query = "
			CREATE TABLE {$database['prefix']}SkinSettingsMig (
				blogid int(11) NOT NULL default 0,
				name varchar(32) NOT NULL default '',
				value text NOT NULL,
				PRIMARY KEY (blogid,name)
			)
		";
		if (POD::execute($query . ((POD::charset() === 'utf8mb4') ? ' ' . POD::tableCharsetClause() : ' DEFAULT CHARSET=utf8')) || POD::execute($query)) {	// Textcube-NG: r4-10 — 표시가 있는(utf8mb4) 사이트는 utf8mb4·그 콜레이션으로 만든다. utf8 사이트는 그대로
			$query = DBModel::getInstance();
			$query->reset('SkinSettings');
			if($query->doesExist()) {
				$changed = true;
				$fieldnames = array(
					'blogid',
					'skin',
					'entriesOnRecent',
					'commentsOnRecent',
					'commentsOnGuestbook',
					'archivesOnPage',
					'tagsOnTagbox',
					'tagboxAlign',
					'trackbacksOnRecent',
					'expandComment',
					'expandTrackback',
					'recentNoticeLength',
					'recentEntryLength',
					'recentCommentLength',
					'recentTrackbackLength',
					'linkLength',
					'showListOnCategory',
					'showListOnArchive',
					'showListOnTag',
					'showListOnAuthor',
					'showListOnSearch',
					'tree',
					'colorOnTree',
					'bgColorOnTree',
					'activeColorOnTree',
					'activeBgColorOnTree',
					'labelLengthOnTree',
					'showValueOnTree');
				$queryString = implode(',',$fieldnames);
				if($lowerChar) $queryString = strtolower($queryString);
				if ($skinSettings = $query->getAll($queryString)) {
					foreach($skinSettings as $skinSetting) {
						foreach($fieldnames as $fieldname) {
							if($lowerChar) {
								$origFieldName = strtolower($fieldname);
							} else $origFieldName = $fieldname;
							setSkinSettingForMigration($skinSetting['blogid'],$fieldname,$skinSetting[$origFieldName]);
						}
					}
					$checked = true;
					foreach($skinSettings as $skinSetting) {
						foreach($fieldnames as $fieldname) {
							if($lowerChar) {
								$origFieldName = strtolower($fieldname);
							} else $origFieldName = $fieldname;
							if(getSkinSettingForMigration($skinSetting['blogid'],$fieldname) != $skinSetting[$origFieldName]) {$checked = false;break;}
						}
					}
					unset($blogSettings);
					if($checked == false) {
						POD::execute("DROP TABLE {$database['prefix']}SkinSettingsMig");
						showCheckupMessage(false);
					} else {
						// Change Table
						POD::execute("DROP TABLE {$database['prefix']}SkinSettings");
						POD::execute("RENAME TABLE {$database['prefix']}SkinSettingsMig TO {$database['prefix']}SkinSettings");
						showCheckupMessage(true);
					}
				} else showCheckupMessage(false);
			} else showCheckupMessage(false);
		} else showCheckupMessage(false);
	}


	if (!POD::queryExistence("DESC {$database['prefix']}DailyStatistics datemark")) {
		$changed = true;
		echo '<li>', _text('다양한 데이터베이스 엔진 호환성을 위하여 모든 필드의 이름을 소문자로 변환합니다.'), ': ';
		if (
			POD::execute("ALTER TABLE {$database['prefix']}Categories
				CHANGE entriesInLogin entriesinlogin int(11) NOT NULL default 0,
				CHANGE bodyId bodyid varchar(20) DEFAULT NULL")
			&& POD::execute("ALTER TABLE {$database['prefix']}Comments
				DROP KEY isFiltered,
				CHANGE isFiltered isfiltered int(11) NOT NULL DEFAULT 0,
				ADD KEY isfiltered (isfiltered)")
			&& POD::execute("ALTER TABLE {$database['prefix']}CommentsNotified
				CHANGE siteId siteid int(11) NOT NULL default 0,
				CHANGE isNew isnew int(1) NOT NULL default 1,
				CHANGE remoteId remoteid int(11) NOT NULL DEFAULT 0,
				CHANGE entryTitle entrytitle varchar(255) NOT NULL DEFAULT '',
				CHANGE entryURL entryurl varchar(255) NOT NULL DEFAULT ''")
			&& POD::execute("ALTER TABLE {$database['prefix']}CommentsNotifiedQueue
				CHANGE commentId commentid int(11) NOT NULL default 0,
				CHANGE sendStatus sendstatus int(1) NOT NULL default 0,
				CHANGE checkDate checkdate int(11) NOT NULL DEFAULT 0")
			&& POD::execute("ALTER TABLE {$database['prefix']}DailyStatistics
				DROP PRIMARY KEY,
				CHANGE date datemark int(11) NOT NULL default 0,
				ADD PRIMARY KEY (blogid, datemark)")
			&& POD::execute("ALTER TABLE {$database['prefix']}Entries
				CHANGE contentFormatter contentformatter varchar(32) DEFAULT '' NOT NULL,
				CHANGE contentEditor contenteditor varchar(32) DEFAULT '' NOT NULL,
				CHANGE acceptComment acceptcomment int(1) NOT NULL DEFAULT 1,
				CHANGE acceptTrackback accepttrackback int(1) NOT NULL DEFAULT 1")
			&& POD::execute("ALTER TABLE {$database['prefix']}EntriesArchive
				CHANGE contentFormatter contentformatter varchar(32) DEFAULT '' NOT NULL,
				CHANGE contentEditor contenteditor varchar(32) DEFAULT '' NOT NULL")
			&& POD::execute("ALTER TABLE {$database['prefix']}FeedGroupRelations
				DROP PRIMARY KEY,
				CHANGE groupId groupid int(11) NOT NULL default 0,
				ADD PRIMARY KEY (blogid, feed, groupid)")
			&& POD::execute("ALTER TABLE {$database['prefix']}FeedSettings
				CHANGE updateCycle updatecycle int(11) NOT NULL DEFAULT 120,
				CHANGE feedLife feedlife int(11) NOT NULL DEFAULT 30,
				CHANGE loadImage loadimage int(11) NOT NULL DEFAULT 1,
				CHANGE allowScript allowscript int(11) NOT NULL DEFAULT 2,
				CHANGE newWindow newwindow int(11) NOT NULL DEFAULT 1")
			&& POD::execute("ALTER TABLE {$database['prefix']}Feeds
				CHANGE xmlURL xmlurl varchar(255) NOT NULL DEFAULT '',
				CHANGE blogURL blogurl varchar(255) NOT NULL DEFAULT ''")
			&& POD::execute("ALTER TABLE {$database['prefix']}OpenIDUsers
				CHANGE firstLogin firstlogin int(11) default NULL,
				CHANGE lastLogin lastlogin int(11) default NULL")
			&& POD::execute("ALTER TABLE {$database['prefix']}OpenIDUsers
				CHANGE data openidinfo text,
				CHANGE firstLogin firstlogin int(11) default NULL,
				CHANGE loginCount logincount int(11) default NULL,
				CHANGE lastLogin lastlogin int(11) default NULL")
			&& POD::execute("ALTER TABLE {$database['prefix']}Sessions
				CHANGE data privilege text")
			&& POD::execute("ALTER TABLE {$database['prefix']}RemoteResponses
				DROP KEY isFiltered,
				CHANGE isFiltered isfiltered int(11) NOT NULL default 0,
				ADD KEY isfiltered (isfiltered)")
			&& POD::execute("ALTER TABLE {$database['prefix']}Users
				CHANGE lastLogin lastlogin int(11) NOT NULL default 0")
			&& POD::execute("ALTER TABLE {$database['prefix']}Privileges
				CHANGE lastLogin lastlogin int(11) NOT NULL default 0")
			&& POD::execute("ALTER TABLE {$database['prefix']}XMLRPCPingSettings
				CHANGE type pingtype varchar(32) NOT NULL default 'xmlrpc'")
			)
			showCheckupMessage(true);
		else {
			showCheckupMessage(false);
		}
	}

	if (!POD::queryExistence("DESC {$database['prefix']}FeedSettings loadimage")) {
		$changed = true;
		echo '<li>', _text('피드 설정 테이블의 테이블 구조 오류를 보정합니다.'), ': ';
		if (POD::execute("ALTER TABLE {$database['prefix']}FeedSettings CHANGE loadimge loadimage int(11) NOT NULL DEFAULT 1")) {
			showCheckupMessage(true);
		} else {
			showCheckupMessage(false);
		}
	}

	if (!POD::queryExistence("DESC {$database['prefix']}OpenIDUsers loginCount")) {
		$changed = true;
		echo '<li>', _text('OpenID 사용자 테이블의 테이블 구조 오류를 보정합니다.'), ': ';
		if (POD::execute("ALTER TABLE {$database['prefix']}OpenIDUsers CHANGE loginCount logincount int(11) default NULL")) {
			showCheckupMessage(true);
		} else {
			showCheckupMessage(false);
		}
	}

	if (!POD::queryExistence("DESC {$database['prefix']}Filters filtertype")) {
		$changed = true;
		echo '<li>', _text('필터 호환성을 위하여 필드의 이름을 변경합니다.'), ': ';
		if (POD::execute("ALTER TABLE {$database['prefix']}Filters
				CHANGE type filtertype enum('content','ip','name','url','whiteurl') NOT NULL default 'content'")) {
			showCheckupMessage(true);
		} else {
			showCheckupMessage(false);
		}
	}

	if (!doesExistTable($database['prefix'] . 'Lines')) {
		$changed = true;
		echo '<li>', _text('라인 기능을 위한 테이블을 만듭니다'), ': ';
		// Textcube-NG: r4-13 — TEXT 열의 default '' 를 뺌(설치 SQL 과 같게). MySQL STRICT(8.0 기본)에서 1101 로 표를 만들지 못했고, 비STRICT MySQL 은 원래 이 기본값을 버렸다(dojoref 2.5)
		$query = "
		CREATE TABLE {$database['prefix']}Lines (
		  id int(11) NOT NULL default 0,
		  blogid int(11) NOT NULL default 0,
		  root varchar(11) NOT NULL default 'default',
		  category varchar(11) NOT NULL default 'public',
		  author varchar(32) NOT NULL default '',
		  content mediumtext NOT NULL,
		  permalink varchar(128) NOT NULL default '',
		  created int(11) NOT NULL default 0,
		  PRIMARY KEY (id),
		  UNIQUE KEY (blogid, created),
		  KEY (blogid, category, created)
		)
		";
		if (POD::execute($query . ((POD::charset() === 'utf8mb4') ? ' ' . POD::tableCharsetClause() : ' DEFAULT CHARSET=utf8')) || POD::execute($query))	// Textcube-NG: r4-10 — 표시가 있는(utf8mb4) 사이트는 utf8mb4·그 콜레이션으로 만든다. utf8 사이트는 그대로
			showCheckupMessage(true);
		else {
			showCheckupMessage(false);
		}
	}

	if (!DBAdapter::queryExistence("DESC {$database['prefix']}Lines permalink")) {
		$changed = true;
		echo '<li>', _text('라인 기능에 여러 라인의 통합을 위한 필드를 추가합니다.'), ': ';
		if (DBAdapter::execute("ALTER TABLE {$database['prefix']}Lines ADD root varchar(11) NOT NULL default 'default' AFTER blogid") &&
			DBAdapter::execute("ALTER TABLE {$database['prefix']}Lines ADD author varchar(32) NOT NULL default '' AFTER category") &&
			DBAdapter::execute("ALTER TABLE {$database['prefix']}Lines ADD permalink varchar(128) NOT NULL default '' AFTER content"))
			showCheckupMessage(true);
		else
			showCheckupMessage(false);
	}

	if (!doesExistTable($database['prefix'] . 'Widgets')) {
		$changed = true;
		echo '<li>', _text('위젯 기능 및 오픈소셜 지원을 위한 테이블을 만듭니다'), ': ';
		// Textcube-NG: r4-13 — content text 의 default '' 를 뺌(설치 SQL 과 같게, MySQL STRICT 1101 — dojoref 2.5)
		$query = "
		CREATE TABLE {$database['prefix']}Widgets (
		  id int(11) NOT NULL default 1,
		  blogid int(11) NOT NULL default 1,
		  title varchar(64) NOT NULL default 'Widget',
		  author varchar(32) NOT NULL default 'Textcube',
		  email varchar(32) DEFAULT NULL,
		  screenshot varchar(128) DEFAULT NULL,
		  thumbnail varchar(128) DEFAULT NULL,
		  titleurl varchar(128) DEFAULT NULL,
		  authorlink varchar(128) default null,
		  authorlocation varchar(32) default null,
		  authorphoto varchar(128) DEFAULT NULL,
		  height int(11) DEFAULT NULL,
		  scrolling int(1) default 0,
		  feature varchar(32) default 'opensocial',
		  content text NOT NULL,
		  PRIMARY KEY(id),
		  KEY(blogid)
		)
		";
		if (POD::execute($query . ((POD::charset() === 'utf8mb4') ? ' ' . POD::tableCharsetClause() : ' DEFAULT CHARSET=utf8')) || POD::execute($query))	// Textcube-NG: r4-10 — 표시가 있는(utf8mb4) 사이트는 utf8mb4·그 콜레이션으로 만든다. utf8 사이트는 그대로
			showCheckupMessage(true);
		else {
			showCheckupMessage(false);
		}
	}
	/* From Textcube 1.8.4 */
	if (!POD::queryExistence("DESC {$database['prefix']}RemoteResponseLogs responsetype")) {
		$changed = true;
		echo '<li>', _text('트랙백과 핑백의 출력을 위하여 필드 속성을 변경합니다.'), ': ';
		if (POD::execute("ALTER TABLE {$database['prefix']}RemoteResponseLogs CHANGE type responsetype ENUM('trackback','pingback') NOT NULL DEFAULT 'trackback'"))
			showCheckupMessage(true);
		else
			showCheckupMessage(false);
	}

	if(Setting::getServiceSetting('useNewPluginSetting',false,true) != true) {
		$changed = true;
		echo '<li>', _text('플러그인 환경 설정 저장 방식을 변경합니다.'), ': ';
		$query = DBModel::getInstance();
		$query->reset('Plugins');
		if($candidates = $query->getAll()) {
			foreach ($candidates as $c) {
				if(!is_null($c['settings'])) {
					$query->reset('Plugins');
					$query->setQualifier('blogid','equals',$c['blogid']);
					$query->setQualifier('name','equals',$c['name'],true);
					$query->setAttribute('settings',serialize(Setting::fetchConfigXML($c['settings'])),true);
					$query->update();
				}
			}
			Setting::setServiceSetting('useNewPluginSetting',true,true);
			showCheckupMessage(true);
		} else {
			showCheckupMessage(false);
		}
	}
	/* From Textcube 1.9 */
	if (version_compare($currentVersion, '1.10.7','<')) {
		$changed = true;
		echo '<li>', _text('기본 에디터를 변경합니다.'), ': ';
		$query = DBModel::getInstance();
		$query->reset('BlogSettings');
		$query->setQualifier('name','equals','defaultEditor',true);
		$query->setQualifier('value','equals','modern',true);
		$query->setAttribute('value','tinyMCE',true);
		if($query->update())
			showCheckupMessage(true);
		else
			showCheckupMessage(false);

		echo '<li>', _text('기존 포스트의 에디터를 modern에서 tinyMCE로 변환합니다.'), ': ';
		$query = DBModel::getInstance();
		$query->reset('Entries');
		$query->setQualifier('contenteditor','equals','modern',true);
		$query->setAttribute('contenteditor','tinyMCE',true);
		if($query->update())
			showCheckupMessage(true);
		else
			showCheckupMessage(false);
	}
}

/* PHP port: idx_name_value on BlogSettings — required for secondaryDomain lookup.
   Runs on every checkup regardless of version, since mig DBs may be at current
   version already but were created before this index was added. */
// PHP port(Textcube-NG): r3-10 R-277 블로그 소유자(doesHaveOwnership)의 점검일 때만 확인·변경한다 — 로그인하지 않은 방문이 checkup 을 열어도 테이블·함수를 바꾸지 않음(아래 Sessions 색인 블록과 같은 방식)
if ($checkupAuthorized && in_array(POD::dbms(), array('MySQL', 'MySQLi')) &&
	!POD::queryCell("SHOW INDEX FROM {$database['prefix']}BlogSettings WHERE Key_name = 'idx_name_value'", 'Key_name')) {
	$changed = true;
	echo '<li>', _text('secondaryDomain 검색 성능을 위하여 블로그 설정 테이블에 인덱스를 추가합니다.'), ': ';
	if (DBAdapter::execute("ALTER TABLE {$database['prefix']}BlogSettings ADD INDEX idx_name_value (name, value(64))"))
		showCheckupMessage(true);
	else
		showCheckupMessage(false);
}

/* Users.password: $service['passwordHash'] 가 'multi'·'migrate' 일 때 password_hash() 값(현재 bcrypt 60자, 기본 알고리즘
   변경 대비 255자)을 저장할 수 있도록 열을 넓힌다(원본 varchar(32) 는 md5 전용). 기본 'md5' 방식에서는 테이블을 바꾸지 않는다.
   버전과 무관하게 매 점검 시 확인(이미 현재 버전인 DB 포함). 넓히기 전에는 Auth::storePassword() 가 저장값을 확인해
   md5 로 저장한다(로그인 유지). */
// PHP port(Textcube-NG): r3-10 R-277 블로그 소유자(doesHaveOwnership)의 점검일 때만 확인·변경한다 — 로그인하지 않은 방문이 checkup 을 열어도 테이블·함수를 바꾸지 않음(아래 Sessions 색인 블록과 같은 방식)
if ($checkupAuthorized && (Auth::passwordHashMode() != 'md5') && in_array(POD::dbms(), array('MySQL', 'MySQLi')) &&
	preg_match('/^(var)?char\((\d+)\)/i', (string)POD::queryCell("SHOW COLUMNS FROM {$database['prefix']}Users LIKE 'password'", 'Type', false), $passwordColumn) &&
	(intval($passwordColumn[2]) < 255)) {
	$changed = true;
	echo '<li>', _text('비밀번호 해시 저장을 위하여 사용자 테이블의 비밀번호 필드 길이를 늘립니다.'), ': ';
	if (DBAdapter::execute("ALTER TABLE {$database['prefix']}Users MODIFY password varchar(255) default NULL"))
		showCheckupMessage(true);
	else
		showCheckupMessage(false);
}

/* PostgreSQL 호환 함수(resources/setup/compatibility.PostgreSQL.sql, 설치 시 생성): 원본 SQL 은 PostgreSQL 12 에서 없어진
   abstime 형, 따옴표로 감싼 LANGUAGE 'SQL', 함수 본문 안의 ';'(설치 화면은 ';' 로 나눠 실행), standard_conforming_strings
   (9.1 부터 기본 on)에서 문법 오류가 되는 \' 때문에 함수가 만들어지지 않았고, year() 는 '99' 형식이라 연도가 두 자리였다
   (보관 목록·달력 질의 실패). 버전과 무관하게 매 점검 시 함수 결과를 확인해 다르면 고친 SQL 로 다시 만든다. */
// PHP port(Textcube-NG): r3-10 R-277 블로그 소유자(doesHaveOwnership)의 점검일 때만 확인·변경한다 — 로그인하지 않은 방문이 checkup 을 열어도 테이블·함수를 바꾸지 않음(아래 Sessions 색인 블록과 같은 방식)
if ($checkupAuthorized && POD::dbms() == 'PostgreSQL') {
	// Textcube-NG: from_unixtime(bigint)·(numeric) 오버로드(2038년 이후 값·소수 epoch)도 확인해 기존 설치에도 만든다(v3.183)
	$compatCheck = "SELECT CASE WHEN year(TIMESTAMP '2026-10-11 12:00:00') = 2026 AND month(TIMESTAMP '2026-10-11 12:00:00') = 10"
		." AND dayofmonth(TIMESTAMP '2026-10-11 12:00:00') = 11 AND unix_timestamp(from_unixtime(1000000000)) = 1000000000"
		." AND from_unixtime(2147483648) > from_unixtime(2147483647) AND from_unixtime(1.5) > from_unixtime(1)"
		." AND unix_timestamp() = unix_timestamp(now()) THEN 1 ELSE 0 END";
	if (@POD::queryCell($compatCheck, 0, false) != 1) {	// Textcube-NG: 오버로드가 없는 기존 설치에서는 점검식이 "does not exist" 로 실패한다 — 다시 만들라는 뜻의 예상된 실패이므로 native pg_query() 경고를 내지 않는다(같은 파일의 원본 @POD::execute("DROP TABLE …LinkCategories") 와 같은 방식)(v3.222)
		$changed = true;
		echo '<li>', _text('PostgreSQL 호환 함수를 다시 만듭니다.'), ': ';
		$compatSchema = @file_get_contents(ROOT . '/resources/setup/compatibility.PostgreSQL.sql');
		if ($compatSchema !== false) {
			foreach (explode(';', trim($compatSchema)) as $compatQuery) {
				if (trim($compatQuery) != '') POD::query($compatQuery);
			}
		}
		if (POD::queryCell($compatCheck, 0, false) == 1)
			showCheckupMessage(true);
		else
			showCheckupMessage(false);
	}
}

/* Textcube-NG: 댓글 알림 대기열(CommentsNotifiedQueue)의 고유 인덱스를 commentid 하나에서 (blogid, commentid)로 바꾼다
   (resources/setup/initialize.*.sql 과 같음). commentid 는 블로그마다 따로 매기는 번호라, 두 블로그에 같은 번호의 댓글이 있으면
   뒤의 것이 대기열에 들어가지 못했다. 버전과 무관하게 매 점검 시 인덱스 열을 확인한다. 조회에 실패하면 건너뛰고, 인덱스가 없으면
   (지운 뒤 만들기에 실패한 경우 등) 새로 만든다(v3.185). */
// PHP port(Textcube-NG): r3-10 R-277 블로그 소유자(doesHaveOwnership)의 점검일 때만 확인·변경한다 — 로그인하지 않은 방문이 checkup 을 열어도 테이블·함수를 바꾸지 않음(아래 Sessions 색인 블록과 같은 방식)
if ($checkupAuthorized) {
	$queueTable = $database['prefix'] . 'CommentsNotifiedQueue';
	$queueIndexName = $queueTable . '_commentid_idx';
	$queueIndexColumns = null;
	$queueIndexDrop = "DROP INDEX $queueIndexName";
	$queueIndexCreate = "CREATE UNIQUE INDEX $queueIndexName ON $queueTable (blogid, commentid)";
	switch (POD::dbms()) {
		case 'MySQL':
		case 'MySQLi':
			$queueIndexRows = POD::queryAll("SHOW INDEX FROM $queueTable WHERE Key_name = 'commentid'", 'assoc');
			if (is_array($queueIndexRows)) {
				$queueIndexColumns = array();
				foreach ($queueIndexRows as $queueIndexRow)
					$queueIndexColumns[] = $queueIndexRow['Column_name'];
			}
			$queueIndexDrop = "ALTER TABLE $queueTable DROP INDEX commentid";
			$queueIndexCreate = "ALTER TABLE $queueTable ADD UNIQUE KEY commentid (blogid, commentid)";
			break;
		case 'PostgreSQL':
			$queueIndexColumns = POD::queryColumn("SELECT a.attname FROM pg_index i JOIN pg_class c ON c.oid = i.indexrelid"
				." JOIN pg_namespace n ON n.oid = c.relnamespace JOIN pg_attribute a ON a.attrelid = i.indrelid AND a.attnum = ANY(i.indkey)"
				." WHERE n.nspname = current_schema() AND c.relname = '" . POD::escapeString(strtolower($queueIndexName)) . "'", false);
			break;
		case 'SQLite3':
			$queueIndexRows = POD::queryAll("PRAGMA index_info('" . POD::escapeString($queueIndexName) . "')", 'assoc');
			if (is_array($queueIndexRows)) {
				$queueIndexColumns = array();
				foreach ($queueIndexRows as $queueIndexRow)
					$queueIndexColumns[] = $queueIndexRow['name'];
			}
			break;
		case 'Cubrid':
			$queueIndexColumns = POD::queryColumn("SELECT key_attr_name FROM db_index_key WHERE index_name = '" . POD::escapeString(strtolower($queueIndexName))
				."' AND class_name = '" . POD::escapeString(strtolower($queueTable)) . "'", false);
			$queueIndexDrop = "DROP INDEX $queueIndexName ON $queueTable";
			break;
	}
	if (is_array($queueIndexColumns) && !in_array('blogid', array_map('strtolower', $queueIndexColumns))) {
		$changed = true;
		echo '<li>', _text('댓글 알림 대기열 테이블의 고유 인덱스를 블로그별로 바꿉니다.'), ': ';
		if ((empty($queueIndexColumns) || POD::execute($queueIndexDrop)) && POD::execute($queueIndexCreate))
			showCheckupMessage(true);
		else
			showCheckupMessage(false);
	}
}

/* Textcube-NG: 세션 테이블(Sessions)에 접속 주소(address) 색인을 더한다(resources/setup/initialize.*.sql 과 같은 이름, r3-6, R-115).
   익명 세션 조회(framework/legacy/Textcube.Control.Session.php 의 WHERE address = … AND userid IS NULL AND preexistence IS NULL)는
   기본 키 (id, address)의 둘째 열만 조건으로 써서 테이블 전체를 읽었다. 블로그 소유자(doesHaveOwnership)의 점검일 때만, 버전과 무관하게
   매 점검 시 색인을 확인하고, 조회에 실패하면 건너뛴다(로그인하지 않은 방문은 색인을 만들지 않음). */
if ($checkupAuthorized) {
	$sessionsTable = $database['prefix'] . 'Sessions';
	$sessionsIndexName = $sessionsTable . '_address_idx';
	$sessionsIndexColumns = null;
	$sessionsIndexCreate = "CREATE INDEX $sessionsIndexName ON $sessionsTable (address)";
	switch (POD::dbms()) {
		case 'MySQL':
		case 'MySQLi':
			$sessionsIndexRows = POD::queryAll("SHOW INDEX FROM $sessionsTable WHERE Key_name = 'address'", 'assoc');
			if (is_array($sessionsIndexRows)) {
				$sessionsIndexColumns = array();
				foreach ($sessionsIndexRows as $sessionsIndexRow)
					$sessionsIndexColumns[] = $sessionsIndexRow['Column_name'];
			}
			$sessionsIndexCreate = "ALTER TABLE $sessionsTable ADD INDEX address (address)";
			break;
		case 'PostgreSQL':
			$sessionsIndexColumns = POD::queryColumn("SELECT a.attname FROM pg_index i JOIN pg_class c ON c.oid = i.indexrelid"
				." JOIN pg_namespace n ON n.oid = c.relnamespace JOIN pg_attribute a ON a.attrelid = i.indrelid AND a.attnum = ANY(i.indkey)"
				." WHERE n.nspname = current_schema() AND c.relname = '" . POD::escapeString(strtolower($sessionsIndexName)) . "'", false);
			break;
		case 'SQLite3':
			$sessionsIndexRows = POD::queryAll("PRAGMA index_info('" . POD::escapeString($sessionsIndexName) . "')", 'assoc');
			if (is_array($sessionsIndexRows)) {
				$sessionsIndexColumns = array();
				foreach ($sessionsIndexRows as $sessionsIndexRow)
					$sessionsIndexColumns[] = $sessionsIndexRow['name'];
			}
			break;
		case 'Cubrid':
			$sessionsIndexColumns = POD::queryColumn("SELECT key_attr_name FROM db_index_key WHERE index_name = '" . POD::escapeString(strtolower($sessionsIndexName))
				."' AND class_name = '" . POD::escapeString(strtolower($sessionsTable)) . "'", false);
			break;
	}
	if (is_array($sessionsIndexColumns) && empty($sessionsIndexColumns)) {
		$changed = true;
		echo '<li>', _text('세션 테이블에 접속 주소 색인을 추가합니다.'), ': ';
		if (POD::execute($sessionsIndexCreate))
			showCheckupMessage(true);
		else
			showCheckupMessage(false);
	}
}

/* Textcube-NG: 글 테이블(Entries)의 기본 키를 (blogid, id, draft, category, published)에서 (blogid, id, draft)로 바꾼다
   (resources/setup/initialize.*.sql 과 같음, r3-9, 459i). 원본 키는 분류·발행 시각까지 키에 넣어, 글 번호와 draft 가 같은 행이
   분류나 발행 시각만 달리하여 둘 이상 들어갈 수 있었다. 블로그 소유자(doesHaveOwnership)의 점검일 때만, 버전과 무관하게 매 점검 시
   기본 키 열을 확인하고 원본의 다섯 열일 때만 바꾼다(조회에 실패하거나 다른 키면 건너뜀). (blogid, id, draft)가 같은 행이 있으면
   자료를 지우지 않고 키도 바꾸지 않으며 "확인 필요"를 보이고 점검 완료 기록(CHECKUP)을 남기지 않는다(다시 점검하면 재시도). SQLite3 은 기본 키를 바꾸는 ALTER 가 없어
   한 트랜잭션 안에서 새 키의 테이블을 만들어 옮겨 담고 바꿔 끼운 뒤 색인을 다시 만든다(행 수가 다르거나 실패하면 되돌림). */
if ($checkupAuthorized) {
	$entriesTable = $database['prefix'] . 'Entries';
	$entriesKeyColumns = null;
	$entriesKeyName = null;
	switch (POD::dbms()) {
		case 'MySQL':
		case 'MySQLi':
			$entriesKeyRows = POD::queryAll("SHOW INDEX FROM $entriesTable WHERE Key_name = 'PRIMARY'", 'assoc');
			if (is_array($entriesKeyRows)) {
				$entriesKeyColumns = array();
				foreach ($entriesKeyRows as $entriesKeyRow)
					$entriesKeyColumns[] = $entriesKeyRow['Column_name'];
			}
			break;
		case 'PostgreSQL':
			$entriesKeyColumns = POD::queryColumn("SELECT a.attname FROM pg_index i JOIN pg_class c ON c.oid = i.indrelid"
				." JOIN pg_namespace n ON n.oid = c.relnamespace JOIN pg_attribute a ON a.attrelid = i.indrelid AND a.attnum = ANY(i.indkey)"
				." WHERE i.indisprimary AND n.nspname = current_schema() AND c.relname = '" . POD::escapeString(strtolower($entriesTable)) . "'", false);
			$entriesKeyName = POD::queryCell("SELECT con.conname FROM pg_constraint con JOIN pg_class c ON c.oid = con.conrelid"
				." JOIN pg_namespace n ON n.oid = c.relnamespace WHERE con.contype = 'p' AND n.nspname = current_schema()"
				." AND c.relname = '" . POD::escapeString(strtolower($entriesTable)) . "'", 0, false);
			break;
		case 'SQLite3':
			$entriesKeyRows = POD::queryAll("PRAGMA table_info('" . POD::escapeString($entriesTable) . "')", 'assoc');
			if (is_array($entriesKeyRows)) {
				$entriesKeyColumns = array();
				foreach ($entriesKeyRows as $entriesKeyRow)
					if ((int)$entriesKeyRow['pk'] > 0) $entriesKeyColumns[] = $entriesKeyRow['name'];
			}
			break;
		case 'Cubrid':
			$entriesKeyColumns = POD::queryColumn("SELECT k.key_attr_name FROM db_index i, db_index_key k WHERE i.index_name = k.index_name"
				." AND i.class_name = k.class_name AND i.is_primary_key = 'YES' AND i.class_name = '" . POD::escapeString(strtolower($entriesTable)) . "'", false);
			$entriesKeyName = POD::queryCell("SELECT index_name FROM db_index WHERE is_primary_key = 'YES'"
				." AND class_name = '" . POD::escapeString(strtolower($entriesTable)) . "'", 0, false);
			break;
	}
	if (is_array($entriesKeyColumns)) {
		$entriesKeyColumns = array_map('strtolower', $entriesKeyColumns);
		sort($entriesKeyColumns);
	}
	if (is_array($entriesKeyColumns) && $entriesKeyColumns === array('blogid', 'category', 'draft', 'id', 'published')) {
		$changed = true;
		echo '<li>', _text('글 테이블의 기본 키를 (blogid, id, draft)로 바꿉니다.'), ': ';
		$entriesDuplicates = POD::queryCell("SELECT COUNT(*) FROM (SELECT blogid, id, draft FROM $entriesTable GROUP BY blogid, id, draft HAVING COUNT(*) > 1) d", 0, false);
		if ($entriesDuplicates === null || (int)$entriesDuplicates !== 0) {
			$succeed = false;	// 점검 완료 기록을 남기지 않아 다음 점검에서 다시 시도한다
			echo _text('글 번호와 draft 가 같은 글이 둘 이상 있어 바꾸지 않았습니다. 겹치는 글을 정리한 뒤 다시 점검해 주십시오.'), ' <span class="result fail">', _text('확인 필요'), '</span></li>';
		} else {
			$entriesNewKey = '(blogid, id, draft)';
			$entriesResult = false;
			switch (POD::dbms()) {
				case 'MySQL':
				case 'MySQLi':
					$entriesResult = POD::execute("ALTER TABLE $entriesTable DROP PRIMARY KEY, ADD PRIMARY KEY $entriesNewKey");
					break;
				case 'PostgreSQL':
					if (is_string($entriesKeyName) && preg_match('/\A[A-Za-z0-9_]+\z/', $entriesKeyName))
						$entriesResult = POD::execute("ALTER TABLE $entriesTable DROP CONSTRAINT \"$entriesKeyName\", ADD PRIMARY KEY $entriesNewKey");
					break;
				case 'Cubrid':
					if (is_string($entriesKeyName) && preg_match('/\A[A-Za-z0-9_]+\z/', $entriesKeyName)
						&& POD::execute("ALTER TABLE $entriesTable DROP CONSTRAINT $entriesKeyName")) {
						$entriesResult = POD::execute("ALTER TABLE $entriesTable ADD PRIMARY KEY $entriesNewKey");
						if (!$entriesResult)	// 새 키를 만들지 못하면 원래 키를 다시 만든다
							POD::execute("ALTER TABLE $entriesTable ADD CONSTRAINT $entriesKeyName PRIMARY KEY (blogid, id, draft, category, published)");
					}
					break;
				case 'SQLite3':
					$entriesTemp = $entriesTable . '_ngkey';
					$entriesCreate = POD::queryCell("SELECT sql FROM sqlite_master WHERE type = 'table' AND name = '" . POD::escapeString($entriesTable) . "'", 0, false);
					$entriesIndexes = POD::queryColumn("SELECT sql FROM sqlite_master WHERE type = 'index' AND tbl_name = '" . POD::escapeString($entriesTable) . "' AND sql IS NOT NULL", false);
					$entriesKeyCount = 0;
					$entriesNameCount = 0;
					if (is_string($entriesCreate)) {
						$entriesCreate = preg_replace('/PRIMARY\s+KEY\s*\(\s*blogid\s*,\s*id\s*,\s*draft\s*,\s*category\s*,\s*published\s*\)/i', 'PRIMARY KEY ' . $entriesNewKey, $entriesCreate, 1, $entriesKeyCount);
						$entriesCreate = preg_replace('/\A\s*CREATE\s+TABLE\s+["`\[]?' . preg_quote($entriesTable, '/') . '["`\]]?(?=\s*\()/i', 'CREATE TABLE ' . $entriesTemp, $entriesCreate, 1, $entriesNameCount);
					}
					if ($entriesKeyCount === 1 && $entriesNameCount === 1 && (is_array($entriesIndexes) || is_null($entriesIndexes)) && POD::execute('BEGIN')) {
						$entriesResult = POD::execute($entriesCreate)
							&& POD::execute("INSERT INTO $entriesTemp SELECT * FROM $entriesTable")
							&& ((string)POD::queryCell("SELECT COUNT(*) FROM $entriesTemp", 0, false) === (string)POD::queryCell("SELECT COUNT(*) FROM $entriesTable", 0, false))
							&& POD::execute("DROP TABLE $entriesTable")
							&& POD::execute("ALTER TABLE $entriesTemp RENAME TO $entriesTable");
						if ($entriesResult && is_array($entriesIndexes)) {
							foreach ($entriesIndexes as $entriesIndexSql) {
								if (!POD::execute($entriesIndexSql)) {
									$entriesResult = false;
									break;
								}
							}
						}
						if ($entriesResult)
							$entriesResult = POD::execute('COMMIT');
						if (!$entriesResult)
							POD::rollback();
					}
					break;
			}
			showCheckupMessage($entriesResult ? true : false);
		}
	}
}

/* Textcube-NG: 소셜 로그인(CL_SocialLogin) 식별자에 앱 구분 표지를 넣는다(r3-9, 468d). 공급자 사용자 id 는 앱(client_id)마다 따로 매겨져
   '{공급자}:{id}' 만으로는 다른 블로그(다른 앱)의 같은 id 와 구별되지 않았다. 새 식별자는 '{공급자}:{표지}:{id}'
   (표지 = client_id 의 SHA-256 앞 12자, plugins/CL_SocialLogin/lib/SocialOAuth.php 의 clientTag() 와 같음).
   블로그 소유자의 점검일 때 한 번만(ServiceSettings socialIdentifierClientTag) 옛 식별자를 옮긴다. 값은 escape 해 한 행씩 바꾼다.
   - 댓글(Comments·TrashComments.openid): 댓글이 달린 블로그의 플러그인 설정(Plugins CL_SocialLogin)으로 표지를 정한다. 그 블로그에 해당 공급자의 client_id 가 없으면 건너뛴다.
   - 계정 연결(UserSettings openid.N, 사용자 전역): 사용자가 속한 블로그(Privileges)의 해당 공급자 표지로 옮긴다. 표지가 둘 이상(블로그마다 다른 앱)이면
     첫 표지(정렬 순)는 그 자리에서 바꾸고 나머지는 빈 openid.N 칸(0 ~ OPENID_REGISTERS-1)에 복제한다. 표지가 없거나 빈 칸이 없으면 건너뛴다(다시 연결 필요).
   바꾸기에 실패한 행이 있으면 표시를 남기지 않아 다음 점검에서 다시 시도한다. 건너뛴 행은 "확인 필요"로 보이되 점검 완료 기록은 막지 않는다. */
if ($checkupAuthorized && is_null(Setting::getServiceSetting('socialIdentifierClientTag', null, true))) {
	$socialTags = array();
	$socialProviders = array();
	$socialRows = POD::queryAll("SELECT blogid, settings FROM {$database['prefix']}Plugins WHERE name = 'CL_SocialLogin'", 'assoc');
	if (is_array($socialRows)) {
		foreach ($socialRows as $socialRow) {
			$socialConfig = is_string($socialRow['settings']) ? unserialize($socialRow['settings'], array('allowed_classes' => false)) : false;
			if (!is_array($socialConfig)) continue;
			foreach ($socialConfig as $socialKey => $socialValue) {
				if (preg_match('/\A([a-z0-9_]+)_client_id\z/', (string)$socialKey, $socialMatch) && is_string($socialValue) && $socialValue !== '') {
					$socialTags[(int)$socialRow['blogid']][$socialMatch[1]] = substr(hash('sha256', $socialValue), 0, 12);
					$socialProviders[$socialMatch[1]] = true;
				}
			}
		}
	}
	$socialMoved = 0;
	$socialSkipped = 0;
	$socialFailed = is_array($socialRows) ? 0 : 1;
	if (!empty($socialProviders)) {
		$socialBlogList = implode(',', array_map('intval', array_keys($socialTags)));
		foreach (array('Comments', 'TrashComments') as $socialTable) {
			if (!doesExistTable($database['prefix'] . $socialTable)) continue;
			$socialList = POD::queryAll("SELECT blogid, id, openid FROM {$database['prefix']}$socialTable WHERE blogid IN ($socialBlogList) AND openid <> ''", 'assoc');
			if (!is_array($socialList)) {
				$socialFailed++;
				continue;
			}
			foreach ($socialList as $socialItem) {
				$socialOld = (string)$socialItem['openid'];
				if (!preg_match('/\A([a-z0-9_]+):/', $socialOld, $socialMatch) || preg_match('/\A[a-z0-9_]+:[0-9a-f]{12}:/', $socialOld)
					|| !isset($socialProviders[$socialMatch[1]])) continue;
				$socialBlog = (int)$socialItem['blogid'];
				if (!isset($socialTags[$socialBlog][$socialMatch[1]])) {
					$socialSkipped++;
					continue;
				}
				$socialNew = $socialMatch[1] . ':' . $socialTags[$socialBlog][$socialMatch[1]] . substr($socialOld, strlen($socialMatch[1]));
				if (strlen($socialNew) > 128) {
					$socialSkipped++;
					continue;
				}
				if (POD::execute("UPDATE {$database['prefix']}$socialTable SET openid = '" . POD::escapeString($socialNew) . "'"
					." WHERE blogid = $socialBlog AND id = " . (int)$socialItem['id'] . " AND openid = '" . POD::escapeString($socialOld) . "'"))
					$socialMoved++;
				else
					$socialFailed++;
			}
		}
		$socialReserved = POD::reservedFieldNames();
		$socialValueField = (is_array($socialReserved) && in_array('value', $socialReserved)) ? '"value"' : 'value';
		$socialLinks = POD::queryAll("SELECT userid, name, $socialValueField AS linkvalue FROM {$database['prefix']}UserSettings WHERE name LIKE 'openid.%'", 'assoc');
		if (!is_array($socialLinks)) {
			$socialFailed++;
		} else {
			foreach ($socialLinks as $socialLink) {
				$socialOld = (string)$socialLink['linkvalue'];
				if (!preg_match('/\A([a-z0-9_]+):/', $socialOld, $socialMatch) || preg_match('/\A[a-z0-9_]+:[0-9a-f]{12}:/', $socialOld)
					|| !isset($socialProviders[$socialMatch[1]])) continue;
				$socialUser = (int)$socialLink['userid'];
				$socialCandidates = array();
				$socialUserBlogs = POD::queryColumn("SELECT blogid FROM {$database['prefix']}Privileges WHERE userid = $socialUser", false);
				if (is_array($socialUserBlogs)) {
					foreach ($socialUserBlogs as $socialUserBlog) {
						if (isset($socialTags[(int)$socialUserBlog][$socialMatch[1]]))
							$socialCandidates[$socialTags[(int)$socialUserBlog][$socialMatch[1]]] = true;
					}
				}
				if (count($socialCandidates) === 0) {
					$socialSkipped++;
					continue;
				}
				$socialCandidates = array_keys($socialCandidates);
				sort($socialCandidates);
				$socialRest = substr($socialOld, strlen($socialMatch[1]));
				// 첫 표지는 그 자리(openid.N)에서 바꾼다
				if (!POD::execute("UPDATE {$database['prefix']}UserSettings SET $socialValueField = '" . POD::escapeString($socialMatch[1] . ':' . $socialCandidates[0] . $socialRest) . "'"
					." WHERE userid = $socialUser AND name = '" . POD::escapeString((string)$socialLink['name']) . "' AND $socialValueField = '" . POD::escapeString($socialOld) . "'")) {
					$socialFailed++;
					continue;
				}
				$socialMoved++;
				if (count($socialCandidates) < 2) continue;
				// 나머지 표지는 비어 있는 openid.0 ~ openid.(OPENID_REGISTERS-1) 칸에 복제한다(연결 화면 addOIDCIdentity 와 같은 칸 규칙)
				$socialSlots = POD::queryAllWithoutCache("SELECT name, $socialValueField AS linkvalue FROM {$database['prefix']}UserSettings WHERE userid = $socialUser AND name LIKE 'openid.%'", 'assoc');
				if (!is_array($socialSlots)) {
					$socialFailed++;
					continue;
				}
				$socialUsedNames = array();
				$socialUsedValues = array();
				foreach ($socialSlots as $socialSlot) {
					$socialUsedNames[(string)$socialSlot['name']] = true;
					$socialUsedValues[(string)$socialSlot['linkvalue']] = true;
				}
				$socialSlotIndex = 0;
				$socialRegisters = defined('OPENID_REGISTERS') ? OPENID_REGISTERS : 10;
				for ($socialCandidate = 1; $socialCandidate < count($socialCandidates); $socialCandidate++) {
					$socialNew = $socialMatch[1] . ':' . $socialCandidates[$socialCandidate] . $socialRest;
					if (isset($socialUsedValues[$socialNew])) continue;	// 이미 연결됨
					while ($socialSlotIndex < $socialRegisters && isset($socialUsedNames['openid.' . $socialSlotIndex]))
						$socialSlotIndex++;
					if ($socialSlotIndex >= $socialRegisters) {	// 빈 칸 없음
						$socialSkipped++;
						continue;
					}
					if (POD::execute("INSERT INTO {$database['prefix']}UserSettings (userid, name, $socialValueField) VALUES ($socialUser, 'openid.$socialSlotIndex', '" . POD::escapeString($socialNew) . "')")) {
						$socialUsedNames['openid.' . $socialSlotIndex] = true;
						$socialUsedValues[$socialNew] = true;
						$socialMoved++;
					} else {
						$socialFailed++;
					}
				}
			}
		}
	}
	if ($socialMoved > 0 || $socialSkipped > 0 || $socialFailed > 0) {
		$changed = true;
		echo '<li>', _textf('소셜 로그인 식별자에 앱 구분 표지를 넣습니다(옮김 %1, 건너뜀 %2).', $socialMoved, $socialSkipped), ': ';
		if ($socialFailed > 0)
			showCheckupMessage(false);
		else if ($socialSkipped > 0)
			echo _text('건너뛴 연결은 해당 사용자가 계정 설정에서 소셜 계정을 다시 연결해야 합니다.'), ' <span class="result fail">', _text('확인 필요'), '</span></li>';
		else
			showCheckupMessage(true);
	}
	if ($socialFailed == 0)
		Setting::setServiceSetting('socialIdentifierClientTag', 1, true);
}

/***** Common parts. *****/
if($checkupAuthorized) clearCache();

$filename = ROOT . '/.htaccess';
// Textcube-NG: .htaccess 가 없으면 빈 내용으로 본다 — fopen 실패 뒤 filesize·fread(false) 는 PHP 8 에서 TypeError(r3-15, R-276)
$content = file_exists($filename) ? (string)@file_get_contents($filename) : '';
// PHP port(Textcube-NG): r3-11 이미 점검이 끝난 상태에서 다시 점검을 고르지 않았으면($checkupSkip) .htaccess 규칙도 다시 쓰지 않는다
// Textcube-NG: 규칙은 주인·점검 키를 받은 요청($checkupAuthorized, !$checkupSkip 포함)일 때만 다시 쓴다 — 손님 요청으로 .htaccess 를 덮지 않는다(r3-15, R-276)
if ($checkupAuthorized && ((preg_match('@rewrite\.php@', $content) == 0 ) ||
		(strpos($content,'[OR]') !== false) ||
		(strpos($content,' -d') == false) ||
		(strpos($content,'(cache|xml|txt|log)') == false)
		)) {
	echo '<li>', _textf('htaccess 규칙을 수정합니다.'), ': ';
	// Textcube-NG: 그날 백업(.htaccess_backup_날짜)이 이미 있으면 다시 쓰지 않는다 — 원본은 같은 날 다시 실행하면 그때의 .htaccess 로
	// 기존 백업을 덮어썼다(r3-16, R-388). 아래 .htaccess 규칙 맞춤 블록의 백업과 같은 방식
	$htaccessBackupFile = $filename.'_backup_'.Timestamp::format('%Y%m%d');
	$fp = file_exists($htaccessBackupFile) ? false : fopen($htaccessBackupFile, "w");
	// Textcube-NG: 백업·쓰기 파일을 열지 못했으면 fwrite·fclose 를 부르지 않는다 — false 를 넘기면 PHP 8 에서 TypeError(r3-15, R-276)
	if ($fp) {
		fwrite($fp,$content);
		fclose($fp);
	}
	// Textcube-NG: 루트 .htaccess 와 같은 내용·순서 — thumbnail 규칙을 앞으로 옮겨 rewrite.php 규칙에 !-f 조건이 붙게 함(R-10), .user.ini 차단(Apache 2.2·2.4 겸용, R-6), 스킨 index.xml 차단(5330)(v3.196). 스킨 index.xml 은 깊이와 관계없이 막는다(skin/blog/customize/<id>/index.xml 포함, r3-3)
	// Textcube-NG: r4-6 — 루트 .htaccess 와 같이 .user.ini 차단을 <Files> 블록 대신 RewriteRule 로 바꿈(AllowOverride FileInfo 만으로 동작). 이미 설치된 사이트의 옛 <Files> 블록은 이 점검이 바꾸지 않는다(사용자 결정 11-23 — 안내만)
	// Textcube-NG: plugins/ 아래 PHP 파일 직접 요청 차단 규칙 추가(r3-6, R-111). 이 블록이 쓰지 않은 기존 .htaccess 는 아래 별도 점검이 이 규칙 한 줄을 넣고 옛 스킨 index.xml 차단 줄을 바꾼다(R-58)
	// Textcube-NG: cache/ 차단 규칙의 이름 부분을 (.+[^/]) 에서 (.*[^/]) 로 바꿔 한 글자 이름(cache/x.cache)도 막는다 — 원본은 두 글자 이상만 막았다(r3-16, R-389)
	// Textcube-NG: attach/ 아래 PHP 파일 실행 차단 규칙을 plugins 차단 줄 뒤에 추가(r3-20). 이 블록이 쓰지 않은 기존 .htaccess 는 아래 별도 점검이 이 규칙 한 줄을 넣는다
	$content =
"#<IfModule mod_url.c>
#CheckURL Off
#</IfModule>
#SetEnv PRELOAD_CONFIG 1

RewriteEngine On
RewriteBase ".$service['path']."/
RewriteRule ^(thumbnail)/([0-9]+/.+)$ cache/$1/$2 [L]
RewriteCond %{REQUEST_FILENAME} -f
RewriteRule ^(cache)+/+(.*[^/])\\.(cache|xml|txt|log)$ - [NC,F,L]
RewriteCond %{REQUEST_FILENAME} -f
RewriteRule ^(plugins/+[^/]+/+(locale/+.+|index\\.xml)|resources/+locale/+.+|skin/+.+/+index\\.xml)$ - [NC,F,L]
RewriteRule ^plugins/+.+\\.(php[0-9]*|phps|phtml|phar)(/|$) - [NC,F,L]
# Textcube-NG: 첨부 폴더(attach/) 안 PHP 실행 차단 — 올린 파일은 PHP 로 실행되면 안 된다(r3-20)
# Textcube-NG: r3-27 R-448 — 가운데 확장자(a.php.jpg)도 막는다: 확장자 뒤에 . 이 이어지는 경우를 더함
RewriteRule ^attach/+.+\\.(php[0-9]*|phps|phtml|pht|phar)(\\.|/|$) - [NC,F,L]
# Textcube-NG: r4-6 — PHP 사용자 설정 파일(.user.ini, 어느 폴더든) 직접 접근 차단을 <Files> 블록에서 RewriteRule 로 옮김(plugins·attach 규칙과 같이 끝을 슬래시 또는 경로 끝으로 맞춰 PATH_INFO 가 붙은 경로 .user.ini/x 도 막음). <Files> 안 Require·Order 는 AllowOverride AuthConfig·Limit 이 있어야 해서 FileInfo 만 허용한 서버에서 모든 요청이 500 이었다
RewriteRule ^(.+/+)?\\.user\\.ini(/|$) - [NC,F,L]
RewriteCond %{REQUEST_FILENAME} -d
RewriteRule ^(.+[^/])$ $1/ [L]
RewriteCond %{REQUEST_FILENAME} !-f
RewriteRule ^(.*)$ rewrite.php [L,QSA]
";
	$fp = fopen($filename, "w");
	if($fp && fwrite($fp, $content)) {
		fclose($fp);
		showCheckupMessage(true);
	} else {
		if ($fp) fclose($fp);
		showCheckupMessage(false);
	}
}

/* Textcube-NG: 이미 설치된 블로그의 루트 .htaccess 를 새 규칙에 맞춘다(r3-6, R-58). 사용자가 더한 규칙은 그대로 두고 줄마다 원래 줄바꿈(CRLF/LF)을 지킨다.
   ① 스킨 index.xml 차단 규칙(r3-3, R-56): 이 이식본이 만들던 옛 줄(v3.171 의 plugins·resources locale 줄, v3.196 의 skin/<종류>/<스킨>/index.xml 줄)이
      한 줄과 정확히 같고 그런 줄이 하나뿐이면 깊이와 관계없이 막는 새 줄로 바꾼다. 새 줄이 이미 있으면 그대로 둔다.
   ② plugins/ 아래 PHP 파일 직접 요청 차단 규칙(R-111): 없으면 plugins 차단 줄(없으면 RewriteBase, 그것도 없으면 RewriteEngine On 줄) 바로 뒤에 한 줄을 넣는다.
   ③ attach/ 아래 PHP 파일 실행 차단 규칙(r3-20): 없으면 ② 의 plugins PHP 차단 줄(없으면 ② 와 같은 자리) 바로 뒤에 한 줄을 넣는다.
   쓰기 전에 .htaccess_backup_날짜 백업을 남긴다(그날 백업이 이미 있으면 그대로 둔다). 파일·백업을 쓸 수 없거나 바꿀 줄·넣을 자리를 찾지 못하면
   규칙을 보여 주어 관리자가 직접 고치게 하고("확인 필요"), 점검 완료 기록은 막지 않는다. */
$htaccessPluginPhpRule = 'RewriteRule ^plugins/+.+\.(php[0-9]*|phps|phtml|phar)(/|$) - [NC,F,L]';
$htaccessAttachPhpRule = 'RewriteRule ^attach/+.+\.(php[0-9]*|phps|phtml|pht|phar)(\.|/|$) - [NC,F,L]';	// Textcube-NG: r3-27 R-448 — 가운데 확장자(a.php.jpg) 추가. 옛 규칙 줄이 있어도 이 새 줄이 없으면 점검이 새 줄을 더한다	// Textcube-NG: ③(r3-20)
$htaccessSkinXmlRule = 'RewriteRule ^(plugins/+[^/]+/+(locale/+.+|index\.xml)|resources/+locale/+.+|skin/+.+/+index\.xml)$ - [NC,F,L]';
$htaccessSkinXmlOldRules = array(
	'RewriteRule ^(plugins/+[^/]+/+(locale/+.+|index\.xml)|resources/+locale/+.+|skin/+[^/]+/+[^/]+/+index\.xml)$ - [NC,F,L]',
	'RewriteRule ^(plugins/+[^/]+/+(locale/+.+|index\.xml)|resources/+locale/+.+)$ - [NC,F,L]');
if ($checkupAuthorized && file_exists($filename)) {
	$htaccessCurrent = @file_get_contents($filename);
	if (is_string($htaccessCurrent)) {
		$htaccessLines = explode("\n", $htaccessCurrent);
		$htaccessSkinXmlHasNew = false;
		$htaccessSkinXmlOldAt = array();
		foreach ($htaccessLines as $htaccessIndex => $htaccessLine) {
			$htaccessLine = rtrim($htaccessLine, "\r");
			if ($htaccessLine === $htaccessSkinXmlRule)
				$htaccessSkinXmlHasNew = true;
			else if (in_array($htaccessLine, $htaccessSkinXmlOldRules, true))
				$htaccessSkinXmlOldAt[] = $htaccessIndex;
		}
		$htaccessSkinXmlStep = 0;	// 0: 할 일 없음, 1: 옛 줄을 바꿈, 2: 안내
		if (!$htaccessSkinXmlHasNew) {
			if (count($htaccessSkinXmlOldAt) == 1) {
				$htaccessIndex = $htaccessSkinXmlOldAt[0];
				$htaccessLines[$htaccessIndex] = $htaccessSkinXmlRule . ((substr($htaccessLines[$htaccessIndex], -1) === "\r") ? "\r" : '');
				$htaccessSkinXmlStep = 1;
			} else {
				$htaccessSkinXmlStep = 2;
			}
		}
		$htaccessNew = implode("\n", $htaccessLines);
		$htaccessPluginPhpStep = 0;	// 0: 할 일 없음, 1: 넣음, 2: 안내
		if (strpos($htaccessNew, $htaccessPluginPhpRule) === false) {
			$htaccessPluginPhpStep = 2;
			foreach (array('@^[ \t]*RewriteRule[ \t]+\^\(plugins/[^\r\n]*@mi', '@^[ \t]*RewriteBase\b[^\r\n]*@mi', '@^[ \t]*RewriteEngine[ \t]+On\b[^\r\n]*@mi') as $htaccessPattern) {
				if (preg_match($htaccessPattern, $htaccessNew, $htaccessMatch, PREG_OFFSET_CAPTURE)) {
					$htaccessAt = $htaccessMatch[0][1] + strlen($htaccessMatch[0][0]);
					if (substr($htaccessNew, $htaccessAt, 2) === "\r\n")
						$htaccessEol = "\r\n";
					else if (substr($htaccessNew, $htaccessAt, 1) === "\n")
						$htaccessEol = "\n";
					else
						$htaccessEol = (strpos($htaccessNew, "\r\n") !== false) ? "\r\n" : "\n";
					$htaccessNew = substr($htaccessNew, 0, $htaccessAt) . $htaccessEol . $htaccessPluginPhpRule . substr($htaccessNew, $htaccessAt);
					$htaccessPluginPhpStep = 1;
					break;
				}
			}
		}
		// Textcube-NG: ③ attach/ 아래 PHP 파일 실행 차단 규칙(r3-20) — ② 와 같은 방식, plugins PHP 차단 줄을 첫 자리로 본다
		$htaccessAttachPhpStep = 0;	// 0: 할 일 없음, 1: 넣음, 2: 안내
		if (strpos($htaccessNew, $htaccessAttachPhpRule) === false) {
			$htaccessAttachPhpStep = 2;
			foreach (array('@^[ \t]*RewriteRule[ \t]+\^plugins/\+\.\+[^\r\n]*@mi', '@^[ \t]*RewriteRule[ \t]+\^\(plugins/[^\r\n]*@mi', '@^[ \t]*RewriteBase\b[^\r\n]*@mi', '@^[ \t]*RewriteEngine[ \t]+On\b[^\r\n]*@mi') as $htaccessPattern) {
				if (preg_match($htaccessPattern, $htaccessNew, $htaccessMatch, PREG_OFFSET_CAPTURE)) {
					$htaccessAt = $htaccessMatch[0][1] + strlen($htaccessMatch[0][0]);
					if (substr($htaccessNew, $htaccessAt, 2) === "\r\n")
						$htaccessEol = "\r\n";
					else if (substr($htaccessNew, $htaccessAt, 1) === "\n")
						$htaccessEol = "\n";
					else
						$htaccessEol = (strpos($htaccessNew, "\r\n") !== false) ? "\r\n" : "\n";
					$htaccessNew = substr($htaccessNew, 0, $htaccessAt) . $htaccessEol . $htaccessAttachPhpRule . substr($htaccessNew, $htaccessAt);
					$htaccessAttachPhpStep = 1;
					break;
				}
			}
		}
		$htaccessWritten = false;
		if (($htaccessSkinXmlStep == 1) || ($htaccessPluginPhpStep == 1) || ($htaccessAttachPhpStep == 1)) {
			$htaccessBackup = $filename . '_backup_' . Timestamp::format('%Y%m%d');
			if (is_writable($filename)
				&& (file_exists($htaccessBackup) || (@file_put_contents($htaccessBackup, $htaccessCurrent) === strlen($htaccessCurrent)))
				&& (@file_put_contents($filename, $htaccessNew, LOCK_EX) === strlen($htaccessNew)))
				$htaccessWritten = true;
		}
		if ($htaccessSkinXmlStep != 0) {
			$changed = true;
			echo '<li>', _text('htaccess의 스킨 index.xml 차단 규칙을 바꿉니다.'), ': ';
			if (($htaccessSkinXmlStep == 1) && $htaccessWritten)
				showCheckupMessage(true);
			else
				echo _text('.htaccess 파일을 쓸 수 없거나 바꿀 규칙 줄을 찾지 못했습니다. 웹서버 설정의 스킨 index.xml 차단 규칙 줄을 아래 규칙으로 직접 바꾸거나, 없으면 아래 두 줄을 rewrite.php 규칙보다 앞에 넣어 주십시오.'), '<br /><code>', htmlspecialchars('RewriteCond %{REQUEST_FILENAME} -f', ENT_QUOTES, 'UTF-8'), '<br />', htmlspecialchars($htaccessSkinXmlRule, ENT_QUOTES, 'UTF-8'), '</code> <span class="result fail">', _text('확인 필요'), '</span></li>';
		}
		if ($htaccessPluginPhpStep != 0) {
			$changed = true;
			echo '<li>', _text('htaccess에 플러그인 PHP 파일 직접 요청 차단 규칙을 넣습니다.'), ': ';
			if (($htaccessPluginPhpStep == 1) && $htaccessWritten)
				showCheckupMessage(true);
			else
				echo _text('.htaccess 파일을 쓸 수 없거나 넣을 자리를 찾지 못했습니다. 웹서버 설정에 아래 규칙을 직접 넣어 주십시오.'), '<br /><code>', htmlspecialchars($htaccessPluginPhpRule, ENT_QUOTES, 'UTF-8'), '</code> <span class="result fail">', _text('확인 필요'), '</span></li>';
		}
		if ($htaccessAttachPhpStep != 0) {
			$changed = true;
			echo '<li>', _text('htaccess에 첨부 폴더 PHP 파일 실행 차단 규칙을 넣습니다.'), ': ';
			if (($htaccessAttachPhpStep == 1) && $htaccessWritten)
				showCheckupMessage(true);
			else
				echo _text('.htaccess 파일을 쓸 수 없거나 넣을 자리를 찾지 못했습니다. 웹서버 설정에 아래 규칙을 직접 넣어 주십시오.'), '<br /><code>', htmlspecialchars($htaccessAttachPhpRule, ENT_QUOTES, 'UTF-8'), '</code> <span class="result fail">', _text('확인 필요'), '</span></li>';
		}
		// Textcube-NG: 규칙 줄을 넣지 못해 '확인 필요'를 보인 단계가 하나라도 있으면 점검 완료 기록(CHECKUP)을 남기지 않는다 — 다음 점검에서 다시 알린다(원본은 .htaccess 쓰기 실패 시 CHECKUP 미기록, R-457)
		if ((($htaccessSkinXmlStep != 0) && !(($htaccessSkinXmlStep == 1) && $htaccessWritten))
			|| (($htaccessPluginPhpStep != 0) && !(($htaccessPluginPhpStep == 1) && $htaccessWritten))
			|| (($htaccessAttachPhpStep != 0) && !(($htaccessAttachPhpStep == 1) && $htaccessWritten)))
			$succeed = false;
	}
}

/* PHP port: 원본 버전 값(1행)에 NG 점검 표시(2행)를 더해 기록(Checkup::stamp(), Textcube.Core.php). 내용이 다를 때만 기록. */
if (((!file_exists(__TEXTCUBE_CACHE_DIR__.'/CHECKUP')) || (file_get_contents(__TEXTCUBE_CACHE_DIR__.'/CHECKUP') !== Checkup::stamp())) && ($succeed == true) && $checkupAuthorized) {
	$fp = fopen(__TEXTCUBE_CACHE_DIR__.'/CHECKUP', 'w');
	if ($fp !== FALSE) {
		fwrite($fp, Checkup::stamp());
		fclose($fp);
		@chmod(__TEXTCUBE_CACHE_DIR__.'/CHECKUP', 0666);
		clearCache();
	}
}
// Textcube-NG: 플러그인 차단 확인 — 관리자·글쓴이(doesHaveOwnership)에게만. 켜진 플러그인을 다시 판정해 막힌 것을 보이고 대시보드 알림과 맞춘다(v3.219)
if ($checkupAuthorized) {
	require_once ROOT . '/library/model/common.plugin.guard.php';
	$pluginNoticeBlocked = pluginNoticeCheckup();
	if (!empty($pluginNoticeBlocked)) {
		$pluginNoticeItems = array();
		foreach ($pluginNoticeBlocked as $pluginNoticeName => $pluginNoticeReason) {
			$pluginNoticeItem = pluginNoticeName($pluginNoticeName) . '(' . pluginGuardReasonText($pluginNoticeReason) . ')';
			if ($pluginNoticeReason === 'integrity' || $pluginNoticeReason === 'strict') $pluginNoticeItem .= ' ' . _text('끄고 다시 켜면 풀립니다');
			$pluginNoticeItems[] = $pluginNoticeItem;
		}
		echo '<li>', pluginNoticeEscape(_text('플러그인 차단 확인')), ': ', pluginNoticeEscape(implode(', ', $pluginNoticeItems)), '<br />', pluginNoticeEscape(_text('대시보드의 플러그인 알림과 플러그인 화면(/owner/plugin)에서 확인할 수 있습니다.')), ' <span class="result fail">', pluginNoticeEscape(_text('확인 필요')), '</span></li>';
	}
	unset($pluginNoticeBlocked, $pluginNoticeItems, $pluginNoticeName, $pluginNoticeReason, $pluginNoticeItem);
}
?>
					</ul>
<?php
// PHP port(Textcube-NG): r4-10 데이터베이스 문자셋(이모지) 절 — MySQL·MySQLi 이고 서비스 관리자(group.creators)이며 점검 키 요청이 아닐 때만(사용자 결정 11-23)
$dbcharsetContinue = false;
if ($dbcharsetVisible) {
	echo '<h3>', htmlspecialchars(_text('데이터베이스 문자셋(이모지)'), ENT_QUOTES, 'UTF-8'), '</h3>', "\n", '<ul id="dbCharset">', "\n";
	if ($dbcharsetStored !== null) {
		echo '<li>', htmlspecialchars(_textf('데이터베이스 문자셋: utf8mb4(%1). 이모지를 저장할 수 있습니다.', $dbcharsetStored), ENT_QUOTES, 'UTF-8'), '</li>', "\n";
		// Textcube-NG: r4-10 — 바꾼 뒤에도 등록되지 않은 테이블(바꾸지 않음) 목록을 계속 보인다(명세 R10 2.2-7)
		list($dbcharsetTables, $dbcharsetUnknown) = checkupDbCharsetTargets();
		if (!empty($dbcharsetUnknown))
			echo '<li>', htmlspecialchars(_textf('등록되지 않은 테이블은 바꾸지 않았습니다: %1. 이 테이블에 이모지를 쓰거나 찾으면 오류가 날 수 있으니, 플러그인을 다시 켜서 등록하거나 직접 바꿔 주십시오.', implode(', ', $dbcharsetUnknown)), ENT_QUOTES, 'UTF-8'), '</li>', "\n";
	} else {
		echo '<li>', htmlspecialchars(_text('데이터베이스 문자셋: utf8. 4바이트 문자(이모지)는 저장되지 않습니다.'), ENT_QUOTES, 'UTF-8'), '</li>', "\n";
		if ($dbcharsetCollation === null) {
			echo '<li>', htmlspecialchars(_textf('이 데이터베이스 서버(%1)는 utf8mb4 변환 기준(MySQL 5.6 이상·MariaDB 10.0 이상)이 아니어서 바꿀 수 없습니다.', (string)POD::queryCell('SELECT VERSION()', 0, false)), ENT_QUOTES, 'UTF-8'), '</li>', "\n";
		} else {
			list($dbcharsetTables, $dbcharsetUnknown) = checkupDbCharsetTargets();
			$dbcharsetPending = checkupDbCharsetPending($dbcharsetTables, $dbcharsetCollation);
			if (is_array($dbcharsetPending))
				echo '<li>', htmlspecialchars(_textf('바꿀 테이블 %1개 가운데 %2개를 바꿨습니다.', count($dbcharsetTables), count($dbcharsetTables) - count($dbcharsetPending)), ENT_QUOTES, 'UTF-8'), '</li>', "\n";
			if (!empty($dbcharsetUnknown))
				echo '<li>', htmlspecialchars(_textf('등록되지 않은 테이블은 바꾸지 않습니다: %1. 다 바꾼 뒤 이 테이블에 이모지를 쓰거나 찾으면 오류가 날 수 있으니, 플러그인을 다시 켜서 등록하거나 직접 바꿔 주십시오.', implode(', ', $dbcharsetUnknown)), ENT_QUOTES, 'UTF-8'), '</li>', "\n";
			echo '<li>', htmlspecialchars(_text('바꾸는 동안 테이블마다 잠시 쓰기가 막히고, 글이 많으면 한 테이블에 수십 초가 걸릴 수 있습니다. 시작하기 전에 데이터베이스를 백업하십시오. 바꾼 뒤 되돌리는 화면은 없으며, 되돌리려면 백업을 복원해야 합니다.'), ENT_QUOTES, 'UTF-8'), '</li>', "\n";
			// Textcube-NG: r4-10 — 변환 전 중복 검사(사용자 결정 11-23 '미리 검사·목록 안내'). 중복이 있거나 확인하지 못하면 버튼을 내지 않는다(변환 요청도 서버 쪽에서 거부)
			$dbcharsetDupBlocked = false;
			if (is_array($dbcharsetPending) && !empty($dbcharsetPending)) {
				if (!is_array($dbcharsetDuplicates))
					$dbcharsetDuplicates = checkupDbCharsetDuplicates($dbcharsetPending, $dbcharsetCollation);
				if (!is_array($dbcharsetDuplicates)) {
					$dbcharsetDupBlocked = true;
					echo '<li>', htmlspecialchars(_text('바꾸면 같은 값이 되는 중복이 있는지 확인하지 못했습니다. 잠시 뒤 이 화면을 다시 열어 주십시오.'), ENT_QUOTES, 'UTF-8'), ' <span class="result fail">', htmlspecialchars(_text('확인 필요'), ENT_QUOTES, 'UTF-8'), '</span></li>', "\n";
				} else if (!empty($dbcharsetDuplicates)) {
					$dbcharsetDupBlocked = true;
					echo '<li>', htmlspecialchars(_text('변환 전에 정리할 중복'), ENT_QUOTES, 'UTF-8'), ': ',
						htmlspecialchars(_text('utf8mb4 로 바꾸면 아래 키에서 같은 값이 되는 묶음이 있어 바꿀 수 없습니다(긴 값은 앞 191자까지만 비교하고, 문자 비교는 새 콜레이션을 따릅니다). 바꾸기 전에 관리 화면에서 긴 태그·링크 주소·필터 등을 고치거나 지워 주십시오(자동으로 고치지 않습니다). 정리한 뒤 이 화면을 다시 열면 바꿀 수 있습니다.'), ENT_QUOTES, 'UTF-8'),
						' <span class="result fail">', htmlspecialchars(_text('확인 필요'), ENT_QUOTES, 'UTF-8'), '</span>', "\n", '<ul>', "\n";
					foreach ($dbcharsetDuplicates as $dbcharsetDuplicate) {
						$dbcharsetDuplicateValues = array();
						foreach ($dbcharsetDuplicate[3] as $dbcharsetDuplicateItem)
							$dbcharsetDuplicateValues[] = _textf('%1건: %2', $dbcharsetDuplicateItem[0], $dbcharsetDuplicateItem[1]);
						echo '<li>', htmlspecialchars(_textf('테이블 %1 의 키 %2: 같은 값이 되는 묶음 %3개', $dbcharsetDuplicate[0], $dbcharsetDuplicate[1], ($dbcharsetDuplicate[2] > 20) ? '20+' : $dbcharsetDuplicate[2]), ENT_QUOTES, 'UTF-8'),
							'<br />', htmlspecialchars(implode(' / ', $dbcharsetDuplicateValues), ENT_QUOTES, 'UTF-8'), '</li>', "\n";
					}
					echo '</ul></li>', "\n";
					unset($dbcharsetDuplicate, $dbcharsetDuplicateValues, $dbcharsetDuplicateItem);
				}
			}
			if (!$checkupIsCurrent) {
				echo '<li>', htmlspecialchars(_text('먼저 점검을 끝낸 뒤 바꿀 수 있습니다.'), ENT_QUOTES, 'UTF-8'), '</li>', "\n";
			} else if (!$dbcharsetDupBlocked) {
				echo '<li>';
				if (!$checkupTokenPrinted)
					echo '<input type="hidden" name="csrfToken" value="', htmlspecialchars(getCSRFToken(), ENT_QUOTES, 'UTF-8'), '" />';
				echo '<label><input type="checkbox" name="dbcharsetAuto" value="1"', ($dbcharsetAuto ? ' checked="checked"' : ''), ' /> ', htmlspecialchars(_text('끝날 때까지 이어서 바꾸기'), ENT_QUOTES, 'UTF-8'), '</label> ',
					'<input type="submit" name="dbcharsetConvert" value="', htmlspecialchars(_text('이모지(utf8mb4)로 바꾸기'), ENT_QUOTES, 'UTF-8'), '" onclick="',
					htmlspecialchars('return confirm(' . json_encode(_text('데이터베이스를 백업했습니까? 테이블을 utf8mb4 로 바꾸기 시작합니다. 되돌리려면 백업을 복원해야 합니다.')) . ');', ENT_QUOTES, 'UTF-8'), '" /></li>', "\n";
				$dbcharsetContinue = ($dbcharsetStep === 'next') && $dbcharsetAuto;
			}
		}
	}
	echo '</ul>', "\n";
	// '끝날 때까지 이어서': 직전 요청이 테이블 하나를 바꿨고 남았을 때만 1초 뒤 다시 보낸다(실패·끝·잠금 실패면 멈춘다)
	if ($dbcharsetContinue)
		echo '<script type="text/javascript">', "\n", '//<![CDATA[', "\n",
			'setTimeout(function () { var f = document.getElementById("checkup"); if (!f) return; var i = document.createElement("input"); i.type = "hidden"; i.name = "dbcharsetConvert"; i.value = "1"; f.appendChild(i); f.submit(); }, 1000);', "\n",
			'//]]>', "\n", '</script>', "\n";
}
?>

					<p id="lastMessage">
						<?php
	if ($checkupAuthorized) reloadSkin(1);
	echo ($changed ? _text('완료되었습니다.') : _text('확인되었습니다.'));
?>
					</p>
				</div>

				<div id="navigation">
					<a href="<?php echo $blogURL.'/owner/center/dashboard';?>"><img src="<?php echo $service['path']?>/resources/style/setup/image/icon_ok.gif" width="74" height="24" alt="<?php echo _text('돌아가기');?>" /></a>
				</div>
			</div>
		</form>
	</div>
</body>
</html>
