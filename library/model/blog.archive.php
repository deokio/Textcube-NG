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

function getArchives($blogid, $option = 'yearmonth') {
	global $database;
	$archives = array();
	$visibility = doesHaveOwnership() ? '' : 'AND e.visibility > 0'.getPrivateCategoryExclusionQuery($blogid);
	// Textcube-NG: 글관리 권한(group.editors)이 없는 구성원은 남의 비공개 글을 보지 않는다(blog.entry.php 글 목록과 같은 조건)(v3.145)
	$visibility .= (doesHaveOwnership() && !Acl::check('group.editors')) ? ' AND (e.userid = '.getUserId().' OR e.visibility > 0)' : '';
	$skinSetting = getSkinSettings($blogid);
	$archivesOnPage = $skinSetting['archivesOnPage'];
	
	switch (POD::dbms()) {
		case 'PostgreSQL':
			if($option == 'year') $format = 'year';
			else if ($option == 'month') $format = 'month';
			else $format = 'year, month';
			// Textcube-NG: year·month 옵션은 묶는 열만 고른다(묶지 않은 열이 있어 GROUP BY 오류). yearmonth 는 연·월 모두 내림차순(원본 ORDER BY year, month DESC 는 연도 오름차순이라 가장 오래된 달부터 나옴)(v3.183)
			$columns = array();
			if ($format != 'month') $columns[] = 'EXTRACT(YEAR FROM FROM_UNIXTIME(e.published)) AS year';
			if ($format != 'year') $columns[] = 'EXTRACT(MONTH FROM FROM_UNIXTIME(e.published)) AS month';
			$order = ($format == 'year, month') ? 'year DESC, month DESC' : $format.' DESC';
			$sql = "SELECT ".implode(', ', $columns).", COUNT(*) AS count 
				FROM {$database['prefix']}Entries e
				WHERE e.blogid = $blogid AND e.draft = 0 $visibility AND e.category >= 0 
				GROUP BY $format 
				ORDER BY $order
				LIMIT $archivesOnPage";
			$result = POD::queryAllWithDBCache($sql, 'entry');
			if ($result) {
				foreach($result as $archive) {
					switch ($option) {
						case 'year':
							$archive['period'] = $archive['year'];
							break;
						case 'month':
							$archive['period'] = sprintf("%02d",$archive['month']);
							break;
						case 'yearmonth':
						default:
							$archive['period'] = $archive['year'].sprintf("%02d",$archive['month']);
							break;
					}
					array_push($archives, $archive);
				}
			}
			break;
		case 'SQLite3':
			if($option == 'year') $format = '%Y';
			else if ($option == 'month') $format = '%m';
			else $format = '%Y%m';
			$sql = "SELECT strftime('".$format."',e.published,'unixepoch') AS period, COUNT(*) AS count 
				FROM {$database['prefix']}Entries e
				WHERE e.blogid = $blogid AND e.draft = 0 $visibility AND e.category >= 0 
				GROUP BY period 
				ORDER BY period 
				DESC LIMIT $archivesOnPage";
			$result = POD::queryAllWithDBCache($sql, 'entry');
			if ($result) {
				foreach($result as $archive)
					array_push($archives, $archive);
			}
			break;
		case 'Cubrid':
			if($option == 'year') $format = 'YYYY';
			else if ($option == 'month') $format = 'MM';
			else $format = 'YYYYMM';
			// Textcube-NG: 기준 시각을 시간대 없는 DATETIME 으로 더한다(TIMESTAMP 범위 밖 published 와 세션 시간대 Asia/Seoul 에서 쿼리 전체가 실패하던 문제). 묶음도 옵션 형식으로(v3.183)
			$sql = "SELECT TO_CHAR(DATE_ADD(DATETIME'1970-01-01 09:00:00', INTERVAL e.published SECOND), '$format') period, 
				COUNT(*) \"count\"
				FROM {$database['prefix']}Entries e
				WHERE e.blogid = $blogid AND e.draft = 0 $visibility AND e.category >= 0 
				GROUP BY TO_CHAR(DATE_ADD(DATETIME'1970-01-01 09:00:00', INTERVAL e.published SECOND), '$format') 
				ORDER BY period
				DESC FOR ORDERBY_NUM() BETWEEN 1 AND $archivesOnPage";
			$result = POD::queryAllWithDBCache($sql, 'entry');
			if($result) {
				foreach($result as $archive)
					array_push($archives, $archive);
			}
			break;
		case 'MySQL':
		case 'MySQLi':
		default:
			if($option == 'year') $format = 'year';
			else if ($option == 'month') $format = 'month';
			else $format = 'year_month';
			$sql = "SELECT EXTRACT($format FROM FROM_UNIXTIME(e.published)) period, COUNT(*) count 
				FROM {$database['prefix']}Entries e
				WHERE e.blogid = $blogid AND e.draft = 0 $visibility AND e.category >= 0 
				GROUP BY period 
				ORDER BY period 
				DESC LIMIT $archivesOnPage";
			$result = POD::queryAllWithDBCache($sql, 'entry');
			if ($result) {
				foreach($result as $archive)
					array_push($archives, $archive);
			}
			break;
	}
	return $archives;
}

function getCalendar($blogid, $period) {
	global $database;
	$calendar = array('days' => array());
	if (($period === true) || !checkPeriod($period))
		$period = Timestamp::getYearMonth();
	$calendar['period'] = $period;
	$calendar['year'] = substr($period, 0, 4);
	$calendar['month'] = substr($period, 4, 2);
	$visibility = doesHaveOwnership() ? '' : 'AND e.visibility > 0'.getPrivateCategoryExclusionQuery($blogid);
	// Textcube-NG: 글관리 권한(group.editors)이 없는 구성원은 남의 비공개 글을 보지 않는다(blog.entry.php 글 목록과 같은 조건)(v3.145)
	$visibility .= (doesHaveOwnership() && !Acl::check('group.editors')) ? ' AND (e.userid = '.getUserId().' OR e.visibility > 0)' : '';
	
	switch(POD::dbms()) {
		case 'Cubrid':
			// Textcube-NG: 기준 시각을 시간대 없는 DATETIME 으로 더한다(TIMESTAMP 범위 밖 published 와 세션 시간대 Asia/Seoul 에서 쿼리 전체가 실패하던 문제)(v3.183)
			$result=POD::queryAllWithDBCache("SELECT DISTINCT TO_CHAR(DATE_ADD(DATETIME'1970-01-01 09:00:00', INTERVAL e.published SECOND), 'DD')
				FROM {$database['prefix']}Entries e
				WHERE e.blogid = $blogid AND e.draft = 0 $visibility AND e.category >= 0 AND
					TO_CHAR(DATE_ADD(DATETIME'1970-01-01 09:00:00', INTERVAL e.published SECOND), 'YYYY') = '{$calendar['year']}' AND
					TO_CHAR(DATE_ADD(DATETIME'1970-01-01 09:00:00', INTERVAL e.published SECOND), 'MM') = '{$calendar['month']}'",'entry');	
			break;
		case 'SQLite3':
		$result = POD::queryAllWithDBCache("SELECT DISTINCT strftime('%d',e.published,'unixepoch') 
			FROM {$database['prefix']}Entries e
			WHERE e.blogid = $blogid AND e.draft = 0 $visibility AND e.category >= 0 AND strftime('%Y',e.published,'unixepoch') = {$calendar['year']} AND strftime('%m',e.published,'unixepoch') = {$calendar['month']}",'entry');		
			break;
		case 'MySQL':
		case 'MySQLi':
		case 'PostgreSQL':
		default:	
			$result = POD::queryAllWithDBCache("SELECT DISTINCT DAYOFMONTH(FROM_UNIXTIME(e.published)) 
				FROM {$database['prefix']}Entries e
				WHERE e.blogid = $blogid AND e.draft = 0 $visibility AND e.category >= 0 AND YEAR(FROM_UNIXTIME(e.published)) = {$calendar['year']} AND MONTH(FROM_UNIXTIME(e.published)) = {$calendar['month']}",'entry');
			break;
	}
	if ($result) {
		foreach($result as $dayArray) {
			list($day) = $dayArray;
			array_push($calendar['days'], $day);
		}
	}
	$calendar['days'] = array_flip($calendar['days']);
	return $calendar;
}
?>
