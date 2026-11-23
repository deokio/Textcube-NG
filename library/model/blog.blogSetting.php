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

function setBlogTitle($blogid, $title) {
	$context = Model_Context::getInstance();
	if ($title == $context->getProperty('blog.title'))
		return true;
	if(Setting::setBlogSetting('title', UTF8::lessenAsEncoding($title, 255),true) === false) return false;
	$context->setProperty('blog.title',$title);
	$blog['title'] = $title;
	requireModel('blog.feed');
	requireLibrary('blog.skin');
	clearFeed();
	CacheControl::flushSkin();
	return true;
}

function setBlogDescription($blogid, $description) {
	$context = Model_Context::getInstance();
	if ($description == $context->getProperty('blog.description'))
		return true;
	if(Setting::setBlogSettingGlobal('description',UTF8::lessenAsEncoding($description, 255)) === false) return false;
	$context->setProperty('blog.description', $description);
	requireModel('blog.feed');
	requireLibrary('blog.skin');
	clearFeed();
	CacheControl::flushSkin();
	return true;
}

function setBlogTags($blogid, $tags) {
	if(isset($tags)) {
		Setting::setBlogSettingGlobal('blogTags',$tags);
		return true;
	}
	return false;
}

function getBlogTags($blogid) {
	if($tags = Setting::getBlogSettingGlobal('blogTags')) {
		return $tags;
	}
	return null;
}

function removeBlogLogo($blogid) {
	$context = Model_Context::getInstance();
	$skin = new Skin($context->getProperty('skin.skin'));
	requireModel('blog.attachment');

	if(Setting::setBlogSettingGlobal('logo','') === false) return false;
	else {
		deleteAttachment($blogid, - 1, $context->getProperty('blog.logo'));
		$context->setProperty('blog.logo','');
		$skin->purgeCache();
		return true;
	}
}

function changeBlogLogo($blogid, $file) {
	$context = Model_Context::getInstance();
	$skin = new Skin($context->getProperty('skin.skin'));
	requireModel('blog.attachment');
	if (($attachment = addAttachment($blogid, - 1, $file)) === false) {
		return false;
	}
	if (strncmp($attachment['mime'], 'image/', 6) != 0) {
		deleteAttachment($blogid, - 1, $attachment['name']);
		return false;
	}
	if(Setting::setBlogSettingGlobal('logo',$attachment['name'])) {
		deleteAttachment($blogid, - 1, $context->getProperty('blog.logo'));
		$blog['logo'] = $attachment['name'];
		$skin->purgeCache();
		return true;
	}
	return false;
}

function checkBlogName($name) {
	return preg_match('/^[-a-zA-Z0-9]+$/', $name);
}

function setPrimaryDomain($blogid, $name) {
	global $database, $blog;
	requireModel('blog.feed');
	$name = UTF8::lessenAsEncoding(strtolower(trim($name)), 32);
	if ($name == $blog['name'])
		return 0;
	if (!checkBlogName($name))
		return 1;
	if (POD::queryCount("SELECT * FROM {$database['prefix']}ReservedWords WHERE '$name' like word") > 0)
		return 2;
	if (POD::queryCount("SELECT * FROM {$database['prefix']}BlogSettings WHERE name = 'name' AND value = '$name'") > 0)
		return 3;
	// Textcube-NG: 저장에 실패해도 0(성공과 같은 값)을 돌려주던 것을 4(실패)로, 성공은 null 대신 0 을 돌려준다.
	// 호출부(domain/primary·domain/set)는 '> 0' 을 실패로 본다(domain/set 은 error 2·msg 4 → '1차 블로그 도메인을 변경하지 못했습니다')(r3-5)
	if(Setting::setBlogSettingGlobal('name', $name)) {
		$blog['name'] = $name;
		clearFeed();
		return 0;
	} else {
		return 4;
	}
}

function setSecondaryDomain($blogid, $domain) {
	global $database, $blog;
	requireModel('blog.feed');
	$domain = UTF8::lessenAsEncoding(strtolower(trim($domain)), 64);
	if ($domain == $blog['secondaryDomain'])
		return 0;
	if (empty($domain))
		setBlogSetting('secondaryDomain','');
	else if (Validator::domain($domain)) {
		if (POD::queryExistence("SELECT * FROM {$database['prefix']}BlogSettings
			WHERE blogid <> $blogid
				AND name = 'secondaryDomain'
				AND (value = '$domain' OR value = '" . (substr($domain, 0, 4) == 'www.' ? substr($domain, 4) : 'www.' . $domain) . "')"))
			return 1;
		setBlogSetting('secondaryDomain',$domain);
	}
	else
		return 2;
	$blog['secondaryDomain'] = $domain;
	clearFeed();
	return 0;
}

function setDefaultDomain($blogid, $default) {
	global $blog;
	requireModel('blog.feed');
	$default = $default == 1 ? 1 : 0;
	if (empty($blog['secondaryDomain']) && $default == 1)
		return false;
	if ($default == $blog['defaultDomain'])
		return true;
	if(Setting::setBlogSettingGlobal('defaultDomain',$default) === false) {
		return false;
	}
	$blog['defaultDomain'] = $default;
	clearFeed();
	return true;
}

function useBlogSlogan($blogid, $useSloganOnPost, $useSloganOnCategory, $useSloganOnTag) {
	global $blog;
	requireModel('blog.feed');
	$useSloganOnPost     = $useSloganOnPost     ? 1 : 0;
	$useSloganOnCategory = $useSloganOnCategory ? 1 : 0;
	$useSloganOnTag      = $useSloganOnTag      ? 1 : 0;
	if ($useSloganOnPost == $blog['useSloganOnPost']
		&& $useSloganOnCategory == $blog['useSloganOnCategory']
		&& $useSloganOnTag == $blog['useSloganOnTag'])
		return true;
/*	if(Setting::setBlogSettingGlobal('useSloganOnPost',$useSlogan) === false
	|| Setting::setBlogSettingGlobal('useSloganOnCategory',$useSlogan) === false
	|| Setting::setBlogSettingGlobal('useSloganOnTag',$useSlogan) === false
		) {
		return false;
	}*/
	Setting::setBlogSettingGlobal('useSloganOnPost',$useSloganOnPost);
	Setting::setBlogSettingGlobal('useSloganOnCategory',$useSloganOnCategory);
	Setting::setBlogSettingGlobal('useSloganOnTag',$useSloganOnTag);

	$blog['useSloganOnPost'] = $useSloganOnPost;
	$blog['useSloganOnCategory'] = $useSloganOnCategory;
	$blog['useSloganOnTag'] = $useSloganOnTag;
	/* Textcube-NG: 원본은 flushCategory()·flushEntry()·flushTag() 를 차례로 불렀다(r3-9, 503-15). 번호 없는 flushEntry() 가
	   flushCategory()(→ flushRSS())와 flushTag(null, false)(태그 목록·키워드·태그 피드)를 이미 부르므로, 나머지인 태그 페이지(tagPage)만
	   더 비운다. 비우는 캐시는 원본과 같고, 같은 비우기 질의(피드 캐시 기록 DELETE 등)가 저장 한 번에 세 번씩 나가던 것이 한 번이 된다. */
	CacheControl::flushEntry();
	CacheControl::flushTagPage();
	fireEvent('ToggleBlogSlogan',null,$blog['useSloganOnPost']);
	clearFeed();
	return true;
}

function setEntriesOnRSS($blogid, $entriesOnRSS) {
	$context = Model_Context::getInstance();
	requireModel('blog.feed');
	if ($entriesOnRSS == $context->getProperty('blog.entriesOnRSS'))
		return true;
	if(Setting::setBlogSettingGlobal('entriesOnRSS',$entriesOnRSS) === false) return false;
	$context->setProperty('blog.entriesOnRSS',$entriesOnRSS);
	clearFeed();
	return true;
}

function setCommentsOnRSS($blogid, $commentsOnRSS) {
	global $blog;
	requireModel('blog.feed');
	if ($commentsOnRSS == $blog['commentsOnRSS'])
		return true;
	if(Setting::setBlogSettingGlobal('commentsOnRSS',$commentsOnRSS) === false) return false;
	$blog['commentsOnRSS'] = $commentsOnRSS;
	$cache = pageCache::getInstance();
	$cache->name = 'commentRSS';
	$cache->purge();
	return true;
}

function setBlogLanguage($blogid, $language, $blogLanguage) {
	global $blog;
	requireModel('blog.feed');
	if (($language == $blog['language']) && ($blogLanguage == $blog['blogLanguage']))
		return true;
	$language = UTF8::lessenAsEncoding($language, 5);
	$blogLanguage = UTF8::lessenAsEncoding($blogLanguage, 5);
	if(Setting::setBlogSettingGlobal('language',$language) && Setting::setBlogSettingGlobal('blogLanguage',$blogLanguage)) {
		$blog['language'] = $language;
		$blog['blogLanguage'] = $blogLanguage;
		clearFeed();
		// Textcube-NG: 언어를 바꿔도 PluginSettings 페이지 캐시(편집기 목록 '편집기 없음' 라벨 등 요청 언어로 만든 문구)가 남아 이전 언어로 나오던 것을
		// 저장 성공 때만 비운다(owner/plugin/index.php 와 같은 pageCache getInstance/reset/purge)(v3.172)
		$pageCache = pageCache::getInstance();
		$pageCache->reset('PluginSettings');
		$pageCache->purge();
		return true;
	} else return false;
}

function setGuestbook($blogid, $write, $comment) {
	if (!is_numeric($write) || !is_numeric($comment))
		return false;
	if(Setting::setBlogSettingGlobal('allowWriteOnGuestbook',$write) && Setting::setBlogSettingGlobal('allowWriteDblCommentOnGuestbook',$comment)) {
		return true;
	} else return false;
}

function addBlog($blogid, $userid, $identify) {
	global $database, $service;

	if(empty($userid)) {
		$userid = 1; // If no userid, choose the service administrator.
	} else {
		if(!POD::queryExistence("SELECT userid
			FROM {$database['prefix']}Users
			WHERE userid = ".$userid)) return 3; // 3: No user exists with specific userid
	}

	if(!empty($blogid)) { // If blogid,
		if(!POD::queryExistence("SELECT blogid
			FROM {$database['prefix']}BlogSettings
			WHERE blogid = ".$blogid)) {
			return 2; // 2: No blog exists with specific blogid
		}
		// Thus, blog and user exists. Now combine both.
		$result = POD::query("INSERT INTO {$database['prefix']}Privileges
			(blogid,userid,acl,created,lastlogin)
			VALUES($blogid, $userid, 0, UNIX_TIMESTAMP(), 0)");
		return $result;
	} else { // If no blogid, create a new blog.
		if (!preg_match('/^[a-zA-Z0-9]+$/', $identify))
			return 4; // Wrong Blog name
		$identify = POD::escapeString(UTF8::lessenAsEncoding(strtolower($identify), 32));

		$blogName = $identify;

		$result = POD::queryCount("SELECT *
			FROM {$database['prefix']}ReservedWords
			WHERE word = '$blogName'");
		if ($result && $result > 0) {
			return 60;	// Reserved blog name.
		}
		$reserved = POD::reservedFieldNames();
		$valueField = (is_array($reserved) && in_array('value', $reserved)) ? '"value"' : 'value';
		$result = POD::queryCount("SELECT $valueField
			FROM {$database['prefix']}BlogSettings
			WHERE name = 'name' AND $valueField = '$blogName'");
		if ($result && $result > 0) {
			return 61; // Same blogname is already exists.
		}
		$blogid = POD::queryCell("SELECT max(blogid)
			FROM {$database['prefix']}BlogSettings") + 1;
		$baseTimezone = POD::escapeString($service['timezone']);
		$basicInformation = array(
			'name'         => $identify,
			'defaultDomain'            => 0,
			'title'                    => '',
			'description'              => '',
			'logo'                     => '',
			'logoLabel'                => '',
			'logoWidth'                => 0,
			'logoHeight'               => 0,
			'useFeedViewOnCategory'    => 1,
			'useSloganOnPost'          => 1,
			'useSloganOnCategory'      => 1,
			'useSloganOnTag'           => 1,
			'entriesOnPage'            => 10,
			'entriesOnList'            => 10,
			'entriesOnRSS'             => 10,
			'commentsOnRSS'            => 10,
			'publishWholeOnRSS'        => 1,
			'publishEolinSyncOnRSS'    => 1,
			'allowWriteOnGuestbook'    => 1,
			'allowWriteDblCommentOnGuestbook' => 1,
			'acceptComments'           => 1,
			'acceptTrackbacks'         => 1,
			'visibility'               => 2,
			'created'                  => Timestamp::getUNIXtime(),
			'language'     => $service['language'],
			'blogLanguage' => $service['language'],
			'timezone'     => $baseTimezone);
		$isFalse = false;
		foreach($basicInformation as $fieldname => $fieldvalue) {
			if(setBlogSettingDefault($fieldname,$fieldvalue,$blogid) === false) {
				$isFalse = true;
			}
		}
		if($isFalse == true) {
			POD::query("DELETE FROM {$database['prefix']}BlogSettings WHERE blogid = $blogid");
			return 12;
		}

		if(!POD::query("INSERT INTO {$database['prefix']}SkinSettings VALUES ($blogid,'skin','".$service['skin']."')")) {
			deleteBlog($blogid);
			return 13;
		}
		if(!POD::query("INSERT INTO {$database['prefix']}FeedSettings
			(blogid) VALUES ($blogid)")) {
			deleteBlog($blogid);
			return 62;
		}

		if(!POD::query("INSERT INTO {$database['prefix']}FeedGroups
			(blogid, id)
			VALUES ($blogid, 0)")) {
			deleteBlog($blogid);
			return 62;
		}

		setBlogSetting('defaultEditor', 'tinyMCE', $blogid);
		setBlogSetting('defaultFormatter', 'ttml', $blogid);

		//Combine user and blog.
		if(POD::query("INSERT INTO {$database['prefix']}Privileges
			(blogid,userid,acl,created,lastlogin)
			VALUES($blogid, $userid, 16, UNIX_TIMESTAMP(), 0)")) {
			setDefaultPost($blogid, $userid);
			return true;
		} else {
			return 65;
		}
	}
	//return true; // unreachable code
}

function getDefaultPostContent() {
	return _t('<p>텍스트큐브 사용을 환영합니다. 텍스트큐브(Textcube) 는 웹에서 자신의 생각이나 일상을 기록하고 표현하기 위한 도구입니다. 강력한 글 관리와 편집 기능을 통하여 쉽고 빠르게 글을 작성하고 알릴 수 있습니다. 또한 통합된 소통 기능및 RSS 바깥글 읽기 기능을 통하여 다양한 사람들과 간단하게 의견을 주고 받을 수 있습니다.</p><p>또한 텍스트큐브는 플러그인과 테마 시스템을 통하여 다양한 기능을 추가하거나 스킨을 바꾸고 편집할 수 있습니다. 뿐만 아니라 OpenID, microformat 지원 등의 기술적인 부분 및 다국어 지원을 포함한 강력한 저작 도구입니다.</p><p>사용하며 도움말이 필요할 때는 관리자 메뉴의 우측 상단의 도우미 링크를 누르시면 도움말을 보실 수 있습니다. 기타 자세한 정보는 http://www.textcube.org 를 방문해서 확인하실 수 있습니다.</p><p>이 글은 새 블로그에 자동으로 적힌 글입니다. 관리자 화면에서 언제든지 지우셔도 됩니다.</p>'); // 언어팩에서 자유롭게 메세지를 변경할 수 있도록 함.
}

function setDefaultPost($blogid, $userid) {
	requireModel('blog.entry');
	$entry = array();
	$entry['category']         = 0;
	$entry['visibility']       = 2;
	$entry['location']         = '/';
	$entry['tag']              = '';
	$entry['title']            = _t('환영합니다!');
	$entry['slogan']           = 'welcome';
	$entry['contentformatter'] = 'ttml';
	$entry['contenteditor']    = 'tinyMCE';
	$entry['starred']          = 0;
	$entry['acceptcomment']    = 1;
	$entry['accepttrackback']  = 1;
	$entry['published']        = null;
	$entry['firstEntry']       = true;
	$entry['content']          = getDefaultPostContent();
	return addEntry($blogid, $entry, $userid);
}

function getInvited($userid) {
	global $database;
	return POD::queryAll("SELECT *
		FROM {$database['prefix']}Users
		WHERE host = '".$userid."'
		ORDER BY created ASC");
}

function getBlogName($blogid) {
	global $database;
	// Textcube-NG: Cubrid 는 value 가 예약어라 SELECT value 가 구문 오류 — " 로 감싼다(suggest 와 같음)(v3.222)
	$reserved = POD::reservedFieldNames();
	$valueField = (is_array($reserved) && in_array('value', $reserved)) ? '"value"' : 'value';
	return POD::queryCell("SELECT $valueField
		FROM {$database['prefix']}BlogSettings
		WHERE blogid = $blogid AND name = 'name'");
}
function getAuthToken($userid){
	// [보안] 유효한(만료되지 않은) 인증 토큰만 돌려준다(Auth::getValidAuthToken — 만료된 토큰은 지움)
	return Auth::getValidAuthToken($userid);
}

function sendInvitationMail($blogid, $userid, $name, $comment, $senderName, $senderEmail) {
	global $database, $service, $hostURL, $serviceURL;
	if(empty($blogid)) {
		$blogid = POD::queryCell("SELECT max(blogid)
			FROM {$database['prefix']}BlogSettings"); // If no blogid, get the latest created blogid.
	}
	$email = getUserEmail($userid);
	// [보안] 저장된 비밀번호 해시는 초대 링크에 넣지 않으므로(getInvitationLink) 읽지 않음
	$authtoken = getAuthToken($userid);
	$blogName = getBlogName($blogid);

	if (empty($email))
		return 1;
	if (!preg_match('/^[^@]+@([-a-zA-Z0-9]+\.)+[-a-zA-Z0-9]+$/', $email))
		return 2;
	/* [보안] 인증 토큰은 만료·1회용(Auth::getValidAuthToken)이므로, 아직 로그인한 적 없는 계정(Users.lastlogin = 0)에 유효한
	   토큰이 없으면(만료) 새로 발급해 다시 보낸 초대 메일로 로그인할 수 있게 한다(원본은 토큰이 만료되지 않아 같은 토큰을 다시 보냄). */
	if (empty($authtoken)) {
		$lastlogin = POD::queryCell("SELECT lastlogin FROM {$database['prefix']}Users WHERE userid = " . intval($userid), 0, false);
		if (is_numeric($lastlogin) && (intval($lastlogin) == 0))
			$authtoken = Auth::issueAuthToken($userid);
	}
	if (empty($name))
		$name = User::getName($userid);

	if (strcmp($email, UTF8::lessenAsEncoding($email, 64)) != 0) return 11;

	//$loginid = POD::escapeString(UTF8::lessenAsEncoding($email, 64));
	$name = POD::escapeString(UTF8::lessenAsEncoding($name, 32));

	//$headers = 'From: ' . encodeMail($senderName) . '<' . $senderEmail . ">\n" . 'X-Mailer: ' . TEXTCUBE_NAME . "\n" . "MIME-Version: 1.0\nContent-Type: text/html; charset=utf-8\n";
	if (empty($name))
		$subject = _textf('귀하를 %1님이 초대합니다', $senderName);
	else
		$subject = _textf('%1님을 %2님이 초대합니다', $name, $senderName);
	$message = file_get_contents(ROOT . "/resources/style/letter/letter.html");
	$message = str_replace('[##_title_##]', _text('초대장'), $message);
	$message = str_replace('[##_content_##]', $comment, $message);
	// Textcube-NG: Host 헤더 검사 — 메일의 주소(이미지·초대 링크·블로그 주소)는 요청 Host 가 아니라 domain 과 config.php 의 포트(적지 않았으면
	// 서비스 포트)로 만든다(v3.205)
	$mailPort = isset($service['configuredPort']) ? $service['configuredPort'] : (isset($service['port']) ? $service['port'] : null);
	$mailDomain = $service['domain'] . (!empty($mailPort) ? ':' . $mailPort : '');
	$mailServiceURL = isset($service['serviceURL']) ? URL::toAbsolute($service['serviceURL']) : (Model_Context::getInstance()->getProperty('service.useSSL', false) ? 'https://' : 'http://') . ((isset($service['type']) && $service['type'] == 'domain' && (string)$blogName !== '') ? $blogName . '.' : '') . $mailDomain . $service['path'];	// Textcube-NG: 서비스 주소가 //host 이면 useSSL 기준 스킴을 붙인다(빈 값일 때의 기본 분기와 같은 규칙)(r3-34)
	$message = str_replace('[##_images_##]', $mailServiceURL."/resources/style/letter", $message);
	$message = str_replace('[##_link_##]', getInvitationLink(getBlogURL($blogName, $mailDomain),$email, null, $authtoken), $message);
	$message = str_replace('[##_go_blog_##]', getBlogURL($blogName, $mailDomain), $message);
	$message = str_replace('[##_link_title_##]', _text('블로그 바로가기'), $message);
	if (empty($name)) {
		$message = str_replace('[##_to_##]', '', $message);
	} else {
		$message = str_replace('[##_to_##]', _text('받는 사람') . ': ' . $name, $message);
	}
	$message = str_replace('[##_sender_##]', _text('보내는 사람') . ': ' . $senderName, $message);
	$ret = sendEmail($senderName, $senderEmail, $name, $email, $subject, $message );
	if( $ret !== true ) {
		return array( 14, $ret[1] );
	}
	return true;
}

function getInvitationLink($url, $email, $password, $authtoken) {
	/* [보안] 원본은 requestURI 에 저장된 비밀번호 해시(?password=)를 넣어 초대 메일·팀블로그 화면 URL 로 해시가 나갔다.
	   초대받은 사용자는 인증 토큰이 있으면 현재 비밀번호 없이 변경되고(owner/setting/account/password), 저장된 해시는
	   이전 비밀번호로 받지 않으므로(changePassword) 넣지 않는다. $password 인자는 호출 호환용으로 남김(사용하지 않음).
	   토큰이 없을 때 rawurlencode(null) 은 PHP 8.1+ deprecated(내장 함수 non-nullable 인자에 null) → 문자열로 변환. */
	return $url. '/login?loginid=' . rawurlencode((string)$email) . '&password=' . rawurlencode((string)$authtoken) . '&requestURI=' . rawurlencode($url . "/owner/setting/account");
}

function cancelInvite($userid,$clean = true) {
	global $database;
	requireModel('blog.user');
	if (POD::queryCell("SELECT count(*) FROM {$database['prefix']}Users WHERE userid = $userid AND lastlogin = 0") == 0)
		return false;
	if (POD::queryCell("SELECT count(*) FROM {$database['prefix']}Users WHERE userid = $userid AND host = ".getUserId()) === 0)
		return false;

	$blogidWithOwner = User::getOwnedBlogs($userid);
	foreach($blogidWithOwner as $blogids) {
		if(deleteBlog($blogids) === false) return false;
	}
	if($clean && !POD::queryAll("SELECT * FROM {$database['prefix']}Privileges WHERE userid = $userid")) {
		User::removePermanent($userid);
	}
	return true;
}

function changePassword($userid, $pwd, $prevPwd, $forceChange = false) {
	global $database;
	if (!strlen($pwd) || (!strlen($prevPwd) && !$forceChange))
		return false;
	if($forceChange === true) {
		// Auth::storePassword 로 저장(저장 방식 $service['passwordHash'], 기본 md5). 저장에 성공한 경우에만 인증 토큰 삭제
		if (!Auth::storePassword($userid, $pwd))
			return false;
		Auth::removeAuthToken($userid);	// 토큰·발급 시각 삭제(원본 DELETE ... LIMIT 1 은 MySQL 전용)
		return true;
	}
	/* [보안] 원본은 32자 입력을 저장된 해시 자체로도 받아(해시만 알면 비밀번호 변경 가능) 그 값을 escape 없이 SQL 에 넣었다 →
	   이전 비밀번호는 저장값을 읽어 Auth::verifyPassword 로만 확인 */
	$hash = POD::queryCell("SELECT password FROM {$database['prefix']}Users WHERE userid = " . intval($userid), 0, false);
	if (!Auth::verifyPassword($prevPwd, $hash))
		return false;
	if (!Auth::storePassword($userid, $pwd))
		return false;
	Auth::removeAuthToken($userid);	// 토큰·발급 시각 삭제(원본 DELETE ... LIMIT 1 은 MySQL 전용)
	return true;
}

function changeAPIKey($userid, $key) {
	if($key) return Setting::setUserSettingGlobal('APIKey',$key,$userid);
	else return Setting::removeUserSettingGlobal('APIKey',$userid);
}

function deleteBlog($blogid) {
	global $database;
	if($blogid == 1) return false;
	if (POD::execute("DELETE FROM {$database['prefix']}BlogSettings WHERE blogid = $blogid")
		&& POD::execute("DELETE FROM {$database['prefix']}SkinSettings WHERE blogid = $blogid")
		&& POD::execute("DELETE FROM {$database['prefix']}FeedSettings WHERE blogid = $blogid")
		&& POD::execute("DELETE FROM {$database['prefix']}FeedGroups WHERE blogid = $blogid")
		&& POD::execute("DELETE FROM {$database['prefix']}Privileges WHERE blogid = $blogid")
	)
	{
		return true;
	}
	return false;
}

function removeBlog($blogid) {
	global $database;
	if (getServiceSetting("defaultBlogId",1) == $blogid) {
		return false;
	}
	$tags = POD::queryColumn("SELECT DISTINCT tag FROM {$database['prefix']}TagRelations WHERE blogid = $blogid");
	$feeds = POD::queryColumn("SELECT DISTINCT feeds FROM {$database['prefix']}FeedGroupRelations WHERE blogid = $blogid");

	//Clear Tables
	POD::execute("DELETE FROM {$database['prefix']}Attachments WHERE blogid = $blogid");
	POD::execute("DELETE FROM {$database['prefix']}BlogSettings WHERE blogid = $blogid");
	POD::execute("DELETE FROM {$database['prefix']}BlogStatistics WHERE blogid = $blogid");
	POD::execute("DELETE FROM {$database['prefix']}Categories WHERE blogid = $blogid");
	POD::execute("DELETE FROM {$database['prefix']}Comments WHERE blogid = $blogid");
	POD::execute("DELETE FROM {$database['prefix']}CommentsNotified WHERE blogid = $blogid");
	POD::execute("DELETE FROM {$database['prefix']}CommentsNotifiedQueue WHERE blogid = $blogid");
	POD::execute("DELETE FROM {$database['prefix']}DailyStatistics WHERE blogid = $blogid");
	POD::execute("DELETE FROM {$database['prefix']}Entries WHERE blogid = $blogid");
	POD::execute("DELETE FROM {$database['prefix']}EntriesArchive WHERE blogid = $blogid");
//	POD::execute("DELETE FROM {$database['prefix']}FeedGroupRelations WHERE blogid = $blogid");
	POD::execute("DELETE FROM {$database['prefix']}FeedGroups WHERE blogid = $blogid");
	POD::execute("DELETE FROM {$database['prefix']}FeedReads WHERE blogid = $blogid");
	POD::execute("DELETE FROM {$database['prefix']}FeedStarred WHERE blogid = $blogid");
	POD::execute("DELETE FROM {$database['prefix']}FeedSettings WHERE blogid = $blogid");
	POD::execute("DELETE FROM {$database['prefix']}Filters WHERE blogid = $blogid");
	POD::execute("DELETE FROM {$database['prefix']}Links WHERE blogid = $blogid");
	POD::execute("DELETE FROM {$database['prefix']}LinkCategories WHERE blogid = $blogid");
	POD::execute("DELETE FROM {$database['prefix']}PageCacheLog WHERE blogid = $blogid");
	POD::execute("DELETE FROM {$database['prefix']}Plugins WHERE blogid = $blogid");
	POD::execute("DELETE FROM {$database['prefix']}RefererLogs WHERE blogid = $blogid");
	POD::execute("DELETE FROM {$database['prefix']}RefererStatistics WHERE blogid = $blogid");
	POD::execute("DELETE FROM {$database['prefix']}RemoteResponses WHERE blogid = $blogid");
	POD::execute("DELETE FROM {$database['prefix']}RemoteResponseLogs WHERE blogid = $blogid");
	POD::execute("DELETE FROM {$database['prefix']}SkinSettings WHERE blogid = $blogid");
	POD::execute("DELETE FROM {$database['prefix']}TagRelations WHERE blogid = $blogid");
	POD::execute("DELETE FROM {$database['prefix']}Privileges WHERE blogid = $blogid");
	POD::execute("DELETE FROM {$database['prefix']}XMLRPCPingSettings WHERE blogid = $blogid");

	//Delete Tags
	if (!empty($tags))
	{
		$tagliststr = implode(', ', $tags);	// Tag id used at deleted blog.
		$nottargets = POD::queryColumn("SELECT DISTINCT tag FROM {$database['prefix']}TagRelations WHERE tag in ( $tagliststr )");	// Tag id used at other blogs.
		if (!empty($nottargets)) {
			$nottargetstr	= implode(', ', $nottargets);
			POD::execute("DELETE FROM {$database['prefix']}Tags WHERE id IN ( $tagliststr ) AND id NOT IN ( $nottargetstr )");
		} else {
			POD::execute("DELETE FROM {$database['prefix']}Tags WHERE id IN ( $tagliststr ) ");
		}
	}
	//Delete Feeds
	if (!empty($feeds))
	{
		foreach($feeds as $feedId)
		{
			deleteFeed($blogid,$feedId);
		}
	}

	//Clear Plugin Database
	// TODO : encapsulate with 'value'
	$query = "SELECT name, value FROM {$database['prefix']}ServiceSettings WHERE name like 'Database\\_%'";
	// Textcube-NG: POD::queryAll() 은 쿼리가 실패하면 null 을 돌려준다 — foreach(null) 은 원본 5.6 부터 경고(PHP 8: foreach() argument must be of type array|object). 배열이 아니면 빈 배열로 둔다(원본 5.6 결과와 같음)(v3.222)
	$plugintablesraw = POD::queryAll($query);
	if (!is_array($plugintablesraw)) $plugintablesraw = array();
	foreach($plugintablesraw as $table) {
		$dbname = $database['prefix'] . substr($table['name'], 9);
		POD::execute("DELETE FROM {$database['prefix']}{$dbname} WHERE blogid = $blogid");
	}

	//Clear RSS Cache
	if (file_exists(__TEXTCUBE_CACHE_DIR__."/rss/$blogid.xml"))
		unlink(__TEXTCUBE_CACHE_DIR__."/rss/$blogid.xml");

	//Delete Attachments
	Path::removeFiles(Path::combine(ROOT, 'attach', $blogid));

	return true;
}

function setSmtpServer( $useCustomSMTP, $smtpHost, $smtpPort ) {
	if( empty($useCustomSMTP) ) {
		Setting::setServiceSettingGlobal( 'useCustomSMTP', 0 );
		return true;
	}
	if( !Setting::setServiceSettingGlobal( 'useCustomSMTP', 1 ) ) return false;
	if( !Setting::setServiceSettingGlobal( 'smtpHost', $smtpHost ) ) return false;
	if( !Setting::setServiceSettingGlobal( 'smtpPort', $smtpPort ) ) return false;
	return true;
}

function setDefaultBlog( $blogid ) {
	if(!Acl::check("group.creators")) {
		return false;
	}
	$result = Setting::setServiceSettingGlobal("defaultBlogId", $_GET['blogid']);
	return $result;
}
?>
