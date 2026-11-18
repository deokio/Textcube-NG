<?php
/// Copyright (c) 2004-2016, Needlworks  / Tatter Network Foundation
/// All rights reserved. Licensed under the GPL.
/// See the GNU General Public License for more details. (/documents/LICENSE, /documents/COPYRIGHT)
/* Textcube-NG: 플러그인 허용 목록·무결성 엄격 모드·PDO 검사 결과 화면(v3.211). 서비스 관리자(group.creators)만.
   값은 서비스 설정 pluginAllowlistEnabled·pluginAllowlist·pluginIntegrityStrictEnabled(save/)에 둔다. 허용 목록이 꺼져 있으면 모든 플러그인을 허용한다 */
require ROOT . '/library/preprocessor.php';
requirePrivilege('group.creators');
requireModel('common.plugin');
require ROOT . '/interface/common/control/header.php';

$pluginGuardAllow = pluginGuardAllowlist(true);
$pluginGuardStrict = pluginGuardStrictEnabled(true);
$pluginGuardIsPDO = pluginGuardIsPDO();
$pluginGuardCore = pluginGuardCorePlugins();
$pluginGuardList = array();
$pluginGuardEntries = @scandir(ROOT . '/plugins');
if (is_array($pluginGuardEntries)) {
	foreach ($pluginGuardEntries as $pluginGuardName) {
		if ($pluginGuardName === '.' || $pluginGuardName === '..' || !pluginGuardNameIsValid($pluginGuardName) || !is_dir(ROOT . '/plugins/' . $pluginGuardName)) {
			continue;
		}
		$pluginGuardInfo = getPluginInformation($pluginGuardName);
		if (!is_array($pluginGuardInfo)) {
			continue;
		}
		$pluginGuardList[$pluginGuardName] = is_string($pluginGuardInfo['title']) ? $pluginGuardInfo['title'] : '';
	}
}
ksort($pluginGuardList, SORT_STRING);
// Textcube-NG: 화면을 보호 모드 구역(설정·도움말)과 플러그인 목록 표(허용·폴더 이름·제목·검사 결과)로 나누고 판정 설명을 붙임 — 입력 id·class·value 와 저장 스크립트는 그대로(v3.218)
?>
						<script type="text/javascript">
							//<![CDATA[
							function setPluginAllowlist() {
								// Textcube-NG: 플러그인 허용 목록 저장(v3.211)
								var enabled = document.getElementById('pluginAllowlistEnabled').checked ? 1 : 0;
								var strict = document.getElementById('pluginIntegrityStrictEnabled').checked ? 1 : 0;
								var inputs = document.getElementsByTagName('input');
								var allowlist = [];
								for (var i = 0; i < inputs.length; i++) {
									if ((' ' + inputs[i].className + ' ').indexOf(' plugin-allow ') != -1 && inputs[i].checked) {
										allowlist.push(inputs[i].value);
									}
								}
								var request = new HTTPRequest("POST", "<?php echo $blogURL;?>/control/plugin/save/");
								request.onSuccess = function() {
									PM.showMessage("<?php echo _t('저장했습니다');?>", "center", "bottom");
								}
								request.onError = function() {
									PM.showErrorMessage("<?php echo _t('저장하지 못했습니다');?>", "center", "bottom");
								}
								request.send("enabled=" + enabled + "&strict=" + strict + "&allowlist=" + encodeURIComponent(JSON.stringify(allowlist)) + "&noticeLimit=" + encodeURIComponent(document.getElementById("pluginNoticeLimit").value));
							}
							//]]>
						</script>

						<div id="part-control-plugin" class="part">
							<h2 class="caption"><span class="main-text"><?php echo _t('플러그인 허용 목록');?></span></h2>
							<div class="main-explain-box">
								<p class="explain"><?php echo _t('서비스 전체에 적용되는 플러그인 보호 설정입니다. 플러그인을 불러올 때마다 허용 목록·무결성 기록·PDO 검사 결과를 다시 확인하고, 맞지 않으면 켜져 있어도 불러오지 않습니다.');?></p>
							</div>

							<div class="data-inbox">
								<form id="pluginAllowlistSetting" class="section" method="post" action="<?php echo $blogURL;?>/control/plugin/save/" enctype="application/x-www-form-urlencoded">
									<h3 class="ng-section-title"><?php echo _t('보호 모드');?></h3>
									<fieldset id="plugin-allowlist-container" class="container">
										<legend><?php echo _t('보호 모드');?></legend>
										<dl id="plugin-allowlist-line" class="line">
											<dt><span class="label"><?php echo _t('허용 목록 사용');?></span></dt>
											<dd>
												<input type="checkbox" id="pluginAllowlistEnabled" class="checkbox" name="pluginAllowlistEnabled"<?php echo $pluginGuardAllow[0] ? ' checked="checked"' : '';?> /><label for="pluginAllowlistEnabled"><?php echo _t('켜면 아래에서 고른 플러그인만 쓸 수 있습니다.');?></label>
											</dd>
										</dl>
										<p class="ng-help"><?php echo _t('허용 목록을 켜면 고른 플러그인만 켤 수 있고, 목록 밖 플러그인은 이미 켜져 있어도 불러오지 않습니다. 끄면 모든 플러그인을 허용합니다.');?></p>
										<dl id="plugin-strict-line" class="line">
											<dt><span class="label"><?php echo _t('무결성 엄격 모드');?></span></dt>
											<dd>
												<input type="checkbox" id="pluginIntegrityStrictEnabled" class="checkbox" name="pluginIntegrityStrictEnabled"<?php echo $pluginGuardStrict ? ' checked="checked"' : '';?> /><label for="pluginIntegrityStrictEnabled"><?php echo _t('켜면 무결성 기록이 없는 플러그인을 막습니다.');?></label>
											</dd>
										</dl>
										<p class="ng-help"><?php echo _t('무결성 엄격 모드를 켜면 무결성 기록이 없는 플러그인(기록을 저장하지 못한 경우 포함)을 불러오지 않습니다. 끄면 처음 본 상태를 기준값으로 삼습니다.');?></p>
										<p class="ng-help"><?php echo _t('무결성 기록은 플러그인을 켤 때 블로그마다 남기는 플러그인 폴더 안 모든 파일의 sha256 묶음값입니다. 불러올 때 파일이 기록과 다르면 엄격 모드와 관계없이 불러오지 않습니다. 플러그인 파일을 고친 뒤에는 그 플러그인을 껐다가 다시 켜야 새 기록이 남습니다.');?></p>
										<dl id="plugin-driver-line" class="line">
											<dt><span class="label"><?php echo _t('DB 연결 방식');?></span></dt>
											<dd><?php echo htmlspecialchars($pluginGuardIsPDO ? 'PDO' : 'native', ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');?></dd>
										</dl>
										<p class="ng-help"><?php echo _t('config.php 의 $database 설정 driver 값으로 정합니다(Cubrid·MySQL·MySQLi·PostgreSQL·SQLite3 공통, 비우면 native). PDO 에서는 직접 SQL 이나 판정 불가 호출(검토된 번들 예외 제외)이 있는 플러그인을 켤 수 없고, 이미 켜져 있어도 불러오지 않습니다. native 에서는 켜되 경고합니다.');?></p>
										<dl id="plugin-notice-limit-line" class="line">
											<dt><span class="label"><?php echo _t('알림 저장 개수');?></span></dt>
											<dd><input type="text" class="input-text" id="pluginNoticeLimit" name="pluginNoticeLimit" size="4" maxlength="3" value="<?php echo (int)pluginNoticeLimit(true);?>" /></dd>
										</dl>
										<p class="ng-help"><?php echo _t('대시보드 플러그인 알림을 블로그마다 이 개수(1~200)까지 남깁니다. 넘치면 확인한 알림 가운데 오래된 것부터 지우고, 확인하지 않은 알림은 저장 크기 한도를 넘을 때만 지웁니다.');?></p>
									</fieldset>
									<h3 class="ng-section-title"><?php echo _t('플러그인 목록');?></h3>
									<fieldset id="plugin-list-container" class="container">
										<legend><?php echo _t('플러그인 목록');?></legend>
										<table class="data-inbox" cellspacing="0" cellpadding="0">
											<thead>
												<tr>
													<th class="allow"><span class="text"><?php echo _t('허용');?></span></th>
													<th class="folder"><span class="text"><?php echo _t('폴더 이름');?></span></th>
													<th class="plugin-title"><span class="text"><?php echo _t('제목');?></span></th>
													<th class="scan"><span class="text"><?php echo _t('검사 결과');?></span></th>
												</tr>
											</thead>
											<tbody>
<?php
$pluginGuardIndex = 0;
foreach ($pluginGuardList as $pluginGuardName => $pluginGuardTitle) {
	$pluginGuardIndex++;
	$pluginGuardIsCore = in_array($pluginGuardName, $pluginGuardCore, true);
	$pluginGuardChecked = ($pluginGuardIsCore || in_array($pluginGuardName, $pluginGuardAllow[1], true));
	$pluginGuardScanResult = pluginGuardScan($pluginGuardName);
	$pluginGuardLabels = array();
	if (((int)$pluginGuardScanResult['sql'] + (int)$pluginGuardScanResult['conn']) > 0) {
		$pluginGuardLabels[] = ($pluginGuardIsPDO ? _t('사용 불가') . '(PDO)' : _t('직접 SQL')) . ' ' . ((int)$pluginGuardScanResult['sql'] + (int)$pluginGuardScanResult['conn']) . pluginGuardScanSummary($pluginGuardScanResult, array('sql', 'conn'));
	}
	if ((int)$pluginGuardScanResult['dyn'] > 0) {
		if (pluginGuardDynExempt($pluginGuardName, $pluginGuardScanResult)) {
			$pluginGuardLabels[] = _t('검토된 번들 예외') . ' (' . _t('판정 불가 호출') . ' ' . (int)$pluginGuardScanResult['dyn'] . ')';
		} else {
			$pluginGuardLabels[] = ($pluginGuardIsPDO ? _t('사용 불가') . '(PDO) ' : '') . _t('판정 불가 호출') . ' ' . (int)$pluginGuardScanResult['dyn'] . pluginGuardScanSummary($pluginGuardScanResult, array('dyn'));
		}
	}
	if (empty($pluginGuardLabels)) {
		$pluginGuardLabels[] = _t('통과');
	}
	if ($pluginGuardIsCore) {
		$pluginGuardLabels[] = _t('항상 허용');
	}
?>
												<tr id="plugin-allow-line-<?php echo $pluginGuardIndex;?>">
													<td class="allow"><input type="checkbox" id="pluginAllow<?php echo $pluginGuardIndex;?>" class="checkbox plugin-allow" value="<?php echo htmlspecialchars($pluginGuardName, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');?>"<?php echo $pluginGuardChecked ? ' checked="checked"' : '';?><?php echo $pluginGuardIsCore ? ' disabled="disabled"' : '';?> /></td>
													<td class="folder"><label for="pluginAllow<?php echo $pluginGuardIndex;?>"><?php echo htmlspecialchars($pluginGuardName, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');?></label></td>
													<td class="plugin-title"><?php echo htmlspecialchars($pluginGuardTitle, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');?></td>
													<td class="scan"><span class="plugin-guard-scan"><?php echo htmlspecialchars(implode(', ', $pluginGuardLabels), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');?></span></td>
												</tr>
<?php
}
?>
											</tbody>
										</table>
										<div class="ng-legend">
											<h4 class="ng-legend-title"><?php echo _t('범례');?></h4>
											<table class="ng-legend-table" cellspacing="0" cellpadding="0">
												<thead>
													<tr>
														<th class="mark"><span class="text"><?php echo _t('표시');?></span></th>
														<th class="meaning"><span class="text"><?php echo _t('뜻');?></span></th>
													</tr>
												</thead>
												<tbody>
<?php
// Textcube-NG: 판정 설명(p.ng-help)을 '범례' 테두리 상자 안 [표시 | 뜻] 2열 표로 바꿈. 문구는 기존 '표시 : 뜻' 번역을 첫 ' : ' 에서 나눠 쓰고(번역에 ' : ' 이 없으면 뜻 칸에 전체), 표시 칸은 목록 검사 결과 칸과 같은 마크업(td.scan·span.plugin-guard-scan). 괄호 안 설명 줄은 표 아래 p.ng-help 로 둠(v3.220)
foreach (array(
	_t('통과 : 직접 SQL·판정 불가 호출을 찾지 못했습니다.'),
	_t('직접 SQL : POD 로 문자열 SQL 을 실행하거나 Textcube 가 제공하지 않는 DB 연결 요소를 쓰는 곳이 있습니다(뒤의 숫자는 곳의 수). PDO 에서는 사용 불가(PDO)로 표시합니다.'),
	_t('판정 불가 호출 : 변수 함수 호출·eval 처럼 정적 검사로 대상을 정할 수 없는 호출이 있습니다(뒤의 숫자는 곳의 수). PDO 에서는 앞에 사용 불가(PDO)를 붙입니다.'),
	_t('검토된 번들 예외 : 판정 불가 호출을 검토한 번들 플러그인입니다. 파일 sha256 을 고정해 두었으므로 파일이 하나라도 다르면 예외가 풀립니다.'),
	_t('항상 허용 : 코어 플러그인(FM_TTML·ED_tinyMCE)은 허용 목록과 관계없이 허용합니다. 무결성 검사는 같게 받습니다.')
) as $pluginGuardLegendLine) {
	$pluginGuardLegend = array_pad(explode(' : ', $pluginGuardLegendLine, 2), -2, '');
?>
													<tr>
														<td class="mark scan"><span class="plugin-guard-scan"><?php echo htmlspecialchars($pluginGuardLegend[0], ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');?></span></td>
														<td class="meaning"><?php echo htmlspecialchars($pluginGuardLegend[1], ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');?></td>
													</tr>
<?php
}
?>
												</tbody>
											</table>
											<p class="ng-help"><?php echo _t('직접 SQL·판정 불가 호출 뒤의 괄호 안은 찾은 곳 가운데 앞의 3곳까지의 파일:줄과 찾은 내용입니다.');?></p>
										</div>
									</fieldset>
									<div class="button-box">
										<input type="submit" class="save-button input-button" value="<?php echo _t('저장하기');?>" onclick="setPluginAllowlist(); return false;" />
									</div>
								</form>
							</div>
						</div>
<?php
require ROOT . '/interface/common/control/footer.php';
?>
