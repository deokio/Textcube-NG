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
$IV = array(
	'GET' => array(
		'subject' => array('filename'),
		'lang' => array('string')
		)
	);

require ROOT . '/library/preprocessor.php';
if (false) {
	fetchConfigVal();
}
// 경로순회(LFI) 대응: $_GET['lang']는 $IV 'string' 타입이라 내용 필터링이 없어
// '../' 등으로 help 디렉터리 이탈이 가능. 화이트리스트(언어코드만)로 구분자·점 제거.
// upstream 미수정(master 동일). 검증: _sectest/help_lfi_test.php (악성 입력 ALL PASS).
$lang = preg_replace('/[^A-Za-z0-9_\-]/', '', $_GET['lang']);
$filename = $lang.'.'.$_GET['subject'].'.html';
$shortcutFilename = $lang.'.shortcut.html';

header('Content-Type: text/html; charset=utf-8');
if (!file_exists(ROOT . "/interface/owner/help/".$filename)){
	if (!file_exists(ROOT . "/interface/owner/help/".$shortcutFilename)){
		echo _t('죄송합니다. 아직 해당 메뉴에 대한 도움말이 준비되지 않았습니다.');
		exit;
	} else {
		$result = file_get_contents(ROOT . "/interface/owner/help/".$shortcutFilename);
		echo '<div id="helper-panel">'.CRLF.$result.'</div>';
		exit;
	}
}
$result = file_get_contents(ROOT . "/interface/owner/help/".$filename);
echo '<div id="helper-panel">'.CRLF.$result.'</div>';
exit;
?>
