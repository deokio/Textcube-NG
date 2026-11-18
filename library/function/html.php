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

function stripHTML($text, $allowTags = array()) {
	$text = preg_replace('/<(script|style)[^>]*>.*?<\/\1>/si', '', $text);
	if(count($allowTags) == 0)
		$text = preg_replace('/<[\w\/!]+[^>]*>/', '', $text);
	else {
		preg_match_all('/<\/?([\w!]+)[^>]*?>/s', $text, $matches);
		for($i=0; $i<count($matches[0]); $i++) {
			if (!in_array(strtolower($matches[1][$i]), $allowTags))
				$text = str_replace($matches[0][$i], '', $text);
		}
	}
	$text = preg_replace('/&nbsp;?|\xc2\xa0\x20/', ' ', $text);
	//$text = trim(preg_replace('/\s+/', ' ', $text)); // replace white-spaces to one white-space (For narrow screen)
	if(!empty($text))
		$text = str_replace(array('&#39;', '&apos;', '&quot;'), array('\'', '\'', '"'), $text);
	return $text;
}

function str_innerHTML($str) {
	$pattern = array( '/\r\n|\r|\n/' , '@</@' , '@"@');
	$replace = array( ''             , '<\/'  , '\"' );
	return preg_replace($pattern, $replace, $str);
}

function nl2brWithHTML($str) {
	$str = str_replace('[CODE]', '[CODE][HTML]', $str);
	$str = str_replace('[/CODE]', '[/HTML][/CODE]', $str);
	$inHTML = false;
	$out = '';
	while (true) {
		if ($inHTML) {
			$end = strpos($str, '[/HTML]');
			if ($end === false)
				break;
			else {
				$out .= substr($str, 0, $end);
				$str = substr($str, $end + 7);
				$inHTML = false;
			}
		} else {
			$offset = strpos($str, '[HTML]');
			if ($offset === false) {
				$out .= nl2br($str);
				break;
			} else {
				$out .= nl2br(substr($str, 0, $offset));
				$str = substr($str, $offset + 6);
				$inHTML = true;
			}
		}
	}
	return $out;
}

function parseURL($path) {
	// Reserved.
	return $path;
}

function addLinkSense($text, $attributes = '') {
	return preg_replace('@(^|\s|"|\')(https?://[^\s"\']+)@i','$1<a href="$2"' . $attributes . ' rel="external nofollow">$2</a>',$text);
}

function addProtocolSense($url, $protocol = 'http://') {
	return preg_match('/^[a-zA-Z0-9]+:/', $url) ? $url : $protocol . $url;
}

function decorateSrcInObject($html)
{
	$count = preg_match_all('@src="(.+)"@iU', $html, $matches, PREG_PATTERN_ORDER);
	while ($count > 0) {
		$orig = $matches[0][$count - 1];
		$filename = $matches[1][$count - 1];
		if (strncasecmp($filename, 'http://' , 7) != 0) {
			$html = str_replace($orig, substr($orig,0,4) . '"http://' . $_SERVER['HTTP_HOST'] . $filename . '"', $html);
		} else if (strncasecmp($filename, 'https://' , 8) != 0) {
			$html = str_replace($orig, substr($orig,0,4) . '"https://' . $_SERVER['HTTP_HOST'] . $filename . '"', $html);
		} else if (strncasecmp($filename, '//' , 2) != 0) {
			$html = str_replace($orig, substr($orig,0,4) . '"//' . $_SERVER['HTTP_HOST'] . $filename . '"', $html);
		}
		$count--;
	}
	return $html;
}

function avoidFlashBorder($html, $tag='object') {
	$pos1 = $pos2 = 0;

	$str = strtolower($html);

	$result = '';
	while(($pos1 = strpos($str, "<$tag", $pos2)) !== false) {
		$result .= substr($html, $pos2, $pos1 - $pos2);
		$pos2 = $pos1;
		while(true) {
			if(($pos2 = strpos($str, "</$tag>", $pos2)) === false) {
				return $result . '<script type="text/javascript">//<![CDATA[' . CRLF
				    .'writeCode2("' . str_replace(array('"', "\r", "\n"), array('\"', '', "\\\r\n"), decorateSrcInObject(substr($html, $pos1))) . '")'.CRLF
				    .'//]]></script>';
			}
			$pos2 += strlen($tag) + 3;
			$chunk = substr($str, $pos1, $pos2 - $pos1);
			if(substr_count($chunk, "<$tag") == substr_count($chunk, "</$tag>"))
				break;
		}
		$result .= '<script type="text/javascript">//<![CDATA['. CRLF
		    .'writeCode2("' . str_replace(array('"', "\r", "\n"), array('\"', '', "\\\r\n"), decorateSrcInObject(substr($html, $pos1, $pos2 - $pos1))) . '")'.CRLF
		    .'//]]></script>';
	}
	return $result . substr($html, $pos2);
}

function str_tag_on($str) {
	$str = str_replace("&amp;", "&", $str);
	$str = str_replace("&lt;", "<", $str);
	return str_replace("&gt;", ">", $str);
}

function str_tag_off($str) {
	$str = str_replace("<", "&lt;", $str);
	return str_replace(">", "&gt;", $str);
}

function str_tag_truncate($str) {
	return strip_tags(preg_replace('/\[##(.+?)##\]/', '', $str));
}

function str_cut($str, $maxlen, $type) {
	$str = str_trans_rev($str);
	$len = strlen($str);
	if ($len <= $maxlen)
		return str_tag_off(str_trans($str));
	$return_str = "";
	for ($i = 0; $i < ($maxlen - 1); $i++) {
		if (ord(substr($str, $i, 1)) < 128) {
			$return_str .= substr($str, $i, 1);
		} else {
			$return_str .= substr($str, $i, 2);
			$i++;
		}
	}
	$str = str_tag_off(str_trans($str));
	$return_str = str_tag_off(str_trans($return_str));
	if ($type)
		return "<span title=\"$str\">{$return_str}..</span>";
	else
		return $return_str . "..";
}

function link_cut($url, $checkURL = 35)
{
	$leftURL	= $checkURL - 14;
	$rightURL	= -8;
	$link = (strlen($url) > $checkURL) ? substr($url, 0 , $leftURL).' &hellip; '.substr($url, $rightURL) : $url;
	return $link;
}

/// Whitelist HTML sanitizer for untrusted markup (e.g. feed entries in the reader).
/// Parses with DOMDocument and rebuilds the markup from allowed elements, attributes,
/// URL schemes and CSS properties only. Text and attribute values are re-encoded.
function sanitizeHTML($html) {
	if (!is_string($html) || $html === '' || strpos($html, '<') === false)
		return $html;
	if (!class_exists('DOMDocument'))
		return htmlspecialchars(html_entity_decode(strip_tags($html), ENT_QUOTES, 'UTF-8'), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
	// Pass non-ASCII characters as numeric character references so that charset declarations
	// inside the markup cannot change how libxml decodes the input.
	$ascii = preg_replace_callback('/[\x{80}-\x{10FFFF}]/u', function ($m) {
		$c = $m[0];
		switch (strlen($c)) {
			case 2: $code = ((ord($c[0]) & 0x1F) << 6) | (ord($c[1]) & 0x3F); break;
			case 3: $code = ((ord($c[0]) & 0x0F) << 12) | ((ord($c[1]) & 0x3F) << 6) | (ord($c[2]) & 0x3F); break;
			default: $code = ((ord($c[0]) & 0x07) << 18) | ((ord($c[1]) & 0x3F) << 12) | ((ord($c[2]) & 0x3F) << 6) | (ord($c[3]) & 0x3F);
		}
		return '&#' . $code . ';';
	}, $html);
	if ($ascii === null) // invalid UTF-8
		return htmlspecialchars(html_entity_decode(strip_tags($html), ENT_QUOTES, 'UTF-8'), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
	$doc = new DOMDocument();
	$internalErrors = libxml_use_internal_errors(true);
	$loaded = $doc->loadHTML('<html><body>' . $ascii . '</body></html>', LIBXML_NONET);
	libxml_clear_errors();
	libxml_use_internal_errors($internalErrors);
	if (!$loaded)
		return htmlspecialchars(html_entity_decode(strip_tags($html), ENT_QUOTES, 'UTF-8'), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
	$body = $doc->getElementsByTagName('body')->item(0);
	return ($body === null) ? '' : sanitizeHTMLChildren($body);
}

function sanitizeHTMLChildren($parent) {
	static $allowed = array(
		'a' => array('href', 'target'),
		'abbr' => array(), 'acronym' => array(), 'address' => array(), 'b' => array(), 'bdo' => array(),
		'big' => array(), 'br' => array(), 'cite' => array(), 'code' => array(), 'dd' => array(),
		'dfn' => array(), 'dl' => array(), 'dt' => array(), 'em' => array(), 'figcaption' => array(),
		'figure' => array(), 'i' => array(), 'kbd' => array(), 'mark' => array(), 'pre' => array(),
		's' => array(), 'samp' => array(), 'small' => array(), 'span' => array(), 'strike' => array(),
		'strong' => array(), 'sub' => array(), 'sup' => array(), 'tbody' => array(), 'tfoot' => array(),
		'thead' => array(), 'tt' => array(), 'u' => array(), 'var' => array(), 'wbr' => array(),
		'blockquote' => array('cite'), 'q' => array('cite'),
		'del' => array('cite', 'datetime'), 'ins' => array('cite', 'datetime'),
		'p' => array('align'), 'div' => array('align'), 'center' => array(), 'caption' => array('align'),
		'h1' => array('align'), 'h2' => array('align'), 'h3' => array('align'),
		'h4' => array('align'), 'h5' => array('align'), 'h6' => array('align'),
		'hr' => array('align', 'size', 'width', 'noshade'),
		'font' => array('color', 'face', 'size'),
		'img' => array('src', 'alt', 'width', 'height', 'border', 'align', 'hspace', 'vspace'),
		'ul' => array('type'), 'ol' => array('type', 'start'), 'li' => array('type', 'value'),
		'table' => array('border', 'cellpadding', 'cellspacing', 'width', 'align', 'summary', 'bgcolor'),
		'tr' => array('align', 'valign', 'bgcolor'),
		'td' => array('colspan', 'rowspan', 'align', 'valign', 'width', 'height', 'bgcolor', 'nowrap', 'scope', 'abbr'),
		'th' => array('colspan', 'rowspan', 'align', 'valign', 'width', 'height', 'bgcolor', 'nowrap', 'scope', 'abbr'),
		'col' => array('span', 'width', 'align', 'valign'), 'colgroup' => array('span', 'width', 'align', 'valign'),
		'audio' => array('src', 'controls'), 'video' => array('src', 'controls', 'width', 'height', 'poster'),
		'source' => array('src', 'type'),
	);
	// 1: removed together with their content. 2: void elements, removed without their children
	// (libxml's HTML 4 parser may attach the following siblings to them, e.g. an unclosed <embed>).
	// Other unknown elements are unwrapped (content kept).
	static $dropped = array('script' => 1, 'style' => 1, 'iframe' => 1, 'frame' => 2, 'frameset' => 1,
		'object' => 1, 'embed' => 2, 'applet' => 1, 'param' => 2, 'noscript' => 1, 'noembed' => 1,
		'noframes' => 1, 'template' => 1, 'textarea' => 1, 'select' => 1, 'option' => 1, 'optgroup' => 1,
		'input' => 2, 'title' => 1, 'head' => 1, 'meta' => 2, 'link' => 2, 'base' => 2, 'svg' => 1,
		'math' => 1, 'xmp' => 1, 'plaintext' => 1, 'listing' => 1, 'canvas' => 1, 'map' => 1, 'area' => 2);
	static $void = array('br' => 1, 'hr' => 1, 'img' => 1, 'wbr' => 1, 'col' => 1, 'source' => 1);
	$out = '';
	foreach ($parent->childNodes as $node) {
		switch ($node->nodeType) {
			case XML_TEXT_NODE:
			case XML_CDATA_SECTION_NODE:
				$out .= htmlspecialchars($node->nodeValue, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
				break;
			case XML_ELEMENT_NODE:
				$tag = strtolower($node->nodeName);
				if (isset($dropped[$tag])) {
					if ($dropped[$tag] == 2)
						$out .= sanitizeHTMLChildren($node);
					break;
				}
				if (!isset($allowed[$tag])) {
					$out .= sanitizeHTMLChildren($node);
					break;
				}
				$out .= '<' . $tag . sanitizeHTMLAttributes($node, $allowed[$tag]);
				if (isset($void[$tag]))
					$out .= ' />' . sanitizeHTMLChildren($node);
				else
					$out .= '>' . sanitizeHTMLChildren($node) . '</' . $tag . '>';
				break;
			// Comments, processing instructions and other node types are removed.
		}
	}
	return $out;
}

function sanitizeHTMLAttributes($node, $allowedAttributes) {
	static $global = array('title' => 1, 'lang' => 1, 'dir' => 1, 'style' => 1);
	$out = '';
	$newWindow = false;
	foreach ($node->attributes as $attribute) {
		$name = strtolower($attribute->nodeName);
		$value = $attribute->value;
		if (!isset($global[$name]) && !in_array($name, $allowedAttributes, true))
			continue;
		switch ($name) {
			case 'href':
				if (!sanitizeHTMLURL($value, array('http', 'https', 'mailto', 'ftp')))
					continue 2;
				break;
			case 'src':
			case 'cite':
			case 'poster':
				if (!sanitizeHTMLURL($value, array('http', 'https')))
					continue 2;
				break;
			case 'style':
				$value = sanitizeHTMLStyle($value);
				if ($value === '')
					continue 2;
				break;
			case 'target':
				if (strtolower(trim($value)) !== '_blank')
					continue 2;
				$value = '_blank';
				$newWindow = true;
				break;
		}
		$out .= ' ' . $name . '="' . htmlspecialchars($value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') . '"';
	}
	if ($newWindow)
		$out .= ' rel="noopener noreferrer"';
	return $out;
}

function sanitizeHTMLURL($url, $schemes) {
	// Browsers ignore control characters and spaces around/inside the scheme part.
	$url = preg_replace('/[\x00-\x20\x7F]+/', '', $url);
	if (preg_match('/^([^\/?#]*?):/', $url, $match))
		return in_array(strtolower($match[1]), $schemes, true);
	return true; // relative URL
}

function sanitizeHTMLStyle($style) {
	static $properties = array('text-align' => 1, 'color' => 1, 'background-color' => 1, 'font-weight' => 1,
		'font-style' => 1, 'font-size' => 1, 'font-family' => 1, 'text-decoration' => 1, 'float' => 1,
		'clear' => 1, 'width' => 1, 'height' => 1, 'max-width' => 1, 'vertical-align' => 1,
		'line-height' => 1, 'list-style-type' => 1, 'white-space' => 1, 'border' => 1,
		'border-width' => 1, 'border-style' => 1, 'border-color' => 1);
	$out = array();
	foreach (explode(';', $style) as $declaration) {
		$pos = strpos($declaration, ':');
		if ($pos === false)
			continue;
		$property = strtolower(trim(substr($declaration, 0, $pos)));
		$value = trim(substr($declaration, $pos + 1));
		if (!isset($properties[$property]) || $value === '')
			continue;
		// Keywords, numbers, units, colors and rgb()/rgba() only: no url(), quotes, escapes or comments.
		if (!preg_match('/^(?:[a-zA-Z0-9#.,%! \t\-]|rgba?\([0-9.,% \t]*\))+$/', $value))
			continue;
		$out[] = $property . ': ' . $value;
	}
	return implode('; ', $out);
}
?>
