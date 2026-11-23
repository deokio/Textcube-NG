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

	if(!defined('__TEXTCUBE_SETUP__')) {
		$context = Model_Context::getInstance();
		$dbms = 'MySQLi';
		if(!is_null($context->getProperty('database.dbms'))) $dbms = $context->getProperty('database.dbms');
	} else {
		global $dbms;
	}
	require_once(ROOT."/framework/data/IAdapter.php");	
	require_once(ROOT."/framework/data/".$dbms."/Adapter.php");
?>
