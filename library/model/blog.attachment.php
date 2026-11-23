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

global $__gCacheAttachment;
$__gCacheAttachment = array();

function getAttachments($blogid, $parent, $orderBy = null, $sort='ASC') {
	global $__gCacheAttachment;
	if(isset($__gCacheAttachment) && !empty($__gCacheAttachment)) {
		if($result = getAttachmentsFromCache($blogid, $parent, 'parent')) {
			return $result;
		}
	}
	$attachments = array();
	$pool = DBModel::getInstance();
	$pool->reset('Attachments');
	$pool->setQualifier('blogid','equals',intval($blogid));
	$pool->setQualifier('parent','equals',intval($parent));
	if(!is_null($orderBy)) {
		$pool->setOrder($orderBy,$sort);
	}
	if($result = $pool->getAll('*')) {	
		foreach($result as $attachment) {
			array_push($attachments, $attachment);
			array_push($__gCacheAttachment, $attachment);
		}
	}
	return $attachments;
}

function getAttachmentsFromCache($blogid, $value, $filter = 'parent') {
	global $__gCacheAttachment;
	$result = array();
	if (!empty($__gCacheAttachment)) {
		foreach($__gCacheAttachment as $id => $info) {
			$row = array_search($value, $info);
			if ($row !== FALSE)
				array_push($result,$__gCacheAttachment[$id]);
		}
	}
	return $result;
}

function getAttachmentFromCache($blogid, $value, $filter = 'name') {
	global $__gCacheAttachment;
	if (!empty($__gCacheAttachment)) {
		foreach($__gCacheAttachment as $id => $info) {
			$row = array_search($value, $info);
			//if($row && $row == $filter) return $__gCacheAttachment[$id];
			if ($row !== FALSE)
				return $__gCacheAttachment[$id];
		}
	}
	return false;
}

function getAttachmentByName($blogid, $parent, $name) {
	global $database, $__gCacheAttachment;
	if(!isset($__gCacheAttachment))
		getAttachments($blogid, $parent);
	if($result = getAttachmentFromCache($blogid, $name, 'name') && $result['parent'] == $parent) {
		return $result;
	}
	return false;
}

function getAttachmentByOnlyName($blogid, $name) {
	global $__gCacheAttachment;
	if(!empty($__gCacheAttachment) && $result = getAttachmentFromCache($blogid, $name, 'name')) {
		return $result;
	} else {
		$pool = DBModel::getInstance();
		$pool->reset('Attachments');
		$pool->setQualifier('blogid','equals',$blogid);
		$pool->setQualifier('name','equals',$name,true);
		$newAttachment = $pool->getRow('*');
		array_push($__gCacheAttachment,$newAttachment);
		return $newAttachment;
	}
}

function getAttachmentByLabel($blogid, $parent, $label) {
	if ($parent === false)
		$parent = 0;
	$pool = DBModel::getInstance();
	$pool->reset('Attachments');
	$pool->setQualifier('blogid','equals',$blogid);
	$pool->setQualifier('parent','equals',$parent);
	$pool->setQualifier('label','equals',$label,true);
	return $pool->getRow('*');
}

function getAttachmentSize($blogid=null, $parent = null) {
	$pool = DBModel::getInstance();
	$pool->reset('Attachments');
	if(!empty($blogid)) {
		$pool->setQualifier('blogid','equals',$blogid);	
	}	
	if ($parent == 0 || !empty($parent)) {
		$pool->setQualifier('parent','equals',$parent);	
	}	
	return $pool->getCell('SUM(size)');
}

function getAttachmentSizeLabel($blogid=null, $parent = null) {
	//return number_format(ceil(getAttachmentSize($blogid,$parent)/1024)).' / '.number_format(ceil(getAttachmentSize($blogid)/1024)).' (KByte)';
	return number_format(ceil(getAttachmentSize($blogid,$parent)/1024)).' (KByte)';
}

function addAttachment($blogid, $parent, $file) {
	global $database;
	if (empty($file['name']) || ($file['error'] != 0))
		return false;
	$filename = $file['name'];

	$pool = DBModel::getInstance();
	$pool->reset('Attachments');
	$pool->setQualifier('blogid','equals',$blogid);
	$pool->setQualifier('parent','equals',$parent);
	$pool->setQualifier('label','equals',$filename,true);
	if($pool->getCell('count(*)') > 0) {
		return false;
	}
	$attachment = array();
	$attachment['parent'] = $parent ? $parent : 0;
	$attachment['label'] = Path::getBaseName($file['name']);
	$attachment['size'] = $file['size'];
	$extension = Misc::getFileExtension($attachment['label']);
	switch (strtolower($extension)) {
		case 'exe':case 'php':case 'sh':case 'com':case 'bat':
			$extension = 'xxx';
			break;
	}
	/* [보안] 위 목록은 .phtml·.phar·.php5 등을 놓친다 → Attachment::isExecutableName 기준으로 xxx (글 첨부·블로그 로고 업로드 경로) */
	if (Attachment::isExecutableName('.' . $extension))
		$extension = 'xxx';
	if ((strlen($extension) > 6) || ($extension == '')) {
		$extension = 'xxx';
	}
	// Textcube-NG: 그림 검사(r3-20) — svg 는 임시 파일을 정화본으로 바꾸고, 크기·가로세로(4096)·확장자 대 실제 형식 불일치는 거부한다
	// (Attachment::classifyUpload). svg 정화에 실패하면 확장자를 xxx 로 바꿔 그림이 아닌 파일로 저장한다
	$imageCheck = Attachment::classifyUpload($file['tmp_name'], $attachment['label'], $file['size'], false);
	if (!$imageCheck['ok'])
		return false;
	if (($imageCheck['kind'] === 'svg') && ($imageCheck['error'] === 'svgInvalid'))
		$extension = 'xxx';
	$path = __TEXTCUBE_ATTACH_DIR__."/$blogid";
	if (!is_dir($path)) {
		mkdir($path);
		if (!is_dir($path))
			return false;
		@chmod($path, 0777);
	}
	do {
		$attachment['name'] = random_int(1000000000, 9999999999) . ".$extension";
		$attachment['path'] = "$path/{$attachment['name']}";
	} while (file_exists($attachment['path']));
	if (!move_uploaded_file($file['tmp_name'], $attachment['path']))
		return false;
	@chmod($attachment['path'], 0666);
	if ($imageAttributes = @getimagesize($attachment['path'])) {
		$attachment['mime'] = $imageAttributes['mime'];
		$attachment['width'] = $imageAttributes[0];
		$attachment['height'] = $imageAttributes[1];
	} else {
		$attachment['mime'] = Misc::getMIMEType($extension);
		$attachment['width'] = 0;
		$attachment['height'] = 0;
	}
	// Textcube-NG: 그림 검사 결과 반영(r3-20) — svg 는 image/svg+xml 과 정화본의 가로세로(정화 실패면 application/octet-stream),
	// 일반 그림은 설정 imageReencode 가 true 일 때만 다시 인코딩한다. 파일이 바뀐 경우 크기를 다시 잰다. svg 의 PNG 변환본은 설정 svgRasterize 가 true 일 때만 만든다
	$refreshSize = false;
	if ($imageCheck['kind'] === 'svg') {
		if ($imageCheck['error'] === 'svgInvalid') {
			$attachment['mime'] = 'application/octet-stream';
			$attachment['width'] = 0;
			$attachment['height'] = 0;
		} else {
			$attachment['mime'] = 'image/svg+xml';
			$attachment['width'] = (int)$imageCheck['width'];
			$attachment['height'] = (int)$imageCheck['height'];
			makeAttachmentSVGRaster($blogid, $attachment['path'], $attachment['name']);
		}
		$refreshSize = true;
	} else if (($imageCheck['kind'] === 'raster') && Attachment::reencodeImage($attachment['path'], $imageCheck['type'])) {
		@chmod($attachment['path'], 0666);
		$refreshSize = true;
	}
	if ($refreshSize) {
		clearstatcache(true, $attachment['path']);
		$fileSize = @filesize($attachment['path']);
		if ($fileSize !== false)
			$attachment['size'] = $fileSize;
	}
	// 업로드 화면 알림용(DB 에는 넣지 않음): 정화로 지운 외부 참조 개수
	$attachment['svgExternal'] = (($imageCheck['kind'] === 'svg') && ($imageCheck['error'] === '')) ? (int)$imageCheck['svgExternal'] : 0;
	$attachment['label'] = UTF8::lessenAsEncoding($attachment['label'], 64);
	$attachment['mime']  = UTF8::lessenAsEncoding($attachment['mime'], 32);


	$pool->reset('Attachments');
	$pool->setAttribute('blogid',$blogid);
	$pool->setAttribute('parent',$attachment['parent']);
	$pool->setAttribute('name',$attachment['name'],true);
	$pool->setAttribute('label',$attachment['label'],true);
	$pool->setAttribute('mime',$attachment['mime'],true);
	$pool->setAttribute('size',$attachment['size'],true);
	$pool->setAttribute('width',$attachment['width']);
	$pool->setAttribute('height',$attachment['height']);
	$pool->setAttribute('attached',Timestamp::getUNIXtime());
	$pool->setAttribute('downloads',0);
	$pool->setAttribute('enclosure',0);
	$result = $pool->insert();
	if (!$result) {
		@unlink($attachment['path']);
		return false;
	}
	return $attachment;
}

// Textcube-NG: svg 첨부의 PNG 변환본(r3-20) — cache/thumbnail/{blogid}/{첨부 이름}.png ({n}.svg.png, 스킨 출력이 같은 경로를 본다).
// 같은 이름으로 다시 올린 경우의 옛 변환본은 먼저 지우고, 설정 svgRasterize 가 true 일 때만 새로 만든다(실패하면 정화한 svg 를 그대로 쓴다).
// 폴더 만들기는 framework/utils/Image.php 썸네일과 같은 방식
function makeAttachmentSVGRaster($blogid, $svgPath, $name) {
	$dst = __TEXTCUBE_CACHE_DIR__ . "/thumbnail/$blogid/$name.png";
	if (is_file($dst))
		@unlink($dst);
	if (Model_Context::getInstance()->getProperty('service.svgRasterize') !== true)
		return false;
	if (!is_dir(__TEXTCUBE_CACHE_DIR__ . "/thumbnail")) {
		@mkdir(__TEXTCUBE_CACHE_DIR__ . "/thumbnail");
		@chmod(__TEXTCUBE_CACHE_DIR__ . "/thumbnail", 0777);
	}
	if (!is_dir(__TEXTCUBE_CACHE_DIR__ . "/thumbnail/$blogid")) {
		@mkdir(__TEXTCUBE_CACHE_DIR__ . "/thumbnail/$blogid");
		@chmod(__TEXTCUBE_CACHE_DIR__ . "/thumbnail/$blogid", 0777);
	}
	return Attachment::rasterizeSVG($svgPath, $dst);
}

// Textcube-NG: 첨부를 지울 때 그 첨부에서 생긴 캐시 썸네일도 지운다(r3-24, R-438).
//   svg 래스터 cache/thumbnail/{blogid}/{name}.png 와 크기 변환 썸네일 {base}.w{W}-h{H}.{ext}(framework/utils/Image.php getImageResizer 의 이름 규칙)
//   glob 을 쓰지 않고 그 블로그 썸네일 폴더 하나를 scandir 해 정규식 전체 일치로만 고른다(이름의 glob 메타문자 문제 없음).
//   이름은 Validator::filename 을 통과한 것만 쓰고, 지우는 대상은 그 폴더 바로 아래 파일뿐이다.
function deleteAttachmentThumbnails($blogid, $names) {
	$blogid = intval($blogid);
	if ($blogid < 1 || empty($names))
		return;
	$dir = __TEXTCUBE_CACHE_DIR__ . "/thumbnail/$blogid";
	if (!is_dir($dir))
		return;
	$targets = array();
	foreach ($names as $name) {
		if (!Validator::filename($name))
			continue;
		$targets[$name] = true;
		// Textcube-NG: 표지 썸네일(MT_Meta_RecentPS_Default 의 coverPostThumbnail/th_<파일>)도 지움(r3-30 R-478)
		if (!is_link("$dir/coverPostThumbnail") && is_file("$dir/coverPostThumbnail/th_$name")) @unlink("$dir/coverPostThumbnail/th_$name");
		if (is_file("$dir/$name.png"))
			@unlink("$dir/$name.png");
	}
	if (empty($targets))
		return;
	$entries = @scandir($dir);
	if ($entries === false)
		return;
	foreach ($entries as $entry) {
		if (preg_match('/^(.+)\.w[0-9]+-h[0-9]+\.([[:alnum:]]+)$/D', $entry, $m) && isset($targets[$m[1] . '.' . $m[2]]) && is_file("$dir/$entry"))
			@unlink("$dir/$entry");
	}
}

// Textcube-NG: 블로그 전체 첨부 삭제 때 그 블로그의 썸네일 폴더(cache/thumbnail/{blogid}) 아래 파일을 모두 지운다. 그 폴더 밖과 하위 폴더는 건드리지 않는다(r3-24, R-438)
function deleteAttachmentThumbnailsAll($blogid) {
	$blogid = intval($blogid);
	if ($blogid < 1)
		return;
	$dir = __TEXTCUBE_CACHE_DIR__ . "/thumbnail/$blogid";
	if (!is_dir($dir) || is_link($dir))
		return;
	$entries = @scandir($dir);
	if ($entries === false)
		return;
	foreach ($entries as $entry) {
		if ($entry === '.' || $entry === '..')
			continue;
		if (is_file("$dir/$entry") || is_link("$dir/$entry"))
			@unlink("$dir/$entry");
	}
	// Textcube-NG: 하위 폴더 coverPostThumbnail 을 비우고 지워야 $dir 의 rmdir 이 성공함(r3-30 R-478)
	$cover = "$dir/coverPostThumbnail";
	if (is_dir($cover) && !is_link($cover)) {
		foreach ((array)@scandir($cover) as $f) {
			if ($f === '.' || $f === '..') continue;
			if (is_file("$cover/$f") || is_link("$cover/$f")) @unlink("$cover/$f");
		}
		@rmdir($cover);
	}
	@rmdir($dir);
}

function deleteAttachment($blogid, $parent, $name) {
	requireModel('blog.feed');
	if (!Validator::filename($name)) 
		return false;
	$origname = $name;
	$pool = DBModel::getInstance();
	$pool->reset('Attachments');
	$pool->setQualifier('blogid','equals',$blogid);
	/* [보안] 원본은 parent 인자를 쓰지 않고 이름만으로 지웠고, DELETE 문이 오류 없이 끝나면(지운 행이 없어도) 파일을 지웠다
	   → 요청한 parent(글·임시 첨부·로고 -1)의 행을 실제로 지운 경우에만 파일을 지운다 */
	$pool->setQualifier('parent','equals',intval($parent));
	$pool->setQualifier('name','equals',$name,true);
	if(intval($pool->delete(null, 'count')) > 0) {
		if( file_exists( __TEXTCUBE_ATTACH_DIR__."/$blogid/$origname") ) {
			@unlink(__TEXTCUBE_ATTACH_DIR__."/$blogid/$origname");
		}
		deleteAttachmentThumbnails($blogid, array($origname));	// Textcube-NG: svg 래스터·크기 변환 썸네일도 지운다(r3-24, R-438)
		clearFeed();
		return true;
	}
	return false;
}

function copyAttachments($blogid, $originalEntryId, $targetEntryId) {
	global $database;
	$path = __TEXTCUBE_ATTACH_DIR__."/$blogid";
	$attachments = getAttachments($blogid, $originalEntryId);
	if(empty($attachments)) return true;

	$pool = DBModel::getInstance();
	$pool->reset('Entries');
	$pool->setQualifier('blogid','equals',$blogid);
	$pool->setQualifier('id','equals',$originalEntryId);
	if(!$pool->getCell('id')) return 2;	// original entry does not exists;
	/* Textcube-NG: DBModel::setQualifier() 는 같은 필드 조건을 덮어쓰지 않고 AND 로 더한다(id = 원본 AND id = 대상 → 항상 3).
	   원본부터 첨부가 있는 서식을 불러오면 실패했다 → 원본 글 조건을 지우고 대상 글을 확인 */
	$pool->unsetQualifier('id');
	$pool->setQualifier('id','equals',$targetEntryId);
	if(!$pool->getCell('id')) return 3; // target entry does not exists;
	
	foreach($attachments as $attachment) {
		$extension = Misc::getFileExtension($attachment['label']);
		$originalPath = "$path/{$attachment['name']}";
		do {
			$attachment['name'] = random_int(1000000000, 9999999999) . ".$extension";
			$attachment['path'] = "$path/{$attachment['name']}";
		} while (file_exists($attachment['path']));
		if(!copy($originalPath, $attachment['path'])) return 4; // copy failed.
	
		$pool->reset('Attachments');
		$pool->setAttribute('blogid',$blogid);
		$pool->setAttribute('parent',$targetEntryId);
		$pool->setAttribute('name',$attachment['name'],true);
		$pool->setAttribute('label',$attachment['label'],true);
		$pool->setAttribute('mime',$attachment['mime'],true);
		$pool->setAttribute('size',$attachment['size'],true);
		$pool->setAttribute('width',$attachment['width']);
		$pool->setAttribute('height',$attachment['height']);
		$pool->setAttribute('attached',Timestamp::getUNIXtime());
		$pool->setAttribute('downloads',0);
		$pool->setAttribute('enclosure',0);		
		if(!$pool->insert()) {
			return false;
		}
	}
	return true;
}
function deleteTotalAttachment($blogid) {
	requireModel('blog.feed');
	deleteAttachmentThumbnailsAll($blogid);	// Textcube-NG: 그 블로그의 캐시 썸네일도 지운다(r3-24, R-438)
	$d = dir(__TEXTCUBE_ATTACH_DIR__."/$blogid");
	while($file = $d->read()) {
		if(is_file(__TEXTCUBE_ATTACH_DIR__."/$blogid/$file"))
			unlink(__TEXTCUBE_ATTACH_DIR__."/$blogid/$file");
	}
	rmdir(__TEXTCUBE_ATTACH_DIR__."/$blogid/");
	clearFeed();
	return true;
}

function deleteAttachmentMulti($blogid, $parent, $names) {
	requireModel('blog.feed');
	$pool = DBModel::getInstance();
	$files = explode('!^|', $names);
	/* Textcube-NG: 원본은 DELETE 문이 오류 없이 끝나면(조건에 맞는 행이 없어도) 파일을 지우고 항상 true 를 돌려줘,
	   다른 글(parent)의 같은 이름 파일이 디스크에서만 지워지거나 DB 잠금으로 지우지 못해도 편집 화면 목록에서 사라졌다
	   → 행을 실제로 지운 경우에만 파일을 지우고, 요청한 이름을 모두 지웠을 때만 true */
	$deleted = 0;
	$failed = 0;
	$deletedNames = array();
	foreach ($files as $name) {
		if ($name == '')
			continue;
		if (!Validator::filename($name)) {
			$failed++;
			continue;
		}
		$origname = $name;
		$pool->reset('Attachments');
		$pool->setQualifier('blogid','eq',$blogid);
		$pool->setQualifier('parent','eq',intval($parent));
		$pool->setQualifier('name','eq',$name,true);
		if(intval($pool->delete(null, 'count')) > 0) {
			$deleted++;
			if (file_exists(__TEXTCUBE_ATTACH_DIR__."/$blogid/$origname"))
				@unlink(__TEXTCUBE_ATTACH_DIR__."/$blogid/$origname");
			$deletedNames[] = $origname;
		} else {
			$failed++;
		}
	}
	if (!empty($deletedNames))
		deleteAttachmentThumbnails($blogid, $deletedNames);	// Textcube-NG: svg 래스터·크기 변환 썸네일도 지운다(r3-24, R-438)
	clearFeed();
	return ($deleted > 0 && $failed == 0);
}



function deleteAttachments($blogid, $parent) {
	$attachments = getAttachments($blogid, $parent);
	foreach ($attachments as $attachment)
		deleteAttachment($blogid, $parent, $attachment['name']);
}

/* [보안] Textcube-NG: 새 글(저장 전, 편집 화면 entryId 0) 임시 첨부를 사용자별로 나눈다.
   원본은 parent=0 하나를 블로그 전체가 같이 써서 팀블로그 구성원이 다른 사람의 임시 첨부를 보고·지우고·자기 글로 가져갈 수 있었다.
   임시 첨부 parent = -(userid+1) (userid 1 → -2). -1 은 블로그 로고(blog.blogSetting), 0 은 업그레이드 전 임시 첨부(관리자만 정리) */
function getTemporaryAttachmentParent($userid) {
	$userid = intval($userid);
	if ($userid <= 0)
		return false;
	return -($userid + 1);
}

/* 사용자($userid)의 임시 첨부를 저장된 글($entryId)로 옮긴다(addEntry·updateEntry·글 저장 핸들러) */
function moveTemporaryAttachments($blogid, $userid, $entryId) {
	$temporary = getTemporaryAttachmentParent($userid);
	$entryId = intval($entryId);
	if ($temporary === false || $entryId <= 0)
		return false;
	$pool = DBModel::getInstance();
	$pool->reset('Attachments');
	$pool->setAttribute('parent', $entryId);
	$pool->setQualifier('blogid', 'equals', intval($blogid));
	$pool->setQualifier('parent', 'equals', $temporary);
	return $pool->update();
}

/* 현재 사용자가 이 parent 의 첨부를 다룰 수 있는지. 글(parent > 0)은 이 블로그의 글이고 편집자(group.editors) 이상이거나 작성자
   (interface/owner/entry/edit·update 와 같은 기준), 그 밖에는 현재 사용자의 임시 첨부만 */
function canEditAttachmentParent($blogid, $parent) {
	$parent = intval($parent);
	if ($parent > 0) {
		$pool = DBModel::getInstance();
		$pool->reset('Entries');
		$pool->setQualifier('blogid', 'equals', intval($blogid));
		$pool->setQualifier('id', 'equals', $parent);
		$pool->setQualifier('draft', 'equals', 0);
		$owner = $pool->getCell('userid');
		if (is_null($owner) || $owner === false)
			return false;
		return (Acl::check('group.editors') || intval($owner) == getUserId());
	}
	$temporary = getTemporaryAttachmentParent(getUserId());
	return ($temporary !== false && $parent === $temporary);
}

/* 편집 화면·첨부 핸들러의 entryId(URL·GET) → 첨부 parent. 0 이면 현재 사용자의 임시 첨부, 양수면 다룰 수 있는 글일 때 그 id,
   그 밖에는(정수가 아님·음수·다른 블로그의 글·다른 사람의 글) false */
function getAttachmentParentForEdit($blogid, $entryId) {
	$entryId = filter_var($entryId, FILTER_VALIDATE_INT);
	if ($entryId === false || $entryId < 0)
		return false;
	if ($entryId == 0)
		return getTemporaryAttachmentParent(getUserId());
	return canEditAttachmentParent($blogid, $entryId) ? $entryId : false;
}

function downloadAttachment($name) {
	requireModel('blog.feed');
	$pool = DBModel::getInstance();
	$blogid = getBlogId();

	$pool->reset('Attachments');
	$pool->setQualifier('blogid','eq',$blogid);
	$pool->setQualifier('name','eq',$name,true);
	$downloadCount = $pool->getCell('downloads');
	if($downloadCount !== false) {	
		$pool->reset('Attachments');
		$pool->setAttribute('downloads',$downloadCount + 1);
		$pool->setQualifier('blogid','eq',$blogid);
		$pool->setQualifier('name','eq',$name,true);
		$pool->update();
	}
}

function setEnclosure($name, $order) {
	requireModel('blog.feed');
	requireModel('blog.attachment');

	$pool = DBModel::getInstance();
	$blogid = getBlogId();

	$pool->reset('Attachments');
	$pool->setQualifier('blogid','eq',$blogid);
	$pool->setQualifier('name','eq',$name,true);
	$parent = $pool->getCell('parent');
	if($parent !== null) {
		$pool->setAttribute('enclosure',0);
		$pool->setQualifier('parent','eq',$parent);
		$pool->unsetQualifier('name');
		$pool->update();
		if($order) {
			clearFeed();
			$pool->setAttribute('enclosure',1);
			$pool->unsetQualifier('parent');
			$pool->setQualifier('name','eq',$name,true);
			return $pool->update();
		} else {
			return 0;
		}
	} else {
		return 3;	
	} 	
}

function getEnclosure($entry) {
	/* [보안] 음수 parent 는 현재 사용자의 임시 첨부(편집 화면의 새 글)만 — 로고(-1)·다른 사용자의 임시 첨부는 null */
	if ($entry < 0 && intval($entry) !== getTemporaryAttachmentParent(getUserId()))
		return null;
	$pool = DBModel::getInstance();
	
	$pool->reset('Attachments');
	$pool->setQualifier('blogid','eq',getBlogId());
	$pool->setQualifier('parent','eq',$entry);
	$pool->setQualifier('enclosure','eq',1);
	return $pool->getCell('name');
}

function return_bytes($val) {
    $val = trim($val);
    $last = strtolower($val[strlen($val)-1]);
    // Textcube-NG: 단위 문자를 떼고 곱한다. PHP 7.1 부터 '2M' 처럼 앞부분만 숫자인 문자열의 산술은 E_NOTICE, 8.0 부터 E_WARNING(값은 같음)(r4-2)
    if ($last == 'g' || $last == 'm' || $last == 'k') {
        $val = substr($val, 0, -1);
    }
    switch($last) {
        // The 'G' modifier is available since PHP 5.1.0
        case 'g':
            $val *= 1024;
        case 'm':
            $val *= 1024;
        case 'k':
            $val *= 1024;
    }

    return $val;
}
?>
