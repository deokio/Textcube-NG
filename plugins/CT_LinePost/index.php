<?php
/**
 * line Post for Textcube 1.8
 * ----------------------------------
 * Version 1.0
 * By Jeongkyu Shin
 *
 * Created at       : 2009.06.04
 * Last modified at : 2009.07.30
 *  
 * This plugin makes you to post line.
 * For the detail, visit http://forest.nubimaru.com
 * 
 * General Public License
 * http://www.gnu.org/licenses/gpl.html
 * 
 * This program is free software; you can redistribute it and/or modify
 * it under the terms of the GNU General Public License as published by
 * the Free Software Foundation; either version 2 of the License, or
 * (at your option) any later version.
*/

function linePost_generateTranslationJavascript($messages) {
	// The language setting follows in which context this function is called.
	$table = array();
	foreach ($messages as $text) {
		$table[$text] = _t($text);
	}
	$json = jsonEncodeCompat($table, JSON_FORCE_OBJECT | JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_UNESCAPED_UNICODE);	// Textcube-NG: JSON_INVALID_UTF8_SUBSTITUTE(7.2) 대신 jsonEncodeCompat()(00-Compat.php, r4-4)
	if ($json === false) {
		$json = '{}';
	}
	return "<script type=\"text/javascript\">\n(function(t){var own=function(o,k){return Object.prototype.hasOwnProperty.call(o,k);};var d=window.__text||(window.__text={});for(var k in t){if(own(t,k)&&!own(d,k))d[k]=t[k];}if(typeof window._t!=='function'){window._t=function(m){var x=window.__text;return(x&&own(x,m)&&x[m])||m;};}})(" . $json . ");\n</script>\n";
}

/// Posting widget for center.
function linePost_widget($target) {
	global $pluginURL, $blogURL, $configVal;
	// Textcube-NG: 위젯 문구는 HTML escape 해서 넣는다(v3.193)
	$public = htmlspecialchars(_t('공개'), ENT_QUOTES, 'UTF-8');
	$private = htmlspecialchars(_t('비공개'), ENT_QUOTES, 'UTF-8');
	$write = htmlspecialchars(_t('쓰기'), ENT_QUOTES, 'UTF-8');
	$translation = linePost_generateTranslationJavascript(array(
		'저장되었습니다',
		'저장하지 못했습니다',
		'저장하고 있습니다',
	));
	$view  = <<<EOS
	<script type="text/javascript" src="{$blogURL}/plugin/linePostWidget.js"></script> 
	<link rel="stylesheet" type="text/css" media="screen" href="{$pluginURL}/widget.css" />
	{$translation}
	<div id="linePost_widget">
		<input type="radio" id="linePost_public" class="radio" name="category" value="public" checked="checked" />
		<label for="linePost_public">{$public}</label>
		<input type="radio" id="linePost_private" class="radio" name="category" value="private"  />
		<label for="linePost_private">{$private}</label>
		<textarea id="linePost_widget_textarea" maxlength="150" onkeypress="if (event.keyCode == 13) { return false; }"></textarea><br />	
		<input id="linePost_widget_button" type="button" class="input-button" value="{$write}" onclick="linePost_save();return false;"/>
	</div>
EOS;
	
	return $view;
}

/// Dynamic JavaScript for Center Widget.
function linePost_widget_Javascript($target) {
	global $blogURL;
	$view = <<< EOS
/// Copyright (C) Jeongkyu Shin. / Needlworks
/// Line Post Widget for Textcube
//<![CDATA[
function linePost_getData() {
	var content = trim(document.getElementById("linePost_widget_textarea").value);
	if(content == "") return null;
	var radios = document.getElementsByName("category");
	var category = "public";
	for(var i = 0; i < radios.length; i++) {
		if(radios[i].checked) { category = radios[i].value; break; }
	}
	return ("content=" + encodeURIComponent(content) +
			"&category=" + category +
			"&mode=ajax");
}
function linePost_save() {
	/// Set default values.
	var data = linePost_getData();
	if(data == null) return false;
	var request = new HTTPRequest("POST", "{$blogURL}/line/");
	request.onSuccess = function () {
		PM.removeRequest(this);
		PM.showMessage(_t("저장되었습니다"), "center", "bottom");
		document.getElementById("linePost_widget_textarea").value = "";
	}
	request.onError = function() {
		PM.removeRequest(this);
		PM.showErrorMessage(_t("저장하지 못했습니다"), "center", "bottom");
	}
	PM.addRequest(request, _t("저장하고 있습니다"));
	request.send(data);
	return true;
}
//]]>	
EOS;
	header('Content-Type: text/javascript; charset=UTF-8');
	echo $view;
}
?>
