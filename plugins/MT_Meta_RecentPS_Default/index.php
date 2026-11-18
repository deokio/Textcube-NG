<?php
/* Textcube-NG: 직접 SQL(POD 문자열 SQL)을 준비 문장(POD::prepare·bindAndExecute)으로 바꿨다. 값은 SQL 문장과 따로 보낸다(PDO 모드 호환)(v3.211)
   행은 열 이름 키 배열(fetchAllStmt)이다. 실패하면 원래 POD 함수와 같이 null·false·0을 돌려준다. */
function MT_Cover_preparedRows($query, $types, $params) {
	$stmt = POD::prepare($query);
	if (!$stmt) return null;
	$rows = POD::bindAndExecute($stmt, $types, ...$params) ? POD::fetchAllStmt($stmt) : null;
	$stmt->close();
	return $rows;
}

function MT_Cover_preparedCell($query, $types, $params) {
	$rows = MT_Cover_preparedRows($query, $types, $params);
	if (empty($rows)) return null;
	$row = array_values($rows[0]);
	return $row[0];
}

function MT_Cover_preparedColumn($query, $types, $params) {
	$rows = MT_Cover_preparedRows($query, $types, $params);
	if ($rows === null) return null;
	$column = array();
	foreach ($rows as $row) {
		$row = array_values($row);
		$column[] = $row[0];
	}
	return $column;
}

// Paging::fetch()와 같은 계산(url·prefix는 기본값). COUNT와 목록 질의를 모두 준비 문장으로 보낸다. ? 자리는 FROM … ORDER BY 사이에만 둔다
function MT_Cover_preparedPaging($sql, $types, $params, $page, $count) {
	$context = Model_Context::getInstance();
	$paging = array('url' => $context->getProperty('uri.folder'), 'prefix' => '?page=', 'postfix' => '', 'onclick' => null);
	if (empty($sql))
		return array(array(), $paging);
	if (preg_match('/\s(FROM.*)(ORDER BY.*)$/si', $sql, $matches))
		$from = $matches[1];
	else
		return array(array(), $paging);
	$paging['total'] = MT_Cover_preparedCell("SELECT COUNT(*) $from", $types, $params);
	if ($paging['total'] === null)
		return array(array(), $paging);
	$count = (int)$count;
	if ($count < 1) $count = 1;
	$paging['pages'] = intval(ceil($paging['total'] / $count));
	$paging['page'] = is_numeric($page) ? $page : 1;
	if ($paging['page'] > $paging['pages']) {
		$paging['page'] = $paging['pages'];
		if ($paging['pages'] > 0)
			$paging['prev'] = $paging['pages'] - 1;
	}
	if ($paging['page'] > 1)
		$paging['prev'] = $paging['page'] - 1;
	if ($paging['page'] < $paging['pages'])
		$paging['next'] = $paging['page'] + 1;
	$offset = ($paging['page'] - 1) * $count;
	if ($offset < 0) $offset = 0;
	return array(MT_Cover_preparedRows("$sql LIMIT $count OFFSET $offset", $types, $params), $paging);
}

/* Textcube-NG: 여러 블로그 표지(coverMode 2)의 비공개 분류 조건. 분류 id 는 블로그마다 따로이므로 블로그마다 getCategoryVisibilityList($blogid, 'private') 와 같은 방법
   (visibility 1 인 분류와 그 하위 분류, 같은 정렬·비교)으로 목록을 만들어 ' AND NOT ((e.blogid = 블로그 AND e.category IN (분류,…)) OR …)' 를 돌려준다.
   정수만 넣으므로 ? 자리가 없다. 비공개 분류가 없거나 읽지 못하면 ''(getPrivateCategoryExclusionQuery 와 같음)(r3-5) */
function MT_Cover_privateCategoryExclusionByBlog() {
	global $database;
	$rows = MT_Cover_preparedRows("SELECT blogid, id, parent, visibility FROM {$database['prefix']}Categories WHERE id >= 0 ORDER BY blogid, parent, priority", '', array());
	if (empty($rows)) return '';
	$blogs = array();
	foreach ($rows as $row) $blogs[$row['blogid']][$row['id']] = $row;
	$parts = array();
	foreach ($blogs as $blogid => $categories) {
		$list = array();
		foreach ($categories as $row) {
			if (isset($row['visibility']) && $row['visibility'] == 1) array_push($list, $row['id']);
		}
		foreach ($categories as $row) {
			if (isset($row['parent']) && in_array($row['parent'], $list) && !in_array($row['id'], $list)) array_push($list, $row['id']);
		}
		if (!empty($list)) $parts[] = '(e.blogid = ' . intval($blogid) . ' AND e.category IN (' . implode(',', array_map('intval', $list)) . '))';
	}
	return empty($parts) ? '' : ' AND NOT (' . implode(' OR ', $parts) . ')';
}

function MT_Cover_getRecentEntries($parameters){
	global $database, $blog, $service, $serviceURL, $suri, $configVal, $defaultURL, $skin;
	requireModel("blog.entry");
	requireModel("blog.tag");
	$data = Setting::fetchConfigVal($configVal);
	$data['coverMode']	= !isset($data['coverMode']) ? 1 : $data['coverMode'];
	if(Misc::isMetaBlog() != true) $data['coverMode'] = 1;
	$data['screenshot']	= !isset($data['screenshot']) ? 1 : $data['screenshot'];
	$data['screenshotSize']	= !isset($data['screenshotSize']) ? 90 : $data['screenshotSize'];
	$data['paging'] = !isset($data['paging'])?'2':$data['paging'];
	$data['contentLength']	= !isset($data['contentLength']) ? 250 : $data['contentLength'];

	if (isset($parameters['preview'])) {
		// preview mode
		$retval = '표지에 최신 글 목록을 추가합니다.';
		return htmlspecialchars($retval);
	}
	$entryLength = isset($parameters['entryLength'])?$parameters['entryLength']:10;

	if (!is_dir(__TEXTCUBE_CACHE_DIR__."/thumbnail")) {
		@mkdir(__TEXTCUBE_CACHE_DIR__."/thumbnail");
		@chmod(__TEXTCUBE_CACHE_DIR__."/thumbnail", 0777);
	}
	if (!is_dir(__TEXTCUBE_CACHE_DIR__."/thumbnail/" . getBlogId())) {
		@mkdir(__TEXTCUBE_CACHE_DIR__."/thumbnail/" . getBlogId());
		@chmod(__TEXTCUBE_CACHE_DIR__."/thumbnail/" . getBlogId(), 0777);
	}
	if (!is_dir(__TEXTCUBE_CACHE_DIR__."/thumbnail/" . getBlogId() . "/coverPostThumbnail/")) {
		@mkdir(__TEXTCUBE_CACHE_DIR__."/thumbnail/" . getBlogId() . "/coverPostThumbnail/");
		@chmod(__TEXTCUBE_CACHE_DIR__."/thumbnail/" . getBlogId() . "/coverPostThumbnail/", 0777);
	}

	$page = ($data['paging'] == '1' && !empty($_GET['page'])) ? intval($_GET['page']) : 1;

	$cache = new PageCache;
	$cache->name = 'MT_Cover_RecentPS';
	if($cache->load()) { //If successful loads
		$cache->contents = unserialize($cache->contents);
		// If coverpage is single mode OR coverpage is coverblog and cache is not expired, return cache contents.
		if(($data['coverMode']==1 || $data['coverMode']==2) && array_key_exists($page, $cache->contents) && (Timestamp::getUNIXtime() - $cache->dbContents < 300)) {
			return $cache->contents[$page];
		}
	}

	// Textcube-NG: 비공개 분류의 하위 분류 글도 뺀다(정수 id 만 넣어 값에 ? 자리가 없고 아래 자리표시자 순서는 그대로).
	// 한 블로그 표지는 현재 블로그 목록(getPrivateCategoryExclusionQuery), 여러 블로그 표지(coverMode 2)는 분류 id 가 블로그마다 따로이므로 블로그별 목록(MT_Cover_privateCategoryExclusionByBlog)으로 뺀다(r3-5)
	$privateCategories = ($data['coverMode']==2) ? MT_Cover_privateCategoryExclusionByBlog() : getPrivateCategoryExclusionQuery(getBlogId());
	// Textcube-NG: 여러 블로그 표지(coverMode 2)는 single 모드에서도 공개 글만 보인다 — 다른 블로그의 비공개 글이 대표 블로그 구성원에게 나오지 않게(r3-15, R-300)
	if((Misc::isMetaBlog() == true) && doesHaveOwnership() && ($service['type'] != 'single' || $data['coverMode']==2)) {
		$visibility = 'AND e.visibility > 1 AND (c.visibility > 1 OR e.category = 0)'.$privateCategories;
	} else {
		$visibility = doesHaveOwnership() ? '' : 'AND e.visibility > 1 AND (c.visibility > 1 OR e.category = 0)'.$privateCategories;
	}
	// Textcube-NG: 글관리 권한(group.editors)이 없는 구성원은 남의 비공개 글을 보지 않는다(목록·아래 COUNT 같은 조건)(v3.145)
	// Textcube-NG: 값은 자리표시자(?)로 따로 보낸다(v3.211)
	$visibilityOwn = (doesHaveOwnership() && !Acl::check('group.editors'));
	$visibility .= $visibilityOwn ? ' AND (e.userid = ? OR e.visibility > 0)' : '';
	$visibilityTypes = $visibilityOwn ? 'i' : '';
	$visibilityParams = $visibilityOwn ? array(getUserId()) : array();
	$multiple = ($data['coverMode']==2) ? '' : 'e.blogid = ? AND';
	$multipleTypes = ($data['coverMode']==2) ? '' : 'i';
	$multipleParams = ($data['coverMode']==2) ? array() : array(getBlogId());
	// Textcube-NG: value 는 text(Cubrid varchar) 열이라 PG 에서 value < 2 는 text < integer 오류, Cubrid 는 value 가 예약어라 구문 오류 — 문자열 비교 '2' 로 바꾸고(값은 '0'~'3' 한 자리라 결과 같음) Cubrid 는 " 로 감싼다(suggest 와 같음)(v3.222)
	$reserved = POD::reservedFieldNames();
	$valueField = (is_array($reserved) && in_array('value', $reserved)) ? '"value"' : 'value';
	$privateBlogId = MT_Cover_preparedColumn("SELECT blogid 
		FROM {$database['prefix']}BlogSettings
		WHERE name = 'visibility'
		AND $valueField < '2'", '', array());
	if(!empty($privateBlogId)) $privateBlogs = ' AND e.blogid NOT IN ('.implode(',', array_fill(0, count($privateBlogId), '?')).')';
	else $privateBlogs = '';
	$privateTypes = empty($privateBlogId) ? '' : str_repeat('i', count($privateBlogId));
	$privateParams = empty($privateBlogId) ? array() : array_values($privateBlogId);
	list($entries, $paging) = MT_Cover_preparedPaging("SELECT e.blogid, e.id, e.userid, e.title, e.content, e.slogan, e.category, e.published, e.contentformatter, c.label
		FROM {$database['prefix']}Entries e
		LEFT JOIN {$database['prefix']}Categories c ON e.blogid = c.blogid AND e.category = c.id
		WHERE $multiple e.draft = 0 $visibility AND e.category >= 0 $privateBlogs
		ORDER BY published DESC", $multipleTypes . $visibilityTypes . $privateTypes, array_merge($multipleParams, $visibilityParams, $privateParams), $page, $entryLength);

	$html = '';
	foreach ((array)$entries as $entry){
		$tagLabelView = "";
		$blogid = ($data['coverMode']==2) ? $entry['blogid'] : getBlogId();
		$entryTags = getTags($blogid, $entry['id']);
		$defaultURL = getDefaultURL($blogid);
		if (sizeof($entryTags) > 0) {
			$tags = array();
			foreach ($entryTags as $entryTag) {
				$tags[$entryTag['name']] = "<a href=\"{$defaultURL}/tag/" . (Setting::getBlogSettingGlobal('useSloganOnTag',true) ? URL::encode($entryTag['name'],$service['useEncodedURL']) : $entryTag['id']) . '">' . htmlspecialchars($entryTag['name']) . '</a>';
			}
			$tagLabelView = "<div class=\"post_tags\"><span>TAG : </span>".implode(",\r\n", array_values($tags))."</div>";
		}
		
		if (empty($entry['category'])) {
			$entry['label'] = _text('분류없음');
			$entry['link'] = "{$defaultURL}/category";
		} else {
			$entry['link'] = "{$defaultURL}/category/" . (Setting::getBlogSettingGlobal('useSloganOnCategory',true) ? URL::encode($entry['label'],$service['useEncodedURL']) : $entry['category']);
		}
		$permalink = "{$defaultURL}/" . (Setting::getBlogSettingGlobal('useSloganOnPost',true) ? "entry/" . URL::encode($entry['slogan'],$service['useEncodedURL']) : $entry['id']);

		$html .= '<div class="coverpost">'.CRLF;
		if($imageName = MT_Cover_getAttachmentExtract($entry['content'])){
			if(($tempImageSrc = MT_Cover_getImageResizer($blogid, $imageName, $data['screenshotSize'])) && ($data['screenshot'] == 1)){
				$html .= '<div class="img_preview"><a href="'.htmlspecialchars($permalink, ENT_QUOTES, 'UTF-8').'"><img src="'.htmlspecialchars($tempImageSrc, ENT_QUOTES, 'UTF-8').'" alt="" /></a></div>'.CRLF;
			}
		}
		$html .= '	<div class="content_box">';
		$html .= '		<h2><a href="'.htmlspecialchars($permalink, ENT_QUOTES, 'UTF-8').'">'.htmlspecialchars($entry['title']).'</a></h2>'.CRLF;
		$html .= '		<div class="post_info">'.CRLF;
		$html .= '			<span class="category"><a href="'.htmlspecialchars($entry['link']).'">'.htmlspecialchars($entry['label']).'</a></span>'.CRLF;
		$html .= '			<span class="date">'.Timestamp::format5($entry['published']).'</span>'.CRLF;
		$html .= '			<span class="author"><span class="preposition">by </span>'.htmlspecialchars(User::getName($entry['userid']), ENT_QUOTES, 'UTF-8').'</span>'.CRLF;
		$html .= '		</div>'.CRLF;
		$html .= '		<div class="post_content">'.htmlspecialchars(UTF8::lessenAsEm(removeAllTags(stripHTML($entry['content'])), $data['contentLength'])).'</div>'.CRLF;
		$html .=		$tagLabelView;
		$html .= '		<div class="clear"></div>'.CRLF;
		$html .= '	</div>';
		$html .= '</div>'.CRLF;
	}

	if ($data['paging'] == '1') {

		$paging['page'] = $page;
		$paging['total'] = MT_Cover_preparedCell("SELECT COUNT(*) FROM {$database['prefix']}Entries e WHERE $multiple e.draft = 0 $visibility AND e.category >= 0", $multipleTypes . $visibilityTypes, array_merge($multipleParams, $visibilityParams));

		$html .= Paging::getPagingView($paging, $skin->paging, $skin->pagingItem).CRLF;

		$html .= '<script type="text/javascript">'.CRLF;
		$html .= '//<![CDATA['.CRLF;
		if ($paging['page'] > 1) {
			$html .= 'var prevURL = '.json_encode($paging['url'].'?page='.($paging['page'] - 1)).';'.CRLF;
		}
		if ($paging['page'] < $paging['total']) {
			$html .= 'var nextURL = '.json_encode($paging['url'].'?page='.($paging['page'] + 1)).';'.CRLF;
		}
		$html .= '//]]>'.CRLF;
		$html .= '</script>';
	}

	$target = $html;
	$cache->contents[$page] = $target;
	$cache->contents = serialize($cache->contents);
	$cache->dbContents = Timestamp::getUNIXtime();
	$cache->update();
	unset($cache);

	return $target;
}

function MT_Cover_getRecentEntries_purgeCache($target, $mother) {
	$cache = new PageCache;
	$cache->name = 'MT_Cover_RecentPS';
	$cache->purge();
	return $target;
}

function MT_Cover_getImageResizer($blogid, $filename, $cropSize){
	global $serviceURL;
	$tempFile = null;
	$thumbFilename = $filename;
	$imageURL = "{$serviceURL}/attach/{$blogid}/{$filename}";
	if (extension_loaded('gd')) {	
		if (stristr($filename, 'http://') ) {
			$thumbFilename = MT_Cover_getRemoteImageFilename($filename);
		}

		$thumbnailSrc = __TEXTCUBE_CACHE_DIR__."/thumbnail/{$blogid}/coverPostThumbnail/th_{$thumbFilename}";
		if (!file_exists($thumbnailSrc)) {
			$imageURL = MT_Cover_getCropProcess($blogid, $filename, $cropSize);
		} else {
			$imageURL = "{$serviceURL}/thumbnail/{$blogid}/coverPostThumbnail/th_{$thumbFilename}";
			$imageInfo = getimagesize($thumbnailSrc);
			if ($imageInfo[0] != $cropSize) {
				$imageURL = MT_Cover_getCropProcess($blogid, $filename, $cropSize);
			}
		}
	} else {
		if(stristr($filename, 'http://') ){
			$imageURL = $filename;
		}
	}
	return $imageURL;
}

function MT_Cover_getCropProcess($blogid, $filename, $cropSize) {
	global $serviceURL;
	$tempFile = null;
	$imageURL = null;
	if(stristr($filename, 'http://') ){
		list($originSrc, $filename, $tempFile) = MT_Cover_getCreateRemoteImage($blogid, $filename);
	} else {
		$originSrc = __TEXTCUBE_ATTACH_DIR__."/{$blogid}/{$filename}";
	}

	$thumbnailSrc = __TEXTCUBE_CACHE_DIR__."/thumbnail/{$blogid}/coverPostThumbnail/th_{$filename}";
	if (file_exists($originSrc)) {
		requireComponent('Textcube.Function.Image');
		$imageInfo = getimagesize($originSrc);

		$objThumbnail = new Utils_Image();
		if ($imageInfo[0] > $imageInfo[1])
			list($tempWidth, $tempHeight) = $objThumbnail->calcOptimizedImageSize($imageInfo[0], $imageInfo[1], NULL, $cropSize);
		else
			list($tempWidth, $tempHeight) = $objThumbnail->calcOptimizedImageSize($imageInfo[0], $imageInfo[1], $cropSize, null);

		$objThumbnail->imageFile = $originSrc;
		if ($objThumbnail->resample($tempWidth, $tempHeight) && $objThumbnail->cropRectBySize($cropSize, $cropSize)) {
			$imageURL = "{$serviceURL}/thumbnail/{$blogid}/coverPostThumbnail/th_{$filename}";
			$objThumbnail->saveAsFile($thumbnailSrc);
		}

		unset($objThumbnail);
		if($tempFile) unlink($originSrc);
	} else {
		$imageURL = null;
	}

	return $imageURL;
}

function MT_Cover_getCreateRemoteImage($blogid, $filename) {
	$fileObject = false;
	$tmpDirectory = __TEXTCUBE_CACHE_DIR__."/thumbnail/{$blogid}/coverPostThumbnail/";
	$tempFilename = tempnam($tmpDirectory, "remote_");
	$fileObject = @fopen($tempFilename, "w");

	if ($fileObject) {
		$originSrc = $tempFilename;
		$remoteImage = MT_Cover_getHTTPRemoteImage($filename);
		$filename = MT_Cover_getRemoteImageFilename($filename);
		fwrite($fileObject, $remoteImage);
		fclose($fileObject);
		return array($originSrc, $filename, true);
	} else {
		return array(null, null, null);
	}
}

function MT_Cover_getHTTPRemoteImage($remoteImage) {
    $response = '';
	$remoteStuff = parse_url($remoteImage);
	$port = isset($remoteStuff['port']) ? $remoteStuff['port'] : 80;

	/* [보안] 글 본문 첫 이미지 주소의 임의 호스트·포트로 소켓을 열었다(방문자가 표지를 볼 때 실행) →
	   http 80 포트의 공인 IPv4 주소만(HTTPRequest::getPublicAddress), 확인한 IP 로 연결. 연결 실패 시 빈 응답 */
	if (!is_array($remoteStuff) || !isset($remoteStuff['scheme'], $remoteStuff['host']) || strtolower($remoteStuff['scheme']) !== 'http'
		|| (int)$port !== 80 || ($address = HTTPRequest::getPublicAddress($remoteStuff['host'])) === false)
		return '';
	$socket = @fsockopen($address, $port);
	if (!$socket)
		return '';
    fputs($socket, "GET " . $remoteStuff['path'] . " HTTP/1.1\r\n");
    fputs($socket, "Host: " . $remoteStuff['host'] . "\r\n");
    fputs($socket, "User-Agent: Mozilla/4.0 (compatible; Textcube)\r\n");
    fputs($socket, "Accept-Encoding: identity\r\n");
    fputs($socket, "Connection: close\r\n");
    fputs($socket, "\r\n");

	while ($buffer = fread($socket, 1024)) {
		$response .= $buffer;
	}

	preg_match('/Content-Length: ([0-9]+)/', $response, $matches);
	return substr($response, - $matches[1]);
}

function MT_Cover_getRemoteImageFilename($filename) {
	$filename = md5($filename) . "." . Misc::getFileExtension($filename);
	return $filename;
}

function MT_Cover_getAttachmentExtract($content){
	$result = null;
	// Textcube-NG: webp·avif 치환자도 받는다(크기 조절은 GD 가 읽을 수 있을 때만, Utils_Image::resample)(r3-20)
	if(preg_match_all('/\[##_(1R|1L|1C|2C|3C|iMazing|Gallery)\|[^|]*\.(gif|jpg|jpeg|png|bmp|webp|avif|GIF|JPG|JPEG|PNG|BMP|WEBP|AVIF)\|.*_##\]/si', $content, $matches)) {
		$split = explode("|", $matches[0][0]);
		$result = $split[1];
	}else if(preg_match_all('/<img[^>]+?src=("|\')?([^\'">]*?)("|\')/si', $content, $matches)) {
		if( stristr($matches[2][0], 'http://') ){
			$result = $matches[2][0];
		}
	}
	return $result;
}

function MT_Cover_getRecentEntryStyle($target){
	global $pluginURL, $configVal;
	$data = Setting::fetchConfigVal($configVal);
	$data['cssSelect']	= !isset($data['cssSelect'])?1:$data['cssSelect'];
	if($data['cssSelect'] == 1){
		$target .= '<link rel="stylesheet" media="screen" type="text/css" href="' . $pluginURL . '/style.css" />' . CRLF;
	}
	return $target;
}

function MT_Cover_getRecentEntries_DataSet($DATA){
	$cfg = Setting::fetchConfigVal($DATA);

	MT_Cover_getRecentEntries_purgeCache(null, null);
	return true;
}

function MT_Cover_getRecentEntries_ConfigOut_ko($plugin) {
	$manifest = NULL;

	$manifest .= '<?xml version="1.0" encoding="utf-8"?>'.CRLF;
	$manifest .= '<config dataValHandler="MT_Cover_getRecentEntries_DataSet" >'.CRLF;
	$manifest .= '	<window width="500" height="345" />'.CRLF;
	$manifest .= '	<fieldset legend="표지 출력 설정">'.CRLF;
	$manifest .= '		<field title="출력 형태 :" name="coverMode" type="radio"  >'.CRLF;
	$manifest .= '			<op value="1" checked="checked"><![CDATA[단일 사용자&nbsp;]]></op>'.CRLF;
	$manifest .= '			<op value="2">다중 사용자</op>'.CRLF;
	$manifest .= '		</field>'.CRLF;
	$manifest .= '		<field title="페이징 적용 :" name="paging" type="radio"  >'.CRLF;
	$manifest .= '			<op value="1"><![CDATA[적용&nbsp;]]></op>'.CRLF;
	$manifest .= '			<op value="2" checked="checked">미적용</op>'.CRLF;
	$manifest .= '		</field>'.CRLF;
	$manifest .= '		<field title="스크린 샷 :" name="screenshot" type="radio"  >'.CRLF;
	$manifest .= '			<op value="1" checked="checked"><![CDATA[적용&nbsp;]]></op>'.CRLF;
	$manifest .= '			<op value="2">미적용</op>'.CRLF;
	$manifest .= '		</field>'.CRLF;
	$manifest .= '		<field title="스크린 샷 크기 :" name="screenshotSize" type="text" size="5" value="90" />'.CRLF;
	$manifest .= '		<field title="CSS 적용 :" name="cssSelect" type="radio"  >'.CRLF;
	$manifest .= '			<op value="1" checked="checked"><![CDATA[적용&nbsp;]]></op>'.CRLF;
	$manifest .= '			<op value="2">미적용</op>'.CRLF;
	$manifest .= '		</field>'.CRLF;
	$manifest .= '		<field title="본문 길이 :" name="contentLength" type="text" size="5" value="250" />'.CRLF;
	$manifest .= '	</fieldset>'.CRLF;
	$manifest .= '</config>'.CRLF;

	return $manifest;
}

function MT_Cover_getRecentEntries_ConfigOut_en($plugin) {

	$manifest = NULL;

	$manifest .= '<?xml version="1.0" encoding="utf-8"?>'.CRLF;
	$manifest .= '<config dataValHandler="MT_Cover_getRecentEntries_DataSet" >'.CRLF;
	$manifest .= '	<window width="500" height="345" />'.CRLF;
	$manifest .= '	<fieldset legend="Cover list setup">'.CRLF;
	$manifest .= '		<field title="List mode :" name="coverMode" type="radio"  >'.CRLF;
	$manifest .= '			<op value="1" checked="checked"><![CDATA[Single user&nbsp;]]></op>'.CRLF;
	$manifest .= '			<op value="2">Multi user</op>'.CRLF;
	$manifest .= '		</field>'.CRLF;
	$manifest .= '		<field title="Apply Pagination :" name="paging" type="radio"  >'.CRLF;
	$manifest .= '			<op value="1"><![CDATA[Apply&nbsp;]]></op>'.CRLF;
	$manifest .= '			<op value="2" checked="checked">Not apply</op>'.CRLF;
	$manifest .= '		</field>'.CRLF;
	$manifest .= '		<field title="Screenshot :" name="screenshot" type="radio"  >'.CRLF;
	$manifest .= '			<op value="1" checked="checked"><![CDATA[Apply&nbsp;]]></op>'.CRLF;
	$manifest .= '			<op value="2">Not apply</op>'.CRLF;
	$manifest .= '		</field>'.CRLF;
	$manifest .= '		<field title="Screenshot size:" name="screenshotSize" type="text" size="5" value="90" />'.CRLF;
	$manifest .= '		<field title="Apply CSS :" name="cssSelect" type="radio"  >'.CRLF;
	$manifest .= '			<op value="1" checked="checked"><![CDATA[Apply&nbsp;]]></op>'.CRLF;
	$manifest .= '			<op value="2">Not apply</op>'.CRLF;
	$manifest .= '		</field>'.CRLF;
	$manifest .= '		<field title="Content length :" name="contentLength" type="text" size="5" value="250" />'.CRLF;
	$manifest .= '	</fieldset>'.CRLF;
	$manifest .= '</config>'.CRLF;

	return $manifest;
}
?>
