<?php

function GoogleMap_generateTranslationJavascript($messages) {
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

function GoogleMap_Header($target) {
	global $configVal, $pluginURL;
	$config = Setting::fetchConfigVal($configVal);
	if (!is_null($config)) {
		$apiKey    = isset($config['apiKey']) ? trim($config['apiKey']) : '';
		$keyParam  = !empty($apiKey) ? '?key=' . htmlspecialchars($apiKey, ENT_QUOTES, 'UTF-8') : '';
		$mapsJsSrc = 'https://maps.googleapis.com/maps/api/js' . $keyParam;
		$target .= <<<EOS
<link rel="stylesheet" type="text/css" href="$pluginURL/styles/common.css" />
<script type="text/javascript" src="$mapsJsSrc"></script>
<script type="text/javascript" src="$pluginURL/scripts/common.js"></script>
<script type="text/javascript">
//<![CDATA[
	var GMapOnLoadCallbacks = [];
//]]>
</script>
EOS;
	}
	return $target;
}

function GoogleMap_AdminHeader($target) {
	$ctx = Model_Context::getInstance();
	$blogURL = $ctx->getProperty('uri.blog');
	global $pluginURL, $configVal;
	if ($ctx->getProperty('suri.directive') == '/owner/entry/post' || $ctx->getProperty('suri.directive') == '/owner/entry/edit') {
		$pluginURLJs = _GMap_jsString($pluginURL);
		$blogURLJs = _GMap_jsString($blogURL);
		$config    = Setting::fetchConfigVal($configVal);
		$apiKey    = isset($config['apiKey']) ? trim($config['apiKey']) : '';
		$apiKeyJs  = htmlspecialchars($apiKey, ENT_QUOTES, 'UTF-8');
		$keyParam  = !empty($apiKey) ? '?key=' . htmlspecialchars($apiKey, ENT_QUOTES, 'UTF-8') : '';
		$mapsJsSrc = 'https://maps.googleapis.com/maps/api/js' . $keyParam;
		$target .= <<<EOS
<link rel="stylesheet" type="text/css" href="$pluginURL/styles/common.css" />
<script type="text/javascript" src="$mapsJsSrc"></script>
<script type="text/javascript" src="$pluginURL/scripts/common.js"></script>
<script type="text/javascript" src="$pluginURL/scripts/editor.js"></script>
<script type="text/javascript">
//<![CDATA[
	var pluginURL = $pluginURLJs;
	var blogURL = $blogURLJs;
	var gMapApiKey = '$apiKeyJs';
	var GMapOnLoadCallbacks = [];
//]]>
</script>
EOS;
	}
	return $target;
}

function GoogleMap_Footer($target) {
	global $configVal, $pluginURL;
	$ctx = Model_Context::getInstance();
	if ($ctx->getProperty('is_used')) {
		$config = Setting::fetchConfigVal($configVal);
		if (!is_null($config)) {
			$target .= <<<EOS
<script type="text/javascript">
//<![CDATA[
	(function($) {
	$(document).ready(function() {
		var i;
		for (i = 0; i < GMapOnLoadCallbacks.length; i++)
			GMapOnLoadCallbacks[i]();
	});
	})(jQuery);
//]]>
</script>
EOS;
		}
	}
	return $target;
}

function GoogleMap_AdminFooter($target) {
	global $configVal, $pluginURL;
	$ctx = Model_Context::getInstance();
	if ($ctx->getProperty('is_used')) {
		$config = Setting::fetchConfigVal($configVal);
		if (!is_null($config)) {
			$target .= <<<EOS
<script type="text/javascript">
//<![CDATA[
	(function($) {
	$(document).ready(function() {
		var i;
		for (i = 0; i < GMapOnLoadCallbacks.length; i++)
			GMapOnLoadCallbacks[i]();
	});
	})(jQuery);
//]]>
</script>
EOS;
		}
	}
	return $target;
}

function GoogleMap_AddToolbox($target) {
	global $pluginURL;
	// Textcube-NG: 툴박스 문구는 HTML escape 해서 넣는다(v3.191)
	$m_addGoogleMap = _GMap_escapeAttr(_t("지도 삽입하기"));
	$m_attachLocation = _GMap_escapeAttr(_t("현재 위치 첨부하기"));
	$target .= GoogleMap_generateTranslationJavascript(array(
		'지도 삽입하기',
		'현재 위치 첨부하기',
		'첨부된 위치 제거하기',
		'첨부된 위치 정보를 제거하시겠습니까?',
		'위치 정보를 가져오지 못하였습니다.',
		'권한 없음',
		'위치정보 없음',
		'시간 제한 초과',
		'알 수 없는 오류',
		'현재 웹브라우저는 Geolocation 기능을 지원하지 않습니다.'
	));
	$target .= <<<EOS
	<dl id="toolbox-googlemap">
		<dd class="command-box"><a class="button" id="googlemap-insertMap" href="#insertGoogleMap" onclick="GMapTool_insertMap(); return false;">$m_addGoogleMap</a></dd>
		<dd class="command-box"><a class="button" id="googlemap-attachLocation" href="#getLocation" onclick="GMapTool_attachLocation(); return false;">$m_attachLocation</a></dd>
	</dl>
EOS;
	return $target;
}

/* Textcube-NG: 직접 SQL(POD 문자열 SQL)을 준비 문장(POD::prepare·bindAndExecute)으로 바꿨다. 값은 SQL 문장과 따로 보낸다(PDO 모드 호환)(v3.211)
   행은 열 이름 키 배열(fetchAllStmt)이다. 실패하면 원래 POD 함수와 같이 null·false·0을 돌려준다. */
function GoogleMap_preparedRows($query, $types, $params) {
	$stmt = POD::prepare($query);
	if (!$stmt) return null;
	$rows = call_user_func_array(array('POD', 'bindAndExecute'), array_merge(array($stmt, $types), $params)) ? POD::fetchAllStmt($stmt) : null;
	$stmt->close();
	return $rows;
}

function GoogleMap_preparedRow($query, $types, $params) {
	$rows = GoogleMap_preparedRows($query, $types, $params);
	return empty($rows) ? null : $rows[0];
}

function GoogleMap_preparedExecute($query, $types, $params) {
	$stmt = POD::prepare($query);
	if (!$stmt) return false;
	$result = call_user_func_array(array('POD', 'bindAndExecute'), array_merge(array($stmt, $types), $params)) ? true : false;
	$stmt->close();
	return $result;
}

function GoogleMap_View($target, $mother) {
	global $configVal, $pluginURL;
	$ctx = Model_Context::getInstance();
	if ($ctx->getProperty('is_used') === null)
		$ctx->setProperty('is_used', false);
	$dbPrefix = $ctx->getProperty('database.prefix');
	$blogId   = $ctx->getProperty('blog.id');
	$config   = Setting::fetchConfigVal($configVal);
	$apiKey   = isset($config['apiKey']) ? trim($config['apiKey']) : '';
	// API 키 파라미터 — URL 내 & 는 HTML 속성 외부(JS/src)에서 사용되므로 urlencode만 적용
	$apiKeyParam     = !empty($apiKey) ? '&key=' . urlencode($apiKey) : '';
	$apiKeyParamHtml = !empty($apiKey) ? '&amp;key=' . htmlspecialchars($apiKey, ENT_QUOTES, 'UTF-8') : '';
	$matches  = array();
	$offset   = 0;

	while (preg_match('/\[##_GoogleMap\|(([^|]+)\|)?_##\]/', $target, $matches, PREG_OFFSET_CAPTURE, $offset) > 0) {
		$ctx->setProperty('is_used', true);
		// SUGGUEST: [##_GoogleMap|{JSON_REPRESENTATION_OF_PARAMETERS_WITHOUT_NEWLINES}|_##]
		$id = 'GMapContainer'.$mother.rand();
		ob_start();

		// Mobile & iPhone (differences between these will be handled later.)
		if (defined('__TEXTCUBE_MOBILE__') || defined('__TEXTCUBE_IPHONE__')) {
			$json = json_decode($matches[2][0], true);
			switch ($json['type']) {
			case 'G_SATELLITE_MAP':
			case 'SATELLITE':
				$maptype = 'satellite';
				$imgformat = 'jpg';
				break;
			case 'G_HYBRID_MAP':
			case 'HYBRID':
				$maptype = 'hybrid';
				$imgformat = 'jpg';
				break;
			case 'G_PHYSICAL_MAP':
			case 'TERRAIN':
				$maptype = 'terrain';
				$imgformat = 'jpg';
				break;
			default:
				$maptype = 'roadmap';
				$imgformat = 'png';
				break;
			}
			$markers = '';
			for ($i = 0; $i < count($json['user_markers']); $i++) {
				if ($i > 0)
					$markers .= '|';
				$markers .= _GMap_escapeAttr($json['user_markers'][$i]['lat']) . ',' . _GMap_escapeAttr($json['user_markers'][$i]['lng']);
			}
			/* [보안] 원본은 글 본문의 지도 설정(JSON) 값을 escape 없이 img 의 src·title 속성에 넣어, 따옴표가 든 마커 제목·설명이 속성을 끝내고
			   태그를 이어 쓸 수 있었다. 속성 값으로 escape 한다(정상 값의 표시는 같음). */
			$imgSrc = "https://maps.googleapis.com/maps/api/staticmap?center=" . _GMap_escapeAttr($json['center']['latitude']) . "," . _GMap_escapeAttr($json['center']['longitude']) . "&amp;zoom=" . _GMap_escapeAttr($json['zoom']) . "&amp;size=" . _GMap_escapeAttr($json['width']) . "x" . _GMap_escapeAttr($json['height']) . "&amp;maptype={$maptype}&amp;format={$imgformat}&amp;markers={$markers}{$apiKeyParamHtml}";
			echo "<div class=\"googlemap\"><img src=\"{$imgSrc}\" title=\"" . _GMap_escapeAttr($json['user_markers'][0]['title']) . " - " . _GMap_escapeAttr($json['user_markers'][0]['desc']) . "\" alt=\"User-inserted Map\" /></div>";
		}
		// Desktop
		else {
?>
		<div id="<?php echo $id;?>" class="GMapContainer"></div>
		<script type="text/javascript">
		//<![CDATA[
		GMapOnLoadCallbacks.push(function() {
			var c = document.getElementById('<?php echo $id;?>');
			var map = GMap_createMap(c, <?php echo $matches[2][0];?>);
		});
		//]]>
		</script>
<?php
		}
		$output = ob_get_contents();

		ob_end_clean();
		$target = substr_replace($target, $output, $matches[0][1], strlen($matches[0][0]));
	}
	// Check if location is attached to this post.
	$row = GoogleMap_preparedRow("SELECT latitude, longitude FROM {$dbPrefix}Entries WHERE blogid = ? AND id = ?", 'ii', array($blogId, $mother));
	if ($row['latitude'] && $row['longitude']) {
		$staticUrl  = "https://maps.googleapis.com/maps/api/staticmap?center={$row['latitude']},{$row['longitude']}&amp;zoom=12&amp;size=260x120&amp;maptype=roadmap&amp;markers=color:red|size:small|{$row['latitude']},{$row['longitude']}{$apiKeyParamHtml}";
		$mapsLink   = "https://maps.google.com/maps?iwloc=exact&amp;q={$row['latitude']},{$row['longitude']}&amp;z=15";
		$target .= <<<EOS
<div class="googlemap-geolocation-attached">
	<h5>Location</h5>
	<a href="$mapsLink"><img src="$staticUrl" /></a>
</div>
EOS;
	}
	return $target;
}

function GoogleMap_LocationLogView($target) {
	$ctx = Model_Context::getInstance();
	$blogId     = $ctx->getProperty('blog.id');
	$blogURL    = $ctx->getProperty('uri.blog');
	$serviceURL = $ctx->getProperty('uri.service');
	global $pluginURL, $configVal;
	$ctx->setProperty('is_used', true);
	$config       = Setting::fetchConfigVal($configVal);
	$locatives    = getEntries($blogId, 'id, title, slogan, location, longitude, latitude','(length(location)>1 AND category > -1) OR (`longitude` IS NOT NULL AND `latitude` IS NOT NULL)', 'location');
	$width        = Misc::getContentWidth();
	$height       = intval($width * 1.2);
	$default_type = isset($config['locative_maptype']) ? _GMap_convertLegacyMapType($config['locative_maptype']) : 'ROADMAP';
	$id           = 'LocationMap';
	$lat          = $config['latitude'];
	$lng          = $config['longitude'];
	$zoom         = 10;
	$isOwner      = doesHaveOwnership();
	$fallback     = _GMap_fallbackLocation();
	ob_start();
?>
	<div style="text-align:center;">
		<div id="<?php echo $id;?>" style="margin:0 auto;"></div>
	</div>
	<?php echo GoogleMap_generateTranslationJavascript(array('%1에 얽힌 이야기')); ?>
	<script type="text/javascript">
	//<![CDATA[
	var process_count = 0;
	var polling_interval = 100; // ms
	var query_interval = 500; // ms
	var query_interval_handle = null;
	var progress = null;
	var boundary = null;
	var locationMap = null;
	function locationFetch(tofind) {
		if (tofind.length == 0) {
			window.clearInterval(query_interval_handle);
			return;
		}
		GMap_addLocationMark.apply(this, tofind.pop());
	}
	function locationFetchPoller(target_count) {
		if (process_count != target_count) {
			progress.setProgress(process_count / target_count);
			window.setTimeout('locationFetchPoller('+target_count+');', polling_interval);
			return;
		}
		progress.setProgress(1.0);
		window.setTimeout(function() {progress.remove();}, 200); // eyecandy
		locationMap.fitBounds(boundary);
	}
	GMapOnLoadCallbacks.push(function() {
		var c = document.getElementById('<?php echo $id;?>');
		c.style.width = "<?php echo $width;?>px"
		c.style.height = "<?php echo $height;?>px";
		locationMap = new google.maps.Map(c, {
			'center': new google.maps.LatLng(<?php echo $lat;?>, <?php echo $lng;?>),
			'zoom': <?php echo $zoom;?>,
			'mapTypeId': google.maps.MapTypeId.<?php echo $default_type;?>,
			'mapTypeControl': true,
			'navigationControl': true,
			'scaleControl': true
		});
		progress = new GProgressControl(locationMap);
		plugin.gmap.fallbackLocation = new google.maps.LatLng(<?php echo $fallback['latitude'];?>, <?php echo $fallback['longitude'];?>);
		google.maps.event.addListenerOnce(locationMap, 'idle', function() {
			boundary = locationMap.getBounds();
			var locations = new Array();
			var tofind = new Array();
<?php
	$count = 0;
	$countRemoteQuery = 0;
	$dbPrefix = $ctx->getProperty('database.prefix');
	foreach ($locatives as $locative) {
		//if ($count == 10) break; // for testing purpose
		$locative['link'] = "$blogURL/" . ($ctx->getProperty('blog.useSloganOnPost') ? 'entry/' . URL::encode($locative['slogan'],$ctx->getProperty('service.useEncodedURL')) : $locative['id']);
		$found = false;

		if ($locative['longitude'] != NULL && $locative['latitude'] != NULL) {
			$found = true;
			$lat = $locative['latitude'];
			$lng = $locative['longitude'];
			$locative['location'] = _t("위도")." : " . $lat . ", "._t("경도")." : " . $lng;
		} else {
			$row = GoogleMap_preparedRow("SELECT * FROM {$dbPrefix}GMapLocations WHERE blogid = ? AND original_address = ?", 'is', array($blogId, $locative['location']));
			if ($row == null || empty($row)) {
				/* [보안] 원본은 캐시에 없는 위치를 방문자 브라우저가 지오코딩해 /plugin/GMapCache/ 로 기록했다(처음 기록한 방문자가
				   좌표를 정함). 캐시 기록은 관리자만 하고(GoogleMap_Cache), 관리자 외 방문자에게는 기본 좌표(서울시청)에 표시한다.
				   관리자가 지역로그를 열면 이전처럼 브라우저에서 지오코딩해 캐시에 기록한다. */
				if ($isOwner) {
					$found = false;
				} else {
					$lat = $fallback['latitude'];
					$lng = $fallback['longitude'];
					$found = true;
				}
			} else {
				$lat = $row['latitude'];
				$lng = $row['longitude'];
				$found = true;
			}
		}
		/* [보안] 원본은 위치·글 제목·글 주소를 작은따옴표만 바꿔(좌표가 정해진 위치는 그대로) JS 문자열 리터럴에 넣어, 역슬래시·줄바꿈·
		   </script> 가 든 값이 스크립트를 깨거나 코드가 될 수 있었다. JSON 문자열 리터럴(<, >, &, 따옴표는 \uXXXX)로 넣고,
		   좌표는 숫자일 때만 그대로 넣는다(아니면 기본 좌표). 표시용 HTML 의 escape 는 scripts/common.js(GMap_buildLocationInfoHTML). */
		$jsLocation = _GMap_jsString($locative['location']);
		$jsTitle    = _GMap_jsString($locative['title']);
		$jsLink     = _GMap_jsString($locative['link']);
		if ($found) { // found, just output
			if (!is_numeric($lat) || !is_numeric($lng)) {
				$lat = $fallback['latitude'];
				$lng = $fallback['longitude'];
			}
			echo "\t\t\tGMap_addLocationMarkDirect(locationMap, {address:plugin.gmap.normalizeAddress({$jsLocation}), path:{$jsLocation}, original_path:{$jsLocation}}, {$jsTitle}, encodeURI({$jsLink}), new google.maps.LatLng($lat, $lng), boundary, locations, false);\n";
		} else // try to find in the client
			echo "\t\t\ttofind.push([locationMap, {$jsLocation}, {$jsTitle}, encodeURI({$jsLink}), boundary, locations]);\n";
		$count++;
	}
?>
			progress.setLabel('Loading locations...');
			query_interval_handle = window.setInterval(function() {locationFetch(tofind);}, query_interval);
			window.setTimeout(function() {locationFetchPoller(<?php echo $count;?>);}, polling_interval);
		});
	});
	//]]>
	</script>
<?php
	$output = ob_get_contents();
	ob_end_clean();
	return $output;
}

function GoogleMap_ConfigHandler($data) {
	$config = Setting::fetchConfigVal($data);
	if (!is_numeric($config['latitude']) || !is_numeric($config['longitude']) ||
		$config['latitude'] < -90 || $config['latitude'] > 90 || $config['longitude'] < -180 || $config['longitude'] > 180)
		return _t('위도 또는 경도의 값이 올바르지 않습니다.');
	return true;
}

function GoogleMap_Cache() {
	/* [보안] 원본은 비로그인 방문자도 캐시를 기록해, 캐시에 없는 위치의 좌표를 처음 기록한 방문자가 정했다.
	   관리자(지역로그를 연 블로그 관리자·글쓴이) 브라우저의 지오코딩 결과만 기록한다. 관리자 외 방문자에게 캐시에 없는
	   위치는 기본 좌표(서울시청)로 표시한다(GoogleMap_LocationLogView). */
	if (!doesHaveOwnership()) {
		echo 'error: permission denied';
		return;
	}
	/* [보안] 다른 사이트가 관리자 브라우저로 이 주소에 요청을 보내 캐시를 기록하지 못하게(CSRF), 관리 요청들과 같은 Referer 확인
	   (requireStrictRoute: 같은 호스트·같은 블로그에서 보낸 요청만, 아니면 412 로 중단)을 거친다. 지역로그의 기록 요청(scripts/common.js sendCache)은 통과. */
	requireStrictRoute();
	$ctx = Model_Context::getInstance();
	$dbPrefix = $ctx->getProperty('database.prefix');
	$blogId = $ctx->getProperty('blog.id');
	$IV = array(
		'POST' => array(
			'original_path' => array('string', 'default'=>''),
			'path' => array('string', 'default'=>''),
			'lat' => array('number', 'default'=>null),
			'lng' => array('number', 'default'=>null)
		)
	);
	/* [보안] 원본은 Validator::validate() 결과를 무시하고 $_POST['lat'], $_POST['lng'] 를 따옴표 없이 INSERT 에 넣었다
	   (비로그인으로 호출되는 /plugin/GMapCache/ 에서 SQL 주입). 검증 실패 시 중단하고, 좌표는 숫자·범위 확인 후
	   float 로 변환해 로케일과 무관한 숫자 리터럴(%.10F)로만 넣는다. */
	if (!Validator::validate($IV)) {
		echo 'error: invalid request';
		return;
	}
	if (empty($_POST['path']) || empty($_POST['original_path']) || !is_string($_POST['path']) || !is_string($_POST['original_path'])) {
		echo 'error: empty path';
		return;
	}
	if (!is_numeric($_POST['lat']) || !is_numeric($_POST['lng'])) {
		echo 'error: invalid location';
		return;
	}
	$lat = (float)$_POST['lat'];
	$lng = (float)$_POST['lng'];
	if (!($lat >= -90 && $lat <= 90 && $lng >= -180 && $lng <= 180)) {
		echo 'error: invalid location';
		return;
	}
	// Textcube-NG: 값은 준비 문장으로 따로 보내므로 escapeString을 쓰지 않는다(v3.211)
	/* [보안] 원본은 비로그인 요청이 임의 주소 키로 캐시 행을 무제한 추가할 수 있었다. 지역로그
	   (GoogleMap_LocationLogView)에 표시되는 이 블로그 글의 위치에 대해서만 캐시를 기록한다(관리자만 여기까지 오므로 비공개 글 포함). */
	if (empty(GoogleMap_preparedRows("SELECT id FROM {$dbPrefix}Entries WHERE blogid = ? AND draft = 0 AND category > -1 AND length(location) > 1 AND location = ?", 'is', array($blogId, $_POST['original_path'])))) {
		echo 'error: unknown location';
		return;
	}
	$row = GoogleMap_preparedRow("SELECT * FROM {$dbPrefix}GMapLocations WHERE blogid = ? AND original_address = ?", 'is', array($blogId, $_POST['original_path']));
	if ($row == null || empty($row)) {
		if (GoogleMap_preparedExecute("INSERT INTO {$dbPrefix}GMapLocations VALUES (?, ?, ?, ?, ?, ?)", 'issddi', array($blogId, $_POST['original_path'], $_POST['path'], (float)sprintf('%.10F', $lng), (float)sprintf('%.10F', $lat), time())))
			echo 'ok';
		else
			echo 'error: cache failed';
	} else {
		echo 'duplicate';
	}
}

function GoogleMapUI_InsertMap() {
	global $configVal, $pluginURL;
	$config = Setting::fetchConfigVal($configVal);
	$apiKey = isset($config['apiKey']) ? trim($config['apiKey']) : '';
	$lat = $config['latitude'];
	$lng = $config['longitude'];
	$default_type = 'ROADMAP';
	$default_width = min(Misc::getContentWidth(), 500);
	$default_height = 400;
	$zoom = 10;
	_GMap_printHeaderForUI(_t('구글맵 삽입하기'), 'insert', $apiKey);
?>
	<div id="controls">
		<button id="toggleMarkerAddingMode"><?php echo _t("마커 표시 모드");?></button>
		<button id="doInsert"><?php echo _t("본문에 삽입하기");?></button>
	</div>
	<div style="text-align:center;">
		<div class="ui-widget-content" id="GoogleMapPreview" style="width:<?php echo $default_width;?>px; height:<?php echo $default_height;?>px; margin:0 auto;"></div>
	</div>
	<script type="text/javascript">
	//<![CDATA[
	GMapOnLoadCallbacks.push(function() {
		map = new google.maps.Map($('#GoogleMapPreview')[0], {
			'center':new google.maps.LatLng(<?php echo $lat;?>, <?php echo $lng;?>),
			'zoom': <?php echo $zoom;?>,
			'mapTypeId': google.maps.MapTypeId.<?php echo $default_type;?>,
			'mapTypeControl': true,
			'navigationControl': true,
			'scaleControl': true
		});
		google.maps.event.addListenerOnce(map, 'idle', initializeCustomizableMap);
	});
	//]]>
	</script>
	<h2><?php echo _t("지도 검색");?></h2>
	<div class="accordion-elem">
		<p><label><?php echo _t("위치 검색");?> : <input type="text" class="editControl" id="inputQuery" value="" /></label><button id="queryLocation"><?php echo _t("찾기");?></button></p>
	</div>
	<h2><?php echo _t("기본 설정");?></h2>
	<div class="accordion-elem">
		<p><label><?php echo _t("가로");?>(px) : <input type="text" class="editControl" id="inputWidth" value="<?php echo $default_width;?>" /></label></p>
		<p><label><?php echo _t("세로");?>(px) : <input type="text" class="editControl" id="inputHeight" value="<?php echo $default_height;?>" /></label></p>
		<p><button id="applyBasicSettings"><?php echo _t("적용");?></button></p>
	</div>
<?php
	// TODO: 주소 추출 UI
	// - TODO: 포스트 내용 텍스트 얻어오기 및 주소 정보 추출
	_GMap_printFooterForUI('insert');
}

function _GMap_printHeaderForUI($title, $jsName, $apiKey) {
	$ctx = Model_Context::getInstance();
	$blogURL = $ctx->getProperty('uri.blog');
	global $pluginURL;
	$apiKeyJs  = htmlspecialchars($apiKey, ENT_QUOTES, 'UTF-8');
	$keyParam  = !empty($apiKey) ? '?key=' . htmlspecialchars($apiKey, ENT_QUOTES, 'UTF-8') : '';
	$mapsJsSrc = 'https://maps.googleapis.com/maps/api/js' . $keyParam;
	// Textcube-NG: 제목은 HTML escape, pluginURL·blogURL 은 JS 문자열로 escape 한다(v3.191)
	header('Content-Type: text/html; charset=utf-8');
?><!DOCTYPE html>
<html>
<head>
	<meta charset="utf-8">
	<meta http-equiv="Content-Type" content="text/html; charset=utf-8">
	<title>Google Map Plugin: <?php echo _GMap_escapeAttr($title);?></title>
	<link rel="stylesheet" type="text/css" href="<?php echo _GMap_escapeAttr($pluginURL);?>/styles/popup.css">
	<link rel="stylesheet" type="text/css" href="https://code.jquery.com/ui/1.13.3/themes/base/jquery-ui.min.css" integrity="sha384-/sabCyiqgk1iEAFRWfrrAxK3GOpHCGe5cImfsl4N9OuY5ye83XpTAmFnMA27d7xw" crossorigin="anonymous">
	<script type="text/javascript" src="https://code.jquery.com/jquery-3.7.1.min.js" integrity="sha384-1H217gwSVyLSIfaLxHbE7dRb3v4mYCKbpQvzx0cegeju1MVsGrX5xXxAvs/HgeFs" crossorigin="anonymous"></script>
	<script type="text/javascript" src="https://code.jquery.com/ui/1.13.3/jquery-ui.min.js" integrity="sha384-oVpH0DXO9nadZxTmPSQo3YwWqfN/Up9aRDHCxLrw8A2LjkFNcM/XILw4KGMaL95z" crossorigin="anonymous"></script>
	<script type="text/javascript" src="<?php echo $mapsJsSrc;?>"></script>
	<script type="text/javascript" src="<?php echo _GMap_escapeAttr($pluginURL);?>/scripts/common.js"></script>
	<script type="text/javascript" src="<?php echo _GMap_escapeAttr($pluginURL);?>/scripts/<?php echo $jsName;?>.js"></script>
	<script type="text/javascript">
	//<![CDATA[
	var pluginURL = <?php echo _GMap_jsString($pluginURL);?>;
	var blogURL = <?php echo _GMap_jsString($blogURL);?>;
	var gMapApiKey = '<?php echo $apiKeyJs;?>';
	var GMapOnLoadCallbacks = [];
	//]]>
	</script>
	<?php echo GoogleMap_generateTranslationJavascript(array('검색 결과가 없습니다.', '마커 고정시키기', '오류가 발생하였습니다. (%1)', '닫기', '제목 : ', '설명 : ', '삭제하기', '%1에 얽힌 이야기')); ?>
</head>
<body>
<div id="all-wrap">
	<h1><?php echo _GMap_escapeAttr($title);?></h1>
	<div id="layout-body">
<?php
}

function _GMap_printFooterForUI($jsName) {
	global $pluginURL;
?>
	<script type="text/javascript">
	//<![CDATA[
	(function($) {
	$(document).ready(function() {
		var i;
		for (i = 0; i < GMapOnLoadCallbacks.length; i++)
			GMapOnLoadCallbacks[i]();
	});
	})(jQuery);
	//]]>
	</script>
	</div>
</div>
</body>
</html>
<?php
}

/// 지역로그에서 캐시(GMapLocations)에 없는 위치를 표시할 기본 좌표: 서울특별시청(37°34'00.1"N 126°58'42.3"E).
/// 문자열로 반환(PHP 7.4 의 float → 문자열 변환은 LC_NUMERIC 로케일을 따르므로 JS 숫자 리터럴로 그대로 출력).
function _GMap_fallbackLocation() {
	return array('latitude' => '37.5667', 'longitude' => '126.9784');
}

/// JS 문자열 리터럴(따옴표 포함). <, >, &, ', " 는 \uXXXX 로 바꿔 <script> 블록 안에서도 문자열 밖으로 나가지 않는다.
/// UTF-8 이 아닌 바이트는 U+FFFD 로 바꾼다(json_encode 실패로 값이 빠지지 않게).
function _GMap_jsString($value) {
	$s = jsonEncodeCompat((string)$value, JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_UNESCAPED_UNICODE);
	return ($s === false) ? '""' : $s;
}

/// HTML 속성 값(따옴표 안)용 escape. 배열 등 문자열이 아닌 값은 빈 문자열.
function _GMap_escapeAttr($value) {
	return is_scalar($value) ? htmlspecialchars((string)$value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') : '';
}

function _GMap_normalizeAddress($address) {
	return trim(implode(' ', explode('/', $address)));
}

function _GMap_convertLegacyMapType($type) {
	$names = Array(
		'G_NORMAL_MAP' => 'ROADMAP',
		'G_SATELLITE_MAP' => 'SATELLITE',
		'G_HYBRID_MAP' => 'HYBRID',
		'G_PHYSICAL_MAP' => 'TERRAIN'
	);
	if ($names[$type])
		return $names[$type];
	return $type;
}

/* vim: set noet ts=4 sts=4 sw=4: */
?>
