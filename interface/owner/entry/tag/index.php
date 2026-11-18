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
if(count($_POST) > 0) {
	$IV = array(
		'POST' => array(
			'deleteTag' => array('id', 'mandatory' => false),
			'modifyTag' => array('id', 'mandatory' => false),
			'newName' => array('string', 'mandatory' => false)
		)
	);
}

require ROOT . '/library/preprocessor.php';

requireModel('blog.tag');
requireModel('blog.entry');
$blogid = getBlogId();
/* Textcube-NG: 태그 삭제·이름 변경(POST)에 출처 확인 — panel/index.php 와 같이 requireStrictRoute()(CSRF, v3.135) */
if (count($_POST) > 0)
	requireStrictRoute();
if (!empty($_POST['deleteTag'])) {
	// Textcube-NG: 지우지 못하면 오류 응답(이름 변경과 같은 -1). 원본은 실패해도 0(r3-8)
	Respond::ResultPage(deleteTagById($blogid, $_POST['deleteTag']) ? 0 : -1);
	exit;
}

if (!empty($_POST['modifyTag']) && !empty($_POST['newName'])) {
	$newTagId = renameTag($blogid, $_POST['modifyTag'], $_POST['newName']);
	/* Textcube-NG: 이름을 바꾸지 못하면(새 태그를 만들지 못함) 오류 응답(v3.136). 원본은 실패해도 성공 응답을 보냈다 */
	if ($newTagId === false)
		Respond::ResultPage(-1);
	$newTagList = getTagListTemplate(getSiteTags($blogid));
	// Textcube-NG: 응답에는 저장된 이름(renameTag 가 정리·길이 상한을 적용한 이름, 이미 있던 태그로 합쳐지면 그 이름)을 쓴다. 원본은 입력값을 그대로 썼다(r3-8)
	$newTagName = (string)getTagById($blogid, $newTagId);
	$newEntryList = '<a href="'.$blogURL.'/owner/entry/?tagId='.$newTagId.'">'._f('"%1" 태그를 갖는 모든 글의 목록을 봅니다',htmlspecialchars($newTagName, ENT_QUOTES)).'</a>';
	$result = array('error'=>0, 'tagId' => $newTagId, 'tagName' => $newTagName, 'entryList' => $newEntryList, 'tagList' => $newTagList);	
	Respond::PrintResult($result);
	exit;
}

if (!empty($_POST['id']) && !empty($_POST['category'])) {
	$entries = array();
	foreach (getEntriesByTagId($blogid, $_POST['id']) as $entry) {
		$entries[] = $entry['id'];
	}
	changeCategoryOfEntries($blogid, implode(',', $entries), $_POST['category']);
}

$tags = getSiteTags($blogid);
if(isset($suri['value']) && !empty($suri['value']) ) {
	$tag = $suri['value'];
	$list = getEntryListWithPagingByTag($blogid, $tag, $suri['page'], 20);
} else {
	$list = null;	
}

function getTagListTemplate($tags) {
	$view = '';
	/* Textcube-NG: 태그 이름은 필자(writers)도 넣을 수 있으므로 HTML 로 쓸 때 escape(v3.135) */
	foreach($tags as $t):
		$view .= '<span id="tag'.$t['id'].'" class="tag"><a href="#" class="tag" onclick="updateTagPanel('.$t['id'].');return false;">'.htmlspecialchars($t['name'], ENT_QUOTES).'</a></span> ';
	endforeach;
	return $view;
}

require ROOT . '/interface/common/owner/header.php';
?>
						<script type="text/javascript">
							//<![CDATA[
							var tagId = 0;
							function updateTagPanel(id) {
								var request = new HTTPRequest("POST","<?php echo $blogURL;?>/owner/entry/tag/panel/");
								request.onSuccess = function () {
									PM.removeRequest(this);
									tagName = this.getText("/response/tagName");
									updatePanelText("tag-name",tagName);
									document.getElementById('tag-newname').value = tagName;
									numberOfPosts = this.getText("/response/numberOfPosts");
									updatePanel("tag-number-of-posts-value",numberOfPosts);
									tagEntryList = this.getText("/response/entryList");
									updatePanel("tag-entry-list",tagEntryList);
									tagId = id;
									PM.showMessage("<?php echo _t('태그 정보를 불러왔습니다.');?>", "center", "bottom");
								}
								request.onError = function () {
									PM.removeRequest(this);
									PM.showErrorMessage("<?php echo _t('태그 정보를 불러올 수 없었습니다.');?>","center","bottom");
								}
								PM.addRequest(request, "<?php echo _t('태그 정보를 불러오고 있습니다.');?>");
								request.send("tagId="+id);
							}
							
							function updatePanel(id,view) {
								Econtent = document.getElementById(id);
								Econtent.innerHTML = view;
								return true;							
							}
							
							// Textcube-NG: 태그 이름(서버 응답 tagName·입력란 값)은 HTML 이 아니라 글자로 넣는다(v3.135)
							function updatePanelText(id,text) {
								var element = document.getElementById(id);
								element.innerHTML = '';
								element.appendChild(document.createTextNode(text));
								return true;
							}
							
							function deleteTag() {
								if(tagId == 0) {
									alert('<?php echo _t('먼저 삭제할 태그를 선택하세요');?>');
									return false;
								}
								var request = new HTTPRequest("POST","<?php echo $blogURL;?>/owner/entry/tag/");
								request.onSuccess = function () {
									PM.removeRequest(this);
									updatePanel("tag-name",'<?php echo _t('선택되지 않았습니다.');?>');
									document.getElementById('tag-newname').value = '';
									updatePanel("tag-number-of-posts-value",0);
									updatePanel("tag-entry-list",
									'<?php echo _t('선택된 태그가 없습니다.');?>');
									document.getElementById('tag'+tagId).innerHTML = '';
									tagId = 0;
									PM.showMessage("<?php echo _t('태그를 삭제하였습니다.');?>", "center", "bottom");
								}
								request.onError = function () {
									PM.removeRequest(this);
									PM.showErrorMessage("<?php echo _t('태그를 삭제할 수 없었습니다.');?>","center","bottom");
								}
								PM.addRequest(request, "<?php echo _t('태그를 삭제하고 있습니다.');?>");
								request.send("deleteTag="+tagId);
							}
							function modifyTag() {
								if(tagId == 0) {
									alert('<?php echo _t('먼저 수정할 태그를 선택하세요');?>');
									return false;
								}
								var newName = document.getElementById('tag-newname').value;
								
								var request = new HTTPRequest("POST","<?php echo $blogURL;?>/owner/entry/tag/");
								request.onSuccess = function () {
									PM.removeRequest(this);
									var savedName = this.getText("/response/tagName");	// Textcube-NG: 서버가 저장한 이름(정리·길이 상한 뒤)을 보여준다(r3-8)
									updatePanelText("tag-name",savedName);
									document.getElementById('tag-newname').value = savedName;
									tagEntryList = this.getText("/response/entryList");
									updatePanel("tag-entry-list",tagEntryList);
									tagList = this.getText("/response/tagList");
									tagId = this.getText("/response/tagId");
									updatePanel("tag-cloud",tagList);
									PM.showMessage("<?php echo _t('태그를 수정하였습니다.');?>", "center", "bottom");
								}
								request.onError = function () {
									PM.removeRequest(this);
									PM.showErrorMessage("<?php echo _t('태그를 수정할 수 없었습니다.');?>","center","bottom");
								}
								PM.addRequest(request, "<?php echo _t('태그를 수정하고 있습니다.');?>");
								request.send("modifyTag="+tagId+"&newName="+encodeURIComponent(newName));
							}
							//]]>
						</script>
						<div id="part-post-tag" class="part">
							<h2 class="caption"><span class="main-text"><?php echo _t('태그를 관리합니다');?></span></h2>
						</div>

						<hr class="hidden" />

						<div id="tag-content-box">
							<div id="tag-cloud">
<?php
	echo getTagListTemplate($tags);
?>
							</div>
							<div id="tag-panel">
								<dl class="line">
									<dt><span class="label"><?php echo _t('현재 태그');?></span></dt>
									<dd id="tag-name"><?php echo _t('선택되지 않았습니다.');?></dd>

									<dd id="tag-number-of-posts"><?php echo _t('해당 태그가 포함된 글 수');?> :  <span id="tag-number-of-posts-value" class="text">0</span></dd>
								</dl>
								<dl class="line">
									<dt><?php echo _t('글목록');?></dt>
									<dd id="tag-entry-list">
										<span class="text"><?php echo _t('선택된 태그가 없습니다.');?></span>
									</dd>
								</dl>
								<dl class="line">
									<dt><span class="label"><?php echo _t('태그 이름 변경');?></span></dt>
									<dd>
										<input type="text" id="tag-newname" class="input-text" name="tag-newname" value="" />
										<input type="button" id="tagRename" class="input-button" name="tagRename" value="<?php echo _t('변경');?>" onclick="modifyTag();return false;" />
										<br />
										<label for="tag-newname"><?php echo _t('현재의 태그를 새로운 이름으로 변경합니다.').'<br />'._t('같은 태그를 가지고 있는 모든 글의 태그 이름이 함께 바뀌게 됩니다.').' '._t('입력한 이름의 태그가 이미 있는 경우, 하나의 태그로 합쳐지게 됩니다.');?></label>
									</dd>
								</dl>
								<dl class="line">
									<dt><span class="label"><?php echo _t('태그 삭제');?></span></dt>
									<dd>
										<input type="button" id="tagDelete" class="input-button" name="tagDelete" value="<?php echo _t('삭제');?>" onclick="deleteTag();return false;" />
										<br />
										<label for="tagName"><?php echo _t('이 태그를 삭제합니다. 같은 태그를 가지고 있는 모든 글에서 이 태그가 삭제됩니다.');?></label>
									</dd>
								</dl>
							</div>
							<hr class="clear" />
						</div>

						<hr class="hidden clear" />
						
<?php
require ROOT . '/interface/common/owner/footer.php';
?>
