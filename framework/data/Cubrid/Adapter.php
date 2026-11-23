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

// DBQuery version 1.8 for Cubrid
/* 드라이버 선택 (STAGE4 v3.117): config.php 의 $database['driver'] 로 고른다.
   'native'(기본값·생략 시) — cubrid 확장(cubrid_* 함수), 기존 동작.  'pdo' — PDO_CUBRID(PDO::getAvailableDrivers() 에 'cubrid').
   PDO 모드는 결과 행을 돌려주는 문장(SELECT 등)만 PDO::query(), 나머지는 SQL 을 해석하지 않는 PDO::exec() 로 실행한다.
   PDO 의 SQL 파서가 문자열 경계를 서버와 다르게 읽어 :이름 을 자리표시자로 바꿀 문장은 실행하지 않는다(__pdoParserRewrites). */

global $fileCachedResult;

class DBAdapter implements IAdapter {	
	static $dbProperties, $cachedResult,$lastQueryType;
	/*@static@*/
	public static function bind($database) {
		// Connects DB and set environment variables
		// $database array should contain 'server','username','password'.
		if(!isset($database) || empty($database)) return false;
		// 드라이버 선택: 'native'(기본값) 또는 'pdo'. 그 밖의 값이면 연결하지 않는다
		$driver = isset($database['driver']) ? $database['driver'] : '';
		if($driver === '') $driver = 'native';
		if(!is_string($driver) || !in_array(strtolower($driver), array('native','pdo'), true)) {
			error_log("Cubrid connection error : unknown driver setting (database['driver'] must be 'native' or 'pdo')");
			return false;
		}
		if(strtolower($driver) == 'pdo') return self::__bindPDO($database);
		if(!function_exists('cubrid_connect')) {
			error_log("Cubrid connection error : cubrid extension is not available");
			return false;
		}
		if(!empty($database['ssl'])) {
			// ── SSL/TLS 연결 (config 에 ssl 가 설정된 경우에만) ──
			// CUBRID PHP 드라이버(CCI)는 연결 URL 의 useSSL 속성으로 암호화만 지원(브로커 cubrid_broker.conf 의 SSL=ON 필요)하며
			// 서버 인증서 검증·CA·클라이언트 인증서를 지정할 수단이 없음 → ssl_ca·ssl_cert·ssl_key 는 쓰지 않고,
			// 검증을 요구한 설정(ssl_verify)은 충족할 수 없으므로 검증 없이 연결하지 않고 실패 처리.
			if(!empty($database['ssl_verify'])) {
				error_log("Cubrid SSL connection error : server certificate verification (ssl_verify) is not supported by the CUBRID PHP driver");
				return false;
			}
			if(!function_exists('cubrid_connect_with_url')) {
				error_log("Cubrid SSL connection error : cubrid_connect_with_url() is not available");
				return false;
			}
			// URL 구분자(: ? &)가 든 서버·DB 이름은 다른 연결 속성으로 해석될 수 있으므로 거부. 사용자·비밀번호는 URL 이 아닌 인자로 전달
			if(strpbrk((string)$database['server'].(string)$database['database'], ':?&') !== false) {
				error_log("Cubrid SSL connection error : server or database name contains a URL delimiter");
				return false;
			}
			$handle = @cubrid_connect_with_url('CUBRID:'.$database['server'].':'.intval($database['port']).':'.$database['database'].':::?useSSL=true', $database['username'], $database['password']);
		} else {
			$handle = @cubrid_connect($database['server'], intval($database['port']), $database['database'], $database['username'], $database['password']);
		}
		if(!$handle) return false;
		self::$dbProperties['driver'] = 'native';
		self::$dbProperties['handle'] = $handle;	// Keeping handle
		self::$dbProperties['charset'] = 'utf8';
		return true;
	}
	
	/* PDO_CUBRID 연결 (STAGE4 v3.117). DSN 은 'cubrid:host=…;port=…;dbname=…' — DSN 구분자(; =)·연결 URL 구분자(: ? &)가 든 서버·DB 이름은
	   다른 속성으로 해석될 수 있어 거부. 계정·비밀번호는 DSN 이 아닌 인자로 넘긴다.
	   SSL 은 네이티브와 같이 암호화만(useSSL, 브로커 SSL=ON 필요) 지원하므로 인증서 검증 요구(ssl_verify)는 실패 처리. */
	public static function __bindPDO($database) {
		if(!class_exists('PDO') || !in_array('cubrid', PDO::getAvailableDrivers(), true)) {
			error_log("Cubrid connection error : PDO_CUBRID driver is not available");
			return false;
		}
		if(strpbrk((string)$database['server'].(string)$database['database'], ";=:?&\0") !== false) {
			error_log("Cubrid connection error : server or database name contains a DSN or URL delimiter");
			return false;
		}
		$options = array();
		if(!empty($database['ssl'])) {
			if(!empty($database['ssl_verify'])) {
				error_log("Cubrid SSL connection error : server certificate verification (ssl_verify) is not supported by the CUBRID PHP driver");
				return false;
			}
			$options['useSSL'] = 'true';	// pdo_cubrid 는 문자열 키 옵션을 연결 URL 속성으로 붙인다
		}
		try {
			$handle = new PDO('cubrid:host='.$database['server'].';port='.intval($database['port']).';dbname='.$database['database'], $database['username'], $database['password'], $options);
		} catch (Exception $e) {
			error_log("Cubrid connection error : PDO connection failed (".$e->getCode().")");
			return false;
		}
		$handle->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_SILENT);	// 실패는 false·errorInfo() 로 받는다(네이티브와 같이 예외 없음)
		if(!$handle->setAttribute(PDO::ATTR_STATEMENT_CLASS, array('DBAdapterPDOStatement'))) {	// 결과 행을 네이티브 키 순서로 다시 짜는 문장 객체(__fetchRow)
			error_log("Cubrid connection error : PDO statement class could not be set");
			return false;
		}
		self::$dbProperties['driver'] = 'pdo';
		self::$dbProperties['handle'] = $handle;	// Keeping handle
		self::$dbProperties['charset'] = 'utf8';
		self::$dbProperties['lastError'] = array(0, '');
		return true;
	}

	public static function unbind() {
		if(self::__isPDO()) {	// 트랜잭션 중이면 커밋(commit() 참고)하고 객체를 놓아 연결을 닫는다
			self::commit();
			self::$dbProperties['handle'] = null;
			return true;
		}
		@cubrid_commit(self::$dbProperties['handle']);
		cubrid_disconnect(self::$dbProperties['handle']);
		return true;
	}

	public static function charset() {
		if (array_key_exists('charset', self::$dbProperties)) return self::$dbProperties['charset'];
		else return null;
	}
	public static function dbms() {
		return 'Cubrid';
	}

	public static function version($mode = 'server') {
		if (array_key_exists('version', self::$dbProperties)) return self::$dbProperties['version'];
		else {
			self::$dbProperties['version'] = self::__isPDO() ? self::$dbProperties['handle']->getAttribute(PDO::ATTR_CLIENT_VERSION) : cubrid_version();
			return self::$dbProperties['version'];
		}
	}

	public static function tableList($condition = null) {
		if (!array_key_exists('tableList', self::$dbProperties)) { 
			self::$dbProperties['tableList'] = self::queryColumn("SELECT class_name FROM db_class WHERE is_system_class = 'NO'");
		}
		if(!is_null($condition)) {
			$result = array();
			foreach(self::$dbProperties['tableList'] as $item) {
				if(strpos($item, $condition) === 0) {array_push($result, $item);}
			}
			return $result;
		} else {
			return self::$dbProperties['tableList'];
		}
	}

	public static function reservedFieldNames() {
		return array('date','value','data','count','year','month', 'type', 'size');
	}
	
	public static function reservedFunctionNames() {
		return array('UNIX_TIMESTAMP()');
	}
	
	public static function setTimezone($time) {
		return true;
		return self::query('SET time_zone = \'' . Timezone::getCanonical() . '\'');
	}

	/*@static@*/
	public static function query($query, $compatibility = true) {
		/// Bypassing compatiblitiy issue : will be replace to NAF2.
		$query = $query ?? '';
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
		if( in_array(self::$lastQueryType, array('insert','update','delete','replac'))) {
			self::commit();
			self::clearCache();
		} else if( self::__isSchemaChange($query) ) {
			unset(self::$dbProperties['tableList']);
			self::clearCache();
		}
		return $result;
	}

	/* 드라이버별 실행·결과 처리 (STAGE4 v3.117). 네이티브는 원본과 같은 cubrid_* 호출.
	   PDO 는 결과 행을 돌려주는 문장만 PDO::query()(PDOStatement), 나머지는 PDO::exec()(DBAdapterWriteResult). 실패는 false, 오류는 lastError 에 남긴다. */
	public static function __execute($query) {
		if(!self::__isPDO()) return cubrid_execute(self::$dbProperties['handle'],$query);
		$pdo = isset(self::$dbProperties['handle']) ? self::$dbProperties['handle'] : null;
		if(!($pdo instanceof PDO)) return self::__pdoFail(1, 'No CUBRID PDO connection');
		if((string)$query === '') return self::__pdoFail(-424, 'No statement to execute.');	// Textcube-NG: 빈 문장은 PDO 에 넘기지 않고 네이티브 cubrid_execute 와 같은 -424 로 실패(PHP 8 의 PDO::exec('')·query('') 는 ValueError, 7.4 는 경고)(v3.184)
		if(self::__pdoReturnsRows($query)) {
			if(self::__pdoParserRewrites($query)) return self::__pdoFail(1, 'Statement rejected: PDO SQL parser would treat :name as a placeholder');
			$result = $pdo->query($query);
		} else {
			$count = $pdo->exec($query);
			$result = ($count === false) ? false : new DBAdapterWriteResult($count);
		}
		if($result === false) return self::__pdoError($pdo->errorInfo());
		self::$dbProperties['lastError'] = array(0, '');
		return $result;
	}

	public static function __pdoError($info) {
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

	public static function __numRows($result) {
		if($result instanceof PDOStatement) return $result->rowCount();
		if($result instanceof DBAdapterWriteResult) return 0;
		return cubrid_num_rows($result);
	}

	public static function __affectedRows($result) {
		if($result instanceof DBAdapterWriteResult) return $result->affected_rows;
		if($result instanceof PDOStatement) return $result->rowCount();
		return cubrid_affected_rows($result);
	}

	public static function __closeResult($result) {
		if($result instanceof PDOStatement) return $result->closeCursor();
		if($result instanceof DBAdapterWriteResult) return true;
		return cubrid_close_request($result);
	}

	/* 한 행 읽기. PDO 는 FETCH_NUM 으로 읽어 네이티브와 같은 키·순서로 다시 짠다 — BOTH 는 열마다 번호 키 다음 이름 키(PDO 의 FETCH_BOTH 는
	   이름 키를 먼저 넣어 이름이 숫자인 열이 있으면 값이 다름). 네이티브는 문자열 값을 NUL 앞까지만 돌려주므로(PDO 는 전체) 같은 길이로 자른다.
	   BLOB·CLOB 은 PDO 가 스트림으로 돌려주므로 그대로 둔다. 끝이면 네이티브처럼 false */
	public static function __fetchRow($result, $realtype) {
		if($result instanceof DBAdapterWriteResult) return false;
		if(!($result instanceof PDOStatement)) return cubrid_fetch($result, $realtype);
		$row = $result->fetch(PDO::FETCH_NUM);
		if($row === false) return false;
		foreach($row as $i => $value) {
			if(is_string($value) && ($nul = strpos($value, "\0")) !== false) $row[$i] = substr($value, 0, $nul);
		}
		if($realtype == PDO::FETCH_NUM) return $row;
		$names = $result->columnNames();
		$keyed = array();
		foreach($row as $i => $value) {
			if($realtype == PDO::FETCH_BOTH) $keyed[$i] = $value;
			$keyed[$names[$i]] = $value;
		}
		return $keyed;
	}

	public static function __lastErrorCode() {
		if(self::__isPDO()) return isset(self::$dbProperties['lastError']) ? self::$dbProperties['lastError'][0] : 0;
		return cubrid_error_code();
	}

	public static function __lastErrorMessage() {
		if(self::__isPDO()) return isset(self::$dbProperties['lastError']) ? self::$dbProperties['lastError'][1] : '';
		return cubrid_error_msg();
	}

	/* 결과 행을 돌려주는 문장인지 — 맨 앞 공백·여는 괄호·주석(-- · // 줄 주석, 블록 주석)을 건너뛴 첫 낱말로 판단 */
	public static function __pdoReturnsRows($query) {
		return (preg_match('/^(?:\s|\(|--[^\n]*(?:\n|$)|\/\/[^\n]*(?:\n|$)|\/\*.*?\*\/)*(SELECT|WITH|SHOW|DESC|DESCRIBE|EXPLAIN|VALUES)\b/is', (string)$query) === 1);
	}

	/* PDO SQL 파서(PHP 코어. pdo_cubrid 는 드라이버 전용 파서가 없어 기본 파서)가 :이름 을 이름 자리표시자로 볼 문장인지.
	   그러면 PDO 가 문장을 고쳐 써(:이름 → ?) 실행하므로 서버에 가는 문장이 달라진다. 기본 파서 규칙을 그대로 따른다:
	   PHP 8.4 미만 — 따옴표 안 백슬래시 escape(CUBRID 기본 no_backslash_escapes=yes 와 다름), -- 주석은 \r·\n 앞까지.
	   PHP 8.4 이상 — 같은 따옴표 두 개가 escape, -- 주석은 \n 앞까지.
	   공통 — 블록 주석, 두 개 이상 이어진 : 은 문자, 바로 앞 바이트가 영숫자인 :이름 은 자리표시자 아님, 닫히지 않은 따옴표·블록 주석은 한 글자.
	   NUL 이 든 문장은 파서가 거기서 멈추므로 거부한다. */
	public static function __pdoParserRewrites($query) {
		$query = (string)$query;
		if(strpos($query, "\0") !== false) return true;
		$old = (PHP_VERSION_ID < 80400);
		$word = 'ABCDEFGHIJKLMNOPQRSTUVWXYZabcdefghijklmnopqrstuvwxyz0123456789';
		$length = strlen($query);
		for($i = 0; $i < $length; $i++) {
			$c = $query[$i];
			if($c === '\'' || $c === '"') {
				$end = -1;
				for($j = $i + 1; $j < $length; $j++) {
					if($old) {
						if($query[$j] === '\\') { $j++; continue; }
						if($query[$j] === $c) { $end = $j; break; }
					} else if($query[$j] === $c) {
						$end = $j;
						if($j + 1 < $length && $query[$j + 1] === $c) { $j++; continue; }
						break;
					}
				}
				if($end >= 0) $i = $end;
			} else if($c === '-' && $i + 1 < $length && $query[$i + 1] === '-') {
				$i += strcspn($query, $old ? "\r\n" : "\n", $i);
			} else if($c === '/' && $i + 1 < $length && $query[$i + 1] === '*') {
				$end = strpos($query, '*/', $i + 2);
				if($end !== false) $i = $end + 1;
			} else if($c === ':') {
				$run = strspn($query, ':', $i);
				if($run > 1) { $i += $run - 1; continue; }
				$name = strspn($query, $word.'_', $i + 1);
				if($name > 0) {
					if($i == 0 || strpos($word, $query[$i - 1]) === false) return true;
					$i += $name;
				}
			}
		}
		return false;
	}

	/* query() 의 호환 변환을 함수로 분리. prepare() 는 문장 틀에 같은 변환을 적용한다.
	   [보안] CONCAT 변환의 따옴표 탐색이 닫는 따옴표를 찾지 못하면(백슬래시로 끝나는 값 등) 원본은 처음부터 다시 훑으며 끝나지 않았다 → 문장 끝으로 처리. */
	public static function __compatQuery($query) {
		// Textcube-NG: 따옴표·주석 밖의 UNIX_TIMESTAMP() 만 바꾼다(사용자 값 안의 같은 문자열이 저장 전에 epoch 로 바뀌던 문제, S-3)(v3.183)
		$query = self::__replaceOutsideQuotes($query, 'UNIX_TIMESTAMP()', (string)Timestamp::getUNIXtime()); // compatibility issue.
		$literals = array();	// Textcube-NG: 문자열 리터럴 안의 LIMIT·ORDER BY 등은 페이징 변환하지 않는다
		$query = preg_replace_callback("/'[^']*+(?:''[^']*+)*+'/", function ($m) use (&$literals) { $literals[] = $m[0]; return "'" . (count($literals) - 1) . "'"; }, $query);
		if(stripos($query, "ORDER BY")!==false) {
			$origPagingInst = array(
				'/(ASC|DESC) LIMIT ([0-9]+) OFFSET 0/si',
				'/(ASC|DESC) LIMIT ([0-9]+) OFFSET ([0-9]+)/si',
				'/(ASC|DESC) LIMIT 1(^[0-9])/si',
				'/(ASC|DESC) LIMIT ([0-9]+)/si',
				'/RAND\(\) LIMIT ([0-9]+)/si'
			);
			$descPagingInst = array(
				'$1 FOR ORDERBY_NUM() BETWEEN 1 AND $2',
				'$1 FOR ORDERBY_NUM() BETWEEN ($3+1) AND ($2+$3)',
				'$1 FOR ORDERBY_NUM() = 1',
				'$1 FOR ORDERBY_NUM() BETWEEN 1 AND $2',
				'RANDOM() FOR ORDERBY_NUM() BETWEEN 1 AND $1'
			);
		} else if(stripos($query, "GROUP BY")!==false) {
			$origPagingInst = array(
				'/GROUP BY(.*)(ORDER BY)(.*)(ASC|DESC) LIMIT ([0-9]+) OFFSET 0/si',
				'/GROUP BY(.*)(ORDER BY)(.*)(ASC|DESC) LIMIT ([0-9]+) OFFSET ([0-9]+)/si',
				'/GROUP BY(.*)(ORDER BY)(.*)(ASC|DESC) LIMIT 1(^[0-9])/si',
				'/GROUP BY(.*)(ORDER BY)(.*)(ASC|DESC) LIMIT ([0-9]+)/si',
				'/GROUP BY(.*)(ORDER BY)(.*)RAND\(\) LIMIT ([0-9]+)/si'
			);
			$descPagingInst = array(
				'GROUP BY $1 HAVING GROUPBY_NUM() = $5 $2 $3 $4',
				'GROUP BY $1 HAVING GROUPBY_NUM() BETWEEN ($6+1) AND $5 $2 $3 $4',
				'GROUP BY $1 HAVING GROUPBY_NUM() = 1 $2 $3 $4',
				'GROUP BY $1 HAVING GROUPBY_NUM() BETWEEN 1 AND $5 $2 $3 $4',
				'GROUP BY $1 HAVING GROUPBY_NUM() BETWEEN 1 AND $4 $2 RANDOM() $3'
			);
		} else {
			// Textcube-NG: 첫 WHERE 까지를 원자 그룹으로 잡아 첫 WHERE 에서만 맞춰 본다. 원본은 WHERE 마다 (.*) 역추적을 되풀이해 WHERE 가 많은 문장에서
			// 시간이 제곱으로 늘었다. 결과(첫 WHERE 앞은 그대로, 그 뒤에 ROWNUM 조건)는 같고, 앞부분을 ${1} 로 되돌려 써 번호가 하나씩 밀린다(r3-3)
			$origPagingInst = array(
				'/^(?>(.*?)WHERE)(.*)LIMIT ([0-9]+) OFFSET 0/si',
				'/^(?>(.*?)WHERE)(.*)LIMIT ([0-9]+) OFFSET ([0-9]+)/si',
				'/^(?>(.*?)WHERE)(.*)LIMIT 1(^[0-9])/si',
				'/^(?>(.*?)WHERE)(.*)LIMIT ([0-9]+)/si',
				'/SUM\((size|value)\)/si'
				);
			$descPagingInst = array(
				'${1}WHERE ROWNUM BETWEEN 1 AND $3 AND ($2)',	// Textcube-NG: 원래 WHERE 조건을 괄호로 묶는다 — 최상위 OR 가 있으면 ROWNUM 조건이 앞 항에만 걸려 LIMIT 가 풀렸다(이 분기는 ORDER BY·GROUP BY 가 없는 문장이라 $2 는 조건뿐, r3-15)
				'${1}WHERE ROWNUM BETWEEN ($4+1) AND ($3+$4) AND ($2)',
				'${1}WHERE ROWNUM = 1 AND ($2)',
				'${1}WHERE ROWNUM BETWEEN 1 AND $3 AND ($2)',
				'SUM("$1")'
				);
		}
		$query = preg_replace($origPagingInst, $descPagingInst,$query);
		if ($query === null) return '';	// Textcube-NG: preg_replace() 가 PCRE 오류(backtrack 한도 초과 등)로 null 을 돌려주면 빈 문장을 돌려준다 — CONCAT 처리와 호출한 쪽(query()·prepare()·__tcSqlLogBegin)의 문자열 함수에 null 을 넘기지 않는다(PHP 8.1 null 인자 deprecated). 빈 문장은 원본(5.6 의 null → '' 변환)과 같이 실행 단계에서 실패(-424)(v3.221)

		$query = preg_replace_callback("/'([0-9]+)'/", function ($m) use ($literals) { return $literals[$m[1]]; }, $query);

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

	/* Textcube-NG: 따옴표·주석 밖에서만 $search 를 $replace 로 바꾼다(v3.183). Cubrid 는 // 줄 주석도 건너뛴다. 사용자 값이 든 '…' 리터럴(escapeString 이 ' 를 '' 로 겹침),
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
			} elseif (($c == '-' || $c == '/') && $i + 1 < $length && $query[$i + 1] == $c) {
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
		$query = trim($query ?? '');	// Textcube-NG: null 을 받으면 빈 문장으로 본다(PHP 8.1 부터 trim() 에 null 을 넘기면 deprecated — 원래 동작 trim(null) === '' 와 같음)(v3.221)
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
			while ($row = self::__fetchRow($result, self::__queryType('both')))
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
		if(self::__isPDO()) return self::$dbProperties['handle']->lastInsertId();
		return cubrid_insert_id();
	}
	
	public static function escapeString($string, $link = null){
		return preg_replace("/'/","''",$string);
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
		if(self::__isPDO()) {
			// PDO_CUBRID 는 자동 커밋(ATTR_AUTOCOMMIT 기본값 true). 트랜잭션 중일 때만 커밋한다(트랜잭션 밖 PDO::commit() 은 ERRMODE 와 무관하게 예외)
			if(isset(self::$dbProperties['handle']) && self::$dbProperties['handle'] instanceof PDO && self::$dbProperties['handle']->inTransaction()) self::$dbProperties['handle']->commit();
			return;
		}
		@cubrid_commit(self::$dbProperties['handle']);
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
		if (!($handle instanceof PDOStatement) && !($handle instanceof DBAdapterWriteResult) && !is_resource($handle)) return;	// Textcube-NG: __closeResult() 가 다루는 결과(PDOStatement·DBAdapterWriteResult·cubrid_execute 의 resource)가 아닌 값(DBModel::getAll() 의 배열·실패한 질의의 false·null)은 닫을 것이 없다. 원본(PHP 5.6 의 cubrid_close_request)은 경고 뒤 계속했지만 PHP 8.0 부터는 TypeError 로 멈춘다(v3.214)
		self::__closeResult($handle);
	}
	
	/*@static@*/
	public static function fetch($handle = null, $type = 'assoc') {
		$realtype = self::__queryType($type);
		return self::__fetchRow($handle,$realtype);
	}
	
	/*@static@*/
	public static function error($err = null) {
		if(self::__isPDO()) return self::__lastErrorMessage();
		if($err === null) return cubrid_error();
		else return cubrid_error($err);
	}
	
	/*@static@*/
	public static function stat($stat = null) {
		/* CUBRID PHP 확장에는 cubrid_stat() 이 없다(원본은 정의되지 않은 함수 호출로 치명적 오류).
		   연결(인자가 없으면 현재 연결)의 서버 정보 문자열로 대신한다(cubrid_get_server_info 는 연결 핸들이 필수). */
		if ($stat === null) $stat = isset(self::$dbProperties['handle']) ? self::$dbProperties['handle'] : null;
		if (!$stat) return false;
		if ($stat instanceof PDO) return $stat->getAttribute(PDO::ATTR_SERVER_VERSION);
		return cubrid_get_server_info($stat);
	}
	
	/*@static@*/
	public static function __queryType($type) {
		if(self::__isPDO()) {
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
				return CUBRID_NUM;
			case 'assoc':
				return CUBRID_ASSOC;				
			case 'both':
			default:
				return CUBRID_BOTH;
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
        "text" => "varchar");	// Textcube-NG: Cubrid 에는 text 형이 없다(CREATE 가 -494 '<스키마>.text is not defined'). 코어 스키마(initialize.Cubrid.sql)가 MySQL text 열에 쓰는 길이 없는 varchar 로 맞춘다(v3.210)

/*** Prepared Statement API (v1.85, STAGE 2 / Cubrid 구현 STAGE4 v3.88, 확장 바인딩 STAGE4 v3.117) ***/
	/* MySQLi 어댑터와 같은 호출 방식. 문장 틀에만 query() 와 같은 호환 변환을 적용하고, 값은 드라이버 바인딩으로 넘긴다
	   (네이티브: cubrid_prepare·cubrid_bind·cubrid_execute, PDO: PDO::prepare·bindValue·execute) — 값은 SQL 문장에 들어가지 않는다.
	   v3.88 은 따옴표 밖 ? 를 형식별 리터럴로 바꿔 query() 로 실행했다(주석 안 ? 도 자리표시자로 셈, 실행 오류 때 경고 출력).
	   형식: i → BIGINT(PDO 는 int32 범위 밖이면 문자열), d → var_export 문자열(소수점 표기 NUMERIC·지수 표기 DOUBLE — v3.88 리터럴과 같은 형식. PDO 는 문자열),
	   s·b → 문자열(NUL 이 든 값은 거부), null → NULL.
	   LOB 형식은 쓰지 않는다(확장은 LOB 에 준 문자열 값을 파일 경로로 연다). */
	public static function prepare($query) {
		if (!isset(self::$dbProperties['handle'])) return false;
		$query = self::__compatQuery($query);
		$paramCount = count(self::__splitPlaceholders($query)) - 1;
		if (self::__isPDO()) {
			$pdo = self::$dbProperties['handle'];
			if (!($pdo instanceof PDO)) return self::__pdoFail(1, 'No CUBRID PDO connection');
			if ((string)$query === '') return self::__pdoFail(-424, 'No statement to execute.');	// Textcube-NG: 빈 문장(__compatQuery 가 PCRE 오류로 '' 를 돌려준 경우 포함 — v3.221 전에는 null)은 네이티브 cubrid_prepare 와 같은 -424 로 실패(PHP 8 의 PDO::prepare('') 는 ValueError)(v3.184)
			if (self::__pdoParserRewrites($query)) return self::__pdoFail(1, 'Statement rejected: PDO SQL parser would treat :name as a placeholder');
			$request = $pdo->prepare($query);
			if ($request === false) return self::__pdoError($pdo->errorInfo());
			self::$dbProperties['lastError'] = array(0, '');
		} else {
			$request = @cubrid_prepare(self::$dbProperties['handle'], $query);
			if (!$request) return false;
		}
		return new DBAdapterStatement($query, $request, $paramCount);
	}

	public static function bindAndExecute($stmt, $types, ...$params) {
		if (!($stmt instanceof DBAdapterStatement)) return false;
		if (!$stmt->handle) return $stmt->fail('Statement is closed');
		$stmt->reset();
		$values = $stmt->normalize($types, $params);
		if ($values === false) return false;
		$request = $stmt->handle;
		$pdo = ($request instanceof PDOStatement);
		foreach ($values as $index => $value) {
			$position = $index + 1;
			if (is_null($value[0])) {
				$ok = $pdo ? $request->bindValue($position, null, PDO::PARAM_NULL) : @cubrid_bind($request, $position, null);
			} else if ($value[1] == 'i') {
				if ($pdo) $ok = ($value[0] >= -2147483648 && $value[0] <= 2147483647) ? $request->bindValue($position, $value[0], PDO::PARAM_INT) : $request->bindValue($position, (string)$value[0], PDO::PARAM_STR);
				else $ok = @cubrid_bind($request, $position, (string)$value[0], 'BIGINT');
			} else if ($value[1] == 'd') {
				$number = var_export($value[0], true);	// DOUBLE 로 보내면 결과가 소수 16자리 형식으로 바뀌므로(0.1+0.2 → 0.3000000000000000) 리터럴과 같이 고른다
				$ok = $pdo ? $request->bindValue($position, $number, PDO::PARAM_STR) : @cubrid_bind($request, $position, $number, (stripos($number, 'E') === false) ? 'NUMERIC' : 'DOUBLE');
			} else if (strpos($value[0], "\0") !== false) {
				return $stmt->fail('String parameter contains NUL byte');
			} else {
				$ok = $pdo ? $request->bindValue($position, $value[0], PDO::PARAM_STR) : @cubrid_bind($request, $position, $value[0], 'STRING');
			}
			if (!$ok) return self::__stmtFail($stmt, $pdo);
		}
		$log = function_exists('__tcSqlLogBegin');
		if ($log) __tcSqlLogBegin($stmt->query);
		$ok = $pdo ? $request->execute() : @cubrid_execute($request);
		if ($pdo) {
			if ($ok) self::$dbProperties['lastError'] = array(0, '');
			else self::__pdoError($request->errorInfo());
		}
		if ($log) __tcSqlLogEnd($ok ? $request : false, 0);
		if (!$ok) return self::__stmtFail($stmt, $pdo);
		self::$lastQueryType = strtolower(substr($stmt->query, 0, 6));
		if (in_array(self::$lastQueryType, array('insert','update','delete','replac'))) {
			$stmt->affected_rows = $pdo ? $request->rowCount() : cubrid_affected_rows($request);
			self::commit();
			self::clearCache();
		} else {
			$stmt->result = $request;
			$stmt->affected_rows = 0;
			if (self::__isSchemaChange($stmt->query)) {
				unset(self::$dbProperties['tableList']);
				self::clearCache();
			}
		}
		return true;
	}

	/* 바인딩·실행 실패를 문장 객체의 errno·error 로 옮긴다(PDO 는 lastError 에도 남긴다) */
	public static function __stmtFail($stmt, $pdo) {
		if ($pdo) {
			self::__pdoError($stmt->handle->errorInfo());
			return $stmt->fail(self::$dbProperties['lastError'][1], self::$dbProperties['lastError'][0]);
		}
		$code = (int)cubrid_error_code();
		return $stmt->fail(cubrid_error_msg(), $code ? $code : 1);
	}

	public static function fetchAllStmt($stmt) {
		if (!($stmt instanceof DBAdapterStatement) || !$stmt->result) return null;
		$all = array();
		$pdo = ($stmt->result instanceof PDOStatement);
		while ($row = self::__fetchRow($stmt->result, $pdo ? PDO::FETCH_ASSOC : CUBRID_ASSOC)) {	// PDO 도 네이티브와 같은 키·NUL 처리
			$all[] = $row;
		}
		$stmt->freeResult();
		$stmt->affected_rows = count($all);
		return $all;
	}

	/* 문장 틀을 자리표시자 ? 로 나눈다(개수 확인용). 서버와 같이 문자열 리터럴('…', 같은 따옴표 두 개는 따옴표 문자),
	   따옴표 식별자("…", `…`, […]), 주석(-- · // 는 줄 끝까지, 블록 주석)은 건너뛴다. 닫히지 않은 것은 문장 끝까지.
	   서버는 식별자 안 ? 도 자리표시자로 세므로 그런 문장 틀은 바인딩되지 않은 자리로 실행이 실패한다(Textcube 호출부에는 없음). */
	public static function __splitPlaceholders($query) {
		$parts = array();
		$start = 0;
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
			} else if (($c === '-' || $c === '/') && $i + 1 < $length && $query[$i + 1] === $c) {
				$i += strcspn($query, "\r\n", $i);
			} else if ($c === '/' && $i + 1 < $length && $query[$i + 1] === '*') {
				$end = strpos($query, '*/', $i + 2);
				$i = ($end === false) ? $length : $end + 1;
			} else if ($c === '?') {
				$parts[] = substr($query, $start, $i - $start);
				$start = $i + 1;
			}
		}
		$parts[] = substr($query, $start);
		return $parts;
	}
}

/*** Prepared Statement 문장 객체 (STAGE4 v3.88) ***/
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
		else if ($this->result) @cubrid_free_result($this->result);	// 결과 버퍼만 비운다(요청은 다시 실행할 수 있게 남김)
		$this->result = null;
		return true;
	}

	public function close() {
		$this->freeResult();
		if ($this->handle && !($this->handle instanceof PDOStatement)) @cubrid_close_request($this->handle);	// 네이티브 요청 해제(PDOStatement 는 객체를 놓으면 해제)
		$this->handle = null;
		return true;
	}
}

/* PDO 모드 쓰기 문장(PDO::exec) 결과 (STAGE4 v3.117) — 영향받은 행 수만 담는다 */
class DBAdapterWriteResult {
	public $affected_rows;
	public function __construct($count) {
		$this->affected_rows = (int)$count;
	}
}

/* PDO 모드 결과 문장 객체 (STAGE4 v3.119) — PDO::ATTR_STATEMENT_CLASS 로 PDO 가 만든다. 행을 네이티브 키 순서로 다시 짜기 위한 열 이름을 한 번 읽어 둔다(columnNames).
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
