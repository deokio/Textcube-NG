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

// DBQuery version 1.8 for MySQL (PHP 7.4+ compatible — uses mysqli internally)
// PHP 7.0 removed ext/mysql. This adapter now delegates all operations to the
// mysqli extension while preserving the 'MySQL' adapter identity for config compatibility.
/* 드라이버 선택: config.php 의 $database['driver'] 로 고른다. MySQL·MariaDB 공통.
   'native'(기본값·생략 시) — mysqli 확장, 기존 동작.  'pdo' — PDO_MYSQL(PDO::getAvailableDrivers() 에 'mysql').
   두 드라이버 모두 같은 설정(서버·포트·계정·DB·utf8·SSL)으로 연결한다. 연결 객체는 self::$db(mysqli 또는 PDO).
   PDO 모드도 한 번에 한 문장만 실행하고(다중 문장 비활성) 일반 질의의 값은 네이티브처럼 문자열로 받는다. */

global $fileCachedResult;

class DBAdapter implements IAdapter {
	static $db;
	static $cachedResult, $dbProperties, $escapeTag, $lastQueryType;

	public static function bind($database) {
		// PHP 8.1+ 의 mysqli 기본 보고 모드(MYSQLI_REPORT_ERROR|MYSQLI_REPORT_STRICT)는 연결·SQL 오류를 예외로 던지므로
		// MySQLi 어댑터와 같이 끄고 false 반환(PHP 7.x 와 같은 동작)을 유지.
		if (function_exists('mysqli_report')) mysqli_report(MYSQLI_REPORT_OFF);	// mysqli 확장이 없는 설치(PDO 드라이버만)에서도 읽을 수 있게
		self::$cachedResult = self::$dbProperties = array();
		if (!isset($database) || empty($database)) return false;
		if (!isset($database['port']) && strpos($database['server'], ':')) {
			$port = explode(':', $database['server']);
			$database['server'] = $port[0];
			$database['port']   = $port[1];
		}
		// 드라이버 선택: 'native'(기본값) 또는 'pdo'. 그 밖의 값이면 연결하지 않는다
		$driver = isset($database['driver']) ? $database['driver'] : '';
		if ($driver === '') $driver = 'native';
		if (!is_string($driver) || !in_array(strtolower($driver), array('native', 'pdo'), true)) {
			error_log("MySQL/MariaDB connection error : unknown driver setting (database['driver'] must be 'native' or 'pdo')");
			return false;
		}
		if (strtolower($driver) == 'pdo') return self::__bindPDO($database);
		if (!class_exists('mysqli')) {
			error_log('MySQL/MariaDB connection error : mysqli extension is not available');
			return false;
		}
		self::$dbProperties['driver'] = 'native';
		if (!empty($database['ssl'])) {
			// SSL/TLS 연결 (config 에 ssl 가 설정된 경우에만) — MySQLi 어댑터와 같은 방식. MySQL·MariaDB 공통.
			$port   = isset($database['port']) ? intval($database['port']) : 3306;
			$verify = !empty($database['ssl_verify']);
			self::$db = mysqli_init();
			// 인증서는 설정된 것만 사용: ca만 = 서버SSL / ca+cert+key = 상호TLS
			$ca   = !empty($database['ssl_ca'])   ? $database['ssl_ca']   : null;
			$cert = !empty($database['ssl_cert']) ? $database['ssl_cert'] : null;
			$key  = !empty($database['ssl_key'])  ? $database['ssl_key']  : null;
			self::$db->ssl_set($key, $cert, $ca, null, null);
			$flags = MYSQLI_CLIENT_SSL;
			if ($verify) {
				// 서버 인증서와 서버 이름을 검증 — mysqlnd 는 검증 여부를 연결 플래그로 정함(options() 의 검증 옵션은 덮어씀).
				// 플래그가 없으면 ca·cert·key 가 모두 비었을 때 검증을 건너뛰므로 명시. CA 미지정 시 시스템 CA 사용
				if (defined('MYSQLI_CLIENT_SSL_VERIFY_SERVER_CERT')) $flags |= MYSQLI_CLIENT_SSL_VERIFY_SERVER_CERT;
				else { error_log("MySQL/MariaDB SSL connection error : server certificate verification is not supported by this PHP (mysqli)"); return false; }	// Textcube-NG: 검증을 켤 수 없으면 연결하지 않는다(r4-4)
			} else {
				// 서버 인증서 검증 비활성(자체서명 CA 등)
				if (defined('MYSQLI_CLIENT_SSL_DONT_VERIFY_SERVER_CERT')) $flags |= MYSQLI_CLIENT_SSL_DONT_VERIFY_SERVER_CERT;	// Textcube-NG: 상수는 PHP 5.6.16+(r4-4)
			}
			// 실패(인증서 검증·인증 실패 등) 시 서버 오류 문구는 로그에만 남기고 false 반환(호출부의 DB 연결 오류 처리)
			// 실패 원인은 경고로만 나오는 경우가 있어(PHP 7.4 는 connect_error 가 빔) 경고를 직접 받아 로그에 씀(화면 출력 없음, 기존 @ 와 같음)
			$warns = array();
			set_error_handler(function ($no, $str) use (&$warns) { $warns[] = trim(preg_replace('/\s+/', ' ', $str)); return true; });
			$connected = self::$db->real_connect(
				$database['server'], $database['username'],
				$database['password'], $database['database'],
				$port, null, $flags
			);
			restore_error_handler();
			if (!$connected) {
				error_log('MySQL/MariaDB SSL connection error : ' . mysqli_connect_errno() . ' - ' . implode(' / ', array_unique($warns)));
				return false;
			}
		} else if (isset($database['port'])) {
			// 원본 @mysql_connect 와 같이 연결 경고(접속 사용자·호스트 포함)는 화면에 내지 않고 실패 시 false
			self::$db = @new mysqli(
				$database['server'], $database['username'],
				$database['password'], $database['database'],
				intval($database['port'])
			);
		} else {
			self::$db = @new mysqli(
				$database['server'], $database['username'],
				$database['password'], $database['database']
			);
		}
		if (!self::$db || self::$db->connect_errno) {
			error_log('MySQL/MariaDB connection error : ' . mysqli_connect_errno() . ' - ' . mysqli_connect_error());
			return false;
		}
		// 원본 @mysql_select_db 와 같이 실패 시 false (DB 오류 문구를 화면에 내지 않음), 원인은 error_log 로 기록
		if (!@self::$db->select_db($database['database'])) {
			error_log('MySQL/MariaDB select_db error : ' . self::$db->errno . ' - ' . self::$db->error);
			return false;
		}
		self::$db->autocommit(true);
		if (self::$db->set_charset('utf8'))
			self::$dbProperties['charset'] = 'utf8';
		else
			self::$dbProperties['charset'] = 'default';
		@self::query('SET SESSION collation_connection = \'utf8_general_ci\'');
		return true;
	}

	/* PDO_MYSQL 연결. 네이티브와 같은 서버·포트·계정·DB·문자셋(utf8)·SSL 설정을 쓴다.
	   오류는 예외 대신 반환값으로 받고(ERRMODE_SILENT), 일반 질의는 클라이언트 쪽 준비(에뮬레이션 — 네이티브 query() 와 같은 텍스트 프로토콜)·값은 문자열로 받는다.
	   다중 문장은 끈다. 실패 원인(서버 오류 문구)은 화면에 내지 않고 error_log 로만 남긴다(네이티브와 같음) */
	public static function __bindPDO($database) {
		self::$db = null;
		if (!class_exists('PDO') || !in_array('mysql', PDO::getAvailableDrivers(), true)) {
			error_log('MySQL/MariaDB connection error : PDO_MYSQL driver is not available');
			return false;
		}
		// DSN 구분자(;)·NUL 이 든 서버·DB 이름은 다른 DSN 항목을 끼워 넣을 수 있어 거부
		if (preg_match('/[;\x00]/', $database['server'] . $database['database'])) {
			error_log('MySQL/MariaDB connection error : server or database name contains a DSN delimiter');
			return false;
		}
		$dsn = 'mysql:host=' . $database['server'] . (isset($database['port']) ? ';port=' . intval($database['port']) : '') . ';dbname=' . $database['database'] . ';charset=utf8';
		$options = array(
			PDO::ATTR_ERRMODE           => PDO::ERRMODE_SILENT,
			PDO::ATTR_EMULATE_PREPARES  => true,
			PDO::ATTR_STRINGIFY_FETCHES => true,
			PDO::ATTR_STATEMENT_CLASS   => array('DBAdapterPDOStatement'),
		);
		if (defined('PDO::MYSQL_ATTR_MULTI_STATEMENTS')) $options[PDO::MYSQL_ATTR_MULTI_STATEMENTS] = false;	// Textcube-NG: 다중 문장 끄기 — 상수는 PHP 5.5.21+(5.5.0~5.5.20 은 이 차단이 없다)(r4-4)
		if (!empty($database['ssl'])) {
			// 네이티브 SSL 연결과 같은 규칙: 인증서는 설정된 것만, CAPATH 는 빈 값(TLS 사용 강제), 서버 인증서 검증은 ssl_verify 일 때만
			if (!empty($database['ssl_ca']))   $options[PDO::MYSQL_ATTR_SSL_CA]   = $database['ssl_ca'];
			if (!empty($database['ssl_cert'])) $options[PDO::MYSQL_ATTR_SSL_CERT] = $database['ssl_cert'];
			if (!empty($database['ssl_key']))  $options[PDO::MYSQL_ATTR_SSL_KEY]  = $database['ssl_key'];
			$options[PDO::MYSQL_ATTR_SSL_CAPATH] = '';
			if (defined('PDO::MYSQL_ATTR_SSL_VERIFY_SERVER_CERT')) $options[PDO::MYSQL_ATTR_SSL_VERIFY_SERVER_CERT] = !empty($database['ssl_verify']);
			else if (!empty($database['ssl_verify'])) { error_log("MySQL/MariaDB connection error : PDO_MYSQL server certificate verification needs PHP 7.0.18+"); return false; }	// Textcube-NG: 검증을 켤 수 없으면 연결하지 않는다(r4-4)
		}
		$warns = array();
		$code = 0;
		$handle = null;
		set_error_handler(function ($no, $str) use (&$warns) { $warns[] = trim(preg_replace('/\s+/', ' ', $str)); return true; });
		try {
			$handle = new PDO($dsn, $database['username'], $database['password'], $options);
		} catch (Exception $e) {
			$warns[] = trim(preg_replace(array('/\s+/', '/^SQLSTATE\[\w{5}\] \[\d+\] /'), array(' ', ''), $e->getMessage()));	// 네이티브 문구처럼 SQLSTATE·오류 번호 앞머리는 뗀다(번호는 앞에 따로 기록)
			$code = $e->getCode();
		}
		restore_error_handler();
		if (!$handle) {
			$warns = array_unique(array_filter($warns, 'strlen'));	// PDO_MYSQL 은 SSL 실패의 세부 원인(인증서 검증 등)을 알려 주지 않는다(PHP 7.4 는 문구도 비어 있음)
			error_log('MySQL/MariaDB ' . (empty($database['ssl']) ? '' : 'SSL ') . 'connection error : ' . $code . ' - ' . ($warns ? implode(' / ', $warns) : 'no detail from PDO_MYSQL'));
			return false;
		}
		// 네이티브는 DB 이름이 비면 select_db 가 실패(1046)해 false
		if ((string)$database['database'] === '') {
			error_log('MySQL/MariaDB select_db error : 1046 - No database selected');
			return false;
		}
		self::$db = $handle;
		self::$dbProperties['driver'] = 'pdo';
		self::$dbProperties['lastError'] = array(0, '');
		self::$dbProperties['affectedRows'] = 0;
		self::__pdoExec('SET AUTOCOMMIT=1');	// 네이티브 autocommit(true)
		self::$dbProperties['charset'] = 'utf8';	// DSN 의 charset=utf8 (네이티브 set_charset('utf8'))
		@self::query('SET SESSION collation_connection = \'utf8_general_ci\'');
		return true;
	}

	public static function unbind() {
		if (self::__isPDO()) {
			self::$db = null;	// PDO 는 객체를 놓으면 연결을 닫는다
			return true;
		}
		self::$db->close();
		return true;
	}

	public static function charset() {
		if (array_key_exists('charset', self::$dbProperties)) return self::$dbProperties['charset'];
		return null;
	}

	/* Textcube-NG: r4-10 utf8mb4 연결(사용자 결정 11-23). 연결은 bind() 처럼 utf8 로 맺고, 표 변환 완료 표시
	   (ServiceSettings 'databaseCharset' = 콜레이션 이름)가 있을 때만 utf8mb4 로 바꾼다 — library/preprocessor.php·setup.php 가 부른다.
	   콜레이션은 utf8mb4_unicode_520_ci(MySQL 5.6+·MariaDB 10.0+)·utf8mb4_0900_ai_ci(MySQL 8.0+)만 받는다.
	   PDO 는 DSN 문자셋을 연결 뒤 바꿀 수 없어 SET NAMES 로 서버 쪽만 바꾼다 — utf8·utf8mb4 는 escape 대상 바이트(ASCII)가 같다 */
	public static function setCharsetCollation($collation) {
		if (!is_string($collation) || !in_array($collation, array('utf8mb4_unicode_520_ci', 'utf8mb4_0900_ai_ci'), true)) return false;
		if (self::__isPDO()) {
			if (!self::__pdoExec("SET NAMES utf8mb4 COLLATE $collation")) return false;
		} else if (!(self::$db->set_charset('utf8mb4') && self::execute("SET SESSION collation_connection = '$collation'"))
			&& !self::execute("SET NAMES utf8mb4 COLLATE $collation")) {
			@self::$db->set_charset('utf8');	// 콜레이션을 맞추지 못했으면 bind() 와 같은 utf8 연결로 되돌린다(set_charset('utf8mb4') 만 성공한 경우)
			@self::query('SET SESSION collation_connection = \'utf8_general_ci\'');
			return false;
		}
		self::$dbProperties['charset'] = 'utf8mb4';
		self::$dbProperties['collation'] = $collation;
		return true;
	}

	public static function applyStoredCharset($prefix) {
		if (!is_string($prefix) || !preg_match('/^[A-Za-z0-9_]*$/', $prefix)) return false;
		$collation = self::queryCell("SELECT value FROM {$prefix}ServiceSettings WHERE name = 'databaseCharset'", 0, false);
		if (!is_string($collation) || $collation === '') return false;	// 표시 없음 = utf8 그대로(지금과 같음)
		if (self::setCharsetCollation($collation)) return true;
		error_log("MySQL/MariaDB charset error : the stored databaseCharset could not be applied; this request uses utf8");
		return false;
	}

	public static function utf8mb4Collation() {
		$version = (string)self::queryCell('SELECT VERSION()', 0, false);
		if (stripos($version, 'MariaDB') === false && version_compare(preg_replace('/[^0-9.].*$/', '', $version), '8.0.0', '>=')
			&& self::queryAllWithoutCache("SHOW COLLATION LIKE 'utf8mb4_0900_ai_ci'")) return 'utf8mb4_0900_ai_ci';
		if (self::queryAllWithoutCache("SHOW COLLATION LIKE 'utf8mb4_unicode_520_ci'")) return 'utf8mb4_unicode_520_ci';
		return null;
	}

	public static function tableCharsetClause() {
		$charset = self::charset();
		if ($charset === 'utf8mb4' && isset(self::$dbProperties['collation'])) return 'DEFAULT CHARSET=utf8mb4 COLLATE ' . self::$dbProperties['collation'];
		if ($charset === 'utf8') return 'DEFAULT CHARSET=utf8';
		return '';
	}

	public static function dbms() {
		return 'MySQL';
	}

	public static function version($mode = 'server') {
		if (array_key_exists('version', self::$dbProperties)) return self::$dbProperties['version'];
		self::$dbProperties['version'] = self::queryCell('SELECT VERSION()');
		return self::$dbProperties['version'];
	}

	public static function tableList($condition = null) {
		if (!array_key_exists('tableList', self::$dbProperties)) {
			$tableData = self::queryAll('SHOW TABLES');
			self::$dbProperties['tableList'] = array();
			foreach ($tableData as $tbl) {
				array_push(self::$dbProperties['tableList'], $tbl[0]);
			}
		}
		if (!is_null($condition)) {
			$result = array();
			foreach (self::$dbProperties['tableList'] as $item) {
				if (strpos($item, $condition) === 0)
					array_push($result, $item);
			}
			return $result;
		}
		return self::$dbProperties['tableList'];
	}

	public static function setTimezone($time) {
		return self::query('SET time_zone = \'' . Timezone::getCanonical() . '\'');
	}

	public static function reservedFieldNames() {
		return null;
	}

	public static function reservedFunctionNames() {
		return array('UNIX_TIMESTAMP()');
	}

	public static function queryExistence($query) {
		if ($result = self::query($query)) {
			if (self::__numRows($result) > 0) {
				self::__closeResult($result);
				return true;
			}
			self::__closeResult($result);
		}
		return false;
	}

	public static function queryCount($query) {
		$count = 0;
		$query = trim($query);
		if ($result = self::query($query)) {
			$operation = strtolower(substr($query, 0, 6));
			self::$lastQueryType = $operation;
			switch ($operation) {
				case 'select':
					$count = self::__numRows($result);
					self::__closeResult($result);
					break;
				case 'insert':
				case 'update':
				case 'delete':
				case 'replac':
				default:
					$count = self::__affectedRows();
					break;
			}
		}
		return $count;
	}

	public static function queryCell($query, $field = 0, $useCache = true) {
		/* 형식 이름으로 넘긴다: PDO 모드에는 mysqli 확장 상수가 없을 수 있다. 원본의 정수 상수는 __queryType() 이 알아보지 못해
		   항상 BOTH 로 읽혔다(돌려주는 값은 같음. 열 이름이 숫자여서 번호 키에 다른 열 값이 들던 경우만 이제 그 번호의 열 값). PostgreSQL·Cubrid 어댑터와 같은 방식. */
		if (is_numeric($field)) {
			$type = 'num';
		} else {
			$type = 'assoc';
		}
		if ($useCache) {
			$result = self::queryAllWithCache($query, $type);
		} else {
			$result = self::queryAllWithoutCache($query, $type);
		}
		if (empty($result)) return null;
		return $result[0][$field];
	}

	public static function queryRow($query, $type = 'both', $useCache = true) {
		if ($useCache) {
			$result = self::queryAllWithCache($query, $type, 1);
		} else {
			$result = self::queryAllWithoutCache($query, $type, 1);
		}
		if (empty($result)) return null;
		return $result[0];
	}

	public static function queryColumn($query, $useCache = true) {
		$cacheKey = "{$query}_queryColumn";
		if ($useCache && isset(self::$cachedResult[$cacheKey])) {
			if (function_exists('__tcSqlLogBegin')) {
				__tcSqlLogBegin($query);
				__tcSqlLogEnd(null, 1);
			}
			self::$cachedResult[$cacheKey][0]++;
			return self::$cachedResult[$cacheKey][1];
		}
		$column = null;
		if ($result = self::query($query)) {
			$column = array();
			while ($result !== true && $row = self::__fetchRow($result, self::__queryType('num')))	// Textcube-NG: 결과 집합이 없는 문장(주석만 있는 문장·SET 등)은 query() 가 true 를 돌려준다. 행을 읽지 않고 빈 배열(Cubrid·SQLite3 와 같음)이며 행 0개 SELECT 처럼 캐시한다(v3.184)
				array_push($column, $row[0]);
			self::__closeResult($result);
		}
		if ($useCache && !is_null($column)) {	// 실패(null)는 캐시하지 않음
			self::$cachedResult[$cacheKey] = array(1, $column);
		}
		return $column;
	}

	public static function queryAll($query, $type = 'both', $count = -1) {
		return self::queryAllWithCache($query, $type, $count);
	}

	public static function queryAllWithoutCache($query, $type = 'both', $count = -1) {
		$all = array();
		$realtype = self::__queryType($type);
		if ($result = self::query($query)) {
			while ($result !== true && ($count-- != 0) && $row = self::__fetchRow($result, $realtype))	// Textcube-NG: 결과 집합이 없는 문장(query() 가 true)은 행을 읽지 않고 빈 배열(Cubrid·SQLite3 와 같음). 닫기는 __closeResult 의 true 가드가 받는다(v3.184)
				array_push($all, $row);
			self::__closeResult($result);
			return $all;
		}
		return null;
	}

	public static function queryAllWithCache($query, $type = 'both', $count = -1) {
		$cacheKey = "{$query}_{$type}_{$count}";
		if (isset(self::$cachedResult[$cacheKey])) {
			if (function_exists('__tcSqlLogBegin')) {
				__tcSqlLogBegin($query);
				__tcSqlLogEnd(null, 1);
			}
			self::$cachedResult[$cacheKey][0]++;
			return self::$cachedResult[$cacheKey][1];
		}
		$all = self::queryAllWithoutCache($query, $type, $count);
		/* 실패한 질의(null)는 캐시하지 않는다(원본은 실패 결과도 다음 쓰기 문장까지 돌려줘, 같은 요청에서 테이블을 만든 뒤에도 실패로 보였다). */
		if (!is_null($all)) self::$cachedResult[$cacheKey] = array(1, $all);
		return $all;
	}

	public static function execute($query) {
		return self::query($query) ? true : false;
	}

	public static function multiQuery() {
		$result = false;
		foreach (func_get_args() as $query) {
			if (is_array($query)) {
				foreach ($query as $subquery)
					if (($result = self::query($subquery)) === false)
						return false;
			} else if (($result = self::query($query)) === false)
				return false;
		}
		return $result;
	}

	public static function query($query) {
		if (function_exists('__tcSqlLogBegin')) {
			__tcSqlLogBegin($query);
			$result = self::__execute($query);
			__tcSqlLogEnd($result, 0);
		} else {
			$result = self::__execute($query);
		}
		self::$lastQueryType = strtolower(substr($query, 0, 6));
		if (self::__isSchemaChange($query)) {
			unset(self::$dbProperties['tableList']);
			self::clearCache();
		} else if (stristr($query, 'update ') ||
			stristr($query, 'insert ') ||
			stristr($query, 'delete ') ||
			stristr($query, 'replace ')) {
			self::clearCache();
		}
		return $result;
	}

	/* 드라이버별 실행·결과 처리. 네이티브는 원본과 같은 mysqli 호출.
	   PDO 는 PDO::query()(결과는 DBAdapterPDOStatement, 결과 열이 없는 문장은 true). 실패는 false 이고, 오류 번호·메시지는 lastError,
	   영향받은 행 수는 affectedRows 에 남긴다(네이티브 errno·error·affected_rows 와 같은 값). */
	public static function __execute($query) {
		if ((string)$query === '') {	// Textcube-NG: 빈 문장은 보내지 않고 실패(PHP 8 의 mysqli::query('')·PDO::query('') 는 ValueError). 네이티브는 원본 MySQLi 처럼 false(오류 상태는 그대로. 원본의 'Empty query' 경고는 내지 않음), PDO 는 __preparePDO 와 같은 1065(v3.184)
			if (!self::__isPDO()) return false;
			self::$dbProperties['affectedRows'] = -1;
			return self::__pdoFail(1065, 'Query was empty');
		}
		if (!self::__isPDO()) return self::$db->query($query);
		$result = self::$db->query($query);
		if ($result === false) {
			self::$dbProperties['affectedRows'] = -1;
			return self::__pdoError(self::$db->errorInfo());
		}
		self::$dbProperties['lastError'] = array(0, '');
		self::$dbProperties['affectedRows'] = $result->rowCount();
		if ($result->columnCount() == 0) {
			$result->closeCursor();
			return true;
		}
		return $result;
	}

	/* PDO::errorInfo() → lastError. 오류 번호는 MySQL 오류 번호(없으면 2000 — 클라이언트 쪽 알 수 없는 오류). SQLSTATE 가 00000 이면 오류가 아니다
	   (PHP 7.4 의 errorInfo() 는 성공한 뒤에도 이전 오류를 남겨 두므로 실패했을 때만 읽는다) */
	public static function __pdoError($info) {
		if (!isset($info[0]) || $info[0] === '00000') return self::__pdoFail(0, '');
		$code = isset($info[1]) ? (int)$info[1] : 0;
		return self::__pdoFail($code ? $code : 2000, isset($info[2]) ? $info[2] : 'SQLSTATE[' . $info[0] . ']');
	}

	public static function __pdoFail($code, $message) {
		self::$dbProperties['lastError'] = array($code, (string)$message);
		return false;
	}

	public static function __isPDO() {
		return (isset(self::$dbProperties['driver']) && self::$dbProperties['driver'] === 'pdo');
	}

	/* 결과 없는 제어 문장(SET·COMMIT·ROLLBACK) 실행. PDO::commit()·rollBack() 은 트랜잭션 밖에서 예외를 던지므로 네이티브처럼 문장을 보낸다 */
	public static function __pdoExec($query) {
		$count = self::$db->exec($query);
		if ($count === false) {
			self::$dbProperties['affectedRows'] = -1;
			return self::__pdoError(self::$db->errorInfo());
		}
		self::$dbProperties['lastError'] = array(0, '');
		self::$dbProperties['affectedRows'] = $count;
		return true;
	}

	/* 결과 행 수(네이티브 num_rows). PDO_MYSQL 은 결과를 모두 받아 두므로(버퍼 질의) rowCount() 가 같은 값 */
	public static function __numRows($result) {
		if ($result === true) return 0;	// Textcube-NG: 결과 집합이 없는 문장(주석만 있는 문장·SET 등)은 query() 가 true 를 돌려준다. 행 없음으로 본다(v3.184)
		if ($result instanceof PDOStatement) return $result->rowCount();
		return $result->num_rows;
	}

	public static function __affectedRows() {
		if (self::__isPDO()) return isset(self::$dbProperties['affectedRows']) ? self::$dbProperties['affectedRows'] : -1;
		return self::$db->affected_rows;
	}

	public static function __closeResult($result) {
		if ($result === true) return true;	// Textcube-NG: 결과 집합이 없으면 닫을 것이 없다(v3.184)
		if ($result instanceof PDOStatement) return $result->closeCursor();
		return $result->free();
	}

	/* 한 행 읽기. PDO 는 FETCH_NUM 으로 읽어 네이티브(mysqlnd)와 같은 키·순서로 다시 짠다 — BOTH 는 열마다 번호 키(이미 있으면 그대로 둠)와
	   이름 키를 넣는다(PDO 의 FETCH_BOTH 는 순서가 달라 이름이 숫자인 열이 있으면 값이 다름). 끝이면 네이티브처럼 null */
	public static function __fetchRow($result, $realtype) {
		if (!($result instanceof DBAdapterPDOStatement)) return $result->fetch_array($realtype);
		$row = $result->fetch(PDO::FETCH_NUM);
		if ($row === false) return null;
		if ($realtype == PDO::FETCH_NUM) return $row;
		$names = $result->columnNames();
		$keyed = array();
		foreach ($row as $i => $value) {
			if ($realtype == PDO::FETCH_BOTH && !array_key_exists($i, $keyed)) $keyed[$i] = $value;
			$keyed[$names[$i]] = $value;
		}
		return $keyed;
	}

	public static function __lastErrorCode() {
		if (self::__isPDO()) return isset(self::$dbProperties['lastError']) ? self::$dbProperties['lastError'][0] : 0;
		return self::$db ? self::$db->errno : 0;
	}

	public static function __lastErrorMessage() {
		if (self::__isPDO()) return isset(self::$dbProperties['lastError']) ? self::$dbProperties['lastError'][1] : '';
		return self::$db ? self::$db->error : '';
	}

	/* 연결 문자셋 이름(Debug 의 오류 문구 변환용). PDO 는 DSN 의 charset=utf8 */
	public static function __charsetName() {
		if (self::__isPDO()) return (isset(self::$dbProperties['charset']) && self::$dbProperties['charset'] === 'utf8mb4') ? 'utf8mb4' : 'utf8';	// Textcube-NG: r4-10 — setCharsetCollation() 로 utf8mb4 로 바꾼 뒤에는 utf8mb4
		return self::$db->character_set_name();
	}

	public static function insertId() {
		if (self::__isPDO()) {
			$id = self::$db->lastInsertId();	// PDO 는 문자열로 돌려준다 — 네이티브 insert_id 처럼 정수 범위면 정수로
			return (is_string($id) && $id === (string)(int)$id) ? (int)$id : $id;
		}
		return self::$db->insert_id;
	}

	public static function escapeString($string, $link = null) {
		if (self::__isPDO()) {
			/* PDO::quote() 결과에서 감싼 따옴표를 뗀다(같은 클라이언트 라이브러리 이스케이프 — real_escape_string() 과 같은 결과) */
			$quoted = self::$db->quote($string);
			return ($quoted === false) ? '' : substr($quoted, 1, -1);
		}
		if (is_null(self::$escapeTag)) {
			if (self::$db->real_escape_string('ㅋ') == 'ㅋ') {
				self::$escapeTag = 'real';
			} else {
				self::$escapeTag = 'none';
			}
		}
		if (self::$escapeTag == 'real') {
			return self::$db->real_escape_string($string);
		} else {
			return self::$db->escape_string($string);
		}
	}

	/* 스키마 변경 문장(CREATE·ALTER·DROP·TRUNCATE·RENAME) 여부 — 맨 앞 공백과 SQL 주석(-- 줄 주석·블록 주석)을 건너뛴 첫 낱말로 판단(문자열 리터럴 안의 낱말은 해당 없음).
	   원본은 UPDATE·INSERT·DELETE·REPLACE 가 들어 있을 때만 질의 캐시를 비워, 같은 요청에서 테이블을 만들거나 열을 바꾼 뒤에도
	   캐시된 SELECT(이전의 실패 결과·바뀌기 전 열)와 테이블 목록(tableList)을 그대로 돌려줬다. */
	public static function __isSchemaChange($query) {
		return (preg_match('/^(?:\s|--[^\n]*\n|\/\*.*?\*\/)*(CREATE|ALTER|DROP|TRUNCATE|RENAME)\b/is', (string)$query) === 1);
	}

	public static function clearCache() {
		self::$cachedResult = array();
		if (function_exists('__tcSqlLogBegin')) {
			__tcSqlLogBegin('Cache cleared');
			__tcSqlLogEnd(null, 2);
		}
	}

	public static function cacheLoad() {
		global $fileCachedResult;
	}

	public static function cacheSave() {
		global $fileCachedResult;
	}

	public static function rollback() {
		self::clearCache();	// 되돌린 변경을 읽은 캐시 결과를 버린다
		if (self::__isPDO()) return self::__pdoExec('ROLLBACK');	// PDO::rollBack() 은 트랜잭션 밖에서 예외 — 네이티브처럼 ROLLBACK 문을 보낸다
		return self::$db->rollback();
	}

	public static function commit() {
		if (self::__isPDO()) {
			self::__pdoExec('COMMIT');	// PDO::commit() 은 트랜잭션 밖에서 예외 — 네이티브처럼 COMMIT 문을 보낸다
			return true;
		}
		self::$db->commit();
		return true;
	}

	public static function num_rows($handle = null) {
		switch (self::$lastQueryType) {
			case 'select':
				return $handle ? self::__numRows($handle) : 0;
			default:
				return self::__affectedRows();
		}
	}

	public static function free($handle = null) {
		if ($handle instanceof PDOStatement || $handle instanceof mysqli_result) self::__closeResult($handle);	// Textcube-NG: 결과 객체(PDOStatement·mysqli_result)만 닫는다. DBModel::getAll() 의 배열 등 다른 값은 닫을 것이 없다(원본 PHP 5.6 의 mysql_free_result 는 경고 뒤 계속, 이식본 __closeResult 는 배열에서 Error)(v3.214)
	}

	public static function fetch($handle = null, $type = 'assoc') {
		if ($handle === true) return null;	// Textcube-NG: 결과 집합이 없는 문장의 핸들(true)은 읽을 행이 없다. 네이티브 mysqli 의 행 끝과 같은 null(v3.184)
		if ($handle instanceof DBAdapterPDOStatement) return self::__fetchRow($handle, self::__queryType($type == 'array' ? 'both' : ($type == 'row' ? 'num' : 'assoc')));
		if ($type == 'array') return $handle->fetch_array();
		else if ($type == 'row') return $handle->fetch_row();
		else return $handle->fetch_assoc();
	}

	public static function error($err = null) {
		if (self::__isPDO()) return self::__lastErrorMessage();
		return self::$db->error;
	}

	public static function stat($stat = null) {
		if (self::__isPDO()) return self::$db->getAttribute(PDO::ATTR_SERVER_INFO);	// mysqli::stat() 과 같은 서버 상태 문자열
		return self::$db->stat();
	}

	public static function __queryType($type) {
		if (self::__isPDO()) {
			switch (strtolower($type)) {
				case 'num':    return PDO::FETCH_NUM;
				case 'assoc':  return PDO::FETCH_ASSOC;
				case 'both':
				default:       return PDO::FETCH_BOTH;
			}
		}
		switch (strtolower($type)) {
			case 'num':    return MYSQLI_NUM;
			case 'assoc':  return MYSQLI_ASSOC;
			case 'both':
			default:       return MYSQLI_BOTH;
		}
	}

	public static function fieldType($abstractType) {
		/* 원본은 정적 속성이 아닌 지역 변수 $typeTable 을 읽어 항상 null 을 돌려줬다. */
		if (isset(self::$typeTable[$abstractType])) return self::$typeTable[$abstractType];
	}

	public static $typeTable = array(
		'integer'    => 'int',
		'int'        => 'int',
		'float'      => 'float',
		'double'     => 'double',
		'timestamp'  => 'int',
		'mediumtext' => 'mediumtext',
		'varchar'    => 'varchar',
		'vartext'    => 'varchar',	// 원본은 'vartext' 를 그대로 돌려줬다(DBMS 형식 아님). 다른 어댑터처럼 'varchar' 를 두고 'vartext' 는 별칭으로 남김
		'text'       => 'text',
	);

/*** Prepared Statement API — delegates to mysqli connection ***/
	public static function prepare($query) {
		if (self::__isPDO()) return self::__preparePDO($query);
		return self::$db->prepare($query);
	}
	public static function bindAndExecute($stmt, $types) {
		$params = array_slice(func_get_args(), 2);	// Textcube-NG: PHP 5.5 에는 가변 인자(...)가 없다(r4-4)
		if ($stmt instanceof DBAdapterStatement) return self::__bindAndExecutePDO($stmt, $types, $params);
		if ((string)$types !== '') { $refs = array($types); foreach ($params as $k => $v) $refs[] = &$params[$k]; call_user_func_array(array($stmt, 'bind_param'), $refs); }	// Textcube-NG: 형식 문자열이 빈 호출은 묶지 않고 실행(PHP 8 의 bind_param('') 는 ValueError, 7.4 는 경고와 false 뒤 그대로 실행)(v3.184) — bind_param 은 참조 인자라 참조 배열로 넘긴다(PHP 5.5 가변 인자 없음, r4-4)
		$result = $stmt->execute();
		/* 결과 열이 없는 문장(INSERT·UPDATE·DELETE 등)은 query() 처럼 질의 캐시를 비운다(같은 요청에서 캐시된 SELECT 가 바뀌기 전 값을 돌려주지 않도록). */
		if ($stmt->field_count == 0) self::clearCache();
		return $result;
	}
	public static function fetchAllStmt($stmt) {
		if ($stmt instanceof DBAdapterStatement) return self::__fetchAllStmtPDO($stmt);
		if (!method_exists($stmt, 'get_result')) return self::__fetchAllStmtBound($stmt);	// Textcube-NG: mysqlnd 가 없는 빌드(r4-4)
		$result = $stmt->get_result();
		if ($result === false) return null;
		$all = [];
		while ($row = $result->fetch_assoc()) {
			$all[] = $row;
		}
		$result->free();
		return $all;
	}

	/* Textcube-NG: mysqlnd 가 없는 PHP(libmysqlclient 빌드)에는 mysqli_stmt::get_result() 가 없다 — 결과 열 이름으로 bind_result() 를
	   묶어 같은 연관 배열 목록을 만든다(결과 집합이 없으면 null, 같은 이름 열은 마지막 값 — fetch_assoc 와 같음)(r4-4) */
	public static function __fetchAllStmtBound($stmt) {
		$meta = $stmt->result_metadata();
		if (!$meta) return null;
		$row = array();
		$refs = array();
		while ($field = $meta->fetch_field()) {
			$row[$field->name] = null;
			$refs[] = &$row[$field->name];
		}
		$meta->free();
		if (!$stmt->store_result() || !call_user_func_array(array($stmt, 'bind_result'), $refs)) return null;
		$all = array();
		while ($stmt->fetch()) {
			$copy = array();
			foreach ($row as $k => $v) $copy[$k] = $v;
			$all[] = $copy;
		}
		$stmt->free_result();
		return $all;
	}

	/* PDO 모드 prepare(). 서버 쪽 준비 문장으로 만든다(네이티브와 같이 서버가 문법·자리표시자 수를 확인하고 값은 바이너리 프로토콜로 보냄).
	   ? 자리표시자만 쓴다 — :이름·?? 가 있으면 PDO 가 문장을 고쳐 쓸 수 있어(문자열 안이어도) 거부 */
	public static function __preparePDO($query) {
		if ((string)$query === '') return self::__pdoFail(1065, 'Query was empty');	// 네이티브와 같은 서버 오류(PHP 8 의 PDO::prepare('') 는 ValueError)
		if (preg_match('/:[A-Za-z0-9_]|\?\?/', (string)$query)) return self::__pdoFail(2000, 'Statement rejected: only ? placeholders are supported');
		$handle = self::__prepareHandle($query);
		if ($handle === false) return false;
		return new DBAdapterStatement($query, $handle);
	}

	/* 서버 쪽 준비 문장 만들기 — 연결은 일반 질의용으로 에뮬레이션을 켜 두므로 준비하는 동안만 끈다.
	   오류 정보는 속성을 되돌리기 전에 읽는다(setAttribute() 가 연결의 오류 정보를 지움) */
	public static function __prepareHandle($query) {
		$info = null;
		self::$db->setAttribute(PDO::ATTR_EMULATE_PREPARES, false);
		try {
			$handle = self::$db->prepare($query);
			if ($handle === false) $info = self::$db->errorInfo();
		} finally {
			self::$db->setAttribute(PDO::ATTR_EMULATE_PREPARES, true);
		}
		if ($handle === false) {
			self::$dbProperties['affectedRows'] = -1;
			return self::__pdoError($info);
		}
		self::$dbProperties['lastError'] = array(0, '');
		return $handle;
	}

	/* PDO 모드 바인딩·실행. 형식 문자대로 바인딩한다 — i·d 는 정수·실수 값 그대로(PARAM_INT 는 값의 PHP 형식으로 보냄: 정수 LONGLONG, 실수 DOUBLE),
	   s 는 문자열, b 는 빈 값(네이티브도 send_long_data() 없이는 빈 값), null 은 NULL. 다시 쓰는 문장은 새로 준비한다(PDO 는 앞 실행의 바인딩을
	   남겨 두어 값이 모자라도 실행될 수 있음). 바인딩 실패(값 개수·형식 문자)는 PHP 7.4 네이티브와 같은 오류 2031 로 false(PHP 8 네이티브는 예외)(자리표시자가 없는 문장은 네이티브와 같이 실행, v3.222) */
	public static function __bindAndExecutePDO($stmt, $types, $params) {
		$stmt->reset();
		if (!$stmt->handle) return $stmt->fail('Statement is already closed', 2000);
		if ($stmt->used) {
			$handle = self::__prepareHandle($stmt->query);
			if ($handle === false) {
				$code = self::__lastErrorCode();
				return $stmt->fail(self::__lastErrorMessage(), $code ? $code : 2000);
			}
			$stmt->handle = $handle;
		}
		$stmt->used = true;
		$ok = false;
		$sent = false;
		$values = $stmt->normalize($types, $params);
		if ($values === false) { $stmt->reset(); $values = array(); }	// Textcube-NG: 형식 문자·값 수가 다르거나 모르는 형식 문자면 네이티브(7.4 경고와 false, 8.x ArgumentCountError·ValueError)와 같이 묶지 않은 채 실행 — 자리표시자가 있으면 HY093 → 2031, 없으면 실행(v3.222)
		if ($values !== false) {
			$ok = true;
			foreach ($values as $index => $value) {
				if (is_null($value[0])) $type = PDO::PARAM_NULL;
				else if ($value[1] == 'i' || $value[1] == 'd') $type = PDO::PARAM_INT;
				else $type = PDO::PARAM_STR;
				if (!$stmt->handle->bindValue($index + 1, $value[0], $type)) {	// 자리표시자보다 값이 많음
					if ($index == 0) break;	// Textcube-NG: 첫 값부터 묶이지 않으면 자리표시자가 없는 문장 — 네이티브와 같이 값을 묶지 않은 채 실행(v3.222)
					$ok = $stmt->fail('No data supplied for parameters in prepared statement', 2031);
					break;
				}
			}
		}
		if ($ok) {
			$ok = $stmt->handle->execute();
			$sent = true;
			if (!$ok) {
				$info = $stmt->handle->errorInfo();
				if (isset($info[0]) && $info[0] === 'HY093') {
					$sent = false;
					$stmt->fail('No data supplied for parameters in prepared statement', 2031);	// 자리표시자보다 값이 적음(서버에 보내지 않음)
				} else {
					self::$dbProperties['affectedRows'] = -1;
					self::__pdoError($info);
					$code = self::$dbProperties['lastError'][0];
					$stmt->fail(self::$dbProperties['lastError'][1], $code ? $code : 2000);
				}
			} else {
				self::$dbProperties['lastError'] = array(0, '');
				if ($stmt->handle->columnCount() > 0) {
					$stmt->result = $stmt->handle;
					$stmt->affected_rows = -1;	// 네이티브도 결과를 읽기 전에는 -1
				} else {
					$stmt->affected_rows = $stmt->handle->rowCount();
				}
				self::$dbProperties['affectedRows'] = $stmt->affected_rows;
			}
		}
		/* 결과 열이 없는 문장은 네이티브처럼 질의 캐시를 비운다. 서버에 보내지 않은 문장은 건너뛴다(바뀐 것이 없고, PDO 는 실행 전에는 열 수를 모름) */
		if ($sent && $stmt->handle->columnCount() == 0) self::clearCache();
		return $ok;
	}

	/* PDO 모드 결과 읽기. 네이티브 get_result() 처럼 열 형식대로(정수 int, 실수 float) 받는다 — 일반 질의용 문자열 변환(STRINGIFY_FETCHES)을 잠시 끈다.
	   행은 열 이름 키(같은 이름이면 뒤 열), 읽은 행 수는 문장·연결의 affected_rows(네이티브와 같음) */
	public static function __fetchAllStmtPDO($stmt) {
		if (!$stmt->result) return null;
		$names = $stmt->result->columnNames();
		self::$db->setAttribute(PDO::ATTR_STRINGIFY_FETCHES, false);
		try {
			$rows = $stmt->result->fetchAll(PDO::FETCH_NUM);
		} finally {
			self::$db->setAttribute(PDO::ATTR_STRINGIFY_FETCHES, true);
		}
		$stmt->freeResult();
		$all = [];
		foreach ((is_array($rows) ? $rows : array()) as $row) {
			$assoc = array();
			foreach ($row as $i => $value) $assoc[$names[$i]] = $value;
			$all[] = $assoc;
		}
		$stmt->affected_rows = count($all);
		self::$dbProperties['affectedRows'] = count($all);
		return $all;
	}
}

/* PDO 모드 준비 문장 객체 — PDO 모드의 prepare() 가 mysqli_stmt 대신 돌려준다. 호출부가 쓰는 affected_rows·errno·error·close() 를
   같은 이름으로 두고, 형식 문자(i·d·s·b)로 값을 정리한다(normalize). */
class DBAdapterStatement {
	public $query;
	public $handle;
	public $result = null;
	public $affected_rows = -1;
	public $errno = 0;
	public $error = '';
	public $used = false;

	public function __construct($query, $handle) {
		$this->query = $query;
		$this->handle = $handle;
	}

	/* mysqli_stmt::bind_param 의 형식 문자(i 정수, d 실수, s 문자열, b 바이너리)로 값을 정리한다. null 은 형식과 무관하게 NULL.
	   형식 문자열 길이와 값 개수가 다르거나 모르는 형식 문자면 실패(false). 자리표시자 수는 서버가 확인한다 */
	public function normalize($types, $params) {
		$types = (string)$types;
		$params = array_values($params);
		if (strlen($types) != count($params)) return $this->fail('No data supplied for parameters in prepared statement', 2031);
		$values = array();
		foreach ($params as $index => $value) {
			$type = $types[$index];
			if (strpos('idsb', $type) === false) return $this->fail('No data supplied for parameters in prepared statement', 2031);
			if (is_null($value)) {
				$values[] = array(null, $type);
				continue;
			}
			if ($type == 'i') {
				$value = (int)$value;
			} else if ($type == 'd') {
				$value = (float)$value;
			} else if ($type == 'b') {
				$value = '';
			} else {
				$value = (string)$value;
			}
			$values[] = array($value, $type);
		}
		return $values;
	}

	public function fail($message, $code) {
		$this->errno = $code;
		$this->error = (string)$message;
		$this->affected_rows = -1;
		return false;
	}

	public function reset() {
		$this->freeResult();
		$this->affected_rows = -1;
		$this->errno = 0;
		$this->error = '';
	}

	public function freeResult() {
		if ($this->result instanceof PDOStatement) $this->result->closeCursor();
		$this->result = null;
	}

	public function close() {
		$this->freeResult();
		$this->handle = null;	// PDOStatement 는 객체를 놓으면 닫힌다
		return true;
	}
}

/* PDO 모드 결과 문장 객체 — PDO::ATTR_STATEMENT_CLASS 로 PDO 가 만든다. 행을 네이티브 키 순서로 다시 짜기 위한 열 이름을 한 번 읽어 둔다(columnNames).
   PDO 가 없는 설치에서도 이 파일을 읽을 수 있게 조건부로 선언 */
if (class_exists('PDOStatement', false)) {
class DBAdapterPDOStatement extends PDOStatement {
	public $names = null;

	public function columnNames() {
		$count = $this->columnCount();
		if ($this->names === null || count($this->names) != $count) {
			$this->names = array();
			for ($i = 0; $i < $count; $i++) {
				$meta = $this->getColumnMeta($i);
				$this->names[$i] = (is_array($meta) && isset($meta['name'])) ? $meta['name'] : $i;
			}
		}
		return $this->names;
	}
}
}
?>
