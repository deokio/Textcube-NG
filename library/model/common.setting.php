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

global $userSetting;
$userSetting = array();

function clearServiceSettingCache() {
	global $__serviceSetting;
	if( !empty($__serviceSetting) ) {
		$__serviceSetting = array();
	}
}

function clearUserSettingCache() {
	global $userSetting;
	if( !empty($userSetting) ) {
		$userSetting = array();
	}
}

function getServiceSetting($name, $default = null) {
	return Setting::getServiceSetting($name, $default, true);
}

function setServiceSetting($name, $value) {
	return Setting::setServiceSettingGlobal($name, $value);
}

function removeServiceSetting($name,$pruneSimilarEntries = false) {
	global $database;
	clearServiceSettingCache();
	if($pruneSimilarEntries) return POD::execute("DELETE FROM {$database['prefix']}ServiceSettings WHERE name like '".POD::escapeString($name)."'");
	// Textcube-NG: 저장(Setting::setServiceSetting)과 같이 이름을 32바이트로 줄여 지운다(ServiceSettings.name varchar(32))(r3-5)
	else return POD::execute("DELETE FROM {$database['prefix']}ServiceSettings WHERE name = '".POD::escapeString(UTF8::lessenAsEncoding($name, 32))."'");
}

function getBlogSetting($name, $default = null, $blogid = null) {
	return Setting::getBlogSettingGlobal($name, $default);
}

function setBlogSetting($name, $value, $blogid = null) {
	return Setting::setBlogSettingGlobal($name, $value, $blogid);
}

function setBlogSettingDefault($name, $value, $blogid = null) {
	return Setting::setBlogSettingDefault($name, $value, $blogid);
}

function removeBlogSetting($name, $blogid = null) {
	return Setting::removeBlogSettingGlobal($name, $blogid);
}

function getUserSetting($name, $default = null, $userid = null) {
	return Setting::getUserSettingGlobal($name, $default, $userid);
}

function setUserSetting($name, $value, $userid = null) {
	return Setting::setUserSettingGlobal($name, $value, $userid);
}

function removeUserSetting($name, $userid = null) {
	return Setting::removeUserSettingGlobal($name, $userid);
}

function getDefinedTableNames() {
	global $database;
	$prefix = $database['prefix'];
	$definedTables = array(
			"{$prefix}Attachments", 
			"{$prefix}BlogSettings", 
			"{$prefix}BlogStatistics", 
			"{$prefix}Categories", 
			"{$prefix}Comments", 
			"{$prefix}CommentsNotified", 
			"{$prefix}CommentsNotifiedQueue", 
			"{$prefix}CommentsNotifiedSiteInfo", 
			"{$prefix}DailyStatistics", 
			"{$prefix}Entries", 
			"{$prefix}EntriesArchive", 
			"{$prefix}FeedGroupRelations", 
			"{$prefix}FeedGroups", 
			"{$prefix}FeedItems", 
			"{$prefix}FeedReads", 
			"{$prefix}OpenIDUsers", 
			"{$prefix}Feeds", 
			"{$prefix}FeedSettings", 
			"{$prefix}FeedStarred", 
			"{$prefix}Filters", 
			"{$prefix}Lines", 
			"{$prefix}Links", 
			"{$prefix}LinkCategories", 
			"{$prefix}PageCacheLog", 
			"{$prefix}Plugins", 
			"{$prefix}RefererLogs", 
			"{$prefix}RefererStatistics", 
			"{$prefix}ReservedWords", 
			"{$prefix}ServiceSettings", 
			"{$prefix}Sessions", 
			"{$prefix}SessionVisits", 
			"{$prefix}SkinSettings", 
			"{$prefix}TagRelations", 
			"{$prefix}Tags", 
			"{$prefix}Privileges", 
			"{$prefix}TeamEntryRelations", 
			"{$prefix}RemoteResponseLogs", 
			"{$prefix}RemoteResponses", 
			"{$prefix}Users", 
			"{$prefix}UserSettings", 
			"{$prefix}Widgets", 
			// Textcube-NG: r4-13 — 설치 SQL 의 코어 휴지통 두 테이블이 원본부터 빠져, 플러그인 데이터 관리에서 미등록 표로 보이고(지울 수 있음) REPAIR 대상에서 빠졌다(dojoref 2.5)
			"{$prefix}TrashComments", 
			"{$prefix}TrashRemoteResponses", 
			"{$prefix}XMLRPCPingSettings");
	return $definedTables;
}
?>
