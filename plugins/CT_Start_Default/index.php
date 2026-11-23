<?php
/* Start plugin for Textcube 1.10.3
   ----------------------------------
   Version 1.10.3
   Tatter and Friends development team.

   Creator          : inureyes
   Maintainer       : inureyes, gendoh, graphittie

   Created at       : 2006.8.22
   Last modified at : 2015.2.16

 This plugin adds start panel on 'quilt'.
 For the detail, visit http://forum.tattersite.com/ko

 General Public License
 http://www.gnu.org/licenses/gpl.html

 This program is free software; you can redistribute it and/or modify
 it under the terms of the GNU General Public License as published by
 the Free Software Foundation; either version 2 of the License, or
 (at your option) any later version.

*/
function CT_Start_Default($target) {
	requireModel("blog.attachment");
	$context = Model_Context::getInstance();
	$blogURL = $context->getProperty('uri.blog');
	$blogid = $context->getProperty('blog.id');

	$target .= '<ul>';
	// Textcube-NG: 위젯 문구는 HTML escape 해서 넣는다(v3.193)
	$target .= '<li><a href="'.$blogURL.'/owner/entry/post">'. htmlspecialchars(_t('새 글을 씁니다'), ENT_QUOTES, 'UTF-8').'</a></li>'.CRLF;

	$latestEntryId = Setting::getBlogSettingGlobal('LatestEditedEntry_user'.getUserId(),0);
	if($latestEntryId !== 0){
		$latestEntry = CT_Start_Default_getEntry($blogid,$latestEntryId);
		if($latestEntry!=false){
			$target .= '<li><a href="'.$blogURL.'/owner/entry/edit/'.$latestEntry['id'].'">'. htmlspecialchars(_f('최근글(%1) 수정', UTF8::lessenAsEm($latestEntry['title'],10)), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8').'</a></li>';
		}
	}
	if(Acl::check('group.administrators')) {
		$target .= '<li><a href="'.$blogURL.'/owner/skin">'. htmlspecialchars(_t('스킨을 변경합니다'), ENT_QUOTES, 'UTF-8').'</a></li>'.CRLF;
		$target .= '<li><a href="'.$blogURL.'/owner/skin/sidebar">'. htmlspecialchars(_t('사이드바 구성을 변경합니다'), ENT_QUOTES, 'UTF-8').'</a></li>'.CRLF;
		$target .= '<li><a href="'.$blogURL.'/owner/skin/setting">'. htmlspecialchars(_t('블로그에 표시되는 값들을 변경합니다'), ENT_QUOTES, 'UTF-8').'</a></li>'.CRLF;
		$target .= '<li><a href="'.$blogURL.'/owner/entry/category">'. htmlspecialchars(_t('카테고리를 변경합니다'), ENT_QUOTES, 'UTF-8').'</a></li>'.CRLF;
		$target .= '<li><a href="'.$blogURL.'/owner/plugin">'. htmlspecialchars(_t('플러그인을 켜거나 끕니다'), ENT_QUOTES, 'UTF-8').'</a></li>'.CRLF;
	}
	if($context->getProperty('service.reader',false) != false) {
		$target .= '<li><a href="'.$blogURL.'/owner/network/reader">'. htmlspecialchars(_t('RSS 리더를 봅니다'), ENT_QUOTES, 'UTF-8').'</a></li>'.CRLF;
	}
	$target .= '</ul>';
	return $target;
}

function CT_Start_Default_getEntry($blogid, $id) {
	if ($id == 0) {
		return null;
	}
	$visibility = doesHaveOwnership() ? '' : 'AND visibility > 0';
	$pool = DBModel::getInstance();
	$pool->reset("Entries");
	$pool->setQualifier("blogid","eq",$blogid);
	$pool->setQualifier("id","eq",$id);
	$pool->setQualifier("draft","eq",0);
	if (!doesHaveOwnership()) {
		$pool->setQualifier("visibility",">",0);
	}
	$entry = $pool->getRow("id,title,visibility");
	if (!$entry)
		return false;
	return $entry;
}
?>
