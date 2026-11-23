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
$IV = array(
	'POST' => array(
		'page' => array ('int','min'=>1),
		'lines' => array ('int','min'=>1, 'default'=>15),
		'category' => array('string','mandatory'=>false),
		'keyword' => array('string','mandatory'=>false)	
	)
);
require ROOT . '/library/preprocessor.php';
requireStrictRoute();

$conditions = array();
$conditions['blogid'] = getBlogId();
$conditions['page'] = $_POST['page'];
if(isset($_POST['category'])) $conditions['category'] = $_POST['category'];
if(isset($_POST['keyword'])) $conditions['keyword'] = $_POST['keyword'];
$conditions['linesforpage'] = $_POST['lines'];

$skin = new Skin($context->getProperty('skin.skin'));
	
$isOwner = doesHaveOwnership();
$line = Model_Line::getInstance();
if ($isOwner && !isset($conditions['category'])) {
	$conditions['blogid'] = getBlogId();
} else if (!$isOwner) {
	$conditions['category'] = 'public';	// Textcube-NG: 방문자는 공개 라인만(/line 과 같은 규칙) — category=private POST 로 비공개 라인이 보이던 결함(v3.163)
}
$stream = $line->getWithConditions($conditions);

$contentView = '';

foreach ($stream as $item) {
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
			fireEvent('ViewLineAuthor', htmlspecialchars($item['author'], ENT_QUOTES | ENT_SUBSTITUTE)),
			fireEvent('ViewLineSource', htmlspecialchars($item['root'], ENT_QUOTES | ENT_SUBSTITUTE)),
			fireEvent('ViewLinePermalink', $item['permalink'])
		),
		$skin->lineItem
	);
	if ($isOwner) {
		$toggleLabel = ($item['category'] === 'public') ? _t('비공개로') : _t('공개로');
		$deleteLabel = _t('삭제');
		$adminDd = '<dd class="tc-line-admin" data-id="'.intval($item['id']).'" data-category="'.htmlspecialchars($item['category'], ENT_QUOTES | ENT_SUBSTITUTE).'">'
			.'<button class="tc-line-toggle" onclick="tcToggleLineCategory('.intval($item['id']).', this);return false;">'.htmlspecialchars($toggleLabel, ENT_QUOTES | ENT_SUBSTITUTE).'</button>'
			.' <button class="tc-line-delete" onclick="tcDeleteLine('.intval($item['id']).');return false;">'.htmlspecialchars($deleteLabel, ENT_QUOTES | ENT_SUBSTITUTE).'</button>'
			.'</dd>';
		$rendered = preg_replace('|</dl>|', $adminDd.'</dl>', $rendered, 1);
		$rendered = '<div class="tc-line-item" data-line-id="'.intval($item['id']).'">'.$rendered.'</div>';
	}
	$contentView .= $rendered;
}

if(empty($stream)) {
	$contentView = '';
	$buttonView = '';
} else {
	$buttonView = str_replace(
		array(
			'[##_line_onclick_more_##]'
		),
		array(
			'getMoreLineStream('.($conditions['page']+1).','.$conditions['linesforpage'].',\'bottom\');return false;'
		),
		$skin->lineButton
	);
}
$result = array('error'=>0,'contentView'=> $contentView,'buttonView'=>$buttonView);
Respond::PrintResult($result);
?>
