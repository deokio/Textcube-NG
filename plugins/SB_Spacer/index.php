<?php
/* Spacer plugin for Textcube 1.10
   ----------------------------------
   Version 1.10.4
   Needlworks Tdevelopment team.

   Creator          : inureyes
   Maintainer       : inureyes

   Created at       : 2006.11.1
   Last modified at : 2015.3.4
 
 This plugin adds space on the sidebar.
 For the detail, visit http://forum.tattersite.com/ko


 General Public License
 http://www.gnu.org/licenses/gpl.html

 This program is free software; you can redistribute it and/or modify
 it under the terms of the GNU General Public License as published by
 the Free Software Foundation; either version 2 of the License, or
 (at your option) any later version.

*/
function SB_Spacer($target)
{
	global $configVal;
	$data = Setting::fetchConfigVal($configVal);
	// Textcube-NG: fetchConfigVal() 은 설정이 없으면 false 를 돌려준다. PHP 8 은 array_key_exists() 에 배열이 아닌 값이면 TypeError 를 낸다(PHP 5·7.4 는 경고 뒤 null) — 배열일 때만 읽고 그 밖에는 기본값(R-502)
	if(is_array($data) && array_key_exists('height', $data)){
		$height = $data['height'];
	} else {
		$height = '20';
	}
	$text = '<div class="SB_Spacer" style="height:'.(int)$height.'px;"></div>';
	return $text;
}

function SB_Spacer_DataSet($data){
	// Textcube-NG: r3-42 $data 는 handleDataSet() 이 넘기는 직렬화 문자열(serialize(Setting::fetchConfigXML(...)), 필드 이름 => 문자열|null)이라
	// $data['height'] 는 PHP 8 에서 TypeError(receiveConfig 500), PHP 7.4 는 첫 글자만 읽어 늘 실패했다. 풀어서 SEC-016 검사(숫자, 0~2000)를 그대로 한다.
	// allowed_classes => false 로 객체를 만들지 않는다(PHP 7.0+). 배열이 아니거나 height 가 없으면 받지 않는다
	$config = is_string($data) ? unserialize($data, array('allowed_classes' => false)) : false;
	if(!is_array($config) || !isset($config['height'])) return false;
	if(!is_numeric($config['height']) || (int)$config['height'] < 0 || (int)$config['height'] > 2000) return false;
	return true;
}
?>
