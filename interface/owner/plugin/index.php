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

require ROOT . '/library/preprocessor.php';
require ROOT . '/interface/common/owner/header.php';

requireModel('common.plugin');

$pageCache = pageCache::getInstance();
$pageCache->reset('PluginSettings');
$pageCache->purge();

// set the selected tab.
if (isset($_GET['visibility'])) {
	$_POST['visibility'] = $_GET['visibility'];
} else if (!isset($_POST['visibility'])) {
	$_POST['visibility'] = 'blog';
}

switch ($_POST['visibility']) {
	case 'center':
		define('__TAB_CENTER__', true);
		$memberScopes = 'center';
		break;
	case 'coverpage':
		define('__TAB_COVERPAGE__', true);
		$memberScopes = 'coverpage'; // 임시.
		break;
	case 'blog':
	default:
		define('__TAB_BLOG__', true);
		$memberScopes = 'global|blog|sidebar|admin|editor|formatter|none';
		$_POST['visibility'] = 'blog';
		break;
}

$tabsClass = array();
$tabsClass[$_POST['visibility']] = true;

// Search.
if(isset($_POST['search'])) {
	$search = $_POST['search'];
} else $search = null;

// get and set align type, scope type and status type.
$selectedSort = Setting::getBlogSettingGlobal('pluginListSortType', 'ascend');
$selectedScopes = explode('|', Setting::getBlogSettingGlobal("pluginListScopeType_{$_POST['visibility']}", $memberScopes));
$selectedStatus = explode('|', Setting::getBlogSettingGlobal("pluginListStatusType_{$_POST['visibility']}", 'activated|deactivated'));

// get the list type.
$listType = Setting::getBlogSettingGlobal('pluginViewType', 'iconview');

// Textcube-NG: r3-42 끌 때 보관해 둔 설정 목록(꺼진 플러그인의 [보관 설정 지우기] 표시용)
$pluginKeptSettings = pluginKeptSettingsLoad();

// get all plugin list.
$plugins = array();
$pluginAttrs = array();

$dir = dir(ROOT . '/plugins/');
while (false !== ($plugin = $dir->read())) { // 이게 php.net에서 권장하는 올바른 디렉토리 읽는 법.
	$plugin = trim($plugin);
	$pluginInfo = getPluginInformation($plugin);
	if(empty($pluginInfo)) continue;
	if($pluginInfo['privilege'] == 'administrator' && !Acl::check('group.creators')) continue;
	
	if(!empty($search) && 
		(stristr($pluginInfo['title'],$search) === false) && 
		(stristr($pluginInfo['description'],$search) === false) &&
		(stristr($pluginInfo['author'],$search) === false)) continue; // Search.
		
	$acceptedPathCount = 0;
	$tempXMLPathCount = 0;
	if(empty($pluginInfo['scope'])) continue;
	
	foreach($pluginInfo['scope'] as $pluginScope) {
		if (in_array($pluginScope, $selectedScopes)) {
			$acceptedPathCount++;
		}
	}
	if($acceptedPathCount == 0) continue;

	$pluginDir = $pluginInfo['directory'];
	$pluginAttrs[$pluginDir] = $pluginInfo;
	$plugins[$pluginDir] = $pluginAttrs[$pluginDir]['title'];
}

// sort as value of $selectedSort.
if ($selectedSort == 'ascend') {
	asort($plugins);
} else {
	arsort($plugins);
}

$pluginKeys = array_keys($plugins);
?>
						<script type="text/javascript">
							//<![CDATA[
								var pluginInfo = new Array();
								
<?php
for ($i=0; $i<count($pluginKeys); $i++) {
	$pluginDir = $pluginKeys[$i];
	
	$width = $pluginAttrs[$pluginDir]['width']?$pluginAttrs[$pluginDir]['width']:500;
	$height = $pluginAttrs[$pluginDir]['height']?$pluginAttrs[$pluginDir]['height']:525;
?>
								pluginInfo['<?php echo $pluginDir;?>'] = new Array();
								pluginInfo['<?php echo $pluginDir;?>']['width'] = <?php echo $width;?>;
								pluginInfo['<?php echo $pluginDir;?>']['height'] = <?php echo $height;?>;
<?php
}
?>
								
								// Textcube-NG: r3-42 플러그인 작업(켜기·끄기·다시 검사·보관 설정 지우기) 처리 중 표시. 처리 중에 다시 누르면 막고 안내한다(서버도 블로그별 잠금으로 거절한다)
								var pluginOpBusy = false;
								function pluginOpBegin() {
									if (pluginOpBusy) {
										PM.showMessage("<?php echo escapeJSInCData(_t('앞서 요청한 플러그인 작업을 처리하고 있습니다. 끝난 뒤 다시 누르십시오.'));?>", "center", "bottom");
										return false;
									}
									pluginOpBusy = true;
									return true;
								}
								function pluginOpEnd() {
									pluginOpBusy = false;
								}
								
								function togglePlugin(plugin, num, width, height, obj, force) {
									if (!pluginOpBegin()) return false;	// Textcube-NG: r3-42 처리 중이면 막는다(응답이 오면 onSuccess·onError 에서 푼다)
									var currentIcon = document.getElementById('pluginIcon'+num);
									var currentSettingButton = document.getElementById('pluginSettingButton'+num);
									
									if (force == 'activate') {
										command = true;
									} else if (force == 'deactivate') {
										command = false;
									} else if (document.getElementById('pluginStatus'+num).value == 0) {
										command = true;
									} else {
										command = false;
									}
									
									if (command) {
										var request = new HTTPRequest("POST", "<?php echo $blogURL;?>/owner/plugin/activate");
										request.onSuccess = function() {												
											pluginOpEnd();	// Textcube-NG: r3-42
											var guardMsg = this.getText("/response/message"); // Textcube-NG: 활성화 경고(v3.211)
											if (guardMsg) alert(guardMsg);
											var keptButton = document.getElementById('pluginKeptButton'+num);	// Textcube-NG: r3-42 다시 켜면 보관 설정이 돌아가므로 [보관 설정 지우기]를 없앤다
											if (keptButton && keptButton.parentNode) keptButton.parentNode.removeChild(keptButton);
											currentIcon.setAttribute('alt', '<?php echo _t('켜짐');?>');
											currentIcon.setAttribute('title', '<?php echo _t('이 플러그인은 사용중입니다. 클릭하시면 사용을 중지합니다.');?>');
											document.getElementById('pluginStatus'+num).value = 1;
											objLI = getParentByTagName("LI", obj);
											
											if (document.getElementById('activated-plugin').checked == false) {
												objLI.parentNode.removeChild(objLI);
											} else {
												objLI.className = objLI.className.replace('inactive', 'active');
												
												if (STD.isIE6) {
													if (currentIcon.style.filter == 'progid:DXImageTransform.Microsoft.AlphaImageLoader(src="<?php echo $service['path'] . $adminSkinSetting['skin'];?>/image/icon_plugin_off.png", sizingMethod="scale")')
														currentIcon.style.filter = 'progid:DXImageTransform.Microsoft.AlphaImageLoader(src="<?php echo $service['path'] . $adminSkinSetting['skin'];?>/image/icon_plugin_on.png", sizingMethod="scale")'
													else
														currentIcon.style.filter = 'progid:DXImageTransform.Microsoft.AlphaImageLoader(src="<?php echo $service['path'];?>/plugins/' + plugin + '/images/icon_plugin_on.png", sizingMethod="scale")'
												} else {
													if (currentIcon.src == "<?php echo $hostURL.$service['path'] . $adminSkinSetting['skin'];?>/image/icon_plugin_off.png")
														currentIcon.src = '<?php echo $service['path'] . $adminSkinSetting['skin'];?>/image/icon_plugin_on.png';
													else
														currentIcon.src = '<?php echo $service['path'];?>/plugins/' + plugin + '/images/icon_plugin_on.png';
												}
											
												if (currentSettingButton.className == 'dimmed') {
													tempLink = document.createElement('A');
													tempLink.onclick = function() { getCurrentSetting(plugin, 'Y', width, height, 'setting'); return false; };
													tempLink.setAttribute('href', '#void');
													tempLink.innerHTML = '<?php echo _t('환경설정');?>';
													
													currentSettingButton.innerHTML = '';
													currentSettingButton.className = 'enabled';
													currentSettingButton.appendChild(tempLink);
												}
											}
										}
										request.onError = function() {
											pluginOpEnd();	// Textcube-NG: r3-42
											var guardMsg = this.getText("/response/message"); // Textcube-NG: 활성화 거부 사유(v3.211)
											alert(guardMsg ? guardMsg : "<?php echo _t('플러그인을 활성화하는데 실패했습니다.');?>");
										}
										request.send("name=" + plugin);
									} else {
										var request = new HTTPRequest("POST", "<?php echo $blogURL;?>/owner/plugin/deactivate");

										request.onSuccess = function() {
											pluginOpEnd();	// Textcube-NG: r3-42
											currentIcon.setAttribute('alt', '<?php echo _t('꺼짐');?>');
											currentIcon.setAttribute('title', '<?php echo _t('이 플러그인은 사용중지 상태입니다. 클릭하시면 사용을 시작합니다.');?>');
											document.getElementById('pluginStatus'+num).value = 0;
											objLI = getParentByTagName("LI", obj);
											
											if (document.getElementById('deactivated-plugin').checked == false) {
												objLI.parentNode.removeChild(objLI);
											} else {
												objLI.className = objLI.className.replace('active', 'inactive');
												
												if (STD.isIE6) {
													if (currentIcon.style.filter == 'progid:DXImageTransform.Microsoft.AlphaImageLoader(src="<?php echo $service['path'] . $adminSkinSetting['skin'];?>/image/icon_plugin_on.png", sizingMethod="scale")')
														currentIcon.style.filter = 'progid:DXImageTransform.Microsoft.AlphaImageLoader(src="<?php echo $service['path'] . $adminSkinSetting['skin'];?>/image/icon_plugin_off.png", sizingMethod="scale")'
													else
														currentIcon.style.filter = 'progid:DXImageTransform.Microsoft.AlphaImageLoader(src="<?php echo $service['path'];?>/plugins/' + plugin + '/images/icon_plugin_off.png", sizingMethod="scale")'
												} else {
													if (currentIcon.src == "<?php echo $hostURL.$service['path'] . $adminSkinSetting['skin'];?>/image/icon_plugin_on.png")
														currentIcon.src = '<?php echo $service['path'] . $adminSkinSetting['skin'];?>/image/icon_plugin_off.png';
													else
														currentIcon.src = '<?php echo $service['path'];?>/plugins/' + plugin + '/images/icon_plugin_off.png';
												}
											
												if (currentSettingButton.className == 'enabled') {
													currentSettingButton.innerHTML = '<?php echo _t('환경설정');?>';
													currentSettingButton.className = 'dimmed';
												}
											}
										}
										request.onError = function() {
											pluginOpEnd();	// Textcube-NG: r3-42
											var opMsg = this.getText("/response/message");	// Textcube-NG: r3-42 서버가 사유를 보냈으면(다른 작업 처리 중 등) 그것을 보인다
											if (opMsg) {
												alert(opMsg);
												return;
											}
											switch(parseInt(this.getText("/response/error"))) {
												case 1:
													alert("<?php echo _t('플러그인을 비활성화하지 못하였습니다.');?>");
													break;
												case 2:
													alert("<?php echo _t('모듈을 비활성화하지 못하였습니다.');?>\n\n<?php echo _t('텍스트큐브의 동작을 위하여 모듈이 필요한 부분마다 최소 하나 이상이 필요합니다.\n현재 해당 모듈이 하나밖에 존재하지 않기 때문에 이 모듈의 동작을 중지시킬 수 없습니다.');?>");
													break;
											}
										}
										request.send("name=" + plugin);
									}
								}
								
								function changeList(obj) {
									var currentTab = getObject('currentTab');
									
									var scope = new Array();
									var status = new Array();
									
									var scopeCount = 0;
									var statusCount = 0;
									
									for (var i=0; getObject('part-plugin-list').elements[i]; i++) {
										oElement = getObject('part-plugin-list').elements[i];
										if (oElement.name == 'scopeType' && oElement.checked == true) {
											scope[scopeCount] = oElement.value;
											scopeCount++;
										} else if (oElement.name == 'pluginStatus' && oElement.checked == true) {
											status[statusCount] = oElement.value;
											statusCount++;
										} else if (oElement.name == 'sortType' && oElement.checked == true) {
											var sort = oElement.value;
										} else if (oElement.name == 'viewType' && oElement.checked == true) {
											var viewtype = oElement.value;
										}
									}
									
									if (scope.length == 0 || status.length == 0) {
										obj.checked = true;
										return false;
									}
									
									var request = new HTTPRequest("POST", "<?php echo $blogURL;?>/owner/plugin/saveScope");

									request.onSuccess = function() {
										document.getElementById('part-plugin-list').submit();
									}
									
									request.onError = function() {
										alert("<?php echo _t('선택하신 조건을 적용할 수 없었습니다.');?>");
									}
									
									request.send("visibility=" + currentTab.value + "&scope=" + scope.join('|') + "&status=" + status.join('|') + "&sort=" + sort + "&viewtype=" + viewtype);
								}
								
								window.addEventListener("load", execLoadFunction, false);
								
								function execLoadFunction() {
									if (STD.isIE6) {
										var pluginIcons = document.getElementById('part-plugin-list').getElementsByTagName('img');
										
										for (var i=0; i<pluginIcons.length; ++i) {
											var temp = pluginIcons[i].src;
											pluginIcons[i].setAttribute('src', "<?php echo $service['path'];?>/resources/image/spacer.gif");
											pluginIcons[i].style.filter = 'progid:DXImageTransform.Microsoft.AlphaImageLoader(src="' + temp + '", sizingMethod="scale")';
										}
									}
									
									for (var i=0; getObject('part-plugin-list').elements[i]; ++i) {
										oElement = getObject('part-plugin-list').elements[i];
										if ((oElement.name == 'plugin'))
											oElement.style.display = 'none';	
									}
								}
								
								var currentSetting='';
								function getCurrentSetting( plugin, setYN, width, height, tab) {
									//if( "N" == setYN ) return ;
									if( '' != currentSetting ) currentSetting.close();
									window.open('<?php echo $blogURL;?>/owner/plugin/currentSetting/?Name='+plugin+'&Tab='+tab, 'CurrentSetting', 'width='+width+', height='+height+', scrollbars=1, status=0, resizable=1');	
									return;
								}								

								// Textcube-NG: 차단 풀기(r3-13). r3-42 끄지 않고 켜진 채로 지금 파일을 다시 검사해 무결성 기록을 새로 남긴다(owner/plugin/recheck 한 번 호출 — 설정은 그대로 둔다)
								function recheckPlugin(plugin) {
									if (pluginOpBusy) {
										PM.showMessage("<?php echo escapeJSInCData(_t('앞서 요청한 플러그인 작업을 처리하고 있습니다. 끝난 뒤 다시 누르십시오.'));?>", "center", "bottom");
										return false;
									}
									tcConfirm("<?php echo escapeJSInCData(_t('이 플러그인을 지금 파일로 다시 검사합니다. 무결성 기록을 새로 남기므로, 파일을 바꾼 사람이 본인이거나 믿을 수 있는 사람일 때만 진행하십시오. 플러그인은 켜진 채로 두고 설정도 그대로 둡니다. 계속하시겠습니까?'));?>", function () {	// Textcube-NG: 확인창을 페이지 위 안내 줄의 [확인]/[취소]로 바꿈(r3-17, R-396)
										if (!pluginOpBegin()) return false;
										var request = new HTTPRequest("POST", "<?php echo $blogURL;?>/owner/plugin/recheck");
										request.onSuccess = function() {
											pluginOpEnd();
											var guardMsg = this.getText("/response/message");
											if (guardMsg) alert(guardMsg);
											document.getElementById('part-plugin-list').submit();
										}
										request.onError = function() {
											pluginOpEnd();
											var guardMsg = this.getText("/response/message");
											alert(guardMsg ? guardMsg : "<?php echo escapeJSInCData(_t('다시 검사하지 못했습니다.'));?>");
										}
										request.send("name=" + encodeURIComponent(plugin));
										return false;
									});
									return false;
								}
								
								// Textcube-NG: r3-42 꺼진 플러그인의 보관 설정 지우기(owner/plugin/resetSetting, scope=kept)
								function clearKeptPluginSetting(plugin, num) {
									if (pluginOpBusy) {
										PM.showMessage("<?php echo escapeJSInCData(_t('앞서 요청한 플러그인 작업을 처리하고 있습니다. 끝난 뒤 다시 누르십시오.'));?>", "center", "bottom");
										return false;
									}
									tcConfirm("<?php echo escapeJSInCData(_t('플러그인을 끌 때 보관해 둔 설정을 지웁니다. 다시 켜면 기본값으로 시작합니다. 되돌릴 수 없습니다. 계속하시겠습니까?'));?>", function () {
										if (!pluginOpBegin()) return false;
										var request = new HTTPRequest("POST", "<?php echo $blogURL;?>/owner/plugin/resetSetting");
										request.onSuccess = function() {
											pluginOpEnd();
											var keptButton = document.getElementById('pluginKeptButton'+num);
											if (keptButton && keptButton.parentNode) keptButton.parentNode.removeChild(keptButton);
											PM.showMessage("<?php echo escapeJSInCData(_t('보관 설정을 지웠습니다.'));?>", "center", "bottom");
										}
										request.onError = function() {
											pluginOpEnd();
											var guardMsg = this.getText("/response/message");
											alert(guardMsg ? guardMsg : "<?php echo escapeJSInCData(_t('보관 설정을 지우지 못했습니다.'));?>");
										}
										request.send("name=" + encodeURIComponent(plugin) + "&scope=kept");
										return false;
									});
									return false;
								}
<?php
if (Acl::check('group.creators')) {
?>

								// Textcube-NG: 허용 목록에 추가(r3-13). 서비스 관리자에게만 낸다(서버는 control/plugin/allow 가 POST·CSRF·group.creators 를 다시 검사)
								function allowPlugin(plugin) {
									tcConfirm("<?php echo escapeJSInCData(_t('이 플러그인을 서비스 허용 목록에 추가합니다. 모든 블로그에서 이 플러그인을 켤 수 있게 됩니다. 계속하시겠습니까?'));?>", function () {	// Textcube-NG: 확인창을 페이지 위 안내 줄의 [확인]/[취소]로 바꿈(r3-17, R-396)
									var request = new HTTPRequest("POST", "<?php echo $blogURL;?>/control/plugin/allow/");
									request.onSuccess = function() {
										document.getElementById('part-plugin-list').submit();
									}
									request.onError = function() {
										alert("<?php echo escapeJSInCData(_t('허용 목록에 추가하지 못했습니다.'));?>");
									}
									request.send("name=" + encodeURIComponent(plugin));
									return false;
									});
									return false;
								}

								// Textcube-NG: 무결성 검사 면제(r3-14). 서비스 관리자에게만 낸다(서버는 control/plugin/exempt 가 POST·CSRF·group.creators·이름을 다시 검사)
								function setPluginExempt(plugin, box) {
									var on = box.checked;
									if (on) {
										tcConfirm("<?php echo escapeJSInCData(_t('이 플러그인의 무결성 검사를 모든 블로그에서 건너뜁니다(엄격 모드 포함). 남긴 기록과 파일이 달라도 막지 않으므로, 플러그인 폴더를 바꿀 수 있는 사람을 모두 믿을 수 있을 때만 고르십시오. 허용 목록·서비스 관리자 전용·PDO 모드 검사는 그대로 합니다. 계속하시겠습니까?'));?>", function () {	// Textcube-NG: 확인창을 페이지 위 안내 줄의 [확인]/[취소]로 바꿈(r3-17, R-396)
											box.checked = true;
											tcPluginExemptSend();
										});
										return false;
									}
									tcPluginExemptSend();
									return true;
									function tcPluginExemptSend() {
									var request = new HTTPRequest("POST", "<?php echo $blogURL;?>/control/plugin/exempt/");
									request.onSuccess = function() {
										document.getElementById('part-plugin-list').submit();
									}
									request.onError = function() {
										box.checked = !on;
										alert("<?php echo escapeJSInCData(_t('무결성 검사 면제를 바꾸지 못했습니다.'));?>");
									}
									request.send("name=" + encodeURIComponent(plugin) + "&exempt=" + (on ? "1" : "0"));
									}
								}
<?php
}
?>
							//]]>
						</script>
						
						<form id="part-plugin-list" class="part" method="post" action="<?php echo parseURL($blogURL."/owner/plugin");?>">
							<input type="hidden" name="search" value="<?php echo $search;?>" />
							<h2 class="caption"><span class="main-text"><?php echo _t('설치된 플러그인 목록입니다');?></span></h2>
							
							<div class="main-explain-box">
								<p class="explain"><?php echo _t('블로그에 다양한 기능을 더해보세요. 원하는 기능이 든 아이콘을 눌러주면 바로 블로그의 기능이 업그레이드 됩니다.').'<br /> '._t('알림판 위젯 플러그인은 로그인후 보이는 알림판에 위젯을 추가해 줍니다.').' '._t('블로그 표지 위젯 플러그인은 블로그의 첫 화면이나 표지 화면에 출력되는 위젯을 추가해 줍니다.').' '._t('알림판 위젯 플러그인이나 블로그 표지 플러그인은 사이드바와 같이 자유롭게 끌어서 위치를 바꿀 수 있습니다.');?></p>
							</div>
							
							<ul id="plugin-tabs-box" class="tabs-box">
								<li<?php echo isset($tabsClass['blog']) ? ' class="selected"' : NULL;?>><a href="<?php echo $blogURL;?>/owner/plugin"><?php echo _t('블로그/관리자 기능');?></a></li>
								<li<?php echo isset($tabsClass['center']) ? ' class="selected"' : NULL;?>><a href="<?php echo $blogURL;?>/owner/plugin?visibility=center"><?php echo _t('알림판 위젯');?></a></li>
								<li<?php echo isset($tabsClass['coverpage']) ? ' class="selected"' : NULL;?>><a href="<?php echo $blogURL;?>/owner/plugin?visibility=coverpage"><?php echo _t('블로그 표지 위젯');?></a></li>
							</ul>
							
							<fieldset id="plugin-display-box">
								<legend><?php echo _t('표시할 플러그인의 종류를 선택하세요.');?></legend>
								
								<dl id="scope-line" class="line">
									<dt><?php echo _t('기능');?></dt>
									<dd id="scope-line-plugin">
										<ul>
<?php
if (defined('__TAB_BLOG__')) {
?>
											<li><input type="checkbox" class="checkbox" id="blog-scope" name="scopeType" value="blog" onclick="changeList(this)"<?php echo in_array('blog', $selectedScopes) ? ' checked="checked"' : '';?> /><label id="blog-scope-label" for="blog-scope"<?php echo in_array('blog', $selectedScopes) ? ' class="selected"' : '';?>><?php echo _t('블로그 플러그인');?></label></li>
											<li><input type="checkbox" class="checkbox" id="sidebar-scope" name="scopeType" value="sidebar" onclick="changeList(this)"<?php echo in_array('sidebar', $selectedScopes) ? ' checked="checked"' : '';?> /><label id="sidebar-scope-label" for="sidebar-scope"<?php echo in_array('sidebar', $selectedScopes) ? ' class="selected"' : '';?>><?php echo _t('사이드바 플러그인');?></label></li>
											<li><input type="checkbox" class="checkbox" id="admin-scope" name="scopeType" value="admin" onclick="changeList(this)"<?php echo in_array('admin', $selectedScopes) ? ' checked="checked"' : '';?> /><label id="admin-scope-label" for="admin-scope"<?php echo in_array('admin', $selectedScopes) ? ' class="selected"' : '';?>><?php echo _t('관리자 플러그인');?></label></li>
											<li><input type="checkbox" class="checkbox" id="common-scope" name="scopeType" value="global" onclick="changeList(this)"<?php echo in_array('global', $selectedScopes) ? ' checked="checked"' : '';?> /><label id="common-scope-label" for="common-scope"<?php echo in_array('global', $selectedScopes) ? ' class="selected"' : '';?>><?php echo _t('기타 플러그인');?></label></li>
											<li><input type="checkbox" class="checkbox" id="none-scope" name="scopeType" value="none" onclick="changeList(this)"<?php echo in_array('none', $selectedScopes) ? ' checked="checked"' : '';?> /><label id="none-scope-label" for="none-scope"<?php echo in_array('none', $selectedScopes) ? ' class="selected"' : '';?>><?php echo _t('분류 없음');?></label></li>
										</ul>
									</dd>
								</dl>
								
								<dl id="module-line" class="line">
									<dt><?php echo _t('모듈');?></dt>
									<dd>
										<ul>
											<li><input type="checkbox" class="checkbox" id="editor-scope" name="scopeType" value="editor" onclick="changeList(this)"<?php echo in_array('editor', $selectedScopes) ? ' checked="checked"' : '';?> /><label id="editor-scope-label" for="editor-scope"<?php echo in_array('editor', $selectedScopes) ? ' class="selected"' : '';?>><?php echo _t('에디터 모듈');?></label></li>
											<li><input type="checkbox" class="checkbox" id="formatter-scope" name="scopeType" value="formatter" onclick="changeList(this)"<?php echo in_array('formatter', $selectedScopes) ? ' checked="checked"' : '';?> /><label id="formatter-scope-label" for="formatter-scope"<?php echo in_array('formatter', $selectedScopes) ? ' class="selected"' : '';?>><?php echo _t('포매터 모듈');?></label></li>
<?php
} else if (defined('__TAB_CENTER__')) {
?>
											<li><input type="checkbox" class="checkbox" id="center-scope" name="scopeType" value="center" onclick="this.checked=true;" checked="checked" /><label id="center-scope-label" for="center-scope"<?php echo in_array('center', $selectedScopes) ? ' class="selected"' : '';?>><?php echo _t('알림판 위젯');?></label></li>
<?php
} else if (defined('__TAB_COVERPAGE__')) {
?>
											<li><input type="checkbox" class="checkbox" id="coverpage-scope" name="scopeType" value="coverpage" onclick="this.checked=true;" checked="checked" /><label id="coverpage-scope-label" for="coverpage-scope"<?php echo in_array('coverpage', $selectedScopes) ? ' class="selected"' : '';?>><?php echo _t('블로그 표지 위젯');?></label></li>
<?php
}
?>
										</ul>
									</dd>
								</dl>
								
								<dl id="status-line" class="line">
									<dt><?php echo _t('상태');?></dt>
									<dd id="sorting-line-status">
										<label for="activated-plugin"><input type="checkbox" class="checkbox" id="activated-plugin" name="pluginStatus" value="activated" onclick="changeList(this);return false;"<?php echo in_array('activated', $selectedStatus) ? ' checked="checked"' : '';?> /><?php echo defined('__TAB_ETC__') ? _t('사용중인 플러그인/모듈') : _t('사용중인 플러그인');?></label>
										<label for="deactivated-plugin"><input type="checkbox" class="checkbox" id="deactivated-plugin" name="pluginStatus" value="deactivated" onclick="changeList(this)"<?php echo in_array('deactivated', $selectedStatus) ? ' checked="checked"' : '';?> /><?php echo defined('__TAB_ETC__') ? _t('사용중이 아닌 플러그인/모듈') : _t('사용하지 않는 플러그인');?></label>
									</dd>
								</dl>
								
								<dl id="sorting-line" class="line">
									<dt class="hidden"><?php echo _t('정렬');?></dt>
									<dd id="sorting-line-align">
										<input type="radio" class="radio" id="ascend-sorting" name="sortType" value="ascend" onclick="changeList(this)"<?php echo $selectedSort == 'ascend' ? ' checked="checked"' : '';?> /><label for="ascend-sorting"><?php echo _t('오름차순');?></label>
										<input type="radio" class="radio" id="descend-sorting" name="sortType" value="descend" onclick="changeList(this)"<?php echo $selectedSort == 'descend' ? ' checked="checked"' : '';?> /><label for="descend-sorting"><?php echo _t('내림차순');?></label>
									</dd>
								</dl>
								
								<dl id="viewmode-line" class="line">
									<dt class="hidden"><?php echo _t('출력 설정');?></dt>
									<dd id="viewmode-line-align">
										<input type="radio" class="radio" id="list-view" name="viewType" value="listview" onclick="changeList(this);return false;"<?php echo $listType == 'listview' ? ' checked="checked"' : '';?> /><label for="list-view"><?php echo _t('리스트 보기');?></label>
										<input type="radio" class="radio" id="icon-view" name="viewType" value="iconview" onclick="changeList(this);return false;"<?php echo $listType == 'iconview' ? ' checked="checked"' : '';?> /><label for="icon-view"><?php echo _t('아이콘 보기');?></label>
									</dd>
								</dl>
<?php
if (defined('__TAB_CENTER__') || defined('__TAB_COVERPAGE__')) {
	$text = defined('__TAB_CENTER__') ? _t('센터로 바로 가기') : _t('표지 설정으로 바로가기');
	$link = defined('__TAB_CENTER__') ? $blogURL . '/owner/center/dashboard' : $blogURL . '/owner/skin/coverpage';
} else {
	$text = _t('사이드바로 바로 가기');
	$link = $blogURL . '/owner/skin/sidebar';
}
?>
								<dl id="direct-link-line" class="line">
									<dt><?php echo _t('메인 화면으로 이동');?></dt>
									<dd>
										<a class="button" href="<?php echo $link;?>"><?php echo $text;?></a>
									</dd>
								</dl>
							</fieldset>
							
							<div id="<?php echo $listType;?>-box">
								<ol class="data-inbox">
<?php
list($currentTextcubeVersion) = explode(' ', TEXTCUBE_VERSION, 2);
// Textcube-NG: 무결성 검사 면제 목록(r3-14, 서비스 전체). 목록 보기의 '무결성 검사 없이 항상 허용' 칸에 쓴다. 바꾸기는 서비스 관리자만
$pluginGuardExemptList = pluginGuardExemptList();
$pluginGuardExemptEditable = Acl::check('group.creators');

for ($i=0; $i<count($pluginKeys); $i++) {
	$pluginDir = $pluginKeys[$i];
	
	$link = $pluginAttrs[$pluginDir]['link'];
	$title = $pluginAttrs[$pluginDir]['title'];
	$version = $pluginAttrs[$pluginDir]['version'];
	$requirements = version_compare($currentTextcubeVersion,$pluginAttrs[$pluginDir]['requirements']) >=0 ? true : false;
	$description = $pluginAttrs[$pluginDir]['description'];
	$authorLink = $pluginAttrs[$pluginDir]['authorLink'];
	$author = $pluginAttrs[$pluginDir]['author'];
	$config = $pluginAttrs[$pluginDir]['config'] ? 'Y':'N';
	$width = $pluginAttrs[$pluginDir]['width'] ? $pluginAttrs[$pluginDir]['width'] : 500;
	$height = $pluginAttrs[$pluginDir]['height'] ? $pluginAttrs[$pluginDir]['height'] : 525;
	$active = in_array($pluginDir, $activePlugins);
	
	if ($active == true && !in_array('activated', $selectedStatus))
		continue;
	else if ($active == false && !in_array('deactivated', $selectedStatus))
		continue;
	
	$className = $active ? 'active-class' : 'inactive-class';
	$className .= $requirements ? NULL : ' disabled-class';
	$className .= $i == (count($pluginKeys) - 1) ? ' last-item' : NULL;
	// Textcube-NG: 차단 화면 개선 — 차단 사유·풀기 방법(아이콘 보기 배지·흐림, 목록 보기 경고 상자)(r3-13)
	$pluginGuardInfo = pluginGuardBlockInfo($pluginDir, $active);
	$className .= $pluginGuardInfo !== null ? ' guard-blocked-class' : NULL;
?>
									<li class="<?php echo $className;?>">
<?php
	if ($requirements == false) {
?>
										<div class="plugin-box">
											<img class="plugin-icon plugin-disabled-icon" src="<?php 
		echo $service['path'] . 
			(file_exists(ROOT . "/plugins/{$pluginDir}/images/icon_plugin_off.png") ?
				"/plugins/{$pluginDir}/images/icon_plugin_off.png" :
				$adminSkinSetting['skin'] . "/image/icon_plugin_off.png");?>" alt="<?php echo _t('꺼짐');?>" title="<?php echo _t('이 플러그인은 현재 텍스트큐브와 호환되지 않습니다. 플러그인의 업데이트가 필요합니다.');?>" />
<?php
	} else if ($active) {
?>
										<div class="plugin-box">
											<img id="pluginIcon<?php echo $i;?>" class="plugin-icon" src="<?php 
		echo $service['path'] . 
			(file_exists(ROOT . "/plugins/{$pluginDir}/images/icon_plugin_on.png") ? 
				"/plugins/{$pluginDir}/images/icon_plugin_on.png" :
				$adminSkinSetting['skin'] . "/image/icon_plugin_on.png");?>" onclick="togglePlugin('<?php echo $pluginDir;?>',<?php echo $i;?>,'<?php echo $width;?>','<?php echo $height;?>', this, null); return false;" alt="<?php echo _t('켜짐');?>" title="<?php echo _t('이 플러그인은 사용중입니다. 클릭하시면 사용을 중지합니다.');?>" />
											<input type="hidden" id="pluginStatus<?php echo $i;?>" value="1" />
<?php
	} else {
?>
										<div class="plugin-box">
											<img id="pluginIcon<?php echo $i;?>" class="plugin-icon" src="<?php 
		echo $service['path'] . 
			(file_exists(ROOT . "/plugins/{$pluginDir}/images/icon_plugin_off.png") ? 
				"/plugins/{$pluginDir}/images/icon_plugin_off.png" : 
				$adminSkinSetting['skin'] . "/image/icon_plugin_off.png");?>" onclick="togglePlugin('<?php echo $pluginDir;?>',<?php echo $i;?>,'<?php echo $width;?>','<?php echo $height;?>', this, null); return false;" alt="<?php echo _t('꺼짐');?>" title="<?php echo _t('이 플러그인은 사용 중지 상태입니다. 클릭하시면 사용을 시작합니다.');?>" />
											<input type="hidden" id="pluginStatus<?php echo $i;?>" value="0" />
<?php
	}
?>
<?php
	if ($pluginGuardInfo !== null) {
		// Textcube-NG: r3-42 아이콘 보기 배지를 누르면(Enter·Space 포함) 다시 검사한다(목록 보기의 [다시 검사하고 풀기]와 같은 recheckPlugin)
		$pluginGuardBadgeAttr = '';
		if ($pluginGuardInfo['action'] === 'recheck') {
			$pluginGuardBadgeCall = 'recheckPlugin(\'' . escapeJSInAttribute($pluginDir) . '\'); return false;';
			$pluginGuardBadgeAttr = ' role="button" tabindex="0" onclick="' . $pluginGuardBadgeCall . '" onkeydown="if (event.keyCode == 13 || event.keyCode == 32) { ' . $pluginGuardBadgeCall . ' }"';
		}
?>
										<span class="plugin-guard-badge" title="<?php echo htmlspecialchars($pluginGuardInfo['title'], ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');?>"<?php echo $pluginGuardBadgeAttr;?>><?php echo htmlspecialchars($pluginGuardInfo['badge'], ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');?></span>
<?php
	}
?>
										</div>
										<div class="summary">
											<div class="plugin-title">
<?php
	if ($requirements == false) {
?>
												<input type="checkbox" class="input-checkbox" name="plugin" title="<?php echo _t('이 플러그인은 현재 텍스트큐브와 호환되지 않습니다. 플러그인의 업데이트가 필요합니다.');?>" disabled="disabled" />
<?php
	} else if ($active) {
?>
												<input type="checkbox" class="input-checkbox" name="plugin" value="<?php echo $pluginDir;?>" title="<?php echo _t('이 플러그인은 사용중입니다. 클릭하시면 사용을 중지합니다.');?>" checked="checked" />
<?php
	} else {
?>
												<input type="checkbox" class="input-checkbox" name="plugin" value="<?php echo $pluginDir;?>" title="<?php echo _t('이 플러그인은 사용 중지 상태입니다. 클릭하시면 사용을 시작합니다.');?>" />
<?php
	}
	if ($link) {
		echo sprintf('<a href="%s" title="%s - %s">%s</a>',
						htmlspecialchars($link, ENT_QUOTES | ENT_SUBSTITUTE),
						htmlspecialchars($title, ENT_QUOTES | ENT_SUBSTITUTE),
						_t('판번호') . ' ' . $version,
						$listType == 'listview' ? htmlspecialchars($title, ENT_QUOTES | ENT_SUBSTITUTE) : htmlspecialchars(UTF8::lessenAsEm($title, 20), ENT_QUOTES | ENT_SUBSTITUTE)
					);
	} else {
		echo sprintf('<span title="%s - %s">%s</span>',
						htmlspecialchars($title, ENT_QUOTES | ENT_SUBSTITUTE),
						_t('판번호') . ' ' . $version,
						$listType == 'listview' ? htmlspecialchars($title, ENT_QUOTES | ENT_SUBSTITUTE) : htmlspecialchars(UTF8::lessenAsEm($title, 20), ENT_QUOTES | ENT_SUBSTITUTE)
					);
	}
?>
											</div>
											<ul class="plugin-description">
												<li class="author"><span class="label"><?php echo _t('제작자');?> : </span><a href="<?php echo htmlspecialchars($authorLink, ENT_QUOTES | ENT_SUBSTITUTE);?>" title="<?php echo htmlspecialchars($author, ENT_QUOTES | ENT_SUBSTITUTE);?>"><?php echo htmlspecialchars($author, ENT_QUOTES | ENT_SUBSTITUTE);?></a></li>
												<li class="version"><span class="label"><?php echo _t('판번호');?> : </span><?php echo $version;?></li>
												<li class="description"><span class="label"><?php echo _t('설명');?> : </span><?php echo htmlspecialchars($description, ENT_QUOTES | ENT_SUBSTITUTE);?></li>
<?php
	// Textcube-NG: 안전 검사 결과(차단 사유·차단(무결성)·PDO 사용 불가·직접 SQL·판정 불가 호출·검토된 번들 예외) 표시(v3.211)
	$pluginGuardLabel = pluginGuardListLabel($pluginDir, $active);
	if ($pluginGuardLabel !== '') {
?>
												<li class="plugin-guard"><span class="label"><?php echo _t('검사');?> : </span><?php echo htmlspecialchars($pluginGuardLabel, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');?></li>
<?php
	}
?>
<?php
	// Textcube-NG: 무결성 검사 면제(r3-14). 고르면 모든 블로그에서 이 플러그인의 무결성 검사를 건너뛴다(엄격 모드 포함, 허용 목록·서비스 관리자 전용·PDO 검사는 그대로).
	// 서비스 관리자만 바꿀 수 있다. 다른 관리자에게는 지금 값을 보이되 칸을 막고 안내를 단다
	if ($requirements) {
		$pluginGuardExempt = in_array($pluginDir, $pluginGuardExemptList, true);
?>
												<li class="plugin-guard-exempt"><label for="pluginExempt<?php echo $i;?>"<?php echo $pluginGuardExemptEditable ? '' : ' title="' . htmlspecialchars(_t('서비스 관리자만 바꿀 수 있습니다.'), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') . '"';?>><input type="checkbox" class="checkbox" id="pluginExempt<?php echo $i;?>"<?php echo $pluginGuardExemptEditable ? ' onclick="return setPluginExempt(\'' . escapeJSInAttribute($pluginDir) . '\', this);"' : ' disabled="disabled"';?><?php echo $pluginGuardExempt ? ' checked="checked"' : '';?> /> <?php echo htmlspecialchars(_t('무결성 검사 없이 항상 허용'), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');?></label></li>
<?php
	}
?>
											</ul>
<?php
	if ($pluginGuardInfo !== null) {
		// Textcube-NG: 차단 경고 상자(목록 보기). 아이콘 보기에서는 CSS 로 숨긴다(r3-13)
?>
											<div class="plugin-guard-box">
												<p class="plugin-guard-title"><?php echo htmlspecialchars($pluginGuardInfo['title'], ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');?></p>
<?php
		if ($pluginGuardInfo['text'] !== '') {
?>
												<p class="plugin-guard-text"><?php echo htmlspecialchars($pluginGuardInfo['text'], ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');?></p>
<?php
		}
		if ($pluginGuardInfo['action'] === 'recheck') {
?>
												<p class="plugin-guard-action"><input type="button" class="input-button" value="<?php echo htmlspecialchars(_t('다시 검사하고 풀기'), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');?>" onclick="recheckPlugin('<?php echo escapeJSInAttribute($pluginDir);?>'); return false;" /></p>
<?php
		} else if ($pluginGuardInfo['action'] === 'allow' && Acl::check('group.creators')) {
?>
												<p class="plugin-guard-action"><input type="button" class="input-button" value="<?php echo htmlspecialchars(_t('허용 목록에 추가'), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');?>" onclick="allowPlugin('<?php echo escapeJSInAttribute($pluginDir);?>'); return false;" /></p>
<?php
		} else if ($pluginGuardInfo['action'] === 'privilege') {
?>
												<p class="plugin-guard-action"><a href="<?php echo htmlspecialchars($blogURL . '/control/plugin', ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');?>"><?php echo htmlspecialchars(_t('플러그인 보호 설정'), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');?></a></p>
<?php
		}
?>
											</div>
<?php
	}
?>
											<div class="plugin-buttons">
<?php
	if ($requirements == false) {
?>
												<a href="#void" onclick="getCurrentSetting('<?php echo $pluginDir;?>','<?php echo $config;?>','<?php echo $width;?>','<?php echo $height;?>', 'about'); return false;"><?php echo _t('사용 불가능한 판번호');?></a>
<?php
	} else {
?>
												<a href="#void" onclick="getCurrentSetting('<?php echo $pluginDir;?>','<?php echo $config;?>','<?php echo $width;?>','<?php echo $height;?>', 'about'); return false;"><?php echo _t('자세히 보기');?></a> <span class="divider">|</span>
<?php
		if ($config=='Y') {
			if ($active) {
?>
												<span id="pluginSettingButton<?php echo $i;?>" class="enabled"><a href="#void" onclick="getCurrentSetting('<?php echo $pluginDir;?>','<?php echo $config;?>','<?php echo $width;?>','<?php echo $height;?>', 'setting'); return false;"><?php echo _t('환경설정');?></a></span>
<?php
			} else {
?>
												<span id="pluginSettingButton<?php echo $i;?>" class="dimmed"><?php echo _t('환경설정');?></span>
<?php
				// Textcube-NG: r3-42 끌 때 보관해 둔 설정이 있으면 [보관 설정 지우기]를 단다(다시 켜면 돌아가는 설정)
				if (isset($pluginKeptSettings[$pluginDir])) {
?>
												<span id="pluginKeptButton<?php echo $i;?>"><span class="divider">|</span> <a href="#void" title="<?php echo htmlspecialchars(_t('끌 때 보관해 둔 설정이 있습니다. 다시 켜면 이 설정으로 돌아갑니다.'), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');?>" onclick="clearKeptPluginSetting('<?php echo escapeJSInAttribute($pluginDir);?>', <?php echo $i;?>); return false;"><?php echo htmlspecialchars(_t('보관 설정 지우기'), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');?></a></span>
<?php
				}
?>
<?php
			}
		} else {
?>
												<span id="pluginSettingButton<?php echo $i;?>" class="disabled"><?php echo _t('환경설정');?></span>
<?php
		}
	}
?>
											</div>
										</div>
									</li>
<?php
}
?>
								</ul>
								
								<div class="clear"></div>
							</div>
							
							<input type="hidden" id="currentTab" name="currentTab" value="<?php echo $_POST['visibility'];?>" />
						</form>
						
						<hr class="hidden" />
						<form id="search-form" class="data-subbox" method="post" action="<?php echo $blogURL;?>/owner/plugin">
							<h2><?php echo _t('검색');?></h2>
							<div class="section">
								<label for="search"><?php echo _t('제목');?>, <?php echo _t('내용');?></label>
								<input type="text" id="search" class="input-text" name="search" value="<?php echo htmlspecialchars($search, ENT_QUOTES | ENT_SUBSTITUTE);?>" onkeydown="if (event.keyCode == '13') {  document.getElementById('search-form').submit();return false; }" />
								<input type="submit" class="search-button input-button" value="<?php echo _t('검색');?>" onclick="document.getElementById('search-form').submit();return false;" />
							</div>
						</form>
						<hr class="hidden" />
						<div id="part-plugin-more" class="part">
							<h2 class="caption"><span class="main-text"><?php echo _t('플러그인을 구하려면');?></span></h2>
							
<?php
$linkString = '<a href="http://www.textcube.org/plugin" onclick="window.open(this.href); return false;" title="' . _t('플러그인 업로드 게시판으로 연결합니다.') . '">' . _t('플러그인 업로드 게시판'). '</a>';
$tempString = _f('텍스트큐브 홈페이지의 %1을 방문하시면 다양한 플러그인을 받을 수 있습니다. 받은 플러그인 파일을 텍스트큐브의 plugin 디렉토리(폴더)로 업로드하면 설치가 완료됩니다. 업로드 후, 이 페이지에서 해당 플러그인을 사용중으로 전환하여 사용을 시작할 수 있습니다.',$linkString);
?>
							<div class="main-explain-box">
								<p class="explain"><?php echo $tempString;?></p>
							</div>
						</div>
<?php
require ROOT . '/interface/common/owner/footer.php';
?>
