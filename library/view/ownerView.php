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

function printFormatterSelectScript() {
?>
<script type="text/javascript">
//<![CDATA[
	function getEditorsForFormatter(key) {
		switch (key) {
<?php
	foreach (getAllFormatters() as $id => $formatter) {
		echo "\t\tcase '".addslashes($id)."': return [";
		$delim = '';
		foreach ($formatter['editors'] as $key => $value) {
			echo $delim."'".addslashes($key)."'";
			$delim = ', ';
		}
		echo "];\n";
	}
?>
		}
		return [];
	}

	function setFormatter(key, editorselect, correct) {
		var editoroptions = editorselect.options;
		var editorsusedfor = getEditorsForFormatter(key);
		var editormap = {};
		for (var i = 0; i < editorsusedfor.length; ++i) {
			editormap[editorsusedfor[i]] = true;
		}
		var firsteditor = -1;
		for (var i = 0; i < editoroptions.length; ++i) {
			if (editormap[editoroptions[i].value]) {
				editoroptions[i].disabled = false;
				editoroptions[i].style.color = '';
				if (firsteditor < 0) firsteditor = i;
			} else {
				editoroptions[i].disabled = true;
				editoroptions[i].style.color = 'GrayText';
			}
		}
		//if (correct && !editormap[editorselect.value] && firsteditor >= 0) {
		if (correct && firsteditor >= 0) {
			editorselect.selectedIndex = firsteditor;
			if (typeof correct == 'function') correct(editorselect.value, key);
		}
		return true;
	}

	function saveEditor(select) {
		select.prevSelectedIndex = select.selectedIndex;
		return true;
	}

	function setEditor(select) {
		if (select.options[select.selectedIndex].disabled) {
			select.selectedIndex = select.prevSelectedIndex;
			return false;
		}
		select.prevSelectedIndex = select.selectedIndex;
		return true;
	}
//]]>
</script>
<?php
}

function printOwnerEditorScript($entryId = false) {
	global $pluginURL, $pluginName, $configMappings;
	
	$contentWidth = 500;
	$context = Model_Context::getInstance();
	$browser = Utils_Browser::getInstance();
	if($browser->isMobile()) {
		$contentWidth = 270;
	} else {
		$contentWidth = Misc::getContentWidth();
		/*
		if($skin = $context->getProperty('skin.skin')) {
			if($xml = @file_get_contents(__TEXTCUBE_SKIN_DIR__."/$skin/index.xml")) {
				$xmls = new XMLStruct();
				$xmls->open($xml, $context->getProperty('service.encoding'));
				if($xmls->getValue('/skin/default/contentWidth')) {
					$contentWidth = $xmls->getValue('/skin/default/contentWidth');
				}
			}
		}*/
	}
?>
<script type="text/javascript">
//<![CDATA[
	var entryId = <?php echo $entryId ? $entryId : 0;?>; 
	var skinContentWidth = <?php echo $contentWidth;?>;

	function savePosition(oTextarea) {
		if (oTextarea.createTextRange)
			oTextarea.currentPos = document.selection.createRange().duplicate();
	}

	function insertTag(oTextarea, prefix, postfix) {
		if(isSafari && !isMinSafari3)
			var selection = window.getSelection;
		else
			var selection = document.selection;

		if (selection) {			
			if (oTextarea.createTextRange && oTextarea.currentPos) {				
				oTextarea.currentPos.text = prefix + oTextarea.currentPos.text + postfix;
				oTextarea.focus();
				savePosition(oTextarea);
			}
			else
				oTextarea.value = oTextarea.value + prefix + postfix;
		}
		else if (oTextarea.selectionStart != null && oTextarea.selectionEnd != null) {
			var s1 = oTextarea.value.substring(0, oTextarea.selectionStart);
			var s2 = oTextarea.value.substring(oTextarea.selectionStart, oTextarea.selectionEnd);
			var s3 = oTextarea.value.substring(oTextarea.selectionEnd);
			oTextarea.value = s1 + prefix + s2 + postfix + s3;
		}
		else
			oTextarea.value += prefix + postfix;
			
		return true;	
	}

	function editorChanged() {
		if ((entryManager != undefined) && (entryManager.stateChanged != undefined))
			entryManager.stateChanged();
	}

	// Textcube-NG: 편집기 초기화가 끝났을 때(TinyMCE init) — 초기화하며 다시 직렬화한 본문을 저장된 값으로 본다(edit/index.php resetSavedContent, v3.137)
	function editorInitialized() {
		if ((entryManager != undefined) && (entryManager.resetSavedContent != undefined))
			entryManager.resetSavedContent();
	}

	function getEditor() {
<?php
	$setEditor = $context->getProperty('editor.key');
	/// CHANGED FROM 1.8 : Editor only loads what user is using.
	foreach (getAllEditors() as $id => $editor) {
		if($id == $setEditor) {
			getEditorInfo($id); // explicitly loads plugin code ($pluginURL, $pluginName returned as global)
			if (isset($editor['initfunc']) && function_exists($editor['initfunc'])) {
				echo "\t\t\n".call_user_func_array($editor['initfunc'], array(&$editor))."\t\t\n";
				$pluginURL = $pluginName = "";
			}
		}
	}
?>
		return new TTDefaultEditor();
	}

	var editor = null;
	function setCurrentEditor(key,formatter) {
		var neweditor = getEditor();
		if (neweditor == null) {
			if (editor == null) {
				// this indicates currently selected editor is unavailable;
				// we fallback into the default editor.
				neweditor = new TTDefaultEditor();
			} else {
				return false;
			}
		}
//		if (editor != null) {
//			try { editor.syncTextarea(); } catch(e) {}
//			editor.finalize();
//		}
		editor = neweditor;
		editor.formatter = formatter;
		editor.initialize(document.getElementById("editWindow"));
		return true;
	}

	// Textcube-NG: 막는 창(confirm) 대신 화면 안 확인 상자 — 관리 화면 <dialog> 방식(v3.128 PopupWindow)과 같이 showModal 로 띄우고,
	// showModal 이 없으면 일반 요소로 표시. 확인이면 onYes 를 부르고, 취소·ESC 는 닫기만 한다(r3-8)
	function tcConfirmDialog(msg, onYes) {
		var d = document.createElement('dialog');
		var modal = (typeof d.showModal == 'function');
		var p = document.createElement('p');
		var bar = document.createElement('div');
		var yes = document.createElement('button');
		var no = document.createElement('button');
		var done = false;
		function close() {
			if (done) return;
			done = true;
			if (modal && d.open) d.close();
			if (d.parentNode) d.parentNode.removeChild(d);
		}
		d.setAttribute('data-tc-confirm', '');
		d.style.cssText = 'position:fixed;top:50%;left:50%;transform:translate(-50%,-50%);margin:0;z-index:1000;max-width:30em;padding:1em;background:#fff;color:#000;border:1px solid #999';
		p.appendChild(document.createTextNode(msg));
		bar.style.textAlign = 'right';
		yes.type = 'button';
		no.type = 'button';
		yes.className = 'input-button';
		no.className = 'input-button';
		yes.appendChild(document.createTextNode("<?php echo _t('확인');?>"));
		no.appendChild(document.createTextNode("<?php echo _t('취소');?>"));
		yes.onclick = function() { close(); onYes(); };
		no.onclick = function() { close(); };
		d.addEventListener('cancel', function(e) { e.preventDefault(); close(); }, false);
		bar.appendChild(yes);
		bar.appendChild(document.createTextNode(' '));
		bar.appendChild(no);
		d.appendChild(p);
		d.appendChild(bar);
		document.body.appendChild(d);
		if (modal) d.showModal(); else d.style.display = 'block';
		no.focus();
	}

	function changeEditor(key, formatter) {
		if (entryManager.nowsaving == true) {
			PM.showErrorMessage("<?php echo _t('현재 자동 저장 중입니다. 저장이 완료된 후 다시 시도해 주세요.');?>", "center", "bottom");
			return false;
		}
		// Textcube-NG: confirm 대신 tcConfirmDialog — 확인하면 진행. 상자가 떠 있는 사이 자동 저장이 시작됐으면 위와 같이 알리고 멈춤(r3-8)
		tcConfirmDialog("<?php echo _t('정말로 에디터를 변경하시겠습니까?');?>", function() {
			if (entryManager.nowsaving == true) {
				PM.showErrorMessage("<?php echo _t('현재 자동 저장 중입니다. 저장이 완료된 후 다시 시도해 주세요.');?>", "center", "bottom");
				return;
			}
			if (editor != null) {
				try { editor.syncTextarea(); } catch(e) {}
				editor.finalize();
			}
			entryManager.changeEditor = true;
			entryManager.currentEditor = key;
			entryManager.saveDraft();
		});
	}
	
	function reloadEditor() {
		var url = '<?php echo $context->getProperty('uri.blog')."/owner/entry/edit/";?>'+entryManager.entryId+'?editor='+entryManager.currentEditor;

		if ( entryManager.draftSaved == true) {
			url = url+'&draft=true';
		}
		if ( entryManager.returnURL != null ) {
			url = url+'&returnURL='+encodeURIComponent(entryManager.returnURL);			
		}
		window.location = url;		
	}
//]]>
</script>
<?php

	printFormatterSelectScript();
}

function printEntryFileList($attachments, $param) {
	global $blogURL, $adminSkinSetting;
	$context = Model_Context::getInstance();
	$spacerURL = $context->getProperty('service.path').$adminSkinSetting['skin'].'/image/spacer.gif';

	$blogid = getBlogId();
	if(empty($attachments) || (
	strpos($attachments[0]['name'] ,'.gif') === false &&
	strpos($attachments[0]['name'] ,'.jpg') === false &&
	strpos($attachments[0]['name'] ,'.png') === false)) {
		$fileName =  $spacerURL;
	} else {
		$fileName = "{$context->getProperty('service.path')}/attach/$blogid/{$attachments[0]['name']}";
	}
?>
											<div id="previewSelected" style="width: 120px; height: 90px;"><span class="text"><?php echo _t('미리보기');?></span></div>
											
											<div id="attachManagerSelectNest">				
												<span id="attachManagerSelect">
													<select id="TCfilelist" name="TCfilelist" multiple="multiple" size="8" onchange="selectAttachment();">
<?php 
	$initialFileListForFlash = '';
	$enclosureFileName = '';
	foreach ($attachments as $i => $attachment) {
		
		if (strpos ($attachment['mime'], 'application') !== false ) {
			$class = 'class="MimeApplication"';
		} else if (strpos ($attachment['mime'], 'audio') !== false ) {
			$class = 'class="MimeAudio"';
		} else if (strpos ($attachment['mime'], 'image') !== false ) {
			$class = 'class="MimeImage"';
		} else if (strpos ($attachment['mime'], 'message') !== false ) {
			$class = 'class="MimeMessage"';
		} else if (strpos ($attachment['mime'], 'model') !== false ) {
			$class = 'class="MimeModel"';
		} else if (strpos ($attachment['mime'], 'multipart') !== false ) {
			$class = 'class="MimeMultipart"';
		}  else if (strpos ($attachment['mime'], 'text') !== false ) {
			$class = 'class="MimeText"';
		}  else if (strpos ($attachment['mime'], 'video') !== false ) {
			$class = 'class="MimeVideo"';
		} else {
			$class = '';
		}
		if (!empty($attachment['enclosure']) && $attachment['enclosure'] == 1) {
			$style = 'style="background-color:#c6a6e7; color:#000000"';		
			$enclosureFileName = $attachment['name'];
		} else {
			$style = '';
		}
		
		$value = htmlspecialchars(getAttachmentValue($attachment));
		$label = htmlspecialchars(getPrettyAttachmentLabel($attachment));
		
		$initialFileListForFlash .= escapeJSInAttribute($value.'(_!'.$label.'!^|');
?>
		<option  <?php echo $style;?> value="<?php echo $value;?>"><?php echo $label;?></option>
<?php
	}
?>
													</select>
												</span>
											</div>
											
											<script type="text/javascript">
												//<![CDATA[
													function addAttachment() {
														var attachHidden = document.getElementById('attachHiddenNest');
														attachHidden.contentDocument.forms[0].action = "<?php echo $param['singleUploadPath'];?>"+entryManager.entryId;
														attachHidden.contentDocument.forms[0].attachment.click();
													}
													
													function deleteAttachment() {
														var fileList = document.getElementById('TCfilelist');		
														
														if (fileList.selectedIndex < 0) {
															PM.showErrorMessage("<?php echo _t('삭제할 파일을 선택해 주십시오\t');?>", "center", "bottom");
															return false;
														}
														
														try {
															
															var targetStr = '';
															deleteFileList = new Array();
															for(var i=0; i<fileList.length; i++) {
																if(fileList[i].selected) {
																	var name = fileList[i].value.split("|")[0];
																	targetStr += name+'!^|';
																	deleteFileList.push(i);
																}
															}
														} catch(e) {
															PM.showErrorMessage("<?php echo _t('파일을 삭제하지 못했습니다');?> ::"+String(e.message).replace(/[&<>"']/g, function(c) { return {"&": "&amp;", "<": "&lt;", ">": "&gt;", "\"": "&quot;", "'": "&#39;"}[c]; }), "center", "bottom");
														}
												
														var request = new HTTPRequest("POST", "<?php echo $param['deletePath'];?>"+entryManager.entryId);
														// Textcube-NG: 원본은 onVerify 가 항상 true 라 서버가 삭제하지 못했다고 답해도(-1) 목록에서 지웠다 → 기본 onVerify(/response/error == 0)
												
														request.onSuccess = function() {				
															for(var i=deleteFileList.length-1; i>=0; i--) {
																fileList.remove(deleteFileList[i]);	
															}
															
															if (fileList.options.length == 0)
																document.getElementById('previewSelected').innerHTML = '';
															else {
																fileList.selectedIndex = 0;
																selectAttachment();
															}
															refreshAttachFormSize();
															refreshFileSize();
														}
														
														request.onError = function() {
															PM.showErrorMessage("<?php echo _t('파일을 삭제하지 못했습니다');?>", "center", "bottom");
															refreshAttachList();	// Textcube-NG: 일부만 지워졌을 수 있으므로 서버 기준으로 목록을 다시 읽음
														}
														request.send("names="+targetStr);
													}

													function selectAttachment() {
														try {
														width = document.getElementById('previewSelected').clientWidth;
														height = document.getElementById('previewSelected').clientHeight;
														var code = '';
														var fileList = document.getElementById('TCfilelist');
														if (fileList.selectedIndex < 0)
															return false;
														var fileName = fileList.value.split("|")[0];
														
														if((new RegExp("\\.(gif|jpe?g|png|webp|avif|svg)$", "gi").exec(fileName))) {	// Textcube-NG: webp·avif·svg 도 미리보기(r3-20)
															try {
																var width = new RegExp('width="(\\d+)').exec(fileList.value);
																width = width[1];
																var height = new RegExp('height="(\\d+)').exec(fileList.value);
																height = height[1];
																if(width > 120) {
																	height = 120 / width * height;
																	width = 120;
																}
																if(height > 90) {
																	width = 90 / height * width;
																	height = 90;
																}
																document.getElementById('previewSelected').innerHTML = '<img src="<?php echo $context->getProperty('service.path');?>/attach/<?php echo $blogid;?>/'+fileName+'?randseed='+Math.random()+'" width="' + parseInt(width) + '" height="' + parseInt(height) + '" alt="" style="margin-top: ' + ((90-height)/2) + 'px" onerror="if (this.src != \'<?php echo $spacerURL; ?>\') { this.src=\'<?php echo $spacerURL; ?>\' }"/>';																
															}
															catch(e) { }
															return false;
														}
														
														if((new RegExp("\\.(mp3|ogg|wav|flac|m4a|aac)$", "gi").exec(fileName))) {
									writeCode('<audio controls style="width:100%;margin-top:10px"><source src="<?php echo $context->getProperty('service.path');?>/attach/<?php echo $blogid;?>/' + fileName + '" \/><\/audio>', 'previewSelected');
									return false;
								}
														
														if((new RegExp("\\.(swf)$", "gi").exec(fileName))) {
									writeCode('<p style="padding:20px;color:#666;text-align:center"><?php echo _t('Flash 콘텐츠는 더 이상 지원되지 않습니다.');?><\/p>', 'previewSelected');
									return false;
								}
														
														if((new RegExp("\\.(mov)$", "gi").exec(fileName))) {			
															// Textcube-NG: IE 전용 ActiveX(clsid) object 를 HTML5 video 로 바꿈(r3-30 R-429)
															code = '<video controls style="width:100%"><source src="<?php echo $context->getProperty('service.path');?>/attach/<?php echo $blogid;?>/'+fileName+'" \/><\/video>';
															writeCode(code,'previewSelected');
															return false;
														}
													
														if((new RegExp("\\.(mp2|wma|mid|midi|mpg|wav|avi|mp4)$", "gi").exec(fileName))) {
															// Textcube-NG: IE 전용 ActiveX(clsid) object 를 HTML5 video 로 바꿈(r3-30 R-429)
															code = '<video controls style="width:100%"><source src="<?php echo $context->getProperty('service.path');?>/attach/<?php echo $blogid;?>/'+fileName+'" \/><\/video>';
															
															writeCode(code,'previewSelected');
															
															return false;
														}
														
														if((new RegExp("\\.(rm|ram)$", "gi").exec(fileName))) {		
														/*
															code = '<object classid="clsid:CFCDAA03-8BE4-11cf-B84B-0020AFBBCCFA" width="'+width+'" height="'+height+'"><param name="src" value="<?php echo $context->getProperty('service.path');?>/attach/<?php echo $blogid;?>/'+fileName+'"/><param name="CONTROLS" value="imagewindow"><param name="AUTOGOTOURL" value="FALSE"><param name="CONSOLE" value="radio"><param name="AUTOSTART" value="TRUE">';
															code += '<!--[if !IE]> <--><object type="audio/x-pn-realaudio-plugin" data="<?php echo $context->getProperty('service.path');?>/attach/<?php echo $blogid;?>/'+fileName+'" width="'+width+'" height="'+height+'" ><param name="CONTROLS" value="imagewindow"><param name="AUTOGOTOURL" value="FALSE"><param name="CONSOLE" value="radio"><param name="AUTOSTART" value="TRUE"><\/object><!--> <![endif]--><\/object>';			
														*/
														}
														
														if (code == undefined || code == '') {
															document.getElementById('previewSelected').innerHTML = "<table width=\"100%\" height=\"100%\"><tr><td valign=\"middle\" align=\"center\"><?php echo _t('미리보기');?><\/td><\/tr><\/table>";
															return true;
														}
														
																
														
														return false;
														} catch (e) {
															document.getElementById('previewSelected').innerHTML = "<table width=\"100%\" height=\"100%\"><tr><td valign=\"middle\" align=\"center\"><?php echo _t('미리보기');?><\/td><\/tr><\/table>";	
															PM.showErrorMessage(String(e.message).replace(/[&<>"']/g, function(c) { return {"&": "&amp;", "<": "&lt;", ">": "&gt;", "\"": "&quot;", "'": "&#39;"}[c]; }), "center", "bottom");
															return true;
														}
													}				

													function downloadAttachment() {
														try {
															var fileList = document.getElementById('TCfilelist');
															if (fileList.selectedIndex < 0) {
																return false;
															}
															for(var i=0; fileList.length; i++) {
																if (fileList[i].selected) {
																	var fileName = fileList[i].value.split("|")[0];
																	if(STD.isIE) {
																		document.getElementById('fileDownload').innerHTML='<iframe style="display:none;" src="'+blogURL+'\/attachment\/'+fileName+'"><\/iframe>';
																		
																	} else {
																		window.location = blogURL+'/attachment/'+fileName;
																	}
																	break;
																}
															}
														} catch(e) {
															PM.showErrorMessage(String(e.message).replace(/[&<>"']/g, function(c) { return {"&": "&amp;", "<": "&lt;", ">": "&gt;", "\"": "&quot;", "'": "&#39;"}[c]; }), "center", "bottom");
														}
													}

													STD.addEventListener(window);													
													window.addEventListener("beforeunload", PageMaster.prototype._onBeforeUnload, false);				
													
													function stripLabelToValue(fileLabel) {
														var pos = fileLabel.lastIndexOf('(');
														return fileLabel.substring(0,pos-1);	
													}
													
													function refreshAttachFormSize() {
														fileListObj = document.getElementById('TCfilelist');
														fileListObj.setAttribute('size',Math.max(8,Math.min(fileListObj.length,30)));
													}
													
													function refreshAttachList() {
														var request = new HTTPRequest("POST", "<?php echo $param['refreshPath'];?>"+entryManager.entryId);
														request.onVerify = function () {
															// Textcube-NG: 원본은 항상 true 라 실패·빈 응답(DB 잠금, 로그인 만료 등)으로도 목록을 덮어썼다
															// → HTTP 200 이고 목록(<select id="TCfilelist">…</select>)이 끝까지 온 경우에만 바꿈
															var text = this.getText();
															return (this._request.status == 200 && text != null && text.indexOf('id="TCfilelist"') >= 0 && text.indexOf('</select>') >= 0);
														}
														request.onSuccess = function() {
															var fileListObj = document.getElementById("attachManagerSelect");
															fileListObj.innerHTML = this.getText();
															refreshAttachFormSize();
															//getUploadObj().setAttribute('width',1)
															//getUploadObj().setAttribute('height',1)
															
															
															refreshFileSize();						
															entryManager.delay     = true;
															entryManager.nowsaving = false;
														}
														request.onError = function() {
															entryManager.delay     = true;
															entryManager.nowsaving = false;
															PM.showErrorMessage("<?php echo _t('불러오지 못했습니다');?>", "center", "bottom");	// Textcube-NG: 목록은 그대로 둠
														}
														request.send();					
													}
													
													function uploadProgress(target,loaded, total) {
														loaded = Number(loaded);
														total = Number(total);
														var fileListObj = document.getElementById("TCfilelist");					
														for(var i=0; i<fileListObj.length; i++) {
															if (fileListObj[i].getAttribute("value") == target) {
																fileListObj[i].innerHTML = target+" "+(Math.ceil(100*loaded/total))+"%";
																break;
															}
														}
													}
													
													function uploadComplete(target,size) {
														loaded = Number(loaded);
														total = Number(total);
														var fileListObj = document.getElementById("TCfilelist");
														for(var i=0; i<fileListObj.length; i++) {
															if (fileListObj[i].getAttribute("value") == target) {
																fileListObj[i].innerHTML = target+" "+(Math.ceil(100*loaded/total))+"%";
																break;
															}
														}
													}
													
													/**
													*
													*  Base64 encode / decode
													*  http://www.webtoolkit.info/
													*
													**/
													
													var Base64 = {
													
														// private property
														_keyStr : "ABCDEFGHIJKLMNOPQRSTUVWXYZabcdefghijklmnopqrstuvwxyz0123456789+/=",
													
														// public method for encoding
														encode : function (input) {
															var output = "";
															var chr1, chr2, chr3, enc1, enc2, enc3, enc4;
															var i = 0;
													
															input = Base64._utf8_encode(input);
													
															while (i < input.length) {
													
																chr1 = input.charCodeAt(i++);
																chr2 = input.charCodeAt(i++);
																chr3 = input.charCodeAt(i++);
													
																enc1 = chr1 >> 2;
																enc2 = ((chr1 & 3) << 4) | (chr2 >> 4);
																enc3 = ((chr2 & 15) << 2) | (chr3 >> 6);
																enc4 = chr3 & 63;
													
																if (isNaN(chr2)) {
																	enc3 = enc4 = 64;
																} else if (isNaN(chr3)) {
																	enc4 = 64;
																}
													
																output = output +
																this._keyStr.charAt(enc1) + this._keyStr.charAt(enc2) +
																this._keyStr.charAt(enc3) + this._keyStr.charAt(enc4);
													
															}
													
															return output;
														},
													
														// public method for decoding
														decode : function (input) {
															var output = "";
															var chr1, chr2, chr3;
															var enc1, enc2, enc3, enc4;
															var i = 0;
													
															input = input.replace(/[^A-Za-z0-9\+\/\=]/g, "");
													
															while (i < input.length) {
													
																enc1 = this._keyStr.indexOf(input.charAt(i++));
																enc2 = this._keyStr.indexOf(input.charAt(i++));
																enc3 = this._keyStr.indexOf(input.charAt(i++));
																enc4 = this._keyStr.indexOf(input.charAt(i++));
													
																chr1 = (enc1 << 2) | (enc2 >> 4);
																chr2 = ((enc2 & 15) << 4) | (enc3 >> 2);
																chr3 = ((enc3 & 3) << 6) | enc4;
													
																output = output + String.fromCharCode(chr1);
													
																if (enc3 != 64) {
																	output = output + String.fromCharCode(chr2);
																}
																if (enc4 != 64) {
																	output = output + String.fromCharCode(chr3);
																}
													
															}
													
															output = Base64._utf8_decode(output);
													
															return output;
													
														},
													
														// private method for UTF-8 encoding
														_utf8_encode : function (string) {
															string = string.replace(/\r\n/g,"\n");
															var utftext = "";
													
															for (var n = 0; n < string.length; n++) {
													
																var c = string.charCodeAt(n);
													
																if (c < 128) {
																	utftext += String.fromCharCode(c);
																}
																else if((c > 127) && (c < 2048)) {
																	utftext += String.fromCharCode((c >> 6) | 192);
																	utftext += String.fromCharCode((c & 63) | 128);
																}
																else {
																	utftext += String.fromCharCode((c >> 12) | 224);
																	utftext += String.fromCharCode(((c >> 6) & 63) | 128);
																	utftext += String.fromCharCode((c & 63) | 128);
																}
													
															}
													
															return utftext;
														},
													
														// private method for UTF-8 decoding
														_utf8_decode : function (utftext) {
															var string = "";
															var i = 0;
															var c = c1 = c2 = 0;
													
															while ( i < utftext.length ) {
													
																c = utftext.charCodeAt(i);
													
																if (c < 128) {
																	string += String.fromCharCode(c);
																	i++;
																}
																else if((c > 191) && (c < 224)) {
																	c2 = utftext.charCodeAt(i+1);
																	string += String.fromCharCode(((c & 31) << 6) | (c2 & 63));
																	i += 2;
																}
																else {
																	c2 = utftext.charCodeAt(i+1);
																	c3 = utftext.charCodeAt(i+2);
																	string += String.fromCharCode(((c & 15) << 12) | ((c2 & 63) << 6) | (c3 & 63));
																	i += 3;
																}
													
															}
													
															return string;
														}
													
													}


													
													function addFileList(list) {
														
														var list = Base64.decode(list);														
														var fileListObj = document.getElementById("TCfilelist");
														var listTemp = list.split("!^|");
														var fileLabel = listTemp[0];
														var fileValue = listTemp[1];
														for(var i=0; i<fileListObj.length; i++) {
															if (stripLabelToValue(fileLabel).indexOf(fileListObj[i].getAttribute("value")) != -1) {
																var oOption = document.createElement("option");
																oOption.innerHTML= fileLabel;
																oOption.setAttribute("value",fileValue);
																fileListObj.replaceChild(oOption,fileListObj[i]);
																break;
															}
														}
													}
													
													function newLoadItem(fileValue) {
														var fileListObj = document.getElementById("TCfilelist");
														for(var i=0; i<fileListObj.length; i++) {
															if (fileValue.indexOf(fileListObj[i].getAttribute("value")) != -1) {
																fileListObj[i].style.backgroundColor="#C8DAF3";
																break;
															}
														}	
													}
													

													
													function selectFileList(value) {
														selectedFiles = value.split("!^|");
														var fileListObj = document.getElementById("TCfilelist");
														for(var i=0; i<fileListObj.length; i++) {
															for(var j=0; j<selectedFiles.length; j++) {
																if (fileListObj[i].getAttribute("value") == selectedFiles[j]) {
																	fileListObj[i].setAttribute("selected","selected");
																	selectAttachment();
																	break;
																}
																
																fileListObj[i].setAttribute("selected","");							
															}
														}
														refreshAttachFormSize();
													}
													
													function disabledDeleteBtn() {
														if(document.getElementById('TCfilelist').length>0) {
															document.getElementById('deleteBtn').disabled = false;
														} else {
															document.getElementById('deleteBtn').disabled = true;
														}
													}
													
													function removeUploadList(list) {
														selectedFiles = list.split("!^|");
														var fileListObj = document.getElementById("TCfilelist");
														for(var j=0; j<selectedFiles.length; j++) {
															for(var i=0; i<fileListObj.length; i++) {						
																if(selectedFiles[j] == undefined) 
																	continue;
																if (fileListObj[i].getAttribute("value") == selectedFiles[j]) {								
																	fileListObj.remove(i);
																	break;
																}
															}
														}
														refreshAttachFormSize();
													}
													


													function refreshFileSize() {
														try {
															var request = new HTTPRequest("POST", "<?php echo $param['fileSizePath'];?>"+entryManager.entryId);
															// Textcube-NG: 원본은 onVerify 가 항상 true 라 실패하면 '총 용량: null' → 기본 onVerify(/response/error == 0), 실패하면 기존 표시 유지
															
															request.onSuccess = function() {
																try {
																	var result = this.getText("/response/result");
																	document.getElementById('fileSize').innerHTML = '<?php echo _t('총 용량');?>: ' + result;
																} catch(e) {
																
																}
															}
															
															request.onError = function() {
															}															
															request.send();
															
														} catch(e) {
															PM.showErrorMessage(String(e.message).replace(/[&<>"']/g, function(c) { return {"&": "&amp;", "<": "&lt;", ">": "&gt;", "\"": "&quot;", "'": "&#39;"}[c]; }), "center", "bottom");
														}
													}
 
													refreshAttachFormSize();
												//]]>
											</script>
											
<?php
	$maxSize = min( return_bytes(ini_get('upload_max_filesize')) , return_bytes(ini_get('post_max_size')) );
?>

												<script type="text/javascript">
													//<![CDATA[
													var html5UploadXhr = null;
													var html5UploadAborted = false;
													var html5UploadSvgWarn = [];	// Textcube-NG: svg 외부 참조를 지운 파일 알림(묶음 끝에 알림, r3-20)
													var html5UploadFailed = [];	// Textcube-NG: 첨부하지 못한 파일 이름(묶음 끝에 알림)

													function reloadUploader() {
														var uploaderHtml = '<div id="html5Uploader">'
														    + '<label class="input-button" style="cursor:pointer;display:inline-block;"><?php echo _t('파일 업로드');?>'
														    + '<input type="file" id="html5FileInput" multiple style="display:none" /><\/label>'
														    + '<\/div>';
														writeCode(uploaderHtml, 'uploaderNest');
														var progressArea = document.getElementById('uploadProgressNest');
														if (progressArea) {
															progressArea.innerHTML = '<div id="html5UploadStatus" style="font-size:0.9em;color:#555;"><\/div>'
															    + '<div id="html5UploadBar" style="display:none;margin-top:4px;height:5px;background:#ddd;border-radius:3px;">'
															    + '<div id="html5UploadBarFill" style="height:5px;background:#4a90d9;width:0%;border-radius:3px;"><\/div><\/div>'
															    + '<button type="button" id="html5StopBtn" onclick="stopHtml5Upload()" class="input-button" style="display:none;margin-top:4px;"><?php echo _t('업로드 중지');?><\/button>';
														}
														document.getElementById('html5FileInput').addEventListener('change', function() {
															startHtml5Upload(this.files);
														});
														refreshUploadButton();
													}

													function stopHtml5Upload() {
														html5UploadAborted = true;
														if (html5UploadXhr) { html5UploadXhr.abort(); }
													}

													function startHtml5Upload(files) {
														if (!files || files.length === 0) return;
														html5UploadAborted = false;
														html5UploadFailed = [];
														html5UploadSvgWarn = [];	// Textcube-NG: r3-20
														document.getElementById('html5StopBtn').style.display = '';
														uploadNextHtml5File(files, 0, files.length);
													}

													function uploadNextHtml5File(files, index, total) {
														if (html5UploadAborted || index >= total) {
															document.getElementById('html5StopBtn').style.display = 'none';
															document.getElementById('html5UploadBar').style.display = 'none';
															document.getElementById('html5UploadStatus').innerHTML = '';
															refreshAttachList();
															if (html5UploadFailed.length > 0) {
																PM.showErrorMessage("<?php echo _t('첨부하지 못했습니다.');?><br /><br />" + String(html5UploadFailed.join("\n")).replace(/[&<>"']/g, function(c) { return {"&": "&amp;", "<": "&lt;", ">": "&gt;", "\"": "&quot;", "'": "&#39;"}[c]; }).replace(/\n/g, "<br />"), "center", "bottom");
																html5UploadFailed = [];
															}
															if (html5UploadSvgWarn.length > 0) {	// Textcube-NG: svg 외부 참조를 지운 파일을 묶음 끝에 알림(r3-20, 파일 이름은 사용자 PC 의 이름이라 escape)
																PM.showErrorMessage(String(html5UploadSvgWarn.join("\n")).replace(/[&<>"']/g, function(c) { return {"&": "&amp;", "<": "&lt;", ">": "&gt;", "\"": "&quot;", "'": "&#39;"}[c]; }).replace(/\n/g, "<br />"), "center", "bottom", 8000);
																html5UploadSvgWarn = [];
															}
															return;
														}
														var file = files[index];
														var statusEl = document.getElementById('html5UploadStatus');
														var barFill  = document.getElementById('html5UploadBarFill');
														document.getElementById('html5UploadBar').style.display = '';
														// Textcube-NG: 파일 이름은 사용자 PC 의 이름 그대로라 innerHTML 이 아닌 textContent 로 넣는다
														statusEl.textContent = (index + 1) + ' / ' + total + ': ' + file.name + ' 0%';
														barFill.style.width = '0%';

														// 파일 목록에 진행 중 항목 추가 (원본 uploadProgress 방식과 동일)
														var fileListObj = document.getElementById('TCfilelist');
														var tempOpt = document.createElement('option');
														var shortName = file.name.replace(/.*[\/\\]/, '');
														tempOpt.value = '__uploading__' + index;
														tempOpt.textContent = shortName + ' <?php echo _t('업로드 중..');?> 0%';
														tempOpt.style.backgroundColor = '#A4C3F0';
														if (fileListObj) { fileListObj.appendChild(tempOpt); fileListObj.setAttribute('size', Math.max(8, Math.min(fileListObj.length, 30))); }

														var formData = new FormData();
														formData.append('Filedata', file);
														var xhr = new XMLHttpRequest();
														html5UploadXhr = xhr;
														xhr.upload.onprogress = function(e) {
															if (e.lengthComputable) {
																var pct = Math.round(e.loaded / e.total * 100);
																barFill.style.width = pct + '%';
																statusEl.textContent = (index + 1) + ' / ' + total + ': ' + file.name + ' ' + pct + '%';
																if (tempOpt) tempOpt.textContent = shortName + ' ' + pct + '%';
															}
														};
														// Textcube-NG: 응답 상태를 보지 않고 성공으로 표시했다 → HTTP 200 + &success 일 때만 성공, 그 밖에는 실패로 모음
														xhr.onload = function() {
															if (xhr.status == 200 && xhr.responseText.indexOf('&success') >= 0) {
																barFill.style.width = '100%';
																if (tempOpt) { tempOpt.textContent = shortName + ' ✓'; tempOpt.style.backgroundColor = '#C8DAF3'; }
																var svgExt = /&svgext=(\d+)/.exec(xhr.responseText);	// Textcube-NG: 응답 &success&svgext=N 의 N(svg 에서 지운 외부 참조 수, r3-20). 성공 판정(indexOf('&success'))은 그대로
																if (svgExt && parseInt(svgExt[1], 10) > 0) html5UploadSvgWarn.push(shortName + ': ' + "<?php echo escapeJSInCData(_t('외부 참조 %1개를 지웠습니다. 이미지가 원본과 다르게 보일 수 있습니다.'));?>".replace('%1', parseInt(svgExt[1], 10)));
																uploadNextHtml5File(files, index + 1, total);
															} else {
																xhr.onerror();
															}
														};
														xhr.onerror = function() {
															html5UploadFailed.push(shortName);
															statusEl.textContent += ' — <?php echo _t('오류');?>';
															if (tempOpt) { tempOpt.textContent = shortName + ' <?php echo _t('오류');?>'; tempOpt.style.backgroundColor = '#fcc'; }
															uploadNextHtml5File(files, index + 1, total);
														};
														// Textcube-NG: 중지(abort())는 load·error 가 아니라 abort 이벤트 → 다음 단계(중지 버튼 숨김·목록 갱신)로 넘김
														xhr.onabort = function() {
															uploadNextHtml5File(files, index + 1, total);
														};
														xhr.open('POST', '<?php echo $param['uploadPath'];?>' + entryManager.entryId);
														xhr.send(formData);
													}
													//]]>
													</script>
											<div id="uploaderNest">
											</div>
<?php
}

function printEntryFileUploadButton($entryId) {
	$context = Model_Context::getInstance();

	$blogid = getBlogId();
?>

											<script type="text/javascript">
												//<![CDATA[

													var fileUploadNestOriginal = false;													


													function refreshUploadButton() {
														// HTML5 uploader: always available, no Flash detection needed
													}
													//]]>
											</script>
										<div id="fileUploadNest" class="container">
											<div id="uploadProgressNest"></div>
											<div id="fileSize"><?php echo _t('총 용량'); ?>: <?php echo getAttachmentSizeLabel($blogid, $entryId); ?></div>
											<div id="fileDownload" class="system-message" style="display: none;"></div>
										</div>
										<script type="text/javascript">
										//<![CDATA[
												var deleteNest = document.createElement('div');
												deleteNest.id = 'deleteNest';
												deleteNest.innerHTML = '<input type="button" id="deleteBtn" class="input-button" value="<?php echo _t('삭제하기');?>" onclick="deleteAttachment();return false" />';
												document.getElementById('uploaderNest').insertAdjacentElement('afterend', deleteNest);
												//]]>
										</script>
<?php
}

function getAttachmentValue($attachment) {
	global $g_attachmentFolderPath;
	if (strpos($attachment['mime'], 'image') === 0) {
		return "{$attachment['name']}|width=\"{$attachment['width']}\" height=\"{$attachment['height']}\" alt=\"" . _text('사용자 삽입 이미지') . "\"";		
	} else {
		return "{$attachment['name']}|";
	}
}

function getPrettyAttachmentLabel($attachment) {
	if (strpos($attachment['mime'], 'image') === 0)
		return "{$attachment['label']} ({$attachment['width']}x{$attachment['height']} / ".Misc::getSizeHumanReadable($attachment['size']).')';
	else if(strpos($attachment['mime'], 'audio') !== 0 && strpos($attachment['mime'], 'video') !== 0) {
		if ($attachment['downloads']>0)
			return "{$attachment['label']} (".Misc::getSizeHumanReadable($attachment['size']).' / '._t('다운로드').':'.$attachment['downloads'].')';		
	}
	return "{$attachment['label']} (".Misc::getSizeHumanReadable($attachment['size']).')';
}
?>
