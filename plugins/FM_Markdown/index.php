<?php
// Markdown formatter for Textcube 1.10
// By Jeongkyu Shin. (inureyes@gmail.com)

if(!function_exists('Markdown')) require_once 'markdown.php';

function FM_Markdown_format($blogid, $id, $content, $keywords = array(), $useAbsolutePath = true, $bRssMode = false) {
	$context = Model_Context::getInstance();
	$path = __TEXTCUBE_ATTACH_DIR__."/$blogid";
	$url = $context->getProperty("service.path")."/attach/$blogid";
	if(!function_exists('FM_TTML_bindAttachments')) { // To reduce the amount of loading code!
		require_once 'ttml.php';
	}
	$view = FM_TTML_bindAttachments($id, $path, $url, $content, $useAbsolutePath, $bRssMode);
	$view = FM_TTML_preserve_TTML_type_tags($view);
	$view = Markdown($view, $id);
	$view = FM_TTML_restore_TTML_type_tags($view);
	$view = FM_TTML_bindTags($id, $view);
	return $view;
}

function FM_Markdown_summary($blogid, $id, $content, $keywords = array(), $useAbsolutePath = true) {
	$context = Model_Context::getInstance();
	$view = FM_Markdown_format($blogid, $id, $content, $keywords, $useAbsolutePath, true);
    if (!$context->getProperty("blog.publishWholeOnRSS")) $view = UTF8::lessen(removeAllTags(stripHTML($view)), 255);
	return $view;
}

// 플러그인 설정 탭에 Markdown 에디터 조작 방법 및 문법 사용 방법을 표시하기 위한 manifestHandler.
// handleConfig() 가 이 함수를 호출하고, 반환된 XML 을 파싱해 설정 폼으로 렌더링한다.
// field caption 의 .value 는 raw HTML 로 출력되므로 CDATA 내 HTML 태그 사용 가능.
// title="항목" + value="..." 으로 입력칸에 섹션 요약을 표시, 중요 항목만 caption 내 <b> 태그 적용.
function FM_Markdown_ConfigHandler($plugin) {
	return '<?xml version="1.0" encoding="utf-8"?>'
		. '<config>'

		. '<fieldset legend="' . htmlspecialchars(_t('에디터 조작'), ENT_QUOTES, 'UTF-8') . '">'
		. '<field type="text" name="_md_help_editor" title="' . htmlspecialchars(_t('항목'), ENT_QUOTES, 'UTF-8') . '" value="' . htmlspecialchars(_t('포맷 선택 · 입력 방식 · 툴바 주의 · 첨부 파일 · 미리보기'), ENT_QUOTES, 'UTF-8') . '" size="52">'
		. '<caption><![CDATA['
		. htmlspecialchars(_t('포맷 선택: 글쓰기 하단 포맷 드롭다운 → Markdown'), ENT_QUOTES, 'UTF-8') . '<br>'
		. htmlspecialchars(_t('입력 방식: 순수 텍스트 직접 입력 (WYSIWYG 없음)'), ENT_QUOTES, 'UTF-8') . '<br>'
		. '<b>⚠ ' . htmlspecialchars(_f('툴바 Bold/Italic 버튼 → HTML 태그(%1) 삽입 — 사용 금지', '<strong>/<em>'), ENT_QUOTES, 'UTF-8') . '</b><br>'
		. htmlspecialchars(_t('첨부: 정렬 버튼 클릭 → img 삽입'), ENT_QUOTES, 'UTF-8') . ' &nbsp;/&nbsp; ' . htmlspecialchars(_t('직접:'), ENT_QUOTES, 'UTF-8') . ' [##_ATTACH_PATH_##]/파일명<br>'
		. htmlspecialchars(_t('미리보기: 임시저장 후 미리보기 버튼 이용'), ENT_QUOTES, 'UTF-8')
		. ']]></caption>'
		. '</field>'
		. '</fieldset>'

		. '<fieldset legend="' . htmlspecialchars(_t('기본 문법'), ENT_QUOTES, 'UTF-8') . '">'
		. '<field type="text" name="_md_help_basic" title="' . htmlspecialchars(_t('항목'), ENT_QUOTES, 'UTF-8') . '" value="' . htmlspecialchars(_t('제목 · 강조 · 링크/이미지 · 목록 · 인용 · 코드 · 수평선'), ENT_QUOTES, 'UTF-8') . '" size="52">'
		. '<caption><![CDATA['
		. '# H1 &nbsp; ## H2 &nbsp; ### H3 &nbsp; #### H4 &nbsp;&nbsp; (' . htmlspecialchars(_t('제목'), ENT_QUOTES, 'UTF-8') . ')<br>'
		. '**굵게** &nbsp; *기울임* &nbsp; ~~취소선~~<br>'
		. '[링크텍스트](URL) &nbsp;/&nbsp; ![대체텍스트](URL) &nbsp;&nbsp; (' . htmlspecialchars(_t('링크 / 이미지'), ENT_QUOTES, 'UTF-8') . ')<br>'
		. '- ' . htmlspecialchars(_t('항목'), ENT_QUOTES, 'UTF-8') . ' ' . htmlspecialchars(_t('또는'), ENT_QUOTES, 'UTF-8') . ' * ' . htmlspecialchars(_t('항목'), ENT_QUOTES, 'UTF-8') . ' (' . htmlspecialchars(_t('비순서 목록'), ENT_QUOTES, 'UTF-8') . ') &nbsp;/&nbsp; 1. ' . htmlspecialchars(_t('항목'), ENT_QUOTES, 'UTF-8') . ' (' . htmlspecialchars(_t('순서 목록'), ENT_QUOTES, 'UTF-8') . ')<br>'	// Textcube-NG: '항목'을 플러그인 locale 의 _t 키로 출력한다(v3.189)
		. htmlspecialchars(_t('하위 목록: 4칸 들여쓰기'), ENT_QUOTES, 'UTF-8') . '<br>'
		. '&gt; 인용 &nbsp;/&nbsp; &gt;&gt; 중첩 인용<br>'
		. '`인라인 코드` &nbsp;/&nbsp; ' . htmlspecialchars(_t('코드 블록: 4칸 들여쓰기'), ENT_QUOTES, 'UTF-8') . '<br>'
		. '--- ' . htmlspecialchars(_t('또는'), ENT_QUOTES, 'UTF-8') . ' *** &nbsp;&nbsp; (' . htmlspecialchars(_t('수평선, 단독 줄'), ENT_QUOTES, 'UTF-8') . ')'
		. ']]></caption>'
		. '</field>'
		. '</fieldset>'

		. '<fieldset legend="Markdown Extra">'
		. '<field type="text" name="_md_help_extra" title="' . htmlspecialchars(_t('항목'), ENT_QUOTES, 'UTF-8') . '" value="' . htmlspecialchars(_t('표 · 각주 · 정의 목록 · 펜스 코드 블록 · 약어'), ENT_QUOTES, 'UTF-8') . '" size="52">'
		. '<caption><![CDATA['
		. '<b>' . htmlspecialchars(_t('표:'), ENT_QUOTES, 'UTF-8') . '</b> | 헤더1 | 헤더2 | &nbsp;/&nbsp; ' . htmlspecialchars(_t('구분자:'), ENT_QUOTES, 'UTF-8') . ' | :--- | ---: | &nbsp;/&nbsp; | 값1 | 값2 |<br>'
		. '<b>' . htmlspecialchars(_t('각주:'), ENT_QUOTES, 'UTF-8') . '</b> 본문[^id] &nbsp;/&nbsp; [^id]: 내용 (' . htmlspecialchars(_t('별도 줄'), ENT_QUOTES, 'UTF-8') . ')<br>'
		. '<b>' . htmlspecialchars(_t('정의 목록:'), ENT_QUOTES, 'UTF-8') . '</b> ' . htmlspecialchars(_t('단어 → 바로 다음 줄 : 정의 (빈 줄 없이)'), ENT_QUOTES, 'UTF-8') . '<br>'
		. '<b>' . htmlspecialchars(_t('펜스 코드:'), ENT_QUOTES, 'UTF-8') . '</b> ``` 언어명 / 코드 / ```<br>'
		. '<b>' . htmlspecialchars(_t('약어:'), ENT_QUOTES, 'UTF-8') . '</b> *[HTML]: HyperText Markup Language'
		. ']]></caption>'
		. '</field>'
		. '</fieldset>'

		. '<fieldset legend="' . htmlspecialchars(_t('Textcube 확장 태그'), ENT_QUOTES, 'UTF-8') . '">'
		. '<field type="text" name="_md_help_tc" title="' . htmlspecialchars(_t('항목'), ENT_QUOTES, 'UTF-8') . '" value="' . htmlspecialchars(_t('더보기([#M_..._M#]) · 첨부 파일 경로'), ENT_QUOTES, 'UTF-8') . '" size="52">'
		. '<caption><![CDATA['
		. '<b>' . htmlspecialchars(_t('더보기:'), ENT_QUOTES, 'UTF-8') . '</b> [#M_ 더보기 버튼 | 접기 버튼 | 펼쳐질 내용 _M#]<br>'
		. '<b>' . htmlspecialchars(_t('첨부 경로:'), ENT_QUOTES, 'UTF-8') . '</b> [##_ATTACH_PATH_##] &nbsp;/&nbsp; http://tt_attach_path/'
		. ']]></caption>'
		. '</field>'
		. '</fieldset>'

		. '</config>';
}
?>
