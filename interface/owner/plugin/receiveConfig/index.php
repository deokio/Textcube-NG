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
$IV = array(	'POST' => array(	'Name' => array('string'),	
									'DATA' => array('string')
									)
		);
require ROOT . '/library/preprocessor.php';

$pluginName = $_POST['Name'];
// Textcube-NG: DATA 는 설정 XML 의 UTF-8 바이트를 base64 로 받는다 — 평문 XML 이 OWASP CRS(941xxx·942xxx)에 걸려 403 으로 저장되지 않았다.
// 끝의 '=' 패딩은 보내지 않는다(CRS 4.x PL2 942120 이 '==' 를 탐지, currentSetting). strict 디코딩은 패딩이 없어도 풀고, base64 밖의 글자가 있으면 false 다.
// strict 디코딩 실패·빈 값은 XML 실패와 같은 오류 3. 디코딩한 값은 원래 DATA 에 하던 Validator 'string' 검사(UTF-8 보정)를 그대로 거친다(v3.216)
$DATA = base64_decode($_POST['DATA'], true);
$decoded = array('DATA' => $DATA);
$decodedRule = array('DATA' => array('string'));
if (($DATA === false) || !Validator::validateArray($decoded, $decodedRule))
	Respond::PrintResult(array('error' => '3', 'customError' => ''));
$DATA = $decoded['DATA'];
$result = handleDataSet($pluginName, $DATA );
Respond::PrintResult($result);
?>
