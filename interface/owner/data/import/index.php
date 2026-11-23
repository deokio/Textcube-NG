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
ini_set('display_errors', 'off');
$IV = array(
	'POST' => array(
		'importFrom' => array(array('server', 'uploaded', 'web')),
		'backupURL' => array('url', 'default' => null),
		'correctData' => array(array('on'), 'default' => null)
	),
	'FILES' => array(
		'backupPath' => array('file', 'default' => array() )
	)
);
require ROOT . '/library/preprocessor.php';
requireStrictRoute();
/* [보안] 원본은 이 핸들러에서 권한을 확인하지 않고 ACL 표에 의존했는데 표에 /owner/data 가 없어 전 구성원에게 허용됐다 →
   표 보완(framework/boot/30-Auth.php)과 함께 핸들러에서도 관리자 이상 확인 */
requirePrivilege('group.administrators');
?>
<!DOCTYPE html PUBLIC "-//W3C//DTD XHTML 1.1//EN" "http://www.w3.org/TR/xhtml11/DTD/xhtml11.dtd">
<html xmlns="http://www.w3.org/1999/xhtml" xml:lang="ko">
<head>
	<title>Textcube Data Importing</title>
	<meta http-equiv="Content-Type" content="text/html; charset=utf-8" />
	<script type="text/javascript">
		//<![CDATA[
			var pi = window.parent.document.getElementById("progressIndicator");
			var pt = window.parent.document.getElementById("progressText");
			var pts = window.parent.document.getElementById("progressTextSub");
		//]]>
	</script>
</head>
<body>
<?php
function finish($error = null) {
	global $migrational, $blogid, $database;
?>
	<script type="text/javascript">
		//<![CDATA[
<?php
	if ($error) {
?>
			//pi.style.backgroundColor = "red";
			alert("<?php echo $error;?>");
<?php
	} else {
?>
			alert("<?php echo ($migrational ? _t('성공적으로 이주되었습니다.') : _t('성공적으로 복원되었습니다.'));?>");
<?php
	}
?>
			window.parent.document.getElementById("progressDialog").style.display = "none";
			window.parent.document.getElementById("progressDialogTitle").innerHTML = "";
			window.parent.document.getElementById("progressText").innerHTML = "";
			window.parent.document.getElementById("progressTextSub").innerHTML = "";
		//]]>
	</script>
<?php
	$activeEditors = POD::queryColumn("SELECT DISTINCT contenteditor FROM {$database['prefix']}Entries WHERE blogid = $blogid");
	$activeFormatters = POD::queryColumn("SELECT DISTINCT contentformatter FROM {$database['prefix']}Entries WHERE blogid = $blogid");
	if(!empty($activeEditors)) {foreach($activeEditors as $editor) activatePlugin($editor);}
	if(!empty($activeFormatters)) {foreach($activeFormatters as $formatter) activatePlugin($formatter);}
	echo _t('완료.');
?>
</body>
</html>
<?php
	exit;
}
$lastProgress = 0;
$lastProgressText = null;
$lastProgressTextSub = null;

function setProgress($progress, $text = null, $sub = null) {
	global $lastProgress, $lastProgressText, $lastProgressTextSub;
	$progress = intval($progress);
	$diff = '';
	if (isset($progress) && ($progress != $lastProgress)) {
		$lastProgress = $progress;
		$diff .= 'pi.style.width = "' . $progress . '%";';
	}
	if (isset($text) && ($text != $lastProgressText)) {
		$lastProgressText = $text;
		$diff .= 'pt.innerHTML = "' . $text . '";';
		if (!isset($sub)) {
			$lastProgressTextSub = '';
			$diff .= 'pts.innerHTML = "";';
		}
	}
	if (isset($sub) && ($sub != $lastProgressTextSub)) {
		$lastProgressTextSub = $sub;
		$diff .= 'pts.innerHTML = "(' . $sub . ')";';
	}
	if (!empty($diff)) {
?>
<script type="text/javascript">
	//<![CDATA[
		<?php echo $diff;?>
	//]]>
</script>
<?php
		flush();
	}
}
// 첨부·배너 파일 기록: DB 등록에 성공했고($accepted) 이름이 안전한 경우에만 첨부 폴더에 기록하며, 스트림은 항상 닫는다.
function importAttachmentStream(&$content, $name, $accepted) {
	global $blogid;
	if (empty($content['.stream']))
		return false;
	$result = false;
	if ($accepted && !empty($name) && preg_match('/^\w+(\.\w+)*$/D', $name) && !Attachment::isExecutableName($name)) {
		Attachment::confirmFolder();
		$path = __TEXTCUBE_ATTACH_DIR__ . "/$blogid/$name";
		if ($result = Base64Stream::decode($content['.stream'], $path)) {
			Attachment::adjustPermission($path);
			// Textcube-NG: svg 는 가져올 때도 정화한다(r3-20). 이름은 그대로 두고 크기·가로세로·불일치로 거부하지 않는다(가져오기, 알림 없음).
			// 정화에 실패하면 mime 을 application/octet-stream 으로 둔다. 첨부 행은 파일보다 먼저 들어가므로 정화 뒤 크기·mime·가로세로를 고친다
			// (배너 등 같은 이름의 첨부 행이 없으면 바뀌는 행이 없다)
			if (strtolower(substr($name, -4)) === '.svg') {
				$imageCheck = Attachment::classifyUpload($path, $name, 0, true);
				clearstatcache(true, $path);
				$pool = DBModel::getInstance();
				$pool->reset('Attachments');
				$pool->setAttribute('size', (int)@filesize($path));
				if ($imageCheck['error'] === 'svgInvalid') {
					$pool->setAttribute('mime', 'application/octet-stream', true);
					$pool->setAttribute('width', 0);
					$pool->setAttribute('height', 0);
				} else {
					$pool->setAttribute('mime', 'image/svg+xml', true);
					$pool->setAttribute('width', (int)$imageCheck['width']);
					$pool->setAttribute('height', (int)$imageCheck['height']);
				}
				$pool->setQualifier('blogid', 'equals', (int)$blogid);
				$pool->setQualifier('name', 'equals', $name, true);
				$pool->update();
			}
		}
	}
	fclose($content['.stream']);
	unset($content['.stream']);
	return $result;
}
// 가져올 첨부·배너 이름: 첨부 이름 형식(/^\w+(\.\w+)*$/, 끝 개행 불허)이면 그대로 쓰고, 아니면 업로드(addAttachment)와 같은
// '난수.확장자' 이름을 새로 만든다(확장자가 6자 초과·비어 있음·\w 외 문자 포함·실행형이면 xxx).
// 업로드로 생긴 '123.tar-gz'·'123.한글' 같은 이름이 Attachment::add()·BlogSetting::save() 의 이름 검사(Validator::filename)에
// 걸려 DB 행과 파일이 함께 빠지던 문제 수정. 바뀐 이름은 호출부의 기존 이름 변경 흐름이 본문 참조에도 반영한다.
function importAttachmentName($name) {
	global $blogid;
	if (!is_string($name) || ($name === '') || preg_match('/^\w+(\.\w+)*$/D', $name))
		return $name;
	$extension = Misc::getFileExtension($name);
	if ((strlen($extension) > 6) || ($extension == '') || !preg_match('/^\w+$/D', $extension) || Attachment::isExecutableName('.' . $extension))
		$extension = 'xxx';
	do {
		$generated = random_int(1000000000, 9999999999) . ".$extension";
	} while (file_exists(__TEXTCUBE_ATTACH_DIR__ . "/$blogid/$generated") || Attachment::doesExist($generated));
	return $generated;
}
// 가져온 본문의 첨부 이름 변경: 이름 전체가 일치하는 곳만 바꾼다. 앞이 이름 문자(\w·'.'·'-'·UTF-8 바이트)이거나 뒤가
// 이름 문자 또는 '.'+\w 이면 다른 이름의 일부로 보고 건너뛴다. (str_replace 는 'a.jpg' 를 바꿀 때 같은 본문의 'ba.jpg'
// 참조까지 'b<새 이름>' 으로 바꿔 깨뜨렸다.) 인자 순서는 str_replace 와 같고, 정규식 처리 실패 시에는 str_replace 로 처리한다.
function importAttachmentReplace($from, $to, $content) {
	if (!is_string($content) || !is_string($from) || ($from === '') || ($from === $to))
		return $content;
	$replaced = preg_replace_callback('/(?<![\w.\-\x80-\xff])' . preg_quote($from, '/') . '(?![\w\-\x80-\xff]|\.\w)/', function ($match) use ($to) { return $to; }, $content);
	return is_null($replaced) ? str_replace($from, $to, $content) : $replaced;
}
// 블로그 번호가 바뀐 가져오기의 첨부 직접 경로: 본문의 '[주소][서비스 경로]/attach/{번호}/{이름}' 에서 번호가 가져오는 블로그 번호와
// 다르고 이름이 이번에 가져온 첨부(원래 이름·바뀐 이름, rawurlencode 형태 포함)이면 '[##_ATTACH_PATH_##]/{가져온 이름}' 으로 바꾼다.
// 백업에는 원래 블로그 번호가 없어 다른 번호의 블로그로 복원·이전하면 이 경로가 그대로 남아 첨부가 깨졌다.
// [##_ATTACH_PATH_##] 는 편집기가 저장하는 형식이며 서식기(FM_default_format·TTML·Markdown·Textile)가 현재 서비스 경로·블로그 번호로 출력한다.
// 주소(scheme://호스트, //호스트)가 붙은 경로는 호스트가 이 블로그 주소($hosts, importAttachmentRelinkHosts())일 때만 바꾸고, 그 밖의
// 호스트는 다른 블로그·사이트의 경로로 보아 그대로 둔다(IPv6 주소 '[…]' 호스트 포함). 주소 없는 경로(/…/attach/…)는 원래 서비스의
// 경로이므로 바꾼다.
// 같은 블로그 번호의 경로와 가져오지 않은 이름은 그대로 둔다. 이름은 공백·따옴표·괄호·,;=|?#&/ 앞에서 끝나고, 끝의 .:! 는
// 이름에 없으면 떼어 보며, ?·# 이후는 유지한다.
function importAttachmentRelink($content, $blogid, $names, $hosts = array()) {
	if (!is_string($content) || empty($names) || (strpos($content, 'attach/') === false))
		return $content;
	$replaced = preg_replace_callback('~(?<![^\s"\'<>()\[\]=,;|])((?:[a-zA-Z][a-zA-Z0-9+.\-]*:)?//[^\s"\'<>()\[\]=,;|?#&/]*\[[0-9a-zA-Z:.%]+\][^\s"\'<>()\[\]=,;|?#&]*/|(?:[^\s"\'<>()\[\]=,;|?#&]*/)?)attach/(\d+)/([^\s"\'<>()\[\]=,;|?#&/]+)~', function ($match) use ($blogid, $names, $hosts) {
		if ($match[2] === (string)$blogid)
			return $match[0];
		if (preg_match('~^(?:[a-z][a-z0-9+.\-]*:)?//([^/]*)~i', $match[1], $authority)
			&& !importAttachmentRelinkHostMatch(importAttachmentRelinkHostName($authority[1]), $hosts))
			return $match[0];
		foreach (array($match[3], rtrim($match[3], '.:!')) as $token) {
			foreach (array($token, rawurldecode($token)) as $name) {
				if (($name !== '') && isset($names[$name]))
					return '[##_ATTACH_PATH_##]/' . $names[$name] . substr($match[3], strlen($token));
			}
		}
		return $match[0];
	}, $content);
	return is_null($replaced) ? $content : $replaced;
}
// 호스트 비교용 정규화: 소문자, 사용자 정보·포트·끝의 '.' 제외(IPv6 주소는 [] 포함)
function importAttachmentRelinkHostName($host) {
	$host = strtolower(trim((string)$host));
	if (($at = strrpos($host, '@')) !== false)
		$host = substr($host, $at + 1);
	return preg_match('/^(\[[^\]\/]*\]|[^:\/]*)/', $host, $match) ? rtrim($match[1], '.') : '';
}
// $hosts 의 항목과 같으면 이 블로그 주소. '.' 으로 끝나는 항목(도메인형 주소의 '이름.')은 그것으로 시작하는 더 긴 호스트만 일치
function importAttachmentRelinkHostMatch($host, $hosts) {
	if ($host === '')
		return false;
	foreach ($hosts as $known) {
		if ((substr($known, -1) === '.')
			? ((strlen($host) > strlen($known)) && (strncmp($host, $known, strlen($known)) === 0))
			: ($host === $known))
			return true;
	}
	return false;
}
// 이 블로그 주소의 호스트(블로그 설정 기준). 현재 설정: 서비스 도메인, 서비스 주소($service['serviceURL']), 도메인형 주소
// '현재 이름.서비스 도메인', 현재 2차 도메인. 백업 설정(<setting>, 가져올 때 기록): 원래 2차 도메인, 원래 이름의 도메인형 주소
// '원래 이름.'(원래 서비스 도메인은 백업에 없음). 단일·경로형 블로그의 원래 서비스 도메인도 백업에 없으므로, 다른 서버의 백업이면
// 그 호스트가 붙은 경로는 바꾸지 않는다(주소 없는 경로는 바꿈).
function importAttachmentRelinkHosts() {
	global $blogid, $importedBlogSetting;
	$context = Model_Context::getInstance();
	$current = Setting::getBlogSettingsGlobal($blogid);
	$domain = importAttachmentRelinkHostName($context->getProperty('service.domain'));
	$hosts = array($domain, importAttachmentRelinkHostName(parse_url((string)$context->getProperty('service.serviceURL'), PHP_URL_HOST)));
	if (isset($current['name']) && ((string)$current['name'] !== '') && ($domain !== ''))
		$hosts[] = importAttachmentRelinkHostName($current['name']) . '.' . $domain;
	if (isset($current['secondaryDomain']))
		$hosts[] = importAttachmentRelinkHostName($current['secondaryDomain']);
	if (isset($importedBlogSetting['secondaryDomain']))
		$hosts[] = importAttachmentRelinkHostName($importedBlogSetting['secondaryDomain']);
	if (isset($importedBlogSetting['name']) && (importAttachmentRelinkHostName($importedBlogSetting['name']) !== ''))
		$hosts[] = importAttachmentRelinkHostName($importedBlogSetting['name']) . '.';
	return array_values(array_unique(array_filter($hosts, function ($host) { return ($host !== '') && ($host !== '.') && (strpos($host, '..') === false); })));
}
// 가져온 글·공지·키워드 가운데 본문에 'attach/' 가 있는 것(가져올 때 기록)을 모든 첨부를 가져온 뒤 다시 열어 importAttachmentRelink() 로
// 바꾼다(다른 글의 첨부를 가리키는 경로 포함). 갱신은 첨부 이름 변경과 같은 open()·update() 흐름을 쓴다.
function importAttachmentRelinkEntries() {
	global $blogid, $importedEntries, $importedAttachments;
	if (empty($importedEntries) || empty($importedAttachments))
		return;
	$hosts = importAttachmentRelinkHosts();
	foreach ($importedEntries as $imported) {
		list($class, $id) = $imported;
		$field = ($class == 'Keyword') ? 'description' : 'content';
		$entry = new $class();
		if ($entry->open($id, 'id, content, contentformatter, contenteditor, starred, modified')) {
			$content = importAttachmentRelink($entry->$field, $blogid, $importedAttachments, $hosts);
			if ($content !== $entry->$field) {
				$entry->$field = $content;
				if ($class == 'Post')
					$entry->loadTags();
				$entry->update();
			}
			$entry->close();
		}
		unset($entry);
	}
}
switch (@$_POST['importFrom']) {
	default:
		finish(_t('잘못된 요청입니다.'));
		break;
	case 'server':
		$backup = __TEXTCUBE_CACHE_DIR__."/backup/$blogid.xml";
		break;
	case 'uploaded':
		if (@$_FILES['backupPath']['error'] !== 0)
			finish(_t('업로드가 취소되었거나 업로드 용량 제한을 초과하였습니다.'));
		$backup = $_FILES['backupPath']['tmp_name'];
		break;
	case 'web':
		if (!file_exists(__TEXTCUBE_CACHE_DIR__.'/import')) {
			mkdir(__TEXTCUBE_CACHE_DIR__.'/import');
			@chmod(__TEXTCUBE_CACHE_DIR__.'/import', 0777);
		}
		if (!is_dir(__TEXTCUBE_CACHE_DIR__.'/import')) {
			finish(_t('백업파일을 저장할 공간에 권한이 없습니다.'));
		}
		$request = new HTTPRequest($_POST['backupURL']);
		// [보안] 내부 주소(사설·루프백·클라우드 메타데이터 등)로 요청하지 않도록 공인 주소의 80/443 만(리다이렉트도 확인)
		$request->publicOnly = true;
		$backup = __TEXTCUBE_CACHE_DIR__."/import/$blogid.xml";
		$request->pathToSave = $backup;
		if (!$request->send()) {
			finish(_t('백업파일이 손상되었거나 가져올 수 없습니다.'));
		}
		break;
}
$migrational = false;
$items = 0;
$item = 0;
// 첨부 직접 경로 변환(importAttachmentRelinkEntries)용: 본문에 'attach/' 가 있는 가져온 글·공지·키워드, 가져온 첨부 이름(원래·바뀐 이름 → 가져온 이름)
$importedEntries = array();
$importedAttachments = array();
// 첨부 직접 경로의 호스트 판별(importAttachmentRelinkHosts)용: 백업 <setting> 의 원래 블로그 이름·2차 도메인(블로그 설정에는 반영하지 않음)
$importedBlogSetting = array();
$xmls = new XMLStruct();
set_time_limit(0);
setProgress(0, _t('백업파일을 확인하고 있습니다.'));
$xmls->setStream('/blog/setting/banner/content');
$xmls->setStream('/blog/post/attachment/content');
$xmls->setStream('/blog/notice/attachment/content');
$xmls->setStream('/blog/keyword/attachment/content');
$xmls->setConsumer('scanner');
if (!$xmls->openFile($backup, Validator::getBool(@$_POST['correctData']))) {
	finish(_f('백업파일의 %1번째 줄이 올바르지 않습니다.', $xmls->error['line']));
}
$xmls->close();
if ($items == 0)
	finish(_t('백업파일에 복원할 데이터가 없습니다.'));
if (!$migrational) {
	setProgress(0, _t('복원 위치를 준비하고 있습니다.'));
	DataMaintenance::removeAll(false);
	CacheControl::flushAll();
}
$xmls->setConsumer('importer');
if (!$xmls->openFile($backup, Validator::getBool(@$_POST['correctData']))) {
	finish(_t('백업파일이 올바르지 않습니다.'));
}

$xmls->close();
importAttachmentRelinkEntries();
requireModel('blog.category');	// Textcube-NG: R-592 — 가져온 분류·글로 블로그 전체 글 수(자손 합산)·경로를 한 번 다시 센다
clearCategoryCache();
updateEntriesOfCategory($blogid);
if (file_exists(__TEXTCUBE_CACHE_DIR__."/import/$blogid.xml"))
	@unlink(__TEXTCUBE_CACHE_DIR__."/import/$blogid.xml");
setProgress(100, _t('완료되었습니다.'));
finish();

/*@callback@*/
// Textcube-NG: R-592 — 새 백업(r4 이후)에 든 분류 공개 설정·body id(사용자 결정 ⑩ 안 A). 옛 백업에는 없어 기본값(공개·tt-body-category) 그대로
function importCategoryExtra($category, &$node) {
	if (isset($node['visibility'][0]['.value']) && in_array((string)$node['visibility'][0]['.value'], array('1', '2'), true))
		$category->visibility = (int)$node['visibility'][0]['.value'];
	// body id 는 스킨 <body id="...">·글 class 에 나간다. 출력은 r4-16 부터 escape 하지만, 분류 관리(r4-16)와 같은 규칙으로 영문·숫자·_·- 1~20자만 받고, 아니면 버린다(기본 tt-body-category, RC3 1-2. 주석은 11-24 dojoref 2.7 에 따라 고침)
	if (isset($node['bodyId'][0]['.value']) && is_string($node['bodyId'][0]['.value']) && preg_match('/\A[A-Za-z0-9_-]{1,20}\z/', $node['bodyId'][0]['.value']))
		$category->bodyid = $node['bodyId'][0]['.value'];
}

// Textcube-NG: R-592 — 하위 분류를 깊이만큼 재귀로 복원. 경로(label)가 255자를 넘는 분류와 10단을 넘는 분류는 건너뛰고 기록한다(그 분류의 글은 분류 없음으로 들어감)
function importCategoryChildren(&$node, $parentId, $depth) {
	requireModel('blog.category');
	for ($i = 0; $i < count($node['category']); $i++) {
		$cursor = & $node['category'][$i];
		if ($depth > 10) {
			user_error(__LINE__ . ' category depth');
			continue;
		}
		$childCategory = new Category();
		$childCategory->parent = $parentId;
		$childCategory->name = $cursor['name'][0]['.value'];
		$childCategory->priority = $cursor['priority'][0]['.value'];
		importCategoryExtra($childCategory, $cursor);
		$parentLabel = Category::getLabel($parentId);
		if (!is_null($parentLabel) && isCategoryLabelTooLong($parentLabel . '/' . UTF8::lessenAsEncoding(trim($childCategory->name), 127))) {
			user_error(__LINE__ . ' category label');
			continue;
		}
		if (!$childCategory->add()) {
			user_error(__LINE__ . $childCategory->error);
			continue;
		}
		if ($depth > getCategoryMaxDepth(getBlogId()))
			Setting::setBlogSettingGlobal('categoryMaxDepth', $depth);
		if (isset($cursor['category']))
			importCategoryChildren($cursor, $childCategory->id, $depth + 1);
	}
}
function scanner($path, $node, $line) {
	global $migrational, $items;
	switch ($path) {
		case '/blog':
			if (!preg_match('/^tattertools\/1\.[01]$/', @$node['.attributes']['type'])
			 && !preg_match('/^textcube\/1\.[01]$/', @$node['.attributes']['type']))
				finish(_t('지원하지 않는 백업파일입니다.'));
			$migrational = Validator::getBool(@$node['.attributes']['migrational']);
			return true;
		case '/blog/setting/banner/content':
		case '/blog/post/attachment/content':
		case '/blog/notice/attachment/content':
		case '/blog/keyword/attachment/content':
			if (!empty($node['.stream'])) {
				fclose($node['.stream']);
				unset($node['.stream']);
			}
			return true;
		case '/blog/setting':
		case '/blog/category':
		case '/blog/post':
		case '/blog/notice':
		case '/blog/keyword':
		case '/blog/linkCategories':
		case '/blog/link':
		case '/blog/logs/referer':
		case '/blog/statistics/referer':
		case '/blog/statistics/visits':
		case '/blog/statistics/daily':
		case '/blog/skin':
		case '/blog/plugin':
		case '/blog/commentsNotified/comment':
		case '/blog/commentsNotifiedSiteInfo/site':
		case '/blog/guestbook/comment':
		case '/blog/filter':
		case '/blog/feed':
		case '/blog/line':
			// Textcube-NG: 글·공지·키워드의 published·created·modified 가 0~2147483647 의 정수가 아니면 가져오기를 거부한다. 원본은 상한이 없어 2^31 이상도 저장했고 "1e3"·"0x1A" 같은 값은 PHP 버전마다 결과가 달랐다. 기존 데이터를 지우기 전인 확인 단계에서 멈추고, 요소가 없으면 원본처럼 검사하지 않는다(v3.201)
			if ($path == '/blog/post' || $path == '/blog/notice' || $path == '/blog/keyword') {
				foreach (array('published', 'created', 'modified') as $timeField) {
					if (isset($node[$timeField][0]['.value']) && !Validator::isInteger($node[$timeField][0]['.value'], 0, 2147483647))
						finish(_f('백업파일의 %1번째 줄이 올바르지 않습니다.', $line));
				}
			}
			$items++;
			if (!strpos($path, 'referer'))
				setProgress(null, _t('백업파일을 확인하고 있습니다.'), $line);
			return true;
		case '/blog/personalization':
		case '/blog/userSetting':
			// skip
			return true;
	}
}

/*@callback@*/
function importer($path, $node, $line) {
	global $blogid, $migrational, $items, $item, $importedEntries, $importedAttachments, $importedBlogSetting;
	switch ($path) {
		case '/blog/setting':
			setProgress($item++ / $items * 100, _t('블로그 설정을 복원하고 있습니다.'));
			$importedBlogSetting = array(
				'name' => isset($node['name'][0]['.value']) ? $node['name'][0]['.value'] : '',
				'secondaryDomain' => isset($node['secondaryDomain'][0]['.value']) ? $node['secondaryDomain'][0]['.value'] : '');
			$setting = new BlogSetting();
			if (isset($node['title'][0]['.value']))
				$setting->title = $node['title'][0]['.value'];
			if (isset($node['description'][0]['.value']))
				$setting->description = $node['description'][0]['.value'];
			if (isset($node['banner'][0]['name'][0]['.value'])) {
				if (Attachment::isExecutableName($node['banner'][0]['name'][0]['.value']))
					user_error(__LINE__ . 'banner');
				else
					$setting->banner = importAttachmentName($node['banner'][0]['name'][0]['.value']);
			}
			if (isset($node['useSloganOnPost'][0]['.value']))
				$setting->useSloganOnPost = $node['useSloganOnPost'][0]['.value'];
			if (isset($node['postsOnPage'][0]['.value']))
				$setting->postsOnPage = $node['postsOnPage'][0]['.value'];
			if (isset($node['postsOnList'][0]['.value']))
				$setting->postsOnList = $node['postsOnList'][0]['.value'];
			if (isset($node['postsOnFeed'][0]['.value']))
				$setting->postsOnFeed = $node['postsOnFeed'][0]['.value'];
			if (isset($node['publishWholeOnFeed'][0]['.value']))
				$setting->publishWholeOnFeed = $node['publishWholeOnFeed'][0]['.value'];
			if (isset($node['acceptGuestComment'][0]['.value']))
				$setting->acceptGuestComment = $node['acceptGuestComment'][0]['.value'];
			if (isset($node['acceptcommentOnGuestComment'][0]['.value']))
				$setting->acceptcommentOnGuestComment = $node['acceptcommentOnGuestComment'][0]['.value'];
			if (isset($node['language'][0]['.value']))
				$setting->language = $node['language'][0]['.value'];
			// [보안] 유효한 PHP 시간대 식별자만 복원(아니면 현재 설정 유지 — 저장 값이 DB 세션 시간대 설정 SQL 에 쓰임)
			if (isset($node['timezone'][0]['.value']) && Timezone::isValid($node['timezone'][0]['.value']))
				$setting->timezone = $node['timezone'][0]['.value'];
			if (!$setting->save())
				user_error(__LINE__ . $setting->error);
			if (!empty($node['banner'][0]['content'][0]['.stream']))
				importAttachmentStream($node['banner'][0]['content'][0], $setting->banner, $setting->error != 'banner');
			return true;
		case '/blog/category':
			setProgress($item++ / $items * 100, _t('분류를 복원하고 있습니다.'));
			$category = new Category();
			$category->name = $node['name'][0]['.value'];
			$category->priority = $node['priority'][0]['.value'];
			if (isset($node['root'][0]['.value'])) $category->id = 0;
			importCategoryExtra($category, $node);
			if (!$category->add())
				user_error(__LINE__ . $category->error);
			// Textcube-NG: R-592 — 하위 분류를 깊이만큼 재귀로 복원한다(옛 2단 백업도 같은 길로 읽는다). 백업이 설정 깊이보다 깊으면 모두 가져오고 설정을 올린다(사용자 결정 Q2, 10단을 넘는 분류는 건너뛰고 기록)
			if (isset($node['category']))
				importCategoryChildren($node, $category->id, 2);
			return true;
		case '/blog/post':
			setProgress($item++ / $items * 100, _t('글을 복원하고 있습니다.'));
			$post = new Post();
			$post->id = $node['id'][0]['.value'];
			$post->slogan = @$node['.attributes']['slogan'];
			$post->visibility = $node['visibility'][0]['.value'];
			if(isset($node['starred'][0]['.value']))
				$post->starred = $node['starred'][0]['.value'];
			else $post->starred = 0;
			$post->title = $node['title'][0]['.value'];
			$post->content = $node['content'][0]['.value'];
			$post->contentformatter = isset($node['content'][0]['.attributes']['formatter']) ? $node['content'][0]['.attributes']['formatter'] : 'ttml';
			$post->contenteditor = isset($node['content'][0]['.attributes']['editor']) ? $node['content'][0]['.attributes']['editor'] : 'modern';
			$post->location = $node['location'][0]['.value'];
			$post->password = isset($node['password'][0]['.value']) ? $node['password'][0]['.value'] : null;
			$post->acceptcomment = intval($node['acceptComment'][0]['.value']);
			$post->accepttrackback = intval($node['acceptTrackback'][0]['.value']);
			$post->published = intval($node['published'][0]['.value']);
			if (isset($node['longitude'][0]['.value']))
				$post->longitude = $node['longitude'][0]['.value'];
			if (isset($node['latitude'][0]['.value']))
				$post->latitude = $node['latitude'][0]['.value'];
			$post->created = @$node['created'][0]['.value'];
			$post->modified = @$node['modified'][0]['.value'];
			if (($post->visibility == 'private' && intval($post->published) > $_SERVER['REQUEST_TIME']) ||
				(!empty($node['appointed'][0]['.value']) && $node['appointed'][0]['.value'] == 'true')) // for compatibility of appointed entries
				$post->visibility = 'appointed';
			if ($post->slogan == '') $post->slogan = 'Untitled'.$post->id;
			if (!empty($node['category'][0]['.value']))
				$post->category = Category::getId($node['category'][0]['.value']);
			if (isset($node['tag'])) {
				$post->tags = array();
				for ($i = 0; $i < count($node['tag']); $i++) {
					if (!empty($node['tag'][$i]['.value']))
						array_push($post->tags, $node['tag'][$i]['.value']);
				}
			}
			if (floatval(getServiceSetting('newlineStyle')) >= 1.1 && floatval(@$node['.attributes']['format']) < 1.1)
				$post->content = nl2brWithHTML($post->content);
			if (!$post->add())
				user_error(__LINE__ . $post->error);
			else if (strpos($post->content, 'attach/') !== false)
				$importedEntries[] = array('Post', $post->id);
			if (isset($node['attachment'])) {
				// 가져온 첨부의 원래 이름. 한 글 안에 같은 이름의 첨부가 여러 개면(잘못 만든 백업) 본문 참조는 먼저 가져온
				// 첨부를 가리키게 두고, 뒤 첨부는 바뀐 이름의 별도 첨부로만 가져온다(이름 형식과 무관하게 같은 결과).
				$attachmentNames = array();
				for ($i = 0; $i < count($node['attachment']); $i++) {
					$attachment = new Attachment();
					$attachment->parent = $post->id;
					$cursor = & $node['attachment'][$i];
					$attachment->name = importAttachmentName($cursor['name'][0]['.value']);
					$attachment->label = $cursor['label'][0]['.value'];
					$attachment->mime = @$cursor['.attributes']['mime'];
					$attachment->size = $cursor['.attributes']['size'];
					$attachment->width = $cursor['.attributes']['width'];
					$attachment->height = $cursor['.attributes']['height'];
					$attachment->enclosure = @$cursor['enclosure'][0]['.value'];
					$attachment->attached = $cursor['attached'][0]['.value'];
					$attachment->downloads = @$cursor['downloads'][0]['.value'];
					if (!($accepted = $attachment->add())) {
						user_error(__LINE__ . $attachment->error);
					} else if (!isset($attachmentNames[$cursor['name'][0]['.value']]) && ($cursor['name'][0]['.value'] != $attachment->name)) {
						$post2 = new Post();
						if ($post2->open($post->id, 'id, content, contentformatter, contenteditor, starred, modified')) {
							$post2->content = importAttachmentReplace($cursor['name'][0]['.value'], $attachment->name, $post2->content);
                            $post2->loadTags();
							$post2->update();
							$post2->close();
						}
						unset($post2);
					}
					if ($accepted) {
						$attachmentNames[$cursor['name'][0]['.value']] = true;
						if (!isset($importedAttachments[$cursor['name'][0]['.value']]))
							$importedAttachments[$cursor['name'][0]['.value']] = $attachment->name;
						if (!isset($importedAttachments[$attachment->name]))
							$importedAttachments[$attachment->name] = $attachment->name;
					}
					if (!empty($cursor['content'][0]['.stream']))
						importAttachmentStream($cursor['content'][0], $attachment->name, $accepted);
				}
			}
			if (isset($node['comment'])) {
				for ($i = 0; $i < count($node['comment']); $i++) {
					$comment = new Comment();
					$comment->entry = $post->id;
					$cursor = & $node['comment'][$i];
					$comment->name = $cursor['commenter'][0]['name'][0]['.value'];
					if (!empty($cursor['id'][0]['.value']))
						$comment->id = intval($cursor['id'][0]['.value']);
					if (!empty($cursor['commenter'][0]['.attributes']['id']))
						$comment->commenter = $cursor['commenter'][0]['.attributes']['id'];
					if (!empty($cursor['commenter'][0]['homepage'][0]['.value']))
						$comment->homepage = $cursor['commenter'][0]['homepage'][0]['.value'];
					if (!empty($cursor['commenter'][0]['ip'][0]['.value']))
						$comment->ip = $cursor['commenter'][0]['ip'][0]['.value'];
					if (!empty($cursor['commenter'][0]['openid'][0]['.value']))
						$comment->openid = $cursor['commenter'][0]['openid'][0]['.value'];
					$comment->password = $cursor['password'][0]['.value'];
					$comment->secret = $cursor['secret'][0]['.value'];
					$comment->written = intval($cursor['written'][0]['.value']);
					if (isset($cursor['longitude'][0]['.value']))
						$comment->longitude = $cursor['longitude'][0]['.value'];
					if (isset($cursor['latitude'][0]['.value']))
						$comment->latitude = $cursor['latitude'][0]['.value'];
					$comment->content = $cursor['content'][0]['.value'];
					if (!empty($cursor['isFiltered'][0]['.value']))
				    	$comment->isfiltered = intval($cursor['isFiltered'][0]['.value']);
					if (!$comment->add())
						user_error(__LINE__ . $comment->error);
					if (isset($node['comment'][$i]['comment'])) {
						for ($j = 0; $j < count($node['comment'][$i]['comment']); $j++) {
							$childComment = new Comment();
							$childComment->entry = $post->id;
							$childComment->parent = $comment->id;
							$cursor = & $node['comment'][$i]['comment'][$j];
							if (!empty($cursor['id'][0]['.value']))
								$childComment->id = intval($cursor['id'][0]['.value']);
							if (!empty($cursor['commenter'][0]['.attributes']['id']))
								$childComment->commenter = $cursor['commenter'][0]['.attributes']['id'];
							$childComment->name = $cursor['commenter'][0]['name'][0]['.value'];
							if (!empty($cursor['commenter'][0]['homepage'][0]['.value']))
								$childComment->homepage = $cursor['commenter'][0]['homepage'][0]['.value'];
							if (!empty($cursor['commenter'][0]['ip'][0]['.value']))
								$childComment->ip = $cursor['commenter'][0]['ip'][0]['.value'];
							if (!empty($cursor['commenter'][0]['openid'][0]['.value']))
								$childComment->openid = $cursor['commenter'][0]['openid'][0]['.value'];
							$childComment->password = $cursor['password'][0]['.value'];
							$childComment->secret = $cursor['secret'][0]['.value'];
							$childComment->written = intval($cursor['written'][0]['.value']);
							if (isset($cursor['longitude'][0]['.value']))
								$childComment->longitude = $cursor['longitude'][0]['.value'];
							if (isset($cursor['latitude'][0]['.value']))
								$childComment->latitude = $cursor['latitude'][0]['.value'];
							$childComment->content = $cursor['content'][0]['.value'];
							if (!empty($cursor['isFiltered'][0]['.value']))
					    		$childComment->isfiltered = intval($cursor['isFiltered'][0]['.value']);
							if (!$childComment->add())
								user_error(__LINE__ . $childComment->error);
						}
					}
				}
			}
			if (isset($node['trackback'])) {
				for ($i = 0; $i < count($node['trackback']); $i++) {
					$trackback = new Trackback();
					$trackback->entry = $post->id;
					$cursor = & $node['trackback'][$i];
					$trackback->url = $cursor['url'][0]['.value'];
					$trackback->site = $cursor['site'][0]['.value'];
					$trackback->title = $cursor['title'][0]['.value'];
					$trackback->excerpt = @$cursor['excerpt'][0]['.value'];
					if (!empty($cursor['ip'][0]['.value']))
						$trackback->ip = $cursor['ip'][0]['.value'];
					if (!empty($cursor['received'][0]['.value']))
						$trackback->received = $cursor['received'][0]['.value'];
					if (!empty($cursor['isFiltered'][0]['.value']))
					    	$trackback->isFiltered = $cursor['isFiltered'][0]['.value'];
					if (!$trackback->add())
						user_error(__LINE__ . $trackback->error);
				}
			}
			if (isset($node['logs'][0]['trackback'])) {
				for ($i = 0; $i < count($node['logs'][0]['trackback']); $i++) {
					$log = new TrackbackLog();
					$log->entry = $post->id;
					$cursor = & $node['logs'][0]['trackback'][$i];
					$log->url = $cursor['url'][0]['.value'];
					if (!empty($cursor['sent'][0]['.value']))
						$log->sent = $cursor['sent'][0]['.value'];
					if (!$log->add())
						user_error(__LINE__ . $log->error);
				}
			}
			return true;
		case '/blog/notice':
			setProgress($item++ / $items * 100, _t('공지를 복원하고 있습니다.'));
			$notice = new Notice();
			$notice->id = $node['id'][0]['.value'];
			$notice->slogan = @$node['.attributes']['slogan'];
			$notice->visibility = $node['visibility'][0]['.value'];
			if(isset($node['starred'][0]['.value']))
				$notice->starred = $node['starred'][0]['.value'];
			else $notice->starred = 0;
			$notice->title = $node['title'][0]['.value'];
			$notice->content = $node['content'][0]['.value'];
			$notice->contentformatter = isset($node['content'][0]['.attributes']['formatter']) ? $node['content'][0]['.attributes']['formatter'] : getDefaultFormatter();
			$notice->contenteditor = isset($node['content'][0]['.attributes']['editor']) ? $node['content'][0]['.attributes']['editor'] : getDefaultEditor();
			$notice->published = intval($node['published'][0]['.value']);
			$notice->created = @$node['created'][0]['.value'];
			$notice->modified = @$node['modified'][0]['.value'];
			if (floatval(getServiceSetting('newlineStyle')) >= 1.1 && floatval(@$node['.attributes']['format']) < 1.1)
				$notice->content = nl2brWithHTML($notice->content);
			if (!$notice->add())
				user_error(__LINE__ . $notice->error);
			else if (strpos($notice->content, 'attach/') !== false)
				$importedEntries[] = array('Notice', $notice->id);
			if (isset($node['attachment'])) {
				// 가져온 첨부의 원래 이름. 한 글 안에 같은 이름의 첨부가 여러 개면(잘못 만든 백업) 본문 참조는 먼저 가져온
				// 첨부를 가리키게 두고, 뒤 첨부는 바뀐 이름의 별도 첨부로만 가져온다(이름 형식과 무관하게 같은 결과).
				$attachmentNames = array();
				for ($i = 0; $i < count($node['attachment']); $i++) {
					$attachment = new Attachment();
					$attachment->parent = $notice->id;
					$cursor = & $node['attachment'][$i];
					$attachment->name = importAttachmentName($cursor['name'][0]['.value']);
					$attachment->label = $cursor['label'][0]['.value'];
					$attachment->mime = @$cursor['.attributes']['mime'];
					$attachment->size = $cursor['.attributes']['size'];
					$attachment->width = $cursor['.attributes']['width'];
					$attachment->height = $cursor['.attributes']['height'];
					$attachment->enclosure = @$cursor['enclosure'][0]['.value'];
					$attachment->attached = $cursor['attached'][0]['.value'];
					$attachment->downloads = @$cursor['downloads'][0]['.value'];
					if (!($accepted = $attachment->add())) {
						user_error(__LINE__ . $attachment->error);
					} else if (!isset($attachmentNames[$cursor['name'][0]['.value']]) && ($cursor['name'][0]['.value'] != $attachment->name)) {
						$notice2 = new Notice();
						if ($notice2->open($notice->id, 'id, content, contentformatter, contenteditor, starred, modified')) {
							$notice2->content = importAttachmentReplace($cursor['name'][0]['.value'], $attachment->name, $notice2->content);
							$notice2->update();
							$notice2->close();
						}
						unset($notice2);
					}
					if ($accepted) {
						$attachmentNames[$cursor['name'][0]['.value']] = true;
						if (!isset($importedAttachments[$cursor['name'][0]['.value']]))
							$importedAttachments[$cursor['name'][0]['.value']] = $attachment->name;
						if (!isset($importedAttachments[$attachment->name]))
							$importedAttachments[$attachment->name] = $attachment->name;
					}
					if (!empty($cursor['content'][0]['.stream']))
						importAttachmentStream($cursor['content'][0], $attachment->name, $accepted);
				}
			}
			return true;
		case '/blog/keyword':
			setProgress($item++ / $items * 100, _t('키워드를 복원하고 있습니다.'));
			$keyword = new Keyword();
			$keyword->id = $node['id'][0]['.value'];
			$keyword->visibility = $node['visibility'][0]['.value'];
			if(isset($node['starred'][0]['.value']))
				$keyword->starred = $node['starred'][0]['.value'];
			else $keyword->starred = 0;
			$keyword->name = $node['name'][0]['.value'];
			$keyword->description = $node['description'][0]['.value'];
			$keyword->descriptionEditor = isset($node['description'][0]['.attributes']['editor']) ? $node['description'][0]['.attributes']['editor'] : getDefaultEditor();
			$keyword->descriptionFormatter = isset($node['description'][0]['.attributes']['formatter']) ? $node['description'][0]['.attributes']['formatter'] : getDefaultFormatter();
			$keyword->published = intval($node['published'][0]['.value']);
			$keyword->created = @$node['created'][0]['.value'];
			$keyword->modified = @$node['modified'][0]['.value'];
			if (floatval(getServiceSetting('newlineStyle')) >= 1.1 && floatval(@$node['.attributes']['format']) < 1.1)
				$keyword->description = nl2brWithHTML($keyword->description);
			if (!$keyword->add())
				user_error(__LINE__ . $keyword->error);
			else if (strpos($keyword->description, 'attach/') !== false)
				$importedEntries[] = array('Keyword', $keyword->id);
			if (isset($node['attachment'])) {
				// 가져온 첨부의 원래 이름. 한 글 안에 같은 이름의 첨부가 여러 개면(잘못 만든 백업) 본문 참조는 먼저 가져온
				// 첨부를 가리키게 두고, 뒤 첨부는 바뀐 이름의 별도 첨부로만 가져온다(이름 형식과 무관하게 같은 결과).
				$attachmentNames = array();
				for ($i = 0; $i < count($node['attachment']); $i++) {
					$attachment = new Attachment();
					$attachment->parent = $keyword->id;
					$cursor = & $node['attachment'][$i];
					$attachment->name = importAttachmentName($cursor['name'][0]['.value']);
					$attachment->label = $cursor['label'][0]['.value'];
					$attachment->mime = @$cursor['.attributes']['mime'];
					$attachment->size = $cursor['.attributes']['size'];
					$attachment->width = $cursor['.attributes']['width'];
					$attachment->height = $cursor['.attributes']['height'];
					$attachment->enclosure = @$cursor['enclosure'][0]['.value'];
					$attachment->attached = $cursor['attached'][0]['.value'];
					$attachment->downloads = @$cursor['downloads'][0]['.value'];
					if (!($accepted = $attachment->add())) {
						user_error(__LINE__ . $attachment->error);
					} else if (!isset($attachmentNames[$cursor['name'][0]['.value']]) && ($cursor['name'][0]['.value'] != $attachment->name)) {
						$keyword2 = new Keyword();
						if ($keyword2->open($keyword->id, 'id, content, contentformatter, contenteditor, starred, modified')) {
							$keyword2->description = importAttachmentReplace($cursor['name'][0]['.value'], $attachment->name, $keyword2->description);
							$keyword2->update();
							$keyword2->close();
						}
						unset($keyword2);
					}
					if ($accepted) {
						$attachmentNames[$cursor['name'][0]['.value']] = true;
						if (!isset($importedAttachments[$cursor['name'][0]['.value']]))
							$importedAttachments[$cursor['name'][0]['.value']] = $attachment->name;
						if (!isset($importedAttachments[$attachment->name]))
							$importedAttachments[$attachment->name] = $attachment->name;
					}
					if (!empty($cursor['content'][0]['.stream']))
						importAttachmentStream($cursor['content'][0], $attachment->name, $accepted);
				}
			}
			return true;
		case '/blog/linkCategories':
			setProgress($item++ / $items * 100, _t('링크 카테고리를 복원하고 있습니다.'));
			$linkCategory = new LinkCategories();
			$linkCategory->name = $node['name'][0]['.value'];
			$linkCategory->priority = $node['priority'][0]['.value'];
			$linkCategory->visibility = !isset($node['visibility'][0]['.value']) || empty($node['visibility'][0]['.value'])
				? 2 : $node['visibility'][0]['.value'];

			$linkCategory->id = LinkCategories::getId($linkCategory->name);
			if ($linkCategory->id) {
				if (!$linkCategory->update())
					user_error(__LINE__ . $linkCategory->error);
			} else {
				if (!$linkCategory->add())
					user_error(__LINE__ . $linkCategory->error);
			}
			return true;
		case '/blog/link':
			setProgress($item++ / $items * 100, _t('링크를 복원하고 있습니다.'));
			$link = new Link();
			$link->category = empty($node['category'][0]['.value']) ? 0 : $node['category'][0]['.value'];
			$link->url = $node['url'][0]['.value'];
			$link->title = $node['title'][0]['.value'];
			if (!empty($node['feed'][0]['.value']))
				$link->feed = $node['feed'][0]['.value'];
			if (!empty($node['registered'][0]['.value']))
				$link->registered = $node['registered'][0]['.value'];
			if (!empty($node['xfn'][0]['.value']))
				$link->xfn = $node['xfn'][0]['.value'];
			$link->id = Link::getId($link->url);
			if ($link->id) {
				if (!$link->update())
					user_error(__LINE__ . $link->error);
			} else {
				if (!$link->add())
					user_error(__LINE__ . $link->error);
			}
			return true;
		case '/blog/logs/referer':
			setProgress($item++ / $items * 100, _t('리퍼러 로그를 복원하고 있습니다.'));
			$log = new RefererLog();
			if (isset($node['path'][0]['.value']))
				$log->url = $node['path'][0]['.value'];
			else
				$log->url = $node['url'][0]['.value'];
			$log->referred = $node['referred'][0]['.value'];
			if (!$log->add(false))
				user_error(__LINE__ . $log->error);
			return true;
		case '/blog/commentsNotified/comment':
			setProgress($item++ / $items * 100, _t('댓글 알리미 내용을 복원하고 있습니다.'));
			$cmtNotified = new CommentNotified();
			$cmtNotified->id = $node['id'][0]['.value'];
			$cursor = & $node['commenter'][0];
			$cmtNotified->name = $cursor['name'][0]['.value'];
			$cmtNotified->homepage = $cursor['homepage'][0]['.value'];
			$cmtNotified->ip = $cursor['ip'][0]['.value'];
			$cmtNotified->entry = $node['entry'][0]['.value'];
			$cmtNotified->password = $node['password'][0]['.value'];
			$cmtNotified->content = $node['content'][0]['.value'];
			$cmtNotified->parent = $node['parent'][0]['.value'];
			$cmtNotified->secret = $node['secret'][0]['.value'];
			$cmtNotified->written = $node['written'][0]['.value'];
			$cmtNotified->modified = $node['modified'][0]['.value'];
			$cmtNotified->url = $node['url'][0]['.value'];
			$cmtNotified->isnew = $node['isNew'][0]['.value'];
			$site = new CommentNotifiedSiteInfo();
			if (!$site->open("url = '" . POD::escapeString($node['site'][0]['.value']) . "'")) {
				$site->title = '';
				$site->name = '';
				$site->modified = 31536000;
				$site->url = $node['site'][0]['.value'];
				$site->add();
			}
			$cmtNotified->siteid = $site->id;
			$site->close();
			$cmtNotified->remoteid = $node['remoteId'][0]['.value'];
			$cmtNotified->entrytitle = (!isset($node['entryTitle'][0]['.value']) || empty($node['entryTitle'][0]['.value'])) ? 'No title' : $node['entryTitle'][0]['.value'];
			$cmtNotified->entryurl = $node['entryUrl'][0]['.value'];
			if (!$cmtNotified->add())
				user_error(__LINE__ . $cmtNotified->error);
			return true;
		case '/blog/commentsNotifiedSiteInfo/site':
			setProgress($item++ / $items * 100, _t('댓글 알리미 내용을 복원하고 있습니다.'));
			$cmtNotifiedSite = new CommentNotifiedSiteInfo();
			if ($cmtNotifiedSite->open("url = '" . POD::escapeString($node['url'][0]['.value']) . "'")) {
				if (intval($node['modified'][0]['.value']) > intval($cmtNotifiedSite->modified)) {
					$cmtNotifiedSite->title = $node['title'][0]['.value'];
					$cmtNotifiedSite->name = $node['name'][0]['.value'];
					$cmtNotifiedSite->modified = $node['modified'][0]['.value'];
				}
				if (!$cmtNotifiedSite->update())
					user_error(__LINE__ . $cmtNotifiedSite->error);
			} else {
				$cmtNotifiedSite->url = $node['url'][0]['.value'];
				$cmtNotifiedSite->title = $node['title'][0]['.value'];
				$cmtNotifiedSite->name = $node['name'][0]['.value'];
				$cmtNotifiedSite->modified = $node['modified'][0]['.value'];
				if (!$cmtNotifiedSite->add())
					user_error(__LINE__ . $cmtNotifiedSite->error);
			}
			return true;
		case '/blog/statistics/referer':
			setProgress($item++ / $items * 100, _t('리퍼러 통계를 복원하고 있습니다.'));
			$statistics = new RefererStatistics();
			$statistics->host = $node['host'][0]['.value'];
			$statistics->count = $node['count'][0]['.value'];
			if (!$statistics->add())
				user_error(__LINE__ . $statistics->error);
			return true;
		case '/blog/statistics/visits':
			setProgress($item++ / $items * 100, _t('블로그 통계 정보를 복원하고 있습니다.'));
			$statistics = new BlogStatistics();
			$statistics->visits = $node['.value'];
			if (!$statistics->add())
				user_error(__LINE__ . $statistics->error);
			return true;
		case '/blog/statistics/daily':
			setProgress($item++ / $items * 100, _t('일별 통계 정보를 복원하고 있습니다.'));
			$statistics = new DailyStatistics();
			$statistics->date = $node['date'][0]['.value'];
			$statistics->visits = $node['visits'][0]['.value'];
			if (!$statistics->add())
				user_error(__LINE__ . $statistics->error);
			return true;
		case '/blog/skin':
			setProgress($item++ / $items * 100, _t('스킨 설정을 복원하고 있습니다.'));
			$setting = new SkinSetting();
			if (false) {
				$setting->skin = $node['name'][0]['.value'];
				if (!$setting->save())
					user_error(__LINE__ . $setting->error);
				$setting->skin = null;
			}
			$setting->entriesOnRecent = $node['entriesOnRecent'][0]['.value'];
			$setting->commentsOnRecent = $node['commentsOnRecent'][0]['.value'];
			$setting->trackbacksOnRecent = $node['trackbacksOnRecent'][0]['.value'];
			$setting->commentsOnGuestbook = $node['commentsOnGuestbook'][0]['.value'];
			$setting->tagsOnTagbox = $node['tagsOnTagbox'][0]['.value'];
			$setting->alignOnTagbox = $node['alignOnTagbox'][0]['.value'];
			$setting->expandComment = $node['expandComment'][0]['.value'];
			$setting->expandTrackback = $node['expandTrackback'][0]['.value'];
			if (!empty($node['recentNoticeLength'][0]['.value']))
				$setting->recentNoticeLength = $node['recentNoticeLength'][0]['.value'];
			$setting->recentEntryLength = $node['recentEntryLength'][0]['.value'];
			$setting->recentTrackbackLength = $node['recentTrackbackLength'][0]['.value'];
			$setting->linkLength = $node['linkLength'][0]['.value'];
			$setting->showListOnCategory = $node['showListOnCategory'][0]['.value'];
			$setting->showListOnArchive = $node['showListOnArchive'][0]['.value'];
			if (isset($node['tree'])) {
				$cursor = & $node['tree'][0];
				$setting->tree = $cursor['name'][0]['.value'];
				$setting->colorOnTree = $cursor['color'][0]['.value'];
				$setting->bgcolorOnTree = $cursor['bgColor'][0]['.value'];
				$setting->activecolorOnTree = $cursor['activeColor'][0]['.value'];
				$setting->activebgcolorOnTree = $cursor['activeBgColor'][0]['.value'];
				$setting->labelLengthOnTree = $cursor['labelLength'][0]['.value'];
				$setting->showValueOnTree = $cursor['showValue'][0]['.value'];
			}
			if (!$setting->save())
				user_error(__LINE__ . $setting->error);
			return true;
		case '/blog/plugin':
//			setProgress($item++ / $items * 100, _t('플러그인 설정을 복원하고 있습니다.'));
//			$setting = new PluginSetting();
//			$setting->name = $node['name'][0]['.value'];
//			$setting->setting = $node['setting'][0]['.value'];
//			if (!$setting->add())
//				user_error(__LINE__ . $setting->error);
			return true;
		case '/blog/personalization':
//			setProgress($item++ / $items * 100, _t('사용자 편의 설정을 복원하고 있습니다.'));
//			$setting = new UserSetting();
//			$setting->name = 'rowsPerPage';
//			$setting->value = $node['rowsPerPage'][0]['.value'];
//			if (!$setting->add())
//				user_error(__LINE__ . $setting->error);
//			$setting->name = 'readerPannelVisibility';
//			$setting->value = $node['readerPannelVisibility'][0]['.value'];
//			if (!$setting->add())
//				user_error(__LINE__ . $setting->error);
//			$setting->name = 'readerPannelHeight';
//			$setting->value = $node['readerPannelHeight'][0]['.value'];
//			if (!$setting->add())
//				user_error(__LINE__ . $setting->error);
//			$setting->name = 'lastVisitNotifiedPage';
//			$setting->value = $node['lastVisitNotifiedPage'][0]['.value'];
//			if (!$setting->add())
//				user_error(__LINE__ . $setting->error);
			return true;
		case '/blog/userSetting':
//			setProgress($item++ / $items * 100, _t('사용자 편의 설정을 복원하고 있습니다'));
//			$setting = new UserSetting();
//			$setting->name = $node['name'][0]['.value'];
//			$setting->value = $node['value'][0]['.value'];
//			if (!$setting->add())
//				user_error(__LINE__ . $setting->error);
			return true;
		case '/blog/guestbook/comment':
			setProgress($item++ / $items * 100, _t('방명록을 복원하고 있습니다.'));
			$comment = new GuestComment();
			$comment->name = $node['commenter'][0]['name'][0]['.value'];
			if (!empty($node['id'][0]['.value']))
				$comment->id = $node['id'][0]['.value'];
			if (!empty($node['commenter'][0]['.attributes']['id']))
				$comment->commenter = $node['commenter'][0]['.attributes']['id'];
			if (!empty($node['commenter'][0]['homepage'][0]['.value']))
				$comment->homepage = $node['commenter'][0]['homepage'][0]['.value'];
			if (!empty($node['commenter'][0]['ip'][0]['.value']))
				$comment->ip = $node['commenter'][0]['ip'][0]['.value'];
			if (!empty($node['commenter'][0]['openid'][0]['.value']))
				$comment->openid = $node['commenter'][0]['openid'][0]['.value'];
			$comment->password = $node['password'][0]['.value'];
			$comment->secret = @$node['secret'][0]['.value'];
			$comment->written = $node['written'][0]['.value'];
			$comment->content = $node['content'][0]['.value'];
			if (!$comment->add())
				user_error(__LINE__ . $comment->error);
			if (isset($node['comment'])) {
				for ($j = 0; $j < count($node['comment']); $j++) {
					$childComment = new GuestComment();
					$childComment->parent = $comment->id;
					$cursor = & $node['comment'][$j];
					$childComment->name = $cursor['commenter'][0]['name'][0]['.value'];
					if (!empty($cursor['id'][0]['.value']))
						$childComment->id = $cursor['id'][0]['.value'];
					if (!empty($cursor['commenter'][0]['.attributes']['id']))
						$childComment->commenter = $cursor['commenter'][0]['.attributes']['id'];
					if (!empty($cursor['commenter'][0]['homepage'][0]['.value']))
						$childComment->homepage = $cursor['commenter'][0]['homepage'][0]['.value'];
					if (!empty($cursor['commenter'][0]['ip'][0]['.value']))
						$childComment->ip = $cursor['commenter'][0]['ip'][0]['.value'];
					if (!empty($cursor['commenter'][0]['openid'][0]['.value']))
						$childComment->openid = $cursor['commenter'][0]['openid'][0]['.value'];
					$childComment->password = $cursor['password'][0]['.value'];
					$childComment->secret = @$cursor['secret'][0]['.value'];
					$childComment->written = $cursor['written'][0]['.value'];
					$childComment->content = $cursor['content'][0]['.value'];
					if (!$childComment->add())
						user_error(__LINE__ . $childComment->error);
				}
			}
			return true;
		case '/blog/filter':
			setProgress($item++ / $items * 100, _t('필터 설정을 복원하고 있습니다.'));
			$filter = new Filter();
			$filter->type = $node['.attributes']['type'];
			$filter->pattern = $node['pattern'][0]['.value'];
			if (!$filter->add())
				user_error(__LINE__ . $filter->error);
			return true;
		case '/blog/feed':
			setProgress($item++ / $items * 100, _t('리더 데이터를 복원하고 있습니다.'));
			$feed = new Feed();
			if (!empty($node['group'][0]['.value']))
				$feed->group = FeedGroup::getId($node['group'][0]['.value'], true);
			$feed->url = $node['url'][0]['.value'];
			if (!$feed->add())
				user_error(__LINE__ . $feed->error);
			return true;
		case '/blog/line':
			setProgress($item++ / $items * 100, _t('라인을 복원하고 있습니다.'));
			$line = Model_Line::getInstance();
			$line->reset();
			if (!empty($node['author'][0]['.value']))
				$line->author = $node['author'][0]['.value'];
			if (!empty($node['category'][0]['.value']))
				$line->category = $node['category'][0]['.value'];
			if (!empty($node['root'][0]['.value']))
				$line->root = $node['root'][0]['.value'];
			if (!empty($node['permalink'][0]['.value']))
				$line->permalink = $node['permalink'][0]['.value'];
			if (!empty($node['content'][0]['.value']))
				$line->content = $node['content'][0]['.value'];
			if (!empty($node['created'][0]['.value']))
				$line->created = intval($node['created'][0]['.value']);
			if ($line->add()) {
				return true;
			} else {
				user_error(__LINE__ . $line->_error);
			}
	}
}

?>
