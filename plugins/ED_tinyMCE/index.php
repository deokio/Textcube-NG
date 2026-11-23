<?php
/***
	TinyMCE Editor for Textcube

	Needlworks / Jeongkyu Shin (https://github.com/inureyes)
	CodeMirror plugin by zvuc (https://github.com/zvuc)
*/
function tinyMCE_handleconfig($configVal) {
	$config = Setting::fetchConfigVal($configVal);
	if (isset($config['editormode']) && $config['editormode'] != 'simple' && $config['editormode'] != 'advanced') return false;
	if (isset($config['paragraphdelim']) && $config['paragraphdelim'] != 'P' && $config['paragraphdelim'] != 'BR') return false;
	// PHP port(Textcube-NG): r3-11 skinstyle(편집 구역 스타일)은 index.xml 의 세 값만 받는다(사용자 결정 11-08 16:4x)
	if (isset($config['skinstyle']) && !in_array($config['skinstyle'], array('none', 'local', 'cdn'), true)) return false;
	// PHP port(Textcube-NG): r3-15 R-292 srctheme(코드 보기 테마)도 index.xml 의 값만 받는다(skinstyle 과 같은 방식). 이 값은 tinyMCE_editorinit 이 CodeMirror 테마 이름(JS 문자열)과 테마 CSS 경로에 그대로 낸다
	if (isset($config['srctheme']) && !in_array($config['srctheme'], array('default', 'ambiance', 'base16-dark', 'base16-light', 'cobalt', 'colorforth', 'eclipse', 'elegant', 'liquibyte', 'mdn-like', 'monokai', 'neat', 'neo', 'night', 'paraiso-light', 'paraiso-dark', 'pastel-on-dark', 'rubyblue', 'solarized', 'the-matrix', 'tomorrow-night', 'tomorrow-night-bright', 'tomorrow-night-eighties', 'twilight', 'vibrant-ink', 'xq-dark', 'xq-light', 'zenburn'), true)) return false;
	return true;
}

function tinyMCE_editorinit($editor) {
	global $configVal, $pluginURL, $pluginPath, $adminSkinSetting;
	$context = Model_Context::getInstance();
	$blogid = getBlogId();
	$config = Setting::fetchConfigVal($configVal);
	if(empty($config['editormode'])) $config['editormode'] = 'simple';
	if(empty($config['width'])) $config['width'] = 'skin';
	if(empty($config['srctheme'])) $config['srctheme'] = 'default';
	if($config['srctheme'] == 'default') $config['srctheme'] = 'elegant';
	// Textcube-NG: CodeMirror 에 tomorrow-night 테마 파일이 없어(원본 선택지와 테마 파일 불일치) tomorrow-night-bright 로 싣는다 —
	// 원본은 없는 theme/tomorrow-night.css 를 요청하고 없는 테마 이름을 넘겼다. 아래 theme 이름과 테마 CSS 경로가 함께 바뀐다(r3-16, R-391)
	if($config['srctheme'] == 'tomorrow-night') $config['srctheme'] = 'tomorrow-night-bright';
	$config['formatter'] = null;
    if(empty($config['paragraphdelim'])) $config['paragraphdelim'] = 'BR';
	if($context->getProperty('formatter.key') == 'markdown') {
		$config['formatter'] = 'markdown';
        $config['codemirror_jsfiles'] = array('mode/xml/xml.js','mode/markdown/markdown.js');
		$config['width'] = 'full';
	} else {
        $config['formatter'] = 'htmlmixed';
        $config['codemirror_jsfiles'] = array('mode/xml/xml.js','mode/javascript/javascript.js','mode/css/css.js','mode/htmlmixed/htmlmixed.js');
    }
	ob_start();
?>
			var editor = new tinymce.Editor('editWindow', {
				// General options
				selector : "textarea#editWindow",
				// PHP port(Textcube-NG): TinyMCE 4.9.11→7.9.3+bd1ab5e 백포트(R-173). 'mode' 옵션은 6.0.0 에서 제거됨. 테마 silver·스킨 oxide 는 6.x 이후 기본값
				theme : 'silver',
				skin : 'oxide',
				license_key : 'gpl', // PHP port(Textcube-NG): TinyMCE 7 자체 호스팅 GPLv2+ 사용 표시(Textcube 원본 GPLv2+)
<?php
	// PHP port(Textcube-NG): r3-15 R-361 cache_suffix 에 TTMLsupport plugin.min.js 의 수정 시각(filemtime)을 붙인다(사용자 결정 11-11). r3-11 처럼 같은 경로의 TinyMCE 플러그인 파일을 고쳐도
	// 브라우저가 옛 캐시를 쓰지 않도록 함. 파일이 없거나 시각을 읽지 못하면 지금의 고정값('?v=7.9.3-bd1ab5e')만 씀
	$tinyMCECacheTime = is_file($pluginPath.'/tinymce/plugins/TTMLsupport/plugin.min.js') ? filemtime($pluginPath.'/tinymce/plugins/TTMLsupport/plugin.min.js') : false;
?>
				cache_suffix : '?v=7.9.3-bd1ab5e<?php echo ($tinyMCECacheTime ? '-'.(int)$tinyMCECacheTime : '');?>', // 같은 tinymce/ 경로에 덮어쓰므로 옛 4.9.11 파일과 브라우저 캐시가 섞이지 않도록
<?php
	// PHP port(Textcube-NG): TinyMCE 7 언어 파일 이름(ko_KR 등)에 맞춰 블로그 언어를 고정 목록으로만 바꿈(R-173). 목록 밖 값이면 언어를 지정하지 않음(영어 내장)
	$tinyMCELanguages = array('ko' => 'ko_KR', 'ja' => 'ja', 'vi' => 'vi', 'zh-CN' => 'zh_CN', 'zh-TW' => 'zh_TW');
	$tinyMCELanguage = $context->getProperty('blog.language');
	$tinyMCELanguage = (is_string($tinyMCELanguage) && isset($tinyMCELanguages[$tinyMCELanguage])) ? $tinyMCELanguages[$tinyMCELanguage] : null;
	if ($tinyMCELanguage !== null && file_exists($pluginPath.'/tinymce/langs/'.$tinyMCELanguage.'.js')) {
?>
				language : '<?php echo $tinyMCELanguage;?>',
<?php
	}
?>
				menubar: false,
				branding: false, // PHP port(Textcube-NG): 상태 표시줄 "Powered by Tiny" 표시 끔(TinyMCE 4.5+ 옵션)
				promotion: false, // PHP port(Textcube-NG): 6.x 이후 "Upgrade" 버튼 끔
				// PHP port(Textcube-NG): toolbar_location "external"·fixed_toolbar_container 는 6.x 이후 inline 모드 전용 → 편집기 위 기본 툴바(R-173 결정 d)
				toolbar_mode: 'wrap', // 4.x 처럼 버튼을 모두 보이게(6.x 기본값 floating)
				// PHP port(Textcube-NG): r3-10 R-284 원본(4.1.10)은 툴바를 편집기 밖 #formatbox-container(관리 화면 머리 아래 고정 띠)에 두고 override.css 의 .mce-toolbar-grp 를
				// position:fixed·top 80px(팝업 편집기 .popup-editor 는 45px)로 고정했다. 7.x 는 external 툴바가 inline 모드 전용이라, 편집기 머리(.tox-editor-header)를
				// override.css 에서 같은 자리에 position:fixed 로 둔다(사용자 결정 11-08). 머리가 처음부터 고정이므로 스크롤 때 붙이는 toolbar_sticky·toolbar_sticky_offset 은 쓰지 않는다
				// PHP port(Textcube-NG): r3-10 R-284 원본 4.1.10 autoresize 는 최소 높이(autoresize_min_height, 기본 textarea#editWindow 의 offsetHeight)를 편집 구역(iframe)에만
				// 적용했다. 7.x autoresize 는 min_height(silver 테마 기본 100)를 편집기 전체 높이에 적용해 편집 구역이 좁아졌다 → textarea 높이를 주고,
				// 아래 editor.on('init') 에서 편집기 전체와 편집 구역(iframe) 높이의 차를 더한다. textarea 높이를 읽지 못하면 7.x height 기본값과 같은 400
				min_height: (function () { var t = document.getElementById('editWindow'); return (t && t.offsetHeight > 0) ? t.offsetHeight : 400; })(),
				relative_urls: false,
				convert_urls: false,
				//schema: "html5",
        extended_valid_elements : "div[class|style|align|width|height|id|more|less],img[class|src|border|alt|title|hspace|vspace|width|height|align|onmouseover|onmouseout|name|longdesc|style],pre[*],code[*],object,br",

<?php
	if($config['editormode'] == 'simple') {
?>
				// PHP port(Textcube-NG): 공백 결합 문자열 배열 → 공백 구분 한 문자열(R-173 회귀, TinyMCE 6+ plugins 는 string 또는 이름별 string[]; 4.x 의 공백 결합 배열 요소는 나누지 않음)
				plugins: "TTMLsupport advlist link image lists anchor autoresize media visualblocks table directionality charmap codemirror",
				// PHP port(Textcube-NG): r3-10 R-284 툴바 배치(사용자 결정 11-08, 원본 4.x 의 toolbar1·toolbar2 순서와 다름, 버튼은 같음): 1줄 = 저장·인쇄·글자 모양 + 원래 2줄(실행취소·삽입 등), 2줄 = 문단 모양·글꼴·색·정렬·목록·들여쓰기
				toolbar1: "tcsave print | bold italic underline strikethrough | undo redo | tcattach image media charmap | hr link unlink anchor | table | removeformat | code visualblocks",
				toolbar2: "styles blocks fontfamily fontsize forecolor backcolor | alignleft aligncenter alignright alignjustify | bullist numlist | outdent indent blockquote hr tcmoreless",
<?php
	} else {
?>
				// PHP port(Textcube-NG): 공백 결합 문자열 배열 → 공백 구분 한 문자열(R-173 회귀, TinyMCE 6+ plugins 는 string 또는 이름별 string[]; 4.x 의 공백 결합 배열 요소는 나누지 않음)
				plugins: "TTMLsupport advlist link image lists charmap anchor pagebreak table searchreplace wordcount visualblocks visualchars insertdatetime media nonbreaking directionality emoticons autoresize codemirror",

				// PHP port(Textcube-NG): r3-10 R-284 툴바 배치(사용자 결정 11-08, 원본 4.x 의 toolbar1·toolbar2 순서와 다름, 버튼은 같음): 1줄 = 저장·인쇄·글자 모양 + 원래 2줄(실행취소·삽입 등), 2줄 = 문단 모양·글꼴·색·정렬·목록·들여쓰기
				toolbar1: "tcsave print | bold italic underline strikethrough | undo redo | searchreplace | tcattach image media charmap insertdatetime | subscript superscript ltr rtl | hr link unlink anchor | table | cut copy paste pastetext| removeformat | code visualblocks",
				toolbar2: "styles blocks fontfamily fontsize forecolor backcolor | alignleft aligncenter alignright alignjustify | bullist numlist | outdent indent blockquote hr tcmoreless",
<?php
	}
?>
				// codemirror settings
				codemirror: {
					indentOnInit: false, // Whether or not to indent code on init.
					path: 'CodeMirror', // Path to CodeMirror distribution
					config: {           // CodeMirror config object
    					mode: '<?php echo $config['formatter'];?>',
    					lineNumbers: true,
    					tabSize: 4,
    					indentWithTabs: true,
    					theme: '<?php echo $config['srctheme'] ?>'
					},
					jsFiles: <?php echo json_encode($config['codemirror_jsfiles']); ?>,
					cssFiles: [
						'theme/<?php echo $config['srctheme'] ?>.css'
					],
                    showMarkdownLineBreaks: true
				},
				// content CSS
<?php
	// PHP port(Textcube-NG): r3-11 편집 구역 스타일 설정 skinstyle(사용자 결정 11-08 B1·B2·16:4x, 원본에 없는 새 기능). index.xml 설정 화면의 세 값만 받고,
	// 없거나 목록 밖이면 none 이다(tinyMCE_handleconfig 도 같은 목록으로 막음).
	//  none(기본값, 편집기 기본 스타일) = 원본과 같은 줄(스킨 wysiwyg.css, 없으면 default-wysiwyg.css 하나)을 그대로 낸다. 본문 class·되돌림 CSS·CDN 없음.
	//  local = 블로그 화면용 CSS → 되돌림 CSS → wysiwyg.css(content_css)와 본문 자리 class(body_class). cdn = local 에 더해 integrity 있는 https CDN(아래 init 에서 iframe 에 넣음)
	$skinEditorStyle = (isset($config['skinstyle']) && is_string($config['skinstyle']) && in_array($config['skinstyle'], array('none', 'local', 'cdn'), true)) ? $config['skinstyle'] : 'none';
	if ($skinEditorStyle === 'none') {
?>
				content_css : "<?php echo (file_exists(__TEXTCUBE_SKIN_DIR__.'/'.$context->getProperty('skin.skin').'/wysiwyg.css') ? $context->getProperty('uri.service').'/skin/blog/'.$context->getProperty('skin.skin').'/wysiwyg.css' : $context->getProperty('uri.service').'/resources/style/default-wysiwyg.css');?>",
<?php
	} else {
		// getSkinEditorStyles()(library/model/blog.skin.php)가 목록을 돌려준다. JS 값은 json_encode(HEX 플래그)로만 낸다.
		// 함수가 없거나 인코딩에 실패하면 wysiwyg.css 하나(본문 class·CDN 없음)로 돌아간다. 이때 스킨 이름은 공용 함수와 같이
		// Skin 생성자 방식(customize/ 는 현재 블로그, 그 밖은 마지막 이름만, ''·'.'·'..' 은 버림)으로 정리하고 주소 조각은 rawurlencode 한다(사용자 결정 11-08 (f))
		$skinEditorFlags = JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_UNESCAPED_UNICODE | JSON_INVALID_UTF8_SUBSTITUTE;
		$skinEditorStyles = function_exists('getSkinEditorStyles') ? getSkinEditorStyles($context->getProperty('skin.skin'), $context->getProperty('uri.service'), $skinEditorStyle) : null;
		$skinEditorCSS = is_array($skinEditorStyles) ? jsonEncodeCompat($skinEditorStyles['css'], $skinEditorFlags) : false;	// Textcube-NG: JSON_INVALID_UTF8_SUBSTITUTE(7.2) 대신 jsonEncodeCompat()(00-Compat.php, r4-4)
		$skinEditorCDN = is_array($skinEditorStyles) ? jsonEncodeCompat($skinEditorStyles['cdn'], $skinEditorFlags) : false;
		$skinEditorClass = (is_array($skinEditorStyles) && is_string($skinEditorStyles['bodyClass']) && preg_match('/^[A-Za-z0-9_\- ]+$/D', $skinEditorStyles['bodyClass'])) ? jsonEncodeCompat($skinEditorStyles['bodyClass'], $skinEditorFlags) : false;
		// PHP port(Textcube-NG): r3-11 B2 글자 크기 맞춤 JS(아래 init)에 줄 값: 본문 자리를 감싼 요소들(bodyChain)과 잴 때 잠시 끌 편집기 전용 CSS(editorOnly = 되돌림 CSS·wysiwyg.css, skin.html 이 실은 스킨 wysiwyg.css 는 뺌).
		// 감싼 요소가 없거나 인코딩에 실패하면 false(맞추지 않음, 사용자 결정 11-08 18:4x)
		$skinEditorFit = (is_array($skinEditorStyles) && !empty($skinEditorStyles['bodyChain']) && !empty($skinEditorStyles['editorOnly'])) ? jsonEncodeCompat(array('chain' => $skinEditorStyles['bodyChain'], 'off' => $skinEditorStyles['editorOnly']), $skinEditorFlags) : false;
		// PHP port(Textcube-NG): r3-12 편집기 전용 CSS 생성기(library/model/blog.skin.php getSkinEditorGeneratedCSS)가 지문이 맞는 결과를 주면 그것을 쓴다(사용자 결정 11-08 "생성기 = r3-12").
		// 이때 content_css 는 로컬 CSS 를 뺀 목록(되돌림 CSS·wysiwyg.css)이고, 만든 CSS 는 init 뒤 <style> 로 로컬 CSS 가 있던 자리(그 목록 첫 링크 앞)에 넣으며(아래 JS),
		// body class·r3-11 B2 글자 크기 맞춤 JS 는 쓰지 않는다. 결과가 없거나(CDN 항목·지문 다름·만들지 못함·10초 안 다시 만들기) 인코딩에 실패하면 r3-11 B2 그대로
		$skinEditorGen = false;
		if (is_array($skinEditorStyles) && function_exists('getSkinEditorGeneratedCSS')) {
			$skinEditorGenCSS = getSkinEditorGeneratedCSS(getBlogId(), $context->getProperty('skin.skin'), $context->getProperty('uri.service'), $skinEditorStyles);
			if (is_array($skinEditorGenCSS)) {
				$skinEditorGenList = jsonEncodeCompat($skinEditorGenCSS['css'], $skinEditorFlags);
				$skinEditorGenJS = jsonEncodeCompat(array('style' => $skinEditorGenCSS['style'], 'before' => $skinEditorGenCSS['before']), $skinEditorFlags);
				if ($skinEditorGenList !== false && $skinEditorGenJS !== false) {
					$skinEditorCSS = $skinEditorGenList;
					$skinEditorGen = $skinEditorGenJS;
					$skinEditorClass = false;
					$skinEditorFit = false;
				}
			}
		}
		if ($skinEditorCSS === false || $skinEditorCDN === false) {
			$skinEditorName = $context->getProperty('skin.skin');
			$skinEditorName = is_string($skinEditorName) ? ((strncmp($skinEditorName, 'customize/', 10) == 0) ? 'customize/' . getBlogId() : Path::getBaseName($skinEditorName)) : '';
			$skinEditorCSS = $context->getProperty('uri.service') . '/resources/style/default-wysiwyg.css';
			if ($skinEditorName !== '' && $skinEditorName !== '.' && $skinEditorName !== '..' && file_exists(__TEXTCUBE_SKIN_DIR__ . '/' . $skinEditorName . '/wysiwyg.css'))
				$skinEditorCSS = $context->getProperty('uri.service') . '/skin/blog/' . implode('/', array_map('rawurlencode', explode('/', $skinEditorName))) . '/wysiwyg.css';
			$skinEditorCSS = jsonEncodeCompat($skinEditorCSS, $skinEditorFlags);
			$skinEditorCDN = '[]';
			$skinEditorClass = false;
			$skinEditorFit = false;
			$skinEditorGen = false;	// PHP port(Textcube-NG): r3-12 되돌릴 때는 생성기 CSS 도 쓰지 않음
		}
?>
				content_css : <?php echo $skinEditorCSS;?>,
<?php if ($skinEditorClass !== false) { ?>
				body_class : <?php echo $skinEditorClass;?>,
<?php } ?>
<?php
	}
?>

				// Style formats
				style_formats: [
				    {title: 'Headers', items: [
				        {title: 'h1', block: 'h1'},
				        {title: 'h2', block: 'h2'},
				        {title: 'h3', block: 'h3'},
				        {title: 'h4', block: 'h4'},
				        {title: 'h5', block: 'h5'},
				        {title: 'h6', block: 'h6'}
				    ]},

				    {title: 'Blocks', items: [
				        {title: 'p', block: 'p'},
				        {title: 'div', block: 'div'},
				        {title: 'pre', block: 'pre'}
				    ]},

				    {title: 'Containers', items: [
				        {title: 'section', block: 'section', wrapper: true, merge_siblings: false},
				        {title: 'article', block: 'article', wrapper: true, merge_siblings: false},
				        {title: 'blockquote', block: 'blockquote', wrapper: true},
				        {title: 'hgroup', block: 'hgroup', wrapper: true},
				        {title: 'aside', block: 'aside', wrapper: true},
				        {title: 'figure', block: 'figure', wrapper: true}
				    ]}
				],
				font_size_formats: "8pt 9pt 10pt 11pt 12pt 14pt 18pt 24pt 36pt", // PHP port(Textcube-NG): 6.0.0 이름 변경(fontsize_formats)
<?php /* PHP port(Textcube-NG): TinyMCE 6.0.0 에서 forced_root_block:false 가 없어짐 → BR 모드는 newline_behavior:'linebreak'(6.1+)로 대신함(R-173 결정 c). apply_source_formatting 은 6.x 이후 옵션이 아님 */ ?>
<?php if ($config['formatter'] == 'markdown') { ?>
				forced_root_block : 'p',
				newline_behavior : 'linebreak',
<?php } else if ($config['paragraphdelim'] == 'P') { ?>
				forced_root_block : 'p',
<?php } else { ?>
                forced_root_block : 'p',
                newline_behavior : 'linebreak',
<?php
	}
?>
<?php /* Textcube-NG: skin.contentWidth 는 DB 에 contentWidth 행이 없으면(설치·새 블로그 직후) NULL 이라 폭이 40 이 됨 → ownerView.php skinContentWidth 와 같은 Misc::getContentWidth() 사용. PHP 8 에서 숫자가 아닌 문자열 + int 는 TypeError 이므로 floatval() */ ?>
<?php /* PHP port(Textcube-NG): r3-15 R-357 mobile 관리 스킨(library/preprocessor.php 가 $adminSkinSetting['skin'] 을 '/skin/admin/mobile' 로 둠, tinyMCE_adminheader 와 같은 검사)에서는 폭 설정과 관계없이 화면 폭("100%"). 스킨 폭(+40)이면 좁은 화면에서 툴바가 화면 밖으로 나감 */ ?>
				width : <?php echo (($config['width'] == 'full' || (isset($adminSkinSetting['skin']) && $adminSkinSetting['skin'] === '/skin/admin/mobile')) ? '"100%"' : floatval(Misc::getContentWidth())+40);?>
			}, tinymce.EditorManager);
			editor.initialize = function() {
<?php if ($config['formatter'] == 'markdown') {
?>
			editor.on('postRender', function (e) { editor.plugins.codemirror.showSourceEditorFrame(); });
			editor.on('BeforeSetContent', function(e) {
				if (e.initial) {
					e.content = e.content.replace(/\r?\n/g, '<br />');
				}
			});
<?php
			}
?>
				this.render();
			};
			editor.addObject = function(data) {
				this.plugins.TTMLsupport.addObject(data);
			};
			editor.command = function(command, value1, value2) {
				this.plugins.TTMLsupport.command(command,value1,value2);
			};
			editor.finalize = function() {
                this.destroy();
            };
            editor.syncTextarea = function(){
                if (this.doesCodeMirrorEditorEnabled == true) {
                    this.plugins.codemirror.syncToTinyMCE();
                }
                this.save();
				if (entryManager.nowsaving == true && editor.tcformatter == 'markdown') {
                    var htmlcontent = document.getElementById('editWindow').value;
					htmlcontent = htmlcontent.replace(new RegExp("<br />", "gi"), "\r\n");
                    document.getElementById('editWindow').value = htmlcontent;
                }
			};
			editor.syncEditorWindow = function() {
				this.load();
			};
			editor.on('keyup',editorChanged);
			editor.on('mousedown',editorChanged);
			// PHP port(Textcube-NG): r3-10 R-284 min_height 에 편집기 전체와 편집 구역(iframe) 높이의 차를 더해, 원본 4.1.10 autoresize 처럼 iframe 이 textarea 높이 이상이 되게 한다.
			// 툴바 머리는 override.css 에서 position:fixed 라 이 차에 들지 않는다(상태 표시줄과 .tox-edit-area 의 여백·테두리만 남음). iframe 이 없으면 편집 구역 틀로 잰다
			// (7.x autoresize 는 호출 때마다 min_height 를 다시 읽어 전체 높이 = max(min_height, 본문 + 툴바 등)). render() 전에 등록하므로 autoresize 의 init 보다 먼저 돈다
			// PHP port(Textcube-NG): r3-10 R-284 보완(사용자 결정 11-08) mobile 관리 스킨에서는 툴바 머리가 흐름대로 놓여 이 차에 들지만 init 때는 높이가 0 이고
			// 배치 뒤에 생겨 편집 구역을 깎으므로, 처음 min_height(textarea 높이)를 base 로 두고 머리 높이가 바뀔 때(ResizeObserver) 차를 다시 잰다.
			// canon 은 머리 높이가 그대로라 다시 재도 min_height 가 같아 mceAutoResize 를 다시 부르지 않는다
			editor.on('init', function () {
				var area = editor.iframeElement || editor.getContentAreaContainer();
				var base = editor.options.get('min_height');
				var fit = function () {
					if (editor.removed || editor.doesCodeMirrorEditorEnabled || area.offsetHeight === 0) return;
					var chrome = editor.getContainer().offsetHeight - area.offsetHeight;
					if (chrome > 0 && base + chrome !== editor.options.get('min_height')) { editor.options.set('min_height', base + chrome); editor.execCommand('mceAutoResize'); }
				};
				// PHP port(Textcube-NG): r3-18 R-356 codemirror(마크다운) 화면은 편집 구역을 숨겨 offsetHeight 가 0 이라, fit() 가 컨테이너 전체를 chrome 으로 잡아 min_height 가 커졌다.
				// codemirror 사용 중이거나 편집 구역이 숨겨졌으면 계산하지 않고, autoresize 가 정한 컨테이너 높이를 비워 원본 4.x 처럼 헤더 높이로 줄어들게 한다.
				editor.on('ResizeEditor', function () {
					if (editor.doesCodeMirrorEditorEnabled) { editor.getContainer().style.height = ''; }
				});
				fit();
				var header = editor.getContainer().querySelector('.tox-editor-header');
				if (header && typeof window.ResizeObserver === 'function') {
					var ro = new window.ResizeObserver(fit);
					ro.observe(header);
					editor.on('remove', function () { ro.disconnect(); });
				}
			});
			editor.on('init',editorInitialized); // PHP port(Textcube-NG): 초기화 뒤 다시 직렬화한 본문을 저장된 값으로 봄(pat.im 1249, v3.137)
<?php if ($skinEditorStyle !== 'none' && $skinEditorGen !== false) { ?>
			// PHP port(Textcube-NG): r3-12 편집기 전용 CSS 생성기가 만든 CSS(블로그 화면용 로컬 CSS·skin.html <style> 의 선택자를 편집기 본문에 맞게 바꾼 것)를 편집기 iframe <head> 에 <style> 로 넣는다
			// (사용자 결정 11-08 "생성기 = r3-12", 원본에 없는 새 기능). textContent 로만 넣고(innerHTML 안 씀), 로컬 CSS 가 있던 자리(content_css 첫 링크 = 되돌림 CSS 앞)에 둔다.
			// 그 링크를 못 찾으면 <head> 끝에 둔다. 편집 구역 본문·저장되는 글은 바꾸지 않는다
			editor.on('init', function () {
				var gen = <?php echo $skinEditorGen;?>, doc = editor.getDoc(), links, el, at = null, before, j;
				if (!doc || !doc.head || !gen || typeof gen.style !== 'string') return;
				el = doc.createElement('style');
				el.setAttribute('data-textcube', 'skin-editor-css');
				el.textContent = gen.style;
				if (typeof gen.before === 'string') {
					try { before = new URL(gen.before, doc.baseURI).href.split('?')[0]; } catch (e) { before = null; }
					links = doc.head.getElementsByTagName('link');
					for (j = 0; before && j < links.length; j++) if (links[j].href.split('?')[0] === before) { at = links[j]; break; }
				}
				doc.head.insertBefore(el, at);
				if (editor.plugins && editor.plugins.autoresize) { try { editor.execCommand('mceAutoResize'); } catch (e) {} }
			});
<?php } ?>
<?php if ($skinEditorStyle === 'cdn') { ?>
			// PHP port(Textcube-NG): r3-11 skinstyle 이 cdn 일 때만, skin.html 의 https CDN 스타일(integrity 있는 것만)을 편집기 iframe <head> 에 integrity·crossorigin 과 함께 직접 넣는다(사용자 결정 11-08 B2·16:4x).
			// content_css 로는 integrity 를 줄 수 없으므로 init 뒤 DOM 으로 만든다(innerHTML 안 씀). skin.html 순서를 지키도록 그 다음 로컬 CSS(before) 링크 앞에 넣고, 못 찾으면 <head> 끝에 넣는다
			editor.on('init', function () {
				var list = <?php echo $skinEditorCDN;?>, doc = editor.getDoc(), links, item, el, at, before, i, j;
				if (!doc || !doc.head || !list || typeof list.length !== 'number') return;
				links = doc.head.getElementsByTagName('link');
				for (i = 0; i < list.length; i++) {
					item = list[i];
					if (!item || typeof item.href !== 'string' || !/^https:\/\//.test(item.href) || typeof item.integrity !== 'string' || item.integrity === '') continue;
					el = doc.createElement('link');
					el.setAttribute('rel', 'stylesheet');
					el.setAttribute('integrity', item.integrity);
					if (item.crossorigin === 'anonymous' || item.crossorigin === 'use-credentials') el.setAttribute('crossorigin', item.crossorigin);
					el.setAttribute('href', item.href);
					at = null;
					if (typeof item.before === 'string') {
						try { before = new URL(item.before, doc.baseURI).href.split('?')[0]; } catch (e) { before = null; }
						for (j = 0; before && j < links.length; j++) if (links[j].href.split('?')[0] === before) { at = links[j]; break; }
					}
					doc.head.insertBefore(el, at);
				}
			});
<?php } ?>
<?php if ($skinEditorStyle !== 'none' && $skinEditorFit !== false) { ?>
			// PHP port(Textcube-NG): r3-11 skinstyle 이 local·cdn 일 때 편집 구역 글자 크기를 블로그 화면에 맞춘다(사용자 결정 11-08 18:4x "편집기 안에서 재서 맞춤", 원본에 없는 새 기능).
			// 편집기는 본문 자리를 감싼 요소들의 class 를 body 한 곳에 붙이고 되돌림 CSS 가 html 글자 크기를 100% 로 두므로, 바깥 요소의 em·% 글자 크기가 블로그 화면
			// (예: periwinkle html 62.5% → body 14px → div.container 0.938em = 13.13px)과 달라진다. 모든 content CSS 가 실린 뒤(TinyMCE 는 content_css 를 다 읽은 뒤 init 을 냄.
			// cdn 이면 위에서 넣은 CDN 링크의 load·error 까지 기다리고, 10초가 지나면 그대로 잰다) 한 번 돈다.
			//  1) 편집기 전용 CSS(서버가 준 off: 되돌림 CSS·wysiwyg.css. skin.html 이 블로그 화면용으로 실은 스킨 wysiwyg.css 는 서버가 빼므로 켠 채로 둠)를 잠시 끄고, 편집 문서 html 아래에 보이지 않는 body 요소를 따로 만들어 그 안에 감싼 요소들
			//     (서버가 준 [tag, class], 바깥 → 안쪽)을 겹겹이 넣어 가장 안쪽 요소의 글자 크기(블로그 화면에서 본문이 받는 크기)를 잰 뒤, 지우고 다시 켠다.
			//  2) 편집 문서 html 에 inline font-size(!important. 되돌림 CSS 의 100%!important 보다 셈)를 10px·20px 로 주어 편집 구역 body 크기가 바뀌는 비율을 구하고,
			//     body 크기가 1) 의 값이 되는 html 크기를 준 뒤 다시 재어 0.1px 안에서 맞을 때만 둔다.
			// 잰 값이 숫자가 아니거나 0 이하이거나 72px(편집기 글자 크기 목록의 가장 큰 값 36pt = 48px 의 1.5배)를 넘으면, 끌 CSS 를 다 찾지 못하면, body 크기가 html 크기를 따르지
			// 않으면(px 고정) html style 을 원래대로 되돌려 되돌림 CSS 의 100% 상태로 둔다. 예외는 잡아서 편집을 막지 않는다. 편집 구역 본문·body 의 class·저장되는 글은 바꾸지 않는다
			// (바꾸는 것은 html 요소의 style 뿐이며 TinyMCE 는 body 안만 저장한다). 한계: rem(html 글자 크기 기준)으로 정한 글자 크기, #id·속성·가상 클래스로만 걸리는 글자 크기는 맞추지 못한다
			editor.on('init', function () {
				var fit = <?php echo $skinEditorFit;?>, doc = editor.getDoc(), win = doc ? doc.defaultView : null, max = 72, waits = 0, done = false, timer = null, links, i;
				if (!doc || !win || !doc.head || !doc.documentElement || !fit || typeof fit !== 'object' || !fit.chain || typeof fit.chain.length !== 'number'
					|| fit.chain.length < 1 || fit.chain.length > 64 || !fit.off || typeof fit.off.length !== 'number' || fit.off.length < 1) return;
				function size(el) {
					var v = parseFloat(win.getComputedStyle(el).fontSize);
					return (isFinite(v) && v > 0 && v <= max) ? v : NaN;
				}
				function run() {
					var root = doc.documentElement, body = editor.getBody(), hadStyle, oldValue, oldPriority, off = [], sheets = [], probe = null, parent, el, item, target, b1, b2, a, h, ok = false, j;
					if (done) return;
					done = true;
					if (timer) clearTimeout(timer);
					if (editor.removed || !body) return;
					hadStyle = root.hasAttribute('style');
					oldValue = root.style.getPropertyValue('font-size');
					oldPriority = root.style.getPropertyPriority('font-size');
					try {
						for (j = 0; j < fit.off.length; j++) {
							if (typeof fit.off[j] !== 'string') return;
							off.push(new URL(fit.off[j], doc.baseURI).href.split('?')[0]);
						}
						for (j = 0; j < links.length; j++) {
							if (links[j].sheet && !links[j].sheet.disabled && off.indexOf(links[j].href.split('?')[0]) !== -1) {
								links[j].sheet.disabled = true;
								sheets.push(links[j].sheet);
							}
						}
						if (sheets.length !== off.length) return;
						probe = doc.createElement('body');
						probe.style.setProperty('display', 'none', 'important');
						root.appendChild(probe);
						parent = probe;
						for (j = 0; j < fit.chain.length; j++) {
							item = fit.chain[j];
							if (!item || typeof item.tag !== 'string' || !/^[a-z][a-z0-9]{0,9}$/.test(item.tag) || typeof item['class'] !== 'string' || !/^[A-Za-z0-9_ -]*$/.test(item['class'])) return;
							el = doc.createElement(item.tag);
							if (item['class'] !== '') el.className = item['class'];
							parent.appendChild(el);
							parent = el;
						}
						target = size(parent);
						root.removeChild(probe);
						probe = null;
						for (j = 0; j < sheets.length; j++) sheets[j].disabled = false;
						sheets = [];
						if (!(target > 0)) return;
						root.style.setProperty('font-size', '10px', 'important');
						b1 = size(body);
						root.style.setProperty('font-size', '20px', 'important');
						b2 = size(body);
						a = (b2 - b1) / 10;
						if (!(a > 0.01)) return;
						h = 10 + (target - b1) / a;
						if (!(isFinite(h) && h > 0 && h <= max)) return;
						root.style.setProperty('font-size', h.toFixed(3) + 'px', 'important');
						ok = Math.abs(size(body) - target) <= 0.1;
					} catch (e) {
						ok = false;
					} finally {
						if (probe && probe.parentNode) probe.parentNode.removeChild(probe);
						for (j = 0; j < sheets.length; j++) sheets[j].disabled = false;
						if (!ok) {
							root.style.removeProperty('font-size');
							if (oldValue) root.style.setProperty('font-size', oldValue, oldPriority);
							if (!hadStyle && root.getAttribute('style') === '') root.removeAttribute('style');
						}
					}
					if (ok && editor.plugins && editor.plugins.autoresize) { try { editor.execCommand('mceAutoResize'); } catch (e) {} }	// 글자 크기가 바뀌면 높이를 다시 맞춘다
				}
				links = doc.head.getElementsByTagName('link');
				for (i = 0; i < links.length; i++) {
					if (links[i].hasAttribute('integrity') && !links[i].sheet) {
						waits++;
						links[i].addEventListener('load', function () { if (--waits <= 0) run(); });
						links[i].addEventListener('error', function () { if (--waits <= 0) run(); });
					}
				}
				if (waits > 0) timer = setTimeout(run, 10000);
				else run();
			});
<?php } ?>
			editor.propertyFilePath = "<?php echo $context->getProperty('uri.service');?>/attach/<?php echo $context->getProperty('blog.id');?>/";
            editor.tcformatter = '<?php echo $config['formatter'];?>';
			editor.fixPosition = <?php echo (int)Setting::getBlogSettingGlobal('editorPropertyPositionFix', 0);?>;
			return editor;
<?php
	$result = ob_get_contents();
	ob_end_clean();
	return $result;
}

function tinyMCE_generateTranslationJavascript($messages) {
	// The language setting follows in which context this function is called.
	$table = array();
	foreach ($messages as $text) {
		$table[$text] = _t($text);
	}
	$json = jsonEncodeCompat($table, JSON_FORCE_OBJECT | JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_UNESCAPED_UNICODE);
	if ($json === false) {
		$json = '{}';
	}
	return "<script type=\"text/javascript\">\n(function(t){var own=function(o,k){return Object.prototype.hasOwnProperty.call(o,k);};var d=window.__text||(window.__text={});for(var k in t){if(own(t,k)&&!own(d,k))d[k]=t[k];}if(typeof window._t!=='function'){window._t=function(m){var x=window.__text;return(x&&own(x,m)&&x[m])||m;};}})(" . $json . ");\n</script>\n";
}

// PHP port(Textcube-NG): r3-11 TTMLsupport 의 Textcube 전용 버튼(tcattach·tcsave·tcmoreless) 툴팁 번역표(사용자 결정 11-08 16:52, 원본에 없는 새 기능. 원본 4.x 는 영어 title 고정).
// 키는 tinymce/plugins/TTMLsupport/plugin.js 의 영어 원문, 값은 이 플러그인 locale 의 한국어 키를 _t() 로 옮긴 것이다(fireEvent 가 고른 언어, tinyMCE_generateTranslationJavascript 와 같음).
// 읽은 locale 이 ko 이면 한국어 키 그대로 쓰고, 그 밖의 언어에서 번역이 없으면(값이 키와 같거나 빔) 넣지 않아 plugin.js 가 영어 원문을 쓴다.
// 이 플러그인 locale 이 없는 언어(fireEvent 가 domain 을 바꾸지 않음)도 넣지 않는다. 값은 json_encode(HEX 플래그)로만 낸다
function tinyMCE_generateButtonTranslationJavascript() {
	$buttons = array(
		'Upload and insert files' => '파일 올리기·삽입',
		'Save current post' => '글 저장',
		'Add more/less block' => '더보기/접기 구역 추가'
	);
	$locale = Locales::getInstance();
	$language = (isset($locale->domain) && $locale->domain === 'ED_tinyMCE' && isset($locale->locale['ED_tinyMCE']) && is_string($locale->locale['ED_tinyMCE'])) ? $locale->locale['ED_tinyMCE'] : null;
	$table = array();
	if ($language !== null) {
		foreach ($buttons as $english => $korean) {
			$text = _t($korean);
			if (!is_string($text) || $text === '' || ($text === $korean && $language !== 'ko'))
				continue;
			$table[$english] = $text;
		}
	}
	$json = jsonEncodeCompat($table, JSON_FORCE_OBJECT | JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_UNESCAPED_UNICODE);
	if ($json === false) {
		$json = '{}';
	}
	return "<script type=\"text/javascript\">\nwindow.tcTinyMCEButtonText = " . $json . ";\n</script>\n";
}

function tinyMCE_adminheader($target, $mother) {
	global $suri, $pluginURL, $pluginPath, $adminSkinSetting;
    $context = Model_Context::getInstance();
	if ($context->getProperty('editor.key') == 'tinyMCE') {
		if ($context->getProperty('suri.directive') == '/owner/entry/post' || $suri['directive'] == '/owner/entry/edit') {
			// PHP port(Textcube-NG): TinyMCE 4.9.11→7.9.3+bd1ab5e 백포트(R-173) — 같은 tinymce/ 폴더에 덮어씀. 캐시된 4.9.11 코어와 섞이지 않도록 버전 쿼리
			$target .= "\t<script type=\"text/javascript\" src=\"$pluginURL/tinymce/tinymce.min.js?v=7.9.3-bd1ab5e\"></script>\n";
			// PHP port(Textcube-NG): r3-15 R-354 override.css 에 수정 시각(filemtime) 꼬리를 붙여 바뀐 override.css 를 바로 받게 함(사용자 결정 11-11). 파일이 없거나 시각을 읽지 못하면 지금처럼 꼬리 없음
			$tinyMCEOverrideTime = is_file($pluginPath.'/override.css') ? filemtime($pluginPath.'/override.css') : false;
			$target .= "\t<link rel=\"stylesheet\" type=\"text/css\" media=\"screen\" href=\"$pluginURL/override.css" . ($tinyMCEOverrideTime ? '?v='.(int)$tinyMCEOverrideTime : '') . "\" />\n";
			// PHP port(Textcube-NG): r3-10 R-284 보완(사용자 결정 11-08) mobile 관리 스킨(library/preprocessor.php 의 Utils_Browser::isMobile())에는 canon 의 머리 띠·옆 메뉴가 없어
			// override.css 의 툴바 머리 고정 좌표(top 80px·left 175px, 원본 .mce-toolbar-grp 와 같은 값)와 r3-10 의 #formatbox-container 높이 58px·#category-section 위 여백 87px 이
			// 맞지 않음(툴바가 제목·분류를 덮고 빈 틈이 생김) → 툴바 머리를 편집기 틀 안 흐름대로 두고, 두 값을 skin/admin/mobile/editor.css 값(높이 지정 없음·위 여백 3px)으로 되돌림
			if (isset($adminSkinSetting['skin']) && $adminSkinSetting['skin'] === '/skin/admin/mobile') {
				$target .= "\t<style type=\"text/css\">.tox.tox-tinymce .tox-editor-header,.popup-editor .tox.tox-tinymce .tox-editor-header{position:static!important;top:auto!important;left:auto!important;right:auto!important}#formatbox-container,.popup-editor #formatbox-container{height:auto!important}#category-section,.popup-editor #category-section{margin-top:3px!important}</style>\n";
			}
			$target .= tinyMCE_generateTranslationJavascript(array(
				'Embed',
				'Gallery',
				'Image',
				'Jukebox',
				'More/Less',
				'Object',
				'Object 1',
				'iMazing',
				'가로',
				'기본',
				'내비게이션',
				'높이',
				'대체 텍스트',
				'두번째 오브젝트',
				'두번째 이미지',
				'사이드바',
				'삽입하기',
				'선택한 항목을 아래로 이동합니다.',
				'선택한 항목을 위로 이동합니다.',
				'설정',
				'세로',
				'세번째 오브젝트',
				'세번째 이미지',
				'슬라이드쇼 간격',
				'슬라이딩',
				'심플',
				'아래로',
				'오브젝트 삽입',
				'위로',
				'위치 고정',
				'유형',
				'자동재생',
				'자막',
				'장면전환효과',
				'정렬방법',
				'제목',
				'주소입력',
				'첫번째 오브젝트',
				'첫번째 이미지',
				'최대너비',
				'최대높이',
				'취소하기',
				'코드',
				'코드 붙여넣기',
				'테두리',
				'테두리 없음',
				'투명전환',
				'파일',
				'파일 주소',
				'파일명',
				'파일명(수정불가)',
				'폭',
				'플래쉬',
				'플레이어 보이기',
				'화면당 이미지 수',
				'효과없음',
			));
				// PHP port(Textcube-NG): r3-11 Textcube 전용 버튼 툴팁 번역표(window.tcTinyMCEButtonText, TTMLsupport/plugin.js 가 읽음, 사용자 결정 11-08 16:52)
				$target .= tinyMCE_generateButtonTranslationJavascript();
		}
	}
	return $target;
}
?>
