<?php
/* URL keeper for Tattertools 1.1 / Textcube 1.5
----------------------------------
Version 1.0
By Needlworks / TNF

Created at       : 2006.11.23
Last modified at : 2007.07.21
 
This plugin keeps original permalink.
For the detail, visit http://forum.tattersite.com/ko

General Public License
http://www.gnu.org/licenses/gpl.html

This program is free software; you can redistribute it and/or modify
it under the terms of the GNU General Public License as published by
the Free Software Foundation; either version 2 of the License, or
(at your option) any later version.
*/

function URLkeeper($target)
{
	global $configVal;
	requireComponent('Tattertools.Function.misc');
	$data = Setting::fetchConfigVal($configVal);
	$config = (int)(isset($data['viewForm']) ? $data['viewForm'] : 0);
	$target .= '
<script type="text/javascript">
//<![CDATA[
(function() {
	if (top === self) return;

	var myurl = location.href;
	var config = ' . $config . ';
	var isExternalFrame = false;

	if (window.location.ancestorOrigins) {
		// ancestorOrigins: Chrome / Edge / Safari 지원 — 각 상위 프레임 출처를 직접 열거
		for (var i = 0; i < window.location.ancestorOrigins.length; i++) {
			if (window.location.ancestorOrigins[i] !== window.location.origin) {
				isExternalFrame = true;
				break;
			}
		}
	} else {
		// ancestorOrigins 미지원(Firefox) — top.location 읽기 시도로 교차 출처 판별
		// 동일 출처: 읽기 성공 / 교차 출처: SecurityError 발생
		try {
			void top.location.href;
		} catch (e) {
			isExternalFrame = true;
		}
	}

	if (!isExternalFrame) return;

	var lang = (navigator.language || navigator.userLanguage || "en").substr(0, 2);
	var msg = (lang === "ko")
		? "원래 주소인 " + myurl + " 로 접속해주세요."
		: "Please visit directly via " + myurl;

	function bustFrame() {
		// top.location.replace: 팝업 차단기 영향 없는 탐색 방식
		try { top.location.replace(myurl); return; } catch (e) {}
		try { top.location.href = myurl; return; } catch (e) {}
		location.replace(myurl);
	}

	if (config === 1) {
		bustFrame();
	} else {
		if (confirm(msg)) bustFrame();
	}
})();
//]]>
</script>
' . CRLF;
	return $target;
}
?>
