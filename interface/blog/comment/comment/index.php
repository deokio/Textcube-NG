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
require ROOT . '/library/preprocessor.php';
requireModel('blog.comment');
$IV = array(
	'POST' => array(
		'name' => array('string', 'default' => ''),
		'comment' => array('string' , 'default' => ''),
		'mode' => array(array('commit') , 'default' => ''),
		'homepage' => array('string', 'default' => ''),
		'password' => array('string' , 'default' => ''),
		'secret' => array(array('on'), 'default' => null)
	)
);
$customIV = fireEvent('ManipulateIVRules',$IV,$context->getProperty('uri.interfaceRoute'));
Validator::addRule($customIV);
if(!Validator::isValid())
	Respond::PrintResult(array('error' => 1, 'description' => 'Illegal parameters'));
requireStrictRoute();
$suri['id'] = (int)$suri['id'];

if (!Setting::getBlogSettingGlobal('acceptComments',1) && !doesHaveOwnership()) {	// Textcube-NG: 설정 행이 없을 때의 기본값을 화면(library/view/view.php)·설치 값과 같은 1(허용)로 맞춘다(원본 0, r3-15)
	Respond::PrintResult(array('error' => 0, 'commentBlock' => '', 'recentCommentBlock' => ''));
	exit;
}
if ((doesHaveMembership() || !empty($_POST['name'])) && !empty($_POST['comment']) && !empty($_POST['mode']) && ($_POST['mode'] == 'commit')) {
	// Textcube-NG: r4-9 — 손님 이름·홈페이지 기억 쿠키를 읽는 쪽(library/view/view.php·replyEditorView.php·mobileView.php)과 같은 접두어(service.cookie_prefix) 붙은 이름으로 쓴다. 원본부터 쓰는 이름과 읽는 이름이 달라 기억이 동작하지 않았다
	// Textcube-NG: r4-9 — 접두어에 쿠키 이름에 쓸 수 없는 문자(=,; 공백·탭·줄바꿈·\013·\014)가 있으면 이 기억 쿠키만 쓰지 않는다(PHP 8 setcookie() 의 ValueError 로 댓글 저장까지 막히지 않게, 사용자 결정 11-23)
	$guestCookiePrefix = (string)$context->getProperty('service.cookie_prefix');
	$guestCookieOk = (strpbrk($guestCookiePrefix, "=,; \t\r\n\013\014") === false);
	if (!empty($_POST['name']))
		if ($guestCookieOk) setCookieCompat($guestCookiePrefix . 'guestName', $_POST['name'], array('expires' => time() + 2592000, 'path' => "$blogURL/", 'secure' => (bool)$context->getProperty('service.useSSL', false), 'httponly' => true, 'samesite' => 'Lax'));
	if (!empty($_POST['homepage']) && ($_POST['homepage'] != 'http://')) {
		if ($guestCookieOk && !preg_match('/^\s*javascript\s*:/i', $_POST['homepage'])) {	// Textcube-NG: r4-9 — 이 블록은 홈페이지 기억 쿠키만 쓴다
			if (strpos($_POST['homepage'], 'http://') === 0)
				setCookieCompat($guestCookiePrefix . 'guestHomepage', $_POST['homepage'], array('expires' => time() + 2592000, 'path' => "$blogURL/", 'secure' => (bool)$context->getProperty('service.useSSL', false), 'httponly' => true, 'samesite' => 'Lax'));
			else
				setCookieCompat($guestCookiePrefix . 'guestHomepage', 'http://' . $_POST['homepage'], array('expires' => time() + 2592000, 'path' => "$blogURL/", 'secure' => (bool)$context->getProperty('service.useSSL', false), 'httponly' => true, 'samesite' => 'Lax'));
		}
	}
	$comment = array();
	list($comment['entry']) = getCommentAttributes($blogid, $suri['id'], 'entry');
	if (count($comment) == 0)
		Respond::ErrorPage(_text('댓글이 존재하지 않습니다.'));
	$comment['parent'] = $suri['id'];
	$comment['name'] = empty($_POST['name']) ? '' : trim($_POST['name']);
	$comment['password'] = empty($_POST['password']) ? '' : $_POST['password'];
	$comment['homepage'] = empty($_POST['homepage']) || ($_POST['homepage'] == 'http://') ? '' : trim($_POST['homepage']);
	$comment['secret'] = empty($_POST['secret']) ? 0 : 1;
	$comment['comment'] = trim($_POST['comment']);
	$comment['ip'] = $_SERVER['REMOTE_ADDR'];
	if (!doesHaveMembership() && !doesHaveOwnership() && $comment['name'] == '') {
	?>
<script type="text/javascript">
	//<![CDATA[
		alert("<?php echo _text('이름을 입력해 주십시오.');?>");
	//]]>
</script>
<?php
	} else if ($comment['comment'] == '') {
?>
<script type="text/javascript">
	//<![CDATA[
		alert("<?php echo _text('본문을 입력해 주십시오.');?>");
	//]]>
</script>
<?php
	} else if (addComment($blogid, $comment) !== false) {
		if(!$comment['secret']) {
			if($row = POD::queryRow("SELECT * FROM {$database['prefix']}Entries
				WHERE blogid = $blogid AND id = {$comment['entry']} AND draft = 0 AND visibility = 3 AND acceptcomment = 1"))
				sendCommentPing($comment['entry'], "$defaultURL/".($blog['useSloganOnPost'] ? "entry/{$row['slogan']}": $comment['entry']), is_null($user) ? $comment['name'] : $user['name'], is_null($user) ? $comment['homepage'] : $user['homepage']);
		}
		$skin = new Skin($skinSetting['skin']);
		printHtmlHeader();
?>
<script type="text/javascript">
	//<![CDATA[
		alert("<?php echo _text('댓글이 등록되었습니다.');?>");
<?php
			notifyComment();
			$entry = array();
			$entry['id'] = $comment['entry'];
			$entry['slogan'] = getSloganById($blogid, $entry['id']);
			$tempComments = revertTempTags(removeAllTags(getCommentView($entry, $skin)));
			$tempRecentComments = revertTempTags(getRecentCommentsView(getRecentComments($blogid), null, $skin->recentCommentItem));
?>
		if (opener == null) {
			loader = parent;
		} else {
			loader = opener;
		}
		try {
			var obj = loader.document.getElementById("entry<?php echo (int)$comment['entry'];?>Comment");
			obj.innerHTML = "<?php echo str_innerHTML($tempComments);?>";
		} catch(e) { }
		try {
			obj = loader.document.getElementById("recentComments");
			obj.innerHTML = "<?php echo str_innerHTML($tempRecentComments);?>";
		} catch(e) { }
		try {
<?php
			$commentCount = getCommentCount($blogid, $comment['entry']);
			list($tempTag, $commentView) = getCommentCountPart($commentCount, $skin);
			$commentCount = ($commentCount > 0) ? "($commentCount)" : '';
?>
			obj = loader.document.getElementById("commentCount<?php echo (int)$comment['entry'];?>");
			if (obj != null) obj.innerHTML = "<?php echo str_innerHTML($commentView);?>";
		} catch(e) { }
		try {
			obj = loader.document.getElementById("commentCountOnRecentEntries<?php echo (int)$comment['entry'];?>");
			if (obj != null) obj.innerHTML = "<?php echo str_innerHTML($commentCount);?>";
		} catch(e) { }
		try {
			obj = loader.document.getElementById('list-form');
			if(obj != null) loader.document.getElementById('list-form').submit();
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
	}
}
$pageTitle = _text('댓글에 댓글 달기');
$comment = array('name' => '', 'password' => '', 'homepage' => 'http://', 'secret' => 0, 'comment' => '');
$viewMode = 'comment';
require ROOT . '/library/view/replyEditorView.php';
?>
