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
		'Name' => array('string'),
		'Tab' => array('string', 'default' => 'about')
	)
);
require ROOT . '/library/preprocessor.php';

/* 저장 주소는 $blogURL 기준 상대 주소 — $hostURL 은 service.useSSL 로만 스킴을 정해 https 운영 시 http:// 주소가 되어 차단됐다 */
$targetURL = $blogURL . '/owner/plugin/receiveConfig';
$pluginName = $_GET['Name'];
$tabName = $_GET['Tab'];
/* [보안] Tab 은 문자열 검증만 거쳐 인라인 스크립트 문자열(currentTabName)에 그대로 출력됐다 →
   화면에 있는 탭 이름(about/setting)만 허용 */
if (!in_array($tabName, array('about', 'setting'), true))
	$tabName = 'about';
$active = in_array($pluginName, $activePlugins);
$result =  handleConfig($pluginName);
if( is_null($result) )	Respond::NotFoundPage();

$xmls = new XMLStruct();
if (!$xmls->open(file_get_contents(ROOT . "/plugins/{$pluginName}/index.xml"))) {
	Respond::NotFoundPage();
} else {
	$pluginAttrs = array(
						"link" => $xmls->getValue('/plugin/link[lang()]'),
						"title" => htmlspecialchars($xmls->getValue('/plugin/title[lang()]'), ENT_QUOTES | ENT_SUBSTITUTE),
						"version" => htmlspecialchars($xmls->getValue('/plugin/version[lang()]'), ENT_QUOTES | ENT_SUBSTITUTE),
						"requirements" => $xmls->doesExist('/plugin/requirements/tattertools') ? $xmls->getValue('/plugin/requirements/tattertools') : $xmls->getValue('/plugin/requirements/textcube'),
						"description" => htmlspecialchars($xmls->getValue('/plugin/description[lang()]'), ENT_QUOTES | ENT_SUBSTITUTE),
						"authorLink" => $xmls->getAttribute('/plugin/author[lang()]', 'link'),
						"author" => htmlspecialchars($xmls->getValue('/plugin/author[lang()]'), ENT_QUOTES | ENT_SUBSTITUTE),
						"license" => htmlspecialchars($xmls->getValue('/plugin/license[lang()]'), ENT_QUOTES | ENT_SUBSTITUTE),
						"scope" => array(),
						);
	if ($xmls->doesExist('/plugin/binding/adminMenu'))
		array_push($pluginAttrs['scope'], '관리자');
	if ($xmls->doesExist('/plugin/binding/tag'))
		array_push($pluginAttrs['scope'], '블로그');
	if ($xmls->doesExist('/plugin/binding/center'))
		array_push($pluginAttrs['scope'], '대시보드');
	if ($xmls->doesExist('/plugin/binding/listener'))
		array_push($pluginAttrs['scope'], '분류없음');
	if ($xmls->doesExist('/plugin/binding/sidebar'))
		array_push($pluginAttrs['scope'], '사이드바');
	if ($xmls->doesExist('/plugin/binding/editor') || $xmls->doesExist('/plugin/binding/formatter'))
		array_push($pluginAttrs['scope'], '모듈');
	
	list($currentTextcubeVersion) = explode(' ', TEXTCUBE_VERSION, 2);
	$requirements = version_compare($currentTextcubeVersion,$pluginAttrs['requirements']) >= 0 ? true : false;
}

if (!$xmls->doesExist('/plugin/binding/config') || !$active) {
	$tabName = 'about';
}

if (file_exists(ROOT . "/plugins/{$pluginName}/images/icon_plugin.png")) {
	$iconPath = $serviceURL . "/plugins/{$pluginName}/images/icon_plugin.png";
} else {
	$iconPath = $serviceURL . $adminSkinSetting['skin'] . "/image/icon_plugin.png";
}
?>
<!DOCTYPE html PUBLIC "-//W3C//DTD XHTML 1.1//EN" "http://www.w3.org/TR/xhtml11/DTD/xhtml11.dtd">
<html xmlns="http://www.w3.org/1999/xhtml">
<head>
	<title><?php echo _f('%1 설정', $pluginAttrs['title']);?></title>
	<meta http-equiv="Content-Type" content="text/html; charset=utf-8" />
	<link rel="stylesheet" type="text/css" media="screen" href="<?php echo $service['path'] . $adminSkinSetting['skin'];?>/plugin-config.css" />
<?php
if (true === file_exists(ROOT . "/plugins/$pluginName/plugin-config.css")) {
?>
	<link rel="stylesheet" type="text/css" media="screen" href="<?php echo $service['path'];?>/plugins/<?php echo $pluginName;?>/plugin-config.css" />
<?php
}
?>
	<script type="text/javascript" src="<?php echo $service['path'];?>/resources/script/jquery/jquery-<?php echo JQUERY_VERSION;?>.js"></script>
	<script type="text/javascript">jQuery.noConflict();</script>
	<script type="text/javascript" src="<?php echo $service['path'];?>/resources/script/EAF4.js?v=<?php echo filemtime(ROOT . '/resources/script/EAF4.js');?>"></script>
	<script type="text/javascript" src="<?php echo $service['path'];?>/resources/script/common3.js?v=<?php echo filemtime(ROOT . '/resources/script/common3.js');?>"></script>	<!-- Textcube-NG: r3-42 tcConfirm([초기화] 확인) -->
	<script type="text/javascript" src="<?php echo $service['path'];?>/resources/script/pluginconfig.js?v=<?php echo filemtime(ROOT . '/resources/script/pluginconfig.js');?>"></script>
	<script type="text/javascript">
		//<![CDATA[
			var csrfToken = "<?php echo getCSRFToken();?>";	// Textcube-NG: CSRF 토큰 — EAF4.js 가 같은 출처 XHR(GET·HEAD 외)·POST 폼에 붙인다(v3.147)
			var ngForbiddenNotice = "<?php echo escapeJSInCData(_t('서버가 요청을 거부했습니다(HTTP 403). 웹 방화벽(mod_security 등)이 보낸 내용을 공격으로 판단했을 수 있습니다. 서버 관리자에게 알리거나 documents/setup_modsecurity.txt 4.9절을 참고하십시오.'));?>";	// Textcube-NG: EAF4.js 가 관리 요청의 403 응답에 띄우는 안내(r3-14)
			var fieldnamelist = <?php echo $result['script'] ;?>;
			
			var errorMessage ={
				"1": "<?php echo _t('데이터처리 오류 발생.');?>",
				"2": "<?php echo _t('잘못된 입력입니다.');?>"
			};
			
			// Textcube-NG: 설정 XML 을 UTF-8 바이트의 base64 로 바꾼다 — 평문 XML 이 OWASP CRS(941xxx·942xxx)에 걸려 403 으로 저장되지 않았다.
			// encodeURIComponent 의 %XX 를 바이트로 되돌려 btoa 에 넘긴다(unescape 를 쓰지 않음). 끝의 '=' 패딩은 지운다 — CRS 4.x PL2 의 942120 이 '==' 를 SQL 연산자로 탐지한다(v3.216)
			function encodeConfigData(text) {
				return btoa(encodeURIComponent(text).replace(/%([0-9A-F]{2})/g, function (match, hex) {
					return String.fromCharCode(parseInt(hex, 16));
				})).replace(/=+$/, '');
			}
			
			function saveConfig(plugin) {
				var xmlcon= new Converter(document, fieldnamelist) ; 
			   	var xmlData = encodeURIComponent(encodeConfigData(xmlcon.getXMLData()));
				var request = new HTTPRequest("POST", "<?php echo $targetURL;?>");
				PM.addRequest(request, "<?php echo _t('설정을 저장하고 있습니다.');?>");
				request.onSuccess = function () {
					PM.removeRequest(this);
					PM.showMessage("<?php echo _t('저장 완료');?>", "center", "bottom");
					window.close();
				};		
				request.onError = function () {
					PM.removeRequest(this);
					if( this.getText("/response/error") == "9" )
						alert(this.getText("/response/customError"));
					else if( undefined != errorMessage[ this.getText("/response/error") ] )
						alert( errorMessage[ this.getText("/response/error") ] );
					else if( undefined != this.getText("/response/error") )			
						alert("<?php echo _t('알 수 없는 에러입니다.');?>" );
					else 
						alert("<?php echo _t('데이터 처리 페이지를 찾을 수 없습니다.');?>");
				};
				request.onVerify = function() {
					return (this.getText("/response/error") == "0" );
				};			
				request.send("Name=" + encodeURIComponent(plugin) + "&DATA=" + xmlData);
				xmlcon = null;
				request = null;
			}
			
			// Textcube-NG: r3-42 [초기화] — 이 플러그인의 설정을 지우고 기본값으로 되돌린다(owner/plugin/resetSetting, scope=active). 처리 중에는 다시 누르지 못한다
			var resetBusy = false;
			function resetConfig(plugin) {
				if (resetBusy) return false;
				tcConfirm("<?php echo escapeJSInCData(_t('이 플러그인의 설정을 지우고 기본값으로 되돌립니다. 되돌릴 수 없습니다. 계속하시겠습니까?'));?>", function () {
					if (resetBusy) return false;
					resetBusy = true;
					var request = new HTTPRequest("POST", "<?php echo $blogURL;?>/owner/plugin/resetSetting");
					PM.addRequest(request, "<?php echo escapeJSInCData(_t('설정을 초기화하고 있습니다.'));?>");
					request.onSuccess = function () {
						PM.removeRequest(this);
						resetBusy = false;
						PM.showMessage("<?php echo escapeJSInCData(_t('설정을 초기화했습니다.'));?>", "center", "bottom");
						window.location.reload();
					};
					request.onError = function () {
						PM.removeRequest(this);
						resetBusy = false;
						var msg = this.getText("/response/message");
						alert(msg ? msg : "<?php echo escapeJSInCData(_t('설정을 초기화하지 못했습니다.'));?>");
					};
					request.onVerify = function() {
						return (this.getText("/response/error") == "0");
					};
					request.send("name=" + encodeURIComponent(plugin) + "&scope=active");
					return false;
				});
				return false;
			}
			
			var currentTabName = '<?php echo $tabName;?>-tab';
			var objSubmitButton = <?php echo $xmls->doesExist('/plugin/binding/config') && $active && $tabName == 'about' ? 'true' : 'false';?>;
			
			function toggleTab(name) {
				var newTabName = name + '-tab';
				
				// hide current tab.
				document.getElementById(currentTabName).className = '';
				document.getElementById(currentTabName+'-body').style.display = 'none';
				
				document.getElementById(newTabName).className = 'selected';
				document.getElementById(newTabName+'-body').style.display = 'block';
				
				if (getObject('submitButton') != null && name == 'about') {
					document.getElementById('submitButton').parentNode.removeChild(document.getElementById('submitButton'));
					objSubmitButton = true;
				} else if (objSubmitButton == true && name == 'setting') {
					temp = document.getElementById('layout-foot').innerHTML;
					document.getElementById('layout-foot').innerHTML = '';
					
					if (STD.isIE == true) {
						tempInput = document.createElement('<INPUT onclick="saveConfig(\'<?php echo $pluginName;?>\'); return false;" />');
					} else {
						tempInput = document.createElement('INPUT');
						tempInput.setAttribute('onclick', "saveConfig('<?php echo $pluginName;?>'); return false;");
					}
					tempInput.setAttribute('type','submit');
					tempInput.id = 'submitButton';
					tempInput.className = 'input-button';
					tempInput.value = '<?php echo _t('저장');?>';
					
					document.getElementById('layout-foot').appendChild(tempInput);
					document.getElementById('layout-foot').innerHTML += temp;
					objSubmitButton = false;
				}
				
				currentTabName = newTabName;
			}	
		//]]>
	</script>
</head>
<body>
	<form method="post" action="<?php echo $targetURL;?>">
		<input type="hidden" name="csrfToken" value="<?php echo getCSRFToken();?>" />
		<div id="layout-head">
			<h1 class="caption"><?php echo _f('%1 설정', $pluginAttrs['title']);?></h1>
		</div>
		
		<div id="layout-body">
			<ul id="menu">
				<li id="about-tab"<?php echo $tabName == 'about' ? ' class="selected"' : '';?> onclick="toggleTab('about'); return false;"><?php echo _t('이 플러그인에 대하여');?></li>
<?php
if ($xmls->doesExist('/plugin/binding/config')) {
	if ($active) {
?>
				<li id="setting-tab"<?php echo $tabName == 'setting' ? ' class="selected"' : '';?> onclick="toggleTab('setting'); return false;"><?php echo _t('플러그인 설정');?></li>
<?php
	} else {
?>
				<li id="setting-tab" class="dimmed"><?php echo _t('플러그인 설정');?></li>
<?php
	}
}
?>
			</ul>
			
			<div id="about-tab-body" class="tab"<?php echo $tabName != 'about' ? ' style="display: none"' : '';?>>
				<div id="iconBox">
					<img src="<?php echo $iconPath;?>" width="150" alt="<?php echo _t('플러그인 아이콘');?>" />
				</div>
				<div id="information">
					<div class="title">
						<h2><?php echo $pluginAttrs['title'];?></h2>
						
						<div id="version"><?php echo _f('버전 %1', $pluginAttrs['version']);?></div>
						
<?php
if ($pluginAttrs['authorLink']) {
	$authorLink = "<a href=\"{$pluginAttrs['authorLink']}\" target=\"_blank\">{$pluginAttrs['author']}</a>";
} else {
	$authorLink = $pluginAttrs['author'];
}
?>
						<div id="author">
							<?php echo _f('%1 제작', $authorLink);?>
						</div>
					</div>
					
					<div class="description">
<?php
if ($requirements == false) {
?>
  						<p class="requirement"><em><?php echo _f('이 플러그인을 사용하시려면 Textcube %1으로 업그레이드하셔야 합니다.', $pluginAttrs['requirements']);?></em></p>
<?php
}
?>
						<div class="temp-description">
							<?php echo $pluginAttrs['description'];?>
						</div>
					</div>
				
<?php

if ($pluginAttrs['license'] == 'GPL') {
	$licenseText = _f('이 플러그인은 %1 라이센스를 따릅니다.', $pluginAttrs['license']);
} else if (!empty($pluginAttrs['license'])) {
	$licenseText = _t('라이센스') . ' : ' . $pluginAttrs['license'];
}

if (isset($licenseText)) {
?>
					<div class="license">
						<?php echo $licenseText;?>
					</div>
					<div class="pluginDir">
						<?php echo _t('설치된 플러그인 경로').' : '.$pluginName;?>
					</div>
<?php
}
?>
				</div>
				
				<div class="clear"></div>
			</div>
			
<?php
if ($xmls->doesExist('/plugin/binding/config') && $active) {
?>
			<div id="setting-tab-body" class="tab"<?php echo $tabName != 'setting' ? ' style="display: none"' : '';?>>
				<div id="config_data">
					<?php echo $result['code'];?>
				</div>
			</div>
<?php
}
?>
		</div>
		
		<div id="layout-foot" class="button-box">
<?php
if ($xmls->doesExist('/plugin/binding/config') && $active && $tabName == 'setting') {
?>
			<input type="submit" id="submitButton" class="input-button" value="<?php echo _t('저장');?>" onclick="saveConfig('<?php echo $pluginName;?>'); return false;" />
<?php
}
?>
<?php
if ($xmls->doesExist('/plugin/binding/config') && $active) {
	// Textcube-NG: r3-42 [초기화] — 이 플러그인의 설정을 지우고 기본값으로 되돌린다
?>
			<input type="button" id="resetButton" class="input-button" value="<?php echo htmlspecialchars(_t('초기화'), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');?>" onclick="resetConfig('<?php echo escapeJSInAttribute($pluginName);?>'); return false;" />
<?php
}
?>
			<input type="button" class="input-button" value="<?php echo _t('닫기');?>" onclick="self.close();" />
		</div>
	</form>
</body>
</html>
