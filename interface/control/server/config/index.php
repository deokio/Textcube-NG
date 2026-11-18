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

$IV = array(
	'POST' => array(
		'allowBlogVisibility'    => array('int'),
		'requireLogin'           => array('int',0,1),
		'encoding'               => array('string'),
		'faviconDailyTraffic'    => array('int'),
		'flashClipboardPoter'    => array('int',0,1),
		'flashUploader'          => array('int',0,1),
		'passwordHash'           => array(array('md5', 'multi', 'migrate'), 'mandatory' => false),	// Textcube-NG: 비밀번호 저장 방식 선택(v3.208)
		'language'               => array('string'),
		'serviceurl'             => array('string', 'mandatory' => false, 'default' => ''),	// Textcube-NG: 비우면 강제 지정 안 함(자동, Config 로더). 원본은 필수라 비우면 서버 설정 전체가 저장되지 않았다(pat.im 1336)(v3.139)
		'cookieprefix'           => array('string', 'mandatory' => false, 'default' => ''),
		'skin'                   => array('string'),
		'timeout'                => array('int'),
		'timezone'               => array('string'),
		'useDebugMode'           => array('int',0,1),
		'useEncodedURL'          => array('int',0,1),
		'useNumericRSS'          => array('int',0,1),
		'useServiceURLonSkin'    => array('int',0,1),	// Textcube-NG: 블로그 스킨 출력 주소 설정(v3.206)
		'useServiceURLonAttach'  => array('int',0,1),	// Textcube-NG: 블로그 스킨 출력 주소 설정(v3.206)
		'useServiceURLonResources' => array('int',0,1),	// Textcube-NG: 블로그 스킨 출력 주소 설정(v3.206)
		'usePageCache'           => array('int',0,1),
		'useCodeCache'           => array('int',0,1),
		'useReader'              => array('int',0,1),
		'useRewriteDebugMode'    => array('int',0,1),
		'useSessionDebugMode'    => array('int',0,1),
		'useSkinCache'           => array('int',0,1),
		'useMemcached'           => array('int',0,1),
		'useExternalResource'    => array('int',0,1),
		'externalResourceURL'    => array('string', 'mandatory' => false, 'default' => '')
		)
);

require ROOT . '/library/preprocessor.php';

requireModel('blog.service');
requireStrictRoute();
requirePrivilege('group.creators');
/* [보안] 서비스 기본 시간대는 PHP 시간대 식별자만 받는다(config.php 에 문자열로 기록되고, 블로그 시간대가 없을 때
   DB 세션 시간대 설정 SQL 에 쓰임) */
if (!Timezone::isValid($_POST['timezone']))
	Respond::PrintResult(array('error' => 1, 'msg' => _t('블로그 시간대를 변경할 수 없습니다.')));
$matchTable = array(
	'timeout' => 'timeout',
	'skin'    =>'skin',
	'language'=>'language',
	'timezone'=>'timezone',
	'encoding'=>'encoding',
	'serviceurl'  => 'serviceURL',
	'cookieprefix'=> 'cookie_prefix',
	'usePageCache'=>'pagecache',
	'useCodeCache'=>'codecache',
	'useSkinCache'=>'skincache',
	'useMemcached'=>'memcached',
	'useReader'   =>'reader',
	'useNumericRSS'=>'useNumericURLonRSS',	// config.default.php·blog.feed.php·설정 화면이 읽는 키(예전에는 읽히지 않는 useNumericRSS 로 기록됨)
	'useServiceURLonSkin'=>'useServiceURLonSkin',	// Textcube-NG: 블로그 스킨 출력 주소 설정(v3.206)
	'useServiceURLonAttach'=>'useServiceURLonAttach',	// Textcube-NG: 블로그 스킨 출력 주소 설정(v3.206)
	'useServiceURLonResources'=>'useServiceURLonResources',	// Textcube-NG: 블로그 스킨 출력 주소 설정(v3.206)
	'useEncodedURL'=>'useEncodedURL',
	'useExternalResource'=>'externalresources',
	'externalResourceURL'=>'resourceURL',
	'allowBlogVisibility'   => 'allowBlogVisibilitySetting',
	'requireLogin'          => 'requirelogin',
	'flashClipboardPoter'   => 'flashclipboardpoter',
	'flashUploader' => 'flashuploader',
	'useDebugMode'  =>'debugmode',
	'useSessionDebugMode' => 'debug_session_dump',
	'useRewriteDebugMode' => 'debug_rewrite_module',
	'faviconDailyTraffic' =>'favicon_daily_traffic'
	);
/* Exceptional handling */
$config = array();
foreach($matchTable as $abs => $real) {
	// Textcube-NG: 켬/끔 값(IV 규칙이 'int',0,1 인 키)은 bool 로 기록한다. POST 값은 문자열이라 원본의 === 1 / === 0 비교가 늘 거짓이었고
	// 정수 1·0 으로 기록됐다(config.default.php 의 true/false 와 형이 달랐음). 범위가 없는 정수(allowBlogVisibility·faviconDailyTraffic)는 그대로(r3-5)
	if(isset($IV['POST'][$abs]) && $IV['POST'][$abs] === array('int',0,1)) $config[$real] = ((int)$_POST[$abs] === 1);
	else if($_POST[$abs] === 1) $config[$real] = true;
	else if($_POST[$abs] === 0) $config[$real] = false;
	else $config[$real] = $_POST[$abs];
}
// Textcube-NG: 플래시 기능(트랙백 주소 복사·업로더)은 지원하지 않으므로 받은 값과 관계없이 끔(false)으로 기록한다(v3.208)
$config['flashclipboardpoter'] = false;
$config['flashuploader'] = false;
// Textcube-NG: 비밀번호 저장 방식은 Auth::passwordHashMode() 가 받는 값('md5'·'multi'·'migrate')만 기록한다. 그 밖의 값은 위 $IV 검사(404)와
// 여기서 거부하고 config.php 를 바꾸지 않는다. 값을 보내지 않으면 기록하지 않는다(v3.208)
if (isset($_POST['passwordHash'])) {
	if (!is_string($_POST['passwordHash']) || !in_array($_POST['passwordHash'], array('md5', 'multi', 'migrate'), true))
		Respond::PrintResult(array('error' => 1, 'msg' => _t('비밀번호 저장 방식이 올바르지 않습니다.')));
	$config['passwordHash'] = $_POST['passwordHash'];
}

$result = writeConfigFile($config);
if ($result === true) {
	Respond::PrintResult(array('error' => 0));
} else {
	Respond::PrintResult(array('error' => 1, 'msg' => $result));
}
?>
