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
class SkinSetting {
	function __construct() {
		$this->reset();
	}

	function reset() {
		$this->error =
		$this->skin =
		$this->entriesOnRecent =
		$this->commentsOnRecent =
		$this->trackbacksOnRecent =
		$this->commentsOnGuestbook =
		$this->tagsOnTagbox =
		$this->alignOnTagbox =
		$this->expandComment =
		$this->expandTrackback =
		$this->recentNoticeLength =
		$this->recentEntryLength =
		$this->recentTrackbackLength =
		$this->linkLength =
		$this->showListOnCategory =
		$this->showListOnArchive =
		$this->tree =
		$this->colorOnTree =
		$this->bgcolorOnTree =
		$this->activecolorOnTree =
		$this->activebgcolorOnTree =
		$this->labelLengthOnTree =
		$this->showValueOnTree =
			null;
	}
	
	function load($fields = '*') {
		global $database;
		$this->reset();
		$pool = DBModel::getInstance();
		$pool->reset('SkinSettings');
		$pool->setQualifier('blogid','equals',getBlogId());
		$result = $pool->getAll('name, value');
		if (!empty($result)) {
			foreach ($result as $data) {
				if (!empty($data['name'])) {	// Textcube-NG: r4-9 — $date 오타(원본부터)로 load() 가 값을 하나도 읽지 않아 데이터 내보내기 <skin> 이 빈 값이었다
					if ($data['name'] == 'tagboxAlign') $data['name'] = 'alignOnTagbox';
					$this->{$data['name']} = $data['value'];	// Textcube-NG: r4-9 — PHP 7.0 균일 변수 문법(Uniform Variable Syntax)으로 $this->$data['name'] 의 해석이 ($this->$data)['name'] 으로 바뀜 — 원본(PHP 5)의 뜻 $this->{$data['name']} 을 중괄호로 밝힘
				}
			}
			return true;
		}
		return false;
	}
	
	function save() {
		if (isset($this->skin)) {
			if (!is_string($this->skin))	// [보안] Textcube-NG: 스킨 이름은 문자열이고 index.xml 이 있는 스킨 폴더만(r3-7, 결정 498)
				return $this->_error('skin');
			if (strncmp($this->skin, 'customize/', 10) == 0) {
				if (strcmp($this->skin, "customize/".getBlogId()) != 0)
					return $this->_error('skin');
			} else {
				if (!Validator::filename($this->skin))
					return $this->_error('skin');
			}
			if (!Validator::path($this->skin) || !file_exists(ROOT . '/skin/' . $this->skin . '/index.xml'))
				return $this->_error('skin');
			Setting::setSkinSetting('skin', $this->skin);
		}
		if (isset($this->entriesOnRecent)) {
			if (!Validator::number($this->entriesOnRecent, 1))
				return $this->_error('entriesOnRecent');
			Setting::setSkinSetting('entriesOnRecent', $this->entriesOnRecent);
		}
		if (isset($this->commentsOnRecent)) {
			if (!Validator::number($this->commentsOnRecent, 1))
				return $this->_error('commentsOnRecent');
			Setting::setSkinSetting('commentsOnRecent', $this->commentsOnRecent);
		}
		if (isset($this->trackbacksOnRecent)) {
			if (!Validator::number($this->trackbacksOnRecent, 1))
				return $this->_error('trackbacksOnRecent');
			Setting::setSkinSetting('trackbacksOnRecent', $this->trackbacksOnRecent);
		}
		if (isset($this->commentsOnGuestbook)) {
			if (!Validator::number($this->commentsOnGuestbook, 1))
				return $this->_error('commentsOnGuestbook');
			Setting::setSkinSetting('commentsOnGuestbook', $this->commentsOnGuestbook);
		}
		if (isset($this->tagsOnTagbox)) {
			if (!Validator::number($this->tagsOnTagbox, 1))
				return $this->_error('tagsOnTagbox');
			Setting::setSkinSetting('tagsOnTagbox', $this->tagsOnTagbox);
		}
		if (isset($this->alignOnTagbox)) {
			if (!Validator::number($this->alignOnTagbox, 1, 3))
				return $this->_error('alignOnTagbox');
			Setting::setSkinSetting('tagboxAlign', $this->alignOnTagbox);
		}
		if (isset($this->expandComment))
			Setting::setSkinSetting('expandComment', Validator::getBit($this->expandComment));
		if (isset($this->expandTrackback))
			Setting::setSkinSetting('expandTrackback', Validator::getBit($this->expandTrackback));
		if (isset($this->recentNoticeLength)) {
			if (!Validator::number($this->recentNoticeLength, 0))
				return $this->_error('recentNoticeLength');
			Setting::setSkinSetting('recentNoticeLength', $this->recentNoticeLength);
		}
		if (isset($this->recentEntryLength)) {	// Textcube-NG: r4-14 — 원본부터 빠져 가져오기에서 이 값만 복원되지 않았다(dojoref 2.5)
			if (!Validator::number($this->recentEntryLength, 0))
				return $this->_error('recentEntryLength');
			Setting::setSkinSetting('recentEntryLength', $this->recentEntryLength);
		}
		if (isset($this->recentTrackbackLength)) {
			if (!Validator::number($this->recentTrackbackLength, 0))
				return $this->_error('recentTrackbackLength');
			Setting::setSkinSetting('recentTrackbackLength', $this->recentTrackbackLength);
		}
		if (isset($this->linkLength)) {
			if (!Validator::number($this->linkLength, 0))
				return $this->_error('linkLength');
			Setting::setSkinSetting('linkLength', $this->linkLength);
		}
		if (isset($this->showListOnCategory))
			Setting::setSkinSetting('showListOnCategory', Validator::getBit($this->showListOnCategory));
		if (isset($this->showListOnArchive))
			Setting::setSkinSetting('showListOnArchive', Validator::getBit($this->showListOnArchive));
		if (isset($this->tree)) {
			if (!Validator::directory($this->tree) || !file_exists(ROOT . '/skin/tree/' . $this->tree))
				return $this->_error('tree');
			Setting::setSkinSetting('tree', $this->tree);
		}
		if (isset($this->colorOnTree))
			Setting::setSkinSetting('colorOnTree', $this->colorOnTree);
		if (isset($this->bgcolorOnTree))
			Setting::setSkinSetting('bgcolorOnTree', $this->bgcolorOnTree);
		if (isset($this->activecolorOnTree))
			Setting::setSkinSetting('activecolorOnTree', $this->activecolorOnTree);
		if (isset($this->activebgcolorOnTree))
			Setting::setSkinSetting('activebgcolorOnTree', $this->activebgcolorOnTree);
		if (isset($this->labelLengthOnTree)) {
			if (!Validator::number($this->labelLengthOnTree, 0))
				return $this->_error('labelLengthOnTree');
			Setting::setSkinSetting('labelLengthOnTree', $this->labelLengthOnTree);
		}
		if (isset($this->showValueOnTree))
			Setting::setSkinSetting('showValueOnTree', Validator::getBit($this->showValueOnTree));
		return true;
	}

	function _error($error) {
		$this->error = $error;
		return false;
	}
}
?>
