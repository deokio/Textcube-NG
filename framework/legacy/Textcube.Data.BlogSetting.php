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
class BlogSetting {
	function __construct() {
		$this->reset();
	}

	function reset() {
		$this->error =
		$this->name =
		$this->secondaryDomain =
		$this->defaultDomain =
		$this->title =
		$this->description =
		$this->banner =
		$this->useSloganOnPost =
		$this->useSloganOnCategory =
		$this->useSloganOnTag =
		$this->entriesOnPage =
		$this->entriesOnList =
		$this->postsOnFeed =		
		$this->publishWholeOnFeed =
		$this->acceptGuestComment =
		$this->acceptcommentOnGuestComment =
		$this->language =
		$this->timezone =
			null;
	}
	
	function load($fields = '*') {
		global $database;
		$blogid = getBlogId();
		$this->reset();
		$query = DBModel::getInstance();
		$query->reset('BlogSettings');
		if($query->doesExist()){
			$query->setQualifier('blogid','equals',$blogid);
			$blogSettings = $query -> getAll('name,value');
			if(isset($blogSettings)){
				foreach ($blogSettings as $blogSetting) {
					$name  = $blogSetting['name'];
					$value = $blogSetting['value']; 
					switch ($name) {
						case 'logo':
							$name = 'banner';
							break;
						case 'entriesOnPage':
							$name = 'postsOnPage';
							break;
						case 'entriesOnList':
							$name = 'postsOnList';
							break;
						case 'entriesOnRSS':
							$name = 'postsOnFeed';
							break;
						case 'publishWholeOnRSS':
							$name = 'publishWholeOnFeed';
							break;
						case 'allowWriteOnGuestbook':
							$name = 'acceptGuestComment';
							break;
						case 'allowWriteDblCommentOnGuestbook':
							$name = 'acceptcommentOnGuestComment';
							break;
						case 'defaultDomain':
						case 'useSloganOnPost':
						case 'useSloganOnCategory':
						case 'useSloganOnTag':
						case 'acceptGuestComment':
						case 'acceptcommentOnGuestComment':
							$value = $value ? true : false;
							break;
					}
					$this->$name = $value;
				}
			}
			return true;
		}
		return false;
	}
	
	function save() {
		global $database;
		requireModel('common.setting');
		// Textcube-NG: 설정마다 저장 결과를 확인해 실패하면 그 속성 이름으로 _error(false)를 돌려준다. 원본은 저장 실패를 무시하고 늘 true 를 돌려줘
		// 가져오기(data/import)·시간대 설정 등 호출부가 실패를 알 수 없었다. 실패 앞까지 저장된 값은 그대로 남는다(원본도 단계별 저장)(r3-5)

		if (isset($this->name)) {
			$this->name = trim($this->name);
			if (!BlogSetting::validateName($this->name))
				return $this->_error('name');
			if (!setBlogSetting('name', $this->name))
				return $this->_error('name');
		}
		if (isset($this->secondaryDomain)) {
			$this->secondaryDomain = trim($this->secondaryDomain);
			if (!Validator::domain($this->secondaryDomain))
				return $this->_error('secondaryDomain');
			if (!setBlogSetting('secondaryDomain', $this->secondaryDomain))
				return $this->_error('secondaryDomain');
		}
		if (isset($this->defaultDomain) && !setBlogSetting('defaultDomain', Validator::getBit($this->defaultDomain)))
			return $this->_error('defaultDomain');
		if (isset($this->title)) {
			$this->title = trim($this->title);
			if (!setBlogSetting('title', $this->title))
				return $this->_error('title');
		}
		if (isset($this->description)) {
			$this->description = trim($this->description);
			if (!setBlogSetting('description', $this->description))
				return $this->_error('description');
		}
		if (isset($this->banner)) {
			if ((strlen($this->banner) != 0) && !Validator::filename($this->banner))
				return $this->_error('banner');
			if (!setBlogSetting('logo', $this->banner))
				return $this->_error('banner');
		}
		if (isset($this->useSloganOnPost) && !setBlogSetting('useSloganOnPost', Validator::getBit($this->useSloganOnPost)))
			return $this->_error('useSloganOnPost');
		if (isset($this->useSloganOnCategory) && !setBlogSetting('useSloganOnCategory', Validator::getBit($this->useSloganOnCategory)))
			return $this->_error('useSloganOnCategory');
		if (isset($this->useSloganOnTag) && !setBlogSetting('useSloganOnTag', Validator::getBit($this->useSloganOnTag)))
			return $this->_error('useSloganOnTag');
		if (isset($this->postsOnPage)) {
			if (!Validator::number($this->postsOnPage, 1))
				return $this->_error('postsOnPage');
			if (!setBlogSetting('entriesOnPage', $this->postsOnPage))
				return $this->_error('postsOnPage');
		}
		if (isset($this->postsOnList)) {
			if (!Validator::number($this->postsOnList, 1))
				return $this->_error('postsOnList');
			if (!setBlogSetting('entriesOnList', $this->postsOnList))
				return $this->_error('postsOnList');
		}
		if (isset($this->postsOnFeed)) {
			if (!Validator::number($this->postsOnFeed, 1))
				return $this->_error('postsOnFeed');
			if (!setBlogSetting('entriesOnRSS', $this->postsOnFeed))
				return $this->_error('postsOnFeed');
		}
		if (isset($this->publishWholeOnFeed) && !setBlogSetting('publishWholeOnRSS', Validator::getBit($this->publishWholeOnFeed)))
			return $this->_error('publishWholeOnFeed');
		if (isset($this->acceptGuestComment) && !setBlogSetting('allowWriteOnGuestbook', Validator::getBit($this->acceptGuestComment)))
			return $this->_error('acceptGuestComment');
		if (isset($this->acceptcommentOnGuestComment) && !setBlogSetting('allowWriteDblCommentOnGuestbook', Validator::getBit($this->acceptcommentOnGuestComment)))
			return $this->_error('acceptcommentOnGuestComment');
		if (isset($this->language)) {
			if (!Validator::language($this->language))
				return $this->_error('language');
			// PHP port(Textcube-NG): r3-10 R-289 8.x 이식본(v3.198)과 같게 — save() 는 setBlogLanguage() 를 거치지 않아, 언어를 바꿔도 PluginSettings 페이지 캐시(요청 언어로 만든 문구)가
			// 남아 이전 언어로 나왔다. 값이 바뀌고 저장에 성공했을 때만 setBlogLanguage() 와 같은 getInstance/reset/purge 로 비운다
			$languageChanged = (getBlogSetting('language') != $this->language);
			if (!setBlogSetting('language', $this->language))
				return $this->_error('language');
			if ($languageChanged) {
				$pageCache = pageCache::getInstance();
				$pageCache->reset('PluginSettings');
				$pageCache->purge();
			}
		}
		if (isset($this->timezone)) {
			// [보안] 빈 값뿐 아니라 PHP 시간대 식별자가 아닌 값도 거부(TTXML 복원 등에서 검사 없이 저장되던 경로)
			if (empty($this->timezone) || !Timezone::isValid($this->timezone))
				return $this->_error('timezone');
			if (!setBlogSetting('timezone', $this->timezone))
				return $this->_error('timezone');
		}
		return true;
	}
	
	function escape($escape = true) {
		$this->name = Validator::escapeXML(@$this->name, $escape);
		$this->secondaryDomain = Validator::escapeXML(@$this->secondaryDomain, $escape);
		$this->title = Validator::escapeXML(@$this->title, $escape);
		$this->description = Validator::escapeXML(@$this->description, $escape);
	}
	
	/*@static@*/
	static function setTimezone($timezone) {
		if (Timezone::set($timezone)) {
			$setting = new BlogSetting();
			$setting->timezone = $timezone;
			return $setting->save();
		}
	}

	/*@static@*/
	static function validateName($name) {
		return preg_match('/^[a-zA-Z0-9]+$/', $name);
	}

	function _error($error) {
		$this->error = $error;
		return false;
	}
}
?>
