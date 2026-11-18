<?php
function FM_Modern_handleconfig($configVal) {
	$config = Setting::fetchConfigVal($configVal);
	if (isset($config['defaultmode']) && $config['defaultmode'] != 'WYSIWYG' && $config['defaultmode'] != 'TEXTAREA') return false;
	if (isset($config['paragraphdelim']) && $config['paragraphdelim'] != 'P' && $config['paragraphdelim'] != 'BR') return false;
	return true;
}

function FM_Modern_editorinit($editor) {
	global $service, $configVal, $entry;
	$blogid = getBlogId();
	if (is_null($configVal) || empty($configVal)) {
		$config = array('paragraphdelim' => 'BR',
			'defaultmode' => 'WYSIWYG');
	} else {
		$config = Setting::fetchConfigVal($configVal);
	}
	if (in_array(Setting::getBlogSettingGlobal('defaultFormatter','html'),array('markdown','textile')) ||
		in_array($entry['contentformatter'],array('markdown','textile'))) {
		$config['defaultmode'] = 'TEXTAREA';
	} else if (!isset($config['defaultmode'])) {
		$config['defaultmode'] = 'WYSIWYG';
	}

	ob_start();
?>
			if (typeof(document.execCommand) == "undefined" || !(STD.isIE || STD.isFirefox || (STD.isWebkit && STD.engineVersion >= 419.3))) return null;
			var editor = new TTModernEditor();
			editor.fixPosition = <?php echo (int)Setting::getBlogSettingGlobal('editorPropertyPositionFix', 0);?>;
			editor.hasGD = <?php echo extension_loaded('gd') ? 'true' : 'false';?>;
			editor.propertyFilePath = "<?php echo $service['path'];?>/attach/<?php echo $blogid;?>/";
			editor.editMode = "<?php echo $config['defaultmode'];?>";
			editor.newLineToParagraph = <?php echo (isset($config['paragraphdelim']) && $config['paragraphdelim'] == 'P' ? 'true' : 'false');?>;
			return editor;
<?php
	$result = ob_get_contents();
	ob_end_clean();
	return $result;
}

function FM_Modern_generateTranslationJavascript($messages) {
	// The language setting follows in which context this function is called.
	$table = array();
	foreach ($messages as $text) {
		$table[$text] = _t($text);
	}
	$json = json_encode($table, JSON_FORCE_OBJECT | JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_UNESCAPED_UNICODE | JSON_INVALID_UTF8_SUBSTITUTE);
	if ($json === false) {
		$json = '{}';
	}
	return "<script type=\"text/javascript\">\n(function(t){var own=function(o,k){return Object.prototype.hasOwnProperty.call(o,k);};var d=window.__text||(window.__text={});for(var k in t){if(own(t,k)&&!own(d,k))d[k]=t[k];}if(typeof window._t!=='function'){window._t=function(m){var x=window.__text;return(x&&own(x,m)&&x[m])||m;};}})(" . $json . ");\n</script>\n";
}

function FM_Modern_adminheader($target, $mother) {
	global $suri, $pluginURL;
    $context = Model_Context::getInstance();
	if ($context->getProperty('editor.key') == 'modern') {
		if ($suri['directive'] == '/owner/entry/post' || $suri['directive'] == '/owner/entry/edit') {
			$target .= "\t<link rel=\"stylesheet\" media=\"screen\" type=\"text/css\" href=\"$pluginURL/editor.css\" />\n";
			$target .= "\t<script type=\"text/javascript\" src=\"$pluginURL/editor.js\"></script>\n";
			$target .= FM_Modern_generateTranslationJavascript(array(
				'Embed',
				'Gallery',
				'HTML 모드',
				'Image',
				'Jukebox',
				'More/Less',
				'OBJECT 태그만 입력하세요.',
				'Object',
				'Object 1',
				'URL',
				'URL을 입력하세요.',
				'WYSIWYG 모드',
				'WYSIWYG 편집기',
				'fontDisplayName:fontCode:fontFamily',
				'iMazing',
				'가로',
				'가운데 정렬',
				'굵게',
				'글자색',
				'글자체',
				'기본',
				'기울임',
				'기타',
				'내비게이션',
				'내어쓰기',
				'높이',
				'대상',
				'대체 텍스트',
				'두번째 오브젝트',
				'두번째 이미지',
				'들여쓰기',
				'링크를 만들 영역을 선택해주세요',
				'문단',
				'미디어 삽입',
				'밑줄',
				'박스로 둘러쌀 영역을 선택해주세요',
				'배경색',
				'번호 매긴 리스트',
				'사용 안함',
				'사이드바',
				'삽입하기',
				'새창',
				'선택한 항목을 아래로 이동합니다.',
				'선택한 항목을 위로 이동합니다.',
				'설정',
				'세로',
				'세번째 오브젝트',
				'세번째 이미지',
				'속성',
				'순서없는 리스트',
				'슬라이드쇼 간격',
				'슬라이딩',
				'심플',
				'아래로',
				'알 수 없는 형식의 파일명입니다.',
				'오른쪽 정렬',
				'오브젝트 삽입',
				'왼쪽 정렬',
				'위로',
				'위치 고정',
				'유형',
				'인용구',
				'자동재생',
				'자막',
				'잘못된 OBJECT 태그입니다.',
				'장면전환효과',
				'적용하기',
				'정렬방법',
				'제목',
				'주소입력',
				'첫번째 오브젝트',
				'첫번째 이미지',
				'최대너비',
				'최대높이',
				'취소선',
				'취소하기',
				'코드',
				'코드 붙여넣기',
				'크기',
				'클릭하시면 HTML 모드로 변경합니다.',
				'클릭하시면 HTML 편집기로 변경합니다.',
				'클릭하시면 WYSIWYG 모드로 변경합니다.',
				'테두리',
				'테두리 없음',
				'텍스트 상자',
				'투명전환',
				'파일',
				'파일 주소',
				'파일명',
				'파일명(수정불가)',
				'편집 환경',
				'폭',
				'폰트 설정',
				'폰트 스타일',
				'플래쉬',
				'플레이어 보이기',
				'하이퍼링크',
				'현재창',
				'화면당 이미지 수',
				'효과 제거',
				'효과없음',
			));
		}
	}
	return $target;
}

