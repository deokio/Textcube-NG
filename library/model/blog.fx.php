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

$fxList = array();
function setDetailPanel($id,$type = 'section',$string = '') {
	global $fxList, $service;
//	if(in_array($fxList,$id)) return '';
	array_push($fxList, $id);
	$hrefVal = '';
	switch($type) {
	case 'button':
		if($service['interface'] == 'simple') {
			$hrefVal = TAB.TAB.TAB.TAB.TAB.TAB.TAB.'<dl class="line">'.CRLF
			.TAB.TAB.TAB.TAB.TAB.TAB.TAB.TAB.'<dt class="hidden">'._t('패널 보기 설정').'</dt>'.CRLF
			.TAB.TAB.TAB.TAB.TAB.TAB.TAB.TAB.'<dd><span class="input-button"><a id="toggle'.$id.'" href="#">'._t('자세한 설정 보기').'</a></span></dd>'.CRLF
			.TAB.TAB.TAB.TAB.TAB.TAB.TAB.'</dl>'.CRLF;
		} 
		break;
	case 'link': 
		if($service['interface'] == 'simple') {
			$hrefVal = '<a id="toggle'.$id.'" href="#">'.$string.'</a>';
		} else {
			$hrefVal = $string;
		}
		break;
	case 'sectionButton': default:
		if($service['interface'] == 'simple') {
			$hrefVal = TAB.TAB.TAB.TAB.TAB.TAB.TAB.'<dl class="panel-setting">'.CRLF
			.TAB.TAB.TAB.TAB.TAB.TAB.TAB.TAB.'<dt>'._t('패널 보기 설정').'</dt>'.CRLF
			.TAB.TAB.TAB.TAB.TAB.TAB.TAB.TAB.'<dd><a id="toggle'.$id.'" href="#">'._t('패널 열기').'</a></dd>'.CRLF
			.TAB.TAB.TAB.TAB.TAB.TAB.TAB.'</dl>'.CRLF;
		}
		break;
	}
	return $hrefVal;
}

function activateDetailPanelJS() {
	global $fxList, $service;
	$jsVal = '';
	if(!empty($fxList) && ($service['interface'] == 'simple')) {
		foreach($fxList as $fxItem) {
			// PHP port(Textcube-NG): MooTools 1.2b2 Fx.Slide 대신 jQuery. 처음엔 접어 두고 링크를 누르면 펼침(slideIn 과 같이 펼치기만, 500ms). 맨 앞 ';' 는 footer 의 document.onkeydown 대입문(끝에 ';' 없음)과 이어 붙지 않게 함(v3.174)
			$jsVal .= ";(function($){".CRLF
				."var p = $(document.getElementById('".$fxItem."')).hide();".CRLF
				."$(document.getElementById('toggle".$fxItem."')).on('click', function(e){".CRLF
				."p.slideDown(500);".CRLF
				."e.preventDefault();".CRLF
				."e.stopPropagation();".CRLF
				."});".CRLF
				."})(jQuery);".CRLF;
		}
	}
	return $jsVal;
}
?>
