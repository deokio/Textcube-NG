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
		'defaultDomain' => array(array('0', '1')),
		'primaryDomain' => array('string'),
		'secondaryDomain' => array('domain', 'default' => ''),
		'confirmativePassword' => array('string', 'mandatory' => false)
	)
);
require ROOT . '/library/preprocessor.php';
requireStrictRoute();
if (!isReauthenticated(isset($_POST['confirmativePassword']) ? $_POST['confirmativePassword'] : null))	// Textcube-NG: 블로그 주소(도메인) 변경 전 비밀번호 재확인, 틀리면 9(v3.149)
	Respond::ResultPage(9);

if(empty($_POST['secondaryDomain']) && $_POST['defaultDomain'] == 1)
	Respond::ResultPage(4);
else if( ($result = setPrimaryDomain($blogid, $_POST['primaryDomain'])) > 0 )
	Respond::PrintResult(array('error' => 2, 'msg' => $result));
else if( ($result = setSecondaryDomain($blogid, $_POST['secondaryDomain'])) > 0 )
	Respond::PrintResult(array('error' => 3, 'msg' => $result));
else if(!setDefaultDomain($blogid, $_POST['defaultDomain']))
	Respond::ResultPage(1);
else
	Respond::ResultPage(0);
?>
