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

// DBQuery version 1.8 for SQLite3.
/* 드라이버 선택: config.php 의 $database['driver'] 로 고른다.
   'native'(기본값·생략 시) — sqlite3 확장(SQLite3 클래스), 기존 동작.  'pdo' — PDO_SQLITE(PDO::getAvailableDrivers() 에 'sqlite').
   두 드라이버 모두 같은 파일(__TEXTCUBE_DATA_DIR__/<database>.sqlite)을 연다. 연결 객체는 self::$db(SQLite3 또는 PDO).
   PDO 모드도 query() 는 첫 문장만, execute() 는 모든 문장을 실행한다(네이티브와 같음). */

global $fileCachedResult;

class DBAdapter implements IAdapter {	
	static $db;
	static $cachedResult, $dbProperties, $escapeTag, $lastQueryType;
	/* fetch() 가 끝까지 읽은 SQLite3Result(spl_object_id => 결과 객체). 객체를 함께 보관해 free() 전까지 그 id 가 다른 결과에 다시 쓰이지 않게 한다. */
	static $fetchDone = array();
	public static function bind($database) {
		// Connects DB and set environment variables
		// $database array should contain 'server','username','password'.
		self::$cachedResult = self::$dbProperties = array();
		if(!isset($database) || empty($database)) return false;
		if(!isset($database['port']) && strpos($database['server'],':')) {
			$port = explode(":",$database['server']);
			$database['server'] = $port[0];
			$database['port'] = $port[1];
		}
		if(!defined('__TEXTCUBE_DATA_DIR__')){
			/* 설치(setup.php)는 Dispatcher::pathSelector() 를 거치지 않아 __TEXTCUBE_CACHE_DIR__ 가 없다 — PHP 8 은 정의되지 않은 상수가 Error
			   (7.x 는 상수 이름 문자열로 간주해 ./__TEXTCUBE_CACHE_DIR__ 에 DB 를 만들었고, 실행 시 경로 ROOT/cache 와 달랐음) → pathSelector() 와 같은 ROOT.'/cache' */
			define('__TEXTCUBE_DATA_DIR__', defined('__TEXTCUBE_CACHE_DIR__') ? __TEXTCUBE_CACHE_DIR__ : ROOT.'/cache');
		}
		if(!file_exists(__TEXTCUBE_DATA_DIR__)) {
			@mkdir(__TEXTCUBE_DATA_DIR__);
		}
		// 드라이버 선택: 'native'(기본값) 또는 'pdo'. 그 밖의 값이면 연결하지 않는다
		$driver = isset($database['driver']) ? $database['driver'] : '';
		if($driver === '') $driver = 'native';
		if(!is_string($driver) || !in_array(strtolower($driver), array('native','pdo'), true)) {
			error_log("SQLite3 connection error : unknown driver setting (database['driver'] must be 'native' or 'pdo')");
			return false;
		}
		if(strtolower($driver) == 'pdo') return self::__bindPDO($database);
		if(!class_exists('SQLite3')) {
			error_log("SQLite3 connection error : sqlite3 extension is not available");
			return false;
		}
		self::$db = new SQLite3(__TEXTCUBE_DATA_DIR__.'/'.$database['database'].'.sqlite',SQLITE3_OPEN_READWRITE | SQLITE3_OPEN_CREATE);

		if(!self::$db) return false;
		/* Textcube-NG: SQLite3 확장의 잠금 대기 시간 기본값은 0 이라 다른 요청이 쓰는 중이면(첨부 업로드와 목록 갱신이 겹칠 때 등)
		   바로 'database is locked' 로 실패했다 → 잠금이 풀리기를 최대 5초 기다림 */
		self::$db->busyTimeout(5000);
		self::$dbProperties['driver'] = 'native';

		return true;
	}

	/* PDO_SQLITE 연결. 네이티브와 같은 파일을 연다(경로에 NUL 이 있으면 거부).
	   오류는 예외 대신 반환값으로 받고(ERRMODE_SILENT), 잠금 대기 시간은 네이티브와 같이 5초(PDO 기본값은 60초). */
	public static function __bindPDO($database) {
		self::$db = null;
		if(!class_exists('PDO') || !in_array('sqlite', PDO::getAvailableDrivers(), true)) {
			error_log("SQLite3 connection error : PDO_SQLITE driver is not available");
			return false;
		}
		$path = __TEXTCUBE_DATA_DIR__.'/'.$database['database'].'.sqlite';
		if(strpos($path, "\0") !== false) {
			error_log("SQLite3 connection error : database path contains a NUL byte");
			return false;
		}
		try {
			$handle = new PDO('sqlite:'.$path, null, null, array(PDO::ATTR_ERRMODE => PDO::ERRMODE_SILENT, PDO::ATTR_TIMEOUT => 5, PDO::ATTR_STATEMENT_CLASS => array('DBAdapterPDOStatement')));
		} catch (Exception $e) {
			error_log("SQLite3 connection error : PDO connection failed (".$e->getCode().")");
			return false;
		}
		self::$db = $handle;
		self::$dbProperties['driver'] = 'pdo';
		self::$dbProperties['lastError'] = array(0, '');
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
		else return null;
	}

	public static function dbms() {
		return 'SQLite3';
	}

	public static function version($mode = "server") {
		if (array_key_exists('version', self::$dbProperties)) return self::$dbProperties['version'];
		else {
			$version = self::__isPDO() ? array('versionString' => self::$db->getAttribute(PDO::ATTR_SERVER_VERSION)) : self::$db->version();
			self::$dbProperties['version'] = $version['versionString'];
			return self::$dbProperties['version'];
		}
	}

	public static function tableList($condition = null) {
		if (!array_key_exists('tableList', self::$dbProperties)) { 
			$tableData = self::queryAll('SELECT name from sqlite_master WHERE type="table"');
			self::$dbProperties['tableList'] = array();
			foreach($tableData as $tbl) {
				array_push(self::$dbProperties['tableList'], $tbl[0]);
			}
		}
		$result = array();
		if(!is_null($condition)) {
			$result = array();
			foreach(self::$dbProperties['tableList'] as $item) {
				if(strpos($item, $condition) === 0) {
					array_push($result, $item);
				}
			}
			return $result;
		} else {
			return self::$dbProperties['tableList'];
		}
	}
	
	public static function setTimezone($time) {
		return true;
#		return self::query('SET time_zone = \'' . Timezone::getCanonical() . '\'');
	}
	
	public static function reservedFieldNames() {
		return null;
	}
	
	public static function reservedFunctionNames() {
		return array('UNIX_TIMESTAMP()');
	}
	
	public static function queryExistence($query) {
		/* 원본은 결과 행이 있는지를 SQLite3::changes()(마지막 INSERT/UPDATE/DELETE 가 바꾼 행 수)로 판단해 SELECT 결과와 무관한 값을
		   돌려줬다(직전 변경이 있으면 결과가 없어도 true, 변경이 없으면 결과가 있어도 false). 다른 어댑터(num_rows > 0)처럼 결과 행 유무로 판단한다.
		   결과 열이 없는 문장(INSERT 등)은 fetchArray() 가 문장을 다시 실행하므로 읽지 않고 false. */
		if ($result = self::query($query)) {
			$exists = (self::__columnCount($result) > 0 && self::__fetchRow($result, self::__queryType('num')) !== false);
			self::__closeResult($result);
			return $exists;
		}
		return false;
	}
	
	public static function queryCount($query) {
		$count = 0;
		$query = trim($query);
		if ($result = self::query($query)) {
			$operation = strtolower(substr($query, 0,6));
			self::$lastQueryType = $operation;
			switch ($operation) {
				case 'select':
					while ($buf = self::__fetchRow($result, self::__queryType('num'))) {
						$count++;
					}
					self::__closeResult($result);
					break;
				case 'insert':
				case 'update':
				case 'delete':
				case 'replac':
				default:
					$count = self::__changes();
					//mysqli_free_result();
					break;
			}
		}
		return $count;
	}

	public static function queryCell($query, $field = 0, $useCache=true) {
		/* 형식 이름으로 넘긴다(PDO 모드에는 sqlite3 확장 상수가 없을 수 있다). 원본의 정수 상수는 __queryType() 이 알아보지 못해
		   항상 BOTH 로 읽혔다(돌려주는 값은 같음). PostgreSQL·Cubrid 어댑터와 같은 방식. */
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
			
			while ($row = self::__fetchRow($result, self::__queryType('both')))
				array_push($column, $row[0]);
			self::__closeResult($result);
		}

		if( $useCache && !is_null($column) ) {	// 실패(null)는 캐시하지 않음
			self::$cachedResult[$cacheKey] = array( 1, $column );
		}
		return $column;
	}
	
	public static function queryAll ($query, $type = 'both', $count = -1) {
		return self::queryAllWithCache($query, $type, $count);
		//return self::queryAllWithoutCache($query, $type, $count);  // Your choice. :)
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
		/* 원본은 지역 변수 $cachedResult 를 검사해 캐시가 한 번도 쓰이지 않았다(다른 어댑터처럼 self::$cachedResult). */
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
	
	public static function execute($query) {
		// Textcube-NG: query() 와 같은 호환 변환을 적용한다. 원본은 변환 없이 exec 해 UNIX_TIMESTAMP() 가 든 문장(댓글 알림 큐·링크·팀블로그 설정)이 SQLite 에서 실패했다(v3.183)
		$query = self::__compatQuery($query);
		if (self::__isPDO()) {
			/* 빈 문장은 네이티브 exec('') 처럼 성공으로 둔다(PDO::exec('') 는 PHP 8 에서 ValueError, 7.4 에서 경고·실패) */
			$count = ((string)$query === '') ? 0 : self::$db->exec($query);
			if ($count === false) self::__pdoError(self::$db->errorInfo());
			else self::$dbProperties['lastError'] = array(0, '');
			$result = ($count !== false);
		} else {
			$result = self::$db->exec($query);
		}
		/* query() 를 거치지 않아 쓰기 문장도 질의 캐시를 비우지 않았다(queryAllWithCache() 가 캐시를 쓰지 않아 드러나지 않음).
		   query() 와 같은 조건으로 비운다. */
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

	public static function query($query, $compatibility = true) {
//		var_dump($query);
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

	/* Textcube-NG: query() 의 호환 변환을 함수로 분리(동작 같음). execute() 도 같은 변환을 적용한다(v3.183). CONCAT( 는 SQLite 버전과 관계없이 || 로 바꾼다 — SQLite 3.44 미만에는 CONCAT 함수가 없어 Filter 질의(이름·홈페이지·본문 차단, 화이트리스트)가 실패했다(아래 블록은 같은 트리 PostgreSQL __compatQuery 의 CONCAT 블록에서 탐색 구간만 따옴표 밖으로 묶은 것, r4-2) */
	public static function __compatQuery($query) {
		// Textcube-NG: 따옴표·주석 밖의 UNIX_TIMESTAMP() 만 바꾼다(사용자 값 안의 같은 문자열이 저장 전에 epoch 로 바뀌던 문제, S-3)(v3.183)
		$query = self::__replaceOutsideQuotes($query, 'UNIX_TIMESTAMP()', (string)Timestamp::getUNIXtime()); // compatibility issue.			
		$query = self::__replaceOutsideQuotes($query, 'RAND()', 'RANDOM()'); // compatibility issue.			
		$origPagingInst = array(
			'/(?:\'[^\']*+(?:\'\'[^\']*+)*+\'|\"[^\"]*+(?:\"\"[^\"]*+)*+\")(*SKIP)(*FAIL)|CHAR_LENGTH\(/si',	// Textcube-NG: 함수 이름만 바꾼다. 원본 'CHAR_LENGTH(.*) ' 는 탐욕 패턴이라 마지막 공백까지 한 번에 잡아 두 번째 CHAR_LENGTH 는 그대로 남았다(r3-15)
			'/(?:\'[^\']*+(?:\'\'[^\']*+)*+\'|\"[^\"]*+(?:\"\"[^\"]*+)*+\")(*SKIP)(*FAIL)|IF\(([A-Za-z0-9]+),([A-Za-z0-9]+),([A-Za-z0-9]+)\)/si'
		);
		$descPagingInst = array(
			'LENGTH(',
			'CASE WHEN $1 THEN $2 ELSE $3 END'
		);
		$query = preg_replace($origPagingInst, $descPagingInst,$query);	
		if ($query === null) return '';	// Textcube-NG: preg_replace() 가 PCRE 오류로 null 을 돌려주면 빈 문장(Cubrid __compatQuery 와 같음, r3-3)
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
				// Textcube-NG: PostgreSQL 블록과 같음, 탐색 구간만 따옴표 밖으로 묶음 — 따옴표 수의 제곱 시간 방지(r4-2)
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
	
	/* 드라이버별 실행·결과 처리. 네이티브는 원본과 같은 SQLite3 호출.
	   PDO 는 PDO::query()(첫 문장만, 결과는 DBAdapterPDOStatement). 실패는 false, 오류는 lastError(오류 번호·메시지)에 남긴다.
	   빈 문장은 네이티브처럼 오류 번호 0 으로 실패(PDO::query('') 는 PHP 8 에서 ValueError). */
	public static function __execute($query) {
		if (!self::__isPDO()) return self::$db->query($query);
		if ((string)$query === '') return self::__pdoFail(0, '');
		$result = self::$db->query($query);
		if ($result === false) return self::__pdoError(self::$db->errorInfo());
		self::$dbProperties['lastError'] = array(0, '');
		return $result;
	}

	/* PDO::errorInfo() → lastError. 오류 번호는 SQLite 결과 코드(없으면 1). SQLSTATE 가 00000 이면 오류가 아니다(공백·주석뿐인 문장은
	   네이티브처럼 오류 번호 0. PHP 7.4 의 errorInfo() 는 이때도 이전 오류 번호·메시지를 남겨 두므로 읽지 않는다) */
	public static function __pdoError($info) {
		if (!isset($info[0]) || $info[0] === '00000') return self::__pdoFail(0, '');
		$code = isset($info[1]) ? (int)$info[1] : 0;
		return self::__pdoFail($code ? $code : 1, isset($info[2]) ? $info[2] : 'Query failed');
	}

	public static function __pdoFail($code, $message) {
		self::$dbProperties['lastError'] = array($code, (string)$message);
		return false;
	}

	public static function __isPDO() {
		return (isset(self::$dbProperties['driver']) && self::$dbProperties['driver'] === 'pdo');
	}

	public static function __columnCount($result) {
		if ($result instanceof PDOStatement) return $result->columnCount();
		return $result->numColumns();
	}

	/* 결과 행 수 — 끝까지 세고 처음으로 되돌린다(이후 읽기는 첫 행부터). PDOStatement 는 다시 실행해서 되돌린다(SQLite3Result::reset() 과 같음) */
	public static function __numRows($result) {
		if ($result instanceof PDOStatement) {
			if ($result->columnCount() == 0) return 0;
			if ($result instanceof DBAdapterPDOStatement && $result->fetched) $result->execute();
			$count = 0;
			while ($result->fetch(PDO::FETCH_NUM) !== false) $count++;
			$result->execute();
			if ($result instanceof DBAdapterPDOStatement) $result->fetched = false;
			return $count;
		}
		if (!($result instanceof SQLite3Result) || $result->numColumns() == 0) return 0;
		$result->reset();
		$count = 0;
		while ($result->fetchArray(SQLITE3_NUM) !== false) $count++;
		$result->reset();
		// Textcube-NG: spl_object_id()(7.2) 대신 spl_object_hash()(r4-4)
		unset(self::$fetchDone[spl_object_hash($result)]);
		return $count;
	}

	/* 마지막 INSERT·UPDATE·DELETE 가 바꾼 행 수. PDO 는 SQLite3::changes() 와 같은 값을 주는 SQL 함수 changes() 로 읽는다 */
	public static function __changes() {
		if (!self::__isPDO()) return self::$db->changes();
		$result = self::$db->query('SELECT changes()');
		if ($result === false) return 0;
		$count = $result->fetchColumn();
		$result->closeCursor();
		return (int)$count;
	}

	public static function __closeResult($result) {
		if ($result instanceof PDOStatement) return $result->closeCursor();
		return $result->finalize();
	}

	/* 한 행 읽기. PDO 는 FETCH_NUM 으로 읽어 네이티브와 같은 키·순서로 다시 짠다(PDO 의 FETCH_BOTH 는 이름 키를 먼저 넣고 번호를 이어 붙여
	   숫자 이름 열이 있으면 번호가 밀림). 네이티브는 TEXT 값을 NUL 앞까지만 돌려주므로(BLOB 은 전체) NUL 이 든 TEXT 값은 같은 길이로 자른다 */
	public static function __fetchRow($result, $realtype) {
		if (!($result instanceof DBAdapterPDOStatement)) return $result->fetchArray($realtype);
		$result->fetched = true;
		$row = $result->fetch(PDO::FETCH_NUM);
		if ($row === false) return false;
		foreach ($row as $i => $value) {
			if (is_string($value) && ($nul = strpos($value, "\0")) !== false && !$result->isBlob($i)) $row[$i] = substr($value, 0, $nul);
		}
		if ($realtype == PDO::FETCH_NUM) return $row;
		$names = $result->columnNames();
		$keyed = array();
		foreach ($row as $i => $value) {
			if ($realtype == PDO::FETCH_BOTH) $keyed[$i] = $value;
			$keyed[$names[$i]] = $value;
		}
		return $keyed;
	}

	public static function __lastErrorCode() {
		if (self::__isPDO()) return isset(self::$dbProperties['lastError']) ? self::$dbProperties['lastError'][0] : 0;
		return self::$db ? self::$db->lastErrorCode() : 0;
	}

	public static function __lastErrorMessage() {
		if (self::__isPDO()) return isset(self::$dbProperties['lastError']) ? self::$dbProperties['lastError'][1] : '';
		return self::$db ? self::$db->lastErrorMsg() : '';
	}

	/* PDO 는 PDO::quote() 결과에서 감싼 따옴표를 뗀다(네이티브 escapeString() 과 같은 SQLite 규칙 — 작은따옴표를 두 번 씀).
	   NUL 뒤는 버린다(네이티브 escapeString() 과 같은 결과) */
	public static function escapeString($string, $link = null){
		if (self::__isPDO()) {
			$string = (string)$string;
			$nul = strpos($string, "\0");
			if ($nul !== false) $string = substr($string, 0, $nul);
			$quoted = self::$db->quote($string);
			return ($quoted === false) ? '' : substr($quoted, 1, -1);
		}
		return self::$db->escapeString($string);
	}

/*** Instant cache functions ***/
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
		global $fileCachedResult;
	}
	/* 원본의 rollback()·commit() 은 SQLite3 클래스에 없는 메서드를 불러 치명적 오류(Error)가 났다(세션 저장 뒤의 @POD::commit() 포함, @ 로 막히지 않음).
	   SQL 문장으로 처리한다. SQLite3 는 기본이 자동 커밋이라 열린 트랜잭션이 없으면 COMMIT·ROLLBACK 이 실패하며 경고를 내므로
	   그 문장 동안만 오류 처리기를 바꿔 경고를 감춘다(전역 Debug 오류 처리기는 @ 를 무시하고, enableExceptions(false) 는 PHP 8.4 부터 deprecated). */
	public static function rollback() {
		if (!self::$db) return false;
		self::clearCache();	// 되돌린 변경을 읽은 캐시 결과를 버린다
		if (self::__isPDO()) return (self::$db->exec('ROLLBACK') !== false);	// PDO(ERRMODE_SILENT)는 경고를 내지 않는다
		set_error_handler(function () { return true; });
		$result = self::$db->exec('ROLLBACK');
		restore_error_handler();
		return $result;
	}
	public static function commit() {
		if (self::$db) {
			set_error_handler(function () { return true; });
			self::$db->exec('COMMIT'); // Auto commit.
			restore_error_handler();
		}
		return true;
	}
		
/*** Raw functions (to easier adoptation from traditional queries) ***/
	public static function insertId() {
		if (self::__isPDO()) return (int)self::$db->lastInsertId();	// PDO 는 문자열로 돌려준다
		return self::$db->lastInsertRowID();
	}
	
	/* 원본의 sqlite_num_rows()·sqlite_free_result()·sqlite_fetch_array()·sqlite_fetch_row()·sqlite_fetch_assoc()·sqlite_error() 는
	   SQLite 2 확장 함수(PHP 5.4 에서 확장 제거, SQLite3 결과에는 쓸 수 없음)이거나 그 확장에도 없던 이름이라 정의되지 않은 함수 호출로 치명적 오류가 났다.
	   query() 가 돌려주는 SQLite3Result 로 같은 동작을 한다. SQLite3Result 는 행 수를 알려주지 않고, 끝까지 읽은 뒤 다시 읽으면 문장을 처음부터
	   다시 실행하며(결과 열이 없는 INSERT 등은 그 문장이 다시 실행됨), reset() 으로 처음으로 돌아간다. */
	public static function num_rows($handle = null) {
		switch(self::$lastQueryType) {
			case 'select':
				/* 행을 끝까지 세고 결과를 처음으로 되돌린다(이후 fetch() 는 첫 행부터). */
				return self::__numRows($handle);
				break;
			default:
				return self::__changes();
				break;
		}
		return null;
	}
	
	public static function free($handle = null) {
		if ($handle instanceof PDOStatement) {
			$handle->closeCursor();
			return;
		}
		if (!($handle instanceof SQLite3Result)) return;
		unset(self::$fetchDone[spl_object_hash($handle)]);
		$handle->finalize();
	}
	
	public static function fetch($handle = null, $type = 'assoc') {
		/* 끝까지 읽은 결과는 다시 읽지 않고 false(원본 함수·MySQLi 처럼 끝에서 멈춤). 결과 열이 없는 결과는 읽지 않는다(문장 재실행 방지). */
		if ($handle instanceof DBAdapterPDOStatement) {	// PDO 는 끝까지 읽은 결과를 다시 실행하지 않는다
			if ($handle->columnCount() == 0) return false;
			return self::__fetchRow($handle, self::__queryType($type == 'array' ? 'both' : ($type == 'row' ? 'num' : 'assoc')));
		}
		if (!($handle instanceof SQLite3Result) || $handle->numColumns() == 0 || isset(self::$fetchDone[spl_object_hash($handle)])) return false;
		if($type == 'array') $row = $handle->fetchArray(SQLITE3_BOTH);
		else if ($type == 'row') $row = $handle->fetchArray(SQLITE3_NUM);
		else $row = $handle->fetchArray(SQLITE3_ASSOC);
		if ($row === false) self::$fetchDone[spl_object_hash($handle)] = $handle;
		return $row;
	}
	
	public static function error($err = null) {
		/* 연결(인자가 없으면 현재 연결)의 마지막 오류 메시지, 오류가 없으면 빈 문자열.
		   SQLite 결과 코드 100(SQLITE_ROW)·101(SQLITE_DONE, 결과를 끝까지 읽은 뒤)은 오류가 아니다. */
		if (self::__isPDO()) return (self::__lastErrorCode() == 0) ? '' : self::__lastErrorMessage();
		$db = ($err instanceof SQLite3) ? $err : self::$db;
		if (!$db) return '';
		$code = $db->lastErrorCode();
		if ($code == 0 || $code == 100 || $code == 101) return '';
		return $db->lastErrorMsg();
	}
	
	public static function stat($stat = null) {
		/* SQLite3 클래스에는 stat() 메서드가 없다(원본은 치명적 오류). 서버 상태가 없는 내장 DB 이므로 라이브러리 버전을 돌려준다. */
		if (!self::$db) return false;
		return 'SQLite ' . self::version();
	}
	
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
				return SQLITE3_NUM;
			case 'assoc':
				return SQLITE3_ASSOC;				
			case 'both':
			default:
				return SQLITE3_BOTH;
		}
	}
	
	public static function fieldType($abstractType) {
		/* 원본은 정적 속성이 아닌 지역 변수 $typeTable 을 읽어 항상 null 을 돌려줬다. */
		if(isset(self::$typeTable[$abstractType])) return self::$typeTable[$abstractType];
	}

    static $typeTable = array(
        "integer" => "int",
        "int" => "int",
        "float"	=> "float",
        "double"	=> "float",
        "timestamp"	=> "int",
        "mediumtext" => "mediumtext",
        "varchar" => "varchar",
        "vartext" => "varchar",	// MySQL·MySQLi 형식표의 'vartext' 와 같은 뜻(별칭)
        "text"	=> "text");

/*** Prepared Statement API — Textcube-NG: php8.2 이식본과 같은 구현을 옮겼다(v3.211) ***/
	/* MySQLi 어댑터와 같은 호출 방식(prepare → bindAndExecute → fetchAllStmt → close).
	   문장 틀에는 query() 와 같은 호환 변환(UNIX_TIMESTAMP() 등)을 적용하고, 값은 SQLite3Stmt::bindValue 로 따로 전달한다(값은 SQL 문장으로 해석되지 않음).
	   (v1.85 의 이 어댑터는 RuntimeException 을 던져 로그인·댓글·글·BlogAPI 첨부 저장이 동작하지 않았다) */
	public static function prepare($query) {
		if (!self::$db) return false;
		$query = self::__compatQuery($query);
		if (self::__isPDO()) {
			/* PDOStatement 는 자리표시자 개수를 알려주지 않아 직접 센다. ? 만 허용 — ?NNN·:이름·@이름·$이름 은 SQLite 가 번호를 따로 매겨
			   위치 바인딩과 어긋날 수 있어 거부 */
			$paramCount = self::__countPlaceholders($query);
			if ($paramCount === false) return self::__pdoFail(1, 'Statement rejected: only ? placeholders are supported');
			if ((string)$query === '') return self::__pdoFail(0, '');
			$handle = self::$db->prepare($query);
			if ($handle === false) return self::__pdoError(self::$db->errorInfo());
			self::$dbProperties['lastError'] = array(0, '');
			return new DBAdapterStatement($query, $handle, $paramCount);
		}
		$handle = @self::$db->prepare($query);
		if (!$handle) return false;
		/* Textcube-NG: 공백·주석·';' 만 있는 문장은 SQLite 가 문장 없이 성공을 돌려줘 PHP 8 의 paramCount() 가 Error 를 던진다(7.4 는 0). prepare('') 처럼 false(v3.184) */
		try {
			$paramCount = $handle->paramCount();
		} catch (Error $e) {
			return false;
		}
		return new DBAdapterStatement($query, $handle, $paramCount);
	}

	public static function bindAndExecute($stmt, $types) {
		$params = array_slice(func_get_args(), 2);	// Textcube-NG: PHP 5.5 에는 가변 인자(...)가 없다(r4-4)
		if (!($stmt instanceof DBAdapterStatement) || !$stmt->handle) return false;
		$stmt->reset();
		$values = $stmt->normalize($types, $params);
		if ($values === false) return false;
		if ($stmt->handle instanceof PDOStatement) return self::__bindAndExecutePDO($stmt, $values);
		$stmt->handle->reset();
		$stmt->handle->clear();
		foreach ($values as $index => $value) {
			if (is_null($value[0])) $type = SQLITE3_NULL;
			else if ($value[1] == 'i') $type = SQLITE3_INTEGER;
			else if ($value[1] == 'd') $type = SQLITE3_FLOAT;
			else if ($value[1] == 'b') $type = SQLITE3_BLOB;
			else $type = SQLITE3_TEXT;
			if (!$stmt->handle->bindValue($index + 1, $value[0], $type))
				return $stmt->fail('Unable to bind parameter ' . ($index + 1));
		}
		$result = @$stmt->handle->execute();
		self::$lastQueryType = strtolower(substr($stmt->query, 0,6));
		if ($result === false) {
			$code = self::$db->lastErrorCode();
			return $stmt->fail(self::$db->lastErrorMsg(), $code ? $code : 1);
		}
		if ($result->numColumns() > 0) {
			$stmt->result = $result;
			$stmt->affected_rows = 0;
		} else {
			$stmt->affected_rows = self::$db->changes();
			$result->finalize();
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

	/* PDO 모드 바인딩·실행. PDO_SQLITE 에는 실수 바인딩 형식이 없어 'd' 는 PHP 실수 표기(var_export — 로캘·precision 설정과 무관) 문자열로 넘긴다
	   (숫자 형식 열에는 숫자로 저장·비교됨). 쓰기 문장의 영향받은 행 수는 rowCount()(네이티브 changes() 와 같은 값) */
	public static function __bindAndExecutePDO($stmt, $values) {
		foreach ($values as $index => $value) {
			if (is_null($value[0])) $ok = $stmt->handle->bindValue($index + 1, null, PDO::PARAM_NULL);
			else if ($value[1] == 'i') $ok = $stmt->handle->bindValue($index + 1, $value[0], PDO::PARAM_INT);
			else if ($value[1] == 'd') $ok = $stmt->handle->bindValue($index + 1, var_export($value[0], true), PDO::PARAM_STR);
			else if ($value[1] == 'b') $ok = $stmt->handle->bindValue($index + 1, $value[0], PDO::PARAM_LOB);
			else $ok = $stmt->handle->bindValue($index + 1, $value[0], PDO::PARAM_STR);
			if (!$ok) return $stmt->fail('Unable to bind parameter ' . ($index + 1));
		}
		$ok = $stmt->handle->execute();
		self::$lastQueryType = strtolower(substr($stmt->query, 0,6));
		if (!$ok) {
			self::__pdoError($stmt->handle->errorInfo());
			/* 실패한 문장을 되돌린다(closeCursor() 가 sqlite3_reset 을 부름). 첫 실행이 제약 위반 등으로 실패한 문장은 PDO 가 되돌리지 않아
			   다음 실행이 오류 21(API misuse)로 실패한다(네이티브는 다음 bindAndExecute() 의 reset() 으로 되돌림) */
			$stmt->handle->closeCursor();
			$code = self::$dbProperties['lastError'][0];
			return $stmt->fail(self::$dbProperties['lastError'][1], $code ? $code : 1);
		}
		self::$dbProperties['lastError'] = array(0, '');
		if ($stmt->handle->columnCount() > 0) {
			$stmt->result = $stmt->handle;
			$stmt->affected_rows = 0;
		} else {
			$stmt->affected_rows = $stmt->handle->rowCount();
			$stmt->handle->closeCursor();
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

	/* PDO 모드 prepare() 의 자리표시자 개수. SQLite 토큰 규칙으로 문자열·식별자 따옴표(' " ` [ ])와 주석(-- 줄 주석, 블록 주석)을 건너뛰고
	   첫 문장(; 앞)의 ? 를 센다. ?NNN·:이름·@이름·$이름·#이름, NUL 이 든 문장은 false(거부) */
	public static function __countPlaceholders($query) {
		static $word = null;
		if ($word === null) {
			$word = 'ABCDEFGHIJKLMNOPQRSTUVWXYZabcdefghijklmnopqrstuvwxyz0123456789_$';
			for ($c = 128; $c < 256; $c++) $word .= chr($c);
		}
		$query = (string)$query;
		if (strpos($query, "\0") !== false) return false;
		$count = 0;
		$length = strlen($query);
		for ($i = 0; $i < $length; $i++) {
			$c = $query[$i];
			if ($c === '\'' || $c === '"' || $c === '`') {
				for ($i++; $i < $length; $i++) {
					if ($query[$i] === $c) {
						if ($i + 1 < $length && $query[$i + 1] === $c) $i++;
						else break;
					}
				}
			} else if ($c === '[') {
				$end = strpos($query, ']', $i + 1);
				$i = ($end === false) ? $length : $end;
			} else if ($c === '-' && $i + 1 < $length && $query[$i + 1] === '-') {
				$end = strpos($query, "\n", $i + 2);
				$i = ($end === false) ? $length : $end;
			} else if ($c === '/' && $i + 2 < $length && $query[$i + 1] === '*') {
				$end = strpos($query, '*/', $i + 2);
				$i = ($end === false) ? $length : $end + 1;
			} else if ($c === '?') {
				if ($i + 1 < $length && strpos('0123456789', $query[$i + 1]) !== false) return false;
				$count++;
			} else if ($c === ':' || $c === '@' || $c === '$' || $c === '#') {
				return false;
			} else if ($c === ';') {
				break;
			} else if (strpos($word, $c) !== false) {
				$i += strspn($query, $word, $i) - 1;
			}
		}
		return $count;
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
		else if ($this->result) $this->result->finalize();
		$this->result = null;
		return true;
	}

	public function close() {
		$this->freeResult();
		if ($this->handle && !($this->handle instanceof PDOStatement)) $this->handle->close();	// PDOStatement 는 객체를 놓으면 닫힌다
		$this->handle = null;
		return true;
	}
}

/* PDO 모드 결과 문장 객체 — PDO::ATTR_STATEMENT_CLASS 로 PDO 가 만든다. 행을 네이티브 키 순서로 다시 짜기 위한 열 이름을 한 번 읽어 두고(columnNames),
   현재 행 값이 BLOB 인지 알려 주며(isBlob), 읽은 행이 있는지(fetched) 기록한다(num_rows() 가 처음부터 다시 세기 위해).
   PDO 가 없는 설치에서도 이 파일을 읽을 수 있게 조건부로 선언 */
if (class_exists('PDOStatement', false)) {
class DBAdapterPDOStatement extends PDOStatement {
	public $names = null;
	public $fetched = false;

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

	/* 현재 행의 열 값이 BLOB 인지(getColumnMeta() 의 flags 에 'blob') */
	public function isBlob($column) {
		$meta = $this->getColumnMeta($column);
		return (is_array($meta) && isset($meta['flags']) && is_array($meta['flags']) && in_array('blob', $meta['flags'], true));
	}
}
}
?>
