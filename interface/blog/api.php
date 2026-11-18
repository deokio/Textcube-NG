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
define('__TEXTCUBE_LOGIN__',true);
// [보안] XML-RPC 는 호출마다 인증하므로 세션을 사용하지 않음(원본은 인증 정보가 IP 공유 익명 세션에 기록됨)
define('NO_SESSION', true);
define('__TEXTCUBE_XMLRPC__', true);	// Textcube-NG: XML-RPC 본문(text/xml·application/xml) POST 허용(preprocessor 요청 본문 형식 검사, r3-20)
require ROOT . '/library/preprocessor.php';

requireModel( "blog.api" );

/*--------- API main ---------------*/

if (Setting::getBlogSettingGlobal('useBlogAPI', 0) != 1) {
	Respond::NotFoundPage();
	exit;
}

function SendRSD()
{
	global $hostURL, $blogURL;
	global $blogid;
	$homeurl = $hostURL.$blogURL;
	$apiurl = $homeurl . "/api";
	$blogid = $blogid;

	header( "Content-type: text/xml", true );

	print( '<?xml version="1.0" encoding="utf-8" ?> 
<rsd xmlns="http://archipelago.phrasewise.com/rsd" version="1.0">
    <service xmlns="">
        <engineName>Textcube</engineName> 
        <engineLink>http://www.textcube.org/</engineLink>
        <homePageLink>' . $homeurl . '/</homePageLink>
        <apis>
        		<api name="MovableType" preferred="true" apiLink="' . $apiurl . '" blogID="' . $blogid . '" />
                <api name="MetaWeblog" preferred="false" apiLink="' . $apiurl . '" blogID="' . $blogid . '" />
                <api name="Blogger" preferred="false" apiLink="' . $apiurl . '" blogID="' . $blogid . '" />
        </apis>
    </service>
</rsd>' );
}

if( substr( $_SERVER["REQUEST_URI"], -8 ) == "/api?rsd" )
{
	SendRSD();
}
else
{
	if( strpos( $_SERVER["REQUEST_URI"], "api?rnd" ) !== false ) /* Writely.com */
	{
		api_setHint( "TagsFromCategories" );
	}
	if( isset($_GET['category']) ) {
		api_setHint( "Category", $_GET['category'] );
	}
	api_BlogAPI();
}

?>
