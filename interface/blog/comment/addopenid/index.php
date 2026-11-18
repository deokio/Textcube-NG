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

$entryId = $suri['id'];
$IV = array(
	'GET' => array(
		"tid" => array('string', 'default' => ''),
	),
	'POST' => array(
		'key' => array('string', 32, 32),
		"comment_type" => array(array('openid'), 'mandatory' => true),
		"secret" => array(array('1', 'on'), 'mandatory' => false),
		"homepage" => array('url', 'default' => 'http://'),
		"openid_identifier" => array('string', 'default' => ''),
		"openid_errormsg" => array('string', 'default' => ''),
		"comment" => array('string', 'default' => ''),
		"requestURI" => array('string', 'default' => '' )
	)
);

$tr = array();
if( !empty( $_GET["tid"] ) ) {
	$tr = Transaction::unpickle( $_GET["tid"] );
	if( !is_array($tr) || !isset($tr['_POST']) || !is_array($tr['_POST']) ) {
		// [보안] 다른 세션의 tid·만료·형식 오류(unpickle false, v3.153) — 저장된 값을 쓰지 않는다.
		// Referer 도 지우므로 아래 requireStrictRoute 가 412 로 막는다.
		$tr = array();
		$_POST = array();
		unset($_SERVER['HTTP_REFERER']);
	} else {
		$_POST = $tr['_POST'];
		if( isset($tr['HTTP_REFERER']) && is_string($tr['HTTP_REFERER']) ) {
			$_SERVER['HTTP_REFERER'] = $tr['HTTP_REFERER'];
		} else {
			unset($_SERVER['HTTP_REFERER']);
		}
	}
} else {
	$_SESSION['last_comment'] = array();
	$_SESSION['last_comment']['homepage'] = $_POST["homepage"];
	$_SESSION['last_comment']['comment'] = $_POST["comment"];
}

requireStrictRoute();
// [보안] 돌아갈 주소(오류 알림 뒤 이동·작성 뒤 Location)는 getSafeRedirectURI 로 제한한다(v3.153) — javascript:·외부 주소는 블로그 첫 화면으로.
$_POST["requestURI"] = getSafeRedirectURI((isset($_POST["requestURI"]) && is_string($_POST["requestURI"])) ? $_POST["requestURI"] : '', $context->getProperty('uri.blog') . '/');
header('Content-Type: text/html; charset=utf-8');

if(!Validator::validate($IV)) {
	OpenIDConsumer::printErrorReturn( 'Illegal parameters', $_POST["requestURI"] );
}

if( $_POST["comment_type"] != 'openid' ) {
	OpenIDConsumer::printErrorReturn( 'Invalid comment type', $_POST["requestURI"] );
}

if (!isset($_POST['key']) || $_POST['key'] != md5(filemtime(ROOT . '/config.php'))) {
	OpenIDConsumer::printErrorReturn( 'Illegal parameters', $_POST["requestURI"] );
}

if ($_POST["comment"] == '') {
	OpenIDConsumer::printErrorReturn( _text('본문을 입력해 주십시오.'), $_POST["requestURI"] );
}

$openid_identity = Acl::getIdentity('openid');
// 이 경로(댓글 패널의 '오픈아이디' 선택)는 OpenID(OIDC) 신원만 받는다. 소셜 신원이면 OIDC 인증으로 보낸다 — 소셜 신원은
// 일반 댓글 경로로 쓴다(신원 종류를 섞지 않음, v3.153).
if( OpenID::identityKind($openid_identity) !== 'oidc' ) {
	$openid_identity = null;
}
if( $openid_identity ) {
	/* OpenID success return path.. */
	$_POST["name"] = isset($_SESSION['openid']['nickname']) ? $_SESSION['openid']['nickname'] : '';
	if( !is_string($_POST["name"]) || $_POST["name"] === '' ) {
		// 이름이 없으면 식별자 원문 대신 표시 이름으로(v3.153).
		$_POST["name"] = OpenID::getDisplayName($openid_identity);
	}
	if( empty($_POST["homepage"]) || $_POST["homepage"] == "http://" ) {
		// 홈페이지는 세션 값, 없으면 식별자가 주소(과거 OpenID 2.0)일 때만 — 'oidc:…' 식별자를 홈페이지로 저장하지 않는다(v3.153).
		if( !empty($_SESSION['openid']['homepage']) ) {
			$_POST["homepage"] = $_SESSION['openid']['homepage'];
		} else {
			$_POST["homepage"] = OpenID::identityKind($openid_identity) === 'url' ? $openid_identity : '';
		}
	}
} else {
	if( empty($tr['openid_errormsg']) ) {
		/* OpenID request path.. */
		global $blogURL;
		$pickled = array('_POST' => $_POST);
		if( isset($_SERVER['HTTP_REFERER']) ) {
			$pickled['HTTP_REFERER'] = $_SERVER['HTTP_REFERER'];
		}
		$tid = Transaction::pickle( $pickled );
		// __T__ 는 위 Validator 가 IV 밖 GET 키로 지우므로 보통 비어 있다 — 정의되지 않은 키 경고 없이 같은 결과(v3.153).
		$__T__ = (isset($_GET['__T__']) && is_string($_GET['__T__'])) ? $_GET['__T__'] : '';
		$requestURI = urlencode("$blogURL/comment/addopenid/$entryId?tid=$tid&__T__=" . rawurlencode($__T__));

		/* eas_mode will redirect your browser to the IdP authentication page in EAS4.js addComment-onError handler */
		header( "Location: $blogURL/login/openid?action=try_auth&openid_remember=y&requestURI=$requestURI&fallbackURI=".urlencode($_POST["requestURI"]).
			"&openid_identifier=".urlencode($_POST["openid_identifier"]) );
		exit;
	} else {
		/* OpenID failure return path.. */
		OpenIDConsumer::printErrorReturn($tr['openid_errormsg'], $_POST["requestURI"] );
	}
}

$userName = isset($_POST["name"]) ? $_POST["name"] : '';
$userSecret = isset($_POST["secret"]) ? 1 : 0;
$userHomepage = isset($_POST["homepage"]) ? $_POST["homepage"] : '';
$userComment = isset($_POST["comment"]) ? $_POST["comment"] : '';

$comment = array();
$comment['entry'] = $entryId;
$comment['parent'] = null;
$comment['name'] = $userName;
// [보안] 원본은 모든 OpenID 댓글을 공개 상수 OPENID_PASSWORD 로 저장해 누구나 그 값으로 수정·삭제할 수 있었다 →
// 빈 비밀번호로 저장(비밀번호로는 열 수 없음). 수정·삭제는 OpenID 신원 일치 또는 블로그 소유자만
$comment['password'] = '';
// 입력 홈페이지가 없으면 식별자가 주소(과거 OpenID 2.0)일 때만 홈페이지로 쓴다(v3.153).
$comment['homepage'] = ($userHomepage == '' || $userHomepage == 'http://') ? (OpenID::identityKind($openid_identity) === 'url' ? $openid_identity : '') : $userHomepage;
$comment['secret'] = $userSecret;
$comment['comment'] = $userComment;
$comment['ip'] = $_SERVER['REMOTE_ADDR'];

$result = addComment($blogid, $comment);

$errorString = '';
if (in_array($result, array("ip", "name", "homepage", "comment", "etc"))) {
	switch ($result) {
		case "name":
			$errorString = _text('차단된 이름을 사용하고 계시므로 댓글을 남기실 수 없습니다.');
			break;
		case "ip":
			$errorString = _text('차단된 IP를 사용하고 계시므로 댓글을 남기실 수 없습니다.');
			break;
		case "homepage":
			$errorString = _text('차단된 홈페이지 주소를 사용하고 계시므로 댓글을 남기실 수 없습니다.');
			break;
		case "comment":
			$errorString = _text('금칙어를 사용하고 계시므로 댓글을 남기실 수 없습니다.');
			break;
		case "etc":
			$errorString = _text('귀하는 차단되었으므로 사용하실 수 없습니다.');
			break;
	}
} else if ($result === false) {
	$errorString = _text('댓글을 달 수 없습니다.');
}

if( $errorString ) {
	OpenIDConsumer::printErrorReturn( $errorString, $_POST["requestURI"] );
}

OpenIDConsumer::updateUserInfo( $userName, $userHomepage );

header( "Location: {$_POST['requestURI']}" );
?>
