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

// Dependency : Textcube.Model.Paging (getRefererLogsWithPage)

class Statistics {
	static function getStatistics($blogid) {
		global $database;
		$stats = array('total' => 0, 'today' => 0, 'yesterday' => 0);
		$result = POD::queryCell("SELECT visits FROM {$database['prefix']}BlogStatistics WHERE blogid = $blogid");
		if (!empty($result)) $stats['total'] = $result;
		
		$result = POD::queryAll("SELECT datemark, visits FROM {$database['prefix']}DailyStatistics WHERE blogid = $blogid AND datemark in (" . Timestamp::getDate().",".Timestamp::getDate(time()-86400).")");
		$stat['today'] = $stat['yesterday'] = 0;
		foreach($result as $data) {
			if($data['datemark'] == Timestamp::getDate()) $stats['today'] = $data['visits'];
			if($data['datemark'] == Timestamp::getDate(time()-86400)) $stats['yesterday'] = $data['visits'];
		}
	
		return $stats;
	}

	static function getDailyStatistics($period, $blogid = null) {
		global $database;
		// Textcube-NG: optional $blogid; null keeps the global current blog as before (callers unchanged). A passed value gets
		// the same strict check as getMonthlyStatistics() before it goes into SQL (v3.194)
		if (is_null($blogid)) $blogid = isset($GLOBALS['blogid']) ? $GLOBALS['blogid'] : null;
		else if ((!is_int($blogid) && !is_string($blogid)) || !Validator::isInteger($blogid)) return null;
		else $blogid = (int)$blogid;
		// Strict reject (same criterion as Validator::isInteger): a non-integer is not queried. Only int/string are checked, so the preg_match() inside
		// isInteger never receives an array/object/null (warning or TypeError). The value returned is what POD::queryAll() returns on a failed query (null).
		if ((!is_int($period) && !is_string($period)) || !Validator::isInteger($period)) return null;
		$period = (int)$period;
		// LEFT() is not available on SQLite3/PostgreSQL. First 6 characters of datemark as a string on every DBMS: CAST AS CHAR(11) holds any 32-bit integer
		// (up to 11 characters) without overflow, RTRIM removes the blank padding CUBRID adds to fixed-length CHAR (VARCHAR is not a MySQL CAST type).
		return POD::queryAll("SELECT datemark, visits 
			FROM {$database['prefix']}DailyStatistics 
			WHERE blogid = $blogid 
				AND RTRIM(SUBSTR(CAST(datemark AS CHAR(11)), 1, 6)) = '$period' 
			ORDER BY datemark DESC");
	}
	
	static function getWeeklyStatistics($blogid = null) {
		global $database;
		// Textcube-NG: optional $blogid, same handling as getDailyStatistics() (v3.194)
		if (is_null($blogid)) $blogid = isset($GLOBALS['blogid']) ? $GLOBALS['blogid'] : null;
		else if ((!is_int($blogid) && !is_string($blogid)) || !Validator::isInteger($blogid)) return null;
		else $blogid = (int)$blogid;
		$now_day = date('Ymd', strtotime("now"));
		$old_day = date('Ymd', strtotime("-1 week"));
		return POD::queryAll("SELECT datemark, visits FROM {$database['prefix']}DailyStatistics WHERE blogid = $blogid AND datemark BETWEEN $old_day AND $now_day ORDER BY datemark DESC");
	}

	static function getMonthlyStatistics($blogid) {
		global $database;
		$statistics = array();
		// Strict reject (same criterion as Validator::isInteger) on $blogid, the only input; a failed query leaves $statistics empty, so return that.
		if ((!is_int($blogid) && !is_string($blogid)) || !Validator::isInteger($blogid)) return $statistics;
		$blogid = (int)$blogid;
		// LEFT() is not available on SQLite3/PostgreSQL. First 6 characters of datemark as a string on every DBMS: CAST AS CHAR(11) holds any 32-bit integer
		// (up to 11 characters) without overflow, RTRIM removes the blank padding CUBRID adds to fixed-length CHAR (VARCHAR is not a MySQL CAST type).
		if ($result = POD::queryAll("SELECT RTRIM(SUBSTR(CAST(datemark AS CHAR(11)), 1, 6)) datemark, sum(visits) visits 
			FROM {$database['prefix']}DailyStatistics 
			WHERE blogid = $blogid 
			GROUP BY RTRIM(SUBSTR(CAST(datemark AS CHAR(11)), 1, 6)) 
			ORDER BY datemark DESC")) {
			foreach($result as $record)
				array_push($statistics, $record);
		}
		return $statistics;
	}

	static function getRefererStatistics($blogid) {
		global $database;
		$statistics = array();
		if ($result = POD::queryAll("SELECT host, count FROM {$database['prefix']}RefererStatistics WHERE blogid = $blogid ORDER BY COUNT DESC LIMIT 20")) {
			foreach($result as $record)
				array_push($statistics, $record);
		}
		return $statistics;
	}

	static function getRefererLogsWithPage($page, $count) {  
		global $database, $blogid;
		return Paging::fetch("SELECT host, url, referred FROM {$database['prefix']}RefererLogs WHERE blogid = $blogid ORDER BY referred DESC", $page, $count);  
	}

	static function getRefererLogs() {
		global $database;
		return POD::queryAll("SELECT host, url, referred FROM {$database['prefix']}RefererLogs WHERE blogid = ".getBlogId()." ORDER BY referred DESC LIMIT 1500");
	}

	static function updateVisitorStatistics($blogid) {
		global $database, $blogURL;
		if (!fireEvent('UpdatingVisitorStatistics', true))
			return;
		if (doesHaveOwnership())
			return;
		// [보안] 세션 ID·REMOTE_ADDR escape (원본은 SQL 문자열에 그대로 삽입)
		$id = POD::escapeString(session_id());
		$address = POD::escapeString($_SERVER['REMOTE_ADDR']);
		if(POD::queryCount("SELECT blogid FROM {$database['prefix']}SessionVisits WHERE id = '$id' AND address = '$address' AND blogid = $blogid") > 0)
			return;
		if (POD::queryCount("INSERT INTO {$database['prefix']}SessionVisits values('$id', '$address', $blogid)") > 0) {
			if(POD::queryCount("UPDATE {$database['prefix']}BlogStatistics SET visits = visits + 1 WHERE blogid = $blogid") < 1) {
				POD::execute("INSERT into {$database['prefix']}BlogStatistics values($blogid, 1)");
			}
			
			$period = Timestamp::getDate();
			if(POD::queryCount("UPDATE {$database['prefix']}DailyStatistics SET visits = visits + 1 WHERE blogid = $blogid AND datemark = $period LIMIT 1") < 1) {
				POD::execute("INSERT INTO {$database['prefix']}DailyStatistics VALUES ($blogid, $period, 1)");
			}
			if (!empty($_SERVER['HTTP_REFERER'])) {
				$referer = parse_url($_SERVER['HTTP_REFERER']);
				if (!empty($referer['host']) && (($referer['host'] != $_SERVER['HTTP_HOST']) || (strncmp($referer['path'], $blogURL, strlen($blogURL)) != 0))) {
					if (Filter::isFiltered('ip', $_SERVER['REMOTE_ADDR']) || Filter::isFiltered('url', $_SERVER['HTTP_REFERER']))
						return;
					if (!fireEvent('AddingRefererLog', true, array('host' => $referer['host'], 'url' => $_SERVER['HTTP_REFERER'])))
						return;
					$host = POD::escapeString(UTF8::lessenAsEncoding($referer['host'], 64));
					$url = POD::escapeString(UTF8::lessenAsEncoding($_SERVER['HTTP_REFERER'], 255));
					POD::query("INSERT INTO {$database['prefix']}RefererLogs values($blogid, '$host', '$url', UNIX_TIMESTAMP())");
//					POD::query("DELETE FROM {$database['prefix']}RefererLogs WHERE referred < UNIX_TIMESTAMP() - 604800");	// Moved to trashVan
					if (!POD::queryCount("UPDATE {$database['prefix']}RefererStatistics SET count = count + 1 WHERE blogid = $blogid AND host = '$host' LIMIT 1"))
						POD::execute("INSERT into {$database['prefix']}RefererStatistics values($blogid, '$host', 1)");
				}
			}
		}
	}

	static function setTotalStatistics($blogid) {
		global $database;
		POD::execute("DELETE FROM {$database['prefix']}DailyStatistics WHERE blogid = $blogid");
		$prevCount = POD::queryCell("SELECT visits FROM {$database['prefix']}BlogStatistics WHERE blogid = $blogid");
		if ((!is_null($prevCount)) && ($prevCount == 0))
			return true;
		if(POD::execute("UPDATE {$database['prefix']}BlogStatistics SET visits = 0 WHERE blogid = $blogid")) {
			return true;
		} else {
			$result = POD::execute("INSERT INTO {$database['prefix']}BlogStatistics values($blogid, 0)");
			return $result;
		}
	}
}
?>
