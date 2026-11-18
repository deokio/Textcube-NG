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

function RSSMessage($message) {
	$isPublic = (Setting::getBlogSettingGlobal('visibility',2) == 2 ? true : false);
	return ($isPublic ? $message : _text('비공개'));
}

// Textcube-NG: r3-27 R-435 — $noCache 가 true 이면 cache/rss·cache/atom 파일을 읽지도 쓰지도 않고 만든 피드 문자열을 돌려준다(등록되지 않은 Host 로 만든 URL 이 캐시에 굳지 않게). 기본값 false 는 기존 동작. $noCache 는 $mode 가 'rss' 또는 'atom' 일 때만 쓴다
function refreshFeed($blogid, $mode = 'both', $noCache = false) {
	// Textcube-NG: 아래 질의는 비공개 분류의 하위 분류 글도 뺀다(getPrivateCategoryExclusionQuery, c.visibility 는 분류 자신의 값만 본다)(r3-5)
	global $database, $serviceURL, $defaultURL, $blog, $service;
	// Textcube-NG: 피드(cache/rss·cache/atom)는 독자 기기와 관계없이 한 벌이므로 언제나 desktop 으로 만든다. 모바일 UA 요청이 만들면 stripHTML 된 본문(video·audio 등 빠짐)이 캐시에 남아 모든 구독자에게 나갔다. 원래 displaymode 는 아래 finally 에서 되돌린다(모든 return 포함, 본문은 들여쓰기를 바꾸지 않음) (r3-38 R-561)
	$_feedContext = Model_Context::getInstance();
	$_feedPrevDisplayMode = $_feedContext->getProperty('blog.displaymode', null);
	$_feedContext->setProperty('blog.displaymode', 'desktop');
	try {
	$channel = array();
	$channel = initializeRSSchannel($blogid);
	$result = POD::queryAll("SELECT 
			e.*, 
			c.name AS categoryName, 
			u.name AS author,
			u.loginid AS email
		FROM {$database['prefix']}Entries e 
		LEFT JOIN {$database['prefix']}Categories c
			ON e.blogid = c.blogid AND e.category = c.id
		LEFT JOIN {$database['prefix']}Users u
			ON e.userid = u.userid
		WHERE e.blogid = $blogid AND e.draft = 0 AND e.visibility >= ".($blog['publishEolinSyncOnRSS'] ? '2' : '3')." AND e.category >= 0 AND (c.visibility > 1 OR e.category = 0)".getPrivateCategoryExclusionQuery($blogid)."
		ORDER BY e.published 
		DESC LIMIT {$blog['entriesOnRSS']}");
	if (!$result)
		$result = array();
	$channel['items'] = getFeedItemByEntries($result);
	// RSS
	if($mode == 'both' || $mode == 'rss') {
		if ($noCache) return publishRSS($blogid, array('channel' => $channel));	// Textcube-NG: r3-27 R-435
		$path = __TEXTCUBE_CACHE_DIR__.'/rss';
		if (file_exists($path)) {
			if (!is_dir($path))
				return false;
		} else {
			if (!mkdir($path))
				return false;
			@chmod($path, 0777);
		}
		$path .= "/$blogid.xml";
		$fileHandle = fopen($path, 'w');
		$rss = array('channel' => $channel);
		if (fwrite($fileHandle, publishRSS($blogid, $rss))) {
			@chmod($path, 0666);
			fireEvent('refreshRSS',$rss);
			$result = true;
		} else $result = false;
		fclose($fileHandle);
	}
	// ATOM
	if($mode == 'both' || $mode == 'atom') {
		if ($noCache) return publishATOM($blogid, array('channel' => $channel));	// Textcube-NG: r3-27 R-435
		$path = __TEXTCUBE_CACHE_DIR__.'/atom';
		if (file_exists($path)) {
			if (!is_dir($path))
				return false;
		} else {
			if (!mkdir($path))
				return false;
			@chmod($path, 0777);
		}
		$path .= "/$blogid.xml";
		$fileHandle = fopen($path, 'w');
		$atom = array('channel' => $channel);
		if (fwrite($fileHandle, publishATOM($blogid, $atom))) {
			@chmod($path, 0666);
			fireEvent('refreshATOM',$atom);
			$result = true;
		} else $result = false;
		fclose($fileHandle);
	}
	if($result) return true;
	else return false;
	} finally {	// Textcube-NG: r3-38 R-561 — 시작할 때의 displaymode 로 되돌린다(없었으면 지움)
		if ($_feedPrevDisplayMode === null) $_feedContext->unsetProperty('blog.displaymode');
		else $_feedContext->setProperty('blog.displaymode', $_feedPrevDisplayMode);
	}
}

function initializeRSSchannel($blogid = null) {
	global $serviceURL, $defaultURL, $blogURL, $blog;

	if(empty($blogid)) $blogid = getBlogId();

	$channel = array();
	$channel['title'] = RSSMessage($blog['title']);
	$channel['link'] = "$defaultURL/";
	$channel['description'] = RSSMessage($blog['description']);
	$channel['language'] = $blog['language'];
	$channel['pubDate'] = Timestamp::getUNIXtime();
	$channel['generator'] = TEXTCUBE_NAME . ' ' . TEXTCUBE_VERSION . ' (NG ' . TEXTCUBE_NG_VERSION . ')';

	if ((Setting::getBlogSettingGlobal('visibility',2) == 2) && !empty($blog['logo']) && file_exists(__TEXTCUBE_ATTACH_DIR__."/$blogid/{$blog['logo']}")) {
		$logoInfo = getimagesize(__TEXTCUBE_ATTACH_DIR__."/$blogid/{$blog['logo']}");
		$channel['url'] = URL::toAbsolute($serviceURL)."/attach/".$blogid."/".$blog['logo'];	// Textcube-NG: 서비스 주소가 //host 이면 useSSL 기준 스킴을 붙인다(r3-34)
		$channel['width'] = $logoInfo[0];
		$channel['height'] = $logoInfo[1];
	}
	return $channel;
}

function getFeedItemByEntries($entries) {
	global $database, $serviceURL, $defaultURL, $blog, $service;
	$channelItems = array();
	foreach($entries as $row) {
		$entryURL = $defaultURL . '/' . ($blog['useSloganOnPost'] ? 'entry/' . rawurlencode($row['slogan']) : $row['id']);

		$_itemContext = Model_Context::getInstance();	// Textcube-NG: 피드 본문은 독자 기기와 관계없이 desktop 으로 만든다. 태그·검색·분류·작성자·보관함 피드 pageCache 키에는 displaymode 가 없어 모바일 UA 첫 요청의 stripHTML 본문이 모든 구독자에게 나갔다. refreshFeed(R-561)와 같은 방식으로 바꾸고 finally 에서 되돌린다 (r3-44 R-569)
		$_itemPrevDisplayMode = $_itemContext->getProperty('blog.displaymode', null);
		$_itemContext->setProperty('blog.displaymode', 'desktop');
		try {
			$content = getEntryContentView($row['blogid'], $row['id'], $row['content'], $row['contentformatter'], true, 'Post', true, true);
		} finally {	// Textcube-NG: r3-44 R-569 — 이 글을 만들기 전의 displaymode 로 되돌린다(없었으면 지움)
			if ($_itemPrevDisplayMode === null) $_itemContext->unsetProperty('blog.displaymode');
			else $_itemContext->setProperty('blog.displaymode', $_itemPrevDisplayMode);
		}
		$content = preg_replace('/<a href=("|\')(#[^\1]+)\1/i', '<a href=$1' . htmlspecialchars($entryURL) . '$2$1', $content);
 		if (!$blog['publishWholeOnRSS']) {
			$content .= "<p><strong><a href=\"" . htmlspecialchars($entryURL) . "\">" . _t('글 전체보기') . "</a></strong></p>";
 		} else {
			$content .= "<p><strong><a href=\"" . htmlspecialchars($entryURL) ."?commentInput=true#entry".$row['id']."WriteComment\">" . _t('댓글 쓰기') . "</a></strong></p>";
		}
		$row['repliesCount'] = $row['comments'] + $row['trackbacks'];
		if(!isset($row['author']) && isset($row['userid'])) {
			$row['author'] = User::getName($row['userid']);
		}
		if(!isset($row['categoryName'])) {
			if(isset($row['categoryLabel'])) {
				$row['categoryName'] = $row['categoryLabel'];
			} else {
				$row['categoryName'] = null;	
			}	
		}
		$item = array(
			'id' => $row['id'], 
			'title' => RSSMessage($row['title']), 
			'link' => $entryURL, 
			'categories' => array(), 'description' => RSSMessage($content), 
			'author' => RSSMessage($row['author']), 
			'pubDate' => $row['published'],
			'updDate' => $row['modified'],
			'comments' => $entryURL . '#entry' . $row['id'] . 'comment',
			'guid' => "$defaultURL/" . $row['id'],
			'replies' => array(
				'count' => $row['repliesCount'])
		);
		if(!empty($row['email'])) {
			$item['email'] = RSSMessage($row['email']);
		}
		if (isset($service['useNumericURLonRSS'])) {
			if ($service['useNumericURLonRSS']==true) {
				$item['link'] = $defaultURL."/".$row['id'];
			}
		}
		if (!empty($row['id'])) {
			$sql = "SELECT name, size, mime FROM {$database['prefix']}Attachments WHERE parent= {$row['id']} AND blogid = {$row['blogid']} AND enclosure = 1";
			$attaches = POD::queryRow($sql);
			if (!empty($attaches) && count($attaches) > 0) {
				$item['enclosure'] = array('url' => URL::toAbsolute($serviceURL) . "/attach/{$row['blogid']}/{$attaches['name']}", 'length' => $attaches['size'], 'type' => $attaches['mime']);	// Textcube-NG: 이 함수에는 $blogid 가 없어 주소가 /attach//<이름> 이 되고 Undefined variable 이 났다. 이 글의 blogid 를 쓴다(r3-29 R-492) · 서비스 주소가 //host 이면 useSSL 기준 스킴을 붙인다(r3-34)
			}
		}
		array_push($item['categories'], $row['categoryName']);
		$tag_result = POD::queryColumn("SELECT name 
				FROM {$database['prefix']}Tags, 
					{$database['prefix']}TagRelations 
				WHERE id = tag 
					AND entry = {$row['id']}
					AND blogid = {$row['blogid']}
				ORDER BY name");
		foreach($tag_result as $tag) {
			array_push($item['categories'], $tag);
		}
		array_push($channelItems, $item);
	}
	return $channelItems;
}

function getFeedItemByLines($lines) {
	global $database, $serviceURL, $defaultURL, $blog, $service;
	$channelItems = array();
	foreach($lines as $row) {
		$entryURL = $defaultURL . '/line#' . ($row['id']);
		$content = $row['content'];
		$item = array(
			'id' => $row['id'], 
			'title' => RSSMessage(Timestamp::format5($row['created'])), 
			'link' => $entryURL, 
			'author' => RSSMessage($row['author']), 
			'categories' => array(), 
			'description' => RSSMessage($content), 
			'pubDate' => $row['created'],
			'updDate' => $row['created'],
			'comments' => $entryURL,	// Textcube-NG: 빈 <comments> 방지(r3-30 R-506)
			'guid' => $entryURL
		);
		array_push($channelItems, $item);
	}
	return $channelItems;
}

function getResponseFeedTotal($blogid, $mode = 'rss') {
	global $database, $serviceURL, $defaultURL, $blogURL, $blog, $service;
	if(empty($blogid)) $blogid = getBlogId();
	$channel = initializeRSSchannel($blogid);
	$channel['title'] = $blog['title']. ': '._text('최근 댓글/트랙백 목록');

	$recentComment = getCommentFeedTotal($blogid,true,$mode);
	$recentTrackback = getTrackbackFeedTotal($blogid,true,$mode);
	$merged = array_merge($recentComment, $recentTrackback);
	$channel['items'] = $merged;
	$rss = array('channel' => $channel);
	if($mode == 'rss') return publishRSS($blogid, $rss);
	else if($mode == 'atom') return publishATOM($blogid, $rss);
	return false;
}

function getResponseFeedByEntryId($blogid, $entryId, $mode = 'rss') {
	global $database, $serviceURL, $defaultURL, $blogURL, $blog, $service;
	
	if(empty($blogid)) $blogid = getBlogId();

	$entry = POD::queryRow("SELECT slogan, visibility, category, title FROM {$database['prefix']}Entries WHERE blogid = $blogid AND id = $entryId");
	if(empty($entry)) return false;
	if($entry['visibility'] < 2) return false;
	if(in_array($entry['category'], getCategoryVisibilityList($blogid, 'private'))) return false;
	$channel = array();

	$channel = initializeRSSchannel($blogid);
	$channel['title'] = RSSMessage($blog['title']. ': '._textf('%1에 달린 최근 댓글/트랙백 목록',$entry['title']));

	$recentComment = getCommentFeedByEntryId($blogid,$entryId,true,$mode);
	$recentTrackback = getTrackbackFeedByEntryId($blogid,$entryId,true,$mode);
	$merged = array_merge($recentComment, $recentTrackback);
	$channel['items'] = $merged;
	
	$rss = array('channel' => $channel);
	if($mode == 'rss') return publishRSS($blogid, $rss);
	else if($mode == 'atom') return publishATOM($blogid, $rss);
	return false;
}

function getCommentFeedTotal($blogid, $rawMode = false, $mode = 'rss') {
	global $database, $serviceURL, $defaultURL, $blogURL, $blog, $service;
	$channel = initializeRSSchannel($blogid);
	$channel['title'] = $blog['title']. ': '._text('최근 댓글 목록');
	
	$result = getRecentComments($blogid, Setting::getBlogSettingGlobal('commentsOnRSS',20), false, true);
	if (!$result)
		$result = array();

	$channel['items'] = array();
	foreach($result as $row) {
		$commentURL = $defaultURL."/".$row['entry']."#comment";
		$content = htmlspecialchars($row['comment']);
		$item = array(
			'id' => $row['id'], 
			'title' => RSSMessage(UTF8::lessen($row['title'],30).' : '._textf('%1님의 댓글',$row['name'])), 
			'link' => $commentURL.$row['id'], 
			'categories' => array(), 'description' => RSSMessage($content), 
			'author' => RSSMessage($row['name']), 
			'pubDate' => $row['written'],
			'comments' => $commentURL,
			'guid' => $commentURL.$row['id']
		);
		if($row['secret']) $item['title'] = $item['author'] = $item['description'] = _text('비밀 댓글입니다');
		array_push($channel['items'], $item);
	}
	if($rawMode == true) return $channel['items'];
	$rss = array('channel' => $channel);
	if($mode == 'rss') return publishRSS($blogid, $rss);
	else if($mode == 'atom') return publishATOM($blogid, $rss);
	return false;
}

function getCommentFeedByEntryId($blogid, $entryId, $rawMode = false, $mode = 'rss') {
	global $database, $serviceURL, $defaultURL, $blogURL, $blog, $service;
	
	if(empty($blogid)) $blogid = getBlogId();

	$entry = POD::queryRow("SELECT slogan, visibility, title, category FROM {$database['prefix']}Entries WHERE blogid = $blogid AND id = $entryId");
	if(empty($entry)) return false;
	if($entry['visibility'] < 2) return false;
	if(in_array($entry['category'], getCategoryVisibilityList($blogid, 'private'))) return false;

	$channel = initializeRSSchannel($blogid);
	$channel['title'] = RSSMessage($blog['title']. ': '._textf('%1 에 달린 댓글',$entry['title']));
	if($blog['useSloganOnPost']) {
		$channel['link'] = $defaultURL."/entry/".URL::encode($entry['slogan'],true);
	} else {
		$channel['link'] = $defaultURL."/".$entryId;
	}
	$result = POD::queryAll("SELECT *
		FROM {$database['prefix']}Comments
		WHERE blogid = ".$blogid." 
			AND entry = ".$entryId."
			AND isfiltered = 0");
	if (!$result)
		$result = array();

	$channel['items'] = array();
	foreach($result as $row) {
		$commentURL = $channel['link']."#comment";
		$content = htmlspecialchars($row['comment']);
		$item = array(
			'id' => $row['id'], 
			'title' => RSSMessage(_textf('%1님의 댓글',$row['name'] )), 
			'link' => $commentURL.$row['id'], 
			'categories' => array(), 'description' => RSSMessage($content), 
			'author' => RSSMessage($row['name']), 
			'pubDate' => $row['written'],
			'comments' => $commentURL,
			'guid' => $commentURL.$row['id']
		);
		if($row['secret']) $item['title'] = $item['author'] = $item['description'] = _text('비밀 댓글입니다');
		array_push($channel['items'], $item);
	}
	if($rawMode == true) return $channel['items'];
	$rss = array('channel' => $channel);
	if($mode == 'rss') return publishRSS($blogid, $rss);
	else if($mode == 'atom') return publishATOM($blogid, $rss);
	return false;
}


function getTrackbackFeedTotal($blogid, $rawMode = false, $mode = 'rss') {
	global $database, $serviceURL, $defaultURL, $blogURL, $blog, $service;

	if(empty($blogid)) $blogid = getBlogId();
	$channel = initializeRSSchannel($blogid);
	$channel['title'] = RSSMessage($blog['title']. ': '._text('최근 트랙백 목록'));
	$result = getRecentTrackbacks($blogid, Setting::getBlogSettingGlobal('commentsOnRSS',20), true);
	if (!$result)
		$result = array();

	$channel['items'] = array();
	foreach($result as $row) {
		$trackbackURL = $defaultURL."/".$row['entry']."#trackback";
		$content = htmlspecialchars($row['excerpt']);
		$item = array(
			'id' => $row['id'], 
			'title' => RSSMessage($row['subject']), 
			'link' => $trackbackURL.$row['id'], 
			'categories' => array(), 'description' => RSSMessage($content), 
			'author' => RSSMessage($row['site']), // Textcube-NG: r3-30 R-511 publishRSS·publishATOM 이 출력 때 한 번 escape 한다(원본은 여기서도 escape 해 두 번)
			'pubDate' => $row['written'],
			'comments' => $trackbackURL,
			'guid' => $trackbackURL.$row['id']
		);
		array_push($channel['items'], $item);
	}
	if($rawMode == true) return $channel['items'];
	$rss = array('channel' => $channel);
	if($mode == 'rss') return publishRSS($blogid, $rss);
	else if($mode == 'atom') return publishATOM($blogid, $rss);
	return false;
}

function getTrackbackFeedByEntryId($blogid, $entryId, $rawMode = false, $mode = 'rss') {
	global $database, $serviceURL, $defaultURL, $blogURL, $blog, $service;

	if(empty($blogid)) $blogid = getBlogId();

	$entry = POD::queryRow("SELECT slogan, visibility, category FROM {$database['prefix']}Entries WHERE blogid = $blogid AND id = $entryId");
	if(empty($entry)) return false;
	if($entry['visibility'] < 2) return false;
	if(in_array($entry['category'], getCategoryVisibilityList($blogid, 'private'))) return false;
	$channel = array();

	$channel = initializeRSSchannel($blogid);
	$channel['title'] = RSSMessage($blog['title']. ': '._textf('%1 에 달린 트랙백',$entry['slogan']));
	if($blog['useSloganOnPost']) {
		$channel['link'] = $defaultURL."/entry/".URL::encode($entry['slogan'],true);
	} else {
		$channel['link'] = $defaultURL."/".$entryId;
	}
	// Textcube-NG: RemoteResponses 에는 type 컬럼이 없음, responsetype 으로 고침(r3-30 R-509)
	$result = POD::queryAll("SELECT * 
		FROM {$database['prefix']}RemoteResponses
		WHERE blogid = ".$blogid." 
			AND entry = ".$entryId."
			AND isfiltered = 0
			AND responsetype = 'trackback'");
	if (!$result)
		$result = array();

	$channel['items'] = array();

	foreach($result as $row) {
		$trackbackURL = $channel['link']."#trackback";
		$content = htmlspecialchars($row['excerpt']);
		$item = array(
			'id' => $row['id'], 
			'title' => RSSMessage($row['subject']), 
			'link' => $trackbackURL.$row['id'], 
			'categories' => array(), 'description' => RSSMessage($content), 
			'author' => RSSMessage($row['site']), // Textcube-NG: r3-30 R-511 publishRSS·publishATOM 이 출력 때 한 번 escape 한다(원본은 여기서도 escape 해 두 번)
			'pubDate' => $row['written'],
			'comments' => $trackbackURL,
			'guid' => $trackbackURL.$row['id']
		);
		array_push($channel['items'], $item);
	}
	if($rawMode == true) return $channel['items'];
	$rss = array('channel' => $channel);
	if($mode == 'rss') return publishRSS($blogid, $rss);
	else if($mode == 'atom') return publishATOM($blogid, $rss);
	return false;
}

function getCommentNotifiedFeedTotal($blogid, $mode = 'rss') {
	global $database, $serviceURL, $defaultURL, $blogURL, $blog, $service;

	if(empty($blogid)) $blogid = getBlogId();
	$channel = initializeRSSchannel($blogid);
	$channel['title'] = RSSMessage($blog['title']. ': '._text('최근 댓글 알리미 목록'));
	$mergedComments = array();
	list($comments, $paging) = getCommentsNotifiedWithPagingForOwner($blogid, '', '', '', '', 1, 20);
	for ($i = 0; $i < count($comments); $i++) {
		array_push($mergedComments, $comments[$i]);
		$result = getCommentCommentsNotified($comments[$i]['id']);
		for ($j = 0; $j < count($result); $j++) {
			array_push($mergedComments, $result[$j]);
		}
	}	
	
	if (!$mergedComments)
		$mergedComments = array();

	$channel['items'] = array();
	foreach($mergedComments as $row) {
		$item = array(
			'id' => $row['id'], 
			'title' => RSSMessage($row['entrytitle']), 
			'link' => $row['url'], 
			'categories' => array(), 
			'description' => RSSMessage(htmlspecialchars($row['comment'])), 
			'author' => RSSMessage($row['name']), // Textcube-NG: r3-30 R-511 (위와 같음)
			'pubDate' => $row['written'],
			'comments' => $row['entryurl'],
			'guid' => $row['url']
		);
		array_push($channel['items'], $item);
	}
	$rss = array('channel' => $channel);
	if($mode == 'rss') return publishRSS($blogid, $rss);
	else if($mode == 'atom') return publishATOM($blogid, $rss);
	return false;
}

function getTagFeedByTagId($blogid, $tagId, $mode = 'rss', $tagTitle = null) {
	// Textcube-NG: 아래 질의는 비공개 분류의 하위 분류 글도 뺀다(getPrivateCategoryExclusionQuery, c.visibility 는 분류 자신의 값만 본다)(r3-5)

	global $database, $serviceURL, $defaultURL, $blog, $service;
	$channel = array();
	$channel = initializeRSSchannel($blogid);
	$entries = POD::queryAll("SELECT 
			e.*, 
			c.name AS categoryName,
			u.name AS author,
			u.loginid AS email
		FROM {$database['prefix']}Entries e
		LEFT JOIN {$database['prefix']}Categories c
			ON e.blogid = c.blogid AND e.category = c.id
		LEFT JOIN {$database['prefix']}Users u
			ON e.userid = u.userid
		LEFT JOIN {$database['prefix']}TagRelations t 
			ON e.id = t.entry AND e.blogid = t.blogid 
		WHERE e.blogid = $blogid AND e.draft = 0 AND e.visibility >= ".($blog['publishEolinSyncOnRSS'] ? '2' : '3')." AND (c.visibility > 1 OR e.category = 0)".getPrivateCategoryExclusionQuery($blogid)." AND t.tag = $tagId
		ORDER BY e.published 
		DESC LIMIT {$blog['entriesOnRSS']}");
	if (!$entries)
		$entries = array();

	$channel['items'] = getFeedItemByEntries($entries);
	if(!is_null($tagTitle)) {
		$channel['title'] = RSSMessage($blog['title']. ': '._textf('%1 태그 글 목록',htmlspecialchars($tagTitle)));
	}
	$rss = array('channel' => $channel);

	if($mode == 'rss') return publishRSS($blogid, $rss);
	else if($mode == 'atom') return publishATOM($blogid, $rss);
	return false;
}

function getSearchFeedByKeyword($blogid, $search, $mode = 'rss', $title = null) {
	// Textcube-NG: 아래 질의는 비공개 분류의 하위 분류 글도 뺀다(getPrivateCategoryExclusionQuery, c.visibility 는 분류 자신의 값만 본다)(r3-5)

	global $database, $serviceURL, $defaultURL, $blog, $service;
	$channel = array();
	$channel = initializeRSSchannel($blogid);
	$search = escapeSearchString($search);
	$entries = POD::queryAll("SELECT 
			e.*, 
			c.name AS categoryName,
			u.name AS author,
			u.loginid AS email
		FROM {$database['prefix']}Entries e
		LEFT JOIN {$database['prefix']}Categories c
			ON e.blogid = c.blogid AND e.category = c.id
		LEFT JOIN {$database['prefix']}Users u
			ON e.userid = u.userid
		WHERE e.blogid = $blogid AND e.draft = 0 AND e.visibility >= ".($blog['publishEolinSyncOnRSS'] ? '2' : '3')." AND (c.visibility > 1 OR e.category = 0)".getPrivateCategoryExclusionQuery($blogid)." AND (e.title LIKE '%$search%' OR e.content LIKE '%$search%') 
		ORDER BY e.published 
		DESC LIMIT {$blog['entriesOnRSS']}");
	if (!$entries)
		$entries = array();

	$channel['items'] = getFeedItemByEntries($entries);
	if(!is_null($title)) {
		$channel['title'] = RSSMessage($blog['title']. ': '._textf('%1 이 포함된 글 목록',htmlspecialchars($title)));
	}
	$rss = array('channel' => $channel);

	if($mode == 'rss') return publishRSS($blogid, $rss);
	else if($mode == 'atom') return publishATOM($blogid, $rss);
	return false;
}

function getCategoryFeedByCategoryId($blogid, $categoryIds, $mode = 'rss', $categoryTitle = null) {

	global $database, $serviceURL, $defaultURL, $blog, $service;
	$channel = array();
	$channel = initializeRSSchannel($blogid);
	$entries = POD::queryAll("SELECT 
			e.*, 
			c.name AS categoryName, 
			u.name AS author,
			u.loginid AS email
		FROM {$database['prefix']}Entries e 
		LEFT JOIN {$database['prefix']}Categories c
			ON e.blogid = c.blogid AND e.category = c.id
		LEFT JOIN {$database['prefix']}Users u
			ON e.userid = u.userid
		WHERE e.blogid = $blogid AND e.draft = 0 AND e.visibility >= ".($blog['publishEolinSyncOnRSS'] ? '2' : '3')." AND e.category IN (".implode(',',$categoryIds).")
		ORDER BY e.published 
		DESC LIMIT {$blog['entriesOnRSS']}");
	if (!$entries)
		$entries = array();
	$channel['items'] = getFeedItemByEntries($entries);
	if(!is_null($categoryTitle)) {
		$channel['title'] = RSSMessage($blog['title']. ': '._textf('%1 카테고리 글 목록',htmlspecialchars($categoryTitle)));
	}
	$rss = array('channel' => $channel);

	if($mode == 'rss') return publishRSS($blogid, $rss);
	else if($mode == 'atom') return publishATOM($blogid, $rss);
	return false;
}

function getFeedWithEntries($blogid, $entries, $title = null, $mode = 'rss'){
	$context = Model_Context::getInstance();
	$channel = array();
	$channel = initializeRSSchannel($blogid);
	if (!$entries)
		$entries = array();
	$channel['items'] = getFeedItemByEntries($entries);
	if(!is_null($title)) {// TODO : change blog.title to support other blogs
		$channel['title'] = RSSMessage($context->getProperty('blog.title'). ': '.htmlspecialchars($title));
	}
	$rss = array('channel' => $channel);
	if($mode == 'rss') return publishRSS($blogid, $rss);
	else if($mode == 'atom') return publishATOM($blogid, $rss);
	return false;
}

function getLinesFeed($blogid, $category = 'public', $mode = 'atom') {
	global $blog;
	$channel = array();
	$channel = initializeRSSchannel($blogid);	
	$lineobj = Model_Line::getInstance();
	$lineobj->reset();
	$lineobj->setFilter(array('created','bigger',Timestamp::getUNIXTime()-86400));
	$lineobj->setFilter(array('blogid','equals',$blogid));
	$lineobj->setFilter(array('category','equals',$category,true));
	$lines = $lineobj->get();
	
	$channel['items'] = getFeedItemByLines($lines);
	$channel['title'] = RSSMessage($blog['title']. ': '._text('Lines'));

	$rss = array('channel' => $channel);

	if($mode == 'rss') return publishRSS($blogid, $rss);
	else if($mode == 'atom') return publishATOM($blogid, $rss);
	return false;
}

function publishRSS($blogid, $data) {
	// Textcube-NG: link·language·generator·atom:link·image url/link·author email·guid·comments·enclosure 도 htmlspecialchars(…, ENT_QUOTES) 로 내보낸다. 원본은 그대로 내보내 댓글 알림 피드의 외부 URL 등으로 XML 이 깨지거나 요소가 끼어들 수 있었다. comments 는 줄 피드(getFeedItemByLines)에 키가 없고 image url 은 채널에 없을 수 있어 ?? '' 를 붙인다(r3-29 R-493)
	$context = Model_Context::getInstance();
	$blogid = getBlogId();
	ob_start();
	echo '<?xml version="1.0" encoding="UTF-8"?>', CRLF;
	echo '<rss version="2.0" xmlns:atom="http://www.w3.org/2005/Atom">', CRLF;
	echo '	<channel>', CRLF;
	echo '		<title>', htmlspecialchars($data['channel']['title'], ENT_QUOTES), '</title>', CRLF;
	echo '		<link>', htmlspecialchars($data['channel']['link'], ENT_QUOTES), '</link>', CRLF;
	echo '		<description>', htmlspecialchars($data['channel']['description'], ENT_QUOTES), '</description>', CRLF;
	echo '		<language>', htmlspecialchars($data['channel']['language'], ENT_QUOTES), '</language>', CRLF;
	echo '		<pubDate>', Timestamp::getRFC1123($data['channel']['pubDate']), '</pubDate>', CRLF;
	echo '		<generator>', htmlspecialchars($data['channel']['generator'], ENT_QUOTES), '</generator>', CRLF;
	if(!empty($data['channel']['feed_link'])) {
		echo '		<atom:link href="', htmlspecialchars($data['channel']['feed_link'], ENT_QUOTES) , '" rel="self" type="application/rss+xml" />', CRLF;
	}
	if ($context->getProperty('blog.logo') && file_exists(__TEXTCUBE_ATTACH_DIR__."/$blogid/{$context->getProperty('blog.logo')}")) {
		echo '		<image>', CRLF;
		echo '		<title>', htmlspecialchars($data['channel']['title'], ENT_QUOTES), '</title>', CRLF;
		echo '		<url>', htmlspecialchars($data['channel']['url'] ?? '', ENT_QUOTES), '</url>', CRLF;
		echo '		<link>', htmlspecialchars($data['channel']['link'], ENT_QUOTES), '</link>', CRLF;
		echo '		<width>', $data['channel']['width'], '</width>', CRLF;
		echo '		<height>', $data['channel']['height'], '</height>', CRLF;
		echo '		<description>', htmlspecialchars($data['channel']['description'], ENT_QUOTES), '</description>', CRLF;
		echo '		</image>', CRLF;
	}

	foreach ($data['channel']['items'] as $item) {
		echo '		<item>', CRLF;
		echo '			<title>', htmlspecialchars($item['title'], ENT_QUOTES), '</title>', CRLF;
		echo '			<link>', htmlspecialchars($item['link'], ENT_QUOTES), '</link>', CRLF;
		echo '			<description>', htmlspecialchars($item['description'], ENT_QUOTES), '</description>', CRLF;
		foreach ($item['categories'] as $category) {
			if ($category = trim($category))
				echo '			<category>', htmlspecialchars($category, ENT_QUOTES), '</category>', CRLF; 
		}
		if(!empty($item['email'])) {
			echo '			<author>',htmlspecialchars($item['email'], ENT_QUOTES),' (', htmlspecialchars($item['author'], ENT_QUOTES), ')</author>', CRLF;
		} else {
			echo '			<author>', htmlspecialchars($item['author'], ENT_QUOTES), '</author>', CRLF;
		}
		echo '			<guid>', htmlspecialchars($item['guid'], ENT_QUOTES), '</guid>',CRLF;
		echo '			<comments>', htmlspecialchars($item['comments'] ?? '', ENT_QUOTES) , '</comments>',CRLF;
		echo '			<pubDate>', Timestamp::getRFC1123($item['pubDate']), '</pubDate>', CRLF;
		if (!empty($item['enclosure'])) {
			echo '			<enclosure url="', htmlspecialchars($item['enclosure']['url'], ENT_QUOTES), '" length="', htmlspecialchars($item['enclosure']['length'] ?? '', ENT_QUOTES), '" type="', htmlspecialchars($item['enclosure']['type'], ENT_QUOTES), '" />', CRLF;
		}
		echo '		</item>', CRLF;
	}
	echo '	</channel>', CRLF;
	echo '</rss>', CRLF;
	$rss = ob_get_contents();
	ob_end_clean();
	return $rss;
}

function clearFeed() {
	if (file_exists(__TEXTCUBE_CACHE_DIR__."/rss/".getBlogId().".xml"))
		@unlink(__TEXTCUBE_CACHE_DIR__."/rss/".getBlogId().".xml");
	if (file_exists(__TEXTCUBE_CACHE_DIR__."/atom/".getBlogId().".xml"))
		@unlink(__TEXTCUBE_CACHE_DIR__."/atom/".getBlogId().".xml");
}


function publishATOM($blogid, $data) {
	global $blog;
	$blogid = getBlogId();
	ob_start();
	echo '<?xml version="1.0" encoding="UTF-8"?>', CRLF;
	echo '<feed xmlns="http://www.w3.org/2005/Atom" xmlns:thr="http://purl.org/syndication/thread/1.0">', CRLF;
	echo '  <title type="html">', htmlspecialchars($data['channel']['title'], ENT_QUOTES), '</title>', CRLF;
	// Textcube-NG: link·language·generator 출력을 escape(r3-30 R-507, R-493 과 같은 방식)
	echo '  <id>', htmlspecialchars($data['channel']['link'], ENT_QUOTES), '</id>', CRLF;
	echo '  <link rel="alternate" type="text/html" hreflang="', htmlspecialchars($data['channel']['language'], ENT_QUOTES) ,'" href="', htmlspecialchars($data['channel']['link'], ENT_QUOTES) , '" />', CRLF;
	echo '  <subtitle type="html">', htmlspecialchars($data['channel']['description'], ENT_QUOTES), '</subtitle>', CRLF;
	echo '  <updated>', Timestamp::getISO8601($data['channel']['pubDate']), '</updated>', CRLF;
	echo '  <generator>', htmlspecialchars($data['channel']['generator'], ENT_QUOTES), '</generator>', CRLF;

	foreach ($data['channel']['items'] as $item) {
		echo '  <entry>', CRLF;
		echo '    <title type="html">', htmlspecialchars($item['title'], ENT_QUOTES), '</title>', CRLF;
		echo '    <link rel="alternate" type="text/html" href="', htmlspecialchars($item['link'], ENT_QUOTES), '" />', CRLF;
		if(isset($item['replies'])) {
			// Textcube-NG: r3-31 R-516 — replies 링크의 글 번호·응답 수를 정수로 낸다(값은 DB 정수 열이지만 출력 쪽에서도 막음)
			echo '    <link rel="replies" type="application/atom+xml" href="', htmlspecialchars($data['channel']['link'], ENT_QUOTES), 'atom/response/', intval($item['id']), '" thr:count="', intval($item['replies']['count']) ,'"/>', CRLF;
		}
		foreach ($item['categories'] as $category) {
			if ($category = trim($category))
				echo '    <category term="', htmlspecialchars($category, ENT_QUOTES), '" />', CRLF; 
		}
		if(isset($item['author'])) {
			echo '    <author>', CRLF;
			echo '      <name>', htmlspecialchars($item['author'], ENT_QUOTES), '</name>', CRLF;
			echo '    </author>', CRLF;
		}
		echo '    <id>', htmlspecialchars($item['link'], ENT_QUOTES) ,'</id>', CRLF;
		if(isset($item['updDate']))
			echo '    <updated>', Timestamp::getISO8601($item['updDate']), '</updated>', CRLF;
		echo '    <published>', Timestamp::getISO8601($item['pubDate']), '</published>', CRLF;
/*		if (!empty($item['enclosure'])) {
			echo '			<enclosure url="', $item['enclosure']['url'], '" length="', $item['enclosure']['length'], '" type="', $item['enclosure']['type'], '" />', CRLF;
		}*/
		echo '    <summary type="html">', htmlspecialchars($item['description'], ENT_QUOTES), '</summary>', CRLF;

		echo '  </entry>', CRLF;
	}
	echo '</feed>', CRLF;
	$atom = ob_get_contents();
	ob_end_clean();
	return $atom;
}
?>
