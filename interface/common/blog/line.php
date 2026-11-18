<?php
/// Copyright (c) 2004-2016, Needlworks  / Tatter Network Foundation
/// All rights reserved. Licensed under the GPL.
/// See the GNU General Public License for more details. (/documents/LICENSE, /documents/COPYRIGHT)


if (!doesHaveOwnership() && isset($cache->contents)) {
	dress('line', $cache->contents, $view);
} else if (isset($lines) && isset($skin)) {
	global $blogURL;
	$lineView = $skin->line;
	$itemsView = '';
	$printDate = '';
	$isOwner = doesHaveOwnership();
	foreach ($lines as $item) {
		$time = Timestamp::getHumanReadable($item['created']);
		if($item['root'] == 'default') $item['root'] = 'Textcube Line';
		$rendered = str_replace(
			array(
				'[##_line_rep_regdate_##]',
				'[##_line_rep_content_##]',
				'[##_line_rep_author_##]',
				'[##_line_rep_source_##]',
				'[##_line_rep_permalink_##]'
			),
			array(
				fireEvent('ViewLineDate', $time, $item['created']),
				fireEvent('ViewLineContent', $item['content']),
				fireEvent('ViewLineAuthor', htmlspecialchars($item['author'])),
				fireEvent('ViewLineSource', htmlspecialchars($item['root'])),
				fireEvent('ViewLinePermalink', $item['permalink'])
			),
			$skin->lineItem
		);
		if ($isOwner) {
			$toggleLabel = ($item['category'] === 'public') ? _t('비공개로') : _t('공개로');
			$deleteLabel = _t('삭제');
			$adminDd = '<dd class="tc-line-admin" data-id="'.intval($item['id']).'" data-category="'.htmlspecialchars($item['category']).'">'
				.'<button class="tc-line-toggle" onclick="tcToggleLineCategory('.intval($item['id']).', this);return false;">'.htmlspecialchars($toggleLabel).'</button>'
				.' <button class="tc-line-delete" onclick="tcDeleteLine('.intval($item['id']).');return false;">'.htmlspecialchars($deleteLabel).'</button>'
				.'</dd>';
			$rendered = preg_replace('|</dl>|', $adminDd.'</dl>', $rendered, 1);
			$rendered = '<div class="tc-line-item" data-line-id="'.intval($item['id']).'">'.$rendered.'</div>';
		}
		$itemsView .= $rendered;
	}
	$itemsView = '<div id="line-content">'.CRLF.$itemsView.CRLF.'</div>';
	dress('line_rep', $itemsView, $lineView);
	$buttonView = str_replace(
		array(
			'[##_line_onclick_more_##]'
		),
		array(
			'getMoreLineStream(2,20,\'bottom\');return false;'
		),
		$skin->lineButton
	);
	$buttonView = '<div id="line-more-page">'.CRLF.$buttonView.CRLF.'</div>';
	dress('line_button', $buttonView, $lineView);
	$lineView = fireEvent('ViewLine', $lineView, $lines);
	dress('line_rssurl',$defaultURL.'/rss/line',$lineView);
	dress('line_atomurl',$defaultURL.'/atom/line',$lineView);

//	if(empty($lines)) $lineView = $lineView.CRLF.'[##_paging_line_##]';
	
	if ($isOwner) {
		$lineView .= '<script type="text/javascript">'.CRLF
			.'//<![CDATA['.CRLF
			.'function tcToggleLineCategory(id, btn) {'.CRLF
			.'  var ctrl = btn.parentNode;'.CRLF
			.'  var currentCat = ctrl.getAttribute("data-category");'.CRLF
			.'  var newCat = (currentCat === "public") ? "private" : "public";'.CRLF
			.'  var xhr = new XMLHttpRequest();'.CRLF
			.'  xhr.open("POST", "'.addslashes($blogURL).'/owner/entry/line/updateCategory/");'.CRLF
			.'  xhr.setRequestHeader("Content-Type", "application/x-www-form-urlencoded");'.CRLF
			.'  xhr.onload = function() {'.CRLF
			.'    if (xhr.status === 200) {'.CRLF
			.'      ctrl.setAttribute("data-category", newCat);'.CRLF
			.'      btn.textContent = (newCat === "public") ? "'.addslashes(_t('비공개로')).'" : "'.addslashes(_t('공개로')).'";'.CRLF
			.'    } else { alert("'.addslashes(_t('공개 설정을 변경할 수 없었습니다.')).'"); }'.CRLF
			.'  };'.CRLF
			.'  xhr.onerror = function() { alert("'.addslashes(_t('공개 설정을 변경할 수 없었습니다.')).'"); };'.CRLF
			.'  xhr.send("id=" + id + "&category=" + newCat);'.CRLF
			.'}'.CRLF
			.'function tcDeleteLine(id) {'.CRLF
			.'  tcConfirm("'.addslashes(_t('삭제하시겠습니까?')).'", function () {'.CRLF	// Textcube-NG: 확인창을 페이지 위 안내 줄의 [확인]/[취소]로 바꿈(r3-17, R-396)
			.'  var xhr = new XMLHttpRequest();'.CRLF
			.'  xhr.open("POST", "'.addslashes($blogURL).'/owner/entry/line/delete/");'.CRLF
			.'  xhr.setRequestHeader("Content-Type", "application/x-www-form-urlencoded");'.CRLF
			.'  xhr.onload = function() {'.CRLF
			.'    if (xhr.status === 200) {'.CRLF
			.'      var wrapper = document.querySelector(".tc-line-item[data-line-id=\"" + id + "\"]");'.CRLF
			.'      if (wrapper && wrapper.parentNode) wrapper.parentNode.removeChild(wrapper);'.CRLF
			.'    } else { alert("'.addslashes(_t('삭제할 수 없었습니다.')).'"); }'.CRLF
			.'  };'.CRLF
			.'  xhr.onerror = function() { alert("'.addslashes(_t('삭제할 수 없었습니다.')).'"); };'.CRLF
			.'  xhr.send("id=" + id);'.CRLF
			.'  });'.CRLF
			.'}'.CRLF
			.'//]]>'.CRLF
			.'</script>';
	}
	dress('line', $lineView, $view);
	
	if(!$isOwner && isset($cache)) {
		$cache->contents = $lineView;
		$cache->dbContents = $paging;
		$cache->update();
	}
}
?>
