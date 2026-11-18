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

final class Model_Config extends Singleton {
	public $database, $service, $memcached;
	private $settings, $backend_name;

	public static function getInstance() {
		return self::_getInstance(__CLASS__);
	}

	public function __construct() {
		$this->__basicConfigLoader('textcube');
	}
	
	private function __basicConfigLoader($id) {
		global $database, $service, $memcached;	// For Legacy global variable support
		$this->settings = array();
		if (file_exists(ROOT.'/framework/id/load')) $id = trim(file_get_contents(ROOT.'/framework/id/load'));
		require_once(ROOT.'/framework/id/'.$id.'/config.default.php');	// Loading default configuration
		if (file_exists(__TEXTCUBE_CONFIG_FILE__)) @include(__TEXTCUBE_CONFIG_FILE__);	// Override configuration
		// Textcube-NG: Host 헤더 검사 — config.php 에 적은 포트(요청 포트로 덮어쓰기 전)를 남긴다(v3.205)
		$service['configuredPort'] = isset($service['port']) ? $service['port'] : null;
		// Map port setting.
		// Textcube-NG: Host 헤더 검사 — useConfigPort 가 켜져 있고 config.php 에 포트를 적었으면 요청 포트로 덮어쓰지 않는다(v3.205)
		if (@is_numeric($_SERVER['SERVER_PORT']) && ($_SERVER['SERVER_PORT'] != 80) && ($_SERVER['SERVER_PORT'] != 443) && !(!empty($service['useConfigPort']) && !empty($service['configuredPort'])))
			$service['port'] = $_SERVER['SERVER_PORT'];
		
		// Include installation configuration.
		if(!isset($service['session_cookie_path'])) $service['session_cookie_path'] = '/';
		// Set service path.
		// Textcube-NG: 빈 값('')은 서비스 주소를 강제 지정하지 않은 것(자동)으로 본다. 서비스 설정 화면에서 비워 저장하면 '' 로 기록되는데
		// 그대로 쓰면 스킨·피드 등의 주소가 호스트 없는 경로가 된다(v3.139)
		if(isset($serviceURL) && $serviceURL !== '') $service['serviceURL'] = $serviceURL;
		// Set resource path.
		if($service['externalresources']) {
			if(isset($service['resourceURL']) && !empty($service['resourceURL'])) 
				$service['resourcepath'] = $service['resourceURL'];
			else 
				$service['resourcepath'] = TEXTCUBE_RESOURCE_URL;
		} else {
			$service['resourcepath'] = $service['path'].'/resources';
		}
		
		// Database setting.
		if(isset($service['dbms'])) {
			if($service['dbms'] == 'mysql' && class_exists('mysqli')) $service['dbms'] = 'mysqli';
		}
		
		// Session cookie patch.
		if(!empty($service['domain']) && strstr( $_SERVER['HTTP_HOST'], $service['domain'] ) ) {
			$service['session_cookie_domain'] = $service['domain'];
		} else {
			$service['session_cookie_domain'] = $_SERVER['HTTP_HOST'];
		}
		if ($service['session_cookie_domain'] == 'localhost')
			$service['session_cookie_domain'] = null;
		// Textcube-NG: Host 헤더 검사 — IPv6 주소 Host([…])는 쿠키 domain 속성에 쓸 수 없으므로 지정하지 않는다(v3.205)
		if (substr($_SERVER['HTTP_HOST'], 0, 1) === '[') $service['session_cookie_domain'] = null;

		$this->database = $database;
		$this->service = $service;
		$this->memcached = isset($memcached) ? $memcached : [];
		$this->backend_name = isset($service['dbms']) ? $service['dbms'] : 'mysql';
		$this->updateContext();
	}

	public function updateContext($ns = null) {
		$context = Model_Context::getInstance();
		if(!is_null($ns)) {
			$configs = array($ns);
		} else {
			$context->setProperty('backend_name',$this->backend_name);
			$configs = array('database','service');
			if (!empty($this->memcached)) $configs[] = 'memcached';
		}
		foreach ($configs as $namespace):
			foreach ($this->$namespace as $k => $v):
				$context->setProperty($k,$v,$namespace);
			endforeach;
		endforeach;
	}

	function __get($name) {
		$val = NULL;
		switch ($name) {
			case 'database':
				$val = $this->database;
				break;
			case 'service':
				$val = $this->service;
				break;
			case 'memcached':
				$val = $this->memcached;
				break;
			default:
				$val = $this->settings[$name];
				break;
		}
		return $val;
	}
	
	public function set($category, $name, $value) {
		$this->$category[$name] = $value;	
	}
}

?>
