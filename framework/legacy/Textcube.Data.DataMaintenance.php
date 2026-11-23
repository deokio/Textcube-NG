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
class DataMaintenance {
	/*@static@*/
	static function removeAll($removeAttachments = true) {
		global $database;
		$blogid = getBlogId();	
		$tags = POD::queryColumn("SELECT DISTINCT tag FROM {$database['prefix']}TagRelations WHERE blogid = $blogid");
		
		POD::query("UPDATE {$database['prefix']}BlogStatistics SET visits = 0 WHERE blogid = $blogid");
		POD::query("DELETE FROM {$database['prefix']}DailyStatistics WHERE blogid = $blogid");
		POD::query("DELETE FROM {$database['prefix']}Categories WHERE blogid = $blogid");
		POD::query("DELETE FROM {$database['prefix']}Attachments WHERE blogid = $blogid");
		POD::query("DELETE FROM {$database['prefix']}Comments WHERE blogid = $blogid");
		POD::query("DELETE FROM {$database['prefix']}CommentsNotified WHERE blogid = $blogid");
		POD::query("DELETE FROM {$database['prefix']}RemoteResponses WHERE blogid = $blogid");
		POD::query("DELETE FROM {$database['prefix']}RemoteResponseLogs WHERE blogid = $blogid");
		POD::query("DELETE FROM {$database['prefix']}TagRelations WHERE blogid = $blogid");
		POD::query("DELETE FROM {$database['prefix']}Entries WHERE blogid = $blogid");
		POD::query("DELETE FROM {$database['prefix']}LinkCategories WHERE blogid = $blogid");
		POD::query("DELETE FROM {$database['prefix']}Links WHERE blogid = $blogid");
		POD::query("DELETE FROM {$database['prefix']}RefererLogs WHERE blogid = $blogid");
		POD::query("DELETE FROM {$database['prefix']}RefererStatistics WHERE blogid = $blogid");
		// Textcube-NG: r3-43 R-573 Plugins 행을 지우기 전에 이 블로그의 플러그인 무결성 기준값(BlogSettings pgi_*)도 지운다
		// (deactivatePlugin() 과 같은 pluginGuardRemoveBaseline() 사용 — 그대로 두면 지운 플러그인의 기준값 행이 남는다)
		if (function_exists('pluginGuardRemoveBaseline')) {
			$guardPlugins = POD::queryColumn("SELECT name FROM {$database['prefix']}Plugins WHERE blogid = $blogid");
			if (is_array($guardPlugins)) {
				foreach ($guardPlugins as $guardPlugin) pluginGuardRemoveBaseline($guardPlugin);
			}
			unset($guardPlugins, $guardPlugin);
		}
		POD::query("DELETE FROM {$database['prefix']}Plugins WHERE blogid = $blogid");
		if (function_exists('pluginKeptSettingsClear')) pluginKeptSettingsClear();	// Textcube-NG: r3-42 끌 때 보관한 플러그인 설정도 지운다(BlogSettings pluginSettingsKept)
		//POD::query("DELETE FROM {$database['prefix']}UserSettings WHERE user = $blogid");
		
		POD::query("DELETE FROM {$database['prefix']}Filters WHERE blogid = $blogid");
		POD::query("DELETE FROM {$database['prefix']}FeedStarred WHERE blogid = $blogid");
		POD::query("DELETE FROM {$database['prefix']}FeedReads WHERE blogid = $blogid");
		POD::query("DELETE FROM {$database['prefix']}FeedGroupRelations WHERE blogid = $blogid");
		POD::query("DELETE FROM {$database['prefix']}FeedGroups WHERE blogid = $blogid AND id <> 0");
		
		if (count($tags) > 0) 
		{
			$tagliststr = implode(', ', $tags);
			$nottargets = POD::queryColumn("SELECT DISTINCT tag FROM {$database['prefix']}TagRelations WHERE tag in ( $tagliststr )");
			if (count($nottargets) > 0) {
				$nottargetstr	= implode(', ', $nottargets);
				POD::execute("DELETE FROM {$database['prefix']}Tags WHERE id IN ( $tagliststr ) AND id NOT IN ( $nottargetstr )");
			} else {
				POD::execute("DELETE FROM {$database['prefix']}Tags WHERE id IN ( $tagliststr ) ");
			}
		}
		
		if (file_exists(__TEXTCUBE_CACHE_DIR__."/rss/$blogid.xml"))
			unlink(__TEXTCUBE_CACHE_DIR__."/rss/$blogid.xml");
		
		if ($removeAttachments) {
			Path::removeFiles(Path::combine(ROOT, 'attach', $blogid));
			// Textcube-NG: r3-43 R-572 BlogSettings 는 (blogid, name, value) 표라 원본의 'SET logo = ''' 질의는 없는 칸이라 실패했다(로고 설정이 남음).
			// removeBlogLogo() 와 같은 Setting API 로 logo 값을 비운다(요청 안 설정 묶음과 DB 를 함께 바꾼다)
			Setting::setBlogSettingGlobal('logo', '', $blogid);
		}
	}
}
?>
