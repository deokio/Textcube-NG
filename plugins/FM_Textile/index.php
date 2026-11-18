<?php
// Textile formatter for Textcube 1.10.3
// Library by Threshold state.
// Driver by Jeongkyu Shin. (inureyes@gmail.com)
// 2008.1.21
// Last updated : 2015. 2. 16

if(!class_exists('Textile')) require_once 'classTextile.php';

function FM_Textile_format($blogid, $id, $content, $keywords = array(), $useAbsolutePath = true, $bRssMode = false) {
	$context = Model_Context::getInstance();
	$textile = new Textile();
	$path = __TEXTCUBE_ATTACH_DIR__."/$blogid";
	$url = $context->getProperty("service.path")."/attach/$blogid";
	if(!function_exists('FM_TTML_bindAttachments')) { // To reduce the amount of loading code!
		require_once 'ttml.php';
	}
	$view = FM_TTML_bindAttachments($id, $path, $url, $content, $useAbsolutePath, $bRssMode);
	$view = FM_TTML_preserve_TTML_type_tags($view);
	$view = $textile->TextileThis($view);
	$view = FM_TTML_restore_TTML_type_tags($view);
	$view = FM_TTML_bindTags($id, $view);
	return $view;
}

function FM_Textile_summary($blogid, $id, $content, $keywords = array(), $useAbsolutePath = true) {
	$context = Model_Context::getInstance();

	$view = FM_Textile_format($blogid, $id, $content, $keywords, $useAbsolutePath, true);
	if (!$context->getProperty("blog.publishWholeOnRSS")) $view = UTF8::lessen(removeAllTags(stripHTML($view)), 255);
	return $view;
}

// 플러그인 설정 탭에 Textile 에디터 조작 방법 및 문법 사용 방법을 표시하기 위한 manifestHandler.
// handleConfig() 가 이 함수를 호출하고, 반환된 XML 을 파싱해 설정 폼으로 렌더링한다.
// field caption 의 .value 는 raw HTML 로 출력되므로 CDATA 내 HTML 태그 사용 가능.
// title="항목" + value="..." 으로 입력칸에 섹션 요약을 표시, 중요 항목만 caption 내 <b> 태그 적용.
function FM_Textile_ConfigHandler($plugin) {
	return '<?xml version="1.0" encoding="utf-8"?>'
		. '<config>'

		. '<fieldset legend="' . htmlspecialchars(_t('에디터 조작'), ENT_QUOTES, 'UTF-8') . '">'
		. '<field type="text" name="_textile_help_editor" title="' . htmlspecialchars(_t('항목'), ENT_QUOTES, 'UTF-8') . '" value="' . htmlspecialchars(_t('포맷 선택 · 텍스트 직접 입력 · 툴바 이미지 삽입 · 첨부 파일 · 미리보기'), ENT_QUOTES, 'UTF-8') . '" size="52">'
		. '<caption><![CDATA['
		. htmlspecialchars(_t('포맷 선택: 글쓰기 하단 포맷 드롭다운 → Textile'), ENT_QUOTES, 'UTF-8') . '<br>'
		. htmlspecialchars(_t('입력 방식: 순수 텍스트 직접 입력 (WYSIWYG 없음)'), ENT_QUOTES, 'UTF-8') . '<br>'
		. '<b>' . htmlspecialchars(_f('이미지 삽입: 정렬 버튼(왼쪽/가운데/오른쪽) 클릭 → %1 태그 자동 삽입', '[##_..._##]'), ENT_QUOTES, 'UTF-8') . '</b><br>'
		. htmlspecialchars(_t('첨부 경로 직접 참조:'), ENT_QUOTES, 'UTF-8') . ' [##_ATTACH_PATH_##] ' . htmlspecialchars(_t('또는'), ENT_QUOTES, 'UTF-8') . ' http://tt_attach_path/<br>'
		. htmlspecialchars(_t('미리보기: 임시저장 후 미리보기 버튼 이용'), ENT_QUOTES, 'UTF-8')
		. ']]></caption>'
		. '</field>'
		. '</fieldset>'

		. '<fieldset legend="' . htmlspecialchars(_t('기본 문법'), ENT_QUOTES, 'UTF-8') . '">'
		. '<field type="text" name="_textile_help_basic" title="' . htmlspecialchars(_t('항목'), ENT_QUOTES, 'UTF-8') . '" value="' . htmlspecialchars(_t('제목 · 강조 · 링크/이미지 · 목록 · 인용 · 코드'), ENT_QUOTES, 'UTF-8') . '" size="52">'
		. '<caption><![CDATA['
		. 'h1. 제목1 &nbsp; h2. 제목2 &nbsp; h3. 제목3 &nbsp;&nbsp; (h1~h6)<br>'
		. '_기울임_ &nbsp; *강조* &nbsp; **굵게** &nbsp; -취소선- &nbsp; ^윗첨자^ &nbsp; ~아래첨자~<br>'
		. '"링크텍스트":URL &nbsp;/&nbsp; "링크(설명)":URL &nbsp;&nbsp; (' . htmlspecialchars(_t('링크'), ENT_QUOTES, 'UTF-8') . ')<br>'
		. '!이미지URL! &nbsp;/&nbsp; !이미지URL(대체텍스트)! &nbsp;/&nbsp; !이미지URL!:링크URL<br>'
		. '* ' . htmlspecialchars(_t('항목'), ENT_QUOTES, 'UTF-8') . ' (' . htmlspecialchars(_t('비순서 목록'), ENT_QUOTES, 'UTF-8') . ') &nbsp;/&nbsp; # ' . htmlspecialchars(_t('항목'), ENT_QUOTES, 'UTF-8') . ' (' . htmlspecialchars(_t('순서 목록'), ENT_QUOTES, 'UTF-8') . ') &nbsp;/&nbsp; ** ' . htmlspecialchars(_t('또는'), ENT_QUOTES, 'UTF-8') . ' ## 하위 항목<br>'	// Textcube-NG: '항목'을 플러그인 locale 의 _t 키로 출력한다(v3.189)
		. 'bq. 인용 &nbsp;/&nbsp; bq.:URL ' . htmlspecialchars(_t('출처있는 인용'), ENT_QUOTES, 'UTF-8') . ' &nbsp;/&nbsp; @인라인 코드@'
		. ']]></caption>'
		. '</field>'
		. '</fieldset>'

		. '<fieldset legend="' . htmlspecialchars(_t('고급 문법'), ENT_QUOTES, 'UTF-8') . '">'
		. '<field type="text" name="_textile_help_advanced" title="' . htmlspecialchars(_t('항목'), ENT_QUOTES, 'UTF-8') . '" value="' . htmlspecialchars(_t('표 · 각주 · 약어 · 정렬 · 클래스/ID · notextile'), ENT_QUOTES, 'UTF-8') . '" size="52">'
		. '<caption><![CDATA['
		. '<b>' . htmlspecialchars(_t('표:'), ENT_QUOTES, 'UTF-8') . '</b> |셀1|셀2|셀3| &nbsp;/&nbsp; ' . htmlspecialchars(_t('헤더 행:'), ENT_QUOTES, 'UTF-8') . ' |_. 헤더1|_. 헤더2|<br>'
		. '<b>' . htmlspecialchars(_t('각주:'), ENT_QUOTES, 'UTF-8') . '</b> fn1. 각주 내용 (' . htmlspecialchars(_t('별도 단락'), ENT_QUOTES, 'UTF-8') . ') &nbsp;/&nbsp; ' . htmlspecialchars(_t('본문:'), ENT_QUOTES, 'UTF-8') . ' [1]<br>'
		. '<b>' . htmlspecialchars(_t('약어:'), ENT_QUOTES, 'UTF-8') . '</b> ABC(Always Be Closing) → &lt;acronym&gt;<br>'
		. htmlspecialchars(_t('정렬:'), ENT_QUOTES, 'UTF-8') . ' h2&lt;. ' . htmlspecialchars(_t('왼쪽'), ENT_QUOTES, 'UTF-8') . ' &nbsp;/&nbsp; h2&gt;. ' . htmlspecialchars(_t('오른쪽'), ENT_QUOTES, 'UTF-8') . ' &nbsp;/&nbsp; h2=. ' . htmlspecialchars(_t('가운데'), ENT_QUOTES, 'UTF-8') . ' &nbsp;/&nbsp; h2&lt;&gt;. ' . htmlspecialchars(_t('양쪽'), ENT_QUOTES, 'UTF-8') . '<br>'
		. htmlspecialchars(_t('클래스/ID:'), ENT_QUOTES, 'UTF-8') . ' p(클래스). 단락 &nbsp;/&nbsp; p(#아이디). 단락 &nbsp;/&nbsp; p{color:red}. 스타일<br>'
		. '<b>==서식 없음==</b>: ' . htmlspecialchars(_t('notextile 영역 지정 (HTML 태그 직접 삽입 시 사용)'), ENT_QUOTES, 'UTF-8')
		. ']]></caption>'
		. '</field>'
		. '</fieldset>'

		. '<fieldset legend="' . htmlspecialchars(_t('Textcube 확장 태그'), ENT_QUOTES, 'UTF-8') . '">'
		. '<field type="text" name="_textile_help_tc" title="' . htmlspecialchars(_t('항목'), ENT_QUOTES, 'UTF-8') . '" value="' . htmlspecialchars(_t('더보기([#M_..._M#]) · 첨부 파일 경로'), ENT_QUOTES, 'UTF-8') . '" size="52">'
		. '<caption><![CDATA['
		. '<b>' . htmlspecialchars(_t('더보기:'), ENT_QUOTES, 'UTF-8') . '</b> [#M_ 더보기 버튼 | 접기 버튼 | 펼쳐질 내용 _M#]<br>'
		. '<b>' . htmlspecialchars(_t('첨부 경로:'), ENT_QUOTES, 'UTF-8') . '</b> [##_ATTACH_PATH_##] &nbsp;/&nbsp; http://tt_attach_path/'
		. ']]></caption>'
		. '</field>'
		. '</fieldset>'

		. '</config>';
}
?>
