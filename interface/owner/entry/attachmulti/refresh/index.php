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
requireModel("blog.attachment");
/* [보안] 원본은 entryId 를 확인하지 않아 다른 사람의 글(비공개 포함)·임시 첨부 목록을 볼 수 있었다
   → 첨부 parent 는 공통 기준(blog.attachment.php getAttachmentParentForEdit()) */
$entryId = getAttachmentParentForEdit($blogid, isset($suri['id']) ? $suri['id'] : null);
if ($entryId === false) {
	header('HTTP/1.1 403 Forbidden');
	exit;
}
/* Textcube-NG: getAttachments() 는 조회 실패(DB 잠금 등)와 첨부 없음을 모두 빈 배열로 돌려줘 빈 목록(HTTP 200)이 나갔고,
   편집 화면이 그 목록으로 덮어썼다 → 같은 조건(blogid·parent, label 순)으로 직접 조회해 실패(null)면 HTTP 500 */
$pool = DBModel::getInstance();
$pool->reset('Attachments');
$pool->setQualifier('blogid','equals',intval($blogid));
$pool->setQualifier('parent','equals',intval($entryId));
$pool->setOrder('label','ASC');
$attachments = $pool->getAll('*');
if (!is_array($attachments)) {
	header('HTTP/1.1 500 Internal Server Error');
	exit;
}
$initialFileListForFlash = '';
$enclosureFileName = '';
?>
<select name="TCfilelist" id="TCfilelist" multiple="multiple" size="8" onchange="selectAttachment();">
<?php
foreach ($attachments as $i => $attachment) {
	if (strpos($attachment['mime'], 'application') !== false) {
		$class = 'class="MimeApplication"';
	} else if (strpos($attachment['mime'], 'audio') !== false) {
		$class = 'class="MimeAudio"';
	} else if (strpos($attachment['mime'], 'image') !== false) {
		$class = 'class="MimeImage"';
	} else if (strpos($attachment['mime'], 'message') !== false) {
		$class = 'class="MimeMessage"';
	} else if (strpos($attachment['mime'], 'model') !== false) {
		$class = 'class="MimeModel"';
	} else if (strpos($attachment['mime'], 'multipart') !== false) {
		$class = 'class="MimeMultipart"';
	} else if (strpos($attachment['mime'], 'text') !== false) {
		$class = 'class="MimeText"';
	} else if (strpos($attachment['mime'], 'video') !== false) {
		$class = 'class="MimeVideo"';
	} else {
		$class = '';
	}
	if ($attachment['enclosure'] == 1) {
		$class = ' class="enclosure"';
		$enclosureFileName = $attachment['name'];
	} else {
		$class = '';
	}
	$value = htmlspecialchars(getAttachmentValue($attachment));
	$label = htmlspecialchars(getPrettyAttachmentLabel($attachment));
?>
		        <option<?php echo $class;?> value="<?php echo $value;?>">
	            <?php echo $label;?>
	            </option>
                <?php
}
?>
</select>
