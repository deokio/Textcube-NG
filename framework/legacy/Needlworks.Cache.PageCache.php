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

#[AllowDynamicProperties]
class pageCache extends Singleton {
	function __construct($name = null){
		$this->pool = DBModel::getInstance();
		$this->context = Model_Context::getInstance();
		$this->reset();
	}
    
	public static function getInstance() {
		return self::_getInstance(__CLASS__);
	}

	public function reset($name = null) {
		// Textcube-NG: 사용자별 캐시 파일명·경로와 기록 칸 키(filenameUser·absoluteFilePathUser·dbContentsKey)도 초기화(v3.146)
		$this->name = 
		$this->realName = 
		$this->realNameOwner = 
		$this->realNameGuest = 
		$this->filename =
		$this->filenameOwner =
		$this->filenameGuest =
		$this->filenameUser =
		$this->absoluteFilePath =
		$this->absoluteFilePathOwner =
		$this->absoluteFilePathGuest =
		$this->absoluteFilePathUser =
		$this->dbContentsKey =
		$this->contents =
		$this->dbContents =
		$this->_dbContents =
		$this->_fileCacheOnly =
		$this->error = 
		null;
		if(!is_null($name)) $this->name = $name;
	}

	private function create () {
		$this->initialize();
		if(!$this->getFileName()) return false;
		if(file_exists($this->absoluteFilePath))
			$this->purge();
		if(empty($this->contents)) return $this->_error('No contents');	
		$fileHandle = fopen($this->absoluteFilePath,'w');
		if(fwrite($fileHandle, $this->contents)){
			fclose($fileHandle);
			if(empty($this->_fileCacheOnly)) $this->setPageCacheLog();
			@chmod($this->absoluteFilePath, 0666);
			return true;
		}
		fclose($fileHandle);
		return false;
	}

	public function update () {
		if($this->context->getProperty('service.pagecache') != true) return false;
		if($this->context->getProperty('uri.isRegisteredOrigin') === false) return false;	// Textcube-NG: 등록되지 않은 Host·포트의 요청은 페이지 캐시를 쓰지 않는다(v3.205)
		$this->purge();
		$this->create();
	}

	public function load () {
		if($this->context->getProperty('service.pagecache') != true) return false;
		if($this->context->getProperty('uri.isRegisteredOrigin') === false) return false;	// Textcube-NG: 등록되지 않은 Host·포트의 요청은 페이지 캐시를 쓰지 않는다(v3.205)
		$this->initialize();
		$this->contents = $this->dbContents = null;
		if(!$this->getFileName()) return false;
		if(empty($this->_fileCacheOnly)) $this->getdbContents();
		if($this->getFileContents()) {
			return true;
		}
		else return false;
	}

	private function initialize() {
		 if (!is_dir(__TEXTCUBE_CACHE_DIR__."/pageCache/".getBlogId())){
			 if(!is_dir(__TEXTCUBE_CACHE_DIR__."/pageCache")) {
				 @mkdir(__TEXTCUBE_CACHE_DIR__."/pageCache");
				 @chmod(__TEXTCUBE_CACHE_DIR__."/pageCache",0777);
			 }
			 @mkdir(__TEXTCUBE_CACHE_DIR__."/pageCache/".getBlogId());
			 @chmod(__TEXTCUBE_CACHE_DIR__."/pageCache/".getBlogId(),0777);
		 }
	}
	public function purge () {
		if($this->context->getProperty('service.pagecache') != true) return true;
		$this->getFileName();
		// Textcube-NG: 사용자별 파일은 owner·손님 파일이 없어도 지운다(v3.146)
		$purgedUserFiles = $this->purgeUserFiles();
		if(
			(file_exists($this->absoluteFilePathOwner) && @chmod($this->absoluteFilePathOwner, 0777))
			|| (file_exists($this->absoluteFilePathGuest) && @chmod($this->absoluteFilePathGuest, 0777))
			)
		{
			if(file_exists($this->absoluteFilePathOwner)) @unlink($this->absoluteFilePathOwner);
			if(file_exists($this->absoluteFilePathGuest)) @unlink($this->absoluteFilePathGuest);
			if(empty($this->_fileCacheOnly)) $this->removePageCacheLog();
			return true;
		} else {
			if(empty($this->_fileCacheOnly)) $this->removePageCacheLog();
			return $purgedUserFiles;
		}
	}

	private function getFileName(){
		if(empty($this->name)) return $this->_error('invalid name');
		$this->realName = $this->name;
		$this->realNameOwner = $this->name."_".getBlogId()."_owner";
		$this->realNameGuest = $this->name."_".getBlogId();
		/* Textcube-NG: 파일명 crc32 → sha256(v3.146). 원본 abs(crc32()) 는 선형이라, 손님이 값을 정하는 이름(목록의 page 등)을
		   골라 다른 이름(_owner 포함)의 파일명과 같게 만들 수 있었다. 옛 crc32 이름 파일은 다시 읽히지 않는다. */
		$this->filenameOwner = hash('sha256', $this->realNameOwner).'.cache';
		$this->filenameGuest = hash('sha256', $this->realNameGuest).'.cache';
		$this->absoluteFilePathOwner = __TEXTCUBE_CACHE_DIR__.'/pageCache/'.getBlogId()."/".$this->filenameOwner;
		$this->absoluteFilePathGuest = __TEXTCUBE_CACHE_DIR__.'/pageCache/'.getBlogId()."/".$this->filenameGuest;
		/* Textcube-NG: 원본은 doesHaveOwnership() 만 보고 구성원 모두가 _owner 파일 하나를 함께 썼다(v3.146).
		   블로그 화면 질의는 글관리 권한(group.editors)이 없는 구성원에게 자기 글과 비공개가 아닌 글만 주므로(원본 글 목록, v3.145),
		   권한에 따라 내용이 갈리는 이름(isPerUserName)은 그런 구성원에게 사용자별 파일과 기록 칸(owner_u<번호>)을 쓴다. */
		if(!doesHaveOwnership()) {
			$this->dbContentsKey = 'user';
			$this->filename = $this->filenameGuest;
			$this->absoluteFilePath = $this->absoluteFilePathGuest;
		} else if(Acl::check('group.editors') || !$this->isPerUserName()) {
			$this->dbContentsKey = 'owner';
			$this->filename = $this->filenameOwner;
			$this->absoluteFilePath = $this->absoluteFilePathOwner;
		} else {
			$this->dbContentsKey = 'owner_u'.getUserId();
			$this->filenameUser = $this->getUserFileName(getUserId());
			$this->absoluteFilePathUser = __TEXTCUBE_CACHE_DIR__.'/pageCache/'.getBlogId()."/".$this->filenameUser;
			$this->filename = $this->filenameUser;
			$this->absoluteFilePath = $this->absoluteFilePathUser;
		}
		return true;
	}

	/* Textcube-NG: 권한에 따라 내용이 갈리는 캐시 이름(v3.146). 호출부 이름 그대로 —
	   interface/blog/category.php categoryList-·keyword-category-, author.php authorList-, tag.php tagList-·keyword-·tagPage,
	   library/view/view.php entry-(본문의 키워드 링크, getKeywordNames), 작성자·보관함 피드(피드는 세션이 없어 늘 손님, 방어용),
	   plugins/MT_Meta_RecentPS_Default MT_Cover_RecentPS. */
	private static $perUserPrefixes = array('categoryList-', 'authorList-', 'tagList-', 'keyword-', 'authorRSS-', 'authorATOM-', 'archiveRSS-', 'archiveAtom-');
	private static $perUserNames = array('tagPage', 'MT_Cover_RecentPS');

	private function isPerUserName() {
		$name = (string)$this->name;
		if(in_array($name, self::$perUserNames, true)) return true;
		if(preg_match('/^entry-\d+-/', $name)) return true; // Textcube-NG: entry-<id>- 만 사용자별, entry-queryCache-* 는 나눔(r3-8)
		foreach(self::$perUserPrefixes as $prefix) {
			if(strncmp($name, $prefix, strlen($prefix)) == 0) return true;
		}
		return false;
	}

	private function getUserFileName($userid) {
		return hash('sha256', $this->realNameOwner).'_u'.intval($userid).'.cache';
	}

	/* Textcube-NG: 블로그 구성원 번호(Privileges), 요청마다 블로그별로 한 번만 읽는다(v3.146). */
	private function getMemberIds() {
		static $members = array();
		$blogid = getBlogId();
		if(!isset($members[$blogid])) {
			$this->pool->reset('Privileges');
			$this->pool->setQualifier('blogid','equals',$blogid);
			$rows = $this->pool->getColumn('userid');
			$members[$blogid] = is_array($rows) ? array_map('intval', $rows) : array();
		}
		return $members[$blogid];
	}

	/* Textcube-NG: 이 이름의 사용자별 파일을 지운다(v3.146). 대상은 블로그 구성원과 기록 행 owner_u<번호> 칸의 합집합 —
	   디렉토리를 훑지 않는다. 사용자별 이름이 아니면 하지 않는다. 지운 파일이 있으면 true. */
	private function purgeUserFiles() {
		if(empty($this->name) || !$this->isPerUserName()) return false;
		$userids = $this->getMemberIds();
		if(empty($this->_fileCacheOnly)) {
			$this->pool->reset('PageCacheLog');
			$this->pool->setQualifier('blogid','equals',getBlogId());
			$this->pool->setQualifier('name','equals',$this->realName,true);
			$result = $this->pool->getCell('value');
			$logged = is_null($result) ? null : unserialize($result, array('allowed_classes' => false));
			if(is_array($logged)) {
				foreach(array_keys($logged) as $key) {
					if(preg_match('/^owner_u(\d+)$/', (string)$key, $match)) $userids[] = intval($match[1]);
				}
			}
		}
		$purged = false;
		foreach(array_unique($userids) as $userid) {
			$path = __TEXTCUBE_CACHE_DIR__.'/pageCache/'.getBlogId()."/".$this->getUserFileName($userid);
			if(file_exists($path) && @chmod($path, 0777)) {
				@unlink($path);
				$purged = true;
			}
		}
		return $purged;
	}

	private function getFileContents() {
		if(!isset($this->absoluteFilePath)) return false;
		if(!file_exists($this->absoluteFilePath)) {
			return $this->_error('no file exists');
		}
		$this->contents = @file_get_contents($this->absoluteFilePath);
		if(!isset($this->contents)) {
			return $this->_error('content');
		}
		return true;
	}

	private function getdbContents() {
		return $this->getPageCacheLog();
	}

	private function getPageCacheLog() {
		$this->pool->reset('PageCacheLog');
		$this->pool->setQualifier('blogid','equals',getBlogId());
		$this->pool->setQualifier('name','equals',$this->realName,true);
		$result = $this->pool->getCell('value');
		if(!is_null($result)) {
			$this->_dbContents = unserialize($result);
			// Textcube-NG: 기록 칸은 getFileName() 이 고른 키(user·owner·owner_u<번호>)(v3.146)
			$this->dbContents = isset($this->_dbContents[$this->dbContentsKey]) ? $this->_dbContents[$this->dbContentsKey] : null;
		} else {
			return false;
		}
		return true;
	}

	private function setPageCacheLog() {
		// Textcube-NG: getFileName() 이 고른 칸만 갱신하고 다른 칸은 보존(v3.146). 읽은 기록이 없으면(null·false) 빈 배열에서
		// 시작한다 — PHP 8.1 부터 false 를 배열로 자동 변환하면 Deprecated
		if(!is_array($this->_dbContents)) $this->_dbContents = array();
		$this->_dbContents[$this->dbContentsKey] = $this->dbContents;
		$this->pool->reset('PageCacheLog');
		$this->pool->setAttribute('blogid',getBlogId());
		$this->pool->setAttribute('name',$this->realName,true);
		$this->pool->setAttribute('value',serialize($this->_dbContents),true);
		$this->pool->setQualifier('blogid','equals',getBlogId());
		$this->pool->setQualifier('name','equals',$this->realName,true);
		return $this->pool->replace();
	}

	private function removePageCacheLog() {
		$this->pool->reset('PageCacheLog');
		$this->pool->setQualifier('blogid','equals',getBlogId());
		$this->pool->setQualifier('name','equals',$this->realName,true);
		return $this->pool->delete();
	}

	private function _error($error) {
		$this->error = $error;
		return false;
	}

}

#[AllowDynamicProperties]
class queryCache extends Singleton {
	private $pooltype = null;
	function __construct($query = null, $prefix = null){
		$this->reset();
	
		$this->context = Model_Context::getInstance();
		$this->__usePageCache = $this->context->getProperty('service.pagecache');
		
		if($this->context->getProperty('service.memcached') == true) {
			$this->pool = Cache_Memcache::getInstance();
			$this->pooltype = 'memcache';
		} else {
			$this->pool = DBModel::getInstance();
			$this->pooltype = 'db';
		}
	}

	public static function getInstance() {
		return self::_getInstance(__CLASS__);
	}

	public function reset($query = null, $prefix = null) {
		$this->query = $this->queryHash = $this->contents = $this->error = $this->prefix = $this->namespace = null;
		$this->query = $query;
		if(!is_null($prefix)) {
			$this->prefix = $prefix."-";
			$this->namespace = $this->prefix;
		}
		
	}
	
	public function create() {
		$this->setPageCacheLog();
		return true;
	}
	public function update() {
		if(empty($this->__usePageCache)) return false;
		$this->purge();
		$this->create();
	}
	public function load() {
		if(empty($this->__usePageCache)) return false;
		if($this->getPageCacheLog()) {
			return true;
		}
		else return false;
	}
	public function purge() {
		if(empty($this->__usePageCache)) return false;
		if($this->removePageCacheLog())
			return true;
		else return false;
	}
	public function flush() {
		if(empty($this->__usePageCache)) return false;
		$this->pool->reset('PageCacheLog',$this->prefix);
		if($this->pooltype == 'memcache') {	
			$this->pool->flush();
		} else {
			$this->pool->setQualifier('blogid','equals',getBlogId());
			$this->pool->setQualifier('name','like',$this->prefix.'queryCache-',true);	// Textcube-NG: 이 접두어의 쿼리 캐시 행(<접두어>-queryCache-…)만 지운다. 원본은 '%<접두어>-%' 로 같은 접두어의 페이지 캐시 행(entry-<id>-…)까지 지워 뒤의 flushEntry 가 행을 못 찾아 캐시 파일이 남았다(원본 flushDBCache 주석의 '%<접두어>-queryCache%' 범위, r3-15)
			$this->pool->delete();
		}
	}
	
	private function getQueryHash(){ 
		if(empty($this->query)) return false;
		$this->queryHash = $this->namespace."queryCache-".abs(crc32($this->query));
	}
	
	private function getPageCacheLog() {
		if(empty($this->queryHash)) $this->getQueryHash();
		
		$this->pool->reset('PageCacheLog',$this->prefix);
		$this->pool->setQualifier('blogid','equals',getBlogId());
		$this->pool->setQualifier('name','equals',$this->queryHash,true);
		$result = $this->pool->getCell('value');

		if(!is_null($result) && !empty($result)) {
			$this->contents = unserialize($result);
			return true;
		} else {
			return false;
		}
	}

	private function setPageCacheLog() {
		if(empty($this->queryHash)) $this->getQueryHash();
		
		$name = $this->queryHash;
		$value = serialize($this->contents);
		$this->pool->reset('PageCacheLog',$this->prefix);
		$this->pool->setAttribute('blogid',getBlogId());
		$this->pool->setAttribute('name',$name,true);
		$this->pool->setAttribute('value',serialize($this->contents),true);
		$this->pool->setQualifier('blogid','equals',getBlogId());
		$this->pool->setQualifier('name','equals',$name,true);
		return $this->pool->replace();
	}

	private function removePageCacheLog() {
		if(empty($this->queryHash)) $this->getQueryHash();

		$this->pool->reset('PageCacheLog',$this->prefix);
		$this->pool->setQualifier('blogid','equals',getBlogId());
		$this->pool->setQualifier('name','equals',$this->queryHash,true);
		return $this->pool->delete();
	}

	private function _error($error) {
		$this->error = $error;
		return false;
	}
}

// globalCacheStorage caches essential but 'relatively static' information, like
// blogSettings, ServiceSettings, activePlugins, etc..
// Textcube will use it as global object.
#[AllowDynamicProperties]
class globalCacheStorage extends pageCache {
	function __construct() {
		$this->_isChanged = false;
		$this->_isPurged = false;	// Textcube-NG: 이 요청에서 기록 행을 지웠고 그 뒤 save() 로 다시 쓰지 않았는지(r3-9, 503-15)
		$this->_gCacheStorage = array();
		$this->context = Model_Context::getInstance();
		$this->__usePageCache = $this->context->getProperty('service.pagecache');
		$this->_gBlogId = getBlogId();
		if($this->context->getProperty('service.memcached') == true) {
			$this->pool = Cache_Memcache::getInstance();
		} else {
			$this->pool = DBModel::getInstance();
		}
	}

	public static function getInstance() {
		return self::_getInstance(__CLASS__);
	}

	function load() {
		if(empty($this->__usePageCache)) return false;
		$this->pool->reset('PageCacheLog');
		$this->pool->setQualifier('blogid','equals',$this->_gBlogId);
		$this->pool->setQualifier('name','equals','globalCacheStorage',true);
		$result = $this->pool->getCell('value');
		if(isset($result)) $this->_gCacheStorage[$this->_gBlogId] = unserialize($result);
	}

	function save() {
		if(empty($this->__usePageCache)) return false;
		if($this->_isChanged) {	
			$this->pool->reset('PageCacheLog');
			$this->pool->setAttribute('blogid',$this->_gBlogId);
			$this->pool->setAttribute('name','globalCacheStorage',true);
			$this->pool->setAttribute('value',serialize($this->_gCacheStorage[$this->_gBlogId]),true);
			$this->pool->setQualifier('blogid','equals',$this->_gBlogId);
			$this->pool->setQualifier('name','equals','globalCacheStorage',true);
			$this->_isPurged = false;	// Textcube-NG: 기록 행을 다시 쓰므로 다음 purge() 는 지운다(r3-9, 503-15)
			return $this->pool->replace();
		}
	}
	
	function getContent($name) {
		if(empty($this->_gCacheStorage)) $this->load();
		if(isset($this->_gCacheStorage[$this->_gBlogId][$name])) return $this->_gCacheStorage[$this->_gBlogId][$name];
		else return null;
	}

	function setContent($name, $value) {
		if(isset($this->_gCacheStorage[$this->_gBlogId][$name]) && $this->_gCacheStorage[$this->_gBlogId][$name] == $value) {return true;}
		else {
			$this->_gCacheStorage[$this->_gBlogId][$name] = $value;
			$this->_isChanged = true;
			return true;
		}
	}

	/* Textcube-NG: 이 요청에서 이미 지웠고 그 뒤 save() 로 다시 쓰지 않았으면 같은 DELETE 를 다시 보내지 않는다(r3-9, 503-15).
	   블로그 설정을 여러 개 저장하면(Setting::setBlogSettingGlobal) 값마다 불려 같은 DELETE 가 저장 수만큼 나갔다.
	   이 기록 행(PageCacheLog name 'globalCacheStorage')은 save()(요청 끝 interface/common/blog/end.php)만 쓴다. 지우기에 실패하면 다음에 다시 지운다. */
	function purge() {
		if(empty($this->__usePageCache)) return false;
		if($this->_isPurged) return true;
		$this->pool->reset('PageCacheLog');
		$this->pool->setQualifier('blogid','equals',$this->_gBlogId);
		$this->pool->setQualifier('name','equals','globalCacheStorage',true);
		$result = $this->pool->delete();
		if($result) $this->_isPurged = true;
		return $result;		
	}
}

// CacheControl have functions for flushing caches.
class CacheControl {
	public static function flushAll($blogid = null) {
		if(empty($blogid)) $blogid = getBlogId();
		
		$dir = __TEXTCUBE_CACHE_DIR__.'/pageCache/'.$blogid;
		// Textcube-NG: 디렉터리가 없거나 열리지 않아도 PageCacheLog 삭제는 건너뛰지 않는다 — 원본은 여기서 return true 해 DB 행이 남았다.
		//   파일 삭제만 건너뛰고, unlink 실패 시 false 는 그대로 둔다(r3-24, R-450)
		if (file_exists($dir) && ($dirHandle = @opendir($dir))) {
			while(($object = readdir($dirHandle))) {
				if($object == '.' || $object == '..') continue;
				if(!@unlink($dir.'/'.$object)) { closedir($dirHandle); return false; }
			}
			closedir($dirHandle);
			@rmdir($dir);
		}
		$query = DBModel::getInstance();
		$query->reset('PageCacheLog');
		$query->setQualifier('blogid','equals',$blogid);
		$query->delete();
		return true;
	}
	public static function flushSkin($blogid = null) {
		global $gCacheStorage;
		if(empty($blogid)) $blogid = getBlogId();
		$pool = DBModel::getInstance();
		$pool->reset('PageCacheLog');
		$pool->setQualifier('blogid','eq',$blogid);
		$pool->setQualifier('name','like','skinCache-',true);
		$candidates = $pool->getColumn('name');
		if(!empty($candidates)) CacheControl::purgeItems($candidates);
		$gCacheStorage->purge();
	}
	public static function flushCategory($categoryId = null) {
		global $database;

		if(empty($categoryId)) $categoryId = '';
		else $categoryId = $categoryId.'-';
		
		$categoryLists = POD::queryColumn("SELECT name
			FROM {$database['prefix']}PageCacheLog
			WHERE blogid = ".getBlogId()."
			AND (name like 'categoryList-".$categoryId."%' 
				OR name like 'categoryRSS-".$categoryId."%'
				OR name like 'categoryATOM-".$categoryId."%')");
		CacheControl::purgeItems($categoryLists);
		CacheControl::flushRSS();
		return true;
	}

	public static function flushAuthor($authorId = null) {
		global $database;
		if(empty($authorId)) $authorId = '';
		else $authorId = POD::escapeString($authorId).'-';
		
		$pageLists = POD::queryColumn("SELECT name
			FROM {$database['prefix']}PageCacheLog
			WHERE blogid = ".getBlogId()."
			AND (name like 'authorList-".$authorId."%')");
		CacheControl::purgeItems($pageLists);
		return true;
	}

	public static function flushTag($tagId = null, $tagPage = true) {
		global $database;

		/* Textcube-NG: 태그 번호 하나 또는 배열을 받는다(v3.136). 번호는 정수로 바꿔 SQL 에 넣는다.
		   원본은 번호가 있으면 'tagRSS-<번호>-%'·'tagATOM-<번호>-%' 로 찾아 태그별 피드 캐시(이름 tagRSS-<번호>·tagATOM-<번호>,
		   interface/rss/tag·atom/tag)를 비우지 못했다. 번호가 없으면(전체) 원본과 같은 조건.
		   $tagPage 가 false 이면 RSS 와 태그 페이지(tagPage)는 그대로 둔다 — flushEntry() 가 댓글·트랙백마다 부르므로 */
		if(empty($tagId)) {
			$condition = "name like 'tagList-%'
				OR name like 'keyword-%'
				OR name like 'tagATOM-%'
				OR name like 'tagRSS-%'";
		} else {
			$conditions = array();
			foreach((array)$tagId as $id) {
				$id = intval($id);
				if($id > 0)
					$conditions[$id] = "name like 'tagList-".$id."-%' OR name like 'keyword-".$id."-%' OR name = 'tagATOM-".$id."' OR name = 'tagRSS-".$id."'";
			}
			$condition = empty($conditions) ? null : implode(' OR ', $conditions);
		}
		if(!is_null($condition)) {
			$tagLists = POD::queryColumn("SELECT name
				FROM {$database['prefix']}PageCacheLog
				WHERE blogid = ".getBlogId()."
				AND (".$condition.")");
			CacheControl::purgeItems($tagLists);
		}
		if($tagPage) {
			CacheControl::flushRSS();
			CacheControl::flushTagPage();
		}
		return true;
	}

	public static function flushTagPage() {
		/* Textcube-NG: 태그 페이지(/tag, 캐시 이름 tagPage) 캐시만 비운다(v3.136) — 원본 flushTag() 끝부분과 같은 동작.
		   글의 공개 여부가 바뀔 때(updateEntry·setEntryVisibility)도 부른다 */
		$cache = pageCache::getInstance();
		$cache->reset();
		$cache->name = 'tagPage';
		$cache->purge();
		return true;
	}

	public static function flushKeyword($tagId = null) {
		global $database;

		if(empty($tagId)) $tagId = '';
		else $tagId = $tagId.'-';
		$keywordEntries = POD::queryColumn("SELECT name
			FROM {$database['prefix']}PageCacheLog
			WHERE blogid = ".getBlogId()."
			AND name like 'keyword-".$tagId."%'");
		CacheControl::purgeItems($keywordEntries);
		return true;
	}
	
	public static function flushSearchKeywordRSS($search = null) {
		global $database;

		if(empty($search)) $search = '';
		else $search = escapeSearchString($search);
		$searchEntries = POD::queryColumn("SELECT name
			FROM {$database['prefix']}PageCacheLog
			WHERE blogid = ".getBlogId()."
			AND (name like 'searchATOM-".$search."%'
				OR name like 'searchRSS-".$search."%')");
		if(!empty($searchEntries)) CacheControl::purgeItems($searchEntries);
		return true;
	}
	
	public static function flushEntry($entryId = null) {
		global $database;

		if(empty($entryId)) $entryId = '';
		else $entryId = intval($entryId);
		
		// Textcube-NG: 글 번호 없이 부르면 모든 글 캐시('entry-%')를 찾는다 — 원본은 'entry--%' 로 찾아 글 캐시를 하나도 지우지 않았다(r3-16, R-390)
		$Entries = POD::queryColumn("SELECT name
			FROM {$database['prefix']}PageCacheLog
			WHERE blogid = ".getBlogId()."
			AND (name like 'entry-".(empty($entryId) ? '' : $entryId."-")."%' OR ".(empty($entryId) ? "name like '%RSS-' OR name like '%ATOM-'" : "name IN ('commentRSS-$entryId','commentATOM-$entryId','trackbackRSS-$entryId','trackbackATOM-$entryId','responseRSS-$entryId','responseATOM-$entryId')").")"); // Textcube-NG: tagRSS-<같은 번호> 같은 다른 피드는 지우지 않음(r3-8)
		if(!empty($Entries)) CacheControl::purgeItems($Entries);
		if(!empty($entryId)) {
			$entry = POD::queryRow("SELECT userid, category FROM {$database['prefix']}Entries
				WHERE blogid = ".getBlogId()." AND id = $entryId");
			if(!empty($entry)) {
				CacheControl::flushAuthor($entry['userid']);
				CacheControl::flushCategory($entry['category']);
				CacheControl::flushDBCache('entry');
			}
			/* Textcube-NG: 글에 달린 태그의 목록(tagList-<태그>-<쪽>)·키워드·태그 피드 캐시도 비운다(v3.136) — 원본은 글을 고쳐도
			   태그별 목록이 예전 내용으로 남았다. flushEntry() 는 댓글·트랙백마다 불리므로 태그 페이지(tagPage)와 RSS 는 비우지 않는다
			   (태그 구성·공개 여부가 바뀔 때 Tag 클래스·updateEntry()·setEntryVisibility() 가 비운다) */
			$tagIds = POD::queryColumn("SELECT tag FROM {$database['prefix']}TagRelations
				WHERE blogid = ".getBlogId()." AND entry = $entryId");
			if(!empty($tagIds)) CacheControl::flushTag($tagIds, false);
		} else {
			CacheControl::flushAuthor();
			CacheControl::flushCategory();
			CacheControl::flushDBCache('entry');
			CacheControl::flushTag(null, false);	// Textcube-NG: 글 전체이면 태그별 목록·피드 캐시도 전부(v3.136)
		}
		return true;
	}
	public static function flushRSS() {
		if (file_exists(__TEXTCUBE_CACHE_DIR__."/rss/".getBlogId().".xml"))
			@unlink(__TEXTCUBE_CACHE_DIR__."/rss/".getBlogId().".xml");
		// Textcube-NG: 댓글·트랙백 피드 함수가 각각 부르던 flushResponseRSS() 는 빼고 아래에서 한 번만 부른다(r3-9, 503-15) — 비우는 캐시는 같다
		CacheControl::flushCommentRSS(null, false);
		CacheControl::flushTrackbackRSS(null, false);
		CacheControl::flushResponseRSS();
		CacheControl::flushSearchKeywordRSS();
	}

	/* Textcube-NG: $withResponse 가 false 이면 끝의 flushResponseRSS() 를 부르지 않는다(r3-9, 503-15). flushRSS() 만 false 로 부르고
	   끝에서 한 번 부른다. 다른 호출부(인자 1개 이하)는 원본과 같다. */
	public static function flushCommentRSS($entryId = null, $withResponse = true) {
		global $database;
		if(empty($entryId)) $entryId = '';
		$cache = pageCache::getInstance();
		$cache->name = 'commentRSS-'.$entryId;
		$cache->purge(); 
		$cache->reset();
		$cache->name = 'commentRSS';
		$cache->purge();
		$cache->reset();
		$cache->name = 'commentATOM-'.$entryId;
		$cache->purge();
		$cache->reset();
		$cache->name = 'commentATOM';
		$cache->purge();
		if($withResponse) CacheControl::flushResponseRSS($entryId);
		return true;
	}
	
	// Textcube-NG: $withResponse 는 flushCommentRSS() 와 같다(r3-9, 503-15)
	public static function flushTrackbackRSS($entryId = null, $withResponse = true) {
		if(empty($entryId)) $entryId = '';
		$cache = pageCache::getInstance();
		$cache->name = 'trackbackRSS-'.$entryId;
		$cache->purge();
		$cache->reset();
		$cache->name = 'trackbackRSS';
		$cache->purge();
		$cache->reset();
		$cache->name = 'trackbackATOM-'.$entryId;
		$cache->purge();
		$cache->reset();
		$cache->name = 'trackbackATOM';
		$cache->purge();
		if($withResponse) CacheControl::flushResponseRSS($entryId);
		return true;
	}
		
	public static function flushResponseRSS($entryId = null) {
		if(empty($entryId)) $entryId = '';
		$cache = pageCache::getInstance();
		$cache->name = 'responseRSS-'.$entryId;
		$cache->purge();
		$cache->reset();
		$cache->name = 'responseRSS';
		$cache->purge();
		$cache->reset();
		$cache->name = 'responseATOM-'.$entryId;
		$cache->purge();
		$cache->reset();
		$cache->name = 'responseATOM';
		$cache->purge();
		return true;
	}

	public static function flushCommentNotifyRSS() {
		global $database;
		$cache = pageCache::getInstance();
		$cache->name = 'commentNotifiedRSS';
		$cache->purge();
		$cache->reset();
		$cache->name = 'commentNotifiedATOM';
		$cache->purge();
		return true;
	}

	public static function flushItemsByPlugin($pluginName) {
		$xmls = new XMLStruct();
		$manifest = @file_get_contents(ROOT . "/plugins/$pluginName/index.xml");
		if ($manifest && $xmls->open($manifest)) {
			if ($xmls->doesExist('/plugin/binding/listener')) { //event listener가 있는 경우
				foreach ($xmls->selectNodes('/plugin/binding/listener') as $listener) {
					if (!empty($listener['.attributes']['event']) && !empty($listener['.value'])) { // Event가 있는 경우
						if(strpos(strtolower($listener['.attributes']['event']),'view')!==false) {
							CacheControl::flushCategory();
						}
					}
				}
				unset($listener);
			}
			if ($xmls->doesExist('/plugin/binding/tag')) {
				foreach ($xmls->selectNodes('/plugin/binding/tag') as $tag) {
					if (!empty($tag['.attributes']['name']) && !empty($tag['.attributes']['handler'])) {
						CacheControl::flushCategory();
						CacheControl::flushTag();
					}
				}
				unset($tag);
			}
//			if ($xmls->doesExist('/plugin/binding/sidebar')) {
//			TODO:	사이드바 캐시때 처리하도록 하지요.				
//			}
			if ($xmls->doesExist('/plugin/binding/formatter[lang()]')){
				CacheControl::flushCategory();
			}
			
		}
	}
	public static function flushDBCache($prefix = null) {
		$pool = queryCache::getInstance();
		$pool->reset('PageCacheLog',$prefix);
		return $pool->flush();
/*		
		global $database;
		$context = Model_Context::getInstance();
		if($context->getProperty('service.memcached') !== null) {
			$memcache = Cache_Memcache::getInstance();
			$memcache->reset('PageCacheLog',$prefix);
			return $memcache->flush();
		}
		return POD::query("DELETE FROM {$database['prefix']}PageCacheLog
			WHERE blogid = ".getBlogId()."
			AND name like '%".(!empty($prefix) ? $prefix.'-' : '')."queryCache%'");*/
	}
	public static function purgeItems($items) {
		if(!empty($items)) {
			$cache = pageCache::getInstance();
			foreach($items as $item){
				$cache->reset();
				$cache->name = $item;
				$cache->purge();
			}
		}	
	}
}

// MMCache is instant memory cache as table type data.
// Supports same methods as POD raw mode.
class MMCache{
	/*var $variable;*/
	
	//Variable must be the table form. (2-dimensional recursive structure)
	static function queryRow($var, $key, $value) {
		foreach($var as $row){
			if(isset($row[$key]) && $row[$key] == $value) return $row;
		}
		return false;
	}
	static function queryAll($var, $key, $value) {
		$result = array();
		foreach($var as $row){
			if(isset($row[$key]) && $row[$key] == $value) array_push($result, $row);
		}
		return $result;
	}
	static function queryColumn($var, $key, $value, $column){
		$result = array();
		foreach($var as $row){
			if(isset($row[$key]) && $row[$key] == $value) array_push($result, $row[$column]);
		}
		return $result;
	}
}

#[AllowDynamicProperties]
class CodeCache {
	function __construct() {
		$this->reset();
	}
	
	private function reset() {
		$this->code =
		$this->name =
		$this->fileName =
		null;
		$this->sources = array();
	}
	
	private function initialize() {
		 if (!is_dir(__TEXTCUBE_CACHE_DIR__."/code")){
			 @mkdir(__TEXTCUBE_CACHE_DIR__."/code");
			 @chmod(__TEXTCUBE_CACHE_DIR__."/code",0777);
		 }
	}
	
	public function save() {
		if(!empty($this->name)) $this->__getCodes();	// Get source codes.
		if(empty($this->code)) return $this->_error(2);
		$this->initialize();
		$this->fileName = __TEXTCUBE_CACHE_DIR__."/code/".$this->name;
		$fileHandle = fopen($this->fileName,'w');
		if(fwrite($fileHandle, $this->code)){
			fclose($fileHandle);
			@chmod($this->fileName, 0666);
			return true;
		}
		fclose($fileHandle);
		return $this->_error(3);
	}
	
	/*@ private @*/
	private function __getCodes() {
		$code = '';
		foreach($this->sources as $lib) {
			if(strpos($lib,'DEBUG') === false) $code .= file_get_contents(ROOT .$lib);
		}
		$this->code = $code;
	}
	
	public function flush() {
		$this->initialize();
		foreach (new DirectoryIterator(__TEXTCUBE_CACHE_DIR__."/code") as $code) {
			if($code->isFile()) @unlink($code->getPathname());
		}
		return true;
	}
	private function _error($err = 0) {
		var_dump($err);
		return false;
		//return $err;
	}
}
?>
