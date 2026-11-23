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

/**
 * @requires Model_Context
 */

class Cache_Memcache extends Singleton {
	private $memcache;
	private $__namespace;
	private $__value;
	private $__qualifiers;

	public static function getInstance() {
		return self::_getInstance(__CLASS__);
	}
	public function __construct() {
		$context = Model_Context::getInstance();
		$this->__qualifiers = array();
		if($context->getProperty('service.memcached') == true):
			$this->memcache = new Memcache;
			$server = !is_null($context->getProperty('memcached.server')) ? $context->getProperty('memcached.server') : 'localhost';
			$port   = !is_null($context->getProperty('memcached.port'))   ? (int) $context->getProperty('memcached.port') : 11211;
			if (strncmp((string)$server, 'unix://', 7) === 0) $port = 0;	// Textcube-NG: 유닉스 소켓(unix:///경로)은 포트 0(r3-20)
			$this->memcache->connect($server, $port);
		endif;
	}
	public function __destruct() {		
	}	
	/// Default methods
	public function set($key, $value, $expirationDue = 0) {
		if(strpos($key,'.') === false) {	// If key contains namespace, use it.
			if (!empty($this->__namespace)) {
				$key = $this->getNamespaceHash().'.'.$key;
			} else {
				$key = $this->getNamespaceHash('global').'.'.$key;
			}
		}
		$this->memcache->set($key,$value,0,$expirationDue);
	}
	public function get($key, $clear = false) {
		if(strpos($key,'.') === false) {	// If key doesn't contain namespace,
			if (!empty($this->__namespace)) $key = $this->getNamespaceHash().'.'.$key;
			else $key = $this->getNamespaceHash('global').'.'.$key;
		}
		return $this->memcache->get($key);
	}
	public function purge($key) {
		if(strpos($key,'.') === false) {	// If key doesn't contain namespace,
			if (!empty($this->__namespace)) $key = $this->getNamespaceHash().'.'.$key;
			else $key = $this->getNamespaceHash('global').'.'.$key;
		}
		return $this->memcache->delete($key);
	}
	public function flush() {
		if(is_null($this->__namespace)) return $this->memcache->flush();
		else $this->renewNamespaceHash($this->__namespace);
	}
	/// Namespaces
	public function useNamespace($ns = null) {
		if(is_null($ns)) $this->__namespace = null;
		else $this->__namespace = $ns;
	}
	public function getNamespace() {
		return $this->__namespace;
	}

	/// Compatibility layer via Data_IModel
	public function reset($ns,$param = '') {
		$this->__qualifiers = array();
		$this->useNamespace($ns.$param);
	}
	public function setQualifier($key, $condition, $value, $param = null) {
		array_push($this->__qualifiers,$key.$condition.$value);
	}
	public function setAttribute($key, $value = null, $param = null) {
		$this->__value = $value;	// Last attribute set will be used as key-value pair index.
		return true;
	}
	public function getAll() {
		return $this->getCell();
	}
	public function getCell() {
		return $this->get($this->getKeyFromQualifiers());
	}
	public function insert(){
		// Textcube-NG: config.php $memcached['cache_ttl'](초, 0 = 만료 없음 = 원본 동작, 최대 2592000)을 캐시 항목에 준다(r3-20)
		$ttl = (int) Model_Context::getInstance()->getProperty('memcached.cache_ttl', 0);
		if ($ttl < 0) $ttl = 0;
		if ($ttl > 2592000) $ttl = 2592000;
		return $this->set($this->getKeyFromQualifiers(),$this->__value,$ttl);
	}
	public function delete(){
		return $this->purge($this->getKeyFromQualifiers());
	}
	public function replace(){
		return $this->insert();
	}

	/// Private methods
	private function getNamespaceHash($ns = null, $renew = false) {
		$context = Model_Context::getInstance();
		if(is_null($ns)) $ns = $this->__namespace;
		$prefix = $context->getProperty('service.domain').'-'.$context->getProperty('blog.id').'-'.$ns.'-';
		if($renew !== false) $namehash = false;
		else $namehash = $this->memcache->get($prefix);
		if($namehash == false) {
			$seed = dechex(random_int(0x10000000, 0x7FFFFFFF)).dechex(random_int(0x10000000, 0x7FFFFFFF));
			$this->memcache->set($prefix,$seed);
			return $seed;
		} else {
			return $namehash;
		}
	}
	private function renewNamespaceHash($ns = null) {
		return $this->getNamespaceHash($ns, true);
	}
	private function getKeyFromQualifiers() {
		asort($this->__qualifiers);
		return abs(crc32(implode('-',$this->__qualifiers)));
	}
}
?>
