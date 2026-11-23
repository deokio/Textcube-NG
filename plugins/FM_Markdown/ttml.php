<?php
// PHP TTML parser
// Version 1.10.6 (2015.03.23)
// 2004-2016 Needlworks / TNF / Tatter and Company
// Original TTML is created by JH, 2004.4
// TTML port for Tattertools 1.0 by papacha, 2005.10
// TTML Module port for Tattertools 1.1 by lifthrasiir, 2007.1
// TTML External library for Textcube 1.6 by inureyes, 2008.1
// TTML External library for Textcube 1.8 by inureyes, 2010.7
// TTML External library for Textcube 1.10 by inureyes, 2014.12

function FM_TTML_bindTags($id, $content) {
	$context = Model_Context::getInstance();
	for ($no = 0; (($start = strpos($content, '[#M_')) !== false) && (($end = strpos($content, '_M#]', $start + 4)) !== false); $no++) {
		$prefix = substr($content, 0, $start);
		list($more, $less, $full) = explode('|', substr($content, $start + 4, $end - $start - 4), 3);
		if (strlen($more) == 0) $more = 'more..';
		if (strlen($less) == 0) $less = 'less..';
		$more2 = htmlspecialchars(str_replace("\"", "&quot;", str_replace("'", "&#39;", $more)), ENT_QUOTES | ENT_SUBSTITUTE);
		$less2 = htmlspecialchars(str_replace("\"", "&quot;", str_replace("'", "&#39;", $less)), ENT_QUOTES | ENT_SUBSTITUTE);
		$postfix = substr($content, $end + 4);
		$content = $prefix;
		$content .= "<p id=\"more{$id}_$no\" class=\"moreless_fold\"><span style=\"cursor: pointer;\" onclick=\"toggleMoreLess(this, '{$id}_$no','$more2','$less2'); return false;\">$more</span></p><div id=\"content{$id}_$no\" class=\"moreless_content\" style=\"display: none;\">$full</div>";
		$content .= $postfix;
	}
	return $content;
}

function FM_TTML_bindAttachments($entryId, $folderPath, $folderURL, $content, $useAbsolutePath = false, $bRssMode = false) {
	global $service, $hostURL, $blogURL, $serviceURL;
	$_absServiceURL = $bRssMode ? URL::toAbsolute($serviceURL) : $serviceURL;	// Textcube-NG: 피드(bRssMode)에서만 //host 에 스킴을 붙인다. 화면은 그대로(r3-34)
	requireModel('blog.attachment');
	$context = Model_Context::getInstance();
	$blogid = getBlogId();
	getAttachments($blogid, $entryId); // For attachment caching.
	$view = str_replace('[##_ATTACH_PATH_##]', ($useAbsolutePath ? "{$_absServiceURL}/attach/$blogid" : $folderURL), $content);
	$view = str_replace('http://tt_attach_path/', ($useAbsolutePath ? "{$_absServiceURL}/attach/$blogid/" : ($folderURL . '/')), $view);
	$count = 0;
	$bWritedGalleryJS = false;

	while ((($start = strpos($view, '[##_')) !== false) && (($end = strpos($view, '_##]', $start + 4)) !== false)) {
		$count++;
		$attributes = explode('|', substr($view, $start + 4, $end - $start - 4));
		$prefix = '';
		$buf = '';
		if ($attributes[0] == 'Gallery') {
			if (count($attributes) % 2 == 1)
				array_pop($attributes);
			if ($context->getProperty('blog.displaymode','desktop')=='mobile') {
				$images = array_slice($attributes, 1, count($attributes) - 2);
				for ($i = 0; $i < count($images); $i++) {
					if (!empty($images[$i])) {
						if ($i % 2 == 0)
							$buf .= '<div align="center">' . FM_TTML_getAttachmentBinder($images[$i], '', $folderPath, $folderURL, 1, $useAbsolutePath, $bRssMode) . '</div>';
						else if (strlen($images[$i]) > 0)
							$buf .= "<div align=\"center\">$images[$i]</div>";
					}
				}
			} else if ($bRssMode == true) {
				$items = array();
				for ($i = 1; $i < sizeof($attributes) - 2; $i += 2)
					array_push($items, array($attributes[$i], $attributes[$i + 1]));
				$galleryAttributes = Misc::getAttributesFromString($attributes[sizeof($attributes) - 1]);

				$images = array_slice($attributes, 1, count($attributes) - 2);
				for ($i = 0; $i < count($images); $i++) {
					if (!empty($images[$i])) {
						if ($i % 2 == 0) {
							$setWidth = $setHeight = 0;
							if (list($width, $height) = FM_TTML_getImageSize($folderPath, $images[$i])) {	// Textcube-NG: svg·webp·avif 크기(r3-20)

								$setWidth = $width;
								$setHeight = $height;
								if (isset($galleryAttributes['width']) && $galleryAttributes['width'] < $setWidth) {
									$setHeight = $setHeight * $galleryAttributes['width'] / $setWidth;
									$setWidth = $galleryAttributes['width'];
								}
								if (isset($galleryAttributes['height']) && $galleryAttributes['height'] < $setHeight) {
									$setWidth = $setWidth * $galleryAttributes['height'] / $setHeight;
									$setHeight = $galleryAttributes['height'];
								}

								if (intval($setWidth > 0) && intval($setHeight) > 0)
									$tempProperty = 'width="' . intval($setWidth) . '" height="' . intval($setHeight) . '"';
								else
									$tempProperty = '';

								$buf .= '<div align="center">' . FM_TTML_getAttachmentBinder($images[$i], $tempProperty, $folderPath, $folderURL, 1, $useAbsolutePath, $bRssMode) . '</div>';
							}
						} else if (strlen($images[$i]) > 0) {
							$buf .= "<div align=\"center\">{$images[$i]}</div>";
						}
					}
				}
			} else {
				$id = "gallery$entryId$count";
				$cssId = "tt-gallery-$entryId-$count";
				$contentWidth = Misc::getContentWidth();

				$items = array();
				for ($i = 1; $i < sizeof($attributes) - 2; $i += 2)
					array_push($items, array($attributes[$i], $attributes[$i + 1]));
				$galleryAttributes = Misc::getAttributesFromString($attributes[sizeof($attributes) - 1]);

				if (!isset($galleryAttributes['width']))
					$galleryAttributes['width'] = $contentWidth;
				if (!isset($galleryAttributes['height']))
					$galleryAttributes['height'] = 3/4 * $galleryAttributes['width'];

				if ($galleryAttributes['width'] > $contentWidth) {
					$galleryAttributes['height'] = $galleryAttributes['height'] * $contentWidth / $galleryAttributes['width'];
					$galleryAttributes['width'] = $contentWidth;
				}

				if (($useAbsolutePath == true) && ($bWritedGalleryJS == false)) {
					$bWritedGalleryJS = true;
					$buf .= printScript('gallery.js');
				}
				$buf .= CRLF . '<div id="' . $cssId . '" class="tt-gallery-box">' . CRLF;
				$buf .= '	<script type="text/javascript">' . CRLF;
				$buf .= '		//<![CDATA[' . CRLF;
				$buf .= "			var {$id} = new TTGallery(\"{$cssId}\");" . CRLF;
				// Textcube-NG: 갤러리 JS 라벨은 json_encode, 이미지 alt 는 htmlspecialchars 로 escape 한다(v3.193)
				$buf .= "			{$id}.prevText = " . jsonEncodeCompat(_text('이전 이미지 보기'), JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_UNESCAPED_UNICODE) . "; " . CRLF;	// Textcube-NG: JSON_INVALID_UTF8_SUBSTITUTE(7.2) 대신 jsonEncodeCompat()(00-Compat.php, r4-4)
				$buf .= "			{$id}.nextText = " . jsonEncodeCompat(_text('다음 이미지 보기'), JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_UNESCAPED_UNICODE) . "; " . CRLF;
				$buf .= "			{$id}.enlargeText = " . jsonEncodeCompat(_text('원본 크기로 보기'), JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_UNESCAPED_UNICODE) . "; " . CRLF;
				$buf .= "			{$id}.altText = " . jsonEncodeCompat(_text('갤러리 이미지'), JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_UNESCAPED_UNICODE) . "; " . CRLF;

				foreach ($items as $item) {
					$setWidth = $setHeight = 0;
					if (list($width, $height) = FM_TTML_getImageSize($folderPath, $item[0])) {	// Textcube-NG: svg·webp·avif 크기(r3-20)
						$setWidth = $width;
						$setHeight = $height;
						if (isset($galleryAttributes['width']) && $galleryAttributes['width'] < $setWidth) {
							$setHeight = $setHeight * $galleryAttributes['width'] / $setWidth;
							$setWidth = $galleryAttributes['width'];
						}
						if (isset($galleryAttributes['height']) && $galleryAttributes['height'] < $setHeight) {
							$setWidth = $setWidth * $galleryAttributes['height'] / $setHeight;
							$setHeight = $galleryAttributes['height'];
						}
						$item[1] = str_replace("'", '&#39;', $item[1]);
						$buf .= $id . '.appendImage("' . ($useAbsolutePath ? "{$serviceURL}/attach/$blogid/$item[0]" : "$folderURL/$item[0]") . '", "' . htmlspecialchars($item[1], ENT_QUOTES | ENT_SUBSTITUTE) . '", ' . intval($setWidth) . ', ' . intval($setHeight) . ");";
					}
				}
				$buf .= "			{$id}.show();" . CRLF;
				$buf .= "		//]]>" . CRLF;
				$buf .= '	</script>' . CRLF;
				$buf .= '	<noscript>' . CRLF;
				foreach ($items as $item) {
					$setWidth = $setHeight = 0;
					if (list($width, $height) = FM_TTML_getImageSize($folderPath, $item[0])) {	// Textcube-NG: svg·webp·avif 크기(r3-20)
						$setWidth = $width;
						$setHeight = $height;
						if (isset($galleryAttributes['width']) && $galleryAttributes['width'] < $setWidth) {
							$setHeight = $setHeight * $galleryAttributes['width'] / $setWidth;
							$setWidth = $galleryAttributes['width'];
						}
						if (isset($galleryAttributes['height']) && $galleryAttributes['height'] < $setHeight) {
							$setWidth = $setWidth * $galleryAttributes['height'] / $setHeight;
							$setHeight = $galleryAttributes['height'];
						}

						$buf .= '<div class="imageblock center" style="text-align: center; clear: both;">';
						if ($useAbsolutePath)
							$buf .= '		<img src="' . $serviceURL . "/attach/" . $blogid . "/" . $item[0] . '" width="' . intval($setWidth) . '" height="' . intval($setHeight) . '" alt="' . htmlspecialchars(_text('사용자 삽입 이미지'), ENT_QUOTES, 'UTF-8') . '" />' . CRLF;
						else
							$buf .= '		<img src="' . $folderURL . "/" . $item[0] . '" width="' . intval($setWidth) . '" height="' . intval($setHeight) . '" alt="' . htmlspecialchars(_text('사용자 삽입 이미지'), ENT_QUOTES, 'UTF-8') . '" />' . CRLF;
						if(!empty($item[1]))
							$buf .= '		<p class="cap1">'. $item[1] .'</p>' . CRLF;
						$buf .= '</div>';
					}
				}
				$buf .= '	</noscript>' . CRLF;
				$buf .= '</div>' . CRLF;
			}
		} else if ($attributes[0] == 'iMazing') {
			if ($context->getProperty('blog.displaymode','desktop')=='mobile' || ($bRssMode == true)) {
				$images = array_slice($attributes, 1, count($attributes) - 3);
				for ($i = 0; $i < count($images); $i += 2) {
					if (!empty($images[$i]))
						$buf .= '<div>' . FM_TTML_getAttachmentBinder($images[$i], '', $folderPath, $folderURL, 1, $useAbsolutePath, $bRssMode) . '</div>';
				}
				$buf .= $attributes[count($attributes) - 1];
			} else {
				$params = Misc::getAttributesFromString($attributes[sizeof($attributes) - 2]);
				$id = $entryId . $count;
				$imgs = array_slice($attributes, 1, count($attributes) - 3);
				$imgStr = '';
				for ($i = 0; $i < count($imgs); $i += 2) {
					if ($imgs[$i] != '') {
						$imgStr .= $service['path'] . '/attach/' . $blogid . '/' . $imgs[$i];
						if ($i < (count($imgs) - 2))
							$imgStr .= '*!';
					}
				}
				if (!empty($attributes[count($attributes) - 1])) {
					$caption = '<p class="cap1">' . $attributes[count($attributes) - 1] . '</p>';
				} else {
					$caption = '';
				}
				$buf .= '<div style="clear: both; text-align: center"><img src="' . ($useAbsolutePath ? $serviceURL : $service['path']) . '/resources/image/gallery/gallery_enlarge.gif" alt="' . htmlspecialchars(_text('확대'), ENT_QUOTES, 'UTF-8') . '" style="cursor:pointer" onclick="openFullScreen(\'' . $service['path'] . '/iMazing?d=' . urlencode($id) . '&f=' . urlencode($params['frame']) . '&t=' . urlencode($params['transition']) . '&n=' . urlencode($params['navigation']) . '&si=' . urlencode($params['slideshowinterval']) . '&p=' . urlencode($params['page']) . '&a=' . urlencode($params['align']) . '&o=' . $blogid . '&i=' . $imgStr . '\',\'' . htmlspecialchars(str_replace("'", "&#39;", $attributes[count($attributes) - 1]), ENT_QUOTES | ENT_SUBSTITUTE) . '\',\'' . $service['path'] . '\')" />';
				$_galNav = (!empty($params['navigation']) && $params['navigation'] !== '0') ? true : false;
				$_galInterval = !empty($params['slideshowinterval']) ? intval($params['slideshowinterval']) : 0;
				if ($_galInterval > 0 && $_galInterval <= 100) $_galInterval *= 1000;
				$_galStart = !empty($params['page']) ? max(0, intval($params['page']) - 1) : 0;
				$_galBasePath = $useAbsolutePath ? $serviceURL : $service['path'];
				$_cid = 'iMazingContainer' . $id;
				$buf .= '<style>'
					. '#' . $_cid . '{position:relative;overflow:hidden;background:#000;}'
					. '#' . $_cid . ' img{position:absolute!important;top:0!important;left:0!important;width:100%!important;height:100%!important;margin:0!important;padding:0!important;object-fit:contain;opacity:0!important;transition:opacity 0.5s;}'
					. '#' . $_cid . ' img.tc-gal-on{opacity:1!important;}'
					. '#' . $_cid . ' button{position:absolute;top:50%;transform:translateY(-50%);background:rgba(0,0,0,.5)!important;color:#fff!important;border:none!important;font-size:1.5em;padding:4px 10px;cursor:pointer;z-index:10;}'
					. '</style>';
				$buf .= '<div id="' . $_cid . '" class="iMazingContainer" style="width:' . $params['width'] . 'px;height:' . $params['height'] . 'px;">';
				$_galIdx = 0;
				for ($i = 0; $i < count($imgs); $i += 2) {
					if (!empty($imgs[$i])) {
						$_galSrc = htmlspecialchars($_galBasePath . '/attach/' . $blogid . '/' . $imgs[$i], ENT_QUOTES, 'UTF-8');
						$_galCls = ($_galIdx === $_galStart) ? ' class="tc-gal-on"' : '';
						$buf .= '<img src="' . $_galSrc . '" alt=""' . $_galCls . ' />';
						$_galIdx++;
					}
				}
				if ($_galNav && $_galIdx > 1) {
					$buf .= '<button onclick="tcGal_' . $id . '(-1)" style="left:4px;">&#8249;</button>'
						. '<button onclick="tcGal_' . $id . '(1)" style="right:4px;">&#8250;</button>';
				}
				$buf .= '</div>';
				if ($_galIdx > 0) {
					$buf .= '<script>'
						. '(function(){'
						. 'var imgs=document.querySelectorAll(\'#' . $_cid . ' img\'),cur=' . $_galStart . ',total=imgs.length;'
						. 'window.tcGal_' . $id . '=function(d){imgs[cur].classList.remove(\'tc-gal-on\');cur=(cur+d+total)%total;imgs[cur].classList.add(\'tc-gal-on\');};';
					if ($_galInterval > 0)
						$buf .= 'setInterval(function(){window.tcGal_' . $id . '(1);},' . $_galInterval . ');';
					$buf .= '})();'
						. '</script>';
				}
				$buf .= $caption . '</div>';
			}
		} else if ($attributes[0] == 'Jukebox') {
			if ($context->getProperty('blog.displaymode','desktop')=='mobile') {
				$sounds = array_slice($attributes, 1, count($attributes) - 3);
				for ($i = 0; $i < count($sounds); $i += 2) {
					if (!empty($sounds[$i]))
						$buf .= '<a href="' . htmlspecialchars("$folderURL/" . $sounds[$i], ENT_QUOTES, 'UTF-8') . '">' . htmlspecialchars($sounds[$i], ENT_QUOTES, 'UTF-8') . '</a><br />';	// Textcube-NG: echo 는 본문 자리 밖(호출 시점)으로 나가고 본문 캐시·피드에서 빠지므로 desktop 분기와 같이 $buf 에 escape 해서 붙인다 (r3-38 R-556)
				}
			} else {
				$params = Misc::getAttributesFromString($attributes[sizeof($attributes) - 2]);
				foreach ($params as $key => $value) {
					if ($key == 'autoPlay') {
						unset($params['autoplay']);
						$params['autoplay'] = $value;
					}
				}
				if ($params['visible'] == 1) {
					$width = '250px';
					$height = '27px';
				} else {
					$width = '0px';
					$height = '0px';
				}
				$id = $entryId . $count;
				$imgs = array_slice($attributes, 1, count($attributes) - 3);
				$imgStr = '';
				for ($i = 0; $i < count($imgs); $i++) {
					if ($imgs[$i] == '')
						continue;
					if ($i % 2 == 1) {
						$imgStr .= urlencode($imgs[$i]) . '_*';
						continue;
					} else {
						if ($i < (count($imgs) - 1))
							$imgStr .= "{$service['path']}/attach/$blogid/" . urlencode($imgs[$i]) . '*!';
					}
				}
				if (!empty($attributes[count($attributes) - 1])) {
					$caption = '<div class="cap1" style="text-align: center">' . $attributes[count($attributes) - 1] . '</div>';
				} else {
					$caption = '';
				}

				$_jkAutoplay = (!empty($params['autoplay']) && $params['autoplay'] !== 'false' && $params['autoplay'] !== '0') ? ' autoplay' : '';
				$_jkBasePath = $useAbsolutePath ? $_absServiceURL : $service['path'];
				$_jkTracks = [];
				for ($i = 0; $i < count($imgs); $i += 2) {
					if (!empty($imgs[$i])) {
						$_jkTracks[] = [
							'url'  => htmlspecialchars($_jkBasePath . '/attach/' . $blogid . '/' . $imgs[$i], ENT_QUOTES, 'UTF-8'),
							'name' => (isset($imgs[$i + 1]) && $imgs[$i + 1] !== '') ? htmlspecialchars($imgs[$i + 1], ENT_QUOTES, 'UTF-8') : htmlspecialchars(basename($imgs[$i]), ENT_QUOTES, 'UTF-8'),
						];
					}
				}
				if (count($_jkTracks) > 0) {
					$buf .= '<div id="jukeBox' . $id . 'Div" style="margin-left:auto;margin-right:auto;">';
					$buf .= '<audio id="jukeBoxAudio' . $id . '" controls' . $_jkAutoplay . ' style="width:100%;display:block;margin-bottom:4px">'
						. '<source src="' . $_jkTracks[0]['url'] . '" />'
						. '<a href="' . $_jkTracks[0]['url'] . '">' . $_jkTracks[0]['name'] . '</a>'
						. '</audio>';
					if (count($_jkTracks) > 1) {
						$buf .= '<ul id="jukeBoxList' . $id . '" style="list-style:none;margin:0;padding:0;max-height:200px;overflow-y:auto;border:1px solid #ddd;border-radius:3px;">';
						foreach ($_jkTracks as $_jkIdx => $_jkTrack) {
							$_jkItemStyle = ($_jkIdx === 0) ? 'padding:5px 10px;cursor:pointer;border-bottom:1px solid #eee;background:#e8f0fe;font-weight:bold;' : 'padding:5px 10px;cursor:pointer;border-bottom:1px solid #eee;';
							$buf .= '<li data-idx="' . $_jkIdx . '" style="' . $_jkItemStyle . '" onclick="jkPlay_' . $id . '(' . $_jkIdx . ')">'
								. $_jkTrack['name'] . '</li>';
						}
						$buf .= '</ul>';
					}
					$_jkUrls = json_encode(array_column($_jkTracks, 'url'));
					$buf .= '<script>'
						. '(function(){'
						. 'var a=document.getElementById(\'jukeBoxAudio' . $id . '\');'
						. 'var ul=document.getElementById(\'jukeBoxList' . $id . '\');'
						. 'var t=' . $_jkUrls . ',c=0;'
						. 'window.jkPlay_' . $id . '=function(i){'
						. 'c=i;a.src=t[i];a.load();a.play().catch(function(){});'
						. 'if(ul){var li=ul.querySelectorAll(\'li\');'
						. 'li.forEach(function(el,j){el.style.background=(j===i)?\'#e8f0fe\':\'\';el.style.fontWeight=(j===i)?\'bold\':\'\';});}'
						. '};'
						. 'if(a)a.addEventListener(\'ended\',function(){window.jkPlay_' . $id . '((c+1)%t.length);});'
						. '})();'
						. '</script>';
					$buf .= '</div>';
				}
			}
		} else {
			$contentWidth = Misc::getContentWidth();

			switch (count($attributes)) {
				case 4:
					list($newProperty, $onclickFlag) = FM_TTML_createNewProperty($attributes[1], $contentWidth, $attributes[2]);

					if ($context->getProperty('blog.displaymode','desktop')=='mobile') {
						$buf = '<div class="imageblock center">' . FM_TTML_getAttachmentBinder($attributes[1], $newProperty, $folderPath, $folderURL, 1, $useAbsolutePath, $bRssMode) . "</div><div>$attributes[3]</div>";
					} else {
						if (trim($attributes[3]) == '') {
							$caption = '';
						} else {
							$caption = '<p class="cap1">' . $attributes[3] . '</p>';
						}
						switch ($attributes[0]) {
							case '1L':
								$prefix = '<div class="imageblock left" style="float: left; margin-right: 10px;">';
								break;
							case '1R':
								$prefix = '<div class="imageblock right" style="float: right; margin-left: 10px;">';
								break;
							case '1C':
							default:
								$prefix = '<div class="imageblock center" style="text-align: center; clear: both;">';
								break;
						}
						$buf = $prefix . FM_TTML_getAttachmentBinder($attributes[1], $newProperty, $folderPath, $folderURL, 1, $useAbsolutePath, $bRssMode, $onclickFlag) . $caption . '</div>';
					}
					break;
				case 7:
					$eachImageWidth = floor(($contentWidth - 5 * 3) / 2);
					list($newProperty1, $onclickFlag1) = FM_TTML_createNewProperty($attributes[1], $eachImageWidth, $attributes[2]);
					list($newProperty2, $onclickFlag2) = FM_TTML_createNewProperty($attributes[4], $eachImageWidth, $attributes[5]);
					if ($context->getProperty('blog.displaymode','desktop')=='mobile') {
						$buf = '<div>' . FM_TTML_getAttachmentBinder($attributes[1], $newProperty1, $folderPath, $folderURL, 1, $useAbsolutePath, $bRssMode) . "</div><div>$attributes[3]</div>";
						$buf .= '<div>' . FM_TTML_getAttachmentBinder($attributes[4], $newProperty2, $folderPath, $folderURL, 1, $useAbsolutePath, $bRssMode) . "</div><div>$attributes[6]</div>";
					} else {
						$cap1 = strlen(trim($attributes[3])) > 0 ? '<p class="cap1">' . $attributes[3] . '</p>' : '';
						$cap2 = strlen(trim($attributes[6])) > 0 ? '<p class="cap1">' . $attributes[6] . '</p>' : '';
						$buf = '<div class="imageblock dual" style="text-align: center;"><table cellspacing="5" cellpadding="0" border="0" style="margin: 0 auto;"><tr><td>'
							. FM_TTML_getAttachmentBinder($attributes[1], $newProperty1, $folderPath, $folderURL, 2, $useAbsolutePath, $bRssMode, $onclickFlag1) . $cap1 . '</td><td>'
							. FM_TTML_getAttachmentBinder($attributes[4], $newProperty2, $folderPath, $folderURL, 2, $useAbsolutePath, $bRssMode, $onclickFlag2) . $cap2 . '</td></tr></table></div>';
					}
					break;
				case 10:
					$eachImageWidth = floor(($contentWidth - 5 * 4) / 3);
					list($newProperty1, $onclickFlag1) = FM_TTML_createNewProperty($attributes[1], $eachImageWidth, $attributes[2]);
					list($newProperty2, $onclickFlag2) = FM_TTML_createNewProperty($attributes[4], $eachImageWidth, $attributes[5]);
					list($newProperty3, $onclickFlag3) = FM_TTML_createNewProperty($attributes[7], $eachImageWidth, $attributes[8]);
					if ($context->getProperty('blog.displaymode','desktop')=='mobile') {
						$buf = '<div>' . FM_TTML_getAttachmentBinder($attributes[1], $newProperty1, $folderPath, $folderURL, 1, $useAbsolutePath, $bRssMode) . "</div><div>$attributes[3]</div>";
						$buf .= '<div>' . FM_TTML_getAttachmentBinder($attributes[4], $newProperty2, $folderPath, $folderURL, 1, $useAbsolutePath, $bRssMode) . "</div><div>$attributes[6]</div>";
						$buf .= '<div>' . FM_TTML_getAttachmentBinder($attributes[7],$newProperty3, $folderPath, $folderURL, 1, $useAbsolutePath, $bRssMode) . "</div><div>$attributes[9]</div>";
					} else {
						$cap1 = strlen(trim($attributes[3])) > 0 ? '<p class="cap1">' . $attributes[3] . '</p>' : '';
						$cap2 = strlen(trim($attributes[6])) > 0 ? '<p class="cap1">' . $attributes[6] . '</p>' : '';
						$cap3 = strlen(trim($attributes[9])) > 0 ? '<p class="cap1">' . $attributes[9] . '</p>' : '';
						$buf = '<div class="imageblock triple" style="text-align: center"><table cellspacing="5" cellpadding="0" border="0" style="margin: 0 auto;"><tr><td>'
							. FM_TTML_getAttachmentBinder($attributes[1], $newProperty1, $folderPath, $folderURL, 3, $useAbsolutePath, $bRssMode, $onclickFlag1) . $cap1 . '</td><td>'
							. FM_TTML_getAttachmentBinder($attributes[4], $newProperty2, $folderPath, $folderURL, 3, $useAbsolutePath, $bRssMode, $onclickFlag2) . $cap2 . '</td><td>'
							. FM_TTML_getAttachmentBinder($attributes[7], $newProperty3, $folderPath, $folderURL, 3, $useAbsolutePath, $bRssMode, $onclickFlag3) . $cap3 . '</td></tr></table></div>';
					}
					break;
				// 어디에도 해당되지 않을 경우 임시 태그를 되살림.
				default:
					$buf = '[###_###_###_' . implode('|', $attributes) . '_###_###_###]';
					break;
			}
		}
		$view = substr($view, 0, $start) . $buf . substr($view, $end + 4);
	}

	$view = preg_replace(array("@\[###_###_###_@", "@_###_###_###\]@"), array('[##_', '_##]'), $view);
	return $view;
}

function FM_TTML_getAttachmentBinder($filename, $property, $folderPath, $folderURL, $imageBlocks = 1, $useAbsolutePath = true, $bRssMode = false, $onclickFlag=false) {
	global $database, $skinSetting, $service, $blogURL, $hostURL, $serviceURL;
	$_absServiceURL = $bRssMode ? URL::toAbsolute($serviceURL) : $serviceURL;	// Textcube-NG: 피드(bRssMode)에서만 //host 에 스킴을 붙인다. 화면은 그대로(r3-34)
	$context = Model_Context::getInstance();
	$blogid = getBlogId();
	$path = "$folderPath/$filename";
	if ($useAbsolutePath)
		$url = "$_absServiceURL/attach/$blogid/$filename";
	else
		$url = "$folderURL/$filename";
	$fileInfo = getAttachmentByOnlyName($blogid, $filename);
	switch (Misc::getFileExtension($filename)) {
		case 'jpg':case 'jpeg':case 'gif':case 'png':case 'bmp':case 'webp':case 'avif':	// Textcube-NG: webp·avif(r3-20)
			$bPassing = false;
/*			if ($context->getProperty('blog.displaymode','desktop')=='mobile') {
				if (!is_null(Setting::getBlogSettingGlobal("resamplingDefault"))) {
					$waterMarkOn = Setting::getBlogSettingGlobal("waterMarkDefault", "no");
					$exist = preg_match('/class="tt-watermark"/i', $property);
					if (($waterMarkOn == 'yes') && ($exist == 1)) $bPassing = true;
				}
				if ($bPassing == false)
					return fireEvent('ViewAttachedImageMobile', "<img class=\"img-responsive\" src=\"$blogURL/imageResizer/?f=" . urlencode($filename) . "\" alt=\"\" />", $path);
			}*/
			if ($context->getProperty('blog.displaymode','desktop')=='mobile') {
				$resizeClass="class=\"img-responsive\" ";
			} else {
				$resizeClass="";
			}
			if ( ($context->getProperty('blog.displaymode','desktop')=='mobile') ||
				(($onclickFlag == true) && ($bRssMode == false) && ($bPassing == false)) ) {
				$imageStr = '<img '.$resizeClass.'src="'.$url.'" '.$property.' style="cursor: pointer;" onclick="open_img(\''.$url.'\'); return false;" />';
			} else {
				$imageStr = '<img '.$resizeClass.'src="'.$url.'" '.$property.' />';
			}

			return fireEvent('ViewAttachedImage', $imageStr, $path);
			break;
		// Textcube-NG: svg 는 <img> 로만 낸다(<img> 안 svg 는 스크립트·외부 참조를 실행하지 않는다). config.php $service['svgRasterize'] 가 true 이고
		//   래스터 사본(cache/thumbnail/<blogid>/<파일>.png)이 있으면 사본을 낸다. 크기 속성이 없으면 첨부 행 width·height 를 붙인다(r3-20)
		case 'svg':
			if ($context->getProperty('blog.displaymode','desktop')=='mobile') {
				$resizeClass="class=\"img-responsive\" ";
			} else {
				$resizeClass="";
			}
			$src = $url;
			if ($context->getProperty('service.svgRasterize') === true && is_file(__TEXTCUBE_CACHE_DIR__."/thumbnail/$blogid/$filename.png"))
				$src = ($useAbsolutePath ? $_absServiceURL : $context->getProperty('uri.path'))."/cache/thumbnail/$blogid/$filename.png";
			if (!preg_match('/\b(width|height)\s*=/i', $property) && is_array($fileInfo) && intval($fileInfo['width']) > 0 && intval($fileInfo['height']) > 0)
				$property = trim($property . ' width="' . intval($fileInfo['width']) . '" height="' . intval($fileInfo['height']) . '"');
			if ( ($context->getProperty('blog.displaymode','desktop')=='mobile') ||
				(($onclickFlag == true) && ($bRssMode == false)) ) {
				$imageStr = '<img '.$resizeClass.'src="'.$src.'" '.$property.' style="cursor: pointer;" onclick="open_img(\''.$url.'\'); return false;" />';
			} else {
				$imageStr = '<img '.$resizeClass.'src="'.$src.'" '.$property.' />';
			}
			return fireEvent('ViewAttachedImage', $imageStr, $path);
			break;
		case 'swf':
			return '<p class="system-message">' . _text('Flash 콘텐츠는 더 이상 지원되지 않습니다.') . '</p>';
			break;
		case 'wmv':case 'avi':case 'asf':case 'mpg':case 'mpeg':
			// Textcube-NG: r3-32 R-521 — 원본은 $url·$property 를 escape 없이 writeCode('<embed …>') 로 내보내고 절대경로일 때 http:// 를 잘라 주소를 깨뜨렸다. mov(r3-31 R-429 B)와 같이 HTML5 <video> + 대체 링크로 바꾸고 URL·이름을 escape 한다
			$_videoUrl = htmlspecialchars($url, ENT_QUOTES, 'UTF-8');
			return '<video controls width="400" height="300" style="max-width:100%"><source src="' . $_videoUrl . '" /><a href="' . $_videoUrl . '">' . htmlspecialchars(isset($fileInfo['label']) ? $fileInfo['label'] : $filename, ENT_QUOTES, 'UTF-8') . '</a></video>';
			break;
		case 'mp3':case 'mp2':case 'wma':case 'wav':case 'mid':case 'midi':
			$_audioUrl = htmlspecialchars($url, ENT_QUOTES, 'UTF-8');
			return '<audio controls style="width:100%;display:block;margin:4px 0"><source src="' . $_audioUrl . '" /><a href="' . $_audioUrl . '">' . htmlspecialchars(isset($fileInfo['label']) ? $fileInfo['label'] : $filename, ENT_QUOTES, 'UTF-8') . '</a></audio>';
			break;
		case 'mov':
			// Textcube-NG: IE 전용 QuickTime ActiveX(clsid) object 를 HTML5 video 로 바꿈(r3-31 R-429 B, r3-30 R-429 A·오디오 첨부 <audio> 와 같은 방식)
			$_videoUrl = htmlspecialchars($url, ENT_QUOTES, 'UTF-8');
			return '<video controls width="400" height="300" style="max-width:100%"><source src="' . $_videoUrl . '" /><a href="' . $_videoUrl . '">' . htmlspecialchars(isset($fileInfo['label']) ? $fileInfo['label'] : $filename, ENT_QUOTES, 'UTF-8') . '</a></video>';
			break;
		default:
			if (file_exists(ROOT . '/resources/image/extension/' . Misc::getFileExtension($fileInfo['label']) . '.gif')) {
				return '<a class="extensionIcon" href="' . ($useAbsolutePath ? $hostURL : '') . $blogURL . '/attachment/' . $filename . '">' . fireEvent('ViewAttachedFileExtension', '<img src="' . ($useAbsolutePath ? $_absServiceURL : $service['path']) . '/resources/image/extension/' . Misc::getFileExtension($fileInfo['label']) . '.gif" alt="" />') . ' ' . htmlspecialchars($fileInfo['label'], ENT_QUOTES | ENT_SUBSTITUTE) . '</a>';
			} else {
				return '<a class="extensionIcon" href="' . ($useAbsolutePath ? $hostURL : '') . $blogURL . '/attachment/' . $filename . '">' . fireEvent('ViewAttachedFileExtension', '<img src="' . ($useAbsolutePath ? $_absServiceURL : $service['path']) . '/resources/image/extension/unknown.gif" alt="" />') . ' ' . htmlspecialchars($fileInfo['label'], ENT_QUOTES | ENT_SUBSTITUTE) . '</a>';
			}
			break;
	}
}

// Textcube-NG: 갤러리·iMazing 이미지 크기. svg·webp·avif 는 getimagesize 가 0·실패면 첨부 행 width·height 를 쓴다(r3-20)
//   그 밖의 확장자는 원본과 같이 getimagesize 결과(배열이면 그 크기, 아니면 false)를 돌려준다
function FM_TTML_getImageSize($folderPath, $name) {
	$size = @getimagesize("$folderPath/$name");
	if (!in_array(Misc::getFileExtension($name), array('svg', 'webp', 'avif'), true)) return is_array($size) ? array($size[0], $size[1]) : false;
	if (is_array($size) && $size[0] > 0 && $size[1] > 0) return array($size[0], $size[1]);
	if (!is_file("$folderPath/$name")) return false;
	$info = getAttachmentByOnlyName(getBlogId(), $name);
	if (!is_array($info) || intval($info['width']) <= 0 || intval($info['height']) <= 0) return false;
	return array(intval($info['width']), intval($info['height']));
}

function FM_TTML_createNewProperty($filename, $imageWidth, $property) {
	$blogid = getBlogId();
	$image = Utils_Image::getInstance();
	$imageType = $image->getImageType(__TEXTCUBE_ATTACH_DIR__."/$blogid/$filename");
	// Textcube-NG: webp·avif 는 getimagesize 크기가 0 보다 클 때만 줄인다(resizeImageToContent 가 크기로 나눈다)(r3-20)
	if (in_array($imageType, array('webp', 'avif'), true)) {
		$imageSize = @getimagesize(__TEXTCUBE_ATTACH_DIR__."/$blogid/$filename");
		if (!is_array($imageSize) || $imageSize[0] <= 0 || $imageSize[1] <= 0) $imageType = false;
	}
	if (in_array($imageType, array('gif', 'png', 'jpg', 'bmp', 'webp', 'avif')))
		return $image->resizeImageToContent($property, __TEXTCUBE_ATTACH_DIR__."/$blogid/$filename", $imageWidth);
	else
		return array($property, false);
}

function FM_TTML_preserve_TTML_type_tags($contents) {
	return str_replace("_##]","-##]",str_replace("[##_","[##-",$contents));
}

function FM_TTML_restore_TTML_type_tags($contents) {
	return str_replace("-##]","_##]",str_replace("[##-","[##_",$contents));
}
?>
