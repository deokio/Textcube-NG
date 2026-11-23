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

function doesHaveOpenIDPriv( & $comment )
{
	global $database;
	$blogid = getBlogId();
	$openid = Acl::getIdentity('openid');

	if( !$comment['secret'] || !$openid ) {
		return false;
	}
	if( $comment['openid'] == $openid ) {
		return true;
	}
	if( empty($comment['parent']) ) {
		return false;
	}
	$openid = POD::escapeString($openid);
	$row = POD::queryRow("SELECT * from {$database['prefix']}Comments ".
		"WHERE blogid = $blogid and id = {$comment['parent']} and openid='{$openid}'" );
	return !empty($row);
}

/* [보안] Textcube-NG: 비밀 댓글·방명록 열람 판정(결정 447) — 원본은 doesHaveOwnership()(구성원 전원).
   editors 이상 구성원은 모두 본다. 로그인한 사용자(구성원이 아닌 가입 회원 포함)는 자기가 쓴 비밀 글(replier)과, 자기가 로그인해서 쓴 부모 댓글
   (비밀 여부 무관)에 달린 비밀 답글을 본다 — 글 댓글·방명록 모두. 그 밖에 글 댓글은 그 글의 작성자(구성원)도 본다, 방명록은 여기까지.
   비로그인 손님은 원본대로 부르는 쪽의 doesHaveOpenIDPriv(). $comment 는 Comments 행(replier·parent), 없으면 글·권한만 본다.
   부모 replier·글 작성자 조회는 정수 캐스트한 id 로 하고 요청 안에서 캐시한다(r3-7) */
function canReadSecretComment($entryId, $comment = null) {
	global $database;
	if (doesHaveOwnership() && Acl::check('group.editors'))
		return true;
	$userid = getUserId();
	if (!empty($userid) && is_array($comment)) {
		if (!empty($comment['replier']) && $comment['replier'] == $userid)
			return true;
		if (!empty($comment['parent'])) {
			static $parentRepliers = array();
			$blogid = intval(getBlogId());
			$parentId = intval($comment['parent']);
			$cacheKey = $blogid . ':' . $parentId;
			if (!array_key_exists($cacheKey, $parentRepliers))
				$parentRepliers[$cacheKey] = POD::queryCell("SELECT replier FROM {$database['prefix']}Comments WHERE blogid = " . $blogid . " AND id = " . $parentId);
			if (!empty($parentRepliers[$cacheKey]) && $parentRepliers[$cacheKey] == $userid)
				return true;
		}
	}
	$entryId = intval($entryId);
	if ($entryId <= 0 || !doesHaveOwnership())
		return false;
	static $authors = array();
	if (!array_key_exists($entryId, $authors)) {
		requireModel('blog.entry');
		$authors[$entryId] = getUserIdOfEntry(getBlogId(), $entryId);
	}
	return (!is_null($authors[$entryId]) && $authors[$entryId] == getUserId());
}

function decorateComment( & $comment )
{
	$authorized = canReadSecretComment(isset($comment['entry']) ? $comment['entry'] : 0, $comment);	// Textcube-NG: 비밀 댓글 열람(r3-7, 결정 447)
	$comment['hidden'] = false;
	$comment['name'] = htmlspecialchars($comment['name'], ENT_QUOTES | ENT_SUBSTITUTE);
	$comment['comment'] = htmlspecialchars($comment['comment'], ENT_QUOTES | ENT_SUBSTITUTE);
	if ($comment['secret'] == 1) {
		if($authorized) {
			$comment['comment'] = '<span class="hiddenCommentTag_content">' . _text('[비밀댓글]') . '</span> ' . $comment['comment'];
		} else {
			if( !doesHaveOpenIDPriv($comment) ) {
				$comment['hidden'] = true;
				$comment['name'] = '<span class="hiddenCommentTag_name">' . _text('비밀방문자') . '</span>';
				$comment['homepage'] = '';
				$comment['comment'] = _text('관리자만 볼 수 있는 댓글입니다.');
			} else {
				$comment['name'] = '<span class="hiddenCommentTag_name">' . _text('비밀방문자') . '</span>'. $comment['name'];
			}
		}
	}
}

function getCommentsWithPagingForOwner($blogid, $category, $name, $ip, $search, $page, $count, $isGuestbook = false) {
	global $database;

	$postfix = '';
	if(!$isGuestbook && !Acl::check("group.editors")) $userLimit = ' AND e.userid = '.getUserId();
	else $userLimit = '';
	$sql = "SELECT c.*, e.title, c2.name AS parentName
		FROM {$database['prefix']}Comments c
		LEFT JOIN {$database['prefix']}Entries e ON c.blogid = e.blogid AND c.entry = e.id AND e.draft = 0$userLimit
		LEFT JOIN {$database['prefix']}Comments c2 ON c.parent = c2.id AND c.blogid = c2.blogid
		WHERE c.blogid = $blogid AND c.isfiltered = 0";
	if ($category > 0) {
		requireModel('blog.category'); $categories = getCategoryDescendantIds($blogid, $category);	// Textcube-NG: R-592 — 직계 자식 대신 자손 전체(사용자 결정 ②, 블로그 범위)
		array_push($categories, $category);
		$sql .= ' AND e.category IN (' . implode(', ', $categories) . ')';
		$postfix .= '&amp;category=' . rawurlencode($category);
	} else
		$sql .= ' AND e.category >= 0';
	if (!empty($name)) {
		$sql .= ' AND c.name = \'' . POD::escapeString($name) . '\'';
		$postfix .= '&amp;name=' . rawurlencode($name);
	}
	if (!empty($ip)) {
		$sql .= ' AND c.ip = \'' . POD::escapeString($ip) . '\'';
		$postfix .= '&amp;ip=' . rawurlencode($ip);
	}
	if (!empty($search)) {
		$search = escapeSearchString($search);
		$sql .= " AND (c.name LIKE '%$search%' OR c.homepage LIKE '%$search%' OR c.comment LIKE '%$search%')";
		$postfix .= '&amp;search=' . rawurlencode($search);
	}

	$sql .= ' ORDER BY c.written DESC';
	list($comments, $paging) = Paging::fetch($sql, $page, $count);
	if (strlen($postfix) > 0) {
		$postfix .= '&amp;withSearch=on';
		$paging['postfix'] .= $postfix;
	}

	return array($comments, $paging);
}

function getGuestbookWithPagingForOwner($blogid, $name, $ip, $search, $page, $count) {
	global $database;

	$postfix = '&amp;status=guestbook';

	$sql = "SELECT c.*, c2.name AS parentName
		FROM {$database['prefix']}Comments c
		LEFT JOIN {$database['prefix']}Comments c2 ON c.parent = c2.id AND c.blogid = c2.blogid
		WHERE c.blogid = $blogid AND c.entry = 0 AND c.isfiltered = 0";
	if (!empty($name)) {
		$sql .= ' AND c.name = \'' . POD::escapeString($name) . '\'';
		$postfix .= '&amp;name=' . rawurlencode($name);
	}
	if (!empty($ip)) {
		$sql .= ' AND c.ip = \'' . POD::escapeString($ip) . '\'';
		$postfix .= '&amp;ip=' . rawurlencode($ip);
	}
	if (!empty($search)) {
		$search = escapeSearchString($search);
		$sql .= " AND (c.name LIKE '%$search%' OR c.homepage LIKE '%$search%' OR c.comment LIKE '%$search%')";
		$postfix .= '&amp;search=' . rawurlencode($search);
	}

	$sql .= ' ORDER BY c.written DESC';
	list($comments, $paging) = Paging::fetch($sql, $page, $count);
	if (strlen($postfix) > 0) {
		$postfix .= '&amp;withSearch=on';
		$paging['postfix'] .= $postfix;
	}

	return array($comments, $paging);
}

function getCommentsNotifiedWithPagingForOwner($blogid, $category, $name, $ip, $search, $page, $count) {
	global $database;
	$postfix = '';

	if (empty($name) && empty($ip) && empty($search)) {
		$sql = "SELECT
					c.*,
					csiteinfo.title AS siteTitle,
					csiteinfo.name AS nickname,
					csiteinfo.url AS siteUrl,
					csiteinfo.modified AS siteModified
				FROM
					{$database['prefix']}CommentsNotified c
				LEFT JOIN
						{$database['prefix']}CommentsNotifiedSiteInfo csiteinfo ON c.siteid = csiteinfo.id
				WHERE c.blogid = $blogid AND (c.parent is null)";
		$sql .= ' ORDER BY c.modified DESC';
	} else {
		if (!empty($search)) {
			$search = escapeSearchString($search);
		}

		$preQuery = "SELECT parent FROM {$database['prefix']}CommentsNotified WHERE blogid = $blogid AND parent is NOT NULL";
		if (!empty($name))
			$preQuery .= ' AND name = \''. POD::escapeString($name) . '\' ';
		if (!empty($ip))
			$preQuery .= ' AND ip = \''. POD::escapeString($ip) . '\' ';
		if (!empty($search)) {
			$preQuery .= " AND ((name LIKE '%$search%') OR (homepage LIKE '%$search%') OR (comment LIKE '%$search%'))";
		}

		// Textcube-NG: POD::queryColumn() 은 쿼리가 실패하면 null 을 돌려준다 — PHP 8 에서 array_unique(null) 은 TypeError(7.x 는 경고). 배열이 아니면 빈 배열로 둔다(원본 5.6 결과와 같음)(v3.221)
		$childList = POD::queryColumn($preQuery);
		$childList = is_array($childList) ? array_unique($childList) : array();
		$childListStr = (count($childList) == 0) ? '' : ('OR c.id IN ( ' . implode(', ',$childList) . ' ) ') ;

		$sql = "SELECT
				c.*,
				csiteinfo.title AS siteTitle,
				csiteinfo.name AS nickname,
				csiteinfo.url AS siteUrl,
				csiteinfo.modified AS siteModified
			FROM
				{$database['prefix']}CommentsNotified c
				LEFT JOIN
				{$database['prefix']}CommentsNotifiedSiteInfo csiteinfo ON c.siteid = csiteinfo.id
			WHERE c.blogid = $blogid AND (c.parent is null) ";
		if (!empty($name)) {
			$sql .= ' AND ( c.name = \'' . POD::escapeString($name) . '\') ' ;
			$postfix .= '&amp;name=' . rawurlencode($name);
		}
		if (!empty($ip)) {
			$sql .= ' AND ( c.ip = \'' . POD::escapeString($ip) . '\') ';
			$postfix .= '&amp;ip=' . rawurlencode($ip);
		}
		if (!empty($search)) {
			$sql .= " AND ((c.name LIKE '%$search%') OR (c.homepage LIKE '%$search%') OR (c.comment LIKE '%$search%')) ";
			$postfix .= '&amp;search=' . rawurlencode($search);
		}
		$sql .= $childListStr . ' ORDER BY c.modified DESC';
	}

	list($comments, $paging) = Paging::fetch($sql, $page, $count);
	if (strlen($postfix) > 0) {
		$postfix .= '&amp;withSearch=on';
		$paging['postfix'] .= $postfix;
	}

	return array($comments, $paging);
}

function getCommentCommentsNotified($parent) {
	global $database;
	$comments = array();
	// Textcube-NG: 비밀 댓글 열람은 아래에서 댓글마다 canReadSecretComment() — CommentsNotified 행이라 글·권한만 본다(r3-7, 결정 447)
	$sql = "SELECT
				c.*,
				csiteinfo.title AS siteTitle,
				csiteinfo.name AS nickname,
				csiteinfo.url AS siteUrl,
				csiteinfo.modified AS siteModified
			FROM
				{$database['prefix']}CommentsNotified c
				LEFT JOIN
				{$database['prefix']}CommentsNotifiedSiteInfo csiteinfo ON c.siteid = csiteinfo.id
			WHERE c.blogid = ".getBlogId()." AND c.parent = $parent";
	$sql .= ' ORDER BY c.written ASC';
	if ($result = POD::queryAll($sql)) {
		foreach($result as $comment) {
			if (($comment['secret'] == 1) && !canReadSecretComment($comment['entry'])) {
				if( !doesHaveOpenIDPriv($comment) ) {
					$comment['name'] = '';
					$comment['homepage'] = '';
					$comment['comment'] = _text('관리자만 볼 수 있는 댓글입니다.');
				}
			}
			array_push($comments, $comment);
		}
	}
	return $comments;
}

function getCommentsWithPagingByEntryId($blogid, $entryId, $page, $count, $url = null, $prefix = '?page=', $postfix = '', $countItem = null, $order = 'ASC') {
	global $database;
	$comments = array();
	if($entryId != -1) {
		$filter = 'AND entry = '.$entryId;
	} else $filter = 'AND entry > 0';
	$sql = "SELECT * FROM {$database['prefix']}Comments
		WHERE blogid = $blogid $filter
			AND parent IS NULL
			AND isfiltered = 0
		ORDER BY written ".($order == 'DESC' ? "DESC" : "ASC");
	list($comments, $paging) = Paging::fetch($sql, $page, $count, $url, $prefix, $countItem);
	$paging['postfix'] = $postfix;
	$comments = coverComments($comments);
	return array($comments, $paging);
}

function getCommentsWithPaging($blogid, $page, $count, $url = null, $prefix = '?page=', $postfix = '', $countItem = null) {
	global $database;
	$comments = array();
	$sql = "SELECT r.*
		FROM
			{$database['prefix']}Comments r
			INNER JOIN {$database['prefix']}Entries e ON r.blogid = e.blogid AND r.entry = e.id AND e.draft = 0
			LEFT OUTER JOIN {$database['prefix']}Categories c ON e.blogid = c.blogid AND e.category = c.id
		WHERE
			r.blogid = $blogid AND e.draft = 0 AND r.parent IS NULL".(doesHaveOwnership() ? "" : " AND e.visibility >= 2").getPrivateCategoryExclusionQuery($blogid)."
			AND r.entry > 0 AND r.isfiltered = 0
		ORDER BY
			r.written DESC";
	list($comments, $paging) = Paging::fetch($sql, $page, $count, $url, $prefix, $countItem);
	$paging['postfix'] = $postfix;
	$comments = coverComments($comments);
	return array($comments, $paging);
}
function getCommentsWithPagingForGuestbook($blogid, $page, $count) {
	return getCommentsWithPagingByEntryId($blogid, 0, $page, $count, null, '?page=','',null, 'DESC');
}

function getCommentAttributes($blogid, $id, $attributeNames) {
	global $database;
	return POD::queryRow("SELECT $attributeNames FROM {$database['prefix']}Comments WHERE blogid = $blogid AND id = $id");
}

function getComments($entry,$order = 'ASC') {
	global $database;
	$comments = array();
	$aux = ($entry == 0 ? 'ORDER BY written DESC' : 'ORDER BY id '.($order == 'DESC' ? 'DESC' : 'ASC'));
	$sql = "SELECT *
		FROM {$database['prefix']}Comments
		WHERE blogid = ".getBlogId()."
			AND entry = $entry
			AND parent IS NULL
			AND isfiltered = 0 $aux";
	if ($result = POD::queryAll($sql)) {
		$comments = coverComments($result);
	}
	return $comments;
}

function coverComments($comments) {
	$result = array();

	foreach ($comments as $comment) {
		if (($comment['secret'] == 1) && !canReadSecretComment($comment['entry'], $comment)) {	// Textcube-NG: 댓글마다 판정(r3-7, 결정 447)
			if( !doesHaveOpenIDPriv($comment) ) {
				$comment['name'] = '';
				$comment['homepage'] = '';
				$comment['comment'] = _text('관리자만 볼 수 있는 댓글입니다.');
			}
		}
		if(!empty($comment['replier'])) {
			$comment['homepage'] = User::getHomepage($comment['replier']);
		}
		array_push($result, $comment);
	}
	return $result;
}

function getCommentComments($parent,$parentComment=null) {
	global $database;
	$comments = array();
	// Textcube-NG: 비밀 댓글 열람은 아래에서 댓글마다 canReadSecretComment()(r3-7, 결정 447)
	if ($result = POD::queryAll("SELECT *
		FROM {$database['prefix']}Comments
		WHERE blogid = ".getBlogId()."
			AND parent = $parent
			AND isfiltered = 0
		ORDER BY written")) {
		if( $parentComment == null ) {
			$parentComment = POD::queryRow(
				"SELECT * FROM {$database['prefix']}Comments ".
				"  WHERE blogid = ".getBlogId()." AND id = $parent" );
		}
		$parentByOpenid = !empty( $parentComment['openid'] );
		foreach ($result as $comment) {
			if (($comment['secret'] == 1) && !canReadSecretComment($comment['entry'], $comment)) {
				if( !doesHaveOpenIDPriv($comment) ) {
					$comment['name'] = '';
					$comment['homepage'] = '';
					$comment['comment'] =
						$parentByOpenid ?
							_text('비밀글의 작성자만 읽을 수 있는 댓글입니다.') :
							_text('관리자만 볼 수 있는 댓글입니다.');
				}
			}
			if(!empty($comment['replier'])) {
				$comment['homepage'] = User::getHomepage($comment['replier']);
			}
			array_push($comments, $comment);
		}
	}
	return $comments;
}

function isCommentWriter($blogid, $commentid) {
	global $database;
	if (!doesHaveMembership())
		return false;
	return POD::queryExistence("SELECT replier
		FROM {$database['prefix']}Comments
		WHERE blogid = $blogid
			AND id = $commentid
			AND replier = " . getUserId());
}

/* [보안] OpenID 댓글은 공개 상수 OPENID_PASSWORD 를 비밀번호(md5)로 저장해 왔다 → 그 값은 손님 비밀번호 확인에 쓰지 않는다
   (기존 OpenID 댓글 보호). OpenID 댓글은 OpenID 신원 일치 또는 블로그 소유자만 수정·삭제 */
function isOpenIDCommentPassword($password) {
	return defined('OPENID_PASSWORD') && is_scalar($password) && (string)$password === (string)OPENID_PASSWORD;
}

function getComment($blogid, $id, $password, $restriction = true) {
	global $database;
	$sql = "SELECT *
		FROM {$database['prefix']}Comments
		WHERE blogid = $blogid
			AND id = $id";
	if($restriction == true) {
		if (!doesHaveOwnership()) {
			if (doesHaveMembership())
				$sql .= ' AND replier = ' . getUserId();
			else if (isOpenIDCommentPassword($password))
				return false;	// [보안] OpenID 댓글 공용 비밀번호 거부
			else
				$sql .= ' AND password = \'' . md5($password) . '\'';
		}
	}
	if ($result = POD::queryRow($sql)) {
		if($restriction != true) $result['password'] = null; // scope.
		return $result;
	}
	return false;
}

function getCommentList($blogid, $search) {
	global $database;
	$list = array('title' => "$search", 'items' => array());
	$search = escapeSearchString($search);
	$authorized = doesHaveOwnership() ? '' : 'AND c.secret = 0 '.getPrivateCategoryExclusionQuery($blogid);
	// Textcube-NG: 원본은 방문자 검색에도 비공개 글의 댓글이 나왔다 — 방문자는 공개 글, 글관리 권한(group.editors)이 없는 구성원은
	// 자기 글의 댓글만(getRecentComments 의 방문자 조건·작성자 조건과 같음)(v3.145)
	if (!doesHaveOwnership()) $authorized .= ' AND e.visibility >= 2';
	else if (!Acl::check('group.editors')) $authorized .= ' AND e.userid = '.getUserId();
	if ($result = POD::queryAll("SELECT c.id, c.entry, c.parent, c.name, c.comment, c.written, e.slogan
		FROM {$database['prefix']}Comments c
		INNER JOIN {$database['prefix']}Entries e ON c.entry = e.id AND c.blogid = e.blogid AND e.draft = 0
		WHERE c.entry > 0
			AND c.blogid = $blogid $authorized
			AND c.isfiltered = 0
			AND (c.comment like '%$search%' OR c.name like '%$search%')
		ORDER BY c.written")) {
		foreach ($result as $comment)
			array_push($list['items'], $comment);
	}
	return $list;
}

function updateCommentsOfEntry($blogid, $entryId) {
	global $database;
	requireComponent('Needlworks.Cache.PageCache');
	$commentCount = POD::queryCell("SELECT COUNT(*)
		FROM {$database['prefix']}Comments
		WHERE blogid = $blogid
			AND entry = $entryId
			AND isfiltered = 0");
	POD::query("UPDATE {$database['prefix']}Entries
		SET comments = $commentCount
		WHERE blogid = $blogid
			AND id = $entryId");
	if($entryId >=0) CacheControl::flushEntry($entryId);
	return $commentCount;
}

function sendCommentPing($entryId, $permalink, $name, $homepage) {
	return true;
	global $database, $blog;
	$blogid = getBlogId();
	if($slogan = POD::queryCell("SELECT slogan
		FROM {$database['prefix']}Entries
		WHERE blogid = $blogid
			AND id = $entryId
			AND draft = 0
			AND visibility = 3 
			AND acceptcomment = 1")) {
		$rpc = new XMLRPC();
		$rpc->url = TEXTCUBE_SYNC_URL;
		$summary = array(
			'permalink' => $permalink,
			'name' => $name,
			'homepage' => $homepage
		);
		$rpc->async = true;
		$rpc->call('sync.comment', $summary);
	}
}

/// 댓글 오픈아이디 전용 모드(블로그 설정 AddCommentMode == 'openid')에서 지금 방문자가 막히는지(v3.153).
/// 서버 판정(addComment)과 스킨 표시(view.php getCommentView 의 *_input_form_openid_only)가 같은 함수를 쓴다.
/// 통과 신원은 서버가 검증해 세션에 둔 OIDC 신원('oidc:…', login/openid 콜백)과 이식본 소셜 로그인 신원({공급자}:{id}, login/social 콜백)뿐이다
/// (둘 다 OIDCClient::establishGuestIdentityWith 한 곳에서만 설정). 이름·홈페이지·openid_identifier 같은 방문자 입력값으로는 통과하지 않는다.
function isCommentOpenIDOnlyBlocked() {
	if (doesHaveOwnership() || Acl::check('group.writers')) return false;
	if (Setting::getBlogSettingGlobal('AddCommentMode', '') != 'openid') return false;
	$kind = OpenID::identityKind(Acl::getIdentity('openid'));
	return !($kind === 'oidc' || $kind === 'social');
}

function addComment($blogid, & $comment) {
	global $database, $user, $blog, $defaultURL;

	$openid = Acl::getIdentity('openid');
	$filtered = 0;
	$blockType = ''; // Textcube-NG: 모든 경로에서 정의되도록 초기값(r3-8)

	if (!doesHaveOwnership()) {
		if (!Filter::isAllowed($comment['homepage'])) {
			if (Filter::isFiltered('ip', $comment['ip'])) {
				$blockType = "ip";
				$filtered = 1;
			} else if (Filter::isFiltered('name', $comment['name'])) {
				$blockType = "name";
				$filtered = 1;
			} else if (Filter::isFiltered('url', $comment['homepage'])) {
				$blockType = "homepage";
				$filtered = 1;
			} elseif (Filter::isFiltered('content', $comment['comment'])) {
				$blockType = "comment";
				$filtered = 1;
			} elseif (isCommentOpenIDOnlyBlocked()) {
				$blockType = "openidonly";
				$filtered = 1;
			} else if (!fireEvent('AddingComment', true, $comment)) {
				$blockType = "etc";
				$filtered = 1;
			}
		} else if (isCommentOpenIDOnlyBlocked()) {
			// [보안] 화이트리스트(Filter::isAllowed — 방문자가 입력한 홈페이지가 허용 패턴을 포함하는지만 본다)는 스팸 필터만 건너뛴다.
			// 오픈아이디 전용 모드는 신원 확인이므로 홈페이지 입력으로 건너뛰지 않는다(필명 사칭 방지, v3.153).
			$blockType = "openidonly";
			$filtered = 1;
		}
	}

	$comment['homepage'] = stripHTML($comment['homepage']);
	if (preg_match('/^\s*javascript\s*:/i', $comment['homepage'])) $comment['homepage'] = '';
	$comment['name'] = UTF8::lessenAsEncoding($comment['name'], 80);
	$comment['homepage'] = UTF8::lessenAsEncoding($comment['homepage'], 80);
	$comment['comment'] = UTF8::lessenAsEncoding($comment['comment'], 65535);

	if (!doesHaveOwnership() && $comment['entry'] != 0) {
		$result = POD::queryCount("SELECT *
			FROM {$database['prefix']}Entries
			WHERE blogid = $blogid
				AND id = {$comment['entry']}
				AND draft = 0
				AND visibility > 0
				AND acceptcomment = 1");
		if (!$result || $result == 0)
			return false;
	}
	$parent = $comment['parent'] == null ? 'null' : $comment['parent'];
	if ($user !== null) {
		$comment['replier'] = getUserId();
		$name = POD::escapeString($user['name']);
		$password = '';
		$homepage = POD::escapeString($user['homepage']);
		// 회원 홈페이지가 없으면 신원 식별자가 주소(과거 OpenID 2.0)일 때만 대신 쓴다 — 'oidc:…'·'kakao:…' 를 홈페이지로 저장하지 않는다(v3.153).
		if( empty($homepage) && OpenID::identityKind($openid) === 'url' ) { $homepage = POD::escapeString($openid); }
	} else {
		$comment['replier'] = 'null';
		$name = POD::escapeString($comment['name']);
		$password = empty($comment['password']) ? '' : md5($comment['password']);
		$homepage = POD::escapeString($comment['homepage']);
	}
	$comment0 = POD::escapeString($comment['comment']);
	$filteredAux = ($filtered == 1 ? "UNIX_TIMESTAMP()" : 0);
	$insertId = getCommentsMaxId() + 1;
	$result = POD::query("INSERT INTO {$database['prefix']}Comments
		(blogid,replier,id,openid,entry,parent,name,password,homepage,secret,comment,ip,written,isfiltered)
		VALUES (
			$blogid,
			{$comment['replier']},
			$insertId,
			'$openid',
			{$comment['entry']},
			$parent,
			'$name',
			'$password',
			'$homepage',
			{$comment['secret']},
			'$comment0',
			'{$comment['ip']}',
			UNIX_TIMESTAMP(),
			$filteredAux
		)");
	if ($result) {
		$id = $insertId;
		if($filtered != 1) {
			CacheControl::flushCommentRSS($comment['entry']);
			CacheControl::flushDBCache('comment');
			if ($parent != 'null' && $comment['secret'] < 1) {
				$insertId = getCommentsNotifiedQueueMaxId() + 1;
				POD::execute("INSERT INTO {$database['prefix']}CommentsNotifiedQueue
						( blogid , id, commentid , sendstatus , checkdate , written )
					VALUES
						('".$blogid."' , '".$insertId."', '" . $id . "', '0', '0', UNIX_TIMESTAMP())");
			}
			updateCommentsOfEntry($blogid, $comment['entry']);
			fireEvent($comment['entry'] ? 'AddComment' : 'AddGuestComment', $id, $comment);
			return $id;
		} else {
			return $blockType;
		}
	}
	return false;
}

function updateComment($blogid, $comment, $password) {
	global $database, $user;

	$openid = Acl::getIdentity('openid');
	if (!doesHaveOwnership()) {
		// if filtered, only block and not send to trash
		if (!Filter::isAllowed($comment['homepage'])) {
			if (Filter::isFiltered('ip', $comment['ip']))
				return 'blocked';
			if (Filter::isFiltered('name', $comment['name']))
				return 'blocked';
			if (Filter::isFiltered('url', $comment['homepage']))
				return 'blocked';
			if (Filter::isFiltered('content', $comment['comment']))
				return 'blocked';
			if (!fireEvent('ModifyingComment', true, $comment))
				return 'blocked';
		}
	}

	$comment['homepage'] = stripHTML($comment['homepage']);
	if (preg_match('/^\s*javascript\s*:/i', $comment['homepage'])) $comment['homepage'] = '';
	$comment['name'] = UTF8::lessenAsEncoding($comment['name'], 80);
	$comment['homepage'] = UTF8::lessenAsEncoding($comment['homepage'], 80);
	$comment['comment'] = UTF8::lessenAsEncoding($comment['comment'], 65535);

	$setPassword = '';
	if ($user !== null) {
		$comment['replier'] = getUserId();
		$name = POD::escapeString($user['name']);
		$setPassword = 'password = \'\',';
		$homepage = POD::escapeString($user['homepage']);
		// 회원 홈페이지가 없으면 신원 식별자가 주소(과거 OpenID 2.0)일 때만 대신 쓴다 — 'oidc:…'·'kakao:…' 를 홈페이지로 저장하지 않는다(v3.153).
		if( empty($homepage) && OpenID::identityKind($openid) === 'url' ) { $homepage = POD::escapeString($openid); }
	} else {
		$name = POD::escapeString($comment['name']);
		if ($comment['password'] !== true)
			$setPassword = 'password = \'' . (empty($comment['password']) ? '' : md5($comment['password'])) . '\', ';
		$homepage = POD::escapeString($comment['homepage']);
	}
	$comment0 = POD::escapeString($comment['comment']);

	$guestcomment = false;
	if (POD::queryExistence("SELECT *
		FROM {$database['prefix']}Comments
		WHERE blogid = $blogid
			AND id = {$comment['id']}
			AND replier IS NULL")) {
		$guestcomment = true;
	}

	$wherePassword = '';
	if (!doesHaveOwnership()) {
		if ($guestcomment == false) {
			if (!doesHaveMembership())
				return false;
			$wherePassword = ' AND replier = ' . getUserId();
		}
		else
		{
			if( empty($password) && $openid ) {
				// [보안] 신원 문자열을 escape 없이 SQL 에 넣었다 → escape
				$wherePassword = ' AND openid = \'' . POD::escapeString($openid) . '\'';
			} else if (isOpenIDCommentPassword($password)) {
				return false;	// [보안] OpenID 댓글 공용 비밀번호 거부
			} else {
				$wherePassword = ' AND password = \'' . md5($password) . '\'';
			}
		}
	}

	$replier = is_null($comment['replier']) ? 'NULL' : "'{$comment['replier']}'";

	$result = POD::query("UPDATE {$database['prefix']}Comments
				SET
					name = '$name',
					$setPassword
					homepage = '$homepage',
					secret = {$comment['secret']},
					comment = '$comment0',
					ip = '{$comment['ip']}',
					written = UNIX_TIMESTAMP(),
					isfiltered = {$comment['isfiltered']},
					replier = {$replier}
				WHERE blogid = $blogid
					AND id = {$comment['id']} $wherePassword");
	if($result) {
		CacheControl::flushCommentRSS($comment['entry']); // Assume blogid = current blogid.
		CacheControl::flushDBCache('comment');
		return true;
	} else return false;
}

function deleteComment($blogid, $id, $entry, $password) {
	global $database;

	if (!is_numeric($id)) return false;
	if (!is_numeric($entry)) return false;

	$guestcomment = false;
	if (POD::queryExistence("SELECT * FROM {$database['prefix']}Comments WHERE blogid = $blogid AND id = $id AND replier IS NULL")) {
		$guestcomment = true;
	}

	$wherePassword = '';

	$sql = "DELETE FROM {$database['prefix']}Comments
		WHERE blogid = $blogid
			AND id = $id
			AND entry = $entry";
	if (!doesHaveOwnership()) {
		if( Acl::getIdentity('openid') && empty($password) ) {
			// [보안] 신원 문자열을 escape 없이 SQL 에 넣었다 → escape
			$wherePassword = ' AND openid = \'' . POD::escapeString(Acl::getIdentity('openid')) . '\'';
		} else {
			if ($guestcomment == false) {
				if (!doesHaveMembership()) {
					return false;
				}
				$wherePassword = ' AND replier = ' . getUserId();
			}
			else
			{
				if (isOpenIDCommentPassword($password))
					return false;	// [보안] OpenID 댓글 공용 비밀번호 거부
				$wherePassword = ' AND password = \'' . md5($password) . '\'';
			}
		}
	}
	if(POD::queryCount($sql . $wherePassword)) {
		CacheControl::flushCommentRSS($entry);
		CacheControl::flushDBCache('comment');
		updateCommentsOfEntry($blogid, $entry);
		return true;
	}
	return false;
}

function trashComment($blogid, $id, $entry, $password) {
	global $database;
	if (!doesHaveOwnership()) {
		return false;
	}
	if (!is_numeric($id)) return false;
	if (!is_numeric($entry)) return false;
	$sql = "UPDATE {$database['prefix']}Comments
		SET isfiltered = UNIX_TIMESTAMP()
		WHERE blogid = $blogid
			AND id = $id
			AND entry = $entry";
	$affected = POD::queryCount($sql);
	$sql = "UPDATE {$database['prefix']}Comments
		SET isfiltered = UNIX_TIMESTAMP()
		WHERE blogid = $blogid
			AND parent = $id
			AND entry = $entry";
	$affectedChildren = POD::queryCount($sql);
	if ($affected + $affectedChildren > 0) {
		CacheControl::flushCommentRSS($entry);
		CacheControl::flushDBCache('comment');
		updateCommentsOfEntry($blogid, $entry);
		return true;
	}
	return false;
}

function revertComment($blogid, $id, $entry, $password) {
	// not used, so
	return false;
	global $database;
	if (!doesHaveOwnership()) {
		return false;
	}
	if (!is_numeric($id)) return false;
	if (!is_numeric($entry)) return false;
	$sql = "UPDATE {$database['prefix']}Comments
		SET isfiltered = 0
		WHERE blogid = $blogid
			AND id = $id
			AND entry = $entry";
	if(POD::query($sql)) {
		CacheControl::flushCommentRSS($entry);
		CacheControl::flushDBCache('comment');
		updateCommentsOfEntry($blogid, $entry);
		return true;
	}
	return false;
}

function getRecentComments($blogid,$count = false,$isGuestbook = false, $guestShip = false) {
	global $skinSetting, $database;
	$comments = array();
	if(!$isGuestbook && !Acl::check("group.editors")) $userLimit = ' AND e.userid = '.getUserId();
	else $userLimit = '';
	$sql = (doesHaveOwnership() && !$guestShip) ? "SELECT r.*, e.title, e.slogan
		FROM
			{$database['prefix']}Comments r
			INNER JOIN {$database['prefix']}Entries e ON r.blogid = e.blogid AND r.entry = e.id AND e.draft = 0$userLimit
		WHERE
			r.blogid = $blogid".($isGuestbook != false ? " AND r.entry=0" : " AND r.entry>0")." AND r.isfiltered = 0
		ORDER BY
			r.written
		DESC LIMIT ".($count != false ? $count : $skinSetting['commentsOnRecent']) :
		"SELECT r.*, e.title, e.slogan
		FROM
			{$database['prefix']}Comments r
			INNER JOIN {$database['prefix']}Entries e ON r.blogid = e.blogid AND r.entry = e.id AND e.draft = 0
			LEFT OUTER JOIN {$database['prefix']}Categories c ON e.blogid = c.blogid AND e.category = c.id
		WHERE
			r.blogid = $blogid AND e.draft = 0 AND e.visibility >= 2".getPrivateCategoryExclusionQuery($blogid)
			.($isGuestbook != false ? " AND r.entry = 0" : " AND r.entry > 0")." AND r.isfiltered = 0
		ORDER BY
			r.written
		DESC LIMIT ".($count != false ? $count : $skinSetting['commentsOnRecent']);
	if ($result = POD::queryAllWithDBCache($sql,'comment')) {
		foreach($result as $comment) {
			if (($comment['secret'] == 1) && !canReadSecretComment($comment['entry'], $comment)) {	// Textcube-NG: 비밀 댓글 열람(r3-7, 결정 447)
				if( !doesHaveOpenIDPriv($comment) ) {
					$comment['name'] = _text('비밀방문자');
					$comment['homepage'] = '';
					$comment['comment'] = _text('관리자만 볼 수 있는 댓글입니다.');
				}
			}
			array_push($comments, $comment);
		}
	}
	return $comments;
}

function getRecentGuestbook($blogid,$count = false) {
	global $skinSetting, $database;
	$comments = array();
	$sql = "SELECT r.*
		FROM
			{$database['prefix']}Comments r
		WHERE
			r.blogid = $blogid AND r.entry = 0 AND r.isfiltered = 0
		ORDER BY
			r.written
		DESC LIMIT ".($count != false ? $count : $skinSetting['commentsOnRecent']);

	if ($result = POD::queryAll($sql)) {
		foreach($result as $comment) {
			if (($comment['secret'] == 1) && !canReadSecretComment(0, $comment)) {	// Textcube-NG: 방명록 비밀 글은 editors 이상·본인·회원 글의 답글(r3-7, 결정 447)
				if( !doesHaveOpenIDPriv($comment) ) {
					$comment['name'] = '';
					$comment['homepage'] = '';
					$comment['comment'] = _text('관리자만 볼 수 있는 댓글입니다.');
				}
			}
			array_push($comments, $comment);
		}
	}
	return $comments;
}
function getGuestbookPageById($blogid, $id) {
	return getCommentPageById($blogid, 0, $id);
}

function getCommentPageById($blogid, $entryId, $commentId) {
	global $database, $skinSetting;
	$totalGuestbookId = POD::queryColumn("SELECT id
		FROM {$database['prefix']}Comments
		WHERE
			blogid = $blogid AND entry = $entryId AND isfiltered = 0 AND parent is null
		ORDER BY
			written DESC");
	$order = array_search($commentId, $totalGuestbookId);
	if($order == false) {
		$parentCommentId = POD::queryCell("SELECT parent
			FROM {$database['prefix']}Comments
			WHERE
				blogid = $blogid AND entry = $entryId AND isfiltered = 0 AND id = $commentId");
		if($parentCommentId != false) {
			$order = array_search($parentCommentId, $totalGuestbookId);
		} else {
			return false;
		}
	}
	$base = ($entryId == 0 ? $skinSetting['commentsOnGuestbook'] : $skinSetting['commentsOnEntry']);
	return intval($order / $base)+1;
}

function deleteCommentInOwner($blogid, $id) {
	global $database;
	if (!is_numeric($id)) return false;
	$entryId = POD::queryCell("SELECT entry FROM {$database['prefix']}Comments WHERE blogid = $blogid AND id = $id");
	if(POD::queryCount("DELETE FROM {$database['prefix']}Comments WHERE blogid = $blogid AND id = $id") == 1) {
		if (POD::query("DELETE FROM {$database['prefix']}Comments WHERE blogid = $blogid AND parent = $id")) {
			CacheControl::flushCommentRSS($entryId);
			updateCommentsOfEntry($blogid, $entryId);
			return true;
		}
	}
	return false;
}

/* Textcube-NG: $deferredEntries 에 배열을 넘기면 캐시 비우기·댓글 수 갱신을 하지 않고 옮긴 댓글의 글 번호만 모은다(r3-9, 503-16).
   모은 뒤 flushTrashedCommentsInOwner() 로 글마다 한 번만 비운다. 넘기지 않으면(인자 2개) 원본과 같다. */
function trashCommentInOwner($blogid, $id, &$deferredEntries = null) {
	global $database;
	if (!is_numeric($id)) return false;
	$entryId = POD::queryCell("SELECT entry FROM {$database['prefix']}Comments WHERE blogid = $blogid AND id = $id");
//	$result = POD::queryCount("UPDATE {$database['prefix']}Comments SET isfiltered = UNIX_TIMESTAMP() WHERE blogid = $blogid AND id = $id");
//	if ($result && $result == 1) {
	if(POD::query("UPDATE {$database['prefix']}Comments SET isfiltered = UNIX_TIMESTAMP() WHERE blogid = $blogid AND id = $id")) {
		if (POD::query("UPDATE {$database['prefix']}Comments SET isfiltered = UNIX_TIMESTAMP() WHERE blogid = $blogid AND parent = $id")) {
			if (is_array($deferredEntries)) {
				$deferredEntries[(string)$entryId] = $entryId;	// Textcube-NG: 원래 값(없는 댓글이면 null)을 그대로 넘긴다(r3-9, 503-16)
				return true;
			}
			CacheControl::flushCommentRSS($entryId);
			CacheControl::flushDBCache('comment');
			updateCommentsOfEntry($blogid, $entryId);
			return true;
		}
	}
	return false;
}

/* Textcube-NG: trashCommentInOwner($blogid, $id, $deferredEntries) 로 모은 글마다 캐시 비우기·댓글 수 갱신을 한 번씩 한다(r3-9, 503-16).
   원본은 댓글마다 flushCommentRSS·flushDBCache('comment')·updateCommentsOfEntry(→ CacheControl::flushEntry)를 불러
   같은 글의 캐시를 댓글 수만큼 비웠다. 비우는 캐시·댓글 수 결과는 같다.
   trashCommentInOwnerByIP()·interface/owner/communication/comment/delete·interface/owner/data/correct 가 쓴다. */
function flushTrashedCommentsInOwner($blogid, $deferredEntries) {
	if (empty($deferredEntries)) return true;
	CacheControl::flushDBCache('comment');
	foreach ($deferredEntries as $entryId) {
		CacheControl::flushCommentRSS($entryId);
		updateCommentsOfEntry($blogid, $entryId);
	}
	return true;
}

function trashCommentInOwnerByIP($blogid, $ip) {
	global $database;
	$ids = POD::queryColumn("SELECT id FROM {$database['prefix']}Comments WHERE blogid = $blogid AND ip = '".POD::escapeString($ip)."'");
	$entries = array();	// Textcube-NG: 댓글을 모두 옮긴 뒤 글마다 한 번 비운다(r3-9, 503-16)
	foreach ($ids as $id) {
		trashCommentInOwner($blogid, $id, $entries);
	}
	flushTrashedCommentsInOwner($blogid, $entries);
	return true;
}

function revertCommentInOwner($blogid, $id) {
	global $database;
	if (!is_numeric($id)) return false;
	$entryId = POD::queryCell("SELECT entry FROM {$database['prefix']}Comments WHERE blogid = $blogid AND id = $id");
	$parent = POD::queryCell("SELECT parent FROM {$database['prefix']}Comments WHERE blogid = $blogid AND id = $id");
	if(POD::queryCount("UPDATE {$database['prefix']}Comments SET isfiltered = 0 WHERE blogid = $blogid AND id = $id") == 1) {
		if (is_null($parent) || POD::query("UPDATE {$database['prefix']}Comments SET isfiltered = 0 WHERE blogid = $blogid AND id = $parent")) {
			CacheControl::flushCommentRSS($entryId);
			updateCommentsOfEntry($blogid, $entryId);
			return true;
		}
	}
	return false;
}

function deleteCommentNotifiedInOwner($blogid, $id) {
	global $database;
	if (!is_numeric($id)) return false;

	fireEvent('DeleteCommentNotified', $id);

	$entryId = POD::queryCell("SELECT entry FROM {$database['prefix']}CommentsNotified WHERE blogid = $blogid AND id = $id");
	if(POD::queryCount("DELETE FROM {$database['prefix']}CommentsNotified WHERE blogid = $blogid AND id = $id") == 1) {
		if (POD::query("DELETE FROM {$database['prefix']}CommentsNotified WHERE blogid = $blogid AND parent = $id")) {
			updateCommentsOfEntry($blogid, $entryId);
			CacheControl::flushCommentNotifyRSS();
			return true;
		}
	}
	return false;
}

function notifyComment() {
	global $database, $service, $blog, $defaultURL;
	$blogid = getBlogId();
	// Textcube-NG: 큐를 블로그 구분 없이 꺼내(큐·댓글 id 는 블로그별) 다른 블로그 방문이 남의 알림을 엉뚱한 댓글로 보내거나 지웠고,
	// DELETE 가 id 만 비교해 같은 id 의 다른 블로그 행까지 지웠다. 현재 블로그 행만 처리(v3.156)
	$sql = "SELECT
				CN.*,
				CNQ.id AS queueId,
				CNQ.commentid AS commentid,
				CNQ.sendstatus AS sendstatus,
				CNQ.checkdate AS checkdate,
				CNQ.written  AS queueWritten
			FROM
				{$database['prefix']}CommentsNotifiedQueue AS CNQ
			LEFT JOIN
				{$database['prefix']}Comments AS CN ON CNQ.commentid = CN.id AND CN.blogid = CNQ.blogid
			WHERE
				CNQ.blogid = $blogid
				AND CNQ.sendstatus = 0
				and CN.parent is not null
			ORDER BY CNQ.id ASC LIMIT 1 OFFSET 0";
	$queue = POD::queryRow($sql);
	if (empty($queue) && empty($queue['queueId'])) {
		return false;
	}
	$comments = (POD::queryRow("SELECT * FROM {$database['prefix']}Comments WHERE blogid = $blogid AND id = {$queue['commentid']}"));
	if (empty($comments['parent']) || $comments['secret'] == 1) {
		POD::execute("DELETE FROM {$database['prefix']}CommentsNotifiedQueue WHERE blogid = $blogid AND id={$queue['queueId']}");
		return false;
	}
	$parentComments = (POD::queryRow("SELECT * FROM {$database['prefix']}Comments WHERE blogid = $blogid AND id = {$comments['parent']}"));
	if (empty($parentComments['homepage'])) {
		POD::execute("DELETE FROM {$database['prefix']}CommentsNotifiedQueue WHERE blogid = $blogid AND id={$queue['queueId']}");
		return false;
	}
	$entry = (POD::queryRow("SELECT * FROM {$database['prefix']}Entries WHERE blogid = $blogid AND id={$comments['entry']}"));
	if(is_null($entry)) {
		$r1_comment_check_url = rawurlencode("$defaultURL/guestbook/".$parentComments['id']."#guestbook".$parentComments['id']);
		$r2_comment_check_url = rawurlencode("$defaultURL/guestbook/".$comments['id']."#guestbook".$comments['id']);
		$entry['title'] = _textf('%1 블로그의 방명록',$blog['title']);
		$entryPermaLink = "$defaultURL/guestbook/";
		$entry['id'] = 0;
	} else {
		$r1_comment_check_url = rawurlencode("$defaultURL/" . ($blog['useSloganOnPost'] ? "entry/{$entry['slogan']}" : $entry['id']) . "#comment" . $parentComments['id']);
		$r2_comment_check_url = rawurlencode("$defaultURL/" . ($blog['useSloganOnPost'] ? "entry/{$entry['slogan']}" : $entry['id']) . "#comment" . $comments['id']);
		$entryPermaLink = "$defaultURL/" . ($blog['useSloganOnPost'] ? "entry/{$entry['slogan']}" : $entry['id']);
	}

	// PHP port(Textcube-NG): r3-10 R-282 보내는 홈페이지 값(r1_homepage·r2_homepage)에도 아래 요청 주소($homepage)와 같은 규칙으로 http:// 를 붙인다(https 는 그대로, 빈 값은 빈 값). 받는 쪽 __checkNotifiedCommentURL() 은 http·https 주소만 받아 example.com 같은 값이 빈 값이 되었다
	$r1_homepage = (string)$parentComments['homepage'];
	if ($r1_homepage !== '' && !preg_match('#^https?://#i', $r1_homepage)) $r1_homepage = 'http://' . $r1_homepage;
	$r2_homepage = (string)$comments['homepage'];
	if ($r2_homepage !== '' && !preg_match('#^https?://#i', $r2_homepage)) $r2_homepage = 'http://' . $r2_homepage;
	$data = "url=" . rawurlencode($defaultURL) . "&mode=fb" . "&s_home_title=" . rawurlencode($blog['title']) . "&s_post_title=" . rawurlencode($entry['title']) . "&s_name=" . rawurlencode($comments['name']) . "&s_no=" . rawurlencode($comments['entry']) . "&s_url=" . rawurlencode($entryPermaLink) . "&r1_name=" . rawurlencode($parentComments['name']) . "&r1_no=" . rawurlencode($parentComments['id']) . "&r1_pno=" . rawurlencode($comments['entry']) . "&r1_rno=0" . "&r1_homepage=" . rawurlencode($r1_homepage) . "&r1_regdate=" . rawurlencode($parentComments['written']) . "&r1_url=" . $r1_comment_check_url. "&r2_name=" . rawurlencode($comments['name']) . "&r2_no=" . rawurlencode($comments['id']) . "&r2_pno=" . rawurlencode($comments['entry']) . "&r2_rno=" . rawurlencode($comments['parent']) . "&r2_homepage=" . rawurlencode($r2_homepage) . "&r2_regdate=" . rawurlencode($comments['written']) . "&r2_url=" . $r2_comment_check_url . "&r1_body=" . rawurlencode($parentComments['comment']) . "&r2_body=" . rawurlencode($comments['comment']);
	if (!preg_match('#^https?://#i', (string)$parentComments['homepage'])) { // Textcube-NG: https 홈페이지 앞에 http://를 덧붙이지 않음(r3-8)
		$homepage = 'http://' . $parentComments['homepage'];
	} else {
		$homepage = $parentComments['homepage'];
	}
	// Textcube-NG: 알림 주소는 방문자가 댓글에 적은 홈페이지라 서버 안쪽(루프백·사설망·링크로컬)·80/443 외 포트로 POST 할 수 있었다
	// (블로그 첫 화면 방문마다 실행). 공인 주소만(v3.79 HTTPRequest::$publicOnly)(v3.156)
	$request = new HTTPRequest('POST', $homepage);
	$request->publicOnly = true;
	$request->contentType = 'application/x-www-form-urlencoded; charset=utf-8';
	$request->content = $data;
	if ($request->send()) {
		$xmls = new XMLStruct();
		if ($xmls->open($request->responseText)) {
			$result = $xmls->selectNode('/response/error/');
			if ($result['.value'] != '1' && $result['.value'] != '0') {
				$homepage = rtrim($homepage, '/') . '/index.php';
				$request = new HTTPRequest('POST', $homepage);
				$request->publicOnly = true;	// Textcube-NG: 같은 이유로 공인 주소만(v3.156)
				$request->contentType = 'application/x-www-form-urlencoded; charset=utf-8';
				$request->content = $data;
				if ($request->send()) {
				}
			}
		}
	} else {
	}
	POD::execute("DELETE FROM {$database['prefix']}CommentsNotifiedQueue WHERE blogid = $blogid AND id={$queue['queueId']}");
}

/* Textcube-NG: 댓글 알리미로 받은 주소 값 검사(r3-3). 빈 값은 '', http·https 주소는 그대로, 그 밖(javascript: 등·공백·따옴표·꺾쇠가 든 값)은 false */
function __checkNotifiedCommentURL($url) {
	if (!is_string($url)) return false;
	if ($url === '') return '';
	return preg_match('~^https?://[^/?#\s"\'<>\\\\`\x00-\x1F\x7F]+(?:[/?#][^\s"\'<>\\\\`\x00-\x1F\x7F]*)?$~iD', $url) ? $url : false;
}

function receiveNotifiedComment($post) {
	if (empty($post['mode']) || $post['mode'] != 'fb')
		return 1;
	global $database;

	// Textcube-NG: 관리자가 댓글 알리미 받기를 끄면(블로그 설정 acceptNotifiedComments = 0, 기본 1) 받지 않는다(r3-3)
	if (!Setting::getBlogSettingGlobal('acceptNotifiedComments', 1)) return 8;
	CacheControl::flushCommentNotifyRSS();
	$post = fireEvent('ReceiveNotifiedComment', $post);
	if ($post === false) return 7;
	// Textcube-NG: 주소 값은 http·https 만 받는다. 블로그·글·댓글 주소가 아니면 거부(9), 작성자 홈페이지가 아니면 빈 값으로 둔다(r3-3)
	foreach (array('url', 's_url', 'r1_url', 'r2_url') as $key) {
		$checked = __checkNotifiedCommentURL(isset($post[$key]) ? $post[$key] : '');
		if ($checked === false) return 9;
		$post[$key] = $checked;
	}
	foreach (array('r1_homepage', 'r2_homepage') as $key) {
		$homepageValue = isset($post[$key]) ? $post[$key] : '';
		// PHP port(Textcube-NG): r3-10 R-282 예전 판·다른 블로그가 보낸 스킴 없는 홈페이지(example.com, example.com:8080/path 등)는 앞에 http:// 를 붙여 검사한다. 호스트 문자만으로 시작하는 값만 해당(javascript: 등은 그대로 검사에서 빈 값)
		if (is_string($homepageValue) && preg_match('~^[a-z0-9.\-]+(?::\d+)?(?:[/?#]|$)~iD', $homepageValue)) $homepageValue = 'http://' . $homepageValue;
		$checked = __checkNotifiedCommentURL($homepageValue);
		$post[$key] = ($checked === false) ? '' : $checked;
	}

	$blogid = getBlogId();
	$title = POD::escapeString(UTF8::lessenAsEncoding($post['s_home_title'], 255));
	$name = POD::escapeString(UTF8::lessenAsEncoding($post['s_name'], 255));
	// Textcube-NG: 숫자 필드는 정수로, 글 주소·제목은 길이 제한 뒤 escape(r3-3)
	$entryId = (int)$post['s_no'];
	$homepage = POD::escapeString(UTF8::lessenAsEncoding($post['url'], 255));
	$entryurl = POD::escapeString(UTF8::lessenAsEncoding($post['s_url'], 255));
	$entrytitle = POD::escapeString(UTF8::lessenAsEncoding($post['s_post_title'], 255));
	$parent_id = (int)$post['r1_no'];
	$parent_name = POD::escapeString(UTF8::lessenAsEncoding($post['r1_name'], 80));
	$parent_parent = (int)$post['r1_rno'];
	$parent_homepage = POD::escapeString(UTF8::lessenAsEncoding($post['r1_homepage'], 80));
	$parent_written = (int)$post['r1_regdate'];
	$parent_comment = POD::escapeString($post['r1_body']);
	$parent_url = POD::escapeString(UTF8::lessenAsEncoding($post['r1_url'], 255));
	$child_id = (int)$post['r2_no'];
	$child_name = POD::escapeString(UTF8::lessenAsEncoding($post['r2_name'], 80));
	$child_parent = (int)$post['r2_rno'];
	$child_homepage = POD::escapeString(UTF8::lessenAsEncoding($post['r2_homepage'], 80));
	$child_written = (int)$post['r2_regdate'];
	$child_comment = POD::escapeString($post['r2_body']);
	$child_url = POD::escapeString(UTF8::lessenAsEncoding($post['r2_url'],255));
	$siteid = POD::queryCell("SELECT id FROM {$database['prefix']}CommentsNotifiedSiteInfo WHERE url = '$homepage'");
	if (empty($siteid)) {
		$insertId = getCommentsNotifiedSiteInfoMaxId() + 1;
		if (POD::execute("INSERT INTO {$database['prefix']}CommentsNotifiedSiteInfo
			( id, title, name, url, modified)
			VALUES ($insertId, '$title', '$name', '$homepage', UNIX_TIMESTAMP());"))
			$siteid = $insertId;
		else
			return 2;
	}
	$parentId = POD::queryCell("SELECT id
		FROM {$database['prefix']}CommentsNotified
		WHERE entry = $entryId
			AND siteid = $siteid
			AND blogid = $blogid
			AND remoteid = $parent_id");
	if (empty($parentId)) {
		$insertId = getCommentsNotifiedMaxId() + 1;
		$sql = "INSERT INTO {$database['prefix']}CommentsNotified
			( blogid , replier , id , entry , parent , name , password , homepage , secret , comment , ip , written, modified , siteid , isnew , url , remoteid ,entrytitle , entryurl )
			VALUES (
				$blogid, NULL , $insertId, " . $entryId . ", " . (empty($parent_parent) ? 'null' : $parent_parent) . ", '" . $parent_name . "', '', '" . $parent_homepage . "', '', '" . $parent_comment . "', '', " . $parent_written . ",UNIX_TIMESTAMP(), " . $siteid . ", 1, '" . $parent_url . "'," . $parent_id . ", '" . $entrytitle . "', '" . $entryurl . "'
)";
		if (!POD::execute($sql))
			return 3;
		$parentId = $insertId;
	}
	if (POD::queryCell("SELECT count(*) FROM {$database['prefix']}CommentsNotified WHERE siteid=$siteid AND remoteid=$child_id") > 0)
		return 4;
	$insertId = getCommentsNotifiedMaxId() + 1;
	$sql = "INSERT INTO {$database['prefix']}CommentsNotified
		( blogid , replier , id , entry , parent , name , password , homepage , secret , comment , ip , written, modified , siteid , isnew , url , remoteid ,entrytitle , entryurl )
		VALUES (
			$blogid, NULL , $insertId, " . $entryId . ", $parentId, '$child_name', '', '$child_homepage', '', '$child_comment', '', $child_written, UNIX_TIMESTAMP(), $siteid, 1, '$child_url', $child_id, '$entrytitle', '$entryurl')";
	if (!POD::execute($sql))
		return 5;
	$sql = "UPDATE {$database['prefix']}CommentsNotified SET modified = UNIX_TIMESTAMP() WHERE blogid = $blogid AND id = $parentId";
	if (!POD::execute($sql))
		return 6;
	return 0;
}

function getCommentCount($blogid, $entryId = null) {
	global $database;
	if (is_null($entryId))
		return POD::queryCell("SELECT SUM(comments) FROM {$database['prefix']}Entries WHERE blogid = $blogid AND draft= 0 ");
	return POD::queryCell("SELECT comments FROM {$database['prefix']}Entries WHERE blogid = $blogid AND id = $entryId AND draft = 0");
}

function getGuestbookCount($blogid) {
	global $database;
	return POD::queryCell("SELECT count(id) FROM {$database['prefix']}Comments WHERE blogid = $blogid AND entry = 0");
}

function getCommentCountPart($commentCount, &$skin) {
	$noneCommentMessage = $skin->noneCommentMessage;
	$singleCommentMessage = $skin->singleCommentMessage;

	if ($commentCount == 0 && !empty($noneCommentMessage)) {
		dress('article_rep_rp_cnt', 0, $noneCommentMessage);
		$commentView = $noneCommentMessage;
	} else if ($commentCount == 1 && !empty($singleCommentMessage)) {
		dress('article_rep_rp_cnt', 1, $singleCommentMessage);
		$commentView = $singleCommentMessage;
	} else {
		$commentPart = $skin->commentCount;
		dress('article_rep_rp_cnt', $commentCount, $commentPart);
		$commentView = $commentPart;
	}

	return array("rp_count", $commentView);
}

function getCommentsMaxId() {
	global $database;
	$maxId = POD::queryCell("SELECT max(id)
		FROM {$database['prefix']}Comments
		WHERE blogid = ".getBlogId());
	return empty($maxId) ? 0 : $maxId;
}

function getCommentsNotifiedMaxId() {
	global $database;
	$maxId = POD::queryCell("SELECT max(id)
		FROM {$database['prefix']}CommentsNotified
		WHERE blogid = ".getBlogId());
	return empty($maxId) ? 0 : $maxId;
}

function getCommentsNotifiedQueueMaxId() {
	global $database;
	$maxId = POD::queryCell("SELECT max(id)
		FROM {$database['prefix']}CommentsNotifiedQueue
		WHERE blogid = ".getBlogId());
	return empty($maxId) ? 0 : $maxId;
}

function getCommentsNotifiedSiteInfoMaxId() {
	global $database;
	$maxId = POD::queryCell("SELECT max(id)
		FROM {$database['prefix']}CommentsNotifiedSiteInfo");
	return empty($maxId) ? 0 : $maxId;
}

?>
