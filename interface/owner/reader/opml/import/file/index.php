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
// Textcube-NG: 원본은 입력 규칙 변수 이름이 $VI 로 잘못 적혀 preprocessor 의 입력 검사(Validator::validate($IV))가 동작하지 않았다.
// 파일 필드가 없거나 여러 파일 형식(opmlFile[])이면 PHP 8 에서 file_get_contents() 가 ValueError·TypeError(500)를 냈다(v3.144 보완)
$IV = array(
	'FILES' => array(
		'opmlFile' => array('file')
	)
);
require ROOT . '/library/preprocessor.php';
requireStrictRoute();
set_time_limit(60);
?><!DOCTYPE html PUBLIC "-//W3C//DTD XHTML 1.1//EN" "http://www.w3.org/TR/xhtml11/DTD/xhtml11.dtd">
<html xmlns="http://www.w3.org/1999/xhtml" xml:lang="ko">
	<head profile="http://gmpg.org/xfn/11">
		<title>OPML Uploading</title>
		<meta http-equiv="content-type" content="text/html; charset=utf-8" />
	</head>
	<body>
		<script type="text/javascript">
			//<![CDATA[
<?php
// Textcube-NG: 파일을 고르지 않았거나(error 4) 업로드가 실패한 항목(error ≠ UPLOAD_ERR_OK)은 tmp_name 이 빈 값이다. 원본(PHP 7.4)은
// @file_get_contents('') 가 false 라 "파일 업로드에 실패했습니다." 알림이었으나 PHP 8 은 ValueError(500) — 업로드 오류 코드를 먼저 확인(v3.144 보완)
if (isset($_FILES['opmlFile']['error']) && $_FILES['opmlFile']['error'] === UPLOAD_ERR_OK && ($xml = @file_get_contents($_FILES['opmlFile']['tmp_name']))) {
	list($status, $result) = importOPMLFromFile($blogid, $xml);
	if ($status == 0) {
		if($result['total'] == 1) {
?>
					var str = "<?php echo _f('하나의 피드를 가져왔습니다. 피드를 업데이트 해 주십시오.', $result['total']);?>";
<?php
		} else {
?>
					var str = "<?php echo _f('%1개의 피드를 가져왔습니다. 피드를 업데이트 해 주십시오.', $result['total']);?>";
<?php
		}
?>
					parent.Reader.refreshFeedGroup();
					parent.Reader.refreshFeedList(0);
					parent.Reader.refreshEntryList(0, 0);
					parent.Reader.refreshEntry(0, 0, 0);
					alert(str);
					<?php
	} else if ($status == 1) {
		echo 'alert("' . _t('올바른 XML 파일이 아닙니다.') . '");';
	} else if ($status == 2) {
		echo 'alert("' . _t('올바른 OPML 파일이 아닙니다.') . '");';
	}
} else
	echo 'alert("' . _t('파일 업로드에 실패했습니다.') . '");';
?>
			//]]>
		</script>
	</body>
</html>
