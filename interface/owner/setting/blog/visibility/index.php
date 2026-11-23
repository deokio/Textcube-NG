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
$IV = array(
	'POST' => array(
		'visibility' => array('int',0,3,'mandatory'=>false),
		'acceptComments' => array('int',0,1,'mandatory'=>false),
		'acceptTrackbacks' => array('int',0,1,'mandatory'=>false),
		'acceptNotifiedComments' => array('int',0,1,'mandatory'=>false),
		'useiPhoneUI' => array('int',0,1,'mandatory'=>false)
	)
);
require ROOT . '/library/preprocessor.php';
requireModel('blog.feed');

requireStrictRoute();
$result = false;
if(isset($_POST['visibility'])) {
	if (Setting::setBlogSettingGlobal('visibility',$_POST['visibility'])) {
		CacheControl::flushCommentRSS();
		CacheControl::flushTrackbackRSS();
		clearFeed();
		$result = true;
	}
}
if(isset($_POST['acceptComments'])) {
	if(Setting::setBlogSettingGlobal('acceptComments',$_POST['acceptComments'])) $result = true;
}

if(isset($_POST['acceptTrackbacks'])) {
	if(Setting::setBlogSettingGlobal('acceptTrackbacks',$_POST['acceptTrackbacks'])) $result = true;
}
// Textcube-NG: 댓글 알리미 받기 설정(0 = 받지 않음, 기본 1)(r3-3)
if(isset($_POST['acceptNotifiedComments'])) {
	if(Setting::setBlogSettingGlobal('acceptNotifiedComments',$_POST['acceptNotifiedComments'])) $result = true;
}
if(isset($_POST['useiPhoneUI'])) {
	if($_POST['useiPhoneUI'] == 1) $useiPhoneUI = true;
	else $useiPhoneUI = false;
	if(Setting::setBlogSettingGlobal('useiPhoneUI',$useiPhoneUI)) $result = true;
}
if($result)	{$gCacheStorage->purge();Respond::ResultPage(0);}
else Respond::ResultPage(-1);
?>
