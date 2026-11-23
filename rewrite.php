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

// Textcube-NG: 요청 시작 때 PHP 가 낸 입력 개수 경고(max_input_vars·max_multipart_body_parts)는 error_get_last() 의 마지막 오류 하나로만 남아
// 뒤 오류에 덮어써지므로 다른 코드보다 먼저 받아 둔다. library/preprocessor.php 입력 개수 검사가 쓴다(r3-20)
$__tcStartupError = error_get_last();
define('ROOT',dirname(__FILE__));
require_once(ROOT.'/framework/Dispatcher.php');
/** Dispatching Interface request via URI */
$dispatcher = Dispatcher::getInstance();
/** Interface Loading */
if (empty($service['debugmode'])) {@include_once $dispatcher->interfacePath;}
else {include_once $dispatcher->interfacePath;}
?>
