<?php
/**
 * markdown.php — Backward-compatibility shim for FM_Markdown plugin
 *
 * Delegates to michelf/php-markdown 1.9.1 (PHP Markdown Extra) with 3 upstream fixes backported (fbc3604077, dbb995733d, 98b12b84).
 * Provides the global Markdown() function that index.php relies on.
 *
 * FM_Markdown Plugin for Textcube
 * By Jeongkyu Shin (inureyes@gmail.com)
 * Copyright (c) Needlworks / TNF / Tatter and Company
 *
 * PHP Markdown & Extra (original bundled library, v1.0.1m / v1.2.3)
 * Copyright (c) 2004-2008 Michel Fortin <http://www.michelf.com/>
 * Copyright (c) 2003-2006 John Gruber <http://daringfireball.net/>
 * License: BSD-3-Clause
 *
 * PHP Markdown Extra 1.9.1 (michelf/php-markdown, replaces bundled library above)
 * Copyright (c) 2004-2021 Michel Fortin <https://michelf.ca/projects/php-markdown/>
 * Copyright (c) 2004-2006 John Gruber <https://daringfireball.net/projects/markdown/>
 * License: BSD-3-Clause (GPL-compatible)
 */

require_once __DIR__ . '/michelf/MarkdownInterface.php';
require_once __DIR__ . '/michelf/Markdown.php';
require_once __DIR__ . '/michelf/MarkdownExtra.php';

// Version constants for any code that may reference them.
define('MARKDOWN_VERSION',      \Michelf\Markdown::MARKDOWNLIB_VERSION);
define('MARKDOWNEXTRA_VERSION', \Michelf\Markdown::MARKDOWNLIB_VERSION);

if (!function_exists('Markdown')) {
	/**
	 * Global Markdown() shim — called by FM_Markdown_format() in index.php.
	 * Extra arguments beyond $text are accepted and silently ignored
	 * to maintain backward compatibility.
	 *
	 * @param  string $text Input Markdown text
	 * @return string       HTML output
	 */
	function Markdown($text) {	// Textcube-NG: 스칼라·반환 타입은 PHP 7.0 문법 — 문자열로 바꿔 넘기고 돌려준다(r4-4)
		return (string)\Michelf\MarkdownExtra::defaultTransform((string)$text);
	}
}
