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
require ROOT . '/library/preprocessor.php';
/* [보안] 원본은 서비스 설정 화면(.htaccess 내용 등 표시)에서 권한을 확인하지 않았다 → 다른 control 화면과 같게 서비스 관리자만 */
requirePrivilege('group.creators');
require ROOT . '/interface/common/control/header.php';

$context = Model_Context::getInstance();
// htacccess modification
$htaccessContent = '';
if (file_exists(ROOT . "/.htaccess")) {
	$htaccessContent = @file_get_contents(ROOT . "/.htaccess");
}

// Encodings
$encodingList = array('UTF-8','EUC-KR','SHIFT_JIS','EUC-JP','BIG5','EUC-CN','EUC-TW','GBK');

// Languages
$locale = Locales::getInstance();
$locale->setDirectory(ROOT.'/resources/locale/description');
$supportedLanguages = $locale->getSupportedLocales();

// Skins
$skinList = array($service['skin'] => null);
if (is_dir(ROOT.'/skin/') && $handler = opendir(ROOT.'/skin/')) {
	while (($file = readdir($handler)) !== false) {
		if (!is_dir(__TEXTCUBE_SKIN_DIR__) || in_array($file, array('.', '..', 'customize'))) {
			continue;
		}
		if (!file_exists(__TEXTCUBE_SKIN_DIR__.'/'.$file.'/index.xml') || !file_exists(__TEXTCUBE_SKIN_DIR__.'/'.$file.'/skin.html')) {
			continue;
		}
		$skinList[$file] = null;
	}
}
closedir($handler);

?>
						<script type="text/javascript">
						//<![CDATA[
							function getEncodedValueById(id) {
								val = document.getElementById(id).value;
								return encodeURIComponent(val);
							}
							function setConfigFile() {
								tcConfirm('<?php echo _f('적용하시면 기존의 %1 파일이 변경됩니다. 이 변경사항은 되돌릴 수 없습니다. 그래도 적용하시겠습니까?','config.php');?>', function () {	// Textcube-NG: 확인창을 페이지 위 안내 줄의 [확인]/[취소]로 바꿈(r3-17, R-396)
								if(document.getElementById('usePageCache').checked) usePageCache = 1;
								else usePageCache = 0;
								if(document.getElementById('useCodeCache').checked) useCodeCache = 1;
								else useCodeCache = 0;
								if(document.getElementById('useSkinCache').checked) useSkinCache = 1;
								else useSkinCache = 0;
								if(document.getElementById('useMemcached').checked) useMemcached = 1;
								else useMemcached = 0;
								if(document.getElementById('useExternalResource').checked) useExternalResource = 1;
								else useExternalResource = 0;
								if(document.getElementById('useReader').checked) useReader = 1;
								else useReader = 0;
								if(document.getElementById('useNumericRSS').checked) useNumericRSS = 1;
								else useNumericRSS = 0;
								// Textcube-NG: 블로그 스킨 출력 주소 설정(v3.206)
								if(document.getElementById('useServiceURLonSkin').checked) useServiceURLonSkin = 1;
								else useServiceURLonSkin = 0;
								if(document.getElementById('useServiceURLonAttach').checked) useServiceURLonAttach = 1;
								else useServiceURLonAttach = 0;
								if(document.getElementById('useServiceURLonResources').checked) useServiceURLonResources = 1;
								else useServiceURLonResources = 0;
								if(document.getElementById('useEncodedURL').checked) useEncodedURL = 1;
								else useEncodedURL = 0;
								if(document.getElementById('allowBlogVisibilitySetting').checked) allowBlogVisibilitySetting = 1;
								else allowBlogVisibilitySetting = 0;
								if(document.getElementById('requireLogin').checked) requireLogin = 1;
								else requireLogin = 0;
								// Textcube-NG: 플래시 기능(트랙백 주소 복사·업로더)은 지원하지 않으므로 항상 0 을 보낸다(v3.208)
								flashClipboardPoter = 0;
								flashUploader = 0;
								if(document.getElementById('useDebugMode').checked) useDebugMode = 1;
								else useDebugMode = 0;
								if(document.getElementById('useSessionDebugMode').checked) useSessionDebugMode = 1;
								else useSessionDebugMode = 0;
								if(document.getElementById('useRewriteDebugMode').checked) useRewriteDebugMode = 1;
								else useRewriteDebugMode = 0;
								param = '';
								param += 'usePageCache='+usePageCache+'&';
								param += 'useCodeCache='+useCodeCache+'&';
								param += 'useSkinCache='+useSkinCache +'&';
								param += 'useMemcached='+useMemcached +'&';
								param += 'useExternalResource='+useExternalResource +'&';
								param += 'useReader='+useReader +'&';
								param += 'useNumericRSS='+useNumericRSS +'&';
								param += 'useServiceURLonSkin='+useServiceURLonSkin +'&';
								param += 'useServiceURLonAttach='+useServiceURLonAttach +'&';
								param += 'useServiceURLonResources='+useServiceURLonResources +'&';
								param += 'useEncodedURL='+useEncodedURL +'&';
								param += 'allowBlogVisibility='+allowBlogVisibilitySetting +'&';
								param += 'requireLogin='+requireLogin +'&';
								param += 'flashClipboardPoter='+flashClipboardPoter +'&';
								param += 'flashUploader='+flashUploader +'&';
								param += 'useDebugMode='+useDebugMode +'&';
								param += 'useSessionDebugMode='+useSessionDebugMode +'&';
								param += 'useRewriteDebugMode='+useRewriteDebugMode +'&';
								param += 'timeout='+getEncodedValueById('timeout') +'&';
								param += 'skin='+getEncodedValueById('skin') +'&';
								param += 'faviconDailyTraffic='+getEncodedValueById('faviconDailyTraffic') +'&';
								param += 'language='+getEncodedValueById('language') +'&';
								param += 'timezone='+getEncodedValueById('timezone') +'&';
								param += 'encoding='+getEncodedValueById('encoding') +'&';
								// Textcube-NG: 비밀번호 저장 방식(v3.208)
								param += 'passwordHash='+getEncodedValueById('passwordHash') +'&';
								param += 'serviceurl='+getEncodedValueById('serviceurl') + '&';
								param += 'cookieprefix='+getEncodedValueById('cookieprefix') + '&';
								param += 'externalResourceURL='+getEncodedValueById('externalResourceURL');
								var request = new HTTPRequest("POST", '<?php echo $blogURL;?>/control/server/config/');
								request.onSuccess = function() {
									PM.showMessage("<?php echo _t('저장되었습니다');?>", "center", "bottom");
								}
								request.onError = function() {
									PM.showErrorMessage("<?php echo _t('저장하지 못했습니다');?>", "center", "bottom");
									alert(this.getText("/response/msg"));
								}
								request.send(param);
								});
							}
							function setSmtp() {
								var useCustomSMTP = document.getElementById('useCustomSMTP').checked?1:0;
								var smtpHost = document.getElementById('smtpHost').value;
								var smtpPort = document.getElementById('smtpPort').value;

								var request = new HTTPRequest("POST", "<?php echo $blogURL;?>/control/server/mailhost/");
								request.onVerify = function() {
									return this.getText("/response/error") == 0;
								}
								request.onSuccess = function() {
									PM.showMessage("<?php echo _t('저장했습니다');?>", "center", "bottom");
								}
								request.onError = function() {
										alert('<?php echo _t('저장하지 못했습니다');?>');
								}
								request.send("&useCustomSMTP="+useCustomSMTP+"&smtpHost="+encodeURIComponent(smtpHost)+"&smtpPort="+encodeURIComponent(smtpPort));	// Textcube-NG: r3-44 R-568 smtpPort 도 인코딩한다(25&x=y 가 별도 인자로 갈라져 25 로 저장되던 것)
							}
							function setTagLimit() {
								// Textcube-NG: 태그 목록 개수의 서비스 상한(v3.136)
								var maxTagsOnTagPage = document.getElementById('maxTagsOnTagPage').value.trim();
								var request = new HTTPRequest("POST", "<?php echo $blogURL;?>/control/server/taglimit/");
								request.onSuccess = function() {
									PM.showMessage("<?php echo _t('저장했습니다');?>", "center", "bottom");
								}
								request.onError = function() {
									PM.showErrorMessage("<?php echo _t('저장하지 못했습니다');?>", "center", "bottom");
								}
								request.send("maxTagsOnTagPage=" + encodeURIComponent(maxTagsOnTagPage));
							}
							// Textcube-NG: 선행 항목이 꺼져 있으면 그 항목에서만 쓰는 종속 항목을 비활성으로 표시한다(r3-34).
							// 표시만 바꾸며, 저장할 때는 지금처럼 종속 항목의 값도 함께 보낸다(서버 저장 변경 없음)
							var ngDeps = [
								{on: 'usePageCache', inputs: ['useSkinCache'], rows: ['skincache-line']},
								{on: 'useExternalResource', inputs: ['externalResourceURL'], rows: ['externalresourceeurl-line']},
								{on: 'useDebugMode', inputs: ['useSessionDebugMode', 'useRewriteDebugMode'], rows: ['debug-session-dump-line', 'debug-rewrite-module-line']},
								{on: 'useCustomSMTP', inputs: ['smtpHost', 'smtpPort'], rows: ['smtpHost-line']}
							];
							function ngUpdateDeps() {
								for (var i = 0; i < ngDeps.length; i++) {
									var src = document.getElementById(ngDeps[i].on);
									if (!src) continue;
									var off = !src.checked;
									for (var j = 0; j < ngDeps[i].inputs.length; j++) {
										var el = document.getElementById(ngDeps[i].inputs[j]);
										if (el) el.disabled = off;
									}
									for (var k = 0; k < ngDeps[i].rows.length; k++) {
										var row = document.getElementById(ngDeps[i].rows[k]);
										if (!row) continue;
										var cls = row.className.replace(/(^|\s)ng-off(?=\s|$)/g, '');
										row.className = off ? cls + ' ng-off' : cls;
									}
								}
							}
							document.addEventListener('DOMContentLoaded', ngUpdateDeps);
							window.addEventListener('pageshow', ngUpdateDeps);
<?php if ($service['type'] == 'path') { ?>
							function setRootRedirect() {
								var rootRedirectURL = document.getElementById('rootRedirectURL').value.trim();
								var request = new HTTPRequest("POST", "<?php echo $blogURL;?>/control/server/rootredirect/");
								request.onSuccess = function() {
									PM.showMessage("<?php echo _t('저장했습니다');?>", "center", "bottom");
								}
								request.onError = function() {
									alert('<?php echo _t('저장하지 못했습니다');?>');
								}
								request.send("rootRedirectURL=" + encodeURIComponent(rootRedirectURL));
							}
							function setPathRouting() {
								var pathNotFoundBehavior = document.getElementById('pathNotFoundBehavior').value;
								var pathNotFoundRedirectURL = document.getElementById('pathNotFoundRedirectURL').value.trim();
								var request = new HTTPRequest("POST", "<?php echo $blogURL;?>/control/server/pathrouting/");
								request.onSuccess = function() {
									PM.showMessage("<?php echo _t('저장했습니다');?>", "center", "bottom");
								}
								request.onError = function() {
									alert('<?php echo _t('저장하지 못했습니다');?>');
								}
								request.send(
									"pathNotFoundBehavior=" + encodeURIComponent(pathNotFoundBehavior) +
									"&pathNotFoundRedirectURL=" + encodeURIComponent(pathNotFoundRedirectURL)
								);
							}
							function updatePathRoutingFields() {
								var behavior = document.getElementById('pathNotFoundBehavior').value;
								document.getElementById('pathNotFoundRedirectURL-row').style.display = (behavior === 'redirect') ? '' : 'none';
							}
<?php } ?>
<?php if ($service['type'] == 'domain') { ?>
							function setDomainRouting() {
								var domainRootBehavior = document.getElementById('domainRootBehavior').value;
								var domainRootRedirectURL = document.getElementById('domainRootRedirectURL').value.trim();
								var domainRootBlogId = document.getElementById('domainRootBlogId').value.trim();
								var domainMismatchBehavior = document.getElementById('domainMismatchBehavior').value;
								var domainMismatchRedirectURL = document.getElementById('domainMismatchRedirectURL').value.trim();
								var domainMismatchBlogId = document.getElementById('domainMismatchBlogId').value.trim();
								var request = new HTTPRequest("POST", "<?php echo $blogURL;?>/control/server/domainrouting/");
								request.onSuccess = function() {
									PM.showMessage("<?php echo _t('저장했습니다');?>", "center", "bottom");
								}
								request.onError = function() {
									alert('<?php echo _t('저장하지 못했습니다');?>');
								}
								request.send(
									"domainRootBehavior=" + encodeURIComponent(domainRootBehavior) +
									"&domainRootRedirectURL=" + encodeURIComponent(domainRootRedirectURL) +
									"&domainRootBlogId=" + encodeURIComponent(domainRootBlogId) +
									"&domainMismatchBehavior=" + encodeURIComponent(domainMismatchBehavior) +
									"&domainMismatchRedirectURL=" + encodeURIComponent(domainMismatchRedirectURL) +
									"&domainMismatchBlogId=" + encodeURIComponent(domainMismatchBlogId)
								);
							}
							function updateDomainRoutingFields() {
								var rootBehavior = document.getElementById('domainRootBehavior').value;
								document.getElementById('domainRootRedirectURL-row').style.display = (rootBehavior === 'redirect') ? '' : 'none';
								document.getElementById('domainRootBlogId-row').style.display = (rootBehavior === 'blog') ? '' : 'none';
								var mismatchBehavior = document.getElementById('domainMismatchBehavior').value;
								document.getElementById('domainMismatchRedirectURL-row').style.display = (mismatchBehavior === 'redirect') ? '' : 'none';
								document.getElementById('domainMismatchBlogId-row').style.display = (mismatchBehavior === 'blog') ? '' : 'none';
							}
							// Textcube-NG: 도메인 라우팅 블로그 ID 칸 옆 블로그 구분자 제안(control/action/blog/suggest, JSON). 고르면 ID 칸을 채운다. 결과는 텍스트 노드로만 넣고, 제안 칸의 Enter 는 저장으로 이어지지 않게 막는다(v3.220)
							function ngBlogSuggest(inputId, targetId) {
								var input = document.getElementById(inputId);
								var target = document.getElementById(targetId);
								if (!input || !target)
									return;
								var list = document.createElement('ul');
								list.className = 'ctlUserSuggest';
								list.style.display = 'none';
								input.parentNode.appendChild(list);
								input.setAttribute('autocomplete', 'off');
								var items = [];
								var selected = -1;
								var cursor = 0;
								var last = null;
								function hide() {
									list.style.display = 'none';
									selected = -1;
								}
								function highlight(index) {
									selected = index;
									for (var i = 0; i < list.childNodes.length; i++)
										list.childNodes[i].className = (i === index) ? 'hover' : '';
								}
								function choose(index) {
									if ((index < 0) || (index >= items.length))
										return;
									target.value = String(items[index].id);
									input.value = String(items[index].name);
									last = input.value.trim();
									hide();
								}
								function show(blogs) {
									while (list.firstChild)
										list.removeChild(list.firstChild);
									items = blogs;
									selected = -1;
									if (!blogs.length) {
										hide();
										return;
									}
									for (var i = 0; i < blogs.length; i++) {
										var item = document.createElement('li');
										item.appendChild(document.createTextNode(blogs[i].id + ' - ' + blogs[i].name + (blogs[i].title ? ' - ' + blogs[i].title : '')));
										item.onmousedown = (function(index) { return function() { choose(index); return false; }; })(i);
										item.onmouseover = (function(index) { return function() { highlight(index); }; })(i);
										list.appendChild(item);
									}
									list.style.display = 'block';
								}
								function request() {
									var value = input.value.trim();
									if (value === last)
										return;
									last = value;
									cursor++;
									if (!/^[-a-zA-Z0-9]{1,32}$/.test(value)) {
										show([]);
										return;
									}
									var current = cursor;
									var xhr = new XMLHttpRequest();
									xhr.open('GET', "<?php echo $blogURL;?>/control/action/blog/suggest/?input=" + encodeURIComponent(value), true);
									xhr.onload = function() {
										if (current !== cursor)
											return;
										var data = null;
										try {
											data = JSON.parse(xhr.responseText);
										} catch (e) {
											data = null;
										}
										show(((xhr.status === 200) && data && (data.error === 0) && Array.isArray(data.blogs)) ? data.blogs : []);
									};
									xhr.onerror = function() {
										if (current === cursor)
											show([]);
									};
									xhr.send(null);
								}
								input.onkeydown = function(event) {
									event = event || window.event;
									var key = event.keyCode || event.which;
									if (key === 13) {
										if (selected >= 0)
											choose(selected);
										return false;
									}
									if (key === 27) {
										hide();
										return false;
									}
									if ((key === 38) || (key === 40)) {
										if ((list.style.display === 'none') || !items.length)
											return true;
										if (key === 40)
											highlight((selected + 1) % items.length);
										else
											highlight((selected <= 0) ? items.length - 1 : selected - 1);
										return false;
									}
									return true;
								};
								input.onkeypress = function(event) {
									event = event || window.event;
									return ((event.keyCode || event.which) !== 13);
								};
								input.onkeyup = request;
								input.oninput = request;
								input.onblur = hide;
								input.onfocus = function() {
									last = null;
									request();
								};
							}
							document.addEventListener('DOMContentLoaded', function() {
								ngBlogSuggest('domainRootBlogSuggest', 'domainRootBlogId');
								ngBlogSuggest('domainMismatchBlogSuggest', 'domainMismatchBlogId');
							});
<?php } ?>
<?php
if(!defined('__TEXTCUBE_NO_FANCY_URL__')) {
?>
							function setRewrite() {
								tcConfirm('<?php echo _f('적용하시면 기존의 %1 파일이 변경됩니다. 이 변경사항은 되돌릴 수 없습니다. 그래도 적용하시겠습니까?','.htaccess');?>', function () {	// Textcube-NG: 확인창을 페이지 위 안내 줄의 [확인]/[취소]로 바꿈(r3-17, R-396)
								var htaccess = document.getElementById('rewrite');

								var request = new HTTPRequest("POST", "<?php echo $blogURL;?>/control/server/rewrite/");
								request.onSuccess = function() {
									PM.showMessage("<?php echo _t('저장되었습니다');?>", "center", "bottom");
								}
								request.onError = function() {
									if (this.getText("/response/msg"))
										alert(this.getText("/response/msg"));
									else
										alert('<?php echo _t('실패했습니다.');?>');
								}
								request.send('body='+encodeURIComponent(htaccess.value));
								});
							}
<?php
}
?>
							function setDefault() {
								tcConfirm('<?php echo _f('선택하신 옵션은 %1 파일을 처음 설치할 때의 값으로 되돌립니다.','.htaccess').' '._f('적용하시면 기존의 %1 파일이 변경됩니다. 이 변경사항은 되돌릴 수 없습니다. 그래도 적용하시겠습니까?','.htaccess');?>', function () {	// Textcube-NG: 확인창을 페이지 위 안내 줄의 [확인]/[취소]로 바꿈(r3-17, R-396)
								var htaccess = document.getElementById('rewrite');
								htaccess.value = '<?php echo getDefaultHtaccess(true);?>';
								return true;
								});
							}
						//]]>
						</script>

						<div id="part-control-config" class="part">
							<h2 class="caption"><span class="main-text"><?php	echo _t('서비스 기능을 조정합니다');?></span></h2>
							<div class="main-explain-box">
								<p class="explain"><?php echo _t('텍스트큐브의 기능을 조정합니다.').' '._t('이 명령은 config.php 파일을 직접 수정합니다. 또한 서비스 전체에 동시에 영향을 줍니다.').' '._t('문제가 생길 경우에는 설치된 디렉토리의 config.php를 직접 수정하셔서 복원할 수 있습니다.').' '._t('변경시 텍스트큐브의 동작에 큰 영향을 줄 수 있으므로 주의하시기 바랍니다.');?></p>
<?php
if (!is_writable(ROOT . "/config.php")) {
?>
								<p class="warning"><?php echo _f('파일 쓰기 권한이 없습니다. 웹서버가 %1 파일의 쓰기 권한을 가지고 있는지 확인하세요.','config.php').' '._t('아래의 설정을 저장하셔도 반영되지 않습니다.');?></p>
<?php
}
?>
							</div>

							<div class="data-inbox">
								<form id="configSetting" class="section" method="post" action="<?php echo $blogURL;?>/control/server/config" enctype="application/x-www-form-urlencoded">
<?php // Textcube-NG: 입력칸·select 의 항목 설명은 class="ng-help" 로 맞춰 입력칸 다음 줄에 회색 작은 글씨로 보인다(control.css #part-control-config .ng-help). 체크박스 문구는 체크박스 바로 옆에 그대로 둔다. NG 항목의 설정 이름 표시도 같은 class 로 바꾸고, passwordHash select 앞 줄바꿈은 그 줄바꿈과 겹치므로 뺐다(v3.220) ?>
									<fieldset id="basic-container" class="container">
										<legend><?php echo _t('기본 설정');?></legend>

										<dl id="timeout-line" class="line">
											<dt><span class="label"><?php echo _t('세션 타임아웃');?></span></dt>
											<dd>
												<input id="timeout" type="text" class="input-text" name="timeout" size="5" value="<?php echo htmlspecialchars($service['timeout']);?>" />
												<label for="timeout" class="ng-help"><?php echo _t('세션 타임 아웃까지의 시간을 설정합니다. 단위는 초입니다.');?></label>
											</dd>
										</dl>
										<dl id="skin-line" class="line">
											<dt><span class="label"><?php echo _t('기본 스킨');?></span></dt>
											<dd>
												<select id="skin" name="skin">
<?php
foreach ($skinList as $skinName => $value) {
?>
													<option value="<?php echo $skinName; ?>"<?php echo ($skinName == $service['skin'] ? ' selected="selected"' : ''); ?>><?php echo $skinName; ?></option>
<?php
}
?>												</select>
												<label for="skin" class="ng-help"><?php echo _t('블로그의 기본 스킨을 정합니다. 반드시 존재하는 스킨의 디렉토리 이름이어야 합니다.');?></label>
											</dd>
										</dl>
										<dl id="favicon-line" class="line">
											<dt><span class="label"><?php echo _t('파비콘 트래픽');?></span></dt>
											<dd>
												<input id="faviconDailyTraffic" type="text" class="input-text" name="faviconDailyTraffic" size="5" value="<?php echo htmlspecialchars($service['favicon_daily_traffic']);?>" />
												<label for="faviconDailyTraffic" class="ng-help"><?php echo _t('블로그 파비콘이 하루에 소모하는 트래픽을 제한합니다. 단위는 Megabyte 입니다.');?></label>
											</dd>
										</dl>
										<dl id="language-line" class="line">
											<dt><span class="label"><?php echo _t('언어');?></span></dt>
											<dd>
												<select id="language" name="language">
<?php
foreach ($supportedLanguages as $locale => $language) {
?>
													<option value="<?php echo $locale; ?>"<?php echo ($locale == $service['language'] ? ' selected="selected"' : ''); ?>><?php echo $language; ?></option>
<?php
}
?>												</select>
												<label for="language" class="ng-help"><?php echo _t('이 서비스의 기본 언어를 설정합니다.');?></label>
											</dd>
										</dl>
										<dl id="timezone-line" class="line">
											<dt><span class="label"><?php echo _t('시간대');?></span></dt>
											<dd>
												<select id="timezone" name="timezone">
<?php
$tz = new Timezone;
foreach ($tz->getList() as $timezone) {
?>
													<option value="<?php echo $timezone;?>"<?php echo ($timezone == $service['timezone'] ? ' selected="selected"' : '');?>><?php echo _t($timezone);?></option>
<?php
}
?>
												</select>
												<label for="timezone" class="ng-help"><?php echo _t('이 서비스의 기본 시간대를 설정합니다.');?></label>
											</dd>
										</dl>
										<dl id="encoding-line" class="line">
											<dt><span class="label"><?php echo _t('인코딩');?></span></dt>
											<dd>
												<select id="encoding" name="encoding">
<?php
foreach($encodingList as $enc) {
?>
													<option value="<?php echo $enc;?>"<?php if($enc==$service['encoding']) echo ' selected="selected"';?>><?php echo  htmlspecialchars($enc);?></option>

<?php
}
//											<input id="encoding" type="text" class="input-text" name="encoding" size="13" value="<?php echo $service['encoding'];?>" />
?>
												</select>

												<label for="encoding" class="ng-help"><?php echo _t('이 서비스의 기본 인코딩을 설정합니다.');?></label>
											</dd>
										</dl>
										<dl id="serviceurl-line" class="line">
											<dt><span class="label"><?php echo _t('서비스 리소스 경로');?></span></dt>
											<dd>
												<input id="serviceurl" type="text" class="input-text" name="serviceurl" size="45" value="<?php /* Textcube-NG: 계산된 현재 주소($serviceURL)가 아니라 config 에 강제 지정된 값만 보여 준다(없으면 빈칸 = 자동). 원본은 저장할 때마다 화면을 연 호스트의 주소가 강제 지정되었다(v3.139) */ echo htmlspecialchars(isset($service['serviceURL']) ? $service['serviceURL'] : '');?>" />
												<label for="serviceurl" class="ng-help"><?php echo _t('이 서비스가 참조할 경로를 강제로 지정합니다.').'<br />'._t('정적인 파일들 (script, attach, image, style 하위 디렉토리)을 별도의 경로로 관리할 수 있습니다. 다른 웹 프로그램을 같은 도메인에서 운영할 때 동작이 방해받는 경우, 또는 서버에 걸리는 부하를 분산하고 싶은 경우 지정하면 됩니다.').'<br />'._t('비워 두면(기본) 접속한 주소(https 여부·호스트·포트)로 자동 계산합니다. 문제가 있을 때의 호환 대책으로만 지정하고, 지정할 때는 스킴 없는 //호스트[:포트]/경로 형식을 권장합니다. 이 형식은 화면 주소에 스킴을 고정하지 않으며, 피드·메일에는 config.php 의 useSSL 설정에 따라 http 또는 https 를 붙입니다. 스킴을 넣은 값은 어디서나 그대로 쓰며, 값을 지정하면 https·포트 판정과 호스트 정규화를 하지 않습니다. https 로 서비스하면(프록시에서 TLS 를 끝내는 경우 포함) useSSL 을 true 로 설정하세요.');?></label>
											</dd>
										</dl>
										<dl id="skinServiceURL-line" class="line">
											<dt><span class="label"><?php echo _t('스킨 상대 경로');?></span></dt>
											<dd>
												<input type="checkbox" id="useServiceURLonSkin" class="checkbox" name="useServiceURLonSkin"<?php /* Textcube-NG: 블로그 스킨 출력 주소 설정 — 기본 켬(원본 동작), 끄면 도메인 없는 경로(v3.206) */ echo $service['useServiceURLonSkin'] ? ' checked="checked"' : '';?> /><label for="useServiceURLonSkin"><?php echo _t('스킨의 상대 경로(./)를 서비스 리소스 경로(도메인 포함)로 바꿉니다.').'<br />'._t('끄면 도메인 없는 경로(서비스 경로/skin/blog/스킨 이름/)로 바꿉니다. 같은 블로그를 여러 도메인이나 http·https 로 함께 열 때 끄면 됩니다.');?></label>
											</dd>
										</dl>
										<dl id="attachServiceURL-line" class="line">
											<dt><span class="label"><?php echo _t('스킨 첨부파일 경로');?></span></dt>
											<dd>
												<input type="checkbox" id="useServiceURLonAttach" class="checkbox" name="useServiceURLonAttach"<?php echo $service['useServiceURLonAttach'] ? ' checked="checked"' : '';?> /><label for="useServiceURLonAttach"><?php echo _t('스킨의 블로그 로고 주소(attach)를 서비스 리소스 경로(도메인 포함)로 씁니다.').'<br />'._t('끄면 도메인 없는 경로(서비스 경로/attach/블로그 번호/)를 씁니다.');?></label>
											</dd>
										</dl>
										<dl id="resourcesServiceURL-line" class="line">
											<dt><span class="label"><?php echo _t('스킨 리소스 경로');?></span></dt>
											<dd>
												<input type="checkbox" id="useServiceURLonResources" class="checkbox" name="useServiceURLonResources"<?php echo $service['useServiceURLonResources'] ? ' checked="checked"' : '';?> /><label for="useServiceURLonResources"><?php echo _t('로고가 없을 때 스킨에 넣는 빈 이미지 주소(resources)를 서비스 리소스 경로(도메인 포함)로 씁니다.').'<br />'._t('끄면 도메인 없는 경로(서비스 경로/resources/)를 씁니다.');?></label>
											</dd>
										</dl>
										<dl id="externalResource-line" class="line">
											<dt><span class="label"><?php echo _t('외부 리소스 사용');?></span></dt>
											<dd>
												<input type="checkbox" id="useExternalResource" class="checkbox" name="useExternalResource"<?php echo $service['externalresources'] ? ' checked="checked"' : '';?> onchange="ngUpdateDeps()" /><label for="useExternalResource"><?php echo _t('텍스트큐브의 자바 스크립트 컴포넌트등을 외부에서 불러옵니다.').' '._f('서버의 트래픽을 줄이기 위하여 동작시 필요한 일부 리소스를 공개 텍스트큐브 리소스 저장소(%1)에서 읽어오거나 지정한 서버에서 읽어옵니다.',TEXTCUBE_RESOURCE_URL);?></label>
											</dd>
										</dl>
										<?php // Textcube-NG: 바로 위 선행 항목이 꺼져 있으면 쓰이지 않는 종속 행은 들여 쓰고 처음부터 회색·비활성(disabled)으로 표시한다. 외부 리소스·스킨 캐시·디버그 하위·메일서버 행이 같고, 저장 JS 는 disabled 와 관계없이 값을 읽어 보내므로 저장 값은 바뀌지 않는다(r3-34) ?>
										<dl id="externalresourceeurl-line" class="line ng-dep<?php echo $service['externalresources'] ? '' : ' ng-off';?>">
											<dt><span class="label"><?php echo _t('외부 리소스 저장소 경로');?></span></dt>
											<dd>
												<input id="externalResourceURL" type="text" class="input-text" name="externalResourceURL" size="45"<?php echo $service['externalresources'] ? '' : ' disabled="disabled"';?> value="<?php echo htmlspecialchars(isset($service['resourceURL']) ? $service['resourceURL'] : '');?>" />
												<label for="externalResourceURL" class="ng-help"><?php echo _t('외부 리소스를 사용할 경우 리소스 저장소를 임의로 지정할 수 있습니다.').'<br />'._f('이 값을 지정하지 않고 외부 리소스 사용을 선택할 경우 텍스트큐브 리소스 저장소(%1)를 기본값으로 사용합니다.',TEXTCUBE_RESOURCE_URL);?></label>
											</dd>
										</dl>
										<dl id="cookieprefix-line" class="line">
											<dt><span class="label"><?php echo _t('COOKIE 접두어');?></span></dt>
											<dd>
												<input id="cookieprefix" type="text" class="input-text" name="cookieprefix" size="45" value="<?php echo htmlspecialchars(($context->getProperty('service.cookie_prefix','') == 'Textcube'.str_replace('.','',TEXTCUBE_VERSION_ID)) ? '' : $context->getProperty('service.cookie_prefix',''));?>" />
												<label for="cookieprefix" class="ng-help"><?php echo _t('이 서비스에서 사용할 웹 브라우저 쿠키 이름 앞에 붙을 접두어를 지정합니다.').'<br />'._t('지정하지 않을 경우 웹 브라우저 쿠키 접두어는 Textcube[버전번호] 로 자동으로 지정합니다.');?></label>
											</dd>
										</dl>
									</fieldset>
									<fieldset id="cache-container" class="container">
										<legend><?php echo _t('캐시 사용 조절');?></legend>

										<dl id="cache-line" class="line">
											<dt><span class="label"><?php echo _t('페이지 캐시 사용');?></span></dt>
											<dd>
												<input type="checkbox" id="usePageCache" class="checkbox" name="usePageCache"<?php echo $service['pagecache'] ? ' checked="checked"' : '';?> onchange="ngUpdateDeps()" /><label for="usePageCache"><?php echo _t('텍스트큐브의 전반적인 캐시 기능을 사용합니다. 페이지 캐시, 스킨캐시, 실시간 쿼리 캐시 및 정책을 모두 포함됩니다.');?></label>
											</dd>
										</dl>
										<?php // Textcube-NG: 스킨 캐시는 페이지 캐시를 켜야 쓰이므로 페이지 캐시 바로 아래로 옮겼다(r3-34) ?>
										<dl id="skincache-line" class="line ng-dep<?php echo $service['pagecache'] ? '' : ' ng-off';?>">
											<dt><span class="label"><?php echo _t('스킨 캐시 사용');?></span></dt>
											<dd>
												<input type="checkbox" id="useSkinCache" class="checkbox" name="useSkinCache"<?php echo $service['skincache'] ? ' checked="checked"' : '';?><?php echo $service['pagecache'] ? '' : ' disabled="disabled"';?> /><label for="useSkinCache"><?php echo _t('스킨 캐시를 사용합니다.').' '._t('스킨 캐시를 사용하지 않도록 설정하면 페이지 캐시를 사용하도록 설정해도 스킨 캐시를 사용하지않습니다.').' '._t('스킨 파일을 직접 수정한 후 바로 변경된 결과를 보아야 하는 경우 스킨 캐시를 끄고 작업하시기 바랍니다.').' '._t('페이지 캐시 사용을 켜야 적용됩니다.');?></label>
											</dd>
										</dl>
										<dl id="codecache-line" class="line">
											<dt><span class="label"><?php echo _t('코드 캐시 사용');?></span></dt>
											<dd>
												<input type="checkbox" id="useCodeCache" class="checkbox" name="useCodeCache"<?php echo $service['codecache'] ? ' checked="checked"' : '';?> /><label for="useCodeCache"><?php echo _t('텍스트큐브 코드를 캐시하는 기능을 사용합니다.').' '._t('작업에 따라 각각 하나의 파일로 최적화된 프로그램 코드 캐시를 만들어 메인 코드 대신 사용합니다.').' '._t('페이지 호출 응답 속도가 빨라집니다.');?></label>
											</dd>
										</dl>
										<dl id="memcached-line" class="line">
											<dt><span class="label"><?php echo _t('Memcached 사용');?></span></dt>
											<dd>
												<input type="checkbox" id="useMemcached" class="checkbox" name="useMemcached"<?php echo (isset($service['memcached']) && $service['memcached']) ? ' checked="checked"' : '';?> /><label for="useMemcached"><?php
	$_mcTooltip = htmlspecialchars(implode("\n", [
		_t('Memcached 저장 대상:'),
		'  • ' . _t('세션 저장소 (Sessions DB 테이블 → Memcached)'),
		'  • ' . _t('DB 쿼리 결과 캐시 (queryCache)'),
		'  • ' . _t('전역 설정 캐시 (globalCacheStorage)'),
		'',
		_t('파일시스템 고정 (이 설정과 무관):'),
		'  • ' . _t('페이지 HTML 캐시 (pageCache)'),
		'  • ' . _t('코드 캐시 (codecache)'),
		'  • ' . _t('스킨 캐시 (skincache)'),
	]));
	echo _t('Memcached 모듈을 사용합니다.')
		. ' ' . _t('블로그의 속도 향상을 위하여 Memcached를 사용합니다. 이 기능을 사용하기 위해서는 서버에 Memcached가 설치되어 있고, PHP가 Memcached를 사용할 수 있도록 설정되어 있어야 합니다.')
		. ' <abbr title="' . $_mcTooltip . '">' . _t('적용 범위 ▾') . '</abbr>';
?></label>
											</dd>
										</dl>
										<dl id="reader-line" class="line">
											<dt><span class="label"><?php echo _t('RSS 이웃글 보기 사용');?></span></dt>
											<dd>
												<input type="checkbox" id="useReader" class="checkbox" name="useReader"<?php echo $service['reader'] ? ' checked="checked"' : '';?> /><label for="useReader"><?php echo _t('바깥글 보기 (RSS 리더)를 사용합니다. 끌 경우 리더가 RSS를 읽어오는 로드가 줄어들기 때문에 서버 로드가 줄어듭니다.');?></label>
											</dd>
										</dl>
									</fieldset>

									<fieldset id="option-container" class="container">
										<dl id="numeric-rss-line" class="line">
											<dt><span class="label"><?php echo _t('RSS 주소를 숫자로 사용');?></span></dt>
											<dd>
												<input type="checkbox" id="useNumericRSS" class="checkbox" name="useNumericRSS"<?php echo $service['useNumericURLonRSS'] ? ' checked="checked"' : '';?> /><label for="useNumericRSS"><?php echo _t('RSS 주소를 fancyURL을 사용하지 않고 강제로 숫자로 설정합니다.');?></label>
											</dd>
										</dl>
										<dl id="encoded-url-line" class="line">
											<dt><span class="label"><?php echo _t('인코딩된 문자 주소 사용');?></span></dt>
											<dd>
												<input type="checkbox" id="useEncodedURL" class="checkbox" name="useEncodedURL"<?php echo $service['useEncodedURL'] ? ' checked="checked"' : '';?> /><label for="useEncodedURL"><?php echo _t('영어 이외의 주소 출력시 RFC1738 규격에 맞추어 주소를 인코딩한 채로 출력합니다.');?></label>
											</dd>
										</dl>
										<dl id="blog-visibility-line" class="line">
											<dt><span class="label"><?php echo _t('블로그 공개 설정 변경 허용');?></span></dt>
											<dd>
												<input type="checkbox" id="allowBlogVisibilitySetting" class="checkbox" name="allowBlogVisibilitySetting"<?php echo $service['allowBlogVisibilitySetting'] ? ' checked="checked"' : '';?> /><label for="allowBlogVisibilitySetting"><?php echo _t('다중 블로그 모드에서 블로그 운영자가 블로그를 비공개로 설정하는 것을 허용합니다.');?></label>
											</dd>
										</dl>
										<dl id="blog-service-visibility-line" class="line">
											<dt><span class="label"><?php echo _t('블로그 서비스 공개 정도');?></span></dt>
											<dd>
												<input type="checkbox" id="requireLogin" class="checkbox" name="requireLogin"<?php echo $service['requirelogin'] ? ' checked="checked"' : '';?> /><label for="requireLogin"><?php echo _t('다중 블로그 모드에서 블로그 회원만 다른 블로그를 볼 수 있도록 합니다. 비공개 블로그 커뮤니티에 유용합니다.');?></label>
											</dd>
										</dl>
										<dl id="flash-clipboard--line" class="line">
											<dt><span class="label"><?php echo _t('플래시 트랙백 주소 복사 사용');?></span></dt>
											<dd>
												<input type="checkbox" id="flashClipboardPoter" class="checkbox" name="flashClipboardPoter" disabled="disabled"<?php /* Textcube-NG: 플래시 기능은 지원하지 않으므로 끈 채로 바꿀 수 없게 둔다(v3.208) */ ?> /><label for="flashClipboardPoter"><?php echo _t('크로스 브라우징을 지원하는 트랙백 복사를 위한 플래시를 사용합니다.');?> (<?php echo _t('지원하지 않음');?>)</label>
											</dd>
										</dl>
										<dl id="flash-uploader--line" class="line">
											<dt><span class="label"><?php echo _t('플래시 업로더 사용');?></span></dt>
											<dd>
												<input type="checkbox" id="flashUploader" class="checkbox" name="flashUploader" disabled="disabled"<?php /* Textcube-NG: 플래시 기능은 지원하지 않으므로 끈 채로 바꿀 수 없게 둔다(v3.208) */ ?> /><label for="flashUploader"><?php echo _t('에디터에서 다중 파일 업로드를 지원하는 플래시 업로더를 사용합니다.');?> (<?php echo _t('지원하지 않음');?>)</label>
											</dd>
										</dl>
									</fieldset>

									<fieldset id="debug-container" class="container">
										<legend><?php echo _t('디버그 모드 조절');?></legend>

										<dl id="debugmode-line" class="line">
											<dt><span class="label"><?php echo _t('디버그 모드 사용');?></span></dt>
											<dd>
												<input type="checkbox" id="useDebugMode" class="checkbox" name="useDebugMode"<?php echo $service['debugmode'] ? ' checked="checked"' : '';?> onchange="ngUpdateDeps()" /><label for="useDebugMode"><?php echo _t('디버그 모드를 사용합니다. 텍스트큐브의 모든 쿼리 실행 결과 및 코드의 에러가 발생하는 부분을 추적할 수 있습니다.');?></label>
											</dd>
										</dl>
										<dl id="debug-session-dump-line" class="line ng-dep<?php echo $service['debugmode'] ? '' : ' ng-off';?>">
											<dt><span class="label"><?php echo _t('세션 디버그 모드 사용');?></span></dt>
											<dd>
												<input type="checkbox" id="useSessionDebugMode" class="checkbox" name="useSessionDebugMode"<?php echo $service['debug_session_dump']  ? ' checked="checked"' : '';?><?php echo $service['debugmode'] ? '' : ' disabled="disabled"';?> /><label for="useSessionDebugMode"><?php echo _t('세션 디버그 모드를 디버그 모드에 추가합니다. 현재 사용중인 세션에 대한 정보가 디버그 모드 끝에 출력됩니다.');?></label>
											</dd>
										</dl>
										<dl id="debug-rewrite-module-line" class="line ng-dep<?php echo $service['debugmode'] ? '' : ' ng-off';?>">
											<dt><span class="label"><?php echo _t('rewrite 모듈 디버그 모드 사용');?></span></dt>
											<dd>
												<input type="checkbox" id="useRewriteDebugMode" class="checkbox" name="useRewriteDebugMode"<?php echo $service['debug_rewrite_module'] ? ' checked="checked"' : '';?><?php echo $service['debugmode'] ? '' : ' disabled="disabled"';?> /><label for="useRewriteDebugMode"><?php echo _t('rewrite 모듈 디버그 모드를 디버그 모드에 추가합니다. 접근시 적용된 주소를 해석한 정보가 디버그 모드 끝에 출력됩니다.');?></label>
											</dd>
										</dl>
									</fieldset>
<?php
// Textcube-NG: config.php 에서만 바꾸는 NG 설정(DB SSL/TLS·DB 드라이버, 비밀번호 저장 방식, 인증 토큰·실패 제한, 프록시, Host 검사)의
// 지금 적용 중인 값을 보여 준다. 인증서·키 파일 경로와 이 목록 밖의 $database 값(서버·사용자·비밀번호 등)은 보여 주지 않는다.
// 비밀번호 저장 방식만 이 화면에서 바꿀 수 있다(config/index.php 가 허용 값만 기록)(v3.208)
// Textcube-NG: 네 번째 원소는 그 항목을 쓰게 하는 선행 설정이다. 선행 설정 때문에 쓰이지 않으면 회색과 이유를 표시한다(r3-34)
$ngConfigKeys = array(
	array('database', 'ssl', _t('DB SSL/TLS 사용')),
	array('database', 'ssl_verify', _t('DB 서버 인증서 검증'), 'ssl'),
	array('database', 'ssl_ca', _t('DB CA 인증서'), 'ssl'),
	array('database', 'ssl_cert', _t('DB 클라이언트 인증서'), 'ssl'),
	array('database', 'ssl_key', _t('DB 클라이언트 키'), 'ssl'),
	array('database', 'driver', _t('DB 드라이버')),	// Textcube-NG: Cubrid 전용이 아니라 5개 DB 어댑터 공통 설정(v3.218)
	array('service', 'passwordHash', _t('비밀번호 저장 방식')),
	array('service', 'authTokenLifetime', _t('인증 토큰 유효 시간(초)')),
	array('service', 'loginFailureLimit', _t('로그인 실패 제한 횟수')),
	array('service', 'loginLockScope', _t('로그인 잠금 범위')),	// Textcube-NG: 'account'·'ip'(r3-14)
	array('service', 'loginLockShowRemaining', _t('로그인 잠금 남은 시간 표시'), 'loginLockScope'),	// Textcube-NG: 'ip' 에서만 씀(r3-14)
	array('service', 'loginIpFailureLimit', _t('IP별 로그인 실패 제한 횟수'), 'loginLockScope'),	// Textcube-NG: 'ip' 에서만 씀(r3-14)
	array('service', 'loginIpRecordLimit', _t('IP 로그인 실패 기록 최대 개수'), 'loginLockScope'),	// Textcube-NG: 'ip' 에서만 씀(r3-14)
	array('service', 'passwordConfirmFailureLimit', _t('비밀번호 확인 실패 제한 횟수')),
	array('service', 'useLoginProxy', _t('OIDC·소셜 로그인 요청에 프록시 사용')),
	array('service', 'trustedProxies', _t('신뢰 프록시')),
	array('service', 'hostAliases', _t('추가 Host 이름')),
	array('service', 'hostMismatchBehavior', _t('Host 불일치 동작')),
	array('service', 'useConfigPort', _t('config.php 포트 사용'))
);
$ngSecretKeys = array('ssl_ca', 'ssl_cert', 'ssl_key');
$ngPasswordHashModes = array(
	'md5'     => _t('md5 (기본, 원본과 같은 md5 저장)'),
	'multi'   => _t('multi (새로 설정·변경하는 비밀번호만 password_hash)'),
	'migrate' => _t('migrate (multi + 로그인할 때 기존 md5 값도 password_hash 로 다시 저장)')
);
// 값 표시: 불리언은 켜짐/꺼짐, 배열은 개수와 항목(문자열·숫자만), 그 밖의 스칼라는 escape 한 값
$ngFormat = function ($value) {
	if (is_bool($value))
		return $value ? _t('켜짐') : _t('꺼짐');
	if (is_null($value))
		return 'null';
	if (is_array($value)) {
		$items = array();
		foreach ($value as $item)
			$items[] = (is_string($item) || is_int($item) || is_float($item)) ? htmlspecialchars((string)$item, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') : _t('표시할 수 없는 값');
		return _f('%1개', (string)count($items)) . (count($items) ? ': ' . implode(', ', $items) : '');
	}
	if (!is_scalar($value))
		return _t('표시할 수 없는 값');
	if ((string)$value === '')
		return _t('빈 값');
	return htmlspecialchars((string)$value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
};
// config.php 에 적은 키: $database['키']·$service['키'] 꼴만 센다. 그 밖의 꼴(통째 대입·변수 키 등)이 있거나 파일을 읽지 못하면
// 그 묶음은 config.php 에 적었는지 표시하지 않는다(값은 그대로 보여 준다). 파일 내용은 키 이름을 찾는 데만 쓰고 출력하지 않는다.
$ngListedKeys = array();
$ngUnknownSet = array();
if (!function_exists('token_get_all') || !defined('__TEXTCUBE_CONFIG_FILE__')) {
	$ngUnknownSet = array('database' => true, 'service' => true);
} else if (file_exists(__TEXTCUBE_CONFIG_FILE__)) {
	$ngSource = @file_get_contents(__TEXTCUBE_CONFIG_FILE__);
	if (!is_string($ngSource)) {
		$ngUnknownSet = array('database' => true, 'service' => true);
	} else {
		$ngTokens = token_get_all($ngSource);
		$ngCount = count($ngTokens);
		for ($ngI = 0; $ngI < $ngCount; $ngI++) {
			if (!is_array($ngTokens[$ngI]) || ($ngTokens[$ngI][0] != T_VARIABLE) || !in_array($ngTokens[$ngI][1], array('$database', '$service'), true))
				continue;
			$ngNs = substr($ngTokens[$ngI][1], 1);
			$ngNext = array();
			for ($ngJ = $ngI + 1; ($ngJ < $ngCount) && (count($ngNext) < 2); $ngJ++) {
				if (is_array($ngTokens[$ngJ]) && in_array($ngTokens[$ngJ][0], array(T_WHITESPACE, T_COMMENT, T_DOC_COMMENT), true))
					continue;
				$ngNext[] = $ngTokens[$ngJ];
			}
			if ((count($ngNext) == 2) && ($ngNext[0] === '[') && is_array($ngNext[1]) && ($ngNext[1][0] == T_CONSTANT_ENCAPSED_STRING) && preg_match('/^([\'"])([^\\\\$\'"]*)\1$/', $ngNext[1][1], $ngMatch))
				$ngListedKeys[$ngNs . '.' . $ngMatch[2]] = true;
			else
				$ngUnknownSet[$ngNs] = true;
		}
	}
	unset($ngSource, $ngTokens);
}
$ngMode = Auth::passwordHashMode();
?>
									<fieldset id="ng-config-container" class="container">
										<legend><?php echo _t('config.php 에서만 바꾸는 설정');?></legend>

										<dl id="ng-config-notice-line" class="line">
											<dt><span class="label"><?php echo _t('안내');?></span></dt>
											<dd>
												<?php echo _t('아래 항목은 config.php 에서만 바꿀 수 있으며 지금 적용 중인 값을 보여 줍니다. 비밀번호 저장 방식만 이 화면에서 바꿀 수 있습니다.');?>
												<?php echo _t('인증서·키 파일 경로는 보여 주지 않습니다.');?>
												<?php echo _t('config.php 에 없는 항목은 설정 안 됨과 기본값으로 표시합니다.');?>
												<?php if (count($ngUnknownSet)) echo _t('config.php 를 읽지 못했거나 해석할 수 없어 일부 항목은 config.php 에 적었는지 표시하지 않습니다.');?>
											</dd>
										</dl>
<?php
foreach ($ngConfigKeys as $ngKey) {
	$ngNs = $ngKey[0];
	$ngName = $ngKey[1];
	$ngArray = ($ngNs == 'database') ? $database : $service;
	$ngExists = is_array($ngArray) && array_key_exists($ngName, $ngArray);
	$ngValue = $ngExists ? $ngArray[$ngName] : null;
	if (in_array($ngName, $ngSecretKeys, true))
		$ngText = empty($ngValue) ? _t('비어 있음') : _t('설정됨');
	else
		$ngText = $ngFormat($ngValue);
	if (!isset($ngListedKeys[$ngNs . '.' . $ngName]) && !isset($ngUnknownSet[$ngNs]))
		$ngText = $ngExists ? _t('설정 안 됨') . ' (' . _f('기본 %1', $ngText) . ')' : _t('설정 안 됨');
	if (($ngName == 'passwordHash') && ($ngValue !== $ngMode))
		$ngText .= ' (' . _f('적용되는 방식: %1', $ngMode) . ')';
	// Textcube-NG: 선행 설정 때문에 쓰이지 않거나 일부만 쓰이는 항목에 이유를 적는다(표시만, r3-34)
	$ngDepOff = false;
	$ngNote = '';
	if (isset($ngKey[3]) && ($ngKey[3] == 'ssl')) {
		$ngDepOff = empty($database['ssl']);	// DB 어댑터의 !empty($database['ssl']) 판정과 같음
		if ($ngDepOff) $ngNote = _t('DB SSL/TLS 사용이 꺼져 있어 쓰지 않습니다.');
	} else if (isset($ngKey[3]) && ($ngKey[3] == 'loginLockScope')) {
		$ngDepOff = !(isset($service['loginLockScope']) && ($service['loginLockScope'] === 'ip'));	// auth.php __loginLockScope() 와 같은 판정
		if ($ngDepOff) $ngNote = _t('로그인 잠금 범위가 ip 가 아니어서 쓰지 않습니다.');
	} else if ($ngName == 'trustedProxies') {
		if (isset($service['serviceURL'])) $ngNote = _t('서비스 리소스 경로를 지정해 두어 서비스 주소의 https 판정에는 쓰지 않습니다. 방문자 IP·HSTS 판정과, 경로가 //호스트 형식일 때 소셜 로그인 콜백 주소의 https 판정에는 그대로 씁니다.');
	} else if ($ngName == 'useConfigPort') {
		if (empty($service['configuredPort'])) {	// Config.php 의 !empty($service['configuredPort']) 판정과 반대
			$ngDepOff = true;
			$ngNote = _t('config.php 에 포트를 적지 않아 쓰지 않습니다.');
		} else if (isset($service['serviceURL'])) {
			$ngNote = _t('서비스 리소스 경로를 지정해 두어 서비스 주소(스킨·첨부 경로 등)의 포트에는 쓰지 않습니다. 블로그 주소와 Host 검사에는 그대로 씁니다.');
		}
	}
?>
										<dl id="ng-config-<?php echo $ngNs . '-' . $ngName;?>-line" class="line<?php echo isset($ngKey[3]) ? ' ng-dep' : ''; echo $ngDepOff ? ' ng-off' : '';?>">
											<dt><span class="label"><?php echo $ngKey[2];?></span></dt>
											<dd>
												<?php echo $ngText;?> <span class="ng-help">(<?php echo htmlspecialchars('$' . $ngNs . "['" . $ngName . "']", ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');?>)</span><?php if ($ngNote !== '') echo '<span class="ng-help">' . $ngNote . '</span>';?>
<?php
	if ($ngName == 'passwordHash') {
?>
												<select id="passwordHash" name="passwordHash">
<?php
		foreach ($ngPasswordHashModes as $ngModeValue => $ngModeLabel) {
?>
													<option value="<?php echo $ngModeValue;?>"<?php echo ($ngModeValue === $ngMode ? ' selected="selected"' : '');?>><?php echo $ngModeLabel;?></option>
<?php
		}
?>
												</select>
												<label for="passwordHash" class="ng-help"><?php echo _t('multi·migrate 로 바꾸면 로그인·관리 화면에 점검 안내가 나옵니다. 블로그 주소 뒤에 /checkup 을 붙여 점검을 실행하세요.');?> <?php echo _t('DB 종류가 MySQL·MySQLi 이면 점검이 비밀번호 열을 varchar(255) 로 넓힙니다. 열이 짧으면 md5 로 저장합니다.');?> <?php echo _t('password_hash 로 저장된 비밀번호는 원본 Textcube 등 md5 로 비교하는 코드에서 로그인할 수 없습니다.');?><br /><?php echo _t('주의: md5 로 되돌려도 로그인은 막히지 않지만, 그 뒤 새로 설정·변경하는 비밀번호와 비밀번호로 로그인한 password_hash 계정의 비밀번호는 md5 로 다시 저장됩니다. 로그인하지 않은 계정은 password_hash 값으로 남습니다.');?></label>
<?php
	}
?>
											</dd>
										</dl>
<?php
}
?>
									</fieldset>
									<div class="button-box">
										<input type="submit" class="save-button input-button" value="<?php echo _t('저장하기');?>" onclick="setConfigFile(); return false;" />
									</div>
								</form>
							</div>
						</div>

						<div id="part-control-mailhost" class="part">
							<h2 class="caption"><span class="main-text"><?php	echo _t('메일 보낼 서버를 지정합니다');?></span></h2>

							<div class="data-inbox">
								<form class="section" method="post" action="<?php echo $blogURL;?>/control/server/mailhost">
									<dl>
										<dt class="title"><span class="label"><?php	echo _t('메일 서버 설정');?></span></dt>
										<dd>

											<div class="line">
												<input id="useCustomSMTP" type="checkbox" class="checkbox" name="useCustomSMTP" value="1" <?php if( getServiceSetting( 'useCustomSMTP', 0 ) ) { echo "checked='checked'"; } ?> onchange="ngUpdateDeps()" />
												<label for="useCustomSMTP"><?php echo _t('메일서버 지정'); ?></label>
											</div>
											<?php // Textcube-NG: 메일서버 IP·포트는 메일서버 지정을 켜야 쓰인다. 들여 쓰고, 꺼져 있으면 회색·비활성으로 표시한다(r3-34) ?>
											<div id="smtpHost-line" class="line ng-dep<?php echo getServiceSetting('useCustomSMTP', 0) ? '' : ' ng-off';?>">
												<label for="smtpHost"><?php echo _t('메일서버 IP 주소:포트'); ?></label>
												<input id="smtpHost" type="text" class="input-text" name="smtpHost"<?php echo getServiceSetting('useCustomSMTP', 0) ? '' : ' disabled="disabled"';?> value="<?php echo getServiceSetting( 'smtpHost', '127.0.0.1' ); ?>" /> :
												<input id="smtpPort" type="text" class="input-text" name="smtpPort"<?php echo getServiceSetting('useCustomSMTP', 0) ? '' : ' disabled="disabled"';?> value="<?php echo getServiceSetting( 'smtpPort', 25 );?>" />
												<span class="ng-help"><?php echo _t('메일서버 지정을 켜야 적용됩니다.');?></span>
											</div>
										</dd>
									</dl>
									<div class="button-box">
										<input type="submit" class="save-button input-button" value="<?php	echo _t('저장하기');?>" onclick="setSmtp(); return false;" />
									</div>
								</form>
							</div>
						</div>

						<hr class="hidden" />
						<div id="part-control-taglimit" class="part">
							<h2 class="caption"><span class="main-text"><?php echo _t('태그 목록 개수의 상한을 설정합니다');?></span></h2>
							<div class="main-explain-box">
								<p class="explain"><?php echo _t('각 블로그의 태그 목록 페이지와 태그 관리 화면에 표시하는 태그 개수(블로그 설정)는 이 값을 넘을 수 없습니다.').' '._t('1에서 100000 사이의 값을 입력합니다. 기본값은 2000입니다.');?></p>
							</div>
							<div class="data-inbox">
								<form class="section" method="post" action="<?php echo $blogURL;?>/control/server/taglimit/">
									<dl>
										<dt class="title"><span class="label"><?php echo _t('태그 목록 개수 상한');?></span></dt>
										<dd>
											<input id="maxTagsOnTagPage" type="text" class="input-text" name="maxTagsOnTagPage" size="8" value="<?php echo getMaxTagsOnTagPage(); ?>" />
										</dd>
									</dl>
									<div class="button-box">
										<input type="submit" class="save-button input-button" value="<?php echo _t('저장하기');?>" onclick="setTagLimit(); return false;" />
									</div>
								</form>
							</div>
						</div>

<?php if ($service['type'] == 'path') { ?>
						<hr class="hidden" />
						<div id="part-control-rootredirect" class="part">
							<h2 class="caption"><span class="main-text"><?php echo _t('루트 접근 리다이렉트를 설정합니다');?></span></h2>
							<div class="main-explain-box">
								<p class="explain"><?php echo _t('path 모드에서 도메인 루트(/)로 접근할 때 이동할 URL을 설정합니다.').' '._t('비워두면 첫 번째 블로그 경로로 자동으로 이동합니다.').' '._t('config.php에 $service[\'rootRedirectURL\']을 설정하면 이 값보다 우선 적용됩니다.');?></p>
							</div>
							<div class="data-inbox">
								<form class="section" method="post" action="<?php echo $blogURL;?>/control/server/rootredirect/">
									<dl>
										<dt class="title"><span class="label"><?php echo _t('루트 리다이렉트 URL');?></span></dt>
										<dd>
											<input id="rootRedirectURL" type="text" class="input-text" name="rootRedirectURL" size="60" value="<?php echo htmlspecialchars(Setting::getServiceSettingGlobal('rootRedirectURL', '')); ?>" />
											<label for="rootRedirectURL" class="ng-help"><?php echo _t('비워두면 첫 번째 블로그 경로로 자동 이동합니다. 예: https://example.com/blog/');?></label>
										</dd>
									</dl>
									<div class="button-box">
										<input type="submit" class="save-button input-button" value="<?php echo _t('저장하기');?>" onclick="setRootRedirect(); return false;" />
									</div>
								</form>
							</div>
						</div>
<?php } ?>
<?php if ($service['type'] == 'path') { ?>
						<hr class="hidden" />
						<div id="part-control-pathrouting" class="part">
							<h2 class="caption"><span class="main-text"><?php echo _t('알 수 없는 블로그 접근을 설정합니다');?></span></h2>
							<div class="main-explain-box">
								<p class="explain"><?php echo _t('path 모드에서 존재하지 않는 블로그 경로로 접근할 때의 동작을 설정합니다.').' '._t('기본값으로 두면 기본 블로그를 표시합니다.').' '._t('config.php에 $service[\'pathNotFoundBehavior\']를 설정하면 DB 설정보다 우선 적용됩니다.');?></p>
							</div>
							<div class="data-inbox">
								<form class="section" method="post" action="<?php echo $blogURL;?>/control/server/pathrouting/">
									<dl class="line">
										<dt><span class="label"><?php echo _t('미발견 블로그 동작');?></span></dt>
										<dd>
											<select id="pathNotFoundBehavior" name="pathNotFoundBehavior" onchange="updatePathRoutingFields()">
												<option value=""<?php echo (Setting::getServiceSettingGlobal('pathNotFoundBehavior','') === '') ? ' selected="selected"' : ''; ?>><?php echo _t('기본값 (기본 블로그 표시)');?></option>
												<option value="redirect"<?php echo (Setting::getServiceSettingGlobal('pathNotFoundBehavior','') === 'redirect') ? ' selected="selected"' : ''; ?>><?php echo _t('리다이렉트');?></option>
												<option value="404"<?php echo (Setting::getServiceSettingGlobal('pathNotFoundBehavior','') === '404') ? ' selected="selected"' : ''; ?>><?php echo _t('404 오류');?></option>
											</select>
										</dd>
									</dl>
									<dl id="pathNotFoundRedirectURL-row" class="line ng-dep" style="<?php echo (Setting::getServiceSettingGlobal('pathNotFoundBehavior','') === 'redirect') ? '' : 'display:none'; ?>">
										<dt><span class="label"><?php echo _t('리다이렉트 URL');?></span></dt>
										<dd>
											<input id="pathNotFoundRedirectURL" type="text" class="input-text" name="pathNotFoundRedirectURL" size="60" value="<?php echo htmlspecialchars(Setting::getServiceSettingGlobal('pathNotFoundRedirectURL', '')); ?>" />
										</dd>
									</dl>
									<div class="button-box">
										<input type="submit" class="save-button input-button" value="<?php echo _t('저장하기');?>" onclick="setPathRouting(); return false;" />
									</div>
								</form>
							</div>
						</div>
<?php } ?>
<?php if ($service['type'] == 'domain') { ?>
						<hr class="hidden" />
						<div id="part-control-domainrouting" class="part">
							<h2 class="caption"><span class="main-text"><?php echo _t('도메인 라우팅을 설정합니다');?></span></h2>
							<div class="main-explain-box">
								<p class="explain"><?php echo _t('domain 모드에서 루트 도메인(서브도메인 없음) 또는 알 수 없는 도메인으로 접근할 때의 동작을 설정합니다.').' '._t('config.php에서 설정하면 DB 설정보다 우선 적용됩니다.');?></p>
							</div>
							<div class="data-inbox">
								<form class="section" method="post" action="<?php echo $blogURL;?>/control/server/domainrouting/">
									<fieldset class="container">
										<legend><?php echo _t('루트 도메인 접근');?></legend>
										<dl class="line">
											<dt><span class="label"><?php echo _t('루트 도메인 동작');?></span></dt>
											<dd>
												<select id="domainRootBehavior" name="domainRootBehavior" onchange="updateDomainRoutingFields()">
													<option value=""<?php echo (Setting::getServiceSettingGlobal('domainRootBehavior','') === '') ? ' selected="selected"' : ''; ?>><?php echo _t('기본값 (기본 블로그 표시)');?></option>
													<option value="redirect"<?php echo (Setting::getServiceSettingGlobal('domainRootBehavior','') === 'redirect') ? ' selected="selected"' : ''; ?>><?php echo _t('리다이렉트');?></option>
													<option value="blog"<?php echo (Setting::getServiceSettingGlobal('domainRootBehavior','') === 'blog') ? ' selected="selected"' : ''; ?>><?php echo _t('특정 블로그 표시');?></option>
													<option value="404"<?php echo (Setting::getServiceSettingGlobal('domainRootBehavior','') === '404') ? ' selected="selected"' : ''; ?>><?php echo _t('404 오류');?></option>
												</select>
											</dd>
										</dl>
										<dl id="domainRootRedirectURL-row" class="line ng-dep" style="<?php echo (Setting::getServiceSettingGlobal('domainRootBehavior','') === 'redirect') ? '' : 'display:none'; ?>">
											<dt><span class="label"><?php echo _t('리다이렉트 URL');?></span></dt>
											<dd>
												<input id="domainRootRedirectURL" type="text" class="input-text" name="domainRootRedirectURL" size="60" value="<?php echo htmlspecialchars(Setting::getServiceSettingGlobal('domainRootRedirectURL', '')); ?>" />
											</dd>
										</dl>
										<dl id="domainRootBlogId-row" class="line ng-dep" style="<?php echo (Setting::getServiceSettingGlobal('domainRootBehavior','') === 'blog') ? '' : 'display:none'; ?>">
											<dt><span class="label"><?php echo _t('블로그 ID');?></span></dt>
											<dd>
												<input id="domainRootBlogId" type="text" class="input-text" name="domainRootBlogId" size="10" value="<?php echo htmlspecialchars(Setting::getServiceSettingGlobal('domainRootBlogId', '')); ?>" />
<?php // Textcube-NG: 블로그 구분자 앞부분으로 블로그를 찾아 ID 칸을 채우는 제안 칸. name 이 없어 저장 요청에 들어가지 않는다(v3.220) ?>
												<label for="domainRootBlogSuggest"><?php echo _t('블로그 구분자');?></label>
												<div class="ng-blog-suggest"><input id="domainRootBlogSuggest" type="text" class="input-text" size="20" maxlength="32" /></div>
											</dd>
										</dl>
									</fieldset>
									<fieldset class="container">
										<legend><?php echo _t('알 수 없는 도메인 접근');?></legend>
										<dl class="line">
											<dt><span class="label"><?php echo _t('도메인 불일치 동작');?></span></dt>
											<dd>
												<select id="domainMismatchBehavior" name="domainMismatchBehavior" onchange="updateDomainRoutingFields()">
													<option value=""<?php echo (Setting::getServiceSettingGlobal('domainMismatchBehavior','') === '') ? ' selected="selected"' : ''; ?>><?php echo _t('기본값 (404 오류)');?></option>
													<option value="redirect"<?php echo (Setting::getServiceSettingGlobal('domainMismatchBehavior','') === 'redirect') ? ' selected="selected"' : ''; ?>><?php echo _t('리다이렉트');?></option>
													<option value="blog"<?php echo (Setting::getServiceSettingGlobal('domainMismatchBehavior','') === 'blog') ? ' selected="selected"' : ''; ?>><?php echo _t('특정 블로그 표시');?></option>
													<option value="404"<?php echo (Setting::getServiceSettingGlobal('domainMismatchBehavior','') === '404') ? ' selected="selected"' : ''; ?>><?php echo _t('404 오류');?></option>
												</select>
											</dd>
										</dl>
										<dl id="domainMismatchRedirectURL-row" class="line ng-dep" style="<?php echo (Setting::getServiceSettingGlobal('domainMismatchBehavior','') === 'redirect') ? '' : 'display:none'; ?>">
											<dt><span class="label"><?php echo _t('리다이렉트 URL');?></span></dt>
											<dd>
												<input id="domainMismatchRedirectURL" type="text" class="input-text" name="domainMismatchRedirectURL" size="60" value="<?php echo htmlspecialchars(Setting::getServiceSettingGlobal('domainMismatchRedirectURL', '')); ?>" />
											</dd>
										</dl>
										<dl id="domainMismatchBlogId-row" class="line ng-dep" style="<?php echo (Setting::getServiceSettingGlobal('domainMismatchBehavior','') === 'blog') ? '' : 'display:none'; ?>">
											<dt><span class="label"><?php echo _t('블로그 ID');?></span></dt>
											<dd>
												<input id="domainMismatchBlogId" type="text" class="input-text" name="domainMismatchBlogId" size="10" value="<?php echo htmlspecialchars(Setting::getServiceSettingGlobal('domainMismatchBlogId', '')); ?>" />
<?php // Textcube-NG: 위와 같은 블로그 제안 칸(v3.220) ?>
												<label for="domainMismatchBlogSuggest"><?php echo _t('블로그 구분자');?></label>
												<div class="ng-blog-suggest"><input id="domainMismatchBlogSuggest" type="text" class="input-text" size="20" maxlength="32" /></div>
												<?php // Textcube-NG: 서비스 리소스 경로를 지정하면 이 블로그를 표시해도 서비스 주소(스킨·첨부 경로 등)는 그 블로그 주소로 바뀌지 않는다는 안내(r3-34) ?>
												<?php if (isset($service['serviceURL'])) echo '<span class="ng-help">' . _t('서비스 리소스 경로가 지정되어 있으면 특정 블로그 표시에서도 서비스 주소를 그 블로그 주소로 바꾸지 않습니다.') . '</span>';?>
											</dd>
										</dl>
									</fieldset>
									<div class="button-box">
										<input type="submit" class="save-button input-button" value="<?php echo _t('저장하기');?>" onclick="setDomainRouting(); return false;" />
									</div>
								</form>
							</div>
						</div>
<?php } ?>

						<hr class="hidden" />
<?php
if(!defined('__TEXTCUBE_NO_FANCY_URL__')) {
?>
						<div id="part-control-rewrite" class="part">
							<h2 class="caption"><span class="main-text"><?php	echo _t('rewrite 규칙을 편집합니다');?></span></h2>
							<div class="main-explain-box">
								<p class="explain"><?php echo _t('rewrite 모듈의 동작을 조정하는 .htaccess 파일을 변경합니다.').' '._t('변경시 텍스트큐브의 동작에 큰 영향을 줄 수 있으므로 주의하시기 바랍니다.');?></p>
<?php
if (!is_writable(ROOT . "/.htaccess")) {
?>
								<p class="warning"><?php echo _f('파일 쓰기 권한이 없습니다. 웹서버가 %1 파일의 쓰기 권한을 가지고 있는지 확인하세요.','.htaccess').' '._t('아래의 설정을 저장하셔도 반영되지 않습니다.');?></p>
<?php
}
?>
							</div>
							<div class="data-inbox">
								<form id="rewriteSectionForm" class="section" method="post" action="<?php echo $blogURL;?>/control/server/rewrite/">

									<div id="rewrite-container">
										<textarea id="rewrite" name="htaccess" cols="100" rows="20" onkeyup="htaccessSaved=false"><?php echo htmlspecialchars($htaccessContent);?></textarea>
									</div>
									<div class="button-box">
										<input type="button" class="default-button input-button" value="<?php echo _t('기본값');?>" onclick="setDefault();return false;" />
										<input type="reset" class="reset-button input-button" value="<?php echo _t('되돌리기');?>" />
										<input type="submit" class="save-button input-button" value="<?php echo _t('저장하기');?>" onclick="setRewrite(); return false;" />
									</div>
								</form>
							</div>
						</div>
						<hr class="hidden" />
<?php
}
if (isset($_GET['message'])) {
	$msg = escapeJSInCData($_GET['message']);
?>
	<script type="text/javascript">
		//<![CDATA[
			window.onload = function() { PM.showMessage("<?php echo $msg;?>", "center", "bottom"); }
		//]]>
	</script>
<?php
}

require ROOT . '/interface/common/control/footer.php';
?>
