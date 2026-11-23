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

interface IAdapter {
	/// DBMS Connection
	public static function bind($database);
	public static function unbind();
	/// DBMS Information
	public static function charset();
	public static function dbms();
	public static function version($mode = 'server');
	public static function tableList($condition = null);
	public static function setTimezone($time);
	public static function reservedFieldNames();
	public static function reservedFunctionNames();
	/// Querying
	public static function queryExistence($query);
	public static function queryCount($query);
	public static function queryCell($query, $field = 0, $useCache=true);
	public static function queryRow($query, $type = 'both', $useCache=true);
	public static function queryColumn($query, $useCache=true);
	public static function queryAll ($query, $type = 'both', $count = -1);
	public static function queryAllWithoutCache($query, $type = 'both', $count = -1);
	public static function queryAllWithCache($query, $type = 'both', $count = -1);
	public static function execute($query);
	public static function multiQuery();
	public static function query($query);
	public static function insertId();
	public static function escapeString($string, $link = null);
	public static function clearCache();
	public static function cacheLoad();
	public static function cacheSave();
	/// Transaction
	public static function commit();
	/// RAW connection
	public static function num_rows($handle = null);
	public static function free($handle = null);
	public static function fetch($handle = null, $type = 'assoc');
	public static function error($err = null);
	public static function stat($stat = null);
	public static function __queryType($type);
	/// Model Creation
	public static function fieldType($abstractType);
	/// Prepared Statement (Textcube-NG: php8.2 이식본과 같은 선언)(v3.211)
	public static function prepare($query);
	public static function bindAndExecute($stmt, $types);	// Textcube-NG: 값은 세 번째 인자부터 — PHP 5.5 에는 가변 인자(...)가 없어 구현이 func_get_args() 로 받는다(r4-4)
	public static function fetchAllStmt($stmt);
}
?>
