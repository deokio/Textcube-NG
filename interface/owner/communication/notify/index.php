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
if (isset($_POST['page']))
	$_GET['page'] = $_POST['page'];
if (isset($_GET['category'])) $_POST['category'] = $_GET['category'];
if (isset($_GET['ip'])) $_POST['ip'] = $_GET['ip'];
if (isset($_GET['name'])) $_POST['name'] = $_GET['name'];
if (isset($_GET['perPage'])) $_POST['perPage'] = $_GET['perPage'];
if (isset($_GET['search'])) $_POST['search'] = $_GET['search'];
if (isset($_GET['withSearch'])) $_POST['withSearch'] = $_GET['withSearch'];

if(count($_POST) > 0) {
	$IV = array(
		'GET' => array(
			'name' => array('string', 'mandatory' => false),
			'ip' => array('ip', 'mandatory' => false),
			'page' => array('int', 1, 'default' => 1)
		),
		'POST' => array(
			'category' => array('int', 'default' => 0),
			'name' => array('string', 'mandatory' => false),
			'ip' => array('ip', 'mandatory' => false),
			'withSearch' => array(array('on'), 'mandatory' => false),
			'search' => array('string', 'default' => ''),
			'perPage' => array('int', 1, 'mandatory' => false)
		)
	);
}
require ROOT . '/library/preprocessor.php';
requireModel("blog.comment");
requireModel("blog.entry");

$categoryId = empty($_POST['category']) ? 0 : $_POST['category'];
$name = isset($_GET['name']) && !empty($_GET['name']) ? $_GET['name'] : '';
$name = isset($_POST['name']) && !empty($_POST['name']) ? $_POST['name'] : $name;
$ip = isset($_GET['ip']) && !empty($_GET['ip']) ? $_GET['ip'] : '';
$ip = isset($_POST['ip']) && !empty($_POST['ip']) ? $_POST['ip'] : $ip;
$search = empty($_POST['withSearch']) || empty($_POST['search']) ? '' : trim($_POST['search']);
$perPage = Setting::getBlogSettingGlobal('rowsPerPage', 10);
if (isset($_POST['perPage']) && is_numeric($_POST['perPage'])) {
	$perPage = $_POST['perPage'];
	setBlogSetting('rowsPerPage', $_POST['perPage']);
}

$tabsClass = array();
/* [보안] postfix 는 탭 링크(interface/common/owner/communicationTab.php)의 href 에 그대로 출력된다.
   원본은 검색어·이름 등 GET 값을 인코딩 없이 이어 붙였다 → 값마다 URL 인코딩 */
$tabsClass['postfix'] = null;
$tabsClass['postfix'] .= isset($_POST['category']) ? '&amp;category='.rawurlencode($_POST['category']) : '';
$tabsClass['postfix'] .= isset($_POST['name']) ? '&amp;name='.rawurlencode($_POST['name']) : '';
$tabsClass['postfix'] .= isset($_POST['ip']) ? '&amp;ip='.rawurlencode($_POST['ip']) : '';
$tabsClass['postfix'] .= isset($_POST['search']) ? '&amp;search='.rawurlencode($_POST['search']) : '';
if(!empty($tabsClass['postfix'])) $tabsClass['postfix'] = ltrim($tabsClass['postfix'],'/');

$tabsClass['notify'] = true;
$visibilityText = _t('댓글 알리미');

list($comments, $paging) = getCommentsNotifiedWithPagingForOwner($blogid, '', $name, '', $search, $suri['page'], $perPage);
require ROOT . '/interface/common/owner/header.php';

?>
						<script type="text/javascript">
							//<![CDATA[
							(function($) {
								deleteComment = function(id) {
									tcConfirm("<?php echo _t('선택된 댓글을 삭제합니다. 계속 하시겠습니까?');?>", function () {	// Textcube-NG: 확인창을 페이지 위 안내 줄의 [확인]/[취소]로 바꿈(r3-17, R-396)
									var request = new HTTPRequest("POST", "<?php echo $blogURL;?>/owner/communication/notify/delete/" + id);
									request.onSuccess = function () {
										document.getElementById('list-form').submit();
									}
									request.send();
									});
								}

								deleteComments = function() {
									tcConfirm("<?php echo _t('선택된 댓글을 삭제합니다. 계속 하시겠습니까?');?>", function () {	// Textcube-NG: 확인창을 페이지 위 안내 줄의 [확인]/[취소]로 바꿈(r3-17, R-396)
									var oElement;
									var targets = new Array();
									for (i = 0; document.getElementById('list-form').elements[i]; i ++) {
										oElement = document.getElementById('list-form').elements[i];
										if ((oElement.name == "entry") && oElement.checked)
											targets[targets.length] = oElement.value;
									}
									var request = new HTTPRequest("POST", "<?php echo $blogURL;?>/owner/communication/notify/delete");
									request.onSuccess = function() {
										document.getElementById('list-form').submit();
									}
									request.send("targets=" + targets.join(','));
									});
									return false;
								}

								changeState = function(caller, value, mode) {
									try {
										if (caller.className == 'block-icon bullet') {
											var command 	= 'unblock';
										} else {
											var command 	= 'block';
										}
										var name 		= caller.id.replace(/\-[0-9]+$/, '');
										param  	=  'value='	+ encodeURIComponent(value);
										param 	+= '&mode=' 	+ mode;
										param 	+= '&command=' 	+ command;

										var request = new HTTPRequest("POST", "<?php echo $blogURL;?>/owner/communication/filter/change/");
										var iconList = document.getElementsByTagName("a");
										for (var i = 0; i < iconList.length; i++) {
											icon = iconList[i];
											if(icon.id == null || icon.id.replace(/\-[0-9]+$/, '') != name) {
												continue;
											} else {
												if (command == 'block') {
													icon.className = 'block-icon bullet';
													icon.innerHTML = '<span class="text"><?php echo _t('[차단됨]');?><\/span>';
													icon.setAttribute('title', "<?php echo _t('이 이름은 차단되었습니다. 클릭하시면 차단을 해제합니다.');?>");
												} else {
													icon.className = 'unblock-icon bullet';
													icon.innerHTML = '<span class="text"><?php echo _t('[허용됨]');?><\/span>';
													icon.setAttribute('title', "<?php echo _t('이 이름은 차단되지 않았습니다. 클릭하시면 차단합니다.');?>");
												}
											}
										}
										request.send(param);
									} catch(e) {
										alert(e.message);
									}
								}

								function toggleThisTr(tr, isActive) {
									if (isActive) {
										$(tr).removeClass('inactive-class').addClass('active-class');
									} else {
										$(tr).removeClass('active-class').addClass('inactive-class');
									}
								}

								$(document).ready(function() {
									$('#allChecked').removeAttr('disabled');
									// Textcube-NG: 방문 시각 기록(GET 화면에서 옮김) — POST, CSRF 토큰은 HTTPRequest 가 X-CSRF-Token 헤더로 붙임(r3-8)
									var visitRequest = new HTTPRequest("POST", "<?php echo $blogURL;?>/owner/communication/notify/visit/");
									visitRequest.persistent = false;
									visitRequest.send();
									$('#list-form tbody td.selection').click(function(ev) {
										$('#allChecked').prop('checked', false);
										var checked = $(':checked', this).prop('checked');
										$(':checked', this).prop('checked', checked ? true : false);
										toggleThisTr($(this).parent(), checked);
										ev.stopPropagation();
									});
									$('#allChecked').click(function(ev) {
										var checked = $(this).prop('checked');
										$('#list-form tbody td.selection input:checkbox').each(function(index, item) {
											$(item).prop('checked', checked ? true : false);
											toggleThisTr($(item).parent().parent(), checked);
										});
									});
								});
							})(jQuery);
							//]]>
						</script>

						<div id="part-post-notify" class="part">
							<h2 class="caption">
								<span class="main-text"><?php echo _t('댓글 알리미입니다');?></span>
<?php
if (strlen($name) > 0 || strlen($ip) > 0) {
	if (strlen($name) > 0) {
?>
								<span class="filter-condition"><?php echo htmlspecialchars($name, ENT_QUOTES | ENT_SUBSTITUTE);?></span>
<?php
	}

	if (strlen($ip) > 0) {
?>
								<span class="filter-condition"><?php echo htmlspecialchars($ip, ENT_QUOTES | ENT_SUBSTITUTE);?></span>
<?php
	}
}
?>
							</h2>
<?php
require ROOT . '/interface/common/owner/communicationTab.php';
?>
							<div class="main-explain-box">
								<p class="explain"><?php echo _t('다른 사람의 블로그에 단 댓글에 대한 댓글이 등록되면 알려줍니다. 알리미가 동작하기 위해서는 댓글 작성시 홈페이지 기입란에 자신의 블로그 주소를 입력하셔야 합니다.');?></p>
							</div>

							<form id="list-form" method="post" action="<?php echo $blogURL;?>/owner/communication/notify">
<?php
	if(isset($_POST['search'])) echo '								<input type="hidden" name="search" value="'.htmlspecialchars($_POST['search'], ENT_QUOTES).'" />'.CRLF;
	if(isset($_POST['withSearch'])) echo '								<input type="hidden" name="withSearch" value="'.htmlspecialchars($_POST['withSearch'], ENT_QUOTES).'" />'.CRLF;
?>
								<table class="data-inbox" cellspacing="0" cellpadding="0">
									<thead>
										<tr>
											<th class="selection">
												<input type="checkbox" id="allChecked" class="checkbox" disabled="disabled" />
												<label for="allChecked"></label>
											</th>
											<th class="date"><span class="text"><?php echo _t('등록일자');?></span></th>
											<th class="site"><span class="text"><?php echo _t('사이트명');?></span></th>
											<th class="name"><span class="text"><?php echo _t('이름');?></span></th>
											<th class="content"><span class="text"><?php echo _t('내용');?></span></th>
											<th class="delete"><span class="text"><?php echo _t('삭제');?></span></th>
										</tr>
									</thead>
									<tbody>
<?php
$more = false;
$mergedComments = array();
$lastVisitNotifiedPage = Setting::getBlogSettingGlobal('lastVisitNotifiedPage', null);
// Textcube-NG: GET 화면에서는 방문 시각을 기록하지 않음 — 화면이 뜬 뒤 POST notify/visit 이 기록(r3-8)
for ($i = 0; $i < count($comments); $i++) {
	array_push($mergedComments, $comments[$i]);
	$result = getCommentCommentsNotified($comments[$i]['id']);
	for ($j = 0; $j < count($result); $j++) {
		array_push($mergedComments, $result[$j]);
	}
}

// Textcube-NG: 원격 알림의 링크(siteUrl·entryurl·parentUrl·url)는 http·https 일 때만 escape 해서 걸고, 그 밖(javascript: 등)은 '#'(r3-15, R-264)
$notifyHref = function ($url) { $url = (string)$url; return preg_match('#^https?://#i', $url) ? htmlspecialchars($url, ENT_QUOTES | ENT_SUBSTITUTE) : '#'; };
$nameNumber = array();
for ($i=0; $i<sizeof($mergedComments); $i++) {
	$comment = $mergedComments[$i];

	if (Filter::isFiltered('name', $comment['name']))
		$isNameFiltered = true;
	else
		$isNameFiltered = false;

	if (!isset($nameNumber[$comment['name']])) {
		$nameNumber[$comment['name']] = $i;
		$currentNumber = $i;
	} else {
		$currentNumber = $nameNumber[$comment['name']];
	}

	$className = ($i % 2) == 1 ? 'even-line' : 'odd-line';
	$className .= $comment['parent'] ? ' reply-line' : null;
	$className .= ($i == sizeof($mergedComments) - 1) ? ' last-line' : '';
?>
										<tr class="<?php echo $className;?> inactive-class" onmouseover="rolloverClass(this, 'over')" onmouseout="rolloverClass(this, 'out')">
											<td class="selection">
												<input id="commentCheckId<?php echo $comment['id'];?>" type="checkbox" class="checkbox" name="entry" value="<?php echo $comment['id'];?>" />
												<label for="commentCheckId<?php echo $comment['id'];?>"></label>
											</td>
											<td class="date"><?php echo Timestamp::formatDate($comment['written']);?></td>
											<td class="site"><a href="<?php echo $notifyHref($comment['siteUrl']);?>" onclick="window.open(this.href); return false;" title="<?php echo _t('사이트를 연결합니다.');?>"><?php echo htmlspecialchars($comment['siteTitle'], ENT_QUOTES | ENT_SUBSTITUTE);?></a></td>
											<td class="name">
<?php
	if ($isNameFiltered) {
?>
												<a id="nameFilter<?php echo $currentNumber;?>-<?php echo $i;?>" class="block-icon bullet" href="<?php echo $blogURL;?>/owner/communication/filter/change/?value=<?php echo urlencode(escapeJSInAttribute($comment['name']));?>&amp;mode=name&amp;command=unblock" onclick="changeState(this,'<?php echo escapeJSInAttribute($comment['name']);?>', 'name'); return false;" title="<?php echo _t('이 이름은 차단되었습니다. 클릭하시면 차단을 해제합니다.');?>"><span class="text"><?php echo _t('[차단됨]');?></span></a>
<?php
	} else {
?>
												<a id="nameFilter<?php echo $currentNumber;?>-<?php echo $i;?>" class="unblock-icon bullet" href="<?php echo $blogURL;?>/owner/communication/filter/change/?value=<?php echo urlencode(escapeJSInAttribute($comment['name']));?>&amp;mode=name&amp;command=block" onclick="changeState(this,'<?php echo escapeJSInAttribute($comment['name']);?>', 'name'); return false;" title="<?php echo _t('이 이름은 차단되지 않았습니다. 클릭하시면 차단합니다.');?>"><span class="text"><?php echo _t('[허용됨]');?></span></a>
<?php
	}
?>
												<a href="?name=<?php echo urlencode(escapeJSInAttribute($comment['name']));?>" title="<?php echo _t('이 이름으로 등록된 댓글 목록을 보여줍니다.');?>"><?php echo htmlspecialchars($comment['name'], ENT_QUOTES | ENT_SUBSTITUTE);?></a>
											</td>
											<td class="content">
<?php
	if ($comment['parent']) {
		if ($lastVisitNotifiedPage > time() - 86400) {
?>
												<span class="new-icon bullet" title="<?php echo _t('새로 등록된 댓글입니다.');?>"><span class="text">[<?php echo _t('새 댓글');?>]</span></span>
<?php
		}
	} else {
		echo '<a class="entryURL" href="'.$notifyHref($comment['entryurl']).'" onclick="window.open(this.href); return false;" title="'._t('댓글이 작성된 포스트로 직접 이동합니다.').'">';
		echo '<span class="entry-title">'. htmlspecialchars($comment['entrytitle'], ENT_QUOTES | ENT_SUBSTITUTE) .'</span>';

		if ($comment['entrytitle'] != '' && $comment['parent'] != '') {
			echo '<span class="divider"> | </span>';
		}

		echo empty($comment['parent']) ? '' : "<a href=\"" . $notifyHref($comment['parentUrl']) . "\" onclick=\"window.open(this.href); return false;\">" . _f('%1 님의 댓글에 대한 댓글',htmlspecialchars((string)$comment['parentName'], ENT_QUOTES | ENT_SUBSTITUTE)) . "</a>";
		echo "</a>";
		echo !empty($comment['title']) || !empty($comment['parent']) ? '<br />' : '';
	}
?>
												<a class="commentURL" href="<?php echo $notifyHref($comment['url']);?>" onclick="window.open(this.href); return false;" title="<?php echo _t('댓글이 작성된 위치로 직접 이동합니다.');?>"><?php echo htmlspecialchars($comment['comment'], ENT_QUOTES | ENT_SUBSTITUTE);?></a>
											</td>
											<td class="delete">
												<a class="delete-button button" href="<?php echo $blogURL;?>/owner/communication/notify/delete/<?php echo $comment['id'];?>" onclick="deleteComment(<?php echo $comment['id'];?>); return false;" title="<?php echo _t('이 댓글을 삭제합니다.');?>"><span class="text"><?php echo _t('삭제');?></span></a>
											</td>
										</tr>
<?php
}
?>
									</tbody>
								</table>

								<hr class="hidden" />

								<div class="data-subbox">
									<input type="hidden" name="page" value="<?php echo htmlspecialchars((string)$suri['page'], ENT_QUOTES);?>" />
									<input type="hidden" name="name" value="" />

									<div id="delete-section" class="section">
										<span class="label"><?php echo _t('선택한 알림을');?></span>
										<input type="button" class="delete-button input-button" value="<?php echo _t('삭제');?>" onclick="deleteComments();" />
									</div>
									<div id="page-section" class="section">
										<div id="page-navigation">
											<span id="page-list">
<?php
//$paging['url'] = 'document.getElementById('list-form').page.value=';
//$paging['prefix'] = '';
//$paging['postfix'] = '; document.getElementById('list-form').submit()';
$pagingTemplate = '[##_paging_rep_##]';
$pagingItemTemplate = '<a [##_paging_rep_link_##]>[##_paging_rep_link_num_##]</a>';
print Paging::getPagingView($paging, $pagingTemplate, $pagingItemTemplate);
?>
											</span>
											<span id="total-count"><?php echo _f('총 %1건', empty($paging['total']) ? "0" : $paging['total']);?></span>
										</div>
										<div class="page-count">
											<?php echo getArrayValue(explode('%1', _t('한 페이지에 글 %1건 표시')), 0);?>

											<select name="perPage" onchange="document.getElementById('list-form').page.value=1; document.getElementById('list-form').submit()">
	<?php
	for ($i = 10; $i <= 30; $i += 5) {
	if ($i == $perPage) {
	?>
												<option value="<?php echo $i;?>" selected="selected"><?php echo $i;?></option>
	<?php
	} else {
	?>
												<option value="<?php echo $i;?>"><?php echo $i;?></option>
	<?php
	}
	}
	?>
											</select>
											<?php echo getArrayValue(explode('%1', _t('한 페이지에 글 %1건 표시')), 1).CRLF;?>
										</div>
									</div>
								</div>
							</form>

							<hr class="hidden" />

							<form id="search-form" class="data-subbox" method="post" action="<?php echo $blogURL;?>/owner/communication/notify">
								<h2><?php echo _t('검색');?></h2>

								<div class="section">
									<label for="search"><?php echo _t('제목');?>, <?php echo _t('사이트명');?>, <?php echo _t('내용');?></label>
									<input type="text" id="search" class="input-text" name="search" value="<?php echo htmlspecialchars($search, ENT_QUOTES | ENT_SUBSTITUTE);?>" onkeydown="if (event.keyCode == '13') { document.getElementById('search-form').withSearch.value = 'on'; document.getElementById('search-form').submit(); }" />
									<input type="hidden" name="withSearch" value="" />
									<input type="submit" class="search-button input-button" value="<?php echo _t('검색');?>" onclick="document.getElementById('search-form').withSearch.value = 'on'; document.getElementById('search-form').submit();" />
								</div>
							</form>
						</div>
<?php
require ROOT . '/interface/common/owner/footer.php';
?>
