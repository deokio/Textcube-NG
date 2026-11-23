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

/**
 * This class implements message queue.
 */
#[AllowDynamicProperties]
class Message extends Singleton {
	private static $__storageTemplate = array(
			'id'=>0,
			'status'=>'OK',
			'visibility'=>'public',
			'message'=>'Default message',
			'detail'=>'');
	private static $IV = array(
			'category'=>array('string','default'=>'common'),
			'status'=>array('string','default'=>'OK'),
			'visibility'=>array('string','default'=>'public'),
			'message'=>array('string','default'=>'Default message'),
			'detail'=>array('string','default'=>'','mandatory'=>false)
			);
	private static int $__maxid = 0;
	private static $__storage = array();
	public static function getInstance() {
		return self::_getInstance(__CLASS__);
	}

	/*@constructor@*/
	function __construct() {
	}
	public function add($message) {
		// Textcube-NG: r4-19 — 명백한 오류만 고침(사용자 결정 11-24): 없는 함수 validateiArray → validateArray(10-CoreClasses.php),
		//   정적 속성을 지역 변수($IV·$__maxid)·$this 로 읽던 것을 self:: 로. 쓰이지 않는 클래스(호출처 0건, dojoref 2.7)
		Validator::validateArray($message, self::$IV);
		$message['id'] = self::$__maxid++;
		array_push(self::$__storage,$message);
		return $message['id'];
	}
	public function remove($condition) {
	}
	public function print($mode) {
	}
	public function get($condition = null) {
		$result = array();
		if(is_null($condition)) return self::$__storage;
		if(isset($condition['category'])) {
			if(is_array($condition['category'])) {
				foreach ($condition['category'] as $cat) {
					array_push($result, MMCache::queryAll(self::$__storage,'category',$cat));
				}
			} else {
				array_push($result, MMCache::queryAll(self::$__storage,'category',$condition['category']));
			}
		}
		if(isset($condition['status'])) {
			switch($condition['status']) {
				case 'OK':
					array_push($result, MMCache::queryAll(self::$__storage,'status','OK'));
				case 'WARNING':
					array_push($result, MMCache::queryAll(self::$__storage,'status','WARNING'));
				case 'ERROR':
				default:
					array_push($result, MMCache::queryAll(self::$__storage,'status','OK'));
			}
		}
		return $result;
	}
}
?>
