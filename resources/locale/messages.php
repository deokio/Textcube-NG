<?php
if (!defined('ROOT')) {
	header('HTTP/1.1 403 Forbidden');
	header("Connection: close");
	exit;
}

// TODO: generalize for multiple language support e.g. skin language
$setting = Setting::getBlogSettingsGlobal($blogid);
// Textcube-NG: language 값 검증(v3.192). Locales::set() 과 같은 기준(문자열, 빈 값 아님, '/' '\' NUL '..' 없음)이고 파일이 있을 때만 읽는다. 아니면 번역 없이 둔다(_t() 는 키를 그대로 돌려줌, Locales::set() 실패 때와 같음)
if (isset($setting['language']) && is_string($setting['language']) && $setting['language'] !== '' && strpbrk($setting['language'], "/\\\0") === false && strpos($setting['language'], '..') === false && is_file(__DIR__ . '/owner/' . $setting['language'] . '.php')) {
	require 'owner/'.$setting['language'].'.php';
} else {
	$__text = array();
}

$json = json_encode($__text, JSON_FORCE_OBJECT | JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_UNESCAPED_UNICODE | JSON_INVALID_UTF8_SUBSTITUTE);
if ($json === false) {
	$json = '{}';
}
echo "__text = " . $json . ";\n";
echo <<<EOT

function _t(msg) {
	// Textcube-NG: __text 자신의 키만 찾는다(상속 속성 'constructor' 등은 돌려주지 않음)(v3.192)
	return (Object.prototype.hasOwnProperty.call(__text, msg) && __text[msg]) || msg;
}
EOT;
?>
