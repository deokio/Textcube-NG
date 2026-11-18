<?php
/// Copyright (c) 2004-2016, Needlworks  / Tatter Network Foundation
/// All rights reserved. Licensed under the GPL.
/// See the GNU General Public License for more details. (/documents/LICENSE, /documents/COPYRIGHT)
/* Textcube-NG: 플러그인 데이터 관리(플러그인이 만든 테이블 목록·삭제)(v3.210). 서비스 관리자(group.creators)만.
   옛 /owner/plugin/tableSetting 을 서비스 관리로 옮겼다. 목록은 getPluginTableList()(common.plugin), 삭제는 /control/plugin/table/delete(POST).
   다른 설치로 보이는 테이블(isOtherInstallTableName)은 표시만 하고 삭제 단추를 내지 않는다.
   출력은 모두 htmlspecialchars, 삭제 단추의 이름은 data-name 속성으로 넘긴다(옛 화면은 escape 없이 onclick 문자열에 넣었다) */
// Textcube-NG: 표 아래 사용여부·알 수 없음·다른 설치로 보임의 뜻과 삭제 범위 도움말을 붙임, 삭제 단추 아이콘은 resources/control/image/icon_delete.gif(v3.218)
require ROOT . '/library/preprocessor.php';
requirePrivilege('group.creators');
require ROOT . '/interface/common/control/header.php';

$list = getPluginTableList();
?>
						<script type="text/javascript">
							//<![CDATA[
								function deletePluginTable(name, type) {
									tcConfirm("<?php echo _t('해당 테이블을 정말 삭제하시겠습니까? 사용중인 플러그인은 지금 블로그에서만 해제되고, 다른 블로그에서는 켜진 채로 남습니다.');?>", function () {	// Textcube-NG: 확인창을 페이지 위 안내 줄의 [확인]/[취소]로 바꿈(r3-17, R-396)
									var request = new HTTPRequest("POST", "<?php echo $blogURL;?>/control/plugin/table/delete/");
									request.onSuccess = function() {
										alert("<?php echo _t('테이블이 삭제되었습니다.');?>");
										window.location.href = "<?php echo $blogURL;?>/control/plugin/table";
									}
									request.onError = function() {
										alert("<?php echo _t('테이블을 지우지 못했습니다.');?>");
									}
									request.send("name=" + encodeURIComponent(name) + "&type=" + type);
									});
								}
							//]]>
						</script>

						<div id="part-plugin-table-list" class="part">
							<h2 class="caption"><span class="main-text"><?php echo _t('플러그인이 생성한 테이블입니다');?></span></h2>

							<div class="main-explain-box">
								<p class="explain"><?php echo _t('플러그인이 생성한 테이블입니다. 더이상 사용하지 않는 플러그인이 예전에 만든 테이블의 경우 테이블의 데이터를 삭제할 수 있습니다. 다중 사용자 모드에서 서비스를 운영하는 경우 자신이 사용하지 않더라도 다른 사용자가 해당 플러그인을 사용할 수 있으니 삭제시 유의하시기 바랍니다.');?></p>
							</div>

							<table class="data-inbox" cellspacing="0" cellpadding="0">
								<thead>
									<tr>
										<th class="title"><span class="text"><?php echo _t('플러그인 이름');?></span></th>
										<th class="version"><span class="text"><?php echo _t('버전');?></span></th>
										<th class="using"><span class="text"><?php echo _t('사용여부');?></span></th>
										<th class="tablename"><span class="text"><?php echo _t('테이블 이름');?></span></th>
										<th class="delete"><span class="text"><?php echo _t('삭제');?></span></th>
									</tr>
								</thead>
								<tbody>
<?php
$oddline = true;
foreach ($list['plugins'] as $id => $plugindb) {
	$className = $oddline ? 'odd-line' : 'even-line';
	$oddline = !$oddline;
	$tableNames = array();
	foreach ($plugindb['tables'] as $table) {
		array_push($tableNames, '<span class="table-name">' . htmlspecialchars($table) . '</span>');
	}
?>
									<tr class="<?php echo $className;?>" onmouseover="rolloverClass(this, 'over')" onmouseout="rolloverClass(this, 'out')">
										<td class="title"><?php echo htmlspecialchars($plugindb['plugin']);?></td>
										<td class="version"><?php echo htmlspecialchars($plugindb['version']);?></td>
										<td class="using <?php echo ($plugindb['blogs'] > 0) ? 'active-class' : 'inactive-class';?>"><?php echo ($plugindb['blogs'] > 0) ? _t('사용중') . ' (' . intval($plugindb['blogs']) . ')' : _t('미사용');?></td>
										<td class="tablename"><?php echo empty($tableNames) ? '-' : implode(', ', $tableNames);?></td>
										<td class="delete"><a class="delete-button active-class" href="#void" data-name="<?php echo htmlspecialchars($id);?>" onclick="deletePluginTable(this.getAttribute('data-name'), 1); return false" title="<?php echo _t('이 테이블을 삭제합니다.');?>"><span class="text"><?php echo _t('삭제');?></span></a></td>
									</tr>
<?php
}
foreach ($list['unknown'] as $table) {
	$className = $oddline ? 'odd-line' : 'even-line';
	$oddline = !$oddline;
?>
									<tr class="<?php echo $className;?>" onmouseover="rolloverClass(this, 'over')" onmouseout="rolloverClass(this, 'out')">
										<td class="title"><?php echo _t('알 수 없음');?></td>
										<td class="version"></td>
										<td class="using"></td>
										<td class="tablename"><span class="table-name"><?php echo htmlspecialchars($table);?></span></td>
										<td class="delete"><a class="delete-button active-class" href="#void" data-name="<?php echo htmlspecialchars($table);?>" onclick="deletePluginTable(this.getAttribute('data-name'), 2); return false" title="<?php echo _t('이 테이블을 삭제합니다.');?>"><span class="text"><?php echo _t('삭제');?></span></a></td>
									</tr>
<?php
}
foreach ($list['others'] as $table) {
	$className = $oddline ? 'odd-line' : 'even-line';
	$oddline = !$oddline;
?>
									<tr class="<?php echo $className;?>" onmouseover="rolloverClass(this, 'over')" onmouseout="rolloverClass(this, 'out')">
										<td class="title"><?php echo _t('다른 설치로 보임');?></td>
										<td class="version"></td>
										<td class="using"></td>
										<td class="tablename"><span class="table-name"><?php echo htmlspecialchars($table);?></span></td>
										<td class="delete">-</td>
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
// Textcube-NG: 표 아래 도움말(p.ng-help)을 '범례' 테두리 상자 안 [표시 | 뜻] 2열 표로 바꿈. 문구는 기존 '표시 : 뜻' 번역을 첫 ' : ' 에서 나눠 쓰고(번역에 ' : ' 이 없으면 뜻 칸에 전체), 표시 칸은 목록의 사용여부 칸(td.using active-class·inactive-class)·이름 칸(td.title)과 같은 class(v3.220)
foreach (array(
	array(_t('사용중 (숫자) : 그 수만큼의 블로그에서 켠 플러그인입니다. 삭제하면 테이블과 등록 기록을 지우고, 지금 블로그에서만 그 플러그인을 끕니다. 다른 블로그에서는 켜진 채로 남습니다.'), 'using active-class'),
	array(_t('미사용 : 켠 블로그가 없는 플러그인입니다.'), 'using inactive-class'),
	array(_t('알 수 없음 : 어느 플러그인 등록과도 이어지지 않은 테이블입니다. 삭제하면 그 테이블만 지웁니다.'), 'title'),
	array(_t('다른 설치로 보임 : 접두어 뒤에 다른 이름과 코어 테이블 이름이 붙은 꼴이라, 같은 DB 를 쓰는 다른 설치의 테이블로 보고 표시만 합니다(삭제 단추 없음).'), 'title')
) as $pluginTableLegendLine) {
	$pluginTableLegend = array_pad(explode(' : ', $pluginTableLegendLine[0], 2), -2, '');
?>
										<tr>
											<td class="mark <?php echo $pluginTableLegendLine[1];?>"><?php echo htmlspecialchars($pluginTableLegend[0], ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');?></td>
											<td class="meaning"><?php echo htmlspecialchars($pluginTableLegend[1], ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');?></td>
										</tr>
<?php
}
?>
									</tbody>
								</table>
							</div>
						</div>
<?php
require ROOT . '/interface/common/control/footer.php';
?>
