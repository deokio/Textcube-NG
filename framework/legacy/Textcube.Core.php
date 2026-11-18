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

/* This component contains 'User', 'Blog', 'Transaction' and 'Checkup'(PHP port) class. 
   NOTE : Classes described below are actually not object. Usually they are static.*/

// for Global Cache
global $__gCacheUserNames;
$__gCacheUserNames = array();

class User {
	private static $__gCacheUserNames;
	static function getName($userid = null) {
		global $database, $__gCacheUserNames;
		if (empty($userid))
			$userid = getUserId();
		if (array_key_exists($userid, $__gCacheUserNames)) {
			return $__gCacheUserNames[$userid];
		}
		return $__gCacheUserNames[$userid] = POD::queryCell("SELECT name FROM {$database['prefix']}Users WHERE userid = $userid");
	}
	
	static function getUserIdByName($name) {
		global $database, $__gCacheUserNames;
		if (!isset($name))
			return getUserId();
		$name = POD::escapeString($name);
		$userid = array_search($name, $__gCacheUserNames);
		if(!empty($userid))
			return $userid;
		$userid = POD::queryCell("SELECT userid FROM {$database['prefix']}Users WHERE name = '".$name."'");
		$__gCacheUserNames[$userid] = $name;
		return $userid;
	}
	
	static function getUserNamesOfBlog($blogid) {
		// TODO : Caching with global cache component. (Usually it is not changing easily.)
		global $database;
		$authorIds = POD::queryColumn("SELECT userid
			FROM {$database['prefix']}Privileges
			WHERE blogid = $blogid");
		$authorInfo = POD::queryAll("SELECT userid, name
			FROM {$database['prefix']}Users
			WHERE userid IN (".implode(",",$authorIds).")");
		return $authorInfo;
	}

	static function getBlogOwnerName($blogid) {
		global $database;
		// Textcube-NG: r3-31 R-518 — 심층 방어 정수 캐스트(호출처는 검증된 id·getBlogId()·DB 값뿐이라 도달 경로는 없음, 받는 함수에서 막음)
		$ownerUserId = POD::queryCell("SELECT userid 
			FROM {$database['prefix']}Privileges
			WHERE blogid = ".intval($blogid)."
				AND acl > 15");
		// Textcube-NG: r3-32 R-524 — 소유자 행이 없으면 getName(null) 이 getUserId() 로 대체되어 현재 로그인 사용자 이름을 돌려준다(원본 같음, 손님 페이지 캐시에 남음 R-532). 빈 문자열을 돌려준다
		if (empty($ownerUserId)) return '';
		return User::getName($ownerUserId);
	}

	static function getBlogOwner($blogid) {
		global $database;
		// Textcube-NG: r3-31 R-518 — 심층 방어 정수 캐스트(호출처는 검증된 id·getBlogId()·DB 값뿐이라 도달 경로는 없음, 받는 함수에서 막음)
		$ownerUserId = POD::queryCell("SELECT userid 
			FROM {$database['prefix']}Privileges
			WHERE blogid = ".intval($blogid)."
				AND acl > 15");
		return $ownerUserId;
	}

	static function getEmail($userid = null) {
		global $database;
		if (!isset($userid))
			$userid = getUserId();
		return POD::queryCell("SELECT loginid FROM {$database['prefix']}Users WHERE userid = $userid");
	}

	static function getUserIdByEmail($loginid = null) {
		global $database;
		$loginid = trim($loginid);
		if(!isset($loginid)) return null;
		$loginid = POD::escapeString($loginid);
		return POD::queryCell("SELECT userid FROM {$database['prefix']}Users WHERE loginid = '".$loginid."'");
	}
	
	static function getBlogs($userid = null) {
		global $database;
		if (!isset($userid))
			$userid = getUserId();
		return POD::queryColumn("SELECT blogid FROM {$database['prefix']}Privileges WHERE userid = $userid");
	}

	static function getOwnedBlogs($userid = null) {
		global $database;
		if (!isset($userid))
			$userid = getUserId();
		return POD::queryColumn("SELECT blogid FROM {$database['prefix']}Privileges WHERE userid = $userid AND acl > 15");
	}

	static function getHomepageType($userid = null) {
		global $database;
		if (!isset($userid)) 
			$userid = getUserId();
		$info = unserialize(Setting::getUserSettingGlobal('userLinkInfo','',$userid));
		if(!empty($info)) $type = $info['type'];
		if (empty($type)) {
			$type = "default";
		}
		return $type;
	}

	static function getHomepage($userid = null) {
		global $database;
		if (!isset($userid) || empty($userid)) 
			$userid = getUserId();
		$info = unserialize(Setting::getUserSettingGlobal('userLinkInfo','',$userid));
		if(!is_array($info)) $info = array('type' => 'default');
		switch ($info['type']) {
			case "external" :
				$homepage = $info['url'];
				break;
			case "internal" :
				$homepage = getDefaultURL($info['blogid']);
				break;
			case "author" : 
				$homepage = getDefaultURL($info['blogid'])."/author/".URL::encode(User::getName($userid));
				break;
			case "default" :
			default :
				$homepage = null;
		}
		return $homepage;
	}

	static function setHomepage($type, $homepage, $blogid = null, $userid = null) {
		global $database;
		$types = array("internal","author","external","default");
		if (!isset($userid)) //TODO : 현재 로그인 사용자의 homepage만 변경가능.setUserSetting함수 특성. 
			$userid = getUserId();
		$info['blogid'] = is_null($blogid) ? getBlogId() : $blogid;
		$info['url'] = is_null($homepage) ? null : $homepage;
		if (in_array($type,$types)) {
			$info['type'] = $type;
			switch ($type) {
				case "internal" : case "author" : 
					$info['url'] = null;
					break;
				case "external" :
					$info['blogid'] = null;
					break;
				case "default" :
				default :
					$info['url'] = null;
					$info['blogid'] = null;
			}
		} else {
			return false;
		}
		$homepage = serialize($info);
		if (Setting::setUserSettingGlobal("userLinkInfo",$homepage, $userid)) {
			return true;
		}
		return false;
	}
	
	static function confirmPassword($userid, $password) {
		global $database;
		if(empty($userid)) $userid = getUserId(); 
		// [보안] md5 SQL 비교 → 저장값을 읽어 Auth::verifyPassword 로 확인(password_hash 값·기존 md5 값 모두)
		$hash = POD::queryCell("SELECT password FROM {$database['prefix']}Users WHERE userid = " . intval($userid), 0, false);
		return Auth::verifyPassword($password, $hash);
	}

	static function authorName($blogid,$entryId){
		if( is_null($blogid) ) {
			$blogid = getBlogId();
		}

		// Read userId of entry from relation table.
		$userid = getUserIdOfEntry($blogid,$entryId);
		if(isset($userid)) {
			return User::getName($userid);
		} else {
			return false;
		}
	}
	static function changeBlog(){
		global $database, $blogURL, $blog, $service;
		$blogid = getBlogId();	

		$blogList = User::getBlogs();
		if (count($blogList) == 0) {
			return;
		}

		$changeBlogView = str_repeat(TAB,6)."<select id=\"blog-list\" onchange=\"location.href='{$blogURL}/owner/network/teamblog/changeBlog/?blogid='+this.value\">".CRLF;
		foreach($blogList as $info){
			$title = UTF8::lessen(Setting::getBlogSettingGlobal("title",null,$info,true), 30);
			$title = ($title ? $title : _f('%1 님의 블로그',User::getBlogOwnerName($info)));
			$changeBlogView .= str_repeat(TAB,7).'<option value="' . $info . '"';
			if($info == $blogid) $changeBlogView .= ' selected="selected"';
			$changeBlogView .= '>' . $title . '</option>'.CRLF;
		}
		$changeBlogView .= str_repeat(TAB,6).'</select>'.CRLF;
		return $changeBlogView;
	}
	
	static function changeSetting($userid, $email, $nickname) {
		global $database;
		if (strcmp($email, UTF8::lessenAsEncoding($email, 64)) != 0) return false;
		$email = POD::escapeString(UTF8::lessenAsEncoding($email, 64));
		$nickname = POD::escapeString(UTF8::lessenAsEncoding($nickname, 32));
		if ($email == '' || $nickname == '') {
			return false;
		}
		if (POD::queryExistence("SELECT * FROM {$database['prefix']}Users WHERE name = '$nickname' AND userid <> $userid")) {
			return false;
		} else {
			$result = POD::query("UPDATE {$database['prefix']}Users SET loginid = '$email', name = '$nickname' WHERE userid = $userid");		
			if (!$result) {
				return false;
			} else {
				return true;
			}
		}
	}

	static function add($email, $name) {
		global $database, $service, $user, $blog;
		if (empty($email))
			return 1;
		if (!preg_match('/^[^@]+@([-a-zA-Z0-9]+\.)+[-a-zA-Z0-9]+$/', $email))
			return 2;
	
		if (strcmp($email, UTF8::lessenAsEncoding($email, 64)) != 0) return 11;
	
		$loginid = POD::escapeString(UTF8::lessenAsEncoding($email, 64));	
		$name = POD::escapeString(UTF8::lessenAsEncoding($name, 32));
		$password = User::__generatePassword();
	
		if (POD::queryExistence("SELECT * FROM {$database['prefix']}Users WHERE loginid = '$loginid'")) {
			return 9;	// User already exists.
		}
	
		if (POD::queryCell("SELECT COUNT(*) FROM {$database['prefix']}Users WHERE name = '$name'")) {
			$name = $name . '.' . time();
		}

		// 비밀번호는 행을 만든 뒤 Auth::storePassword 로 저장(저장 방식 $service['passwordHash'], 기본 md5 = 원본과 같은 값)
		$result = POD::query("INSERT INTO {$database['prefix']}Users (userid, loginid, password, name, created, lastlogin, host) VALUES (".(User::__getMaxUserId()+1).", '$loginid', '', '$name', UNIX_TIMESTAMP(), 0, ".getUserId().")");
		if (empty($result)) {
			return 11;
		}
		Auth::storePassword(User::getUserIdByEmail($loginid), $password);
		// [보안] 인증 토큰은 CSPRNG 128비트(원본 md5(약한 8자)와 같은 32자리 16진 형식)로 발급 시각과 함께 저장(Auth::issueAuthToken — 만료·1회용)
		if (Auth::issueAuthToken(User::getUserIdByEmail($loginid)) === false) {
			return 11;
		}
		return true;
	}
	
	/*@static@*/
	static function remove($userid) {
		global $database;
		if ($userid == 1)
			return false;
		if (!isset($userid)) 
			return false;
		$blogs = User::getOwnedBlogs($userid);
		$sql = "UPDATE {$database['prefix']}Comments SET replier = NULL WHERE replier = ".$userid;
		POD::execute($sql);
		foreach ($blogs as $ownedBlog) {
			Blog::changeOwner($ownedBlog,1); // 관리자 uid로 변경
		}
		$blogs = User::getBlogs($userid);
		foreach ($blogs as $joinedBlog) {
			Blog::deleteUser($joinedBlog, $userid);
		}
		User::removePermanent($userid);
		return true;
	}
	
	static function removePermanent($userid) {
		global $database;
		// 인증 토큰·발급 시각 삭제(Auth::removeAuthToken). 원본의 DELETE ... LIMIT 1 은 MySQL 전용이라 PostgreSQL 등에서는 실패해 계정이 지워지지 않았다
		if( Auth::removeAuthToken($userid) ) {
			return POD::execute("DELETE FROM {$database['prefix']}Users WHERE userid = $userid");
		} else {
			return false;
		}
	}
	
	static function __generatePassword() {
		// [보안] generatePassword()(library/auth.php)와 같은 이유로 CSPRNG 소문자·숫자 12자(원본은 약 10^6 가지 이하)
		$chars = 'abcdefghijklmnopqrstuvwxyz0123456789';
		$password = '';
		for ($i = 0; $i < 12; $i++) {
			$password .= $chars[random_int(0, 35)];
		}
		return $password;
	}

	/*@private static@*/
	static function __getMaxUserId() {
		global $database;
		$maxId = POD::queryCell("SELECT max(userid) FROM {$database['prefix']}Users");
		if($maxId) return $maxId;
		else return 0;
	}
}

class Blog {
	/*@static@*/
	static function changeOwner($blogid,$userid) {
		global $database;
		POD::execute("UPDATE {$database['prefix']}Privileges SET acl = 3 WHERE blogid = ".$blogid." and acl = " . BITWISE_OWNER);
	
		$acl = POD::queryCell("SELECT acl FROM {$database['prefix']}Privileges WHERE blogid = $blogid and userid = $userid");
	
		if( $acl === null ) { // If there is no ACL, add user into the blog.
			POD::query("INSERT INTO {$database['prefix']}Privileges  
				VALUES($blogid, $userid, '".BITWISE_OWNER."', UNIX_TIMESTAMP(), 0)");
		} else {
			POD::execute("UPDATE {$database['prefix']}Privileges SET acl = ".BITWISE_OWNER." 
				WHERE blogid = ".$blogid." and userid = " . $userid);
		}
		return true;
	}
	
	/*@static@*/
	/* TODO : remove model dependency (addBlog, sendInvitationMail) */
	static function addUser($email, $name, $comment, $senderName, $senderEmail) {
		requireModel('blog.user');
		requireModel('blog.blogSetting');
		global $database,$service,$blogURL,$hostURL,$user,$blog;
	
		$blogid = getBlogId();
		if(empty($email))
			return 1;
		if(!preg_match('/^[^@]+@([-a-zA-Z0-9]+\.)+[-a-zA-Z0-9]+$/',$email))
			return array( 2, _t('이메일이 바르지 않습니다.') );
		
		$isUserExists = User::getUserIdByEmail($email);
		if(empty($isUserExists)) { // If user is not exist
			User::add($email,$name);
		}
		$userid = User::getUserIdByEmail($email);
		$result = addBlog(getBlogId(), $userid, null);
		if($result === true) {
			return sendInvitationMail(getBlogId(), $userid, User::getName($userid), $comment, $senderName, $senderEmail);
		}
		return $result;
	}

	/*@static@*/
	static function deleteUser($blogid, $userid, $clean = true) {
		global $database;
		if ($blogid == null) {
			$blogid = getBlogId();
		}
		// Textcube-NG: r3-31 R-514 — 소유자(acl>15) 행이 없으면 getBlogOwner() 가 null 이라 `SET userid =` 문법 오류가 났다(원본 같음). 소유자가 있을 때만 글을 옮긴다(결과는 같고 SQL 오류만 없앰)
		$ownerUserId = User::getBlogOwner($blogid);
		if (!empty($ownerUserId)) {
			// Textcube-NG: r3-30 R-504 — 심층 방어 정수 캐스트(호출처 $IV 'id'·DB 정수 값만 들어와 도달 경로는 없음)
			POD::execute("UPDATE {$database['prefix']}Entries 
				SET userid = ".intval($ownerUserId)." 
				WHERE blogid = ".intval($blogid)." AND userid = ".intval($userid));
		}
	
		// Delete ACL relation.
		if(!POD::execute("DELETE FROM {$database['prefix']}Privileges WHERE blogid=".intval($blogid)." and userid=".intval($userid)))
			return false;
		// [보안] Textcube-NG: 블로그에서 뺀 구성원의 임시 첨부(저장 전 글에 올린 파일, parent = -(userid+1))도 지운다 — 원본은 남아 있었다(r3-7, 결정 497)
		requireModel('blog.attachment');
		$temporary = getTemporaryAttachmentParent($userid);
		if ($temporary !== false) {
			$names = POD::queryColumn("SELECT name FROM {$database['prefix']}Attachments WHERE blogid = ".intval($blogid)." AND parent = ".intval($temporary));
			if (!empty($names))
				foreach ($names as $name)
					deleteAttachment(intval($blogid), $temporary, $name);
		}
		// And if there is no blog related to the specific user, delete user.
		if($clean && !POD::queryAll("SELECT * FROM {$database['prefix']}Privileges WHERE userid = ".intval($userid))) {
			User::removePermanent($userid);
		}
		return true;
	}
	
	/*@static@*/
	static function changeACLofUser($blogid, $userid, $ACLtype, $switch) {  // Change user priviledge on the blog.
		global $database;
		if(empty($ACLtype) || empty($userid))
			return false;
		$acl = POD::queryCell("SELECT acl
				FROM {$database['prefix']}Privileges 
				WHERE blogid=$blogid and userid=$userid");
		if( $acl === null ) { // If there is no ACL, add user into the blog.
			$name = User::getName($userid);
			POD::query("INSERT INTO {$database['prefix']}Privileges  
					VALUES($blogid, $userid, 0, UNIX_TIMESTAMP(), 0)");
			$acl = 0;
		}
		$bitwise = null;
		switch( $ACLtype ) {
			case 'admin':
				$bitwise = BITWISE_ADMINISTRATOR;
				break;
			case 'editor':
				$bitwise = BITWISE_EDITOR;
				break;
			default:
				return false;
		}
		if( $switch ) {
			$acl |= $bitwise;
		} else {
			$acl &= ~$bitwise;
		}
		return POD::execute("UPDATE {$database['prefix']}Privileges 
			SET acl = ".$acl." 
			WHERE blogid = ".$blogid." and userid = ".$userid);
	}
}

class Transaction {
	/* [보안] tid 는 pickle() 이 만드는 형식(10자리 시각 + 'P' + 32자리 16진)만 허용한다. 원본은 요청 값
	   (interface/blog/comment/addopenid 의 $_GET['tid'], 로그인 불필요)을 그대로 cache/pickle 경로에 붙여
	   그 밖의 파일을 읽고(unserialize)·삭제할 수 있었다. */
	private static function isValidTid( $tid ) {
		return is_string( $tid ) && preg_match( '/\A[0-9]{10}P[0-9a-f]{32}\z/', $tid ) === 1;
	}

	public static function pickle($data) {
		$pickle_dir = ROOT.DS."cache".DS."pickle".DS;
		/* [보안] tid 는 만든 세션에만 묶는다 — unpickle() 은 이 세션 목록에 있는 tid 만 받는다. 원본은 tid 만 알면 어느 세션에서나
		   읽혀, 남이 만든 tid 로 피해자 세션의 신원으로 댓글을 쓰게 하거나 저장된 Referer 로 경로 검사를 넘을 수 있었다(v3.153).
		   1시간이 지난 목록 항목은 지운다(파일은 그대로 둔다). */
		if( !isset( $_SESSION['pickle'] ) || !is_array( $_SESSION['pickle'] ) ) {
			$_SESSION['pickle'] = array();
		}
		foreach( array_keys( $_SESSION['pickle'] ) as $old ) {
			if( !is_string( $old ) || intval( substr( $old, 0, 10 ) ) < time() - 3600 ) {
				unset( $_SESSION['pickle'][$old] );
			}
		}
		/* [보안] 원본 md5(microtime(true)) 는 생성 시각으로 추측 가능 → CSPRNG(같은 32자리 16진 형식) */
		$tid = sprintf("%010dP%s",time(),bin2hex( random_bytes(16) ));
		while( file_exists( $pickle_dir.$tid ) ) {
			$tid = sprintf("%010dP%s",time(),bin2hex( random_bytes(16) ));
			usleep(50);
		}
		file_put_contents( $pickle_dir.$tid, serialize($data) );
		$_SESSION['pickle'][$tid] = time();
		return $tid;
	}

	public static function unpickle( $tid ) {
		if( !self::isValidTid( $tid ) ) {
			return false;
		}
		/* [보안] 이 세션이 만든 tid 만 받는다(v3.153) — 다른 세션의 tid 면 파일을 읽지도 지우지도 않는다. 받으면 목록에서 지워 한 번만 쓴다. */
		if( !isset( $_SESSION['pickle'] ) || !is_array( $_SESSION['pickle'] ) || !isset( $_SESSION['pickle'][$tid] ) ) {
			return false;
		}
		unset( $_SESSION['pickle'][$tid] );
		$pickle_file = ROOT.DS."cache".DS."pickle".DS.$tid;
		if( !is_file( $pickle_file ) ) {
			return false;
		}
		/* 저장 내용은 $_POST 배열·문자열뿐 — 객체 복원 금지 */
		$data = unserialize(file_get_contents( $pickle_file ), array('allowed_classes' => false));
		unlink( $pickle_file );
		return $data;
	}

	public static function repickle( $tid, & $data ) {
		if( !self::isValidTid( $tid ) ) {
			return;
		}
		/* [보안] Textcube-NG: 다른 세션이 만든 pickle 파일은 덮어쓰지 않는다 — 파일이 있으면 이 세션 목록에 있는 tid 만(unpickle() 로 꺼낸 tid 는 파일이 지워져 다시 쓸 수 있음)(r3-7, 결정 468a) */
		if( file_exists( ROOT.DS."cache".DS."pickle".DS.$tid ) && ( !isset( $_SESSION['pickle'] ) || !is_array( $_SESSION['pickle'] ) || !isset( $_SESSION['pickle'][$tid] ) ) ) {
			return;
		}
		$_SESSION['pickle'][$tid] = $data;
		$pickle_dir = ROOT.DS."cache".DS."pickle".DS;
		if( !file_exists( $pickle_dir ) ) {
			mkdir( $pickle_dir );
		}
		file_put_contents( $pickle_dir.$tid, serialize($data) );
	}

	public static function taste( $tid ) {
		if( !self::isValidTid( $tid ) ) {
			return null;
		}
		/* [보안] Textcube-NG: 이 세션이 만든 tid 만 읽는다 — unpickle() 과 같은 확인(v3.153)(r3-7, 결정 468a) */
		if( !isset( $_SESSION['pickle'] ) || !is_array( $_SESSION['pickle'] ) || !isset( $_SESSION['pickle'][$tid] ) ) {
			return null;
		}
		$pickle_file = ROOT.DS."cache".DS."pickle".DS.$tid;
		if( !file_exists( $pickle_file ) ) {
			return null;
		}
		$data = unserialize(file_get_contents( $pickle_file ), array('allowed_classes' => false));
		return $data;
	}

	public static function clear() {
		return;
	}

	public static function gc() {
		return;
	}

	public static function debug( $tid = null ) {
		return;
	}
}

/* PHP port(Textcube-NG): 버전 변화점 점검 표시(cache/CHECKUP).
   1행은 원본과 같은 TEXTCUBE_VERSION, 2행은 NG 점검 표시 'ng:{TEXTCUBE_NG_CHECKUP_REVISION}'(비밀번호 저장 방식이 'md5' 가
   아니면 ' password_hash' 를 덧붙임). 원본 파일을 덮어쓰는(drop-in) 이전에서는 CHECKUP 이 원본 버전 값과 같아 점검 안내가
   나오지 않아 NG 점검 항목(checkup.php 의 BlogSettings 색인, Users.password 확장)이 적용되지 않았다.
   2행이 없거나, 개정 번호가 낮거나, 'md5' 가 아닌데 password_hash 표시가 없으면 점검이 필요하다(isCurrent()).
   원본은 파일 전체를 버전 값과 비교하므로 원본으로 되돌리면 원본 점검을 한 번 안내한다.
   r3-11: 3행 'ngversion:{TEXTCUBE_NG_VERSION}' 은 표시용이다(ngVersion(), checkup.php '기존 버전'). isCurrent() 는 1·2행만 보므로
   3행이 없는 옛 기록도 전과 같게 판정한다. */
class Checkup {
	// checkup.php 가 점검 성공 후 기록하는 내용
	static function stamp() {
		// PHP port(Textcube-NG): r3-11 3행에 NG 버전 'ngversion:{TEXTCUBE_NG_VERSION}'(표시용)
		return TEXTCUBE_VERSION . "\n" . 'ng:' . TEXTCUBE_NG_CHECKUP_REVISION . ((Auth::passwordHashMode() != 'md5') ? ' password_hash' : '') . "\n" . 'ngversion:' . TEXTCUBE_NG_VERSION;
	}

	// 기록된 원본 버전(1행, 원본처럼 그대로)
	static function version($content) {
		$lines = preg_split('/\r\n|\r|\n/', (string)$content, 2);
		return $lines[0];
	}

	// PHP port(Textcube-NG): r3-11 기록된 NG 버전(3행 'ngversion:…'). 없거나 형식이 다르면 null
	static function ngVersion($content) {
		$lines = preg_split('/\r\n|\r|\n/', (string)$content);
		if (!isset($lines[2]) || !preg_match('/^ngversion:([0-9A-Za-z.+_-]{1,64})\s*$/', $lines[2], $match))
			return null;
		return $match[1];
	}

	// CHECKUP 이 현재 버전·NG 점검 표시와 맞으면 true(파일이 없으면 false)
	static function isCurrent() {
		$file = __TEXTCUBE_CACHE_DIR__ . '/CHECKUP';
		if (!file_exists($file) || !is_string($content = file_get_contents($file)))
			return false;
		$lines = preg_split('/\r\n|\r|\n/', $content);
		if ((trim($lines[0]) !== TEXTCUBE_VERSION) || !isset($lines[1])
			|| !preg_match('/^ng:(\d{1,9})((?: [a-z_]+)*)\s*$/', $lines[1], $match)
			|| (intval($match[1]) < TEXTCUBE_NG_CHECKUP_REVISION))
			return false;
		return (Auth::passwordHashMode() == 'md5') || in_array('password_hash', explode(' ', trim($match[2])), true);
	}
}

?>
