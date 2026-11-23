<?php
/* Textcube-NG: 직접 SQL(POD 문자열 SQL)을 준비 문장(POD::prepare·bindAndExecute)으로 바꿨다. 값은 SQL 문장과 따로 보낸다(PDO 모드 호환)(v3.211)
   행은 열 이름 키 배열(fetchAllStmt)이다. 실패하면 원래 POD 함수와 같이 null·false·0을 돌려준다. */
function ST_TeamBlog_preparedRows($query, $types, $params) {
	$stmt = POD::prepare($query);
	if (!$stmt) return null;
	$rows = call_user_func_array(array('POD', 'bindAndExecute'), array_merge(array($stmt, $types), $params)) ? POD::fetchAllStmt($stmt) : null;
	$stmt->close();
	return $rows;
}

function ST_TeamBlog_preparedRow($query, $types, $params) {
	$rows = ST_TeamBlog_preparedRows($query, $types, $params);
	return empty($rows) ? null : $rows[0];
}

function ST_TeamBlog_preparedCell($query, $types, $params) {
	$rows = ST_TeamBlog_preparedRows($query, $types, $params);
	if (empty($rows)) return null;
	$row = array_values($rows[0]);
	return $row[0];
}

function ST_TeamBlog_preparedExecute($query, $types, $params) {
	$stmt = POD::prepare($query);
	if (!$stmt) return false;
	$result = call_user_func_array(array('POD', 'bindAndExecute'), array_merge(array($stmt, $types), $params)) ? true : false;
	$stmt->close();
	return $result;
}

function getTeamBlogInitConfigVal( &$data ){
	// Textcube-NG: 기본 legend 가 영어로 고정되어 index.xml(ko 작성자 프로필 / en Writer profile / ja 作成者紹介)과 달랐다.
	// 블로그 언어(없으면 service.language)의 앞 2글자로 index.xml 과 같은 값을 고르고, 그 밖의 언어는 en 값을 쓴다(v3.190)
	$postHeaderContext = Model_Context::getInstance();
	$postHeaderLanguage = $postHeaderContext->getProperty('blog.blogLanguage') !== null ? $postHeaderContext->getProperty('blog.blogLanguage') : $postHeaderContext->getProperty('service.language');
	$postHeaderLegends = array('ko' => '작성자 프로필', 'ja' => '作成者紹介');
	$postHeaderLanguage = strtolower(substr((string)$postHeaderLanguage, 0, 2));
	$postHeaderDefault = '<fieldset><legend>' . (isset($postHeaderLegends[$postHeaderLanguage]) ? $postHeaderLegends[$postHeaderLanguage] : 'Writer profile') . '</legend>';
	$postFooterDefault = '</fieldset>';
	$data['p1'] = !isset($data['p1'])?true:$data['p1'];
	$data['postHeader']	= !isset($data['postHeader'])?$postHeaderDefault:$data['postHeader'];
	$data['postFooter']	= !isset($data['postFooter'])?$postFooterDefault:$data['postFooter'];
	$data['imageSize']	= !isset($data['imageSize'])?80:$data['imageSize'];
	$data['imagePosition']	= !isset($data['imagePosition'])?'left':$data['imagePosition'];
	$data['lineColor']	= !isset($data['lineColor'])?'#e3e3e3':$data['lineColor'];
	$data['cssSelect']	= !isset($data['cssSelect'])?1:$data['cssSelect'];
}

function getTeamAuthorStyle($target, $mother){
	global $database, $entry;
	$row = ST_TeamBlog_preparedRow("SELECT style, image, profile FROM {$database['prefix']}TeamUserSettings WHERE blogid =? AND userid=?", 'ii', array(getBlogId(), $entry['userid']));
	$authorStyle = '';
	if($row['style']){
		$style = explode("|", $row['style']);
		if($style[0]=="true"){
			$authorStyle = "font-weight:bold;";
		}
		if($style[1]=="true"){
			$authorStyle .= "font-style:italic;";
		}
		if($style[2]=="true"){
			$authorStyle .= "text-decoration:underline;";
		}
		if($style[3]){
			$authorStyle .= "color:{$style[3]};";
		}
		if($style[4]){
			$authorStyle .= "font-family:{$style[4]};";
		}
		if($style[5]){
			$authorStyle .= "font-size:{$style[5]}pt;";
		}
	}
	$target = "<span style=\"{$authorStyle}\">".$target."</span>";
	return $target;
}

function getTeamProfileView($target, $mother){
	global $suri, $entry, $entryView, $configVal;
	requireComponent('Textcube.Function.misc');
	$data = Setting::fetchConfigVal($configVal);
	getTeamBlogInitConfigVal($data);
	if ($suri['directive'] != "/rss" && $suri['directive'] != "/sync" && $data['p1'] && empty($data['p2']) ) {
		// Textcube-NG: /atom 등 $entry 가 null 인 경로의 PHP 7.4 Notice·8.x Warning 방지(null 병합 연산자, PHP 7.0+, r3-36 R-560)
		$target .= getTeamProfile(isset($entry['userid']) ? $entry['userid'] : null);
	}
	if ($suri['directive'] != "/rss" && $suri['directive'] != "/sync" && $data['p1'] && !empty($data['p2']) ) {
		Misc::dress('TeamBlogProfileTag', getTeamProfile(isset($entry['userid']) ? $entry['userid'] : null), $entryView);
	}
	return $target;
}

function getTeamProfile($userid){
	if (is_null($userid)) return '';	// Textcube-NG: r3-44 R-567 다른 트리와 같게 null 이면 질의 없이 빈 문자열(php7.4 만 빠져 있었음)
	global $database, $serviceURL, $configVal;
	requireComponent('Textcube.Function.misc');
	$data = Setting::fetchConfigVal($configVal);
	getTeamBlogInitConfigVal($data);
	$row = ST_TeamBlog_preparedRow("SELECT style, image, profile FROM {$database['prefix']}TeamUserSettings WHERE blogid =? AND userid=?", 'ii', array(getBlogId(), $userid));
	$imageStyle = $imageTag = $html = '';
	if(!empty($row['image'])){
		$imageSrc = "{$serviceURL}/attach/".getBlogId()."/team/".$row['image'];
		$imageTag = "<img src=\"".$imageSrc."\" alt=\"author image\" align=\"top\" />";
		$imageStyle = "style=\"width:".($data['imageSize']+6)."px; margin-right:10px;\"";
	}
	if(!empty($row['image']) || !empty($row['profile'])){
		$profile = nl2br(addLinkSense(htmlspecialchars($row['profile'], ENT_QUOTES | ENT_SUBSTITUTE), ' onclick="return openLinkInNewWindow(this)"'));
		$html  = "<div class=\"teamProfile\">";
		$html .= $data['postHeader'];
		$html .= "<div class=\"teamMain\">";
		$html .= "<div class=\"teamImage\" {$imageStyle}>".$imageTag."</div>";
		$html .= "<div class=\"teamDesc\">".$profile."</div>";
		$html .= "</div>";
		$html .= $data['postFooter'];
		$html .= "</div>";
	}
	return $html;
}

function getTeamBlogSettings() {
	global $database, $service, $serviceURL, $pluginURL, $configVal, $blogURL;
	requireComponent('Textcube.Function.misc');
	$data = Setting::fetchConfigVal($configVal);
	getTeamBlogInitConfigVal($data);
	// Textcube-NG: JS 의 _t('fontDisplayName:fontCode:fontFamily') 는 window.__text 에서 값을 찾는데 이 키가 넘어가지 않아
	// locale 에 폰트 목록을 넣어도 쓰이지 않았다. 넘기는 문구 목록에 키를 더한다(v3.190)
	$teamBlogTexts = array('정말 삭제하시겠습니까?', '필명 스타일이 저장 되었습니다.', '프로필 설명이 저장 되었습니다.', '저장하지 못했습니다. 다시 시도 해주세요.', 'fontDisplayName:fontCode:fontFamily');
	$teamBlogTable = array();
	foreach ($teamBlogTexts as $teamBlogText) {
		$teamBlogTable[$teamBlogText] = _t($teamBlogText);
	}
	$teamBlogJson = jsonEncodeCompat($teamBlogTable, JSON_FORCE_OBJECT | JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_UNESCAPED_UNICODE);	// Textcube-NG: JSON_INVALID_UTF8_SUBSTITUTE(7.2) 대신 jsonEncodeCompat()(00-Compat.php, r4-4)
	if ($teamBlogJson === false) {
		$teamBlogJson = '{}';
	}
?>
	<script type="text/javascript">
	(function(t){var own=function(o,k){return Object.prototype.hasOwnProperty.call(o,k);};var d=window.__text||(window.__text={});for(var k in t){if(own(t,k)&&!own(d,k))d[k]=t[k];}if(typeof window._t!=='function'){window._t=function(m){var x=window.__text;return(x&&own(x,m)&&x[m])||m;};}})(<?php echo $teamBlogJson;?>);
	</script>
	<script type="text/javascript" src="<?php echo $pluginURL;?>/plugin-main.js"></script>
<?php
	$teamblog_user = ST_TeamBlog_preparedRow("SELECT name, loginid FROM {$database['prefix']}Users WHERE userid=?", 'i', array(getUserId()));
	$row = ST_TeamBlog_preparedRow("SELECT style, image, profile FROM {$database['prefix']}TeamUserSettings WHERE blogid =? and userid=?", 'ii', array(getBlogId(), getUserId()));
	if(!$row){
		ST_TeamBlog_preparedExecute("INSERT INTO {$database['prefix']}TeamUserSettings (blogid,userid,style,image,profile,updated) VALUES(?,?,'','', '',UNIX_TIMESTAMP())", 'ii', array(getBlogId(), getUserId()));
	}
	if($row['image']){
		$image = "{$service['path']}/attach/".getBlogId()."/team/".$row['image'];
		$imageRemoveCheck = "";
	}else{
		$image = "{$service['path']}/resources/image/spacer.gif";
		$imageRemoveCheck = " disabled ";
	}

	$authorStyle = "";
	$italicCheck = "";
	$underlineCheck = "";
	$boldCheck = "";
	$color = "";
	if($row['style']){
		$style = explode("|", $row['style']);
		if(isset($style[0]) && ($style[0]=="true")) {
			$authorStyle = "font-weight:bold;";
			$boldCheck = " checked ";
		}
		if(isset($style[1]) && ($style[1]=="true")) {
			$authorStyle .= "font-style:italic;";
			$italicCheck = " checked ";
		}
		if(isset($style[2]) && ($style[2]=="true")) {
			$authorStyle .= "text-decoration:underline;";
			$underlineCheck = " checked ";
		}
		if(isset($style[3]) && !empty($style[3])){
			$authorStyle .= "color:{$style[3]};";
			$color = $style[3];
		}
		if(isset($style[4]) && !empty($style[4])){
			$authorStyle .= "font-family:{$style[4]};";
			$family = explode(",",$style[4]);
			
		}
		if(isset($style[5]) && !empty($style[5])){
			$authorStyle .= "font-size:{$style[5]}pt;";
			$size = $style[5];
			
		}
	} else {
		$style = array();
	}
	$profile = "";
	if($row['profile']){
		$profile = $row['profile'];
	}
?>
	<div id="part-setting-teams" class="part">
		<h2 class="caption"><span class="main-text"><?php echo _t('팀블로그 정보를 설정합니다');?></span></h2>
		
		<div class="data-inbox">
				<div  class="sectionGroup">
					<fieldset class="container">
						<legend><?php echo _t('필명 설정');?></legend>
						<dl id="team-style-line" class="line">
							<dt><label for="title"><?php echo _t('필명 스타일');?></label></dt>
							<dd>by <span id="nicknameStyle" style="<?php echo $authorStyle;?>"><?php echo htmlspecialchars($teamblog_user['name'], ENT_QUOTES | ENT_SUBSTITUTE);?></span></dd>
							<dd>
								<span><input <?php echo $boldCheck;?> type="checkbox" value="1" id="fontBold" onclick="styleExecCommand('fontBold', 'fontbold', 'bold');" /> <label for="fontBold"><b><?php echo _t('굵게'); ?></b></label></span>
								<span><input <?php echo $italicCheck;?> type="checkbox" id="fontItalic" onclick="styleExecCommand('fontItalic', 'fontitalic', 'italic');" /> <label for="fontItalic"><i><?php echo _t('기울임'); ?></i></label></span>
								<span><input <?php echo $underlineCheck;?> type="checkbox" id="fontUnderline" onclick="styleExecCommand('fontUnderline', 'fontunderline', 'underline');" /> <label for="fontUnderline"><u><?php echo _t('밑줄'); ?></u></label></span>
								<span id="fontStyleElement"></span>
								<script type="text/javascript">
										//<![CDATA[
											var colorCheck;
											var familyCheck;
											var sizeCheck;
											var html = ////
												'<input type="text" id="fontColor" class="input-text2" style="<?php echo empty($color) ? '' : 'color:' . htmlspecialchars($color, ENT_QUOTES, 'UTF-8'); ?>;" value="<?php echo htmlspecialchars($color, ENT_QUOTES, 'UTF-8');?>" />' +
												'<select id="fontColorList" style="width:80px;height:19px;" onchange="styleExecCommand(\'fontColor\', \'fontcolor\', this.value);">' +
													'<option class="head-option" value=""><?php echo _t('글자색'); ?><\/option>';
											for (var i = 0; i < colors.length; ++i) {
												<?php
													if(isset($style[3]) && !empty($style[3])) echo "colorCheck = (colors[i] == '".preg_replace('/[^0-9a-fA-F]/',"",str_replace("#","",$color))."')?' selected ':'';";
												?>
												html += '<option style="color:#' + colors[i] + ';" value="#' + colors[i] + '" ' + colorCheck + '>#' + colors[i] + '<\/option>';
											}
											html += '<\/select>';
											
											html += ////
												'<select id="fontFamilyList" style="width:120px;height:19px;" onchange="styleExecCommand(\'\', \'fontname\', this.value); ">' +
													'<option class="head-option" value=""><?php echo _t('글자체'); ?><\/option>';
											var fontset = _t('fontDisplayName:fontCode:fontFamily').split('|');
											for (var i = 1; i < fontset.length; ++i) {
												var fontinfo = fontset[i].split(':');
												if (fontinfo.length != 3) continue;
												<?php
													if(isset($style[4]) && !empty($style[4])) echo "familyCheck = (fontinfo[1] == ".json_encode($family[0]).")?' selected ':'';";
												?>
												html += '<option style="font-family: \'' + fontinfo[1] + '\';" value="\'' + fontinfo[1] + '\', \'' + fontinfo[2] + '\'"' + familyCheck + '>' + fontinfo[0] + '<\/option>';
											}
											for (var i = 0; i < defaultfonts.length; ++i) {
												var entry = defaultfonts[i];
												<?php
													if(isset($style[4]) && !empty($style[4])) echo "familyCheck = (entry[0] == ".json_encode($family[0]).")?' selected ':'';";
												?>
												html += '<option style="font-family: \'' + entry[0] + '\';" value="\'' + entry.join("','") + '\'" ' + familyCheck + '>' + entry[0] + '<\/option>';
											}
											html += '<\/select>';

											html += ////
												'<select id="fontSizeList" style="width:50px;height:19px;" onchange="styleExecCommand(\'\', \'fontsize\', this.value); ">' +
													'<option class="head-option" value=""><?php echo _t('크기'); ?><\/option>';
											for (var i = 8; i < 16; ++i) {
												<?php
													if(isset($style[5]) && !empty($style[5])) echo "sizeCheck = (i == ".(int)$size.")?' selected ':'';";
												?>
												html += '<option value="' + i + '" ' + sizeCheck + '>' + i + 'pt<\/option>';
											}
											html +=	'<\/select>';
											document.getElementById('fontStyleElement').innerHTML = html;
										//]]>
								</script>
							</dd>
						</dl>
						<dl>
							<dd class="button-box">
								<input type="submit" class="save-button input-button" value="<?php echo _t('저장하기');?>" onclick="setStyleSave(); return false;" />
							</dd>
						</dl>
						<dl id="team-image-line" class="line">
							<dt><label for="profile"><?php echo _t('프로필 사진');?></label></dt>
							<dd>
								<img id="teamImage" src="<?php echo $image;?>" width="80" height="80" border="1" alt="<?php echo _t('프로필 사진');?>" /><br />(Size : <?php echo $data['imageSize']?> x <?php echo $data['imageSize']?>)<br />
								<form id="file_upload_form" method="post" target="uploadTarget" enctype="multipart/form-data" action="<?php echo $blogURL;?>/plugin/teamFileUpload/">
									<input type="hidden" name="type" value="" />
									<input type="file" name="teamImageFile" id="teamImageFile" size="35" onchange="uploadImage(this.form, 'upload');" /> <a href="http://mypictr.com/?size=<?php echo $data['imageSize']?>x<?php echo $data['imageSize']?>" onclick="window.open(this.href); return false;"><font color="#527A98"><u>mypictr.com</u></font></a>에서 사진 편집.<br />
									<div class="tip"><?php echo _t('(찾아보기를 이용하여 사진을 선택하시면 바로 <b>변경</b> 저장됩니다)'); ?></div>
									<div class="tip">
										<input type="checkbox" name="imageRemove" id="imageRemove" onclick="uploadImage(this.form, 'delete');" <?php echo $imageRemoveCheck;?> />
										<label for="imageRemove"><?php echo _t('프로필 사진 초기화');?></label>
									</div>
									<iframe id="uploadTarget" name="uploadTarget" style="width:0;height:0;border:0px solid;"></iframe>
								</form>
						</dd>
						</dl>
						<dl id="team-profile-line" class="line">
							<dt><label for="profile"><?php echo _t('프로필 내용');?></label></dt>
							<dd>
								<textarea id="profile" name="profile" cols="15" rows="7"><?php echo htmlspecialchars($profile, ENT_QUOTES | ENT_SUBSTITUTE);?></textarea>
							</dd>
						</dl>
						<dl>
							<dd class="button-box">
								<input type="submit" class="save-button input-button" value="<?php echo _t('저장하기');?>" onclick="setProfileSave(); return false;" />
							</dd>
						</dl>
					</fieldset>
				</div>
		</div>
	</div>
	
	<div class="clear"></div>

<?php 
}

function getTeamContentsSave($target){
	global $database;
	$flag = isset($_POST['flag']) ? $_POST['flag'] : '';
	$style = isset($_POST['fontstyle']) ? $_POST['fontstyle'] : ''; // Textcube-NG: 값은 준비 문장으로 따로 보내므로 escapeString을 쓰지 않는다(v3.211)
	$profile = isset($_POST['profile']) ? $_POST['profile'] : '';
	if(doesHaveOwnership() && doesHaveMembership()){
		// Textcube-NG: 상태 변경(저장·업로드)은 같은 블로그 Referer 와 CSRF 토큰을 요구한다 — blog 인터페이스(/plugin/…)라
		// preprocessor 의 owner/reader 검사가 적용되지 않아 다른 사이트가 소유자 브라우저로 요청할 수 있었다(v3.147)
		requireStrictRoute();
		requireCSRFToken();
		if($flag == "style"){
			if(ST_TeamBlog_preparedExecute("UPDATE {$database['prefix']}TeamUserSettings SET style=?, updated=UNIX_TIMESTAMP() WHERE blogid=? and userid=?", 'sii', array($style, (int)getBlogId(), (int)getUserId()))){
				Respond::ResultPage(0);
			}
		}else if($flag == "profile"){
			$profile = UTF8::lessenAsEncoding($profile, 65535); // Textcube-NG: 값은 준비 문장으로 따로 보내므로 escapeString을 쓰지 않는다(v3.211)
			if(ST_TeamBlog_preparedExecute("UPDATE {$database['prefix']}TeamUserSettings SET profile=?, updated=UNIX_TIMESTAMP() WHERE blogid=? and userid=?", 'sii', array($profile, (int)getBlogId(), (int)getUserId()))){
				Respond::ResultPage(0);
			}
		}
		Respond::ResultPage(-1);
	}
}

function getImageFileUpload($target){
	global $database;
	if(doesHaveOwnership() && doesHaveMembership()){
		// Textcube-NG: 상태 변경(저장·업로드)은 같은 블로그 Referer 와 CSRF 토큰을 요구한다 — blog 인터페이스(/plugin/…)라
		// preprocessor 의 owner/reader 검사가 적용되지 않아 다른 사이트가 소유자 브라우저로 요청할 수 있었다(v3.147)
		requireStrictRoute();
		requireCSRFToken();
		$type = $_POST['type'];
		$file = $_FILES['teamImageFile'];
		$errcode = 0;
		if($type == "upload"){
			$fileExt=Path::getExtension($file['name']);
			if(($fileExt!='.gif')&&($fileExt!='.jpg')&&($fileExt!='.png')){
				$errmsg = _t('잘못된 파일 형식입니다. 다시 시도하세요');
				$errcode = 1;
			}else{
				// Textcube-NG: 그림 검사(r3-20) — 크기·가로세로(4096)·확장자 대 실제 형식 불일치는 거부한다(Attachment::classifyUpload, 글 첨부와 같은 기준).
				// 업로드 오류(이름 없음·error 값)는 원본 흐름(getAddAttachment 가 false)대로 둔다
				requireComponent('Textcube.Data.Attachment');
				$imageCheck = (empty($file['name']) || ($file['error'] != 0)) ? null : Attachment::classifyUpload($file['tmp_name'], Path::getBaseName($file['name']), $file['size'], false);
				if(is_array($imageCheck) && (!$imageCheck['ok'] || (($imageCheck['kind'] === 'svg') && ($imageCheck['error'] === 'svgInvalid')))){
					$errmsg = _t('잘못된 파일 형식입니다. 다시 시도하세요');
					$errcode = 1;
				}else{
					$result = getAddAttachment($file, $imageCheck);
					$errmsg = _t('새로운 프로필 사진을 저장 했습니다.');
				}
			}
		}else if($type == "delete"){
			$tmpImage = ST_TeamBlog_preparedCell("SELECT image FROM {$database['prefix']}TeamUserSettings WHERE blogid=? and userid=?", 'ii', array(getBlogId(), getUserId()));
			if($tmpImage){
				$result = getDeleteAttachment();
				$errmsg = _t('등록된 프로필 사진을 삭제 하였습니다.');
			}else{
				$errmsg = _t('삭제할 파일이 없습니다. 다시 시도하세요');
				$errcode = 1;
			}
		}
	}
	
	$script  = '<script type="text/javascript">//<![CDATA'.CRLF;
	if($errcode != 1){
		$script .= '	window.parent.top.document.getElementById("teamImage").src = "'.$result.'";';
	}
	$script .= '	window.parent.top.PM.showMessage("'.$errmsg.'", "center", "bottom");';
	$script .= '//]]></script>';
	echo $script;
	exit;
}

function getAddAttachment($file, $imageCheck = null){
	global $database, $serviceURL;
	requireComponent('Textcube.Function.misc');
	requireComponent('Textcube.Data.Attachment');
	Attachment::confirmFolder();
	if(empty($file['name'])||($file['error']!=0))
		return false;
	$attachment = array();
	$attachment['ext'] = Misc::getFileExtension(Path::getBaseName($file['name']));
	$path = __TEXTCUBE_ATTACH_DIR__."/".getBlogId()."/team";
	if(!is_dir($path)){
		mkdir($path);
		if(!is_dir($path))
			return false;
		@chmod($path,0777);
	}
	do{
		$attachment['name']=random_int(1000000000,9999999999).".".$attachment['ext'];
		$attachment['path']="$path/{$attachment['name']}";
	}while(file_exists($attachment['path']));

	if(!move_uploaded_file($file['tmp_name'],$attachment['path']))
		return false;
	@chmod($attachment['path'],0666);
	// Textcube-NG: 일반 그림은 설정 imageReencode 가 true 일 때만 다시 인코딩한다(r3-20, $imageCheck = Attachment::classifyUpload 결과)
	if(is_array($imageCheck) && ($imageCheck['kind'] === 'raster') && Attachment::reencodeImage($attachment['path'], $imageCheck['type']))
		@chmod($attachment['path'],0666);
	$tmpImage = ST_TeamBlog_preparedCell("SELECT image FROM {$database['prefix']}TeamUserSettings WHERE blogid=? and userid=?", 'ii', array(getBlogId(), getUserId()));
	if(!ST_TeamBlog_preparedExecute("UPDATE {$database['prefix']}TeamUserSettings SET image=?, updated=UNIX_TIMESTAMP() WHERE blogid=? and userid=?", 'sii', array($attachment['name'], getBlogId(), getUserId()))){
		@unlink($attachment['path']);
		$result = "{$serviceURL}/resources/image/spacer.gif";
	}else{
		$result = "{$serviceURL}/attach/".getBlogId()."/team/".$attachment['name'];
	}
	if(!empty($tmpImage))
	@unlink($path."/".$tmpImage);
	return $result;
}

function getDeleteAttachment($filename){
	global $database, $serviceURL;
	$tmpImage = ST_TeamBlog_preparedCell("SELECT image FROM {$database['prefix']}TeamUserSettings WHERE blogid=? and userid=?", 'ii', array(getBlogId(), getUserId()));
	if($tmpImage){
		ST_TeamBlog_preparedExecute("UPDATE {$database['prefix']}TeamUserSettings SET image='', updated=UNIX_TIMESTAMP() WHERE blogid=? and userid=?", 'ii', array(getBlogId(), getUserId()));
		@unlink(__TEXTCUBE_ATTACH_DIR__."/".getBlogId()."/team/".$tmpImage);
	}
	$result = "{$serviceURL}/resources/image/spacer.gif";
	return $result;
}

function getTeamBlogStyle($target) {
	global $blogURL, $configVal;
	requireComponent('Textcube.Function.misc');
	$data = Setting::fetchConfigVal($configVal);
	getTeamBlogInitConfigVal($data);
	if($data['cssSelect'] == 1){
		$target .= "<link rel=\"stylesheet\" type=\"text/css\" href=\"{$blogURL}/plugin/teamBlogStyle/\" />".CRLF;
	}
	return $target;
}

function getTeamBlogStyleSet($target){
	global $pluginURL, $configVal;
	requireComponent('Textcube.Function.misc');
	$data = Setting::fetchConfigVal($configVal);
	getTeamBlogInitConfigVal($data);
	$lineColor = (strpos($data['lineColor'], "#")===0)?$data['lineColor']:"#".$data['lineColor'];
	header('Content-type: text/css; charset=utf-8');
	echo '
		/* Team Blog Profile CSS Setting */
		.teamProfile		{ margin:10px 0px 0px 0px;font:8.5pt dotum, AppleGothic;}
		.teamProfile fieldset{ margin:0px; border:1px solid '.$lineColor.'; padding:0px 0px 6px 0px;}

		.teamProfile legend { font-weight:bold; margin:0 0 0 5px;}
		*html .teamProfile legend { margin:0; padding:0 !important;}
		*:first-child+html .teamProfile legend { margin:0; padding:0 !important;}
		
		.teamProfile .teamMain  {margin:0px 6px;} 
		.teamProfile .teamImage {margin:5px 0px 0px 0px; float:'.$data['imagePosition'].';}
		.teamProfile .teamImage img {padding:2px; border:1px solid '.$lineColor.'; width:'.$data['imageSize'].'px; background-color:#fff;}
		.teamProfile .teamDesc  {margin:5px 0px 0px 0px; float:'.$data['imagePosition'].';}
	';
	flush();
}

function getTeamBlog_DataSet($DATA){
	requireComponent('Textcube.Function.misc');
	$cfg = Setting::fetchConfigVal($DATA);
	return true;
}
?>
