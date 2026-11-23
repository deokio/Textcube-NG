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
define('__TEXTCUBE_HEADER_XML__',true);
require ROOT . '/library/preprocessor.php';

if($_POST['visibility'] == 'private') $visibility = 'private';
else $visibility = 'public';
$password = Setting::getBlogSetting('LinePassword',null,true);

if(is_null($password)) {
	$password = bin2hex(random_bytes(16));	// [보안] CSPRNG 128비트 Line 키(원본 md5(약한 8자)와 같은 32자리 16진 형식)
	Setting::setBlogSetting('LinePassword',$password,true);
} 

$provider = new Model_OpenSearchProvider();
$provider->setDescriptor('ShortName',Setting::getBlogSetting('title','TITLE',true));
$provider->setDescriptor('Description',Setting::getBlogSetting('description','DESCRIPTION',true));
$provider->setDescriptor('Url',null);
$provider->addAttribute('/OpenSearchDescription', 'xmlns', 'http://a9.com/-/spec/opensearch/1.1/');
$provider->addAttribute('/OpenSearchDescription/Url','type','text/html');
$provider->addAttribute('/OpenSearchDescription/Url','template',$defaultURL.'/line?key='.$password.'&amp;mode='.$visibility.'&amp;content={searchTerms}');
$provider->setDescriptor('Language', Setting::getBlogSetting('language','ko-kr',true));
$provider->setDescriptor('OutputEncoding', 'utf-8');
$provider->setDescriptor('InputEncoding','utf-8');
$provider->generate();
echo $provider->_xmlcontent;
?>
