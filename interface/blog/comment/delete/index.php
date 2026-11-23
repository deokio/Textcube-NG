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
define('__TEXTCUBE_ADMINPANEL__',true);

$IV = array(
	'POST' => array(
		'mode' => array( array('delete','edit','commit'), 'default' => null ),
		'password' => array( 'string' , 'default' => ''),
		'oldPassword' => array('string' , 'default' => ''),
		'name' => array('string' , 'default' => ''),
		'comment' => array('string' , 'default' => ''),
		'homepage' => array('string', 'default' => ''),
		'openidedit' => array('string', 'default' => ''),
		'secret' => array(array('on'), 'default' => null)
	)
);
require ROOT . '/library/preprocessor.php';

$blogid = getBlogId();
$suri['id'] = (int)$suri['id'];
list($replier) = getCommentAttributes($blogid,$suri['id'],'replier');
$comment = POD::queryRow("SELECT * FROM {$database['prefix']}Comments WHERE blogid = $blogid AND id = {$suri['id']}");
$openid_identity = Acl::getIdentity('openid');

if(!Acl::check('group.administrators') && !Acl::check('group.owners')) { // If no administration permission,
	if(!empty($replier)) {	// If replier exists, (member of the blog system)
		if(!Acl::check('group.owners')) { // If not blog owner,
			if(!Acl::check('group.editors') && $replier != getUserId()) {
				echo "<script type=\"text/javascript\">//<![CDATA[".CRLF
					."alert('"._t('권한이 없습니다.')."'); window.close(); //]]></script>";
				// [보안] 원본은 안내만 출력하고 아래 처리(편집 화면 출력 등)를 계속했다 → 여기서 종료
				exit;
			}
		}
	}
}
list($replier) = getCommentAttributes($blogid, $suri['id'], 'replier');
if (!empty($_POST['mode'])) {
	switch ($_POST['mode']) {
		case 'delete':
			if (!list($entryId) = getCommentAttributes($blogid, $suri['id'], 'entry'))
				Respond::ErrorPage(_text('댓글이 존재하지 않습니다.'));
			$result = false;
			if (doesHaveOwnership()) {
				$result = trashComment($blogid, $suri['id'], $entryId, isset($_POST['password']) ? $_POST['password'] : '');
			} else {
				$result = deleteComment($blogid, $suri['id'], $entryId, isset($_POST['password']) ? $_POST['password'] : '');
			}
			if ($result == true) {
				$skin = new Skin($skinSetting['skin']);
				$entry = array();
				$entry['id'] = $entryId;
				$entry['slogan'] = getSloganById($blogid, $entry['id']);
				printHtmlHeader();
				$tempComments = revertTempTags(removeAllTags(getCommentView($entry, $skin)));
				$tempRecentComments = revertTempTags(getRecentCommentsView(getRecentComments($blogid), $skin->recentComment, $skin->recentCommentItem));
?>
<script type="text/javascript">
	//<![CDATA[
		alert("<?php echo _text('댓글이 삭제되었습니다.');?>");
		if (opener == null) {
			loader = parent;
		} else {
			loader = opener;
		}
		var obj = loader.document.getElementById("entry<?php echo (int)$entryId;?>Comment");
		obj.innerHTML = "<?php echo str_innerHTML($tempComments);?>";
		obj = loader.document.getElementById("recentComments");
		if(obj)
			obj.innerHTML = "<?php echo str_innerHTML($tempRecentComments);?>";
<?php
$commentCount = getCommentCount($blogid, $entryId);
$commentCount = ($commentCount > 0) ? $commentCount : '';
list($tempTag, $commentView) = getCommentCountPart($commentCount, $skin);
?>
		try {
			obj = loader.document.getElementById("commentCount<?php echo (int)$entryId;?>");
			if (obj != null) obj.innerHTML = "<?php echo str_innerHTML($commentView);?>";
		} catch(e) { }
		try {
			obj = loader.document.getElementById("commentCountOnRecentEntries<?php echo (int)$entryId;?>");
			if (obj != null) obj.innerHTML = "<?php echo str_innerHTML(($commentCount > 0) ? '(' . $commentCount . ')' : '');?>";
		} catch(e) { }
		if (opener == null) {
			parent.tcDialog.close();
		} else {
			window.close();
		}
	//]]>
</script>
<?php
				printHtmlFooter();
				exit;
			} else {
				Respond::ErrorPage(_text('패스워드가 일치하지 않습니다.'));
				exit;
			}

		case 'edit':
			if( !empty( $_POST['openidedit'] ) ) {
				//$comment is feched top of this script;
				/* [보안] 비로그인이면 $openid_identity 는 null, 일반 댓글의 openid 는 '' 라 느슨한 비교(null != '')가 거짓이 되어
				   비밀번호 확인 없이 댓글(비밀 댓글·방명록 포함)이 편집 화면에 출력됐다 →
				   OpenID 신원이 있고 댓글의 openid 와 정확히 같을 때만 허용 */
				if( empty($openid_identity) || !is_array($comment) || $openid_identity !== $comment['openid'] ) {
					$comment = false;
				}
			} else {
				$comment = getComment($blogid, $suri['id'], isset($_POST['password']) ? $_POST['password'] : '');
			}
			if ($comment === false)
				Respond::ErrorPage(_text('댓글이 존재하지 않거나 패스워드가 일치하지 않습니다.'),null,null);
			$pageTitle = _text('댓글을 수정합니다');
			$viewMode = 'edit';
			require ROOT . '/library/view/replyEditorView.php';
			exit;
		case 'commit':
			if( !empty( $_POST['openidedit'] ) ) {
				//$comment is feched top of this script;
				// [보안] edit 와 같은 엄격 비교(OpenID 신원 필수)
				if( empty($openid_identity) || !is_array($comment) || $openid_identity !== $comment['openid'] ) {
					$comment = false;
				}
			} else {
				$comment = getComment($blogid, $suri['id'], isset($_POST['oldPassword']) ? $_POST['oldPassword'] : '');
			}
			if ($comment === false)
				Respond::ErrorPage(_text('댓글이 존재하지 않거나 패스워드가 일치하지 않습니다.'),null,null);
			if ((doesHaveMembership() || !empty($_POST['name'])) && !empty($_POST['comment'])) {
				if (!doesHaveOwnership()) {
					$comment['name'] = $_POST['name'];
					$comment['password'] = $_POST['password'];
					$comment['homepage'] = empty($_POST['homepage']) || ($_POST['homepage'] == 'http://') ? '' : $_POST['homepage'];
					$comment['ip'] = $_SERVER['REMOTE_ADDR'];
				}
				//$comment['email'] = empty($_POST['email']) || ($_POST['email'] == '') ? '' : $_POST['email'];
				$comment['secret'] = empty($_POST['secret']) ? 0 : 1;
				$comment['comment'] = $_POST['comment'];
				$result = updateComment($blogid, $comment, isset($_POST['oldPassword']) ? $_POST['oldPassword'] : '');
				if ($result === 'blocked') {
					printHtmlHeader();
?>
<script type="text/javascript">
	//<![CDATA[
		alert("<?php echo _text('귀하는 차단되었으므로 사용하실 수 없습니다.');?>");
		if (opener == null) {
			parent.tcDialog.close();
		} else {
			window.close();
		}
	//]]>
</script>
<?php
					printHtmlFooter();
					exit;
				} else if ($result !== false) {
					$skin = new Skin($skinSetting['skin']);
					$suri['page'] = getGuestbookPageById($blogid, $suri['id']);
					$entry = array();
					$entry['id'] = $comment['entry'];
					$entry['slogan'] = getSloganById($blogid, $entry['id']);
					if( Acl::getIdentity( 'openid' ) ) {
						OpenIDConsumer::updateUserInfo( $comment['name'], $comment['homepage'] );
					}
					printHtmlHeader();
					$tempComments = revertTempTags(removeAllTags(getCommentView($entry, $skin)));
					$tempRecentComments = revertTempTags(getRecentCommentsView(getRecentComments($blogid), null, $skin->recentCommentItem));
?>
<script type="text/javascript">
	//<![CDATA[
		alert("<?php echo _text('댓글이 수정되었습니다.');?>");
		if (opener == null) {
			loader = parent;
		} else {
			loader = opener;
		}
		try {
			var obj = loader.document.getElementById("entry<?php echo (int)$entry['id'];?>Comment");
			if (obj != null) {
				obj.innerHTML = "<?php echo str_innerHTML($tempComments);?>";
				var recentComment = loader.document.getElementById("recentComments");
				if(recentComment)
					recentComment.innerHTML = "<?php echo str_innerHTML($tempRecentComments);?>";
			} else {
				var listObj = loader.document.getElementById('list-form');
				if(listObj != null) loader.document.getElementById('list-form').submit();
			}
			if (opener == null) {
				parent.tcDialog.close();
			} else {
				window.close();
				opener.openWindow = '';
			}
		} catch(e) {
			// alert(e.message);
		}
	//]]>
</script>
<?php
					printHtmlFooter();
					exit;
				} else {
					Respond::ErrorPage(_text('수정이 실패하였습니다.'));
				}
			}
	}
	Respond::ErrorPage();
}
?>
<!DOCTYPE html>
<html>
<head>
	<title><?php echo _text('댓글 삭제') ;?></title>
	<meta charset="UTF-8" name="viewport" content="width=device-width, initial-scale=1.0">
	<link rel="stylesheet" type="text/css" media="screen" href="<?php echo $service['path'] . $adminSkinSetting['skin'];?>/popup-comment.css" />
	<script type="text/javascript">
		//<![CDATA[
			var servicePath = "<?php echo $service['path'];?>";
			var blogURL = "<?php echo $blogURL;?>";
			var adminSkin = "<?php echo $adminSkinSetting['skin'];?>";
		//]]>
	</script>
<?php /* Textcube-NG: 이식본에서 고친 스크립트(EAF4.js·common3.js·gallery.js·jQuery, v3.147·v3.207)는 externalresources 설정과 관계없이 이 서버의 /resources 에서 읽는다 — 외부 리소스 주소의 원본 스크립트에는 CSRF 토큰 패치가 없다(r3-3) */ ?>
	<script type="text/javascript" src="<?php echo $service['path'];?>/resources/script/common3.js?v=<?php echo filemtime(ROOT . '/resources/script/common3.js');?>"></script>
</head>
<body>
	<form name="deleteComment" method="post" action="<?php echo $blogURL;?>/comment/delete/<?php echo $suri['id'];?>">
		<div id="comment-box">
			<img src="<?php echo $service['path'] . $adminSkinSetting['skin'];?>/image/img_comment_popup_logo.gif" alt="<?php echo _text('텍스트큐브 로고');?>" />
			<a onclick="closeDialog();" href="#" class="close-button"><span>X</span></a>

			<div id="command-box">
<?php
	$render = true;
	if( !empty($comment['openid']) && !Acl::check('group.administrators') ) { ?>
<?php		if( !$openid_identity ) { $render = false;?>
				<div class="edit-line">
					<label><?php echo _text('권한이 없습니다.') ?></label>
				</div>
				<div class="password-line">
					<input type="button" class="input-button" name="Submit" value="<?php echo _text('닫기');?>" onclick="window.close()" />
				</div>
<?php		} else if( $openid_identity != $comment['openid']) { $render = false;?>
				<div class="edit-line">
					<label><?php echo _text('로그인된 오픈아이디의 권한으로는 수정/삭제가 불가능합니다.') ?></label>
				</div>
				<div class="password-line">
					<input type="button" class="input-button" name="Submit" value="<?php echo _text('닫기');?>" onclick="window.close()" />
				</div>
<?php 		} ?>
<?php }
	  if ($render) { ?>
				<div class="edit-line">
					<input type="radio" id="edit" class="radio" name="mode" value="edit" checked="checked" /><label for="edit"><?php echo _text('댓글을 수정합니다.');?></label>
				</div>
				<div class="delete-line">
					<input type="radio" id="delete" class="radio" name="mode" value="delete" /><label for="delete"><?php echo _text('댓글을 삭제합니다.');?></label>
				</div>
				<div class="password-line">
<?php 		if (!doesHaveOwnership() && (!doesHaveMembership() || ($replier != getUserId())) ) {
				if( !$openid_identity || $openid_identity != $comment['openid'] ) { ?>
					<label for="password"><?php echo _text('비밀번호');?><span class="divider"> | </span></label><input type="password" id="password" class="input-text" name="password" />
<?php 			} else { ?>
					<input name="openidedit" type="hidden" value="1" />
<?php 			} ?>
<?php		} ?>
					<input type="button" class="input-button" name="Submit" value="<?php echo _text('다음');?>" onclick="document.deleteComment.submit()" />
				</div>
<?php	} ?>
			</div>
		</div>
	</form>
</body>
</html>
