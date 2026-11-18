<?php
$pluginListForCSS = array();
if (isset($eventMappings['AddPostEditorToolbox'])) {
	foreach ($eventMappings['AddPostEditorToolbox'] as $tempPlugin) {
		array_push($pluginListForCSS, $tempPlugin['plugin']);
	}
}
unset($tempPlugin);
?>
<!DOCTYPE html PUBLIC "-//W3C//DTD XHTML 1.1//EN" "http://www.w3.org/TR/xhtml11/DTD/xhtml11.dtd">
<html xmlns="http://www.w3.org/1999/xhtml" xml:lang="<?php echo (isset($blog['language']) ? $blog['language'] : "ko");?>">
<head>
	<title><?php echo htmlspecialchars($blog['title']);?> &gt; <?php echo _t('글관리');?></title>
	<meta http-equiv="Content-Type" content="text/html; charset=utf-8" />
	<link rel="stylesheet" type="text/css" media="screen" href="<?php echo $service['path'].$adminSkinSetting['skin'];?>/basic.css" />
	<link rel="stylesheet" type="text/css" media="screen" href="<?php echo $service['path'].$adminSkinSetting['skin'];?>/post.css" />
	<link rel="stylesheet" type="text/css" media="screen" href="<?php echo $service['path'].$adminSkinSetting['skin'];?>/popup-editor.css" />
<?php
foreach ($pluginListForCSS as $tempPluginDir) {
	if (isset($tempPluginDir) && file_exists(ROOT . "/plugins/$tempPluginDir/plugin-main.css")) {
?>
	<link rel="stylesheet" type="text/css" href="<?php echo $service['path'];?>/plugins/<?php echo $tempPluginDir;?>/plugin-main.css" />
<?php
	}
}
?>
	<!--[if lte IE 6]>
		<link rel="stylesheet" type="text/css" media="screen" href="<?php echo $service['path'].$adminSkinSetting['skin'];?>/basic.ie.css" />
		<link rel="stylesheet" type="text/css" media="screen" href="<?php echo $service['path'].$adminSkinSetting['skin'];?>/post.ie.css" />
		<link rel="stylesheet" type="text/css" media="screen" href="<?php echo $service['path'].$adminSkinSetting['skin'];?>/popup-editor.ie.css" />
<?php
foreach ($pluginListForCSS as $tempPluginDir) {
	if (isset($tempPluginDir) && file_exists(ROOT . "/plugins/$tempPluginDir/plugin-main.ie.css")) {
?>
	<link rel="stylesheet" type="text/css" href="<?php echo $service['path'];?>/plugins/<?php echo $tempPluginDir;?>/plugin-main.ie.css" />
<?php
	}
}
?>
	<![endif]-->
	<!--[if IE 7]>
		<link rel="stylesheet" type="text/css" media="screen" href="<?php echo $service['path'].$adminSkinSetting['skin'];?>/basic.ie7.css" />
		<link rel="stylesheet" type="text/css" media="screen" href="<?php echo $service['path'].$adminSkinSetting['skin'];?>/post.ie7.css" />
		<link rel="stylesheet" type="text/css" media="screen" href="<?php echo $service['path'].$adminSkinSetting['skin'];?>/popup-editor.ie7.css" />
<?php
foreach ($pluginListForCSS as $tempPluginDir) {
	if (isset($tempPluginDir) && file_exists(ROOT . "/plugins/$tempPluginDir/plugin-main.ie7.css")) {
?>
	<link rel="stylesheet" type="text/css" href="<?php echo $service['path'];?>/plugins/<?php echo $tempPluginDir;?>/plugin-main.ie7.css" />
<?php
	}
}

unset($pluginListForCSS);
unset($tempPluginDir);
?>
	<![endif]-->
	<script type="text/javascript">
		//<![CDATA[
			var servicePath = "<?php echo $service['path'];?>";
			var blogURL = "<?php echo $blogURL;?>";
			var csrfToken = "<?php echo getCSRFToken();?>";	// Textcube-NG: CSRF 토큰 — EAF4.js 가 같은 출처 XHR(GET·HEAD 외)·POST 폼에 붙인다(v3.147)
			var ngForbiddenNotice = "<?php echo escapeJSInCData(_t('서버가 요청을 거부했습니다(HTTP 403). 웹 방화벽(mod_security 등)이 보낸 내용을 공격으로 판단했을 수 있습니다. 서버 관리자에게 알리거나 documents/setup_modsecurity.txt 4.9절을 참고하십시오.'));?>";	// Textcube-NG: EAF4.js 가 관리 요청의 403 응답에 띄우는 안내(r3-14)
			var adminSkin = "<?php echo $adminSkinSetting['skin'];?>";
<?php
if (file_exists(ROOT.$adminSkinSetting['editorTemplate'])) {
?>
			var editorCSS = "<?php echo $adminSkinSetting['editorTemplate'];?>";
<?php
} else {
?>
			var editorCSS = "/resources/style/default-wysiwyg.css";
<?php
}

include ROOT . '/resources/locale/messages.php';
?>
		//]]>
	</script>
<?php // Textcube-NG: messages.php 는 위 include 로 이미 출력된다. 직접 요청은 .htaccess 에서 403 이라 <script src> 를 지움(v3.192) ?>
	<script type="text/javascript" src="<?php echo $service['path'];?>/resources/script/byTextcube.js"></script>
	<script type="text/javascript" src="<?php echo $service['path'];?>/resources/script/jquery/jquery-<?php echo JQUERY_VERSION;?>.js"></script>
	<script type="text/javascript">jQuery.noConflict();</script>
	<script type="text/javascript" src="<?php echo $service['path'];?>/resources/script/EAF4.js?v=<?php echo filemtime(ROOT . '/resources/script/EAF4.js');?>"></script>
	<script type="text/javascript" src="<?php echo $service['path'];?>/resources/script/common3.js?v=<?php echo filemtime(ROOT . '/resources/script/common3.js');?>"></script>
	<script type="text/javascript" src="<?php echo $service['path'];?>/resources/script/gallery.js"></script>
	<script type="text/javascript" src="<?php echo $service['path'];?>/resources/script/owner.js"></script>
	<script type="text/javascript" src="<?php echo $service['path'];?>/resources/script/editor3.js"></script> 
<?php echo fireEvent('ShowAdminHeader', ''); ?>
</head>
<body id="body-entry" class="popup-editor">
	<div id="temp-wrap">
		<div id="all-wrap">
			<div id="layout-header">
				<h1><?php echo _t('텍스트큐브 관리 페이지');?></h1>
			</div>
			
			<hr class="hidden" />

