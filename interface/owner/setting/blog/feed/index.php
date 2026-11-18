<?php
/// Copyright (c) 2004-2016, Needlworks  / Tatter Network Foundation
/// All rights reserved. Licensed under the GPL.
/// See the GNU General Public License for more details. (/documents/LICENSE, /documents/COPYRIGHT)

$IV = array(
	'POST' => array(
		'publishWholeOnRSS' => array('int', 0, 1, 'default' => 0),
		'publishEolinSyncOnRSS' => array('int', 0, 1, 'default' => 0),
		'entriesOnRSS' => array('int', 'default' => 5),
		'commentsOnRSS' => array('int', 'default' => 5),
		'useFeedViewOnCategory' => array('int',0,1,'default'=> 1),
		'rssURL' => array('url','mandatory'=>false),
		'atomURL' => array('url','mandatory'=>false)
		)
	);
require ROOT . '/library/preprocessor.php';
requireStrictRoute();
setCommentsOnRSS($blogid, $_POST['commentsOnRSS']);
setEntriesOnRSS($blogid, $_POST['entriesOnRSS']);

// Feed range 
Setting::setBlogSettingGlobal('publishWholeOnRSS',$_POST['publishWholeOnRSS']);
Setting::setBlogSettingGlobal('publishEolinSyncOnRSS',$_POST['publishEolinSyncOnRSS']);

// Category Feed
Setting::setBlogSettingGlobal('useFeedViewOnCategory',$_POST['useFeedViewOnCategory']);
Setting::setBlogSettingGlobal('atomURL',$_POST['atomURL']);
Setting::setBlogSettingGlobal('rssURL',$_POST['rssURL']);
clearFeed();
CacheControl::flushSkin();
CacheControl::flushEntry();	// Textcube-NG: 피드 공개 범위(publishWholeOnRSS 등)가 바뀌면 글 캐시(RSS 요약 본문 포함)도 비운다. 원본은 피드·스킨 캐시만 비웠다(r3-5)
Respond::ResultPage(0);
?>
