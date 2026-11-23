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
class Attachment {
	function __construct() {
		$this->reset();
	}

	function reset() {
		$this->error =
		$this->name =
		$this->parent =
		$this->label =
		$this->mime =
		$this->size =
		$this->width =
		$this->height =
		$this->downloads =
		$this->enclosure =
		$this->attached =
			null;
	}
	
	function open($filter = '', $fields = '*', $sort = 'attached') {
		global $database;
		$blogid = getBlogId();
		if (!empty($filter))
			$filter = 'AND ' . $filter;
		if (!empty($sort))
			$sort = 'ORDER BY ' . $sort;
		$this->close();
		$this->_result = POD::query("SELECT $fields FROM {$database['prefix']}Attachments WHERE blogid = $blogid $filter $sort");
		if ($this->_result) {
			if ($this->_count = POD::num_rows($this->_result))
				return $this->shift();
			else
				POD::free($this->_result);
		}
		unset($this->_result);
		return false;
	}
	
	function close() {
		if (isset($this->_result)) {
			POD::free($this->_result);
			unset($this->_result);
		}
		$this->_count = 0;
		$this->reset();
	}
	
	function shift() {
		$this->reset();
		if ($this->_result && ($row = POD::fetch($this->_result))) {
			foreach ($row as $name => $value) {
				if ($name == 'blogid')
					continue;
				switch ($name) {
					case 'enclosure':
						$value = $value ? true : false;
						break;
				}
				$this->$name = $value;
			}
			return true;
		}
		return false;
	}
	
	function add() {
		if (!$this->_generateName())
			return false;
	
		if (!$query = $this->_buildQuery())
			return false;
		if (!isset($this->attached))
			$query->setAttribute('attached', 'UNIX_TIMESTAMP()');

		if (!$query->insert())
			return $this->_error('insert');
		return true;
	}
	
	function update() {
		if (!$query = $this->_buildQuery())
			return false;

		if (!$query->update())
			return $this->_error('update');
		return true;
	}
	
	function getCount() {
		return (isset($this->_count) ? $this->_count : 0);
	}
	
	/*@static@*/
	static function doesExist($name) {
		global $database;
		if (!Validator::filename($name))
			return null;
		return POD::queryExistence("SELECT parent FROM {$database['prefix']}Attachments WHERE blogid = ".getBlogId()." AND name = '$name'");
	}
	
	/*@static@*/
	static function getParent($name) {
		global $database;
		if (!Validator::filename($name))
			return null;
		return POD::queryCell("SELECT parent FROM {$database['prefix']}Attachments WHERE blogid = ".getBlogId()." AND name = '$name'");
	}
	
	/*@static@*/
	static function adjustPermission($filename) {
		global $service;
		if (isset($service['umask'])) {
			if (is_dir($filename))
				chmod($filename, 0777);
			else
				chmod($filename, 0666);
		}
	}
	
	/*@static@*/
	static function confirmFolder() {
		global $service;
		$path = __TEXTCUBE_ATTACH_DIR__."/".getBlogId();
		if (!file_exists($path)) {
			mkdir($path);
			if (isset($service['umask']))
				@chmod($path, 0777 - $service['umask']);
		}
		if (!is_dir($path)) {
			//TODO:critical error
			exit;
		}
	}
	
	/*@static@*/
	static function isExecutableName($name) {
		// 서버에서 실행·해석될 수 있는 확장자를 마지막 확장자뿐 아니라 모든 dot-segment 에서 검사 (예: a.php.jpg — AddHandler 다중 확장자 매핑)
		foreach (array_slice(explode('.', strtolower($name)), 1) as $extension) {
			// Textcube-NG: r3-27 R-456 — php[0-9]? 는 php10·php74 같은 두 자리 이상 숫자 확장자를 놓친다 → php[0-9]* (.htaccess·nginx 규칙의 php[0-9]* 와 같음)
			if (preg_match('/^(php[0-9]*|phtml|pht|phar|phps|cgi|pl|py|asp|aspx|jsp|shtml|exe|com|sh|bat|cmd)$/', $extension))
				return true;
		}
		return false;
	}

	/* Textcube-NG: 그림 첨부 검사·SVG 정화·SVG 변환(r3-20, R-417)
	   classifyUpload  : 1단계. 이미지 확장자(jpg·jpeg·png·gif·webp·avif·svg)만 크기·확장자 대 매직 넘버·가로세로를 본다. 백업 가져오기($import)는 거부하지 않는다
	   sanitizeSVG     : 2·3단계. 비 UTF-8·DOCTYPE·ENTITY·파싱 오류는 거부하고, 화이트리스트로 정화한다. 남기는 참조는 같은 문서 #id, <image> 의 data:image, <a> 의 http·https 링크뿐이다
	   sanitizeSVGFile : 파일을 정화본으로 바꿔 쓴다. 실패하면 파일을 그대로 둔다
	   rasterizeSVG    : 4단계 옵트인($service['svgRasterize']). 정화본만 Imagick 에 넘기고 setFormat('svg') 를 강제한다
	   reencodeImage   : 옵트인($service['imageReencode']). 일반 그림을 GD 로 다시 저장한다
	   libxml_disable_entity_loader() 는 PHP 8.0 에서 deprecated 이므로 쓰지 않는다. DOCTYPE 을 거부하고 LIBXML_NONET 만 주며 NOENT·DTDLOAD 를 주지 않아 외부 엔터티를 읽지 않는다 */
	const SVG_NS = 'http://www.w3.org/2000/svg';
	const XLINK_NS = 'http://www.w3.org/1999/xlink';
	const XML_NS = 'http://www.w3.org/XML/1998/namespace';
	const SVG_MAX_BYTES = 1048576;		// svg 크기 상한(고정)
	const IMAGE_MAX_DIMENSION = 4096;	// 가로·세로 상한(고정)
	const SVG_ID_REF = '/^#[A-Za-z_][A-Za-z0-9_.:-]*$/';
	const SVG_LINK_REF = '/^(?:#[A-Za-z_][A-Za-z0-9_.:-]*|(?i:https?):\/\/[^\/?#]+(?:[\/?#].*)?)$/s';	// <a> 만: #id·http·https
	const SVG_FETCH_URL = '/^(?:(?i:https?|ftp|file):|\/\/)/';	// 지우는 animate·set 값 하나가 외부 주소인지
	const SVG_TEXT_SCHEME = '/(?<![A-Za-z0-9+.-])(?:(?i:https?|ftp|file)\s*:\s*\/\/|(?i:javascript|vbscript)\s*:|(?i:data)\s*:\s*[A-Za-z]+\/)/';	// CDATA 텍스트의 외부·스크립트 스킴
	const SVG_DATA_IMAGE = '/^(?i:data:image\/(?:png|jpeg|gif|webp);base64,)[A-Za-z0-9+\/]+={0,2}$/';
	const SVG_CSS_ID_URL = '/url\s*\(\s*([\'"]?)#[A-Za-z_][A-Za-z0-9_.:-]*\1\s*\)/i';
	public static $svgLastError = '';	// 마지막 정화 실패 까닭(영문 코드, api 설명 참조)
	private static $svgElements = array(
		'svg' => 1, 'g' => 1, 'defs' => 1, 'symbol' => 1, 'title' => 1, 'desc' => 1, 'path' => 1, 'rect' => 1, 'circle' => 1,
		'ellipse' => 1, 'line' => 1, 'polyline' => 1, 'polygon' => 1, 'text' => 1, 'tspan' => 1, 'textPath' => 1,
		'linearGradient' => 1, 'radialGradient' => 1, 'stop' => 1, 'pattern' => 1, 'clipPath' => 1, 'mask' => 1, 'marker' => 1,
		'filter' => 1, 'switch' => 1, 'use' => 1, 'style' => 1, 'image' => 1, 'a' => 1,
		'feBlend' => 1, 'feColorMatrix' => 1, 'feComponentTransfer' => 1, 'feComposite' => 1, 'feConvolveMatrix' => 1,
		'feDiffuseLighting' => 1, 'feDisplacementMap' => 1, 'feDistantLight' => 1, 'feDropShadow' => 1, 'feFlood' => 1,
		'feFuncA' => 1, 'feFuncB' => 1, 'feFuncG' => 1, 'feFuncR' => 1, 'feGaussianBlur' => 1, 'feMerge' => 1, 'feMergeNode' => 1,
		'feMorphology' => 1, 'feOffset' => 1, 'fePointLight' => 1, 'feSpecularLighting' => 1, 'feSpotLight' => 1, 'feTile' => 1,
		'feTurbulence' => 1,
	);
	private static $svgTextParents = array('style' => 1, 'text' => 1, 'tspan' => 1, 'title' => 1, 'desc' => 1);
	private static $svgAttributes = array(
		'id' => 1, 'class' => 1, 'style' => 1, 'lang' => 1, 'transform' => 1, 'x' => 1, 'y' => 1, 'x1' => 1, 'y1' => 1, 'x2' => 1, 'y2' => 1,
		'cx' => 1, 'cy' => 1, 'r' => 1, 'rx' => 1, 'ry' => 1, 'fx' => 1, 'fy' => 1, 'fr' => 1, 'width' => 1, 'height' => 1, 'd' => 1,
		'points' => 1, 'pathLength' => 1, 'viewBox' => 1, 'preserveAspectRatio' => 1, 'version' => 1, 'baseProfile' => 1, 'href' => 1,
		'fill' => 1, 'fill-opacity' => 1, 'fill-rule' => 1, 'stroke' => 1, 'stroke-width' => 1, 'stroke-opacity' => 1, 'stroke-linecap' => 1,
		'stroke-linejoin' => 1, 'stroke-miterlimit' => 1, 'stroke-dasharray' => 1, 'stroke-dashoffset' => 1, 'opacity' => 1, 'color' => 1,
		'display' => 1, 'visibility' => 1, 'overflow' => 1, 'clip' => 1, 'clip-path' => 1, 'clip-rule' => 1, 'mask' => 1, 'filter' => 1,
		'cursor' => 1, 'marker' => 1, 'marker-start' => 1, 'marker-mid' => 1, 'marker-end' => 1, 'markerWidth' => 1, 'markerHeight' => 1,
		'markerUnits' => 1, 'refX' => 1, 'refY' => 1, 'orient' => 1, 'gradientUnits' => 1, 'gradientTransform' => 1, 'spreadMethod' => 1,
		'offset' => 1, 'stop-color' => 1, 'stop-opacity' => 1, 'patternUnits' => 1, 'patternContentUnits' => 1, 'patternTransform' => 1,
		'clipPathUnits' => 1, 'maskUnits' => 1, 'maskContentUnits' => 1, 'filterUnits' => 1, 'primitiveUnits' => 1,
		'font-family' => 1, 'font-size' => 1, 'font-weight' => 1, 'font-style' => 1, 'font-variant' => 1, 'font-stretch' => 1,
		'font-size-adjust' => 1, 'letter-spacing' => 1, 'word-spacing' => 1, 'text-anchor' => 1, 'text-decoration' => 1,
		'dominant-baseline' => 1, 'alignment-baseline' => 1, 'baseline-shift' => 1, 'writing-mode' => 1, 'direction' => 1,
		'unicode-bidi' => 1, 'dx' => 1, 'dy' => 1, 'rotate' => 1, 'textLength' => 1, 'lengthAdjust' => 1, 'startOffset' => 1,
		'method' => 1, 'spacing' => 1, 'side' => 1, 'path' => 1, 'color-interpolation' => 1, 'color-interpolation-filters' => 1,
		'color-rendering' => 1, 'shape-rendering' => 1, 'text-rendering' => 1, 'image-rendering' => 1, 'vector-effect' => 1,
		'paint-order' => 1, 'mix-blend-mode' => 1, 'isolation' => 1, 'lighting-color' => 1, 'flood-color' => 1, 'flood-opacity' => 1,
		'in' => 1, 'in2' => 1, 'result' => 1, 'stdDeviation' => 1, 'edgeMode' => 1, 'operator' => 1, 'k1' => 1, 'k2' => 1, 'k3' => 1,
		'k4' => 1, 'mode' => 1, 'values' => 1, 'type' => 1, 'tableValues' => 1, 'slope' => 1, 'intercept' => 1, 'amplitude' => 1,
		'exponent' => 1, 'order' => 1, 'kernelMatrix' => 1, 'divisor' => 1, 'bias' => 1, 'targetX' => 1, 'targetY' => 1,
		'kernelUnitLength' => 1, 'preserveAlpha' => 1, 'surfaceScale' => 1, 'specularConstant' => 1, 'specularExponent' => 1,
		'diffuseConstant' => 1, 'azimuth' => 1, 'elevation' => 1, 'pointsAtX' => 1, 'pointsAtY' => 1, 'pointsAtZ' => 1,
		'limitingConeAngle' => 1, 'radius' => 1, 'baseFrequency' => 1, 'numOctaves' => 1, 'seed' => 1, 'stitchTiles' => 1, 'scale' => 1,
		'xChannelSelector' => 1, 'yChannelSelector' => 1, 'z' => 1, 'systemLanguage' => 1, 'requiredFeatures' => 1, 'media' => 1,
		'role' => 1, 'aria-label' => 1, 'aria-hidden' => 1, 'aria-labelledby' => 1, 'aria-describedby' => 1, 'focusable' => 1,
	);
	private static $svgRefAttributes = array('href' => 1, 'src' => 1, 'srcset' => 1, 'data' => 1, 'action' => 1, 'formaction' => 1,
		'codebase' => 1, 'base' => 1, 'poster' => 1, 'background' => 1);
	private static $svgAnimationValues = array('values' => 1, 'to' => 1, 'from' => 1, 'by' => 1);

	private static function _imageConfig($key, $default) {
		if (!class_exists('Model_Context')) return $default;
		$value = Model_Context::getInstance()->getProperty('service.' . $key, $default);
		return is_null($value) ? $default : $value;
	}

	private static function _svgFail($reason) {
		self::$svgLastError = $reason;
		return false;
	}

	/*@static@*/
	static function classifyUpload($path, $name, $size, $import = false) {
		$dot = strrpos((string) $name, '.');
		$type = ($dot === false) ? '' : strtolower(substr((string) $name, $dot + 1));
		$result = array('ok' => true, 'error' => '', 'kind' => 'other', 'type' => $type, 'mime' => '', 'width' => 0, 'height' => 0, 'svgExternal' => 0);
		$bytes = (is_string($path) && is_file($path)) ? filesize($path) : false;
		if ($bytes === false) $bytes = (int) $size;
		if ($type === 'svg') {
			$result['kind'] = 'svg';
			if (!$import && $bytes > self::SVG_MAX_BYTES) {
				$result['ok'] = false;
				$result['error'] = 'svgTooLarge';
				return $result;
			}
			$clean = self::sanitizeSVGFile($path);
			if ($clean === false) {	// 정화 실패: 업로드는 호출처가 확장자를 xxx 로, 가져오기는 mime 을 octet-stream 으로 둔다
				$result['error'] = 'svgInvalid';
				$result['mime'] = 'application/octet-stream';
				return $result;
			}
			$result['mime'] = 'image/svg+xml';
			$result['width'] = $clean['width'];
			$result['height'] = $clean['height'];
			$result['svgExternal'] = $clean['external'];
			if (!$import && ($clean['width'] > self::IMAGE_MAX_DIMENSION || $clean['height'] > self::IMAGE_MAX_DIMENSION)) {	// D2: svg 도 가로·세로 4096 (가져오기 예외)
				$result['ok'] = false;
				$result['error'] = 'svgDimension';
			}
			return $result;
		}
		$expect = array('jpg' => IMAGETYPE_JPEG, 'jpeg' => IMAGETYPE_JPEG, 'png' => IMAGETYPE_PNG, 'gif' => IMAGETYPE_GIF, 'webp' => IMAGETYPE_WEBP);
		if (defined('IMAGETYPE_AVIF')) $expect['avif'] = constant('IMAGETYPE_AVIF');	// PHP 8.1 부터. php7.4 의 avif 는 이미지로 보지 않는다
		if (!isset($expect[$type])) return $result;
		$result['kind'] = 'raster';
		$maxBytes = (int) self::_imageConfig('imageMaxBytes', 10485760);
		if (!$import && $maxBytes > 0 && $bytes > $maxBytes) {
			$result['ok'] = false;
			$result['error'] = 'imageTooLarge';
			return $result;
		}
		$info = (is_string($path) && is_file($path)) ? @getimagesize($path) : false;
		$match = (is_array($info) && isset($info[2]) && $info[2] === $expect[$type]);
		if ($match && !$import && class_exists('finfo')) {	// finfo 는 보조: 이미지가 아닌 형식으로 분명히 나올 때만 불일치로 본다
			try {
				$finfo = new finfo(FILEINFO_MIME_TYPE);
				$fileMime = @$finfo->file($path);
				if (is_string($fileMime) && $fileMime !== '' && $fileMime !== 'application/octet-stream' && strncmp($fileMime, 'image/', 6) !== 0) $match = false;
			} catch (Exception $e) {	// Textcube-NG: PHP 5.x 에는 Throwable 이 없어 Exception 을 먼저 잡는다(r4-4)
			} catch (Throwable $e) {
			}
		}
		if (!$match) {
			if ($import) {	// 가져오기는 불일치를 거부하지 않는다(mime 은 호출처의 기존 판정을 쓴다)
				if (is_array($info)) {
					$result['width'] = (int) $info[0];
					$result['height'] = (int) $info[1];
				}
				return $result;
			}
			$result['ok'] = false;
			$result['error'] = 'imageMismatch';
			return $result;
		}
		$result['width'] = (int) $info[0];
		$result['height'] = (int) $info[1];
		$result['mime'] = isset($info['mime']) ? (string) $info['mime'] : '';
		if (!$import && ($result['width'] > self::IMAGE_MAX_DIMENSION || $result['height'] > self::IMAGE_MAX_DIMENSION)) {
			$result['ok'] = false;
			$result['error'] = 'imageDimension';
		}
		return $result;
	}

	/*@static@*/
	static function sanitizeSVGFile($path) {
		self::$svgLastError = '';
		if (!is_string($path) || !is_file($path)) return self::_svgFail('noFile');
		$bytes = filesize($path);
		if ($bytes === false) return self::_svgFail('noFile');
		if ($bytes === 0) return self::_svgFail('empty');
		if ($bytes > self::SVG_MAX_BYTES) return self::_svgFail('tooLarge');
		$src = file_get_contents($path, false, null, 0, self::SVG_MAX_BYTES + 1);
		if (!is_string($src)) return self::_svgFail('read');
		$clean = self::sanitizeSVG($src);
		if ($clean === false) return false;
		if ($clean['content'] !== $src) {
			if (file_put_contents($path, $clean['content'], LOCK_EX) !== strlen($clean['content'])) {
				file_put_contents($path, $src, LOCK_EX);
				clearstatcache(true, $path);
				return self::_svgFail('write');
			}
			clearstatcache(true, $path);
		}
		return $clean;
	}

	/*@static@*/
	static function sanitizeSVG($src) {
		self::$svgLastError = '';
		if (!is_string($src) || $src === '') return self::_svgFail('empty');	// PHP 8 의 loadXML('') 은 ValueError 이므로 먼저 거른다
		if (strlen($src) > self::SVG_MAX_BYTES) return self::_svgFail('tooLarge');
		if (!class_exists('DOMDocument')) return self::_svgFail('noDOM');
		$head = substr($src, 0, 4);
		if ($head === "\x00\x00\xFE\xFF" || $head === "\xFF\xFE\x00\x00" || strncmp($src, "\xFE\xFF", 2) === 0 || strncmp($src, "\xFF\xFE", 2) === 0) return self::_svgFail('encoding');
		if (strncmp($src, "\xEF\xBB\xBF", 3) === 0) $src = substr($src, 3);
		if ($src === '') return self::_svgFail('empty');
		if (preg_match('//u', $src) !== 1) return self::_svgFail('encoding');
		if (preg_match('/^\s*<\?xml[^>]*?\sencoding\s*=\s*["\']([^"\']*)["\']/i', $src, $match) === 1 && !in_array(strtolower($match[1]), array('utf-8', 'utf8'), true)) return self::_svgFail('encoding');
		if (stripos($src, '<!DOCTYPE') !== false || stripos($src, '<!ENTITY') !== false) return self::_svgFail('doctype');
		$previous = libxml_use_internal_errors(true);
		libxml_clear_errors();
		$dom = new DOMDocument();
		$loaded = $dom->loadXML($src, LIBXML_NONET);
		$errors = libxml_get_errors();
		libxml_clear_errors();
		libxml_use_internal_errors($previous);
		if ($loaded !== true || count($errors) > 0) return self::_svgFail('parse');	// 경고 하나라도 있으면 거부
		if ($dom->doctype !== null) return self::_svgFail('doctype');
		$root = $dom->documentElement;
		if ($root === null || $root->namespaceURI !== self::SVG_NS || $root->localName !== 'svg') return self::_svgFail('root');
		// 깊이·요소 수 상한(재귀 없이 스택으로 잰다). 0 이하로 정하면 기본값을 쓴다
		$maxDepth = (int) self::_imageConfig('svgMaxDepth', 30);
		$maxNodes = (int) self::_imageConfig('svgMaxNodes', 20000);
		if ($maxDepth <= 0) $maxDepth = 30;
		if ($maxNodes <= 0) $maxNodes = 20000;
		$nodes = 0;
		$stack = array(array($root, 1));
		while (!empty($stack)) {
			$item = array_pop($stack);
			$nodes++;
			if ($item[1] > $maxDepth || $nodes > $maxNodes) return self::_svgFail('limits');
			foreach ($item[0]->childNodes as $child) {
				if ($child->nodeType === XML_ELEMENT_NODE) $stack[] = array($child, $item[1] + 1);
			}
		}
		$external = 0;
		foreach ($dom->childNodes as $top) {	// 루트 밖 PI(xml-stylesheet)는 출력에서 빠진다
			if ($top->nodeType === XML_PI_NODE && strtolower($top->target) === 'xml-stylesheet') $external++;
		}
		self::_svgCleanAttributes($root, $external);
		self::_svgCleanChildren($root, $external);
		if (!self::_svgCheckUse($root)) return self::_svgFail('tooManyUse');
		$width = self::_svgLength($root->getAttribute('width'));
		$height = self::_svgLength($root->getAttribute('height'));
		// 가로·세로를 따로 판정한다. 읽히지 않는 축만 viewBox 값으로 채운다(없으면 0)(decisions D2, 사용자 11-12 "가로·세로 따로 판정")
		$boxW = $boxH = 0;
		$box = preg_split('/[\s,]+/', trim($root->getAttribute('viewBox')));
		if (is_array($box) && count($box) === 4) {
			if (is_numeric($box[2]) && (float) $box[2] > 0) $boxW = (int) round((float) $box[2]);
			if (is_numeric($box[3]) && (float) $box[3] > 0) $boxH = (int) round((float) $box[3]);
		}
		if ($width <= 0) $width = $boxW;
		if ($height <= 0) $height = $boxH;
		$xml = $dom->saveXML($root);
		if (!is_string($xml)) return self::_svgFail('output');
		$content = '<?xml version="1.0" encoding="UTF-8"?>' . "\n" . $xml;
		if (strlen($content) > self::SVG_MAX_BYTES) return self::_svgFail('tooLarge');
		return array('content' => $content, 'width' => $width, 'height' => $height, 'size' => strlen($content), 'external' => $external);
	}

	private static function _svgLength($value) {
		if (preg_match('/^\s*([0-9]*\.?[0-9]+(?:[eE][+-]?[0-9]+)?)\s*(?:px)?\s*$/', (string) $value, $match) !== 1) return 0;
		return (int) round((float) $match[1]);
	}

	private static function _svgCleanChildren($element, &$external) {
		$children = array();
		foreach ($element->childNodes as $child) $children[] = $child;
		foreach ($children as $child) {
			if ($child->nodeType === XML_ELEMENT_NODE) {
				self::_svgCleanElement($child, $external);
			} else if ($child->nodeType === XML_TEXT_NODE) {
				continue;
			} else if ($child->nodeType === XML_CDATA_SECTION_NODE && isset(self::$svgTextParents[$element->localName])) {
				// 허용 요소 안의 CDATA 는 텍스트로(D8). style 은 뒤에서 시트 필터를, text·tspan·title·desc 는 여기서 외부 참조 필터를 건다
				if ($element->localName !== 'style' && self::_svgTextUnsafe($child->data)) {
					$external += self::_svgTextExternal($child->data);
					$element->removeChild($child);
				} else {
					$element->replaceChild($element->ownerDocument->createTextNode($child->data), $child);
				}
			} else {	// PI·주석·그 밖의 CDATA·엔터티 참조
				if ($child->nodeType === XML_PI_NODE && strtolower($child->target) === 'xml-stylesheet') $external++;
				$element->removeChild($child);
			}
		}
	}

	private static function _svgCleanElement($element, &$external) {
		$parent = $element->parentNode;
		$name = $element->localName;
		if ($element->namespaceURI !== self::SVG_NS || !isset(self::$svgElements[$name])) {	// 다른 네임스페이스·허용 목록 밖(script·foreignObject·animate*·set·feImage 등)은 하위째 지운다
			$external += self::_svgCountExternal($element);
			$parent->removeChild($element);
			return;
		}
		if ($name === 'image') {	// D7: data:image/(png|jpeg|gif|webp);base64 만 남긴다
			$refs = self::_svgHrefValues($element);
			$valid = !empty($refs);
			foreach ($refs as $ref) {
				if (preg_match(self::SVG_DATA_IMAGE, $ref) !== 1) $valid = false;
			}
			if (!$valid) {
				$external += self::_svgCountExternal($element);
				$parent->removeChild($element);
				return;
			}
		}
		self::_svgCleanAttributes($element, $external);
		self::_svgCleanChildren($element, $external);
		if ($name === 'style') {	// D4: 남은 텍스트를 모아 CSS 규칙을 거른 뒤 텍스트 하나로 다시 넣는다
			$css = '';
			$children = array();
			foreach ($element->childNodes as $child) $children[] = $child;
			foreach ($children as $child) {
				if ($child->nodeType === XML_TEXT_NODE) $css .= $child->data;
				$element->removeChild($child);
			}
			$css = self::_svgCleanStyleSheet($css, $external);
			if (trim($css) !== '') $element->appendChild($element->ownerDocument->createTextNode($css));
		}
	}

	private static function _svgHrefValues($element) {
		$refs = array();
		if ($element->hasAttributeNS(self::XLINK_NS, 'href')) $refs[] = preg_replace('/[\x00-\x20]+/', '', $element->getAttributeNS(self::XLINK_NS, 'href'));
		if ($element->hasAttribute('href')) $refs[] = preg_replace('/[\x00-\x20]+/', '', $element->getAttribute('href'));
		return $refs;
	}

	private static function _svgCleanAttributes($element, &$external) {
		$attributes = array();
		foreach ($element->attributes as $attribute) $attributes[] = $attribute;
		$isImage = ($element->localName === 'image');
		$isLink = ($element->localName === 'a');	// D8 보강(decisions 78행): <a> 는 남기고 href 만 #id·http·https 로 거른다(target 은 허용 목록 밖이라 지운다)
		foreach ($attributes as $attribute) {
			$ns = $attribute->namespaceURI;
			$name = $attribute->localName;
			$value = $attribute->value;
			$plain = ($ns === null || $ns === '');
			$isHref = false;
			if ($plain) {	// 네임스페이스 없는 속성: 허용 목록 + on* 제거
				$keep = (strncasecmp($name, 'on', 2) !== 0 && isset(self::$svgAttributes[$name]));
				$isHref = ($name === 'href');
			} else if ($ns === self::XLINK_NS) {
				$keep = ($name === 'href');
				$isHref = $keep;
			} else {	// xml:space·xml:lang 만 남긴다(xml:base 는 지운다)
				$keep = ($ns === self::XML_NS && ($name === 'space' || $name === 'lang'));
			}
			if ($keep && $isHref) {	// href: 엔티티는 파서가 이미 풀었다. 제어 문자·공백을 떼고 #id(image 는 data:image, a 는 #id·http·https)만
				$ref = preg_replace('/[\x00-\x20]+/', '', $value);
				$keep = (preg_match($isImage ? self::SVG_DATA_IMAGE : ($isLink ? self::SVG_LINK_REF : self::SVG_ID_REF), $ref) === 1);
				if (!$keep && $isLink) {	// a 의 href 는 가져오기(fetch)가 아니라 이동이므로 외부 참조로 세지 않는다
					$element->removeAttributeNode($attribute);
					continue;
				}
				if ($keep && $ref !== $value) {
					if ($plain) $element->setAttribute('href', $ref);
					else $element->setAttributeNS(self::XLINK_NS, $attribute->nodeName, $ref);
				}
			} else if ($keep && $plain && $name === 'style') {	// style 속성: 위험한 선언만 지운다
				$css = self::_svgCleanDeclarations($value, $external);
				if (trim($css) === '') $element->removeAttributeNode($attribute);
				else if ($css !== $value) $element->setAttribute('style', $css);
				continue;
			} else if ($keep && self::_svgCssUnsafe($value)) {	// 표현 속성의 url()·위험 구문
				$keep = false;
			}
			if (!$keep) {
				$external += self::_svgAttributeExternal($name, $value);
				$element->removeAttributeNode($attribute);
			}
		}
	}

	// 지운 속성 하나가 외부 참조였는지(같은 문서 #id·data: 는 외부 참조로 세지 않는다)
	private static function _svgAttributeExternal($name, $value) {
		$name = strtolower((string) $name);
		if (isset(self::$svgRefAttributes[$name])) {
			$ref = preg_replace('/[\x00-\x20]+/', '', (string) $value);
			return ($ref === '' || $ref[0] === '#' || strncasecmp($ref, 'data:', 5) === 0) ? 0 : 1;
		}
		$count = self::_svgCssExternal((string) $value);
		if (isset(self::$svgAnimationValues[$name])) {	// 지우는 animate·set 의 values·to·from·by 안 외부 주소(알림 개수용)
			foreach (explode(';', (string) $value) as $item) {
				if (preg_match(self::SVG_FETCH_URL, preg_replace('/[\x00-\x20]+/', '', $item)) === 1) $count++;
			}
		}
		return $count;
	}

	// CDATA 를 바꾼 텍스트(text·tspan·title·desc)가 외부 참조를 담았는지: CSS 판정 + 외부·스크립트 스킴
	private static function _svgTextUnsafe($text) {
		return (self::_svgCssUnsafe($text) || preg_match(self::SVG_TEXT_SCHEME, (string) $text) === 1);
	}

	// 지운 텍스트 안 외부 참조 개수: CSS 외부 참조와 http·https·ftp·file·// 주소 중 큰 쪽(같은 주소를 두 번 세지 않게)
	private static function _svgTextExternal($text) {
		$css = self::_svgCssExternal($text);
		$urls = (int) preg_match_all('/(?<![A-Za-z0-9+.-])(?:(?i:https?|ftp|file):)?\/\/[^\s\/"\'()<>]+/', (string) $text);
		return max($css, $urls);
	}

	// 지우는 하위 트리 안의 외부 참조 개수(링크 a 의 href 는 세지 않는다)
	private static function _svgCountExternal($element) {
		$count = 0;
		$stack = array($element);
		while (!empty($stack)) {
			$node = array_pop($stack);
			if ($node->nodeType === XML_PI_NODE) {
				if (strtolower($node->target) === 'xml-stylesheet') $count++;
				continue;
			}
			if ($node->nodeType === XML_TEXT_NODE || $node->nodeType === XML_CDATA_SECTION_NODE) {
				if ($node->parentNode !== null && $node->parentNode->localName === 'style') $count += self::_svgCssExternal($node->data);
				continue;
			}
			if ($node->nodeType !== XML_ELEMENT_NODE) continue;
			if ($node->localName !== 'a') {
				foreach ($node->attributes as $attribute) $count += self::_svgAttributeExternal($attribute->localName, $attribute->value);
			}
			foreach ($node->childNodes as $child) $stack[] = $child;
		}
		return $count;
	}

	// CSS 조각이 위험한지: 역슬래시·주석, 가져오기 구문, url(#id) 아닌 url()
	// Textcube-NG: r3-27 R-441 — attr() 도 위험 구문으로 본다(속성값을 CSS 값으로 끌어와 url() 검사를 피하는 경로)
	private static function _svgCssUnsafe($css) {
		$css = (string) $css;
		if (strpos($css, '\\') !== false || strpos($css, '/*') !== false) return true;
		if (preg_match('/@import|@font-face|src\s*\(|image\s*\(|image-set\s*\(|element\s*\(|attr\s*\(|cross-fade\s*\(|expression\s*\(|behavior|-moz-binding|javascript\s*:|vbscript\s*:/i', $css) === 1) return true;
		$urls = preg_match_all('/url\s*\(/i', $css);
		return ($urls > 0 && preg_match_all(self::SVG_CSS_ID_URL, $css) !== $urls);
	}

	// CSS 조각 안의 외부 참조 개수(url(#id) 아닌 url(), 따옴표 @import, 따옴표 image()·image-set()·src())
	private static function _svgCssExternal($css) {
		$css = (string) $css;
		$count = (int) preg_match_all('/url\s*\(/i', $css) - (int) preg_match_all(self::SVG_CSS_ID_URL, $css);
		$count += (int) preg_match_all('/@import\s*[\'"]|(?:image-set|image|src)\s*\(\s*[\'"]/i', $css);
		return ($count > 0) ? $count : 0;
	}

	// 선언 목록(style 속성·규칙 본문): ';' 로 나눠 위험한 선언만 지운다
	private static function _svgCleanDeclarations($css, &$external) {
		$kept = array();
		foreach (explode(';', (string) $css) as $declaration) {
			if (self::_svgCssUnsafe($declaration)) $external += self::_svgCssExternal($declaration);
			else $kept[] = $declaration;
		}
		$result = implode(';', $kept);
		if (self::_svgCssUnsafe($result)) {
			$external += self::_svgCssExternal($result);
			return '';
		}
		return $result;
	}

	// <style> 본문: '}' 로 규칙을 나눠 머리(선택자·@규칙)가 위험하면 규칙째, 아니면 선언만 지운다
	private static function _svgCleanStyleSheet($css, &$external) {
		$chunks = explode('}', (string) $css);
		$last = count($chunks) - 1;
		$out = '';
		foreach ($chunks as $i => $chunk) {
			$close = ($i < $last) ? '}' : '';
			$open = strrpos($chunk, '{');
			if ($open === false) {
				if (self::_svgCssUnsafe($chunk)) $external += self::_svgCssExternal($chunk);
				else $out .= $chunk . $close;
				continue;
			}
			$head = substr($chunk, 0, $open);
			if (self::_svgCssUnsafe($head)) {
				$external += self::_svgCssExternal($chunk);
				continue;
			}
			$out .= $head . '{' . self::_svgCleanDeclarations(substr($chunk, $open + 1), $external) . $close;
		}
		if (self::_svgCssUnsafe($out)) {
			$external += self::_svgCssExternal($out);
			return '';
		}
		return $out;
	}

	// D5: <use> 는 문서 안에 있는 #id 만, 참조 대상 하위에 use 가 없어야 남긴다. 남은 use 가 상한을 넘으면 false
	private static function _svgCheckUse($root) {
		$elements = array($root);
		foreach ($root->getElementsByTagNameNS(self::SVG_NS, '*') as $element) $elements[] = $element;
		$ids = array();
		$uses = array();
		foreach ($elements as $element) {
			$id = $element->getAttribute('id');
			if ($id !== '' && !isset($ids[$id])) $ids[$id] = $element;
			if ($element->localName === 'use') $uses[] = $element;
		}
		$remaining = 0;
		foreach ($uses as $use) {
			$refs = self::_svgHrefValues($use);
			$valid = !empty($refs);
			foreach ($refs as $ref) {
				$target = (strlen($ref) > 1 && $ref[0] === '#' && isset($ids[substr($ref, 1)])) ? $ids[substr($ref, 1)] : null;
				if ($target === null || $target->localName === 'use' || $target->getElementsByTagNameNS(self::SVG_NS, 'use')->length > 0) $valid = false;
			}
			if ($valid) $remaining++;
			else if ($use->parentNode !== null) $use->parentNode->removeChild($use);
		}
		$maxUse = (int) self::_imageConfig('svgMaxUse', 256);
		if ($maxUse <= 0) $maxUse = 256;
		return ($remaining <= $maxUse);
	}

	/*@static@*/
	static function rasterizeSVG($svgPath, $dstPath) {
		if (self::_imageConfig('svgRasterize', false) !== true) return false;
		if (!extension_loaded('imagick') || !class_exists('Imagick')) return false;
		if (!is_string($svgPath) || !is_file($svgPath)) return false;
		$bytes = filesize($svgPath);
		if ($bytes === false || $bytes <= 0 || $bytes > self::SVG_MAX_BYTES) return false;
		$src = file_get_contents($svgPath, false, null, 0, self::SVG_MAX_BYTES + 1);
		$clean = is_string($src) ? self::sanitizeSVG($src) : false;	// 저장본도 다시 정화해 정화본만 넘긴다
		if ($clean === false) return false;
		if ($clean['width'] <= 0 || $clean['height'] <= 0 || $clean['width'] > self::IMAGE_MAX_DIMENSION || $clean['height'] > self::IMAGE_MAX_DIMENSION) return false;
		$dir = dirname((string) $dstPath);
		if (!is_dir($dir) || !is_writable($dir)) return false;
		$image = null;
		$temp = null;
		$ok = false;
		try {
			self::_imagickLimits();
			$image = new Imagick();
			$image->setBackgroundColor('transparent');
			$image->setFormat('svg');		// 형식 추측을 막는다(D14)
			// Textcube-NG: r3-20 Q35 — ImageMagick 은 TIME 한도에 걸려도 예외 없이 그리다 만 그림을 돌려주므로, 읽기 경과 시간이 한도 이상이면 실패로 본다
			$timeLimit = (int) self::_imageConfig('svgRasterizeTimeLimit', 10);
			$started = microtime(true);
			$image->readImageBlob($clean['content']);
			if ($timeLimit > 0 && (microtime(true) - $started) >= $timeLimit) throw new RuntimeException('svg rasterize time limit');
			$image->setImageFormat('png');
			$image->stripImage();
			$image->setFormat('png');		// getImageBlob 전에 필요(실측 호출 순서)
			$blob = $image->getImageBlob();
			if (is_string($blob) && $blob !== '') {
				$temp = $dstPath . '.' . bin2hex(random_bytes(6)) . '.tmp';
				if (file_put_contents($temp, $blob, LOCK_EX) === strlen($blob) && rename($temp, $dstPath)) {
					$temp = null;
					$ok = true;
				}
			}
		} catch (Exception $e) {	// Textcube-NG: PHP 5.x 에는 Throwable 이 없어 Exception 을 먼저 잡는다(r4-4)
			$ok = false;
		} catch (Throwable $e) {
			$ok = false;
		} finally {
			if ($image !== null) $image->clear();
			if ($temp !== null && is_file($temp)) @unlink($temp);
		}
		return $ok;
	}

	// Imagick 자원 한도(정적, 프로세스 전체에 걸린다). 상수는 Imagick·ImageMagick 판에 따라 없을 수 있어 확인한 뒤 건다
	private static function _imagickLimits() {
		$limits = array(
			'RESOURCETYPE_AREA' => self::IMAGE_MAX_DIMENSION * self::IMAGE_MAX_DIMENSION,
			'RESOURCETYPE_MEMORY' => 268435456,
			'RESOURCETYPE_MAP' => 536870912,
		);
		$time = (int) self::_imageConfig('svgRasterizeTimeLimit', 10);
		if ($time > 0) $limits['RESOURCETYPE_TIME'] = $time;
		foreach ($limits as $type => $limit) {
			if (defined('Imagick::' . $type)) Imagick::setResourceLimit(constant('Imagick::' . $type), $limit);
		}
	}

	/*@static@*/
	static function reencodeImage($path, $type) {
		if (self::_imageConfig('imageReencode', false) !== true) return false;
		if (!extension_loaded('gd') || !is_string($path) || !is_file($path)) return false;
		$codecs = array(
			'jpg' => array('imagecreatefromjpeg', 'imagejpeg'),
			'jpeg' => array('imagecreatefromjpeg', 'imagejpeg'),
			'png' => array('imagecreatefrompng', 'imagepng'),
			'gif' => array('imagecreatefromgif', 'imagegif'),
			'webp' => array('imagecreatefromwebp', 'imagewebp'),
			'avif' => array('imagecreatefromavif', 'imageavif'),
		);
		$type = strtolower((string) $type);
		if (!isset($codecs[$type]) || !function_exists($codecs[$type][0]) || !function_exists($codecs[$type][1])) return false;
		$info = @getimagesize($path);
		if (!is_array($info) || $info[0] <= 0 || $info[1] <= 0 || $info[0] > self::IMAGE_MAX_DIMENSION || $info[1] > self::IMAGE_MAX_DIMENSION) return false;
		$load = $codecs[$type][0];
		$save = $codecs[$type][1];
		$temp = null;
		$ok = false;
		try {
			$image = @$load($path);
			if ($image !== false) {
				if ($type === 'png' || $type === 'webp' || $type === 'avif') {
					imagealphablending($image, false);
					imagesavealpha($image, true);
				}
				$temp = $path . '.' . bin2hex(random_bytes(6)) . '.tmp';
				if ($save === 'imagejpeg' || $save === 'imagewebp') $saved = $save($image, $temp, 90);
				else $saved = $save($image, $temp);
				unset($image);	// imagedestroy() 는 PHP 8.5 에서 deprecated 이므로 참조를 놓아 해제한다
				clearstatcache(true, $temp);
				if ($saved === true && is_file($temp) && filesize($temp) > 0 && rename($temp, $path)) {
					$temp = null;
					$ok = true;
				}
			}
		} catch (Exception $e) {	// Textcube-NG: PHP 5.x 에는 Throwable 이 없어 Exception 을 먼저 잡는다(r4-4)
			$ok = false;
		} catch (Throwable $e) {
			$ok = false;
		} finally {
			if ($temp !== null && is_file($temp)) @unlink($temp);
		}
		if ($ok) clearstatcache(true, $path);
		return $ok;
	}

	function _generateName() {
		$blogid = getBlogId();
		if (isset($this->name)) {
			if (!Validator::filename($this->name))
				return $this->_error('name');
			if (Attachment::isExecutableName($this->name)) {
				$ext = '.xxx';
				$this->name = random_int(1000000000, 9999999999) . $ext;
			} else {
				$ext = Path::getExtension2($this->name);
			}
		} else {
			$ext = '';
		}
		$this->confirmFolder();
		while (Attachment::doesExist($this->name))
			$this->name = random_int(1000000000, 9999999999) . $ext;
		return true;
	}
	
	function _buildQuery() {
		if (!Validator::filename($this->name))
			return $this->_error('name');

		$query = DBModel::getInstance();
		$query->reset('Attachments');
		$query->setQualifier('blogid', 'equals', getBlogId());
		$query->setQualifier('name', 'equals', $this->name, true);
		if (isset($this->parent)) {
			if (!Validator::number($this->parent, -1))
				return $this->_error('parent');
			$query->setAttribute('parent', $this->parent);
		}
		if (isset($this->label)) {
			$this->label = UTF8::lessenAsEncoding(trim($this->label), 64);
			if (empty($this->label))
				return $this->_error('label');
			$query->setAttribute('label', $this->label, true);
		}
		if (isset($this->mime)) {
			$this->mime = UTF8::lessenAsEncoding(trim($this->mime), 32);
			$query->setAttribute('mime', $this->mime, true);
		}
		if (isset($this->size)) {
			if (!Validator::number($this->size, 0))
				return $this->_error('size');
			$query->setAttribute('size', $this->size);
		}
		if (isset($this->width)) {
			if (!Validator::number($this->width, 0))
				return $this->_error('width');
			$query->setAttribute('width', $this->width);
		}
		if (isset($this->height)) {
			if (!Validator::number($this->height, 0))
				return $this->_error('height');
			$query->setAttribute('height', $this->height);
		}
		if (isset($this->downloads)) {
			if (!Validator::number($this->downloads, 0))
				return $this->_error('downloads');
			$query->setAttribute('downloads', $this->downloads);
		}
		if (isset($this->enclosure))
			$query->setAttribute('enclosure', Validator::getBit($this->enclosure));
		if (isset($this->attached)) {
			if (!Validator::number($this->attached, 1))
				return $this->_error('attached');
			$query->setAttribute('attached', $this->attached);
		}
		return $query;
	}

	function _error($error) {
		$this->error = $error;
		return false;
	}
}
?>
