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
if(count($_POST) > 0) {
	$IV = array(
		'POST' => array(
			'deleteCategory' => array('id', 'mandatory' => false),
			'direction' => array(array('up', 'down'), 'mandatory' => false),
			'id' => array('int', 'mandatory' => false),
			'newCategory' => array('string', 'mandatory' => false),
			'modifyCategoryName' => array('string', 'mandatory' => false),
			'modifyCategoryBodyId' => array('string', 'default' => 'tt-body-category'),
			'visibility' => array('int', 'mandatory' => false),
			'newParent' => array('int', 0, 'mandatory' => false),	// Textcube-NG: R-592 — 상위 분류 바꾸기(0 = 최상위)
			'maxDepth' => array('int', 2, 10, 'mandatory' => false)	// Textcube-NG: R-592 — 분류 깊이 설정
		)
	);
}
require ROOT . '/library/preprocessor.php';
requireModel('blog.category');
requireModel('blog.entry');
/* [보안] $IV 는 POST 요청에만 정의돼 GET id 가 검증 없이 SQL(getNumberEntryInCategories 등)과
   인라인 스크립트(oform.id.value=...)에 그대로 쓰였다 → 정수로 변환 */
if (!empty($_POST['id']))
	$selected = (int)$_POST['id'];
else if (empty($_GET['id']))
	$selected = 0;
else
	$selected = (int)$_GET['id'];

if (!empty($_POST['visibility'])) {
	$setVisibility = $_POST['visibility'];
	$visibility = setCategoryVisibility($blogid,$selected,$setVisibility);
} else {
	$visibility = getCategoryVisibility($blogid, $selected);
}

if (!empty($_POST['deleteCategory'])) {
	$parent = getParentCategoryId($blogid, $_POST['deleteCategory']);
	$selected = (is_null($parent)) ? 0 : $parent;
	$_POST['modifyCategoryName'] = '';
	$_POST['modifyCategoryBodyId'] = '';
	if (!deleteCategory($blogid, $_POST['deleteCategory']))	// Textcube-NG: R-592 — 하위 분류가 있으면 서버도 거부(사용자 결정 ⑧)
		$errorMessage = getCategoryErrorMessage($blogid);
}

if (!empty($_POST['direction']))
	moveCategory($blogid, $selected, $_POST['direction']);

// Textcube-NG: R-592 — 상위 분류 바꾸기(사용자 결정 ⑥)·분류 깊이 설정(①). 깊이($depth)는 아래에서 분류 캐시로 센다(원본은 GET name1·name2 로 1·2단만 정했다)
if (isset($_POST['newParent']) && $_POST['newParent'] !== '' && $selected > 0) {
	if (!setCategoryParent($blogid, $selected, (int)$_POST['newParent']))
		$errorMessage = getCategoryErrorMessage($blogid);
}
if (isset($_POST['maxDepth']) && $_POST['maxDepth'] !== '') {
	$newMaxDepth = (int)$_POST['maxDepth'];
	if ($newMaxDepth >= 2 && $newMaxDepth <= 10) {
		if ($newMaxDepth < getCategoryTreeMaxDepth($blogid))
			$errorMessage = _f('지금 분류가 %1단까지 있어 줄일 수 없습니다', getCategoryTreeMaxDepth($blogid));
		else
			Setting::setBlogSettingGlobal('categoryMaxDepth', $newMaxDepth);
	}
}

if (empty($_GET['entries']) || $_GET['entries'] == 0)
	$entries = 0;
else
	$entries = $_GET['entries'];

if ((!empty($_POST['newCategory']) && Misc::isSpace($_POST['newCategory'])) ||
			(!empty($_POST['modifyCategoryName']) && Misc::isSpace($_POST['modifyCategoryName']))) {
	$history = '';
	$errorMessage = _t('공백문자는 카테고리 이름으로 사용할 수 없습니다');
} elseif ((!empty($_POST['newCategory']) && strpos($_POST['newCategory'], '/') !== false) || (!empty($_POST['modifyCategoryName']) && strpos($_POST['modifyCategoryName'], '/') !== false)) {
	$history = '';
	$errorMessage = _t('슬래시가 들어간 카테고리 이름은 사용할 수 없습니다');
/*} elseif ((!empty($_POST['newCategory']) && strpos($_POST['newCategory'], '&') !== false) || (!empty($_POST['modifyCategoryName']) && strpos($_POST['modifyCategoryName'], '&') !== false)) {
	$history = '';
	$errorMessage = _t('앰퍼샌드(&)가 들어간 카테고리 이름은 사용할 수 없습니다');*/
} elseif ((!empty($_POST['newCategory']) && strpos($_POST['newCategory'], '?') !== false) || (!empty($_POST['modifyCategoryName']) && strpos($_POST['modifyCategoryName'], '?') !== false)) {
	$history = '';
	$errorMessage = _t('물음표가 들어간 카테고리 이름은 사용할 수 없습니다');
} elseif (!empty($_POST['newCategory'])) {
	$history = addCategory($blogid, ($selected == 0) ? null : $_POST['id'], trim($_POST['newCategory'])) ? 'document.getElementById("newCategory").select();' : '';
	if(empty($history)) $errorMessage = getCategoryErrorMessage($blogid);	// Textcube-NG: R-592 — 깊이 한도·경로 길이·같은 이름 가운데 거부한 까닭
} else if (!empty($_POST['modifyCategoryName']) || !empty($_POST['modifyCategoryBodyId'])) {
	$history = modifyCategory($blogid, $_POST['id'], trim($_POST['modifyCategoryName']),trim($_POST['modifyCategoryBodyId'])) ? 'document.getElementById("modifyCategoryName").select();' : '';
	// Textcube-NG: r4-16 — 이름은 저장하고 body id 만 거부했을 때(성공으로 돌아옴)도 안내한다(사용자 결정 11-24 00:4x)
	if (getCategoryErrorMessage($blogid, '') !== '')	// Textcube-NG: R-592 — 경로가 255자를 넘어 이름을 바꾸지 않았을 때. 원본의 MySQL 전용(역따옴표) 깊이 판정 질의는 지움(깊이는 아래에서 셈)
		$errorMessage = getCategoryErrorMessage($blogid, '');
} else {
	$history = '';
}
$depth = getCategoryDepth($blogid, $selected);	// Textcube-NG: R-592 — 0(전체)·1(최상위)·2·… 바꾼 뒤의 분류 캐시로 센다
$categoryMaxDepth = getCategoryMaxDepth($blogid);
$categories = getCategories($blogid);
$name = getCategoryNameById($blogid, $selected) ? getCategoryNameById($blogid, $selected) : _t('전체');
$bodyid = getCategoryBodyIdById($blogid, $selected);
if ((empty($_POST['search'])) || ($searchColumn === true)) {
	$searchParam = true;
} else {
	$searchParam[0] = $_POST['searchColumn'];
	$searchParam[1] = $_POST['search'];
}
require ROOT . '/interface/common/owner/header.php';

?>
						<script type="text/javascript">
							//<![CDATA[
								function removeCategory() {
									tcConfirm('<?php echo _t('삭제 하시겠습니까?');?>', function () {	// Textcube-NG: 확인창을 페이지 위 안내 줄의 [확인]/[취소]로 바꿈(r3-17, R-396)
										var oform=document.forms[0];
										oform.deleteCategory.value=<?php echo $selected;?>;

										oform.submit()
									});
								}

								function moveCategory(direction) {
									var oform=document.forms[0];
									oform.direction.value=direction
									oform.id.value=<?php echo $selected;?>;
									oform.submit()
								}

								function addCategory() {
									var oform=document.forms[0];
									oform.id.value=<?php echo $selected;?>;
									oform.submit()
								}

								function modifyCategory() {
									var oform=document.forms[0];
									oform.id.value=<?php echo $selected;?>;
									oform.submit()
								}

								function changeCategoryVisibility() {
									var oform=document.forms[0];
									if (document.getElementById('currentVisibility').checked) {
										oform.visibility.value = 1;
									} else {
										oform.visibility.value = 2;
									}
									oform.id.value=<?php echo $selected;?>;
									oform.submit()
								}

								// Textcube-NG: R-592 — 상위 분류 바꾸기·분류 깊이 저장. 값은 이 단추를 누를 때만 hidden 입력으로 더해 보낸다(다른 동작에는 보내지 않음)
								function changeParent() {
									var oform=document.forms[0];
									var input=document.createElement('input');
									input.type='hidden';
									input.name='newParent';
									input.value=document.getElementById('parentSelect').value;
									oform.appendChild(input);
									oform.id.value=<?php echo $selected;?>;
									oform.submit()
								}

								function changeMaxDepth() {
									var oform=document.forms[0];
									var input=document.createElement('input');
									input.type='hidden';
									input.name='maxDepth';
									input.value=document.getElementById('maxDepthSelect').value;
									oform.appendChild(input);
									oform.id.value=<?php echo $selected;?>;
									oform.submit()
								}
								window.addEventListener("load", expandTreeInit, false);
								function expandTreeInit() {
									try {
										<?php echo $history;?>
										expandTree();
									} catch(e) {
										alert(e.message);
									}
								}

								function validateText(str) {
									return true;
								}
<?php
	if(isset($errorMessage)) {
?>
								alert('<?php echo $errorMessage;?>');
<?php
	}
?>
							//]]>
						</script>

						<div id="part-post-tree" class="part">
							<h2 class="caption"><span class="main-text"><?php echo _t('분류를 관리합니다');?></span></h2>

							<div class="data-inbox">
								<div id="tree-preview-box">
									<div class="title"><?php echo _t('미리보기');?></div>
									<div id="treePreview">
<?php echo getCategoriesViewInOwner(getEntriesTotalCount(getBlogId()), $categories, $selected);?>
									</div>
								</div>

								<form class="section" method="post" action="<?php echo $blogURL;?>/owner/entry/category">
									<fieldset id="property-box" class="container">
										<legend><?php echo _t('분류 관리 및 설정');?></legend>

										<input type="hidden" name="page" value="<?php echo htmlspecialchars((string)$suri['page'], ENT_QUOTES);?>" />
										<input type="hidden" name="deleteCategory" />
										<input type="hidden" name="direction" />
										<input type="hidden" name="id" />
										<input type="hidden" name="visibility" />

										<dl id="label-create-line" class="line">
											<dt><label for="newCategory"><?php echo _t('만들기');?></label></dt>
<?php
if ($depth < $categoryMaxDepth) {	// Textcube-NG: R-592 — 깊이 한도(블로그 설정, 기본 3)까지 하위 분류를 만든다
?>
											<dd>
												<div class="field-box">
													<input type="text" id="newCategory" class="input-text" name="newCategory" onkeyup="if (event.keyCode == 13 &amp;&amp; validateText(this.value)) {addCategory()}" />
													<input type="button" class="add-button input-button" value="<?php echo _t('추가하기');?>" onclick="addCategory(); return false;" />
												</div>
												<p>
													<?php echo _f('"%1"의 하위에 새 분류를 만듭니다.', htmlspecialchars("$name", ENT_QUOTES | ENT_SUBSTITUTE));?>
												</p>
											</dd>
<?php
} else {
?>
											<dd><p><?php echo _f('분류는 %1단까지 허용됩니다.', $categoryMaxDepth);?></p></dd>
<?php
}
?>

										</dl>
										<dl id="label-change-line" class="line">
											<dt><label for="modifyCategoryName"><?php echo _t('이름 변경');?></label></dt>
											<dd>
												<div class="field-box">
													<input type="text" id="modifyCategoryName" class="input-text" name="modifyCategoryName" onkeyup="if (event.keyCode == '13' &amp;&amp; validateText(this.value)) modifyCategory();" value="<?php echo htmlspecialchars($name, ENT_QUOTES | ENT_SUBSTITUTE);?>" />
													<input type="button" class="save-button input-button" value="<?php echo _t('저장하기');?>" onclick="modifyCategory(); return false;" />
												</div>
											</dd>
										</dl>
										<dl id="body-id-line" class="line">
											<dt><label for="modifyCategoryBodyId"><?php echo _t('Body Id 변경');?></label></dt>
											<dd>
												<div class="field-box">
													<input type="text" id="modifyCategoryBodyId" class="input-text" name="modifyCategoryBodyId" onkeyup="if (event.keyCode == '13' &amp;&amp; validateText(this.value)) modifyCategory();" value="<?php echo htmlspecialchars($bodyid, ENT_QUOTES | ENT_SUBSTITUTE);?>" <?php if ($selected == 0) echo 'readonly="readonly"';?> />
													<input type="button" class="save-button input-button" value="<?php echo _t('저장하기');?>" onclick="modifyCategory(); return false;" />
												</div>
												<p><?php echo _t('Body id는 블로그 스킨의 <acronym title="Cascading Style Sheet">CSS</acronym> 활용을 위해 사용합니다.<br /> 기본값인 "tt-body-category"를 그냥 사용하셔도 사용에 지장은 없습니다.');?></p>
											</dd>
										</dl>
										<dl id="label-parent-line" class="line">
											<dt><label for="parentSelect"><?php echo _t('상위 분류');?></label></dt>
<?php
// Textcube-NG: R-592 — 상위 분류 바꾸기(사용자 결정 ⑥). 자기와 자손은 목록에서 빼고, 옮기면 깊이 한도를 넘는 분류는 고를 수 없게 보인다(최종 검사는 서버 setCategoryParent())
if ($selected > 0) {
	$parentExcluded = array_merge(array($selected), getCategoryDescendantIds($blogid, $selected));
	$parentCurrent = getParentCategoryId($blogid, $selected);
	$parentSubtreeHeight = getCategorySubtreeHeight($blogid, $selected);
?>
											<dd>
												<div class="field-box">
													<select id="parentSelect">
														<option value="0"<?php echo (empty($parentCurrent) ? ' selected="selected"' : '');?>><?php echo _t('(최상위)');?></option>
<?php
	foreach (getCategoriesFlat($blogid) as $parentCandidate) {
		if (in_array($parentCandidate['id'], $parentExcluded))
			continue;
		$parentTooDeep = ($parentCandidate['depth'] + $parentSubtreeHeight > $categoryMaxDepth);
?>
														<option value="<?php echo $parentCandidate['id'];?>"<?php echo ($parentCandidate['id'] == $parentCurrent ? ' selected="selected"' : '').($parentTooDeep ? ' disabled="disabled"' : '');?>><?php echo str_repeat('― ', $parentCandidate['depth'] - 1).htmlspecialchars($parentCandidate['name'], ENT_QUOTES | ENT_SUBSTITUTE);?></option>
<?php
	}
?>
													</select>
													<input type="button" class="save-button input-button" value="<?php echo _t('옮기기');?>" onclick="changeParent(); return false;" />
												</div>
												<p><?php echo _t('다른 분류 아래로 옮깁니다. 하위 분류도 함께 옮겨집니다.');?></p>
											</dd>
<?php
} else {
?>
											<dd><p><?php echo _t('최상단 분류는 이동할 수 없습니다.');?></p></dd>
<?php
}
?>
										</dl>
										<dl id="label-depth-line" class="line">
											<dt><label for="maxDepthSelect"><?php echo _t('분류 깊이');?></label></dt>
											<dd>
												<div class="field-box">
													<select id="maxDepthSelect">
<?php
for ($depthOption = 2; $depthOption <= 10; $depthOption++) {
?>
														<option value="<?php echo $depthOption;?>"<?php echo ($depthOption == $categoryMaxDepth ? ' selected="selected"' : '');?>><?php echo _f('%1단', $depthOption);?></option>
<?php
}
?>
													</select>
													<input type="button" class="save-button input-button" value="<?php echo _t('저장하기');?>" onclick="changeMaxDepth(); return false;" />
												</div>
												<p><?php echo _t('분류를 몇 단까지 나눌 수 있는지 정합니다. 지금 있는 분류보다 얕게 줄일 수는 없습니다.');?></p>
											</dd>
										</dl>
										<dl id="label-move-line" class="line">
											<dt><span class="label"><?php echo _t('정렬순서 변경');?></span></dt>
<?php
if ($selected > 0) {
?>
											<dd>
												<div class="field-box">
													<input type="button" class="up-button input-button" value="<?php echo _t('위로');?>" onclick="moveCategory('up');" /><span class="divider"> | </span><input type="button" class="down-button input-button" value="<?php echo _t('아래로');?>" onclick="moveCategory('down');" />
												</div>
												<p><?php echo _t('같은 단계 안에서 순서를 바꿉니다. 다른 분류 아래로 옮기려면 상위 분류를 고르십시오.');?></p>
											</dd>
<?php
} else {
?>
											<dd><p><?php echo _t('최상단 분류는 이동할 수 없습니다.');?></p></dd>
<?php
}
?>

										</dl>
										<dl id="label-visibility-line" class="line">
											<dt><span class="label"><?php echo _t('공개 설정');?></span></dt>
											<dd>
<?php
if ($selected > 0) {
?>
												<input type="checkbox" id="currentVisibility" class="checkbox" name="currentVisibility"<?php echo $visibility != 2 ? ' checked="checked"' : '';?> onclick="changeCategoryVisibility();return false;" /><label for="currentVisibility"><?php echo _t('이 카테고리를 비공개로 설정합니다.');?></label>
<?php
} else {
?>
												<p><?php echo _t('최상단 분류는 비공개로 설정할 수 없습니다.');?></p>
<?php
}
?>
											</dd>
										</dl>
										<dl id="label-remove-line" class="line">
											<dt><span class="label"><?php echo _t('분류 삭제');?></span></dt>
											<dd>

<?php
if ($selected == 0) {
	echo '<p>'._t('최상단 분류는 삭제할 수 없습니다.').'</p>';
} else if (getNumberEntryInCategories($selected) > 0) {
	echo '<p>'._t('분류에 등록된 글이 있으므로 삭제할 수 없습니다.').'</p>';
} else if (getNumberChildCategory($selected) > 0) {
	echo '<p>'._t('하위 분류가 있으므로 삭제할 수 없습니다.').'</p>';
} else {
?>
												<div class="field-box">
													<input type="button" class="remove-button input-button" value="<?php echo _t('삭제하기');?>" onclick="removeCategory();" />
												</div>
<?php
}
?>
											</dd>
										</dl>
									</fieldset>
								</form>
							</div>
						</div>
<?php
require ROOT . '/interface/common/owner/footer.php';
?>
