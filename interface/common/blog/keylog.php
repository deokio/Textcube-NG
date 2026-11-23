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

// $keylog : $keylog (explanation for specific keyword)
// $entries : Posts that contain specific keyword.

$adminSkinSetting['skin'] = "/skin/admin/".getBlogSetting("adminSkin", "canon");
$skin = new KeylogSkin($skinSetting['keylogSkin']);
// Textcube-NG: 이식본에서 고친 스크립트(common3.js·gallery.js)는 externalresources 설정과 관계없이 이 서버의 /resources 에서 읽는다(r3-3)
$out = str_replace("[##_SKIN_head_end_##]", '<script type="text/javascript">//<![CDATA' . CRLF . 'var servicePath = "' . $service['path'] . '"; var blogURL = "' . $blogURL . '"; var adminSkin = "' . $adminSkinSetting['skin'] . '";//]]></script><script type="text/javascript" src="' . $service['path'] . '/resources/script/common3.js?v=' . filemtime(ROOT . '/resources/script/common3.js') . '"></script><script type="text/javascript" src="' . $service['path'] . '/resources/script/gallery.js"></script>' . $skin->skin, $skin->outter);
$keylogView = $skin->keylog;
$itemsView = '';
$contentContainer = array();
foreach ($entries as $item) {
	$itemView = $skin->keylogItem;
	dress('blog_rep_link', "$blogURL/{$item['id']}", $itemView);
	dress('blog_rep_title', htmlspecialchars($item['title'], ENT_QUOTES | ENT_SUBSTITUTE), $itemView);
	dress('blog_rep_regdate', Timestamp::format3($item['published']), $itemView);
	if ($item['comments'] > 0)
		dress('blog_rep_rp_cnt', "({$item['comments']})", $itemView);
	$itemsView .= $itemView;
}
dress('blog_rep', $itemsView, $keylogView);
$contentContainer["keyword_{$keylog['id']}"] = getEntryContentView($blogid, $keylog['id'], $keylog['content'], $keylog['contentformatter'], array(), 'Keyword');
dress('blog_desc', setTempTag("keyword_{$keylog['id']}"), $keylogView);
dress('blog_conform', htmlspecialchars($keylog['title'], ENT_QUOTES | ENT_SUBSTITUTE), $keylogView);
dress('blog', $keylogView, $out);
dress('blog_word', htmlspecialchars($keylog['title'], ENT_QUOTES | ENT_SUBSTITUTE), $out);
dress('body_id',"tt-body-keylog",$out);
$out = revertTempTags(removeAllTags($out));
fireEvent('OBStart');
print $out;
fireEvent('OBEnd');
?>
