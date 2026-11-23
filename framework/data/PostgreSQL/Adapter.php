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

// DBQuery version 1.7 for Postgresql
/* 드라이버 선택: config.php 의 $database['driver'] 로 고른다.
   'native'(기본값·생략 시) — pgsql 확장, 기존 동작.  'pdo' — PDO_PGSQL(PDO::getAvailableDrivers() 에 'pgsql').
   두 드라이버 모두 같은 연결 문자열(서버·포트·계정·DB·UTF8·SSL)로 연결한다. 연결 객체는 self::$dbProperties['handle'](pgsql 연결 또는 PDO).
   PDO 모드도 일반 질의는 단순 질의 프로토콜(네이티브 pg_query() 와 같음)로 보내고, 값은 네이티브와 같은 문자열 형식으로 돌려준다. */

global $fileCachedResult;

class DBAdapter implements IAdapter {	
	static $dbProperties, $cachedResult, $lastQueryType;
	/*@static@*/
	public static function bind($database) {
		// Connects DB and set environment variables
		// $database array should contain 'server','username','password'.
		if(!isset($database) || empty($database)) return false;
		// 드라이버 선택: 'native'(기본값) 또는 'pdo'. 그 밖의 값이면 연결하지 않는다
		$driver = isset($database['driver']) ? $database['driver'] : '';
		if($driver === '') $driver = 'native';
		if(!is_string($driver) || !in_array(strtolower($driver), array('native','pdo'), true)) {
			error_log("PostgreSQL connection error : unknown driver setting (database['driver'] must be 'native' or 'pdo')");
			return false;
		}
		$pdo = (strtolower($driver) == 'pdo');
		if(!$pdo && !function_exists('pg_connect')) {
			error_log("PostgreSQL connection error : pgsql extension is not available");
			return false;
		}
		// 연결 문자열의 값은 __connValue() 로 작은따옴표에 담음 — 공백·따옴표가 든 값이 다른 연결 옵션으로 해석되거나 빈 값이 다음 항목을 삼키지 않도록
		$sql = "host=".self::__connValue($database['server']);
		if(isset($database['port']) && $database['port'] !== '') $sql .= " port=".self::__connValue($database['port']);
		$credentials = " user=".self::__connValue($database['username'])." password=".self::__connValue($database['password']);	// 계정은 따로 둔다 — PDO 는 DSN 이 아닌 인자로 넘긴다(libpq 는 항목 순서와 무관)
		if(isset($database['database'])) $sql .= " dbname=".self::__connValue($database['database']);
		if(!empty($database['ssl'])) {
			// ── SSL/TLS 연결 (config 에 ssl 가 설정된 경우에만). 미설정 시는 기존과 같이 libpq 기본값(sslmode=prefer). ──
			if(!empty($database['ssl_verify'])) {
				// 서버 인증서와 서버 이름(host)을 검증. ssl_ca 미지정 시 libpq 16 이상은 시스템 CA,
				// 그 미만은 libpq 기본 위치(~/.postgresql/root.crt)를 쓰며 없으면 연결하지 않음
				$sql .= " sslmode='verify-full'";
				if(!empty($database['ssl_ca'])) $sql .= " sslrootcert=".self::__connValue($database['ssl_ca']);
				else if(defined('PGSQL_LIBPQ_VERSION') && intval(PGSQL_LIBPQ_VERSION) >= 16) $sql .= " sslrootcert='system'";
			} else {
				// 암호화만 요구(서버 인증서 검증 안 함) — SSL 을 지원하지 않는 서버에는 평문으로 연결하지 않음
				$sql .= " sslmode='require'";
			}
			// 인증서는 설정된 것만 사용: ssl_cert·ssl_key = 클라이언트 인증서(키 파일 권한은 0600)
			if(!empty($database['ssl_cert'])) $sql .= " sslcert=".self::__connValue($database['ssl_cert']);
			if(!empty($database['ssl_key']))  $sql .= " sslkey=".self::__connValue($database['ssl_key']);
		}
		if($pdo) return self::__bindPDO($database, $sql);
		self::$dbProperties['driver'] = 'native';
		// 연결 경고는 화면에 내지 않음(기존 @ 와 같음). SSL 연결 실패 원인(인증서 검증 실패 등)은 로그에만 남김 —
		// 사용자 오류 처리기가 등록돼 있으면 error_get_last() 에 남지 않으므로 경고를 직접 받음
		$error = '';
		set_error_handler(function($no, $str) use (&$error) { $error = trim(preg_replace('/\s+/', ' ', $str)); return true; });
		$handle = pg_connect($sql.$credentials);
		restore_error_handler();
		if(!$handle) {
			if(!empty($database['ssl'])) error_log("PostgreSQL SSL connection error : ".$error);
			return false;
		}
		self::$dbProperties['handle'] = $handle;	// Keeping handle — pg_* 함수에 연결을 명시(PHP 8.x 에서 연결 생략은 E_DEPRECATED)
		
		@pg_set_client_encoding($handle, "UTF8");

		self::$dbProperties['charset'] = 'utf8';
		return true;
	}

	/* libpq 연결 문자열의 값: 작은따옴표로 감싸고 안의 \ 와 ' 를 역슬래시로 escape (libpq 연결 문자열 규칙) */
	private static function __connValue($value) {
		return "'".addcslashes((string)$value, "'\\")."'";
	}

	/* PDO_PGSQL 연결. bind() 가 만든 연결 문자열(서버·포트·DB·SSL)을 그대로 DSN 으로 쓰고 계정은 인자로 넘긴다.
	   문자셋은 연결 문자열의 client_encoding='UTF8'(네이티브 pg_set_client_encoding("UTF8")), 연결 대기 시간은 네이티브처럼 제한 없음(PDO 기본값은 30초).
	   오류는 예외 대신 반환값으로 받고(ERRMODE_SILENT), 일반 질의는 에뮬레이션(서버에 준비 문장을 만들지 않음). 값 형식은 __pdoValue() 가 네이티브에 맞춘다.
	   연결 실패 원인은 화면에 내지 않고 SSL 연결일 때만 error_log 로 남긴다(네이티브와 같음) */
	public static function __bindPDO($database, $conninfo) {
		unset(self::$dbProperties['handle']);
		self::$dbProperties['driver'] = 'pdo';
		self::$dbProperties['lastError'] = '';
		if(!class_exists('PDO') || !in_array('pgsql', PDO::getAvailableDrivers(), true)) {
			error_log("PostgreSQL connection error : PDO_PGSQL driver is not available");
			return false;
		}
		$username = isset($database['username']) ? (string)$database['username'] : '';
		$password = isset($database['password']) ? (string)$database['password'] : '';
		// PDO_PGSQL 은 DSN 의 ; 를 (따옴표 안이어도) 공백으로 바꾸고, DSN 에 user=·password= 가 있으면 계정 인자를 붙이지 않으며, NUL 에서 문자열을 자른다 → 그런 값은 거부
		if(preg_match('/[;\x00]|user=|password=/', $conninfo) || strpos($username.$password, "\0") !== false) {
			error_log("PostgreSQL connection error : connection setting contains a value not allowed by PDO_PGSQL (';', NUL, 'user=', 'password=')");
			return false;
		}
		try {
			$handle = new PDO('pgsql:'.$conninfo." client_encoding='UTF8'", $username, $password, array(
				PDO::ATTR_ERRMODE => PDO::ERRMODE_SILENT,
				PDO::ATTR_EMULATE_PREPARES => true,
				PDO::ATTR_STRINGIFY_FETCHES => false,
				PDO::ATTR_TIMEOUT => 0,
				PDO::ATTR_STATEMENT_CLASS => array('DBAdapterPDOStatement')
			));
		} catch (Exception $e) {
			if(!empty($database['ssl'])) error_log("PostgreSQL SSL connection error : ".trim(preg_replace(array('/\s+/', '/^SQLSTATE\[\w{5}\] \[\d+\] /'), array(' ', ''), $e->getMessage())));
			return false;
		}
		self::$dbProperties['handle'] = $handle;
		self::$dbProperties['charset'] = 'utf8';
		return true;
	}

	public static function unbind() {
		if (isset(self::$dbProperties['handle']) && !self::__isPDO()) pg_close(self::$dbProperties['handle']);	// PDO 는 객체를 놓으면 연결을 닫는다
		unset(self::$dbProperties['handle']);
		return true;
	}

	public static function charset() {
		if (array_key_exists('charset', self::$dbProperties)) return self::$dbProperties['charset'];
		else return null;
	}

	public static function dbms() {
		return 'PostgreSQL';
	}

	public static function version($mode = 'server') {
		/* 원본은 처음 호출에만 문자열을 돌려주고, 이후에는 보관한 pg_version() 배열 전체를 돌려줬다(문자열로 쓰면 'Array'·경고).
		   매번 모드에 맞는 값을 돌려준다. */
		if (!array_key_exists('version', self::$dbProperties)) {
			if (self::__isPDO()) {	// PDO: libpq·서버 버전(pg_version() 의 client·server 와 같은 값)
				if (!isset(self::$dbProperties['handle'])) return null;
				$handle = self::$dbProperties['handle'];
				self::$dbProperties['version'] = array('client' => $handle->getAttribute(PDO::ATTR_CLIENT_VERSION), 'server' => $handle->getAttribute(PDO::ATTR_SERVER_VERSION));
			} else {
				self::$dbProperties['version'] = isset(self::$dbProperties['handle']) ? pg_version(self::$dbProperties['handle']) : pg_version();
			}
		}
		$version = self::$dbProperties['version'];
		if($mode == 'server') return isset($version['server']) ? $version['server'] : null;
		else return isset($version['client']) ? $version['client'] : null;
	}
	public static function tableList($condition = null) {
		if (!array_key_exists('tableList', self::$dbProperties)) { 
			self::$dbProperties['tableList'] = self::queryColumn("SELECT table_name FROM information_schema.tables WHERE table_schema = 'public'");
		}
		if(!is_null($condition)) {
			$result = array();
			foreach(self::$dbProperties['tableList'] as $item) {
				if(strpos($item, $condition) === 0) array_push($result, $item);
			}
			return $result;
		} else {
			return self::$dbProperties['tableList'];
		}
	}

	public static function setTimezone($time) {
		// [보안] 시간대 문자열을 SQL 문자열 리터럴로 escape(원본은 그대로 연결 → 저장 값의 따옴표로 문 분리, pg_query 는 여러 문 실행)
		return self::query('SET TIME ZONE \'' . self::escapeString((string)$time) . '\'');
	}

	public static function reservedFieldNames() {
		return null;
	}

	/*@static@*/
	public static function reservedFunctionNames() {
		return array('UNIX_TIMESTAMP()');
	}

	public static function query($query, $compatibility = true) {	// 매개변수 이름 오타($compatiblity)로 아래 $compatibility 가 정의되지 않아 호환 변환이 실행되지 않았음(PHP 7.4 Notice / 8.x Warning) → 이름 수정
		/// Bypassing compatiblitiy issue : will be replace to NAF2.
		if($compatibility) {
			$query = self::__compatQuery($query);
		}		
		if( function_exists( '__tcSqlLogBegin' ) ) {
			__tcSqlLogBegin($query);
			$result = self::__execute($query);
			__tcSqlLogEnd($result,0);
		} else {
			$result = self::__execute($query);
		}
		self::$lastQueryType = strtolower(substr($query, 0,6));
		if( self::__isSchemaChange($query) ) {
			unset(self::$dbProperties['tableList']);
			self::clearCache();
		} else if( stristr($query, 'update ') ||
			stristr($query, 'insert ') ||
			stristr($query, 'delete ') ||
			stristr($query, 'replace ') ) {
			self::clearCache();
		}
		return $result;
	}

	/* query() 의 호환 변환을 함수로 분리. prepare() 는 문장 틀에 같은 변환을 적용한다.
	   [보안] CONCAT 변환의 따옴표 탐색이 닫는 따옴표를 찾지 못하면(백슬래시로 끝나는 값 등) 원본은 처음부터 다시 훑으며 끝나지 않았다 → 문장 끝으로 처리. */
	public static function __compatQuery($query) {
		// Textcube-NG: 따옴표·주석 밖의 UNIX_TIMESTAMP() 만 바꾼다(사용자 값 안의 같은 문자열이 저장 전에 epoch 로 바뀌던 문제, S-3)(v3.183)
		$query = self::__replaceOutsideQuotes($query, 'UNIX_TIMESTAMP()', (string)Timestamp::getUNIXtime()); // compatibility issue.

		// CONCAT
		$ppos = -1;
		$length = strlen($query);
		do {
			$pos = strpos($query, '\'', $ppos + 1);
			if ($pos === false) {
				$pos = strlen($query);
			}

			while (true) {
				// Textcube-NG: 함수 호출 CONCAT( 만 찾는다. 이름의 일부(GROUP_CONCAT, CONCAT_WS, concat_flag, pg_catalog.concat)는 바꾸지 않는다(v3.183)
				// Textcube-NG: r4-8 — SQLite 블록(r4-2)과 같게 탐색 구간만 따옴표 밖으로 묶음 — 따옴표 수의 제곱 시간 방지
				if ($pos - $ppos - 1 < 6 || !preg_match('/(?<![A-Za-z0-9_$.\x80-\xff])CONCAT(?=\s*\()/i', substr($query, $ppos + 1, $pos - $ppos - 1), $match, PREG_OFFSET_CAPTURE)) {
					break;
				}
				$concat = $ppos + 1 + $match[0][1];
				if ($concat >= $pos) {
					break;
				}

				$depth = 0;
				$quote = null;
				for ($i = $concat + 6; $i < $length; $i++) {
					if ($quote === null) {
						if ($query[$i] == '\'' || $query[$i] == '"') {
							$quote = $query[$i];
						} elseif ($query[$i] == ',' && $depth == 1) {	// Textcube-NG: CONCAT 바로 아래 인자 쉼표만 바꾸고(안쪽 REPLACE(x,'a','b') 의 쉼표는 그대로) 늘어난 길이를 반영한다(v3.183)
							$query = substr($query, 0, $i).' || '.substr($query, $i + 1);
							$length += 3;
						} elseif ($query[$i] == '(') {
							$depth++;
						} elseif ($query[$i] == ')') {
							if (--$depth == 0) {
								break;
							}
						}
					} else {
						if ($query[$i] == $quote) {	// Textcube-NG: 겹친 따옴표('')는 닫힘·열림으로 지나가고 백슬래시는 일반 문자(escapeString 결과)(v3.183)
							$quote = null;
						}
					}
				}
				$query = substr($query, 0, $concat).substr($query, $concat + 6);

				$pos = strpos($query, '\'', $ppos + 1);
				if ($pos === false) {	// [보안] 뒤에 따옴표가 없으면 문장 끝(원본은 false 로 남아 처음부터 다시 훑음)
					$pos = strlen($query);
				}
				$length = strlen($query);
			}

			$ppos = $pos;
			if ($ppos < $length) {	// Textcube-NG: 닫는 따옴표 앞 백슬래시를 따지지 않는다('a\' 처럼 백슬래시로 끝나는 값)(v3.183)
				$ppos = strpos($query, '\'', $ppos + 1);
				if ($ppos === false) {	// [보안] 닫는 따옴표가 없으면 문장 끝으로(원본은 false 로 남아 처음부터 다시 훑으며 끝나지 않음)
					$ppos = $length;
				}
			}
		} while ($ppos < $length);
		return $query;
	}

	/* Textcube-NG: 따옴표·주석 밖에서만 $search 를 $replace 로 바꾼다(v3.183). 사용자 값이 든 '…' 리터럴(escapeString 이 ' 를 '' 로 겹침),
	   "…" 식별자, -- 줄 주석, 블록 주석 안은 그대로 둔다. 같은 따옴표 두 개는 리터럴 안의 따옴표, 백슬래시는 일반 문자로 본다
	   (escapeString 결과는 이 규칙으로 서버와 경계가 같다). E'…' 리터럴·$$ 인용·중첩 주석은 다루지 않는다(앱 SQL 에 없음). */
	private static function __replaceOutsideQuotes($query, $search, $replace) {
		$query = (string)$query;
		$length = strlen($query);
		$result = '';
		$outside = 0;	// 아직 옮기지 않은 따옴표·주석 밖 구간의 시작
		$i = 0;
		while ($i < $length) {
			$c = $query[$i];
			if ($c == '\'' || $c == '"') {
				$end = $i + 1;
				while (($end = strpos($query, $c, $end)) !== false && $end + 1 < $length && $query[$end + 1] == $c) {
					$end += 2;	// 겹친 따옴표는 리터럴 안의 문자
				}
				$end = ($end === false) ? $length : $end + 1;
			} elseif ($c == '-' && $i + 1 < $length && $query[$i + 1] == '-') {
				$end = strpos($query, "\n", $i + 2);
				$end = ($end === false) ? $length : $end + 1;
			} elseif ($c == '/' && $i + 1 < $length && $query[$i + 1] == '*') {
				$end = strpos($query, '*/', $i + 2);
				$end = ($end === false) ? $length : $end + 2;
			} else {
				$i++;
				continue;
			}
			$result .= str_replace($search, $replace, substr($query, $outside, $i - $outside)).substr($query, $i, $end - $i);
			$i = $outside = $end;
		}
		return $result.str_replace($search, $replace, substr($query, $outside));
	}
	
	/*@static@*/
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

	/*@static@*/
	public static function queryCount($query) {
		$count = 0;
		$query = trim($query);
		if ($result = self::query($query)) {
			$operation = strtolower(substr($query, 0,6));
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
					$count = self::__affectedRows($result);
					break;
			}
		}
		return $count;
	}
		
	/*@static@*/
	public static function queryCell($query, $field = 0, $useCache=true) {
		$type = 'both';
		if (is_numeric($field)) {
			$type = 'num';
		} else {
			$type = 'assoc';
		}

		if( $useCache ) {
			$result = self::queryAllWithCache($query, $type);
		} else {
			$result = self::queryAllWithoutCache($query, $type);
		}
		if( empty($result) ) {
			return null;
		}
		return $result[0][$field];
	}
	
	/*@static@*/
	public static function queryRow($query, $type = 'both', $useCache=true) {
		if( $useCache ) {
			$result = self::queryAllWithCache($query, $type, 1);
		} else {
			$result = self::queryAllWithoutCache($query, $type, 1);
		}
		if( empty($result) ) {
			return null;
		}
		return $result[0];
	}
	
	/*@static@*/
	public static function queryColumn($query, $useCache=true) {
		$cacheKey = "{$query}_queryColumn";
		if( $useCache && isset( self::$cachedResult[$cacheKey] ) ) {
			if( function_exists( '__tcSqlLogBegin' ) ) {
				__tcSqlLogBegin($query);
				__tcSqlLogEnd(null,1);
			}
			self::$cachedResult[$cacheKey][0]++;
			return self::$cachedResult[$cacheKey][1];
		}

		$column = null;
		if ($result = self::query($query)) {
			$column = array();
			while ($row = self::__fetchRow($result, self::__queryType('num')))
				array_push($column, $row[0]);
			self::__closeResult($result);
		}

		if( $useCache && !is_null($column) ) {	// 실패(null)는 캐시하지 않음
			self::$cachedResult[$cacheKey] = array( 1, $column );
		}
		return $column;
	}
	
	/*@static@*/
	public static function queryAll($query, $type = 'both', $count = -1) {
		return self::queryAllWithCache($query, $type, $count);
	}

	public static function queryAllWithoutCache($query, $type = 'both', $count = -1) {
		$all = array();
		$realtype = self::__queryType($type);
		if ($result = self::query($query)) {
			while ( ($count-- !=0) && $row = self::__fetchRow($result, $realtype))
				array_push($all, $row);
			self::__closeResult($result);
			return $all;
		}
		return null;
	}
		
	public static function queryAllWithCache($query, $type = 'both', $count = -1) {
		$cacheKey = "{$query}_{$type}_{$count}";
		if( isset( self::$cachedResult[$cacheKey] ) ) {
			if( function_exists( '__tcSqlLogBegin' ) ) {
				__tcSqlLogBegin($query);
				__tcSqlLogEnd(null,1);
			}
			self::$cachedResult[$cacheKey][0]++;
			return self::$cachedResult[$cacheKey][1];
		}
		$all = self::queryAllWithoutCache($query,$type,$count);
		/* 실패한 질의(null)는 캐시하지 않는다(원본은 실패 결과도 다음 쓰기 문장까지 돌려줘, 같은 요청에서 테이블을 만든 뒤에도 실패로 보였다). */
		if( !is_null($all) ) self::$cachedResult[$cacheKey] = array( 1, $all );
		return $all;
	}
	
	/*@static@*/
	public static function execute($query) {
		return self::query($query) ? true : false;
	}
	
	/*@static@*/
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
	
	public static function insertId() {
		return null;
	}
	
	/* 드라이버별 실행·결과 처리. 네이티브는 원본과 같은 pg_* 호출.
	   PDO 는 PDO::query()(결과는 DBAdapterPDOStatement — 결과 열이 없는 문장도 네이티브처럼 결과 객체). 실패는 false 이고 오류 문구는 lastError(pg_last_error() 와 같은 문구) */
	public static function __execute($query) {
		$handle = isset(self::$dbProperties['handle']) ? self::$dbProperties['handle'] : null;
		if (!self::__isPDO()) return $handle ? pg_query($handle, $query) : false;
		if (!$handle) return false;
		if ((string)$query === '') return self::__pdoFail('');	// 네이티브처럼 빈 오류 문구로 실패(PHP 8 의 PDO::query('') 는 ValueError)
		$result = $handle->query($query);
		if ($result === false) {
			$info = $handle->errorInfo();
			return self::__pdoFail(isset($info[2]) ? $info[2] : '');
		}
		self::$dbProperties['lastError'] = '';
		return $result;
	}

	public static function __pdoFail($message) {
		self::$dbProperties['lastError'] = (string)$message;
		return false;
	}

	public static function __isPDO() {
		return (isset(self::$dbProperties['driver']) && self::$dbProperties['driver'] === 'pdo');
	}

	/* 결과 행 수(네이티브 pg_num_rows — 결과 열이 없는 문장은 0) */
	public static function __numRows($result) {
		if ($result instanceof PDOStatement) return ($result->columnCount() == 0) ? 0 : $result->rowCount();
		return pg_num_rows($result);
	}

	/* 영향받은 행 수(네이티브 pg_affected_rows — 명령 태그의 행 수). PDO 의 rowCount() 는 행을 돌려주는 문장이면 결과 행 수이므로
	   명령 태그에 행 수가 없는 SHOW·EXPLAIN·CALL 은 0 */
	public static function __affectedRows($result) {
		if ($result instanceof PDOStatement) {
			if ($result->columnCount() > 0 && preg_match('/^(?:\s|--[^\n]*\n|\/\*.*?\*\/)*(SHOW|EXPLAIN|CALL)\b/is', $result->queryString)) return 0;
			return $result->rowCount();
		}
		return pg_affected_rows($result);
	}

	public static function __closeResult($result) {
		if ($result instanceof PDOStatement) return $result->closeCursor();
		return pg_free_result($result);
	}

	/* 한 행 읽기. PDO 는 FETCH_NAMED 로 읽어(같은 이름의 열은 값 묶음) 네이티브 pg_fetch_array() 와 같은 키·순서·값 형식으로 다시 짠다 —
	   BOTH 는 열마다 번호 키 다음 이름 키(같은 키면 뒤 값). 끝이면 false */
	public static function __fetchRow($result, $realtype) {
		if (!($result instanceof DBAdapterPDOStatement)) return pg_fetch_array($result, null, $realtype);
		if ($result->columnCount() == 0) return false;	// 열이 없는 결과(UPDATE 등) — 네이티브 pg_fetch_array() 처럼 false (PDO fetch() 는 빈 배열)
		$named = $result->fetch(PDO::FETCH_NAMED);
		if (!is_array($named)) return false;
		$values = array();
		foreach ($named as $name => $value) $values[(string)$name] = $value;	// FETCH_NAMED 는 숫자 모양 열 이름("1")을 문자열 키 그대로 넣어 $named['1'] 로 찾을 수 없다 → 보통 배열 키로 다시 넣는다
		$names = $result->columnNames($values);
		$row = array();
		$next = array();
		foreach ($names as $i => $name) {
			$value = array_key_exists($name, $values) ? $values[$name] : null;
			if (is_array($value)) {	// 같은 이름의 열: 열 순서대로 하나씩
				$k = isset($next[$name]) ? $next[$name] : 0;
				$next[$name] = $k + 1;
				$value = isset($value[$k]) ? $value[$k] : null;
			}
			$row[$i] = self::__pdoValue($result, $i, $value);
		}
		if ($realtype == PDO::FETCH_NUM) return $row;
		$keyed = array();
		foreach ($row as $i => $value) {
			if ($realtype == PDO::FETCH_BOTH) $keyed[$i] = $value;
			$keyed[$names[$i]] = $value;
		}
		return $keyed;
	}

	/* PDO 값 → 네이티브(텍스트 결과) 형식: 정수는 문자열, bool 은 't'·'f', bytea(스트림)는 \x 16진수, 실수(PHP 8.4 이상)는 PostgreSQL 출력 형식 */
	public static function __pdoValue($result, $column, $value) {
		if (is_int($value)) return (string)$value;
		if (is_bool($value)) return $value ? 't' : 'f';
		if (is_float($value)) return self::__floatText($value, $result, $column);
		if (is_resource($value)) return '\\x'.bin2hex((string)stream_get_contents($value));
		return $value;
	}

	/* PHP 8.4 이상의 PDO_PGSQL 은 실수 열(float4·float8)을 PHP 실수로 돌려준다 → PostgreSQL 기본 출력(extra_float_digits 1: 되돌렸을 때 같은 값이 되는 가장 짧은 표기)으로 되돌린다.
	   지수가 -4 이상 15(float4 는 6) 미만이면 고정 소수점, 그 밖은 d.ddde±XX. NaN·Infinity·-Infinity·-0 은 PostgreSQL 표기 */
	/* $digits×10^$scale 이 $value 로 읽히고 PostgreSQL 출력이 고르는 표기인지. PostgreSQL(float8) 은 이웃 double 과의 정확한 중간점에 놓인 표기
	   (읽을 때 짝수 쪽 반올림으로만 $value 가 되는 값)를 고르지 않는다 — 끝에 0…01 을 붙인 값과 한 자리 내린 9…9 값이 모두 $value 로 읽혀야 중간점이 아니다.
	   float4 는 PDO 가 PostgreSQL float4 출력(9자리 이하)을 읽은 값이므로 그 표기를 그대로 쓴다 */
	public static function __floatMatch($digits, $scale, $value, $result, $column) {
		if ((float)($digits.'e'.$scale) != $value) return false;
		if ($value < 1e14 || $value >= 1e42) return true;	// 이 범위 밖에서는 17자리 이하 십진 표기가 이웃 double 과의 중간점과 같을 수 없음
		$k = 60;
		if ((float)($digits.str_repeat('0', $k).'1e'.($scale - $k - 1)) == $value
			&& (float)(((int)$digits - 1).str_repeat('9', $k + 1).'e'.($scale - $k - 1)) == $value) return true;
		return $result->columnType($column) === 'float4';
	}

	public static function __floatText($value, $result, $column) {
		if (is_nan($value)) return 'NaN';
		if (is_infinite($value)) return ($value > 0) ? 'Infinity' : '-Infinity';
		$sign = (ord(pack('E', $value)[0]) & 0x80) ? '-' : '';	// 부호 비트(-0 포함 — sprintf 는 -0 의 부호를 버림)
		$value = abs($value);
		if ($value == 0) return $sign.'0';
		// 유효 숫자를 하나씩 늘려(%e 는 올바르게 반올림) 같은 값이 되는 가장 짧은 표기를 찾는다. 2의 거듭제곱 값은 아래쪽 간격이 좁아 반올림한 값 대신 한 칸 위 값만 맞을 수 있다
		// 유효 숫자 개수를 이분 탐색해(N 자리 후보가 맞으면 N+1 자리 후보도 맞음) 같은 값이 되는 가장 짧은 표기를 찾는다. 후보는 %e 로 반올림한 값과
		// 그 한 칸 위 값(2의 거듭제곱 값은 아래쪽 간격이 좁아 한 칸 위 값만 맞을 수 있음). 17자리(%.16e)는 항상 맞는다
		$found = null;
		for ($low = 0, $high = 16; $low <= $high; ) {
			$precision = ($low + $high) >> 1;
			$parts = explode('e', sprintf('%.'.$precision.'e', $value));
			$digits = str_replace('.', '', $parts[0]);
			$exponent = (int)$parts[1];
			$scale = $exponent - (strlen($digits) - 1);
			$candidate = null;
			if (self::__floatMatch($digits, $scale, $value, $result, $column)) {
				$candidate = array($digits, $exponent);
			} else {
				$up = (string)((int)$digits + 1);
				if (self::__floatMatch($up, $scale, $value, $result, $column)) $candidate = array($up, (strlen($up) > strlen($digits)) ? $exponent + 1 : $exponent);
			}
			if ($candidate !== null) {
				$found = $candidate;
				$high = $precision - 1;
			} else {
				$low = $precision + 1;
			}
		}
		list($digits, $exponent) = $found;
		$digits = rtrim($digits, '0');
		$length = strlen($digits);
		$fixedLimit = ($exponent >= 6 && $exponent < 15 && $result->columnType($column) === 'float4') ? 6 : 15;
		if ($exponent < -4 || $exponent >= $fixedLimit) {
			$text = $digits[0].($length > 1 ? '.'.substr($digits, 1) : '').'e'.($exponent < 0 ? '-' : '+').sprintf('%02d', abs($exponent));
		} else if ($exponent < 0) {
			$text = '0.'.str_repeat('0', -$exponent - 1).$digits;
		} else if ($length <= $exponent + 1) {
			$text = $digits.str_repeat('0', $exponent + 1 - $length);
		} else {
			$text = substr($digits, 0, $exponent + 1).'.'.substr($digits, $exponent + 1);
		}
		return $sign.$text;
	}

	public static function __lastErrorMessage() {
		if (self::__isPDO()) return isset(self::$dbProperties['lastError']) ? self::$dbProperties['lastError'] : '';
		return isset(self::$dbProperties['handle']) ? pg_last_error(self::$dbProperties['handle']) : '';
	}

	/* 연결 문자셋 이름(Debug 의 오류 문구 변환용). PDO 는 연결 문자열의 client_encoding='UTF8' */
	public static function __clientEncoding() {
		if (self::__isPDO()) return 'UTF8';
		return pg_client_encoding(self::$dbProperties['handle']);
	}

	public static function escapeString($string, $link = null){
		if (self::__isPDO() && isset(self::$dbProperties['handle'])) {
			/* PDO::quote() 결과에서 감싼 따옴표를 뗀다(같은 libpq 이스케이프 PQescapeStringConn — pg_escape_string() 과 같은 결과) */
			$quoted = self::$dbProperties['handle']->quote($string);
			if ($quoted === false) return '';	// 잘못된 문자 인코딩
			return substr($quoted, 1, -1);
		}
		if (isset(self::$dbProperties['handle'])) return pg_escape_string(self::$dbProperties['handle'], $string);
		/* 연결 전(드라이버 모름)·해제 후: 네이티브와 같은 pg_escape_string($string)(libpq PQescapeString).
		   pgsql 확장이 없는 PDO 전용 설치는 \ 와 ' 를 겹친다(standard_conforming_strings 켜짐·꺼짐 모두 따옴표 밖으로 나가지 못함) */
		if (function_exists('pg_escape_string')) return pg_escape_string($string);
		return strtr((string)$string, array('\\' => '\\\\', "'" => "''"));
	}
	
	/* 스키마 변경 문장(CREATE·ALTER·DROP·TRUNCATE·RENAME) 여부 — 맨 앞 공백과 SQL 주석(-- 줄 주석·블록 주석)을 건너뛴 첫 낱말로 판단(문자열 리터럴 안의 낱말은 해당 없음).
	   원본은 UPDATE·INSERT·DELETE·REPLACE 가 들어 있을 때만 질의 캐시를 비워, 같은 요청에서 테이블을 만들거나 열을 바꾼 뒤에도
	   캐시된 SELECT(이전의 실패 결과·바뀌기 전 열)와 테이블 목록(tableList)을 그대로 돌려줬다. */
	public static function __isSchemaChange($query) {
		return (preg_match('/^(?:\s|--[^\n]*\n|\/\*.*?\*\/)*(CREATE|ALTER|DROP|TRUNCATE|RENAME)\b/is', (string)$query) === 1);
	}

	public static function clearCache() {
		self::$cachedResult = array();
		if( function_exists( '__tcSqlLogBegin' ) ) {
			__tcSqlLogBegin("Cache cleared");
			__tcSqlLogEnd(null,2);
		}
	}

	public static function cacheLoad() {
		global $fileCachedResult;
	}

	public static function cacheSave() {
		@self::commit();
	}

	public static function commit() { 
		if (!isset(self::$dbProperties['handle'])) return false;
		if (self::__isPDO()) return self::__execute("commit");	// PDO::commit() 은 트랜잭션 밖에서 예외 — 네이티브처럼 문장을 보낸다
		return pg_query(self::$dbProperties['handle'], "commit");
//		return true; // Auto commit.
	}

	/* Raw public static functions (to easier adoptation) */
	/*@static@*/
	public static function num_rows($handle = null) {
		switch(self::$lastQueryType) {
			case 'select':
				return self::__numRows($handle);
				break;
			default:
				return self::__affectedRows($handle);
				break;
		}
		return null;
	}
	
	/*@static@*/
	public static function free($handle = null) {
		if (!($handle instanceof PDOStatement) && !($handle instanceof PgSql\Result) && !is_resource($handle)) return;	// Textcube-NG: 결과(PDOStatement, PHP 8.1 부터 PgSql\Result, 그 전 resource)가 아닌 값(DBModel::getAll() 의 배열·실패한 질의의 false·null)은 닫을 것이 없다. 원본(PHP 5.6 의 pg_free_result)은 경고 뒤 계속했지만 PHP 8.0 부터는 TypeError 로 멈춘다(v3.214)
		self::__closeResult($handle);
	}
	
	/*@static@*/
	public static function fetch($handle = null, $type = 'assoc') {
		if ($handle instanceof DBAdapterPDOStatement) return self::__fetchRow($handle, self::__queryType($type == 'array' ? 'both' : ($type == 'row' ? 'num' : 'assoc')));
		if($type == 'array') return pg_fetch_array($handle); // Can I use mysql_fetch_row instead?
		else if ($type == 'row') return pg_fetch_row($handle);
		else return pg_fetch_assoc($handle);
	}
	
	/*@static@*/
	public static function error($err = null) {
		// 원본은 없는 함수 pg_error() 를 불러 Fatal error → pg_last_error
		if($err === null) return self::__lastErrorMessage();
		if($err instanceof PDO || $err instanceof PDOStatement) {	// PDO 모드: 넘긴 연결·결과의 오류 문구(오류가 없으면 빈 문자열)
			$info = $err->errorInfo();
			return (isset($info[0]) && $info[0] !== '00000' && isset($info[2])) ? (string)$info[2] : '';
		}
		else return pg_last_error($err);
	}
	
	/*@static@*/
	public static function stat($stat = null) {
		// 원본은 연결 인자 없이 호출(PHP 8: ArgumentCountError) → 보관한 연결 전달
		if($stat === null) $stat = isset(self::$dbProperties['handle']) ? self::$dbProperties['handle'] : null;
		// PDO 모드: 연결 상태 문구로 PGSQL_CONNECTION_OK(0)·PGSQL_CONNECTION_BAD(1) 와 같은 값을 돌려준다(pgsql 확장이 없으면 상수도 없음)
		if($stat instanceof PDO) return (strpos((string)$stat->getAttribute(PDO::ATTR_CONNECTION_STATUS), 'Connection OK') === 0) ? 0 : 1;
		if($stat === null) return defined('PGSQL_CONNECTION_BAD') ? PGSQL_CONNECTION_BAD : 1;
		return pg_connection_status($stat);
	}
	
	/*@static@*/
	public static function __queryType($type) {
		if (self::__isPDO()) {
			switch(strtolower($type)) {
				case 'num':
					return PDO::FETCH_NUM;
				case 'assoc':
					return PDO::FETCH_ASSOC;
				case 'both':
				default:
					return PDO::FETCH_BOTH;
			}
		}
		switch(strtolower($type)) {
			case 'num':
				return PGSQL_NUM;
			case 'assoc':
				return PGSQL_ASSOC;				
			case 'both':
			default:
				return PGSQL_BOTH;
		}
	}
	
	public static function fieldType($abstractType) {
		/* 원본은 정적 속성이 아닌 지역 변수 $typeTable 을 읽어 항상 null 을 돌려줬다. */
		if(isset(self::$typeTable[$abstractType])) return self::$typeTable[$abstractType];
	}

    static $typeTable = array(
        "integer" => "integer",
        "int" => "integer",
        "float" => "float",
        "double" => "float",
        "timestamp" => "integer",
        "mediumtext" => "varchar(512)",
        "varchar" => "varchar",
        "vartext" => "varchar",	// MySQL·MySQLi 형식표의 'vartext' 와 같은 뜻(별칭)
        "text" => "text");

/*** Prepared Statement API — Textcube-NG: php8.2 이식본과 같은 구현을 옮겼다(v3.211) ***/
	/* MySQLi 어댑터와 같은 호출 방식. 문장 틀에 query() 와 같은 호환 변환을 적용한 뒤 따옴표 밖의 ? 를 $1, $2 ... 로 바꾸고
	   값은 pg_query_params 로 따로 전달한다(값은 SQL 문장으로 해석되지 않음). */
	public static function prepare($query) {
		if (!isset(self::$dbProperties['handle'])) return false;
		if (self::__isPDO()) return self::__preparePDO(self::__compatQuery($query));
		$parts = self::__splitPlaceholders(self::__compatQuery($query));
		$query = $parts[0];
		for ($i = 1; $i < count($parts); $i++) {
			$query .= '$' . $i . $parts[$i];
		}
		return new DBAdapterStatement($query, $parts, count($parts) - 1);
	}

	public static function bindAndExecute($stmt, $types) {
		$params = array_slice(func_get_args(), 2);	// Textcube-NG: PHP 5.5 에는 가변 인자(...)가 없다(r4-4)
		if (!($stmt instanceof DBAdapterStatement)) return false;
		$stmt->reset();
		$values = $stmt->normalize($types, $params);
		if ($values === false) return false;
		if (!isset(self::$dbProperties['handle'])) return $stmt->fail('No PostgreSQL connection');
		$args = array();
		foreach ($values as $value) {
			if (is_null($value[0])) $args[] = null;
			else if ($value[1] == 'i') $args[] = (string)$value[0];
			else if ($value[1] == 'd') $args[] = var_export($value[0], true);
			else if (strpos($value[0], "\0") !== false) return $stmt->fail('String parameter contains NUL byte');	// text 형은 NUL 을 담지 못함(pg_query_params 는 NUL 에서 잘라 보냄)
			else $args[] = $value[0];
		}
		if (self::__isPDO()) return self::__bindAndExecutePDO($stmt, $args);
		$result = @pg_query_params(self::$dbProperties['handle'], $stmt->query, $args);
		self::$lastQueryType = strtolower(substr($stmt->query, 0,6));
		if ($result === false) return $stmt->fail(pg_last_error(self::$dbProperties['handle']));
		if (pg_num_fields($result) > 0) {
			$stmt->result = $result;
			$stmt->affected_rows = 0;
		} else {
			$stmt->affected_rows = pg_affected_rows($result);
			pg_free_result($result);
		}
		if( self::__isSchemaChange($stmt->query) ) {
			unset(self::$dbProperties['tableList']);
			self::clearCache();
		} else if( stristr($stmt->query, 'update ') ||
			stristr($stmt->query, 'insert ') ||
			stristr($stmt->query, 'delete ') ||
			stristr($stmt->query, 'replace ') ) {
			self::clearCache();
		}
		return true;
	}

	public static function fetchAllStmt($stmt) {
		if (!($stmt instanceof DBAdapterStatement) || !$stmt->result) return null;
		$all = array();
		while ($row = self::__fetchRow($stmt->result, self::__queryType('assoc'))) {
			$all[] = $row;
		}
		$stmt->freeResult();
		$stmt->affected_rows = count($all);
		return $all;
	}

	/* 따옴표('...', "...") 밖의 자리표시자 ? 로 문장을 나눈다. 같은 따옴표 두 개('')는 따옴표 문자로 보고 리터럴을 이어 간다.
	   (문장 틀에 이어 붙인 escape 된 리터럴 안의 ? 는 값의 일부로 남는다) */
	public static function __splitPlaceholders($query) {
		$parts = array();
		$start = 0;
		$quote = null;
		$length = strlen($query);
		for ($i = 0; $i < $length; $i++) {
			$c = $query[$i];
			if ($quote !== null) {
				if ($c === $quote) {
					if ($i + 1 < $length && $query[$i + 1] === $quote) $i++;
					else $quote = null;
				}
			} else if ($c === '\'' || $c === '"') {
				$quote = $c;
			} else if ($c === '?') {
				$parts[] = substr($query, $start, $i - $start);
				$start = $i + 1;
			}
		}
		$parts[] = substr($query, $start);
		return $parts;
	}

	/* PDO 모드 prepare(). 네이티브처럼 문장 틀만 보관하고(서버에 보내지 않음), 실행할 때 PDO 서버 쪽 바인딩
	   (PQexecParams — pg_query_params() 와 같은 확장 질의 프로토콜)으로 보낸다. PDO 는 문장 틀을 자체 규칙으로 다시 읽어 ? 를 $1, $2 ... 로 바꾸므로
	   __splitPlaceholders() 와 다르게 읽힐 수 있는 틀은 거부한다(__checkTemplate) */
	public static function __preparePDO($query) {
		$reason = self::__checkTemplate($query);
		if ($reason !== '') return self::__pdoFail('Statement rejected: '.$reason);
		$parts = self::__splitPlaceholders($query);
		return new DBAdapterStatement($query, $parts, count($parts) - 1);
	}

	/* PDO 모드 문장 틀 확인 — __splitPlaceholders() 와 같은 따옴표 규칙으로 훑는다. 따옴표 밖의 :이름·??·$(달러 인용·$n)·주석(-- /*)·E'' 문자열과 NUL 은 거부.
	   PHP 8.4 미만의 PDO 는 따옴표 안의 \ 를 이스케이프로 보므로 홀수 개의 \ 바로 뒤에 같은 따옴표가 오는 리터럴도 거부. 문제가 없으면 빈 문자열 */
	public static function __checkTemplate($query) {
		$query = (string)$query;
		if (strpos($query, "\0") !== false) return 'NUL byte';
		$quote = null;
		$length = strlen($query);
		for ($i = 0; $i < $length; $i++) {
			$c = $query[$i];
			if ($quote !== null) {
				if ($c === '\\' && PHP_VERSION_ID < 80400) {
					$run = strspn($query, '\\', $i);
					if (($run % 2) == 1 && $i + $run < $length && $query[$i + $run] === $quote) return 'backslash before a quote in a literal';
					$i += $run - 1;
				} else if ($c === $quote) {
					if ($i + 1 < $length && $query[$i + 1] === $quote) $i++;
					else $quote = null;
				}
			} else if ($c === '\'' || $c === '"') {
				if ($c === '\'' && $i > 0 && ($query[$i - 1] === 'E' || $query[$i - 1] === 'e') && ($i < 2 || !preg_match('/[A-Za-z0-9_\x80-\xff]/', $query[$i - 2]))) return 'E\'\' string';
				$quote = $c;
			} else if ($c === ':' && $i + 1 < $length && preg_match('/[A-Za-z0-9_]/', $query[$i + 1]) && ($i == 0 || $query[$i - 1] !== ':')) {
				return 'named placeholder';
			} else if ($c === '?' && $i + 1 < $length && $query[$i + 1] === '?') {
				return '??';
			} else if ($c === '$') {
				return 'dollar sign';
			} else if (($c === '-' && $i + 1 < $length && $query[$i + 1] === '-') || ($c === '/' && $i + 1 < $length && $query[$i + 1] === '*')) {
				return 'comment';
			}
		}
		return '';
	}

	/* PDO 모드 바인딩·실행. 값은 bindAndExecute() 가 네이티브와 같게 정리한 문자열·NULL 을 형식 지정 없이 보낸다(pg_query_params() 와 같음).
	   실행할 때마다 새로 준비한다(이름 있는 준비 문장을 서버에 남기지 않음 — DISABLE_PREPARES) */
	public static function __bindAndExecutePDO($stmt, $args) {
		$pdo = self::$dbProperties['handle'];
		self::$lastQueryType = strtolower(substr($stmt->query, 0,6));
		if ((string)$stmt->query === '') {	// 빈 문장 — PDO::prepare('') 는 ValueError, 네이티브 pg_query_params('') 는 빈 오류 메시지로 실패
			self::__pdoFail('');
			return $stmt->fail(self::$dbProperties['lastError']);
		}
		$prepOptions = array(PDO::ATTR_EMULATE_PREPARES => false);
		if (defined('PDO::PGSQL_ATTR_DISABLE_PREPARES')) $prepOptions[PDO::PGSQL_ATTR_DISABLE_PREPARES] = true;	// Textcube-NG: 5.6+ 상수(r4-4)
		$handle = $pdo->prepare($stmt->query, $prepOptions);
		if ($handle === false) {
			$info = $pdo->errorInfo();
			self::__pdoFail(isset($info[2]) ? $info[2] : '');
			return $stmt->fail(self::$dbProperties['lastError']);
		}
		foreach ($args as $index => $arg) {
			if (!$handle->bindValue($index + 1, $arg, is_null($arg) ? PDO::PARAM_NULL : PDO::PARAM_STR)) {
				self::__pdoFail('Unable to bind parameter '.($index + 1));
				return $stmt->fail(self::$dbProperties['lastError']);
			}
		}
		if (!$handle->execute()) {
			$info = $handle->errorInfo();
			self::__pdoFail(isset($info[2]) ? $info[2] : '');
			return $stmt->fail(self::$dbProperties['lastError']);
		}
		self::$dbProperties['lastError'] = '';
		if ($handle->columnCount() > 0) {
			$stmt->result = $handle;
			$stmt->affected_rows = 0;
		} else {
			$stmt->affected_rows = $handle->rowCount();
			$handle->closeCursor();
		}
		if( self::__isSchemaChange($stmt->query) ) {
			unset(self::$dbProperties['tableList']);
			self::clearCache();
		} else if( stristr($stmt->query, 'update ') ||
			stristr($stmt->query, 'insert ') ||
			stristr($stmt->query, 'delete ') ||
			stristr($stmt->query, 'replace ') ) {
			self::clearCache();
		}
		return true;
	}
}

/*** Prepared Statement 문장 객체 ***/
/* DBAdapter::prepare() 가 돌려주는 문장 객체. 호출부가 MySQLi 의 mysqli_stmt 처럼 쓰는 affected_rows·errno·error 속성과 close() 를 제공한다.
   (어댑터는 요청마다 하나만 읽으므로 어댑터 파일마다 같은 이름으로 둔다) */
class DBAdapterStatement {
	public $query;
	public $handle;
	public $paramCount;
	public $result = null;
	public $affected_rows = -1;
	public $errno = 0;
	public $error = '';

	public function __construct($query, $handle, $paramCount) {
		$this->query = $query;
		$this->handle = $handle;
		$this->paramCount = $paramCount;
	}

	/* mysqli_stmt::bind_param 의 형식 문자(i 정수, d 실수, s 문자열, b 바이너리)로 값을 정리한다. null 은 형식과 무관하게 NULL.
	   형식 문자열 길이·값 개수·자리표시자 개수가 다르거나, 모르는 형식 문자, 유한하지 않은 실수는 실패(false). */
	public function normalize($types, $params) {
		$types = (string)$types;
		$params = array_values($params);
		if (strlen($types) != count($params) || count($params) != $this->paramCount)
			return $this->fail('Number of bind values does not match the number of placeholders');
		$values = array();
		foreach ($params as $index => $value) {
			$type = $types[$index];
			if (strpos('idsb', $type) === false) return $this->fail('Unknown bind type');
			if (is_null($value)) {
				$values[] = array(null, $type);
				continue;
			}
			if ($type == 'i') {
				$value = (int)$value;
			} else if ($type == 'd') {
				$value = (float)$value;
				if (!is_finite($value)) return $this->fail('Non-finite float value');
			} else {
				$value = (string)$value;
			}
			$values[] = array($value, $type);
		}
		return $values;
	}

	public function fail($message, $code = 1) {
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
		else if ($this->result) pg_free_result($this->result);
		$this->result = null;
		return true;
	}

	public function close() {
		$this->freeResult();
		$this->handle = null;
		return true;
	}
}

/* PDO 모드 결과 문장 객체 — PDO::ATTR_STATEMENT_CLASS 로 PDO 가 만든다. 같은 이름의 열이 있을 때의 열 이름(columnNames)과
   실수 열의 형식(columnType: float4·float8)을 한 번 읽어 둔다. PDO 가 없는 설치에서도 이 파일을 읽을 수 있게 조건부로 선언 */
if (class_exists('PDOStatement', false)) {
class DBAdapterPDOStatement extends PDOStatement {
	public $names = null;
	public $types = array();

	public function columnNames($named) {
		if ($this->names === null) {
			$count = $this->columnCount();
			if (count($named) == $count) {
				$this->names = array_keys($named);
			} else {
				$this->names = array();	// 같은 이름의 열이 있으면(FETCH_NAMED 가 묶음) 열 정보로 읽는다
				for ($i = 0; $i < $count; $i++) {
					$meta = $this->getColumnMeta($i);
					$this->names[$i] = (is_array($meta) && isset($meta['name'])) ? $meta['name'] : $i;
				}
			}
		}
		return $this->names;
	}

	public function columnType($column) {
		if (!array_key_exists($column, $this->types)) {
			$meta = $this->getColumnMeta($column);
			$this->types[$column] = (is_array($meta) && isset($meta['native_type'])) ? $meta['native_type'] : '';
		}
		return $this->types[$column];
	}
}
}
?>
