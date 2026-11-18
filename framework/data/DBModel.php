<?php
/// Copyright (c) 2004-2016, Needlworks  / Tatter Network Foundation
/// All rights reserved. Licensed under the GPL.
/// See the GNU General Public License for more details. (/documents/LICENSE, /documents/COPYRIGHT)

// DB-related function. will be merged into DBMS unification function.
function escapeSearchString($str) {
	return is_string($str) ? str_replace('_', '\_', str_replace('%', '\%', POD::escapeString($str, null))) : $str;
}

// Textcube-NG: $refresh 이면 테이블 목록을 다시 읽는다(같은 요청의 CREATE·DROP 뒤). 대소문자를 무시하면 목록도 소문자로 비교한다 — 원본은 첫 호출의 목록만 써서 CREATE 뒤에도 '없음'(MySQLi 1050 되풀이), 이름만 소문자로 바꿔 SQLite3 에서 늘 false 였다(v3.210)
function doesExistTable($tablename, $refresh = false) {
	global $database;
	static $tables = array();
	if( empty($tables) || $refresh ) {
		$tables = POD::tableList($database['prefix']);
	}

	$dbCaseInsensitive = Setting::getServiceSetting('lowercaseTableNames',null,'global');
	if($dbCaseInsensitive === null) {
		if(in_array(POD::dbms(),array('MySQL','MySQLi'))) {
			$result = POD::queryRow("SHOW VARIABLES LIKE 'lower_case_table_names'");
			$dbCaseInsensitive = ($result['Value'] == 1) ? 1 : 0;
		} else $dbCaseInsensitive = 1;
		Setting::setServiceSetting('lowercaseTableNames',$dbCaseInsensitive,true);
	}
	if($dbCaseInsensitive == 1) $tablename = strtolower($tablename);
	if( in_array( $tablename, ($dbCaseInsensitive == 1) ? array_map('strtolower', $tables) : $tables ) ) {
		return true;
	}
	return false;
}

/* DBModel */
/* 1.7.2.20141105 */
class DBModel extends Singleton implements IModel {
	protected $_attributes, $_qualifiers, $_query;
	protected $_relations, $_glues, $_filters, $_order, $_limit, $table, $id, $_querysetCount;
	protected $_reservedFields, $_reservedFunctions, $_isReserved, $param;
	protected $context;
	protected $_setFields = array();	// Textcube-NG: 조건 묶음(setQualifierSet)의 항별 열 이름(순서 번호 → 열 이름, r3-9, 488)

	function __construct($table = null) {
		$this->context = Model_Context::getInstance();
		$this->reset($table);
	}

	public static function getInstance() {
		return self::_getInstance(__CLASS__);
	}

	public function reset($table = null, $param = null) {
		if(!is_null($table)) $this->table = $this->context->getProperty('database.prefix').$table;
		else $this->table = null;
		$this->id = null;
		$this->_attributes = array();
		$this->_qualifiers = array();
		$this->_relations = array();
		$this->_glues = array();
		$this->_setFields = array();
		$this->_filters = array();
		$this->_order = array();
		$this->_limit = array();
		$this->_isReserved = array();
		$this->param = array();
		$this->_querysetCount = 0;
		$this->_reservedFields    = POD::reservedFieldNames();
		$this->_reservedFunctions = POD::reservedFunctionNames();
		if(!empty($this->_reservedFields)) {
			foreach($this->_reservedFields as $reserved) {
				$this->_isReserved[$reserved] = true;
			}
		}
		if(!empty($param)) $this->param = $param;
	}

	public function resetAttributes() {
		$this->_attributes = array();
		return $this;
	}

	public function getAttributesCount() {
		return count($this->_attributes);
	}

	public function hasAttribute($name) {
		return isset($this->_attributes[$name]);
	}

	public function getAttribute($name) {
		return $this->_attributes[$name];
	}

	public function setAttribute($name, $value, $escape = false) {
		if (is_null($value))
			$this->_attributes[$name] = null;
//			$this->_attributes[$name] = 'NULL';
		else
			$this->_attributes[$name] = ($escape === false && (!is_string($value) || in_array($value,$this->_reservedFunctions)) ? $value : ($escape ? '\'' . POD::escapeString($value) . '\'' : "'" . $value . "'"));
	}

	public function unsetAttribute($name) {
		unset($this->_attributes[$name]);
		return $this;
	}

	public function resetQualifiers() {
		$this->_qualifiers = array();
		$this->_relations = array();
		return $this;
	}

	public function getQualifiersCount() {
		return count($this->_qualifiers);
	}

	public function hasQualifier($name) {
		return isset($this->_qualifiers[$name]);
	}

	public function getQualifier($name) {
		return $this->_qualifiers[$name];
	}

	public function setQualifier($name, $condition, $value = null, $escape = false, $autoquote = true) {
		$result = $this->getQualifierModel($name, $condition, $value, $escape, $autoquote);
		if($result) {
			if (!isset($this->_qualifiers[$name])) {
				$this->_qualifiers[$name] = array();
				$this->_relations[$name] = array();
			}
			$index = count($this->_qualifiers[$name]);
			$this->_qualifiers[$name][$index] = $result[0];
			$this->_relations[$name][$index] = $result[1];
		}
		return $this;
	}

	public function unsetQualifier($name) {
		unset($this->_qualifiers[$name]);
		unset($this->_relations[$name]);
		return $this;
	}

	/* Textcube-NG: 조건 묶음의 항을 열 이름이 아니라 순서 번호로 저장한다(열 이름은 _setFields, r3-9, 488). 원본은 열 이름을 키로 저장해
	   같은 열을 두 번 쓰면 앞 항이 덮어써지고 결합어가 마지막 항 뒤에 붙어 '(value = … OR )' 같은 문법 오류가 났다
	   (framework/model/URIHandler.php 는 hasoneof 로 우회). 결합어는 앞 항의 번호에 둔다.
	   결합어는 SQL 에 그대로 들어가므로 AND·OR(대소문자 무관)만 받고, 그 밖의 값이면 인자 개수가 맞지 않을 때와 같이
	   아무 조건도 더하지 않고 false 를 돌려준다. */
	public function setQualifierSet() {
		$nargs = func_num_args();
		if ($nargs % 2 != 1) return false;
		$args = func_get_args();
		$mqualifier = array();
		$mrelation = array();
		$mglue = array();
		$mfield = array();
		$index = -1;
		for($i = 0; $i < $nargs; $i += 1) {
			if($i % 2 == 0) {
				$name = $args[$i][0];
				$condition = $args[$i][1];
				$value = $args[$i][2];
				if(isset($args[$i][3])) $escape = $args[$i][3];
				else $escape = null;
				list($qualifier, $relation) = $this->getQualifierModel($name, $condition, $value, $escape);
				$index += 1;
				$mfield[$index] = $name;
				$mqualifier[$index] = $qualifier;
				$mrelation[$index] = $relation;
			} else {
				$glue = is_string($args[$i]) ? strtoupper($args[$i]) : '';
				if ($glue !== 'AND' && $glue !== 'OR') return false;
				$mglue[$index] = $glue;
			}
		}
		//$this->_qualifiers['QualifierSet'.$this->_querysetCount] = array();
		//$this->_relations['QualifierSet'.$this->_querysetCount] = array();
		//$this->_glues['QualifierSet'.$this->_querysetCount] = array();
		$this->_qualifiers['QualifierSet'.$this->_querysetCount] = $mqualifier;
		$this->_relations['QualifierSet'.$this->_querysetCount] = $mrelation;
		$this->_glues['QualifierSet'.$this->_querysetCount] = $mglue;
		$this->_setFields['QualifierSet'.$this->_querysetCount] = $mfield;
		$this->_querysetCount += 1;
		return $this;
	}

	public function setOrder($standard, $order = 'ASC') {
		$this->_order['attribute'] = $standard;
		if(!in_array(strtoupper($order), array('ASC','DESC'))) $order = 'ASC';
		$this->_order['order'] = $order;
		return $this;
	}

	public function unsetOrder() {
		$this->_order = array();
		return $this;
	}

	public function setLimit($count, $offset = 0) {
		$this->_limit['count'] = $count;
		$this->_limit['offset'] = $offset;
		return $this;
	}

	public function unsetLimit() {
		$this->_limit = array();
		return $this;
	}

	public function doesExist($field = '*') {
		return POD::queryExistence('SELECT '. $field . ' FROM ' . $this->table . $this->_makeWhereClause() . ' LIMIT 1');
	}

	public function getCell($field = '*') {
		$field = $this->_treatReservedFields($field);
		return POD::queryCell('SELECT ' . $field . ' FROM ' . $this->table . $this->_makeWhereClause() . ' LIMIT 1');
	}

	public function getRow($field = '*') {
		$field = $this->_treatReservedFields($field);
		return POD::queryRow('SELECT ' . $field . ' FROM ' . $this->table . $this->_makeWhereClause());
	}

	public function getColumn($field = '*') {
		$field = $this->_treatReservedFields($field);
		return POD::queryColumn('SELECT ' . $field . ' FROM ' . $this->table . $this->_makeWhereClause());
	}

	public function getAll($field = '*') {
		$field = $this->_treatReservedFields($field);
		return POD::queryAll('SELECT ' . $field . ' FROM ' . $this->table . $this->_makeWhereClause());
	}

	public function getCount($field = '*') { /// Returns the 'selection count'
		$field = $this->_treatReservedFields($field);
		return POD::queryCell('SELECT COUNT(' . $field . ') FROM ' . $this->table . $this->_makeWhereClause());
//		return POD::queryCount('SELECT ' . $field . ' FROM ' . $this->table . $this->_makeWhereClause() . ' LIMIT 1');
	}

	public function insert($option = null) {
		$this->id = null;
		if (empty($this->table))
			return false;
		// Use first qualifiers when multiple conditions exist.
		$qualifiers = array();
		if(!empty($this->_qualifiers)) {
			foreach($this->_qualifiers as $key => $index) {
				$qualifiers[$key] = reset($index);
			}
		}
		$attributes = array_merge($qualifiers, $this->_attributes);
		if (empty($attributes))
			return false;
		$pairs = $attributes;
		foreach($pairs as $key => $value) if (is_null($value)) $pairs[$key] = 'NULL';

		$this->_query = 'INSERT INTO ' . $this->table . ' (' . implode(',', $this->_capsulateFields(array_keys($attributes))) . ') VALUES (' . implode(',', $pairs) . ')';
		if($option == 'count') return POD::queryCount($this->_query);
		if (POD::query($this->_query)) {
//			$this->id = POD::insertId();
			return true;
		}
		return false;
	}

	public function update($option = null) {
		if (empty($this->table) || empty($this->_attributes))
			return false;
		$attributes = array();

		foreach ($this->_attributes as $name => $value)
			array_push($attributes,
				(array_key_exists($name, $this->_isReserved) ? '"'.$name.'"' : $name) . '=' .
				(is_null($value) ? ' NULL' : $value ));

		$this->_query = 'UPDATE ' . $this->table . ' SET ' . implode(',', $attributes) . $this->_makeWhereClause();
		if($option == 'count') return POD::queryCount($this->_query);
		if (POD::query($this->_query))
			return true;
		return false;
	}

	public function replace($option = null) {
		$this->id = null;
		if (empty($this->table))
			return false;
		// Use first qualifiers when multiple conditions exist.
		$qualifiers = array();
		if(!empty($this->_qualifiers)) {
			foreach($this->_qualifiers as $key=>$index) {
				$qualifiers[$key] = reset($index);
			}
		}
		$attributes = array_merge($qualifiers, $this->_attributes);
		if (empty($attributes))
			return false;
		$pairs = $attributes;
		foreach($pairs as $key => $value) if (is_null($value)) $pairs[$key] = 'NULL';
		$attributeFields = $this->_capsulateFields(array_keys($attributes));
		if (in_array(POD::dbms(), array('MySQL','MySQLi','SQLite3'))) { // Those supports 'REPLACE'
			$this->_query = 'REPLACE INTO ' . $this->table . ' (' . implode(',', $attributeFields) . ') VALUES(' . implode(',', $pairs) . ')';
			if($option == 'count') return POD::queryCount($this->_query);
			if (POD::query($this->_query)) {
				$this->id = POD::insertId();
				return true;
			}
			return false;
		} else {
			$this->_query = 'SELECT * FROM ' . $this->table . $this->_makeWhereClause() . ' LIMIT 1';
			if(POD::queryExistence($this->_query)) {
				return $this->update($option);
			} else {
				return $this->insert($option);
			}
		}
	}

	public function delete($count = null, $option = null) {
		if (empty($this->table))
			return false;
		if(!is_null($count)) $this->setLimit($count);
		$this->_query = 'DELETE FROM ' . $this->table . $this->_makeWhereClause();
		if($option == 'count') return POD::queryCount($this->_query);
		if (POD::query($this->_query))
			return true;
		return false;
	}

	/// To use create() method, $this->structure variable must be defined.
	/* 원본은 미완성(TO DO): 'CREATE' 뒤에 TABLE 이 없고 필드 사이에 쉼표가 없으며, 형식은 빈 문자열로 fieldType() 을 불러
	   (항상 null) 비어 있었고, length 는 읽지 않았고, 기본값은 큰따옴표(MySQL 이외에서는 식별자)로 감쌌다 — 어떤 DBMS 에서도 실패.
	   structure 의 type(어댑터 fieldType() 형식표)·length(varchar/vartext)·isNull·default 로 CREATE TABLE 을 만든다.
	   필드 이름은 식별자 형식만 받고(예약어는 다른 질의처럼 큰따옴표), 숫자형 기본값은 숫자만, 그 밖의 기본값은 escapeString() 후 작은따옴표.
	   MySQL·MySQLi 는 설치 화면처럼 DEFAULT CHARSET=utf8. 알 수 없는 형식이면 false. */
	public function create() {
		if(!isset($this->structure) || empty($this->structure) || !is_array($this->structure)) return false;
		if(empty($this->table)) return false;
		$fields = array();
		foreach($this->structure as $field => $attributes) {
			if(!is_string($field) || !preg_match('/^[A-Za-z_][A-Za-z0-9_]*$/', $field) || !is_array($attributes)) return false;
			$abstractType = isset($attributes['type']) ? $attributes['type'] : '';
			$type = POD::fieldType($abstractType);	// Type casting
			if(is_null($type)) return false;
			if(isset($attributes['length']) && in_array($abstractType, array('varchar', 'vartext'))) {
				$type .= '('.intval($attributes['length']).')';
			}
			$definition = (array_key_exists($field, $this->_isReserved) ? '"'.$field.'"' : $field).' '.$type;
			if(isset($attributes['default'])) {
				if(in_array($abstractType, array('integer', 'int', 'timestamp', 'float', 'double'))) {
					if(!is_numeric($attributes['default'])) return false;
					$definition .= ' DEFAULT '.($attributes['default'] + 0);
				} else {
					$definition .= ' DEFAULT \''.POD::escapeString((string)$attributes['default']).'\'';
				}
			}
			$definition .= (empty($attributes['isNull']) ? ' NOT NULL' : ' NULL');
			$fields[] = $definition;
		}
		$sql = 'CREATE TABLE '.$this->table.' ('.CRLF.implode(','.CRLF, $fields).CRLF.')';
		if(in_array(POD::dbms(), array('MySQL', 'MySQLi')) && (POD::charset() == 'utf8')) $sql .= ' DEFAULT CHARSET=utf8';
		return POD::execute($sql);
	}

	public function discard() {
		/* 원본은 'DROP '.테이블(TABLE 없음)로 어떤 DBMS 에서도 문법 오류였다. */
		$this->_query = 'DROP TABLE '. $this->table;
		if(POD::query($this->_query))
			return true;
		return false;
	}

	protected function _makeWhereClause() {
		$clause = '';
		if (count($this->_qualifiers) == 0) {
			$clause = '1=1';	// Textcube-NG: 조건 없음은 참인 식 — '1' 은 PostgreSQL(boolean 아님)·Cubrid(-493)에서 오류(v3.210)
		} else {
			foreach ($this->_qualifiers as $name => $value) {
				if (strpos($name,'QualifierSet') === 0) {
					// Textcube-NG: 항을 순서 번호로 읽고(열 이름은 _setFields) 항마다 괄호로 감싼다 — ((a = 1) OR (b > 0))(r3-9, 488)
					$clause .= (strlen($clause) ? ' AND (' : '(');
					foreach ($value as $qindex => $qvalue) {
						$qname = $this->_setFields[$name][$qindex];
						list($qrelations, $qvalue) = $this->_canonicalWhereClause($this->_relations[$name][$qindex],$qvalue);
						$clause .= '(' . (array_key_exists($qname, $this->_isReserved) ? '"'.$qname.'"' : $qname) .
						' '.(is_null($qvalue) ? ' IS NULL' : $qrelations . ' ' . $qvalue) . ')'
						.(isset($this->_glues[$name][$qindex]) ? ' '.$this->_glues[$name][$qindex].' ' : '');
					}

					$clause .= ')';
				} else {
					foreach ($value as $index => $qualifier) {
						list($relations, $qvalue) = $this->_canonicalWhereClause($this->_relations[$name][$index],$qualifier);
						$clause .= (strlen($clause) ? ' AND ' : '') .
							(array_key_exists($name, $this->_isReserved) ? '"'.$name.'"' : $name) .
							' '.(is_null($qvalue) ? ' IS NULL' : $relations . ' ' . $qvalue);
					}
				}
			}
		}
		if(!empty($this->_order)) $clause .= ' ORDER BY '.$this->_treatReservedFields($this->_order['attribute']).' '.$this->_order['order'];
		if(!empty($this->_limit)) $clause .= ' LIMIT '.$this->_limit['count'].' OFFSET '.$this->_limit['offset'];
		return (strlen($clause) ? ' WHERE ' . $clause : '');
	}

	protected function _canonicalWhereClause($relations, $value) {
		if(in_array($relations,array('hasoneof','hasanyof','hasnoneof'))) {
			switch($relations) {
				case 'hasoneof':
				case 'hasanyof':	// Textcube-NG: hasanyof(어느 하나라도 맞음)는 IN 이다. 원본은 NOT IN 쪽에 있었다(코어 호출자 없음, r3-15)
					$relations = ' IN';
					break;
				case 'hasnoneof':
				default:
					$relations = ' NOT IN';
					break;
			}
			if(is_array($value)) {
				$value = implode(',',$value);
			}
			$value = '('.$value.')';
		}
		return array($relations, $value);
	}

	protected function _treatReservedFields($fields) {
		if(empty($this->_reservedFields)) return $fields;
		else {
			$requestedFields = explode(',',str_replace(' ','',$fields));
			return implode(',',$this->_capsulateFields($requestedFields));
		}
	}

	protected function _capsulateFields($requestedFieldArray) {
		$escapedFields = array();
		foreach ($requestedFieldArray as $req) {
			if(array_key_exists($req,$this->_isReserved)) {
				array_push($escapedFields, '"'.$req.'"');
			} else {
				array_push($escapedFields,$req);
			}
		}
		return $escapedFields;
	}

	protected function getQualifierModel($name, $condition, $value = null, $escape = false, $autoquote = true) {
	//OR, setQualifier(string(name_condition_value), $escape = null)     - Descriptive mode (NOT implemented)
		if (is_null($condition)) {
			$qualifiers = $relations = null;
		} else {
			switch(strtolower($condition)) {
				case 'equals':
				case 'eq':
					$relations = '=';
					break;
				case 'not':
				case 'neq':
					$relations = '<>';
					break;
				case 'bigger':
				case 'b':
				case '>':
					$relations = '>';
					break;
				case 'smaller':
				case 's':
				case '<':
					$relations = '<';
					break;
				case 'bigger or same':
				case 'beq':
				case '>=':
					$relations = '>=';
					break;
				case 'smaller or same':
				case 'seq':
				case '<=':
					$relations = '<=';
					break;
				case 'hasoneof':
				case 'hasanyof':
				case 'hasnoneof':
					$relations = strtolower($condition);
					break;
				case 'like':
				default:
					$relations = 'LIKE';
			}
			if(in_array($name,array('blogid','userid'))) {	// Legacy support for plugins (with string-type blogid)
				$qualifiers = (is_array($value) && in_array(strtolower($condition),array('hasoneof','hasanyof','hasnoneof'))) ? (empty($value) ? array(0) : array_map('intval', array_values($value))) : intval($value);	// Textcube-NG: blogid·userid 의 hasoneof 류 배열 값은 원소마다 정수로(원본 intval(배열) = 1, r3-3)
			} else if (in_array(strtolower($condition),array('hasoneof','hasanyof','hasnoneof'))) {
				if($escape !== false) {
					$escapedCandidates = array();
					if(is_array($value)) {
						foreach ($value as $c) {
							array_push($escapedCandidates,'\''.POD::escapeString($c).'\'');
						}
					} else array_push($escapedCandidates,'\''.POD::escapeString((string)$value).'\'');	// Textcube-NG: 배열이 아닌 값도 따옴표로 감싸고 escape(r3-3)
					$value = $escapedCandidates;
				}
				$qualifiers = $value;
			} else {
				$qualifiers = ($escape === false && (!is_string($value) || in_array($value,$this->_reservedFunctions) || $autoquote == false) ?
					$value : ($escape ? '\'' .
						POD::escapeString(
							(($relations == 'LIKE') ? '%'.$value.'%' : $value)
						) .
				'\'' : "'" . $value . "'"));
			}
		}
		return array($qualifiers, $relations);
	}
}
?>
