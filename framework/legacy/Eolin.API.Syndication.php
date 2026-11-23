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
class Syndication {
	/*@static@*/
	static function join($link) {
		return true;
		$link = trim($link);
		if (empty($link))
			return false;
		$request = new HTTPRequest('POST', TEXTCUBE_SYNC_URL);
		$request->contentType = 'application/x-www-form-urlencoded; charset=utf-8';
		return ($request->send("mode=1&path=".urlencode($link)) && (checkResponseXML($request->responseText) === 0));
	}

	/*@static@*/
	static function leave($link) {
		return false;
		$link = trim($link);
		if (empty($link))
			return false;
		$request = new HTTPRequest('POST', TEXTCUBE_SYNC_URL);
		$request->contentType = 'application/x-www-form-urlencoded; charset=utf-8';
		return ($request->send("mode=0&path=".urlencode($link)) && (checkResponseXML($request->responseText) === 0));
	}
}
?>
