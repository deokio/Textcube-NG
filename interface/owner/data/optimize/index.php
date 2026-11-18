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
requirePostMethod();	// Textcube-NG: 상태 변경은 POST 만 받는다(그 밖은 405) — 링크·<img> 로 실행되던 GET CSRF 차단(v3.148)
requireStrictRoute();
requirePrivilege('group.creators');	// Textcube-NG: 서비스 전체 테이블(getDefinedTableNames·getPluginTableName)을 OPTIMIZE 하므로 서비스 관리자(group.creators)만 — 폼 표시 조건(owner/data·center/dashboard)과 같게, 그 밖은 404(v3.154)
?>
<!DOCTYPE html PUBLIC "-//W3C//DTD XHTML 1.1//EN" "http://www.w3.org/TR/xhtml11/DTD/xhtml11.dtd">
<html xmlns="http://www.w3.org/1999/xhtml" xml:lang="ko">
<head>
	<title>Textcube Data Optimizing</title>
	<meta http-equiv="Content-Type" content="text/html; charset=utf-8" />
	<script type="text/javascript">
		//<![CDATA[
			var pi = window.parent.document.getElementById("optimizingIndicator");
			var pt = window.parent.document.getElementById("optimizingText");
			var pts = window.parent.document.getElementById("optimizingTextSub");
		//]]>
	</script>
</head>
<body>
<?php
function finish($error = null) {
?>
	<script type="text/javascript">
		//<![CDATA[
<?php
	if ($error) {
?>
			alert("<?php echo $error;?>");
<?php
	} else {
?>
			alert("<?php echo _t('성공적으로 최적화 되었습니다.');?>");
<?php
	}
?>
			window.parent.document.getElementById("optimizingDataDialog").style.display = "none";
			window.parent.document.getElementById("optimizingDataDialogTitle").innerHTML = "";
			window.parent.document.getElementById("optimizingText").innerHTML = "";
			window.parent.document.getElementById("optimizingTextSub").innerHTML = "";
		//]]>
	</script>
	<?php echo _t('완료.');?>
</body>
</html>
<?php
	exit;
}
$lastProgress = 0;
$lastProgressText = null;
$lastProgressTextSub = null;

function setProgress($progress, $text = null, $sub = null) {
	global $lastProgress, $lastProgressText, $lastProgressTextSub;
	$progress = intval($progress);
	$diff = '';
	if (isset($progress) && ($progress != $lastProgress)) {
		$lastProgress = $progress;
		$diff .= 'pi.style.width = "' . $progress . '%";';
	}
	if (isset($text) && ($text != $lastProgressText)) {
		$lastProgressText = $text;
		$diff .= 'pt.innerHTML = "' . $text . '";';
		if (!isset($sub)) {
			$lastProgressTextSub = '';
			$diff .= 'pts.innerHTML = "";';
		}
	}
	if (isset($sub) && ($sub != $lastProgressTextSub)) {
		$lastProgressTextSub = $sub;
		$diff .= 'pts.innerHTML = "(' . $sub . ')";';
	}
	if (!empty($diff)) {
?>
<script type="text/javascript">
	//<![CDATA[
		<?php echo $diff;?>
	//]]>
</script>
<?php
		flush();
	}
}

setProgress(0, _t('최적화 작업을 진행할 테이블을 확인하고 있습니다.'));
$items = 0;
set_time_limit(0);
$item = 0;
$optimized = 0;

$tcTables = getDefinedTableNames();
$tcPluginTables = getPluginTableName();
$workarounds = array_merge($tcTables, $tcPluginTables);

$items = $items + count($tcTables) + count($tcPluginTables);

foreach($workarounds as $work) {
		setProgress($item++ / ($items * 100), _f('%1 테이블을 최적화하고 있습니다.',$work));
		POD::query("OPTIMIZE TABLE {$work}");
		$optimized++;
}

setProgress(100, _t('완료되었습니다.') . "($optimized)");
finish();
?>
