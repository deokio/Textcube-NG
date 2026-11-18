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
require ROOT . '/library/preprocessor.php';

requireLibrary('blog.skin');

requireModel('reader.common');
requireModel('blog.comment');
requireModel('blog.response.remote');
requireModel('blog.entry');
requireModel('blog.trash');
requireModel('common.setting');

$blogMenu['topMenu'] = 'center';
$blogMenu['contentMenu'] = 'dashboard';

$ctx = Model_Context::getInstance();

// Move spams to trash.
if (!isset($_REQUEST['ajaxcall'])) {
	require ROOT . '/interface/common/owner/header.php';

	trashVan();
}

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
	if (isset($_POST['pos'])) $_GET['pos'] = $_POST['pos'];
	if (isset($_POST['rel'])) $_GET['rel'] = $_POST['rel'];
}
// Dashboard setting
if (($_SERVER['REQUEST_METHOD'] == 'POST') &&
	(!isset($_REQUEST['edit'])) &&
	(empty($_GET['useTTdashboard']))) {	// Turn off case.
	$textcubeDashboard = Setting::getBlogSettingGlobal("textcubeDashboard",1);
	if ($textcubeDashboard == 0) {
		Setting::setBlogSettingGlobal("textcubeDashboard", 1);
		$textcubeDashboard = 1;
	} else {
		Setting::setBlogSettingGlobal("textcubeDashboard", 0);
		$textcubeDashboard = 0;
	}
} else if (($_SERVER['REQUEST_METHOD'] == 'POST') &&
	!isset($_REQUEST['edit'])) {	// Turn on case.
	Setting::setBlogSettingGlobal("textcubeDashboard", 1);
	$textcubeDashboard = 1;
} else {	// Just read it.
	$textcubeDashboard = Setting::getBlogSettingGlobal("textcubeDashboard",1);
}

// Layout setting
$layout = Setting::getBlogSettingGlobal('centerLayout', '');
$newlayout = array();
$addedlayout = array();
$oldcenterlayout = array();
$defaultPanelShown = false;
//$textcubeDashboard = Setting::getBlogSettingGlobal("textcubeDashboard",1);

if (count($centerMappings) == 0) {		// No center widgets
	$layout = '';
	Setting::setBlogSettingGlobal('centerLayout', '');
	unset($_GET['pos']);
	unset($_GET['rel']);
}

function findPlugin($item, $arrays)
{
	foreach($arrays as $key => $data) {
		if (($item['plugin'] === $data['plugin']) && ($item['handler'] === $data['handler']))
			return $key;
	}
	return false;
}

$modified = false;

if ((!empty($layout)) && (($oldcenterlayout = unserialize($layout)) != false) ) {
	$seperatorCount = 0;
	foreach($oldcenterlayout as $item) {
		if ($item['plugin'] == 'defaultDashboardWidget') {
			if($textcubeDashboard == 0) {
				$modified = true;
			} else {
				array_push($newlayout, $item);
				$defaultPanelShown = true;
			}
		} else if ($item['plugin'] == 'TextcubeSeparator') {
			array_push($newlayout, $item);
			$seperatorCount++;
		} else if (($pos = findPlugin($item, $centerMappings)) !== false) {
			array_push($newlayout, $centerMappings[$pos]);
			unset($centerMappings[$pos]);
		} else {
			array_push($addedlayout, $item);
		}
	}
	while ($seperatorCount < 2) {
		array_push($centerMappings, array('plugin' => 'TextcubeSeparator'));
		$seperatorCount++;
	}

	$newlayout = array_merge($newlayout, $centerMappings);
} else if (count($centerMappings) > 0) { // TODO 1/3
	unset($_GET['pos']);
	unset($_GET['rel']);
	$middlepos = (count($centerMappings) + 2)/3;
	array_splice($centerMappings, $middlepos , 0, array(array('plugin' => 'TextcubeSeparator')));
	array_splice($centerMappings, $middlepos * 2, 0, array(array('plugin' => 'TextcubeSeparator')));
	$newlayout = $addedlayout = $centerMappings;
}

if (count($newlayout) == 0) { // If no widget is activated, add default widget & separators
	array_push($newlayout, array('plugin' => 'TextcubeSeparator'));
	array_push($newlayout, array('plugin' => 'TextcubeSeparator'));
	if($textcubeDashboard == 1) //array_push($newlayout, array('plugin' => 'defaultDashboardWidget'));
		array_splice($newlayout, 0, 0, array(array('plugin' => 'defaultDashboardWidget')));
	$modified = true;
}

if ((isset($_GET['pos'])) && (($_GET['pos'] < 0) || ($_GET['pos']) >= count($newlayout))) {
	unset($_GET['pos']);
	unset($_GET['rel']);
}

if (isset($_GET['pos']) && is_numeric($_GET['pos'])) {
	if (isset($_GET['rel']) && is_numeric($_GET['rel']) && (is_numeric($_GET['rel']))) {
		$newpos = $_GET['pos'] + $_GET['rel'];
		if ($newpos < 0) $newpos = 0;
		if ($newpos >= count($newlayout)) $newpos = count($newlayout) - 1;
		$item = array_splice($newlayout, $_GET['pos'], 1);
		array_splice($newlayout, $newpos, 0, $item);
		$modified = true;
	}
}
// Checking whether the default widget is activated or not.
$defaultWidgetPosition = 0;
//$count = 0;
if(($textcubeDashboard == 1) && ($defaultPanelShown == false)) {	// No default widget is activated during process.
	foreach($newlayout as $widget) {	// Double-check.
		if($widget['plugin'] == 'defaultDashboardWidget') $defaultPanelShown = true;
//		else if($widget['plugin'] == 'TextcubeSeparator') $defaultWidgetPosition = $count + 1;
//		$count++;
	}

	if($defaultPanelShown == false) {
		array_splice($newlayout, 0, 0, array(array('plugin' => 'defaultDashboardWidget')));
/*	if($seperatorCount < 2) {
			for($i = 0; $i < (2-$seperatorCount); $i++) {
				array_push($newlayout, array('plugin' => 'TextcubeSeparator'));
			}
			array_push($newlayout, array('plugin' => 'defaultDashboardWidget'));
		} else {
			array_splice($newlayout, $defaultWidgetPosition, 0, array(array('plugin' => 'defaultDashboardWidget')));
		}*/
		$modified = true;
	}
}

if ((count($centerMappings) > 0) || (count($addedlayout) > 0) || ($modified == true)) {
	$saveLayout = array_values($newlayout);
	for ($i = 0; $i < count($saveLayout); $i++)
	{
		unset($saveLayout[$i]['title']);
	}
	Setting::setBlogSettingGlobal('centerLayout', serialize($saveLayout));
}

unset($addedlayout);
unset($layout);
unset($oldcenterlayout);

if (isset($_REQUEST['ajaxcall'])) {
	Respond::ResultPage(0);
	exit;
}

$editClass = NULL;
if (isset($_REQUEST['edit'])) {
	$editClass = "-edit";
?>
<script src="<?php echo $ctx->getProperty('service.path');?>/resources/script/jquery/jquery.ui.essentials-<?php echo JQUERY_UI_VERSION;?>.js" type="text/javascript"></script>
<script type="text/javascript">
//<![CDATA[
<?php echo "\tvar editMode = ".isset($_REQUEST['edit']).";\n";?>
//]]>
</script>
<script src="<?php echo $ctx->getProperty('service.path');?>/resources/script/dashboard.js" type="text/javascript"></script>
<?php
}
?>
						<script type="text/javascript">
							//<![CDATA[
								function changeList() {
									document.getElementById("form-quilt").submit();
								}
								function tcCheckupNotice(msg, url, label, note) {	// Textcube-NG: 점검 안내를 브라우저 confirm 대신 페이지 위 안내 줄로 보인다(r3-16, R-395)
									var bar = document.getElementById("tc-checkup-notice");
									if (!bar) {
										bar = document.createElement("div");
										bar.id = "tc-checkup-notice";
										bar.setAttribute("role", "alert");
										bar.style.cssText = "position:relative;z-index:1000;padding:8px 12px;background:#fff8d6;border-bottom:1px solid #e0c96a;color:#333;font-size:12px;line-height:1.6;text-align:center;";
										bar.appendChild(document.createElement("span"));
										var go = document.createElement("a");
										go.style.cssText = "margin-left:10px;font-weight:bold;";
										bar.appendChild(go);
										var later = document.createElement("button");
										later.type = "button";
										later.style.cssText = "margin-left:8px;";
										later.appendChild(document.createTextNode("<?php echo _t('나중에');?>"));
										later.onclick = function () { bar.style.display = "none"; };
										bar.appendChild(later);
										document.body.insertBefore(bar, document.body.firstChild);
									}
									bar.childNodes[0].textContent = note ? msg + " " + note : msg;
									bar.childNodes[1].href = url;
									bar.childNodes[1].textContent = label;
									bar.style.display = "";
								}
<?php
if (!file_exists(__TEXTCUBE_CACHE_DIR__.'/CHECKUP')) {
?>

								window.addEventListener("load", checkTextcubeVersion, false);
								function checkTextcubeVersion() {
									tcCheckupNotice("<?php echo _t('버전업 체크를 위한 파일을 생성합니다. 지금 생성하시겠습니까?');?>", "<?php echo $ctx->getProperty('uri.blog');?>/checkup", "<?php echo _t('지금 생성');?>");	// Textcube-NG: confirm 대신 안내 줄(r3-16, R-395)
								}
<?php
} else {
	if (!Checkup::isCurrent()) {	// PHP port: NG 점검 표시(2행) 포함 비교(Checkup, Textcube.Core.php)
?>

								window.addEventListener("load", checkTextcubeVersion, false);
								function checkTextcubeVersion() {
									tcCheckupNotice("<?php echo _t('텍스트큐브 시스템 점검이 필요합니다. 지금 점검하시겠습니까?');?>", "<?php echo $ctx->getProperty('uri.blog');?>/checkup", "<?php echo _t('지금 점검');?>");	// Textcube-NG: confirm 대신 안내 줄(r3-16, R-395)
								}
<?php
	}
}
if(Acl::check("group.administrators")) {
?>
								function cleanupCache() {
									var request = new HTTPRequest("POST", "<?php echo $ctx->getProperty('uri.blog');?>/owner/center/dashboard/cleanup/");	// Textcube-NG: POST(토큰은 EAF4.js XHR 패치가 붙인다)(r3-7, 503-7)
									request.onSuccess = function () {
										PM.removeRequest(this);
										PM.showMessage("<?php echo _t('캐시를 정리하였습니다.');?>", "center", "bottom");
									}
									request.onError= function () {
										PM.removeRequest(this);
									}
									PM.addRequest(request, "<?php echo _t('캐시를 정리하고 있습니다.');?>");
									request.send();
								}
								function ackPluginNotice(key) { // Textcube-NG: 플러그인 알림 확인(v3.219)
									var request = new HTTPRequest("POST", "<?php echo $ctx->getProperty('uri.blog');?>/owner/center/dashboard/notice/");
									request.onSuccess = function () {
										PM.removeRequest(this);
										window.location.href = "<?php echo $ctx->getProperty('uri.blog');?>/owner/center/dashboard?pluginNotice"; // Textcube-NG: 확인 뒤 알림 목록을 펼친 채로 돌아온다(r3-13)
									}
									request.onError= function () {
										PM.removeRequest(this);
										PM.showErrorMessage("<?php echo escapeJSInCData(_t('알림을 확인하지 못했습니다.'));?>", "center", "bottom");
									}
									PM.addRequest(request, "<?php echo escapeJSInCData(_t('알림을 확인하고 있습니다.'));?>");
									request.send(key == "all" ? "all=1" : "key=" + encodeURIComponent(key));
								}
								function togglePluginNotice() { // Textcube-NG: 플러그인 알림 탭 — 평소엔 개수만, 누르면 목록을 펼치거나 접는다(r3-13)
									var panel = document.getElementById("plugin-notice-panel");
									var tab = document.getElementById("plugin-notice-tab");
									if (!panel)
										return false;
									var open = (panel.style.display == "none");
									panel.style.display = open ? "" : "none";
									if (tab)
										tab.className = open ? tab.className + " plugin-notice-open" : tab.className.replace(/(^|\s+)plugin-notice-open(?=\s|$)/g, "");
									return false;
								}
<?php
	if(Acl::check("group.creators")) {
?>
								var dialog = null;

								function showDialog($name) {
									if (dialog)
										dialog.style.display = "none";
									dialog = document.getElementById($name + "Dialog");
									PM.showPanel(dialog);
								}

								function hideDialog() {
									if (dialog) {
										dialog.style.display = "none";
										dialog = null;
									}
								}

								function optimizeData() {
									document.getElementById("optimizingIndicator").style.width = "0%";
									document.getElementById("optimizingDataDialogTitle").innerHTML = '<?php echo _t('데이터베이스를 최적화하고 있습니다. 잠시만 기다려 주십시오.');?>';
									PM.showPanel("optimizingDataDialog");
									document.getElementById("dataOptimizer").submit();
								}
<?php
	}
}
?>
							//]]>
						</script>
						<form id="form-quilt" method="post" action="<?php echo parseURL($ctx->getProperty('uri.blog').'/owner/center/dashboard');?>">
							<div id="part-center-quilt<?php echo $editClass;?>" class="part">
								<h2 class="caption"><span class="main-text"><?php echo _t('조각보를 봅니다');?></span></h2>
<?php
if (!isset($_REQUEST['edit']) && Acl::check('group.owners')) {
?>
								<dl id="independent-notice-line" class="line">
									<dt><?php echo _t('정보 패널 설정');?></dt>
									<dd>
										<input type="checkbox" class="checkbox" id="useTTdashboard" name="useTTdashboard" value="on" onclick="changeList();return false;"<?php echo $textcubeDashboard == 1 ? ' checked="checked"' : NULL;?> />
										<label for="useTTdashboard"><?php echo _t('기본 패널을 사용합니다');?></label>
									</dd>
								</dl>
<?php
}
$boardbarNumber = 0;
$positionCounter = 0;
$secondposition = array(0, 0);

if(Acl::check('group.owners')) {
	if(!isset($_REQUEST['edit'])) {
?>
								<ul id="widget-tabs-box" class="tabs-box">
									<li class="dashboard selected"><a href="<?php echo $ctx->getProperty('uri.blog');?>/owner/center/dashboard"><?php echo _t('알림판');?></a></li>
									<li class="edit"><a href="<?php echo $ctx->getProperty('uri.blog');?>/owner/center/dashboard?edit"><?php echo _t('편집');?></a></li>
									<li class="activate"><a href="<?php echo $ctx->getProperty('uri.blog');?>/owner/plugin?visibility=center"><?php echo _t('위젯 켜고 끄기');?></a>
<?php
		// Textcube-NG: 플러그인 알림 탭(알림이 있을 때만, n = 미확인 개수)(r3-13)
		if (Acl::check('group.administrators'))
			echo pluginNoticeTab(isset($_GET['pluginNotice']));
?>
								</ul>
<?php
	} else {
?>
								<ul id="widget-tabs-box" class="tabs-box">
									<li class="dashboard selected"><a href="<?php echo $ctx->getProperty('uri.blog');?>/owner/center/dashboard"><?php echo _t('돌아가기');?></a></li>
								</ul>
<?php
	}
} else {
?>
								<ul class="tabs-box">
<?php
	// Textcube-NG: 플러그인 알림 탭(알림이 있을 때만, n = 미확인 개수)(r3-13)
	if (!isset($_REQUEST['edit']) && Acl::check('group.administrators'))
		echo pluginNoticeTab(isset($_GET['pluginNotice']));
?>
								</ul>
<?php
}
?>
<?php
// Textcube-NG: 플러그인 차단·자동 꺼짐 알림(v3.219). r3-13: 탭 줄 아래로 옮기고 평소엔 접어 둔다('플러그인 알림 (n)' 탭으로 펼침, ?pluginNotice 면 펼친 채)
if (!isset($_REQUEST['edit']) && Acl::check('group.administrators')) {
	echo pluginNoticeRender(!isset($_GET['pluginNotice']));
}
?>
								<div id="widget-container-0" class="panel widget-container">
<?php
foreach ($newlayout as $mapping) {
	if ($mapping['plugin'] == 'TextcubeSeparator') {
?>

								</div>
								<div id="widget-container-<?php echo $boardbarNumber + 1;?>" class="panel widget-container">
<?php
		$secondposition[$boardbarNumber] = $positionCounter;
		$boardbarNumber++;
	} else if ($mapping['plugin'] == 'defaultDashboardWidget') {
		getDefaultCenterPanel($mapping);
	} else {
?>
									<div id="<?php echo $mapping['plugin'];?>" class="section">
										<h3 class="caption">
											<span><?php echo $mapping['title'];?>
<?php
		if (isset($_REQUEST['edit'])) {
?>

											<a class="widget-reorder-up" href="<?php echo $ctx->getProperty('uri.blog');?>/owner/center/dashboard?edit&pos=<?php echo $positionCounter; ?>&amp;rel=-1&edit"><?php echo _t('위로');?></a>
											<a class="widget-reorder-down" href="<?php echo $ctx->getProperty('uri.blog');?>/owner/center/dashboard?edit&pos=<?php echo $positionCounter;?>&amp;rel=1&edit"><?php echo _t('아래로');?></a>
<?php
		}
?>
											</span>
										</h3>
										<?php
		if (!isset($_REQUEST['edit'])) echo handleCenters($mapping);
?>
									</div>
<?php
	}
	$positionCounter++;
}
?>
								</div>
<?php
if ($boardbarNumber < 1) {
?>

								<div id="dojo_boardbar1" class="panel widget-container"></div>
<?php
	$secondposition[$boardbarNumber] = $positionCounter;
	$boardbarNumber++;
	$positionCounter++;
}
if ($boardbarNumber < 2) {
?>
								<div id="dojo_boardbar2" class="panel widget-container"></div>
<?php
	$secondposition[$boardbarNumber] = $positionCounter;
	$boardbarNumber++;
	$positionCounter++;
}

if(Acl::check('group.owners')) {
	if(!isset($_REQUEST['edit'])) {
?>
								<div id="widget-button-bottom" class="button-box">
									<input type="submit" class="input-button" value="<?php echo _t('편집');?>" onclick="window.location.href='<?php echo $ctx->getProperty('uri.blog');?>/owner/center/dashboard?edit'; return false;" />
									<input type="button" class="input-button" value="<?php echo _t('위젯 켜고 끄기');?>" onclick="window.location.href='<?php echo $ctx->getProperty('uri.blog');?>/owner/plugin?visibility=center'; return false;" />
								</div>
<?php
	} else {
?>
								<div class="button-box">
									<input type="button" class="input-button" value="<?php echo _t('돌아가기');?>" onclick="window.location.href='<?php echo $ctx->getProperty('uri.blog');?>/owner/center/dashboard'; return false;" />
								</div>
<?php
	}
} else {
?>
								<div class="button-box">
								</div>
<?php
}
?>
							</div>
						</form>
<?php
if(Acl::check("group.creators") && !isset($_REQUEST['edit'])) {
?>
						<form id="dataOptimizer" method="post" action="<?php echo $ctx->getProperty('uri.blog');?>/owner/data/optimize" target="blackhole"></form>
						<div id="optimizingDialogBox" class="part">
							<div id="optimizingDataDialog" class="system-dialog" style="position: absolute; display: none; z-index: 500;">
								<h4 id="optimizingDataDialogTitle"></h4>
								<div class="message-sub">
									<span id="optimizingText"></span>
									<span id="optimizingTextSub"></span>
								</div>
								<div id="optimizingIndicator" class="progressBar" style="width: 0%; height: 18px; margin-top: 5px; background-color: #66DDFF;"></div>
							</div>
						</div>
						<iframe id="blackhole" name="blackhole" style="display: none;"></iframe>
<?php
}


//************ Default Center Widget module.
function getDefaultCenterPanel($mapping) {
	$ctx = Model_Context::getInstance();
	$blogid = $ctx->getProperty('blog.id');
?>
									<div id="<?php echo $mapping['plugin'];?>" class="section">
									<h3 class="caption<?php echo isset($_REQUEST['edit']) ? ' visible' : ' invisible';?>">
											<span><?php echo _t('알림판');?>
<?php
	if (isset($_REQUEST['edit'])) {
?>
											<a id="<?php echo $mapping['plugin'];?>dojoup" href="<?php echo $ctx->getProperty('uri.blog');?>/owner/center/dashboard?edit&pos=<?php echo $positionCounter; ?>&amp;rel=-1&edit"><?php echo _t('위로');?></a>
											<a id="<?php echo $mapping['plugin'];?>dojodown" href="<?php echo $ctx->getProperty('uri.blog');?>/owner/center/dashboard?edit&pos=<?php echo $positionCounter;?>&amp;rel=1&edit"><?php echo _t('아래로');?></a>
<?php
	}
?>
											</span>
										</h3>
<?php
	if (isset($_REQUEST['edit'])) {
?>
									</div>
<?php
		return true;
	} else {
		// Get default data
		$stats = getStatistics($blogid);
		$latestEntryId = Setting::getBlogSettingGlobal('LatestEditedEntry_user'.getUserId(),0);

		$comments = getRecentComments($blogid,10);
		$guestbooks = getRecentGuestbook($blogid,10);
		list($commentNotifies,$paging) = getCommentsNotifiedWithPagingForOwner($blogid,0,null,null,null,1,10);
		$trackbacks = getRecentTrackbacks($blogid,10);
		$recents = array();	// title, date, link, category
		foreach($comments as $commentRow) {
			array_push($recents, array(
			'title'   =>$commentRow['comment'],
			'date'    =>$commentRow['written'],
			'link'    => $ctx->getProperty('uri.blog')."/".$commentRow['entry']."#comment".$commentRow['id'],
			'category'=>'comment'));
		}
		foreach($commentNotifies as $commentRow) {
			array_push($recents, array(
			'title'   =>$commentRow['comment'],
			'date'    =>$commentRow['written'],
			'link'    => $ctx->getProperty('uri.blog')."/owner/communication/notify",
			'category'=>'commentNotify'));
		}
		foreach($guestbooks as $guestbook) {
			array_push($recents, array(
			'title'   =>$guestbook['comment'],
			'date'    =>$guestbook['written'],
			'link'    => $ctx->getProperty('uri.blog')."/guestbook/".$guestbook['id']."#guestbook".$guestbook['id'],
			'category'=>'guestbook'));
		}
		foreach($trackbacks as $trackbackRow) {
			array_push($recents, array(
			'title'   =>$trackbackRow['subject'],
			'date'    =>$trackbackRow['written'],
			'link'    => $ctx->getProperty('uri.blog')."/".$trackbackRow['entry']."#trackback".$trackbackRow['id'],
			'category'=>'trackback'));
		}
		$sort_array = array();
		foreach($recents as $uniqid => $row){	// Sorting.
			foreach($row as $key=>$value){
				if (!array_key_exists($key, $sort_array)) {
					$sort_array[$key] = array();
				}
				$sort_array[$key][$uniqid] = $value;
			}
		}
		if (!empty($sort_array)) {
			array_multisort($sort_array['date'],SORT_DESC,$recents);
		}
		$recents = array_slice($recents, 0, 14);

?>
										<div id="shortcut-collection">
											<h4 class="caption"><span><?php echo _t('바로가기');?></span></h4>

											<ul>
												<li class="newPost"><a class="newPost" href="<?php echo $ctx->getProperty('uri.blog');?>/owner/entry/post"><span><?php echo _t('새 글 쓰기');?></span></a></li>
<?php
		if($latestEntryId !== 0) {
			$latestEntry = getEntry($blogid,$latestEntryId);
			if(!is_null($latestEntry)) {
?>
												<li class="modifyPost"><a href="<?php echo $ctx->getProperty('uri.blog');?>/owner/entry/edit/<?php echo $latestEntry['id'];?>"><?php echo _f('최근글(%1) 수정', htmlspecialchars(UTF8::lessenAsEm($latestEntry['title'],10)));?></a></li>
<?php
			}
		}
		if ($ctx->getProperty('service.reader') == true) {
?>
												<li class="rssReader"><a href="<?php echo $ctx->getProperty('uri.blog');?>/owner/network/reader"><?php echo _t('RSS로 등록한 이웃 글 보기');?></a></li>
<?php
		}
		if(Acl::check("group.administrators")) {
?>
												<li class="deleteCache"><a href="<?php echo $ctx->getProperty('uri.blog');?>/owner/center/dashboard/cleanup" onclick="cleanupCache();return false;"><?php echo _t('캐시 지우기');?></a></li>
<?php
			if(Acl::check("group.creators")) {
?>
												<li class="optimizeStorage"><a href="<?php echo $ctx->getProperty('uri.blog');?>/owner/data" onclick="optimizeData();return false;"><?php echo _t('저장소 최적화');?></a></li>
<?php
			}
		}
?>
												<li class="clear"></li>
											</ul>
										</div>

										<div id="total-information">
											<h4 class="caption"><span><?php echo _t('요약');?></span></h4>

											<table class="posts-line">
												<caption><?php echo _t('글');?></caption>
												<thead>
													<th>type</th>
													<th>sum</th>
												</thead>
												<tbody>
													<tr>
														<td class="type"><?php echo _t('글');?></td>
														<td class="sum"><?php echo number_format(getEntriesTotalCount($blogid));?></td>
													</tr>
													<tr>
														<td class="type"><?php echo _t('댓글');?></td>
														<td class="sum"><?php echo number_format(getCommentCount($blogid));?></td>
													</tr>
													<tr>
														<td class="type"><?php echo _t('방명록');?></td>
														<td class="sum"><?php echo number_format(getGuestbookCount($blogid));?></td>
													</tr>
													<tr>
														<td class="type"><?php echo _t('걸린 글');?></td>
														<td class="sum"><?php echo number_format(getTrackbackCount($blogid));?></td>
													</tr>
												</tbody>
											</table>
											<table class="visitors-line">
												<caption><?php echo _t('방문자');?></caption>
												<thead>
													<th>type</th>
													<th>sum</th>
												</thead>
												<tbody>
													<tr>
														<td class="type"><?php echo _t('오늘');?></td>
														<td class="sum"><?php echo number_format($stats['today']);?></td>
													</tr>
													<tr>
														<td class="type"><?php echo _t('어제');?></td>
														<td class="sum"><?php echo number_format($stats['yesterday']);?></td>
													</tr>
													<tr>
														<td class="type"><?php echo _t('7일 평균');?></td>
														<td class="sum"><?php
	$weekly = Statistics::getWeeklyStatistics();
	$weeklycount = 0;
	foreach($weekly as $day) $weeklycount += $day['visits'];
	echo number_format($weeklycount/7);
	unset($weekly);
	unset($weeklycount);
	?></td>
													</tr>
													<tr>
														<td class="type"><?php echo _t('총방문자');?></td>
														<td class="sum"><?php echo number_format($stats['total']);?></td>
													</tr>
												</tbody>
											</table>
										</div>

										<div id="myBlogInfo">
											<h4 class="caption"><span><a href="<?php echo $ctx->getProperty('uri.blog').'/owner/communication/comment';?>"><?php echo _t('알림판');?></a></span></h4>
											<table class="recent">
												<caption>asdasd</caption>
												<thead>
													<tr>
														<th scope="col" class="date"><?=_t('날짜')?></th>
														<th scope="col" class="category"><?=_t('종류')?></th>
														<th scope="col"><?=_t('내용')?></th>
													</tr>
												</thead>
												<tbody>
<?php
		foreach ($recents as $item) {
?>
													<tr class="<?php echo $item['category'];?>">
														<td class="date"><?php echo Timestamp::format('%m/%d',$item['date']);?></td>
														<td class="category">
															<?php
			switch($item['category']) {
				case 'trackback' :
					echo '<a href="'.$ctx->getProperty('uri.blog').'/owner/communication/trackback?status=received">'._t('걸린글').'</a>';break;
				case 'comment' :
					echo '<a href="'.$ctx->getProperty('uri.blog').'/owner/communication/comment?status=comment">'._t('댓글').'</a>';break;
				case 'commentNotify' :
					echo '<a href="'.$ctx->getProperty('uri.blog').'/owner/communication/notify">'._t('알리미').'</a>';break;
				case 'guestbook' :
					echo '<a href="'.$ctx->getProperty('uri.blog').'/owner/communication/comment?status=guestbook">'._t('방명록').'</a>';break;
			}
?>
														</td>
														<td class="title"><a href="<?php echo htmlspecialchars($item['link'], ENT_QUOTES | ENT_SUBSTITUTE);?>"><?php echo htmlspecialchars(UTF8::lessenAsEm($item['title'],20));?></a></td>
													</tr>
<?php
		}
?>
												</tbody>
											</table>
										</div>

<?php
		// Textcube-NG: 공지 피드 주소는 config.php 의 $service['noticeFeedURL'] 로 정한다({lang} = 블로그 언어, 없으면 ko).
		// 비어 있으면(기본) 캐시를 읽지 않고 원격 요청도 하지 않는다. 캐시에는 가져온 주소를 함께 두고, 주소가 바뀌었거나
		// 옛 형식(목록만)이면 다시 가져온다(v3.220)
		$noticeLanguage = ($ctx->getProperty('blog.language') ? $ctx->getProperty('blog.language') : "ko");
		$noticeURLRSS = $ctx->getProperty('service.noticeFeedURL');
		$noticeURLRSS = is_string($noticeURLRSS) ? str_replace('{lang}', $noticeLanguage, $noticeURLRSS) : '';
		$noticeEntries = array();
		$noticeCache = ($noticeURLRSS !== '') ? Setting::getServiceSetting('TextcubeNotice'.$noticeLanguage) : null;	// Textcube-NG: 공지 캐시 키도 피드 주소와 같은 언어($noticeLanguage, 빈 값이면 ko)를 쓴다(r3-5)
		// Textcube-NG: 캐시 문자열이 깨지면 unserialize() 는 false 와 함께 E_NOTICE(8.3 부터 E_WARNING)를 낸다 — 끄는 옵션이 없어 @ 로 억제하고, false 는 아래 is_array 검사가 다시 가져오기로 처리한다(v3.222)
		$noticeCache = is_string($noticeCache) ? @unserialize($noticeCache, array('allowed_classes' => false)) : false;

		if (is_array($noticeCache) && isset($noticeCache['url'], $noticeCache['entries']) && $noticeCache['url'] === $noticeURLRSS && is_array($noticeCache['entries'])) {
			$noticeEntries = $noticeCache['entries'];
		} else if ($noticeURLRSS !== '') {
			list($result, $feed, $xml) = getRemoteFeed($noticeURLRSS);
			if ($result == 0) {
				$xmls = new XMLStruct();
				$xmls->setXPathBaseIndex(1);
				$noticeEntries = array();
				if ($xmls->open($xml, $ctx->getProperty('service.encoding'))) {
					if ($xmls->getAttribute('/rss', 'version')) {
						for ($i = 1; $xmls->doesExist("/rss/channel/item[$i]"); $i++) {	// Textcube-NG: link 가 없는 item 은 건너뛰고 다음 item 을 읽는다(원본은 거기서 목록이 끊겼다, 아래 Atom 분기와 같은 꼴, r3-15)
							$link = $xmls->getValue("/rss/channel/item[$i]/link");
							if (!is_string($link) || ($link === ''))
								continue;
							$item = array('permalink' => rawurldecode($link));
							$item['title'] = $xmls->getValue("/rss/channel/item[$i]/title");
							if ($xmls->getValue("/rss/channel/item[$i]/pubDate"))
								$item['written'] = parseDate($xmls->getValue("/rss/channel/item[$i]/pubDate"));
							else if ($xmls->getValue("/rss/channel/item[$i]/dc:date"))
								$item['written'] = parseDate($xmls->getValue("/rss/channel/item[$i]/dc:date"));
							else
								$item['written'] = 0;
							array_push($noticeEntries, $item);
						}
					} else if ($xmls->doesExist('/feed')) {
						// Textcube-NG: Atom(RFC 4287) 공지 피드도 읽는다. 원본은 <rss> 만 읽어 Atom 주소를 넣으면 공지가 비었다(r3-5).
						// 피드 읽기(reader.common.php saveFeedItems)의 /feed 경로(entry·link rel·href)를 따르고, 이 화면의 항목 구조(permalink·title·written)만 채운다.
						// 링크는 rel 이 alternate 이거나 rel 이 없는(RFC 4287 4.2.7.2: 기본값 alternate) 첫 link 의 href, 날짜는 published → updated 순이다.
						// 링크가 없는 entry 는 건너뛴다(RSS 분기도 link 가 있는 item 만 담는다). 출력할 때의 링크 거르기(http·https)와 escape 는 RSS 와 같다
						for ($i = 1; $xmls->doesExist("/feed/entry[$i]"); $i++) {
							$link = '';
							for ($j = 1; $xmls->doesExist("/feed/entry[$i]/link[$j]"); $j++) {
								$href = $xmls->getAttribute("/feed/entry[$i]/link[$j]", 'href');
								if (($xmls->getAttribute("/feed/entry[$i]/link[$j]", 'rel', 'alternate') === 'alternate') && is_string($href) && ($href !== '')) {
									$link = $href;
									break;
								}
							}
							if ($link === '')
								continue;
							$item = array('permalink' => rawurldecode($link));
							$item['title'] = $xmls->getValue("/feed/entry[$i]/title");
							if ($xmls->getValue("/feed/entry[$i]/published"))
								$item['written'] = parseDate($xmls->getValue("/feed/entry[$i]/published"));
							else if ($xmls->getValue("/feed/entry[$i]/updated"))
								$item['written'] = parseDate($xmls->getValue("/feed/entry[$i]/updated"));
							else
								$item['written'] = 0;
							array_push($noticeEntries, $item);
						}
					}
				}
				Setting::setServiceSetting('TextcubeNotice'.$noticeLanguage,serialize(array('url' => $noticeURLRSS, 'entries' => $noticeEntries)));
			}
		}
?>
										<div id="textcube-notice">
											<h4 class="caption"><span><?php echo ($noticeURLRSS !== '' ? '<a href="'.htmlspecialchars($noticeURLRSS, ENT_QUOTES | ENT_SUBSTITUTE).'">'._t('공지사항').'</a>' : _t('공지사항'));?></span></h4>
<?php
		if (count($noticeEntries) > 0) {
			array_splice($noticeEntries, 3, count($noticeEntries) - 3);
?>
											<table>
												<tbody>
<?php
			foreach($noticeEntries as $item) {
				if (!is_array($item)) continue;	// Textcube-NG: 캐시·피드 항목이 배열이 아니면 PHP 8 에서 $item['…'] 이 TypeError(문자열) 또는 경고 — 원본 5.6 은 첫 글자 등 의미 없는 값을 보였다. 링크(v3.220)와 같이 배열이 아닌 항목은 건너뛴다. 아래 날짜·제목은 written·title 이 없으면(title 이 문자열이 아니면) 0·'' 로 넘긴다 — 원본과 같은 화면에 키 없음 경고·null 인자 deprecation(8.1)만 없앤다(v3.222)
				// Textcube-NG: 공지 항목 링크는 출력할 때 거른다(v3.220). 문자열이고 앞뒤 공백을 뺀 값의 scheme 이 http·https(대소문자 무관)일 때만
				// escape 해서 href 에 넣는다. 그 밖의 값(javascript:·data:·scheme 없음·배열 등)은 링크 없이 제목만 보인다. 캐시에서 읽은 값도 같다.
				$noticeLink = (is_array($item) && isset($item['permalink']) && is_string($item['permalink'])) ? trim($item['permalink']) : '';
				if (preg_match('/^https?:/i', $noticeLink) !== 1) $noticeLink = '';
?>
													<tr>
														<td class="date"><?php echo Timestamp::format2(isset($item['written']) ? $item['written'] : 0);?></td>
														<td class="title"><?php if ($noticeLink !== '') { ?><a href="<?php echo htmlspecialchars($noticeLink, ENT_QUOTES | ENT_SUBSTITUTE);?>" onclick="return openLinkInNewWindow(this);" ><?php } ?><?php echo htmlspecialchars(UTF8::lessenAsEm((isset($item['title']) && is_string($item['title'])) ? $item['title'] : '',35));?><?php if ($noticeLink !== '') { ?></a><?php } ?></td>
													</tr>
<?php
			}
?>
												</tbody>
											</table>

<?php
		} else {
?>
											<div id="fail-notice">
												<?php echo ($noticeURLRSS === '' ? _t('공지 피드 주소가 설정되지 않았습니다.') : _t('공지사항을 가져올 수 없습니다. 잠시 후 다시 시도해 주십시오.'));?>
											</div>
<?php
		}
?>
										</div>
<?php

	}
?>
									</div>
<?php
}
require ROOT . '/interface/common/owner/footer.php';
?>
