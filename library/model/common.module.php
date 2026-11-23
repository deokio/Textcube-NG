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

/***** Modules *****/

/* Editor */
function getDefaultEditor() {
	global $editorMappings;
	reset($editorMappings);
	return Setting::getBlogSettingGlobal('defaultEditor', key($editorMappings));
}

function& getAllEditors() { global $editorMappings; return $editorMappings; }

function getEditorInfo($editor) {
	global $editorMappings, $configMappings, $pluginURL, $pluginName, $configVal, $pluginPath; 
	$context = Model_Context::getInstance();
	if (!isset($editorMappings[$editor])) {
		reset($editorMappings);
		$editor = key($editorMappings); // gives first declared (thought to be default) editor
	}
	if (isset($editorMappings[$editor]['plugin'])) {
		$pluginURL = $context->getProperty('service.path').'/plugins/'.$editorMappings[$editor]['plugin'];
		$pluginName = $editorMappings[$editor]['plugin'];
		$pluginPath = ROOT . "/plugins/{$pluginName}";
		if( !empty( $configMappings[$pluginName]['config'] ) ) {
			$configVal = getCurrentSetting($pluginName);
			$context->setProperty('plugin.config',Setting::fetchConfigVal($configVal));
		} else {
			$configVal = null;
			$context->setProperty('plugin.config',array());
		}
		include_once ROOT . "/plugins/{$editorMappings[$editor]['plugin']}/index.php";
	}
	return $editorMappings[$editor];
}


/* Formatter */
// default formatter functions.
function getDefaultFormatter() {
	global $formatterMappings;
	reset($formatterMappings);
	return Setting::getBlogSettingGlobal('defaultFormatter', key($formatterMappings));
}

function& getAllFormatters() { global $formatterMappings; return $formatterMappings; }
function getFormatterInfo($formatter) {
	global $formatterMappings;
	if (!isset($formatterMappings[$formatter])) {
		reset($formatterMappings);
		$formatter = key($formatterMappings); // gives first declared (thought to be default) formatter
	}
	if (isset($formatterMappings[$formatter]['plugin'])) {
		include_once ROOT . "/plugins/{$formatterMappings[$formatter]['plugin']}/index.php";
	}
	return $formatterMappings[$formatter];
}

function getEntryFormatterInfo($id) {
	global $database;
	static $info;
	
	if (!Validator::id($id)) {
		return NULL;
	} else if (!isset($info[$id])) {
		$query = sprintf('SELECT contentformatter FROM %sEntries WHERE id = %d',
							$database['prefix'],
							$id
						);
		$info[$id] = POD::queryCell($query);
	}
	
	return $info[$id];
}

function formatContent($blogid, $id, $content, $formatter, $keywords = array(), $useAbsolutePath = false) {
	$info = getFormatterInfo($formatter);
	$func = (isset($info['formatfunc']) ? $info['formatfunc'] : 'FM_default_format');
	$languageDomain = null;
	if (isset($info['plugin'])) {
		$pluginPath = ROOT . '/plugins/' . $info['plugin'];
		if (is_dir($pluginPath . '/locale/')) {
			$locale = Locales::getInstance();
			$languageDomain = $locale->domain;
			// Textcube-NG: setDirectory() 로 플러그인 locale 폴더로 바꾼 directory 가 그대로 남아, 이후 다른 domain 의 set() 이
			// 플러그인 폴더에서 locale 파일을 찾았다. domain 과 함께 directory 도 저장해 finally 에서 되돌린다(v3.188)
			$languageDirectory = $locale->directory;
			if (file_exists($pluginPath . '/locale/' . $locale->defaultLanguage . '.php')) {
				$locale->setDirectory($pluginPath . '/locale');
				$locale->set($locale->defaultLanguage, $info['plugin']);
				$locale->domain = $info['plugin'];
			}
		}
	}
	try {
		return $func($blogid, $id, $content, $keywords, $useAbsolutePath);
	} finally {
		if (!is_null($languageDomain)) {
			$locale->domain = $languageDomain;
			$locale->directory = $languageDirectory;
		}
	}
}

function summarizeContent($blogid, $id, $content, $formatter, $keywords = array(), $useAbsolutePath = false) {
	global $blog;
	$info = getFormatterInfo($formatter);
	$func = (isset($info['summaryfunc']) ? $info['summaryfunc'] : 'FM_default_summary');
	// summary function is responsible for shortening the content if needed
	$languageDomain = null;
	if (isset($info['plugin'])) {
		$pluginPath = ROOT . '/plugins/' . $info['plugin'];
		if (is_dir($pluginPath . '/locale/')) {
			$locale = Locales::getInstance();
			$languageDomain = $locale->domain;
			// Textcube-NG: setDirectory() 로 플러그인 locale 폴더로 바꾼 directory 가 그대로 남아, 이후 다른 domain 의 set() 이
			// 플러그인 폴더에서 locale 파일을 찾았다. domain 과 함께 directory 도 저장해 finally 에서 되돌린다(v3.188)
			$languageDirectory = $locale->directory;
			if (file_exists($pluginPath . '/locale/' . $locale->defaultLanguage . '.php')) {
				$locale->setDirectory($pluginPath . '/locale');
				$locale->set($locale->defaultLanguage, $info['plugin']);
				$locale->domain = $info['plugin'];
			}
		}
	}
	try {
		return $func($blogid, $id, $content, $keywords, $useAbsolutePath);
	} finally {
		if (!is_null($languageDomain)) {
			$locale->domain = $languageDomain;
			$locale->directory = $languageDirectory;
		}
	}
}

function FM_default_format($blogid, $id, $content, $keywords = array(), $useAbsolutePath = false) {
	global $service, $hostURL;
	$basepath = ($useAbsolutePath ? $hostURL : '');
	return str_replace('[##_ATTACH_PATH_##]', "$basepath{$service['path']}/attach/$blogid", $content);
}

function FM_default_summary($blogid, $id, $content, $keywords = array(), $useAbsolutePath = false) {
	global $blog;
	if (!$blog['publishWholeOnRSS']) $content = UTF8::lessen(removeAllTags(stripHTML($content)), 255);
	return $content;
}
?>
