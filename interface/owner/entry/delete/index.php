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
$IV = array(
	'POST' => array(
		'targets' => array ('list','mandatory'=>false)
	)
);
require ROOT . '/library/preprocessor.php';
requirePostMethod();	// Textcube-NG: 상태 변경은 POST 만 받는다(그 밖은 405) — 링크·<img> 로 실행되던 GET CSRF 차단(v3.148)
requireModel("blog.entry");


requireStrictRoute();

$isAjaxRequest = checkAjaxRequest();
if(isset($suri['id'])) {
	if(!Acl::check("group.editors")) {
		if(getUserIdOfEntry(getBlogId(), $suri['id']) != getUserId()) {
			Respond::ResultPage(-1);
			exit;
		}
	}			
	
	if ($isAjaxRequest) {
		if (deleteEntry($blogid, $suri['id']) === true) {
			Respond::ResultPage(0);
		} else {
			Respond::ResultPage(-1);
		}
	} else {
		deleteEntry($blogid, $suri['id']);
		header("Location: ".getSafeRedirectURI(isset($_SERVER['HTTP_REFERER']) ? (string)$_SERVER['HTTP_REFERER'] : '', $blogURL.'/owner/entry')); // Textcube-NG: Referer 가 없거나 다른 출처면 /owner/entry 로(r3-8)
	}
} else {
	foreach(explode(',', $_POST['targets']) as $target) {
		// TeamBlog check
		/* [보안] 원본은 group.writers(전 구성원이 가짐)를 검사하고 작성자 확인도 $suri['id'](일괄 삭제 시 없음)로 해서
		   다른 사람의 글을 일괄 삭제할 수 있었다 → 단건 삭제와 같게 편집자가 아니면 각 대상 글($target)의 작성자 확인 */
		if(!Acl::check( 'group.editors', 'entry.delete.' . $target )) {
			if(getUserIdOfEntry(getBlogId(), (int)$target) != getUserId()) { 
				Respond::ResultPage(-1);
				exit;
			}
		}
		
		if (!deleteEntry($blogid, $target))
			Respond::ResultPage(-1);
	}
	Respond::ResultPage(0);
}
?>
