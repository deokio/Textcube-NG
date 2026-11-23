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
	'GET' => array(
		'includeFileContents' => array(array('true', 'false'))
	)
);
require ROOT . '/library/preprocessor.php';
requireStrictRoute();
/* [보안] 원본은 이 핸들러에서 권한을 확인하지 않고 ACL 표에 의존했는데 표에 /owner/data 가 없어 전 구성원에게 허용됐다 →
   표 보완(framework/boot/30-Auth.php)과 함께 핸들러에서도 관리자 이상 확인 */
requirePrivilege('group.administrators');
set_time_limit(0);
$includeFileContents = Validator::getBool(@$_GET['includeFileContents']);
$writer = new OutputWriter();
if (defined('__TEXTCUBE_BACKUP__')) {
	if (!file_exists(__TEXTCUBE_CACHE_DIR__.'/backup')) {
		mkdir(__TEXTCUBE_CACHE_DIR__.'/backup');
		// [보안] 백업(블로그 설정·플러그인 설정·사용자 설정·댓글 비밀번호 해시 등 포함) 디렉토리는 웹 서버 사용자만 (원본 0777)
		@chmod(__TEXTCUBE_CACHE_DIR__.'/backup', 0700);
	}
	if (!is_dir(__TEXTCUBE_CACHE_DIR__.'/backup')) {
		exit;
	}
	if ($writer->openFile(__TEXTCUBE_CACHE_DIR__."/backup/$blogid.xml")) {
		// [보안] 쓰는 동안에도 웹 서버 사용자만 읽고 쓰도록(원본은 끝난 뒤 0666 = 누구나 읽기·쓰기)
		@chmod(__TEXTCUBE_CACHE_DIR__."/backup/$blogid.xml", 0600);
	} else {
		exit;
	}
} else {
	if ($writer->openStdout()) {
		header('Content-Disposition: attachment; filename="Textcube-Backup-' . getBlogName($blogid) ."-". Timestamp::getDate() . '.xml"');
		header('Content-Description: Textcube Backup Data');
		header('Content-Transfer-Encoding: binary');
		header('Content-Type: application/xml');
	} else {
		exit;
	}
}
$newlineStyle = (!is_null(getServiceSetting('newlineStyle')) ? ' format="'.getServiceSetting('newlineStyle').'"' : '');
$writer->write('<?xml version="1.0" encoding="utf-8" ?>');
$writer->write('<blog type="tattertools/1.1" migrational="false">');
$setting = new BlogSetting();
if ($setting->load()) {
	$setting->escape();
	$writer->write('<setting>' . '<name>' . $setting->name . '</name>' . '<secondaryDomain>' . $setting->secondaryDomain . '</secondaryDomain>' . '<defaultDomain>' . Validator::getBit($setting->defaultDomain) . '</defaultDomain>' . '<title>' . $setting->title . '</title>' . '<description>' . UTF8::correct($setting->description) . '</description>' . '<banner><name>' . $setting->banner . '</name>');
	if ($includeFileContents && file_exists(__TEXTCUBE_ATTACH_DIR__."/$blogid/{$setting->banner}")) {
		$writer->write('<content>');
		if (!empty($setting->banner) && file_exists(__TEXTCUBE_ATTACH_DIR__."/$blogid/" . $setting->banner))
			Base64Stream::encode(__TEXTCUBE_ATTACH_DIR__."/$blogid/{$setting->banner}", $writer);
		$writer->write('</content>');
	}
	$writer->write('</banner>' . '<useSloganOnPost>' . Validator::getBit($setting->useSloganOnPost) . '</useSloganOnPost>' . '<postsOnPage>' . $setting->postsOnPage . '</postsOnPage>' . '<postsOnList>' . $setting->postsOnList . '</postsOnList>' . '<postsOnFeed>' . $setting->postsOnFeed . '</postsOnFeed>' . '<publishWholeOnFeed>' . Validator::getBit($setting->publishWholeOnFeed) . '</publishWholeOnFeed>' . '<acceptGuestComment>' . Validator::getBit($setting->acceptGuestComment) . '</acceptGuestComment>' . '<acceptcommentOnGuestComment>' . Validator::getBit($setting->acceptcommentOnGuestComment) . '</acceptcommentOnGuestComment>' . '<language>' . $setting->language . '</language>' . '<timezone>' . $setting->timezone . '</timezone>' . '</setting>');
	$writer->write(CRLF);
}
$category = new Category();
if ($category->open()) {
	do {
		if($category->id != 0) {
			$category->escape();
			// Textcube-NG: R-592 — 하위 분류를 깊이만큼 재귀로 쓰고(사용자 결정 ⑩ 안 A), 분류마다 공개 설정(visibility)·body id 를 더한다
			//   (원본은 2단까지만 썼고 공개 설정·body id 를 쓰지 않아, 복원하면 비공개 분류가 공개로 돌아갔다)
			$writer->write('<category>' . '<name>' . $category->name . '</name>' . '<priority>' . $category->priority . '</priority>' . exportCategoryExtra($category));
			exportCategoryChildren($writer, $category, array((string)$category->id => true), 2);
			$writer->write('</category>');
			$writer->write(CRLF);
		} else {
			$category->escape();
			$writer->write('<category>' . '<name>' . $category->name . '</name>' . '<priority>' . $category->priority . '</priority>');
			$writer->write('<root>1</root>');
			$writer->write('</category>');
			$writer->write(CRLF);
		}
	} while ($category->shift());
	$category->close();
}

function exportCategoryExtra($category) {
	$extra = '<visibility>' . ((isset($category->visibility) && $category->visibility !== null && $category->visibility < 2) ? 1 : 2) . '</visibility>';
	if (isset($category->bodyid) && $category->bodyid !== '' && $category->bodyid !== 'tt-body-category')
		$extra .= '<bodyId>' . Validator::escapeXML($category->bodyid) . '</bodyId>';
	return $extra;
}

function exportCategoryChildren($writer, $parent, $visited, $depth) {
	if ($depth > 64)
		return;
	if ($childCategory = $parent->getChildren()) {
		do {
			if (isset($visited[(string)$childCategory->id]))
				continue;
			$childVisited = $visited;
			$childVisited[(string)$childCategory->id] = true;
			$childCategory->escape();
			$writer->write('<category>' . '<name>' . $childCategory->name . '</name>' . '<priority>' . $childCategory->priority . '</priority>' . exportCategoryExtra($childCategory));
			exportCategoryChildren($writer, $childCategory, $childVisited, $depth + 1);
			$writer->write('</category>');
		} while ($childCategory->shift());
		$childCategory->close();
	}
}
$post = new Post();
if ($post->open('', '*', 'published, id')) {
	do {
		$writer->write('<post slogan="' . htmlspecialchars($post->slogan, ENT_QUOTES | ENT_SUBSTITUTE) . '"' . $newlineStyle . '>' .
			'<id>' . $post->id . '</id>');
		if ($post->visibility == 'appointed') { // for backward compatibility
			$writer->write('<appointed>true</appointed>');
			$writer->write('<visibility>private</visibility>');
		} else {
			$writer->write('<appointed>false</appointed>');
			$writer->write('<visibility>' . $post->visibility . '</visibility>');
		}
		$writer->write('<starred>' . $post->starred . '</starred>' .
			'<title>' . htmlspecialchars($post->title, ENT_QUOTES | ENT_SUBSTITUTE) . '</title>' .
			'<content formatter="' . htmlspecialchars($post->contentformatter, ENT_QUOTES | ENT_SUBSTITUTE) . '" editor="' . htmlspecialchars($post->contenteditor, ENT_QUOTES | ENT_SUBSTITUTE) .'">' . htmlspecialchars(UTF8::correct($post->content), ENT_QUOTES | ENT_SUBSTITUTE) . '</content>' .
			'<location>' . htmlspecialchars($post->location, ENT_QUOTES | ENT_SUBSTITUTE) . '</location>' .
			(!is_null($post->password) ? '<password>' . htmlspecialchars($post->password, ENT_QUOTES | ENT_SUBSTITUTE) . '</password>' : '') .
			'<acceptComment>' . $post->acceptcomment . '</acceptComment>' .
			'<acceptTrackback>' . $post->accepttrackback . '</acceptTrackback>' .
			'<published>' . $post->published . '</published>' .
			'<created>' . $post->created . '</created>' .
			'<modified>' . $post->modified . '</modified>' .
			'<longitude>'. $post->longitude . '</longitude>'.
			'<latitude>'. $post->latitude. '</latitude>');

		if ($post->category)
			$writer->write('<category>' . htmlspecialchars(Category::getLabel($post->category), ENT_QUOTES | ENT_SUBSTITUTE) . '</category>');
		if ($post->loadTags()) {
			foreach ($post->tags as $tag)
				$writer->write('<tag>' . htmlspecialchars($tag, ENT_QUOTES | ENT_SUBSTITUTE) . '</tag>');
		}
		$writer->write(CRLF);
		if ($attachment = $post->getAttachments()) {
			do {
				$writer->write('<attachment' . ' mime="' . htmlspecialchars($attachment->mime, ENT_QUOTES | ENT_SUBSTITUTE) . '"' . ' size="' . $attachment->size . '"' . ' width="' . $attachment->width . '"' . ' height="' . $attachment->height . '"' . '>' . '<name>' . htmlspecialchars($attachment->name, ENT_QUOTES | ENT_SUBSTITUTE) . '</name>' . '<label>' . htmlspecialchars($attachment->label, ENT_QUOTES | ENT_SUBSTITUTE) . '</label>' . '<enclosure>' . ($attachment->enclosure ? 1 : 0) . '</enclosure>' . '<attached>' . $attachment->attached . '</attached>' . '<downloads>' . $attachment->downloads . '</downloads>');
				if ($includeFileContents && file_exists(__TEXTCUBE_ATTACH_DIR__."/$blogid/{$attachment->name}")) {
					$writer->write('<content>');
					Base64Stream::encode(__TEXTCUBE_ATTACH_DIR__."/$blogid/{$attachment->name}", $writer);
					$writer->write('</content>');
				}
				$writer->write('</attachment>');
				$writer->write(CRLF);
			} while ($attachment->shift());
			$attachment->close();
		}
		if ($comment = $post->getComments()) {
			do {
				if($comment->isfiltered == 0) {
					$writer->write('<comment>' . '<id>'. $comment->id . '</id>' . '<commenter' . ' id="' . $comment->commenter . '">' . '<name>' . htmlspecialchars(UTF8::correct($comment->name), ENT_QUOTES | ENT_SUBSTITUTE) . '</name>' . '<homepage>' . htmlspecialchars(UTF8::correct($comment->homepage), ENT_QUOTES | ENT_SUBSTITUTE) . '</homepage>' . '<ip>' . $comment->ip . '</ip>' . '<openid>' . htmlspecialchars($comment->openid, ENT_QUOTES | ENT_SUBSTITUTE) . '</openid>' . '</commenter>' .
					'<content>' . htmlspecialchars($comment->content, ENT_QUOTES | ENT_SUBSTITUTE) . '</content>' . '<password>' . htmlspecialchars($comment->password, ENT_QUOTES | ENT_SUBSTITUTE) . '</password>' . '<secret>' . htmlspecialchars($comment->secret, ENT_QUOTES | ENT_SUBSTITUTE) . '</secret>' .'<longitude>'.$comment->longitude .'</longitude>'.'<latitude>'.$comment->latitude.'</latitude>' . '<written>' . $comment->written . '</written>' . '<isFiltered>' . $comment->isfiltered . '</isFiltered>');
					$writer->write(CRLF);
					if ($childComment = $comment->getChildren()) {
						do {
							if($childComment->isfiltered == 0) {
								$writer->write('<comment>' . '<id>' . $childComment->id . '</id>' . '<commenter' . ' id="' . $childComment->commenter . '"' . '>' . '<name>' . htmlspecialchars(UTF8::correct($childComment->name), ENT_QUOTES | ENT_SUBSTITUTE) . '</name>' . '<homepage>' . htmlspecialchars(UTF8::correct($childComment->homepage), ENT_QUOTES | ENT_SUBSTITUTE) . '</homepage>' . '<ip>' . $childComment->ip . '</ip>' . '<openid>' . htmlspecialchars($childComment->openid, ENT_QUOTES | ENT_SUBSTITUTE) . '</openid>' . '</commenter>' . '<content>' . htmlspecialchars($childComment->content, ENT_QUOTES | ENT_SUBSTITUTE) . '</content>' . '<password>' . htmlspecialchars($childComment->password, ENT_QUOTES | ENT_SUBSTITUTE) . '</password>' . '<secret>' . htmlspecialchars($childComment->secret, ENT_QUOTES | ENT_SUBSTITUTE) . '</secret>' . '<written>' . $childComment->written . '</written>' . '<isFiltered>' . $childComment->isfiltered . '</isFiltered>' . '</comment>');
							$writer->write(CRLF);
							}
						} while ($childComment->shift());
						$childComment->close();
					}
					$writer->write('</comment>');
					$writer->write(CRLF);
				}
			} while ($comment->shift());
			$comment->close();
		}
		if ($trackback = $post->getTrackbacks()) {
			do {
				if($trackback->isfiltered == 0) {
					$writer->write('<trackback>' . '<url>' . htmlspecialchars(UTF8::correct($trackback->url), ENT_QUOTES | ENT_SUBSTITUTE) . '</url>' . '<site>' . htmlspecialchars(UTF8::correct($trackback->site), ENT_QUOTES | ENT_SUBSTITUTE) . '</site>' . '<title>' . htmlspecialchars(UTF8::correct($trackback->title), ENT_QUOTES | ENT_SUBSTITUTE) . '</title>' . '<excerpt>' . htmlspecialchars(UTF8::correct($trackback->excerpt), ENT_QUOTES | ENT_SUBSTITUTE) . '</excerpt>' . '<ip>' . $trackback->ip . '</ip>' . '<received>' . $trackback->received . '</received>' . '<isFiltered>' . $trackback->isfiltered . '</isFiltered>' . '</trackback>');
					$writer->write(CRLF);
				}
			} while ($trackback->shift());
			$trackback->close();
		}
		if ($log = $post->getTrackbackLogs()) {
			$writer->write('<logs>');
			do {
				$writer->write('<trackback>' . '<url>' . htmlspecialchars(UTF8::correct($log->url), ENT_QUOTES | ENT_SUBSTITUTE) . '</url>' . '<sent>' . $log->sent . '</sent>' . '</trackback>');
				$writer->write(CRLF);
			} while ($log->shift());
			$writer->write('</logs>');
			$log->close();
		}
		$writer->write('</post>');
		$writer->write(CRLF);
	} while ($post->shift());
	$post->close();
}
$notice = new Notice();
if ($notice->open()) {
	do {
		$writer->write('<notice' . ' slogan="' . htmlspecialchars($notice->slogan, ENT_QUOTES | ENT_SUBSTITUTE) . '"' . $newlineStyle . '>' .
			'<id>' . $notice->id . '</id>' .
			'<visibility>' . $notice->visibility . '</visibility>' .
			'<starred>' . $notice->starred . '</starred>' .
			'<title>' . htmlspecialchars(UTF8::correct($notice->title), ENT_QUOTES | ENT_SUBSTITUTE) . '</title>' .
			'<content formatter="' . htmlspecialchars($notice->contentformatter, ENT_QUOTES | ENT_SUBSTITUTE) . '" editor="' . htmlspecialchars($notice->contenteditor, ENT_QUOTES | ENT_SUBSTITUTE) .'">' . htmlspecialchars(UTF8::correct($notice->content), ENT_QUOTES | ENT_SUBSTITUTE) . '</content>' .
			'<published>' . $notice->published . '</published>' .
			'<created>' . $notice->created . '</created>' .
			'<modified>' . $notice->modified . '</modified>');

		$writer->write(CRLF);
		if ($attachment = $notice->getAttachments()) {
			do {
				$writer->write('<attachment' . ' mime="' . htmlspecialchars($attachment->mime, ENT_QUOTES | ENT_SUBSTITUTE) . '"' . ' size="' . $attachment->size . '"' . ' width="' . $attachment->width . '"' . ' height="' . $attachment->height . '"' . '>' . '<name>' . htmlspecialchars($attachment->name, ENT_QUOTES | ENT_SUBSTITUTE) . '</name>' . '<label>' . htmlspecialchars($attachment->label, ENT_QUOTES | ENT_SUBSTITUTE) . '</label>' . '<enclosure>' . ($attachment->enclosure ? 1 : 0) . '</enclosure>' . '<attached>' . $attachment->attached . '</attached>' . '<downloads>' . $attachment->downloads . '</downloads>');
				if ($includeFileContents && file_exists(__TEXTCUBE_ATTACH_DIR__."/$blogid/{$attachment->name}")) {
					$writer->write('<content>');
					Base64Stream::encode(__TEXTCUBE_ATTACH_DIR__."/$blogid/{$attachment->name}", $writer);
					$writer->write('</content>');
				}
				$writer->write('</attachment>');
				$writer->write(CRLF);
			} while ($attachment->shift());
			$attachment->close();
		}
		$writer->write('</notice>');
		$writer->write(CRLF);
	} while ($notice->shift());
	$notice->close();
}
$keyword = new Keyword();
if ($keyword->open()) {
	do {
		$writer->write('<keyword' . $newlineStyle . '>' .
			'<id>' . $keyword->id . '</id>' .
			'<visibility>' . $keyword->visibility . '</visibility>' .
			'<starred>' . $keyword->starred . '</starred>' .
			'<name>' . htmlspecialchars(UTF8::correct($keyword->name), ENT_QUOTES | ENT_SUBSTITUTE) . '</name>' .
			'<description editor="' . htmlspecialchars($keyword->descriptionEditor, ENT_QUOTES | ENT_SUBSTITUTE) . '" formatter="' . htmlspecialchars($keyword->descriptionFormatter, ENT_QUOTES | ENT_SUBSTITUTE) .'">' . htmlspecialchars(UTF8::correct($keyword->description), ENT_QUOTES | ENT_SUBSTITUTE) . '</description>' .
			'<published>' . $keyword->published . '</published>' .
			'<created>' . $keyword->created . '</created>' .
			'<modified>' . $keyword->modified . '</modified>');

		$writer->write(CRLF);
		if ($attachment = $keyword->getAttachments()) {
			do {
				$writer->write('<attachment' . ' mime="' . htmlspecialchars($attachment->mime, ENT_QUOTES | ENT_SUBSTITUTE) . '"' . ' size="' . $attachment->size . '"' . ' width="' . $attachment->width . '"' . ' height="' . $attachment->height . '"' . '>' . '<name>' . htmlspecialchars($attachment->name, ENT_QUOTES | ENT_SUBSTITUTE) . '</name>' . '<label>' . htmlspecialchars($attachment->label, ENT_QUOTES | ENT_SUBSTITUTE) . '</label>' . '<enclosure>' . ($attachment->enclosure ? 1 : 0) . '</enclosure>' . '<attached>' . $attachment->attached . '</attached>' . '<downloads>' . $attachment->downloads . '</downloads>');
				if ($includeFileContents && file_exists(__TEXTCUBE_ATTACH_DIR__."/$blogid/{$attachment->name}")) {
					$writer->write('<content>');
					Base64Stream::encode(__TEXTCUBE_ATTACH_DIR__."/$blogid/{$attachment->name}", $writer);
					$writer->write('</content>');
				}
				$writer->write('</attachment>');
				$writer->write(CRLF);
			} while ($attachment->shift());
			$attachment->close();
		}
		$writer->write('</keyword>');
		$writer->write(CRLF);
	} while ($keyword->shift());
	$keyword->close();
}
$link = new Link();
if ($link->open()) {
	do {
		$writer->write('<link>' . '<url>' . htmlspecialchars(UTF8::correct($link->url), ENT_QUOTES | ENT_SUBSTITUTE) . '</url>' . '<title>' . htmlspecialchars(UTF8::correct($link->title), ENT_QUOTES | ENT_SUBSTITUTE) . '</title>' . '<feed>' . htmlspecialchars(UTF8::correct($link->feed), ENT_QUOTES | ENT_SUBSTITUTE) . '</feed>' . '<registered>' . $link->registered . '</registered>' . '<xfn>' . htmlspecialchars($link->xfn, ENT_QUOTES | ENT_SUBSTITUTE) . '</xfn>' . '</link>');
		$writer->write(CRLF);
	} while ($link->shift());
	$link->close();
}
$log = new RefererLog();
if ($log->open()) {
	$writer->write('<logs>');
	$writer->write(CRLF);
	do {
		$writer->write('<referer>' . '<url>' . htmlspecialchars(UTF8::correct($log->url), ENT_QUOTES | ENT_SUBSTITUTE) . '</url>' . '<referred>' . $log->referred . '</referred>' . '</referer>');
		$writer->write(CRLF);
	} while ($log->shift());
	$writer->write('</logs>');
	$writer->write(CRLF);
	$log->close();
}
$cmtNotified = new CommentNotified();
$cur_siteinfo = array();
$i = 0;
if ($cmtNotified->open()) {
	$writer->write('<commentsNotified>');
	$writer->write(CRLF);
	do {
		$writer->write('<comment>');
		$writer->write('<id>' . $cmtNotified->id . '</id>');
		$writer->write('<commenter>');
		$writer->write('<name>' . htmlspecialchars(UTF8::correct($cmtNotified->name), ENT_QUOTES | ENT_SUBSTITUTE) . '</name>');
		$writer->write('<homepage>' . htmlspecialchars(UTF8::correct($cmtNotified->homepage), ENT_QUOTES | ENT_SUBSTITUTE) . '</homepage>');
		$writer->write('<ip>' . $cmtNotified->ip . '</ip>');
		$writer->write('</commenter>');
		$writer->write('<entry>' . $cmtNotified->entry . '</entry>');
		$writer->write('<content>' . htmlspecialchars(UTF8::correct($cmtNotified->content), ENT_QUOTES | ENT_SUBSTITUTE). '</content>');
		$writer->write('<password>' . htmlspecialchars($cmtNotified->password, ENT_QUOTES | ENT_SUBSTITUTE) . '</password>');
		$writer->write('<parent>' . htmlspecialchars($cmtNotified->parent, ENT_QUOTES | ENT_SUBSTITUTE) . '</parent>');
		$writer->write('<secret>' . $cmtNotified->secret . '</secret>');
		$writer->write('<written>' . $cmtNotified->written . '</written>');
		$writer->write('<modified>' . $cmtNotified->modified . '</modified>');
		$site = new CommentNotifiedSiteInfo();
		$site->open("id = {$cmtNotified->siteid}");
		$writer->write('<site>' . htmlspecialchars(UTF8::correct($site->url), ENT_QUOTES | ENT_SUBSTITUTE) . '</site>');
		$cur_siteinfo[$i] = $site->id; $i++;
		$site->close();
		$writer->write('<remoteId>' . $cmtNotified->remoteid . '</remoteId>');
		$writer->write('<isNew>' . $cmtNotified->isnew . '</isNew>');
		$writer->write('<url>' . htmlspecialchars(UTF8::correct($cmtNotified->url), ENT_QUOTES | ENT_SUBSTITUTE). '</url>');
		$writer->write('<entryTitle>' . htmlspecialchars(UTF8::correct($cmtNotified->entrytitle), ENT_QUOTES | ENT_SUBSTITUTE). '</entryTitle>');
		$writer->write('<entryUrl>' . htmlspecialchars(UTF8::correct($cmtNotified->entryurl), ENT_QUOTES | ENT_SUBSTITUTE). '</entryUrl>');
		$writer->write('</comment>');
		$writer->write(CRLF);
	} while ($cmtNotified->shift());
	$writer->write('</commentsNotified>');
	$writer->write(CRLF);
	$cmtNotified->close();
}
$cmtNotifiedSite = new CommentNotifiedSiteInfo();
if ($cmtNotifiedSite->open()) {
	$writer->write('<commentsNotifiedSiteInfo>');
	do {
		if (in_array($cmtNotifiedSite->id, $cur_siteinfo)) {
			$writer->write('<site>');
			$writer->write('<title>' . htmlspecialchars(UTF8::correct($cmtNotifiedSite->title), ENT_QUOTES | ENT_SUBSTITUTE) . '</title>');
			$writer->write('<name>' . htmlspecialchars(UTF8::correct($cmtNotifiedSite->name), ENT_QUOTES | ENT_SUBSTITUTE) . '</name>');
			$writer->write('<url>' . htmlspecialchars(UTF8::correct($cmtNotifiedSite->url), ENT_QUOTES | ENT_SUBSTITUTE) . '</url>');
			$writer->write('<modified>' . $cmtNotifiedSite->modified . '</modified>');
			$writer->write('</site>');
		}
	} while ($cmtNotifiedSite->shift());
	$writer->write('</commentsNotifiedSiteInfo>');
	$writer->write(CRLF);
	$cmtNotifiedSite->close();
}
$statistics = new RefererStatistics();
if ($statistics->open()) {
	$writer->write('<statistics>');
	do {
		$writer->write('<referer>' . '<host>' . htmlspecialchars(UTF8::correct($statistics->host), ENT_QUOTES | ENT_SUBSTITUTE) . '</host>' . '<count>' . $statistics->count . '</count>' . '</referer>');
		$writer->write(CRLF);
	} while ($statistics->shift());
	$writer->write('</statistics>');
	$writer->write(CRLF);
	$statistics->close();
}
$statistics = new BlogStatistics();
if ($statistics->load()) {
	$writer->write('<statistics>' . '<visits>' . $statistics->visits . '</visits>' . '</statistics>');
	$writer->write(CRLF);
}
$statistics = new DailyStatistics();
if ($statistics->open()) {
	$writer->write('<statistics>');
	do {
		$writer->write('<daily>' . '<date>' . $statistics->datemark . '</date>' . '<visits>' . $statistics->visits . '</visits>' . '</daily>');
		$writer->write(CRLF);
	} while ($statistics->shift());
	$writer->write('</statistics>');
	$writer->write(CRLF);
	$statistics->close();
}
$setting = new SkinSetting();
if ($setting->load()) {
	// Textcube-NG: r4-9 — SkinSetting::load() 오타를 고쳐 이제 <skin> 에 실제 값이 들어가므로 다른 요소처럼 htmlspecialchars 로 XML escape 한다(행이 없는 값은 null 이라 (string) 으로 바꿈 — PHP 8.1+ null 인자 Deprecated 방지)
	$writer->write('<skin>' .
		'<name>' . htmlspecialchars((string)$setting->skin) . '</name>' .
		'<entriesOnRecent>' . htmlspecialchars((string)$setting->entriesOnRecent) . '</entriesOnRecent>' .
		'<commentsOnRecent>' . htmlspecialchars((string)$setting->commentsOnRecent) . '</commentsOnRecent>' .
		'<trackbacksOnRecent>' . htmlspecialchars((string)$setting->trackbacksOnRecent) . '</trackbacksOnRecent>' .
		'<commentsOnGuestbook>' . htmlspecialchars((string)$setting->commentsOnGuestbook) . '</commentsOnGuestbook>' .
		'<tagsOnTagbox>' . htmlspecialchars((string)$setting->tagsOnTagbox) . '</tagsOnTagbox>' .
		'<alignOnTagbox>' . htmlspecialchars((string)$setting->alignOnTagbox) . '</alignOnTagbox>' .
		'<expandComment>' . htmlspecialchars((string)$setting->expandComment) . '</expandComment>' .
		'<expandTrackback>' . htmlspecialchars((string)$setting->expandTrackback) . '</expandTrackback>' .
		'<recentNoticeLength>' . htmlspecialchars((string)$setting->recentNoticeLength) . '</recentNoticeLength>' .
		'<recentEntryLength>' . htmlspecialchars((string)$setting->recentEntryLength) . '</recentEntryLength>' .
		'<recentTrackbackLength>' . htmlspecialchars((string)$setting->recentTrackbackLength) . '</recentTrackbackLength>' .
		'<linkLength>' . htmlspecialchars((string)$setting->linkLength) . '</linkLength>' .
		'<showListOnCategory>' . htmlspecialchars((string)$setting->showListOnCategory) . '</showListOnCategory>' .
		'<showListOnArchive>' . htmlspecialchars((string)$setting->showListOnArchive) . '</showListOnArchive>' .
		'<tree>' .
			'<name>' . htmlspecialchars((string)$setting->tree) . '</name>' .
			'<color>' . htmlspecialchars((string)$setting->colorOnTree) . '</color>' .
			'<bgColor>' . htmlspecialchars((string)$setting->bgcolorOnTree) . '</bgColor>' .
			'<activeColor>' . htmlspecialchars((string)$setting->activecolorOnTree) . '</activeColor>' .
			'<activeBgColor>' . htmlspecialchars((string)$setting->activebgcolorOnTree) . '</activeBgColor>' .
			'<labelLength>' . htmlspecialchars((string)$setting->labelLengthOnTree) . '</labelLength>' .
			'<showValue>' . htmlspecialchars((string)$setting->showValueOnTree) . '</showValue>' .
		'</tree>' .
	'</skin>');
	$writer->write(CRLF);
}
$setting = new PluginSetting();
if ($setting->open()) {
	do {
		$writer->write('<plugin>' . '<name>' . $setting->name . '</name>' . '<setting>' . htmlspecialchars($setting->setting, ENT_QUOTES | ENT_SUBSTITUTE) . '</setting>' . '</plugin>');
		$writer->write(CRLF);
	} while ($setting->shift());
	$setting->close();
}
$setting = new UserSetting();
if ($setting->open()) {
	do {
		$writer->write('<userSetting>' . '<name>' . $setting->name . '</name>' . '<value>' . htmlspecialchars($setting->value, ENT_QUOTES | ENT_SUBSTITUTE) . '</value>' . '</userSetting>');
		$writer->write(CRLF);
	} while ($setting->shift());
	$setting->close();
}
$comment = new GuestComment();
if ($comment->open('parent IS NULL')) {
	$writer->write('<guestbook>');
	do {
		if ($comment->isfiltered == 0) {
			$writer->write('<comment>' . '<commenter' . ' id="' . $comment->commenter . '">' . '<name>' . htmlspecialchars(UTF8::correct($comment->name), ENT_QUOTES | ENT_SUBSTITUTE) . '</name>' . '<homepage>' . htmlspecialchars(UTF8::correct($comment->homepage), ENT_QUOTES | ENT_SUBSTITUTE) . '</homepage>' . '<ip>' . $comment->ip . '</ip>' . '<openid>' . htmlspecialchars($comment->openid, ENT_QUOTES | ENT_SUBSTITUTE) . '</openid>' . '</commenter>' . '<content>' . htmlspecialchars(UTF8::correct($comment->content), ENT_QUOTES | ENT_SUBSTITUTE) . '</content>' . '<password>' . htmlspecialchars($comment->password, ENT_QUOTES | ENT_SUBSTITUTE) . '</password>' . '<secret>' . htmlspecialchars($comment->secret, ENT_QUOTES | ENT_SUBSTITUTE) . '</secret>' . '<written>' . $comment->written . '</written>');
			$writer->write(CRLF);
			if ($childComment = $comment->getChildren()) {
				do {
					if ($childComment->isfiltered == 0) {
						$writer->write('<comment>' . '<commenter' . ' id="' . $childComment->commenter . '">' . '<name>' . htmlspecialchars(UTF8::correct($childComment->name), ENT_QUOTES | ENT_SUBSTITUTE) . '</name>' . '<homepage>' . htmlspecialchars(UTF8::correct($childComment->homepage), ENT_QUOTES | ENT_SUBSTITUTE) . '</homepage>' . '<ip>' . $childComment->ip . '</ip>' . '<openid>' . htmlspecialchars($childComment->openid, ENT_QUOTES | ENT_SUBSTITUTE) . '</openid>' . '</commenter>' . '<content>' . htmlspecialchars(UTF8::correct($childComment->content), ENT_QUOTES | ENT_SUBSTITUTE) . '</content>' . '<password>' . htmlspecialchars($childComment->password, ENT_QUOTES | ENT_SUBSTITUTE) . '</password>' . '<secret>' . htmlspecialchars($childComment->secret, ENT_QUOTES | ENT_SUBSTITUTE) . '</secret>' . '<written>' . $childComment->written . '</written>' . '</comment>');
						$writer->write(CRLF);
					}
				} while ($childComment->shift());
				$childComment->close();
			}
			$writer->write('</comment>');
			$writer->write(CRLF);
		}
	} while ($comment->shift());
	$writer->write('</guestbook>');
	$writer->write(CRLF);
	$comment->close();
}
$filter = new Filter();
if ($filter->open()) {
	do {
		$writer->write('<filter type="' . $filter->filtertype . '">' . '<pattern>' . htmlspecialchars($filter->pattern, ENT_QUOTES | ENT_SUBSTITUTE) . '</pattern>' . '</filter>');
		$writer->write(CRLF);
	} while ($filter->shift());
	$filter->close();
}
$feed = new Feed();
if ($feed->open()) {
	do {
		$writer->write('<feed>' . '<group>' . htmlspecialchars($feed->getGroupName(), ENT_QUOTES | ENT_SUBSTITUTE) . '</group>' . '<url>' . htmlspecialchars(UTF8::correct($feed->url), ENT_QUOTES | ENT_SUBSTITUTE) . '</url>' . '</feed>');
		$writer->write(CRLF);
	} while ($feed->shift());
	$feed->close();
}

$line = Model_Line::getInstance();
$line->setFilter(array('blogid','eq',getBlogId()));
if($lines = $line->get()) {
	foreach ($lines as $lineitem) {
		$writer->write('<line>' .
			'<author>' . htmlspecialchars(UTF8::correct($lineitem['author']), ENT_QUOTES | ENT_SUBSTITUTE) . '</author>' .
			'<category>' . htmlspecialchars(UTF8::correct($lineitem['category']), ENT_QUOTES | ENT_SUBSTITUTE) . '</category>' .
			'<root>' . htmlspecialchars(UTF8::correct($lineitem['root']), ENT_QUOTES | ENT_SUBSTITUTE) . '</root>' .
			'<permalink>' . htmlspecialchars(UTF8::correct($lineitem['permalink']), ENT_QUOTES | ENT_SUBSTITUTE) . '</permalink>' .
			'<content>' . htmlspecialchars(UTF8::correct($lineitem['content']), ENT_QUOTES | ENT_SUBSTITUTE) . '</content>' .
			'<created>' . $lineitem['created'] . '</created>' .
			'</line>');
		$writer->write(CRLF);
	}
}

$writer->write('</blog>');
$writer->close();
if (defined('__TEXTCUBE_BACKUP__')) {
	@chmod(__TEXTCUBE_CACHE_DIR__."/backup/$blogid.xml", 0600);	// [보안] 원본 0666
	Respond::ResultPage(0);
}
?>
