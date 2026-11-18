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

class URIError extends Exception {};

#[AllowDynamicProperties]
final class Model_URIHandler extends Singleton
{
	public $uri, $suri;
	public $blogid;
	public static function getInstance() {
		return self::_getInstance(__CLASS__);
	}

	public function __construct() {
		$this->__URIInterpreter();
		$this->context = Model_Context::getInstance();
	}

	public function URIParser() { $this->__URIParser();}
	public function VariableParser() { $this->__URIvariableParser();}
	// Textcube-NG: 이 요청이 https 인지 — 신뢰 프록시 플러그인 판정(proxy.scheme), config.php trustedProxies 접속의 X-Forwarded-Proto,
	// 그 밖에는 서버 변수(HTTPS·REQUEST_SCHEME)만 본다. VariableParser 의 uri.service 스킴 판정(v3.151)과 HSTS(preprocessor)가 함께 쓴다(r3-20)
	public function isSecureRequest() {
		$xfp = '';
		if ($this->context->getProperty('proxy.trusted') === true)
			$xfp = (string)$this->context->getProperty('proxy.scheme');
		else if (defined('__TEXTCUBE_TRUSTED_PEER__') && isset($_SERVER['HTTP_X_FORWARDED_PROTO']))
			$xfp = strtolower(trim($_SERVER['HTTP_X_FORWARDED_PROTO']));
		if ($xfp === 'https' || $xfp === 'http') return $xfp === 'https';
		if (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') return true;
		return isset($_SERVER['REQUEST_SCHEME']) && $_SERVER['REQUEST_SCHEME'] === 'https';
	}

	private function __URIInterpreter() {
		$dispatcher = Dispatcher::getInstance();
		$this->uri = $dispatcher->uri;
	}

	private function __URIParser() {
		if(!isset($this->uri)) $this->__URIInterpreter();
		$this->context->useNamespace('service');

		$url             = $this->uri['fullpath'];
		$defaultblogid   = Setting::getServiceSetting("defaultBlogId",1,true);
		$this->suri            = array('url' => $url, 'value' => '');
		$this->blogid    = null;
		$this->uri['isStrictBlogURL'] = true;
		$depth           = substr_count($this->context->getProperty('path'), '/');
		if ($depth > 0) {
			if (preg_match('@^((/+[^/]+){' . $depth . '})(.*)$@', $url, $matches) !== FALSE)
				$url = $matches[3];
			else
				Respond::NotFoundPage();
		}
		// Textcube-NG: Host 헤더 검사 — single·path 형식에서 Host 가 domain·hostAliases 와 다르면 hostMismatchBehavior 에 따라 '404' 는
		// 찾을 수 없음, 'redirect' 는 GET·HEAD 를 domain 주소로 302(그 밖의 요청은 지금처럼 처리). '' 는 지금처럼 처리한다(v3.205)
		$hostMismatchBehavior = $this->context->getProperty('hostMismatchBehavior');
		if (($hostMismatchBehavior === 'redirect' || $hostMismatchBehavior === '404')
			&& ($this->context->getProperty('type') == 'single' || $this->context->getProperty('type') == 'path') && (string)$this->context->getProperty('domain') !== '') {
			$hostAliases = $this->context->getProperty('hostAliases');
			if (!is_array($hostAliases)) $hostAliases = array();
			$requestPort = (string)$this->context->getProperty('port');
			if ($_SERVER['HTTP_HOST'] !== (string)$this->context->getProperty('domain')
				&& !in_array($_SERVER['HTTP_HOST'], $hostAliases, true)
				&& !in_array($_SERVER['HTTP_HOST'] . ($requestPort !== '' ? ':' . $requestPort : ''), $hostAliases, true)) {
				if ($hostMismatchBehavior === '404') Respond::NotFoundPage();
				$requestMethod = isset($_SERVER['REQUEST_METHOD']) ? strtoupper((string)$_SERVER['REQUEST_METHOD']) : '';
				if ($requestMethod === 'GET' || $requestMethod === 'HEAD') {
					$requestURI = isset($_SERVER['REQUEST_URI']) ? (string)$_SERVER['REQUEST_URI'] : '';
					if ($requestURI === '' || $requestURI[0] !== '/') $requestURI = $this->context->getProperty('path') . '/';
					header('Location: ' . ($this->context->getProperty('service.useSSL', false) ? 'https://' : 'http://') . $this->context->getProperty('domain') . ($requestPort !== '' ? ':' . $requestPort : '') . $requestURI, true, 302);
					exit;
				}
			}
		}
		if ($this->context->getProperty('type') == 'single') {
			$this->blogid = $defaultblogid;
		} else {
			if ($this->context->getProperty('type') == 'domain') {	// Domain-based service
				if ($_SERVER['HTTP_HOST'] == $this->context->getProperty('domain')) {
					// Root domain (no subdomain) — check configured behavior
					$domainRootBehavior = $this->context->getProperty('domainRootBehavior');
					if (empty($domainRootBehavior)) $domainRootBehavior = Setting::getServiceSettingGlobal('domainRootBehavior', '');
					if ($domainRootBehavior === 'redirect') {
						$redirectURL = $this->context->getProperty('domainRootRedirectURL');
						if (empty($redirectURL)) $redirectURL = Setting::getServiceSettingGlobal('domainRootRedirectURL', '');
						if (!empty($redirectURL)) { header('Location: ' . $redirectURL, true, 302); exit; }
					} elseif ($domainRootBehavior === 'blog') {
						$configBlogId = (int)$this->context->getProperty('domainRootBlogId');
						if (!$configBlogId) $configBlogId = (int)Setting::getServiceSettingGlobal('domainRootBlogId', 0);
						if ($configBlogId) $this->blogid = $configBlogId;
					} elseif ($domainRootBehavior === '404') {
						Respond::NotFoundPage();
					}
					if ($this->blogid === null) $this->blogid = $defaultblogid;
				} else {
					$domain = explode('.', $_SERVER['HTTP_HOST'], 2);
					// Textcube-NG: 점이 없는 Host(빈 값 포함)는 $domain[1] 이 없어 PHP 8 경고가 났다. 원본과 같은 null 비교(v3.140)
					if ((isset($domain[1]) ? $domain[1] : null) == $this->context->getProperty('domain')) {
						$this->blogid = $this->__getBlogIdByName($domain[0]);
						if ($this->blogid === null)
							$this->blogid = $this->__getBlogIdBySecondaryDomain($_SERVER['HTTP_HOST']);
						} else {
							$this->blogid = $this->__getBlogIdBySecondaryDomain($_SERVER['HTTP_HOST']);
						}
					// Domain not matched — check configured mismatch behavior
					if ($this->blogid === null) {
						$domainMismatchBehavior = $this->context->getProperty('domainMismatchBehavior');
						if (empty($domainMismatchBehavior)) $domainMismatchBehavior = Setting::getServiceSettingGlobal('domainMismatchBehavior', '');
						if ($domainMismatchBehavior === 'redirect') {
							$redirectURL = $this->context->getProperty('domainMismatchRedirectURL');
							if (empty($redirectURL)) $redirectURL = Setting::getServiceSettingGlobal('domainMismatchRedirectURL', '');
							if (!empty($redirectURL)) { header('Location: ' . $redirectURL, true, 302); exit; }
						} elseif ($domainMismatchBehavior === 'blog') {
							$configBlogId = (int)$this->context->getProperty('domainMismatchBlogId');
							if (!$configBlogId) $configBlogId = (int)Setting::getServiceSettingGlobal('domainMismatchBlogId', 0);
							if ($configBlogId) {
								$this->blogid = $configBlogId;
								$this->uri['isHostMismatch'] = true;
							}
						}
						// '404' or unhandled: leave blogid null → outer null check triggers NotFoundPage
					}
				}
			} else {	// Path-based service
				if ($url == '/') {
					// Priority 1: config.php $service['rootRedirectURL']
					$rootRedirectURL = $this->context->getProperty('rootRedirectURL');
					// Priority 2: DB setting managed via control/server
					if (empty($rootRedirectURL)) {
						$rootRedirectURL = Setting::getServiceSettingGlobal('rootRedirectURL', '');
					}
					if (!empty($rootRedirectURL)) {
						header('Location: ' . $rootRedirectURL, true, 302);
						exit;
					}
					// Priority 3: auto-redirect to first blog's path
					$firstBlog = Setting::getBlogSettingsGlobal($defaultblogid);
					if (!empty($firstBlog['name'])) {
						$portStr = !is_null($this->context->getProperty('port')) ? ':' . $this->context->getProperty('port') : '';
						$proto = $this->context->getProperty('useSSL', false) ? 'https://' : 'http://';
						header('Location: ' . $proto . $this->context->getProperty('domain') . $portStr . $this->context->getProperty('path') . '/' . $firstBlog['name'] . '/', true, 302);
						exit;
					}
					// Priority 4: fallback — serve default blog at root (original behavior)
					$this->blogid = $defaultblogid;
				} else if (isset($this->uri['interfacePath']) && $this->uri['interfacePath'] === 'interface/freeSlogan.php' && Dispatcher::isHostRootFreeSloganFile(isset($this->uri['input']) ? $this->uri['input'] : null)) {
					// Textcube-NG: path 모드 호스트 루트의 robots.txt·llms.txt(Dispatcher 가 등록 페이지 처리기로 넘긴 요청)는 블로그 이름이 없으므로 기본 블로그의 등록 페이지로 본다(r3-5)
					$this->blogid = $defaultblogid;
				} else if (preg_match('@^/+([^/]+)(.*)$@', $url, $matches)) {
					$this->blogid = $this->__getBlogIdByName(strtok($matches[1],'?'));
					if ($this->blogid === null) {
						$pathNotFoundBehavior = $this->context->getProperty('pathNotFoundBehavior');
						if (empty($pathNotFoundBehavior)) $pathNotFoundBehavior = Setting::getServiceSettingGlobal('pathNotFoundBehavior', '');
						if ($pathNotFoundBehavior === 'redirect') {
							$redirectURL = $this->context->getProperty('pathNotFoundRedirectURL');
							if (empty($redirectURL)) $redirectURL = Setting::getServiceSettingGlobal('pathNotFoundRedirectURL', '');
							if (!empty($redirectURL)) { header('Location: ' . $redirectURL, true, 302); exit; }
						} elseif ($pathNotFoundBehavior === '404') {
							Respond::NotFoundPage();
						}
						$this->blogid = $defaultblogid;
						$this->uri['isStrictBlogURL'] = false;
					}
					$url = $matches[2];
				} else {
					Respond::NotFoundPage();
				}
			}
			if ($this->blogid === null)
				Respond::NotFoundPage();
		}
		if(isset($this->uri['interfacePath'])) {
			if(strpos($this->uri['interfacePath'],'interface/blog/comment') === 0 ||
				strpos($this->uri['interfacePath'],'interface/blog/trackback') === 0) {
				$depth = substr_count($this->uri['interfacePath'], '/') - 2;
			} else {
				$depth = substr_count($this->uri['interfacePath'], '/') - 1;
			}
		} else {
			$depth = substr_count(ROOT, '/');
		}
		if ($depth > 0) {
			if($this->context->getProperty('fancyURL') === 0 || $this->context->getProperty('fancyURL') === 1) $url = '/'.$this->uri['input']; // Exclude /blog path.
			if (preg_match('@^((/+[^/]+){' . $depth . '})/*(.*)$@', $url, $matches)) {
				$this->suri['directive'] = $matches[1];
				if ($matches[3] !== false) {
					$this->suri['value'] = $matches[3];
				}
			} else {
				Respond::NotFoundPage();
			}
		} else {
			$this->suri['directive'] = '/';
			$this->suri['value'] = ltrim($url, '/');
		}
		if(strpos($this->suri['value'],'?') === 0) $this->suri['value'] = '';
		else $this->suri['value'] = strtok($this->suri['value'], '?');
		$this->suri['directive'] = strtok($this->suri['directive'], '?');
		if (is_numeric($this->suri['value'])) {
			$this->suri['id'] = $this->suri['value'];
		} else {
			$this->suri['value'] = URL::decode(str_replace('index.php','',$this->suri['value']));
			if(is_numeric($isValue = strtok($this->suri['value'],'&'))) $this->suri['id'] = $isValue;
			unset($isValue);
		}

		// Parse page.
		$this->suri['page'] = empty($_POST['page']) ? (empty($_GET['page']) ? true : $_GET['page']) : $_POST['page'];
	}

	private function __URIvariableParser() {
		global $suri, $blog, $blogid, $skinSetting, $gCacheStorage;
		$blogid        = $this->blogid;
		$gCacheStorage = globalCacheStorage::getInstance(); // Initialize global cache

		$suri        = $this->suri;
		$blog        = Setting::getBlogSettingsGlobal($this->blogid);
		$blog['id']  = $this->blogid;
		$skinSetting = Setting::getSkinSettings($this->blogid);

		if(!is_null($this->context->getProperty('service.serviceURL'))) {
			$this->uri['service'] = $this->context->getProperty('service.serviceURL');
		}
		if (!isset($this->uri['service'])) {
			/* [보안] X-Forwarded-Proto 를 모든 연결에서 믿어 방문자가 서비스 주소의 스킴을 바꿀 수 있었다 → 신뢰 프록시 플러그인이
			   신뢰한 연결은 그 판정(proxy.scheme), config.php trustedProxies 에 속한 연결은 이 헤더, 그 밖의 연결은 무시 (v3.151) */
			$proto = ($this->context->getProperty('service.useSSL', false) || $this->isSecureRequest()) ? 'https' : 'http';	// Textcube-NG: useSSL=true 면 https 고정(원본 130-132 행과 같음, r3-35), 아니면 isSecureRequest() 판정(HSTS 와 같은 규칙, r3-20)
			$this->uri['service'] = $proto . '://' . $_SERVER['HTTP_HOST'] . (!is_null($this->context->getProperty('service.port')) ? ':' . $this->context->getProperty('service.port') : '') . $this->context->getProperty('service.path');
		}
		$this->context->useNamespace('service');
		switch ($this->context->getProperty('service.type')) {
			case 'domain':
				$this->uri['path'] = $this->context->getProperty('path');
				$blog['primaryBlogURL'] = ($this->context->getProperty('service.useSSL',false) ? 'https://' : 'http://') . $blog['name'] . '.' . $this->context->getProperty('domain') . (!is_null($this->context->getProperty('port')) ? ':' . $this->context->getProperty('port') : '') . $this->uri['path'];
				if( !empty($blog['secondaryDomain']) )
					$blog['secondaryBlogURL'] = ($this->context->getProperty('service.useSSL',false) ? 'https://' : 'http://') . $blog['secondaryDomain'] . (!is_null($this->context->getProperty('port')) ? ':' . $this->context->getProperty('port') : '') . $this->uri['path'];
				else
					$blog['secondaryBlogURL'] = null;
				if ($blog['defaultDomain']) {
					$this->uri['default'] = $blog['secondaryBlogURL'];
					if ($_SERVER['HTTP_HOST'] == $blog['secondaryDomain'])
						$this->uri['base'] = $this->context->getProperty('path');
					else
						$this->uri['base'] = $this->uri['default'];
				} else {
					$this->uri['default'] = $blog['primaryBlogURL'];
					if ($_SERVER['HTTP_HOST'] == ($blog['name'] . '.' . $this->context->getProperty('domain')))
						$this->uri['base'] = $this->context->getProperty('path');
					else
						$this->uri['base'] = $this->uri['default'];
				}
				break;
			case 'path':
				$this->uri['path'] = $this->context->getProperty('path') . '/' . $blog['name'];
				$blog['primaryBlogURL'] = ($this->context->getProperty('service.useSSL',false) ? 'https://' : 'http://') . $this->context->getProperty('domain') . (!is_null($this->context->getProperty('port')) ? ':' . $this->context->getProperty('port') : '') . $this->uri['path'];
				$blog['secondaryBlogURL'] = null;
				$this->uri['default'] = $blog['primaryBlogURL'];
				if ($_SERVER['HTTP_HOST'] == $this->context->getProperty('domain'))
					$this->uri['base'] = $this->context->getProperty('path') . '/' . $blog['name'];
				else
					$this->uri['base'] = $this->uri['default'];
				break;
			case 'single':
			default:
				$this->uri['path'] = $this->context->getProperty('path');
				$blog['primaryBlogURL'] = ($this->context->getProperty('service.useSSL',false) ? 'https://' : 'http://') . $this->context->getProperty('domain') . (!is_null($this->context->getProperty('port')) ? ':' . $this->context->getProperty('port') : '') . $this->uri['path'];
				$blog['secondaryBlogURL'] = null;
				$this->uri['default'] = $blog['primaryBlogURL'].($this->__getFancyURLpostfix());
				if ($_SERVER['HTTP_HOST'] == $this->context->getProperty('domain'))
					$this->uri['base'] = $this->context->getProperty('path');
				else
					$this->uri['base'] = $this->uri['default'];
				break;
		}
		$this->uri['host'] = ($this->context->getProperty('service.useSSL',false) ? 'https://' : 'http://') . $_SERVER['HTTP_HOST'] . (!is_null($this->context->getProperty('port')) ? ':' . $this->context->getProperty('port') : '');
		if (!empty($this->uri['isHostMismatch'])) {
			$canonicalProto = $this->context->getProperty('service.useSSL', false) ? 'https://' : 'http://';
			$canonicalHost  = $blog['name'] . '.' . $this->context->getProperty('domain');
			$portStr        = !is_null($this->context->getProperty('port')) ? ':' . $this->context->getProperty('port') : '';
			$this->uri['host'] = $canonicalProto . $canonicalHost . $portStr;
			if (is_null($this->context->getProperty('service.serviceURL'))) {
				$this->uri['service'] = $canonicalProto . $canonicalHost . $portStr . $this->context->getProperty('service.path');
			}
		}
		// Textcube-NG: Host 헤더 검사 — 요청 Host·포트가 설정(domain·블로그 주소·hostAliases, config.php 포트)과 같은지(uri.isRegisteredOrigin).
		// 다르면 페이지 캐시를 쓰지 않는다(Needlworks.Cache.PageCache)(v3.205)
		$hostAliases = $this->context->getProperty('hostAliases');
		if (!is_array($hostAliases)) $hostAliases = array();
		$serviceDomain = (string)$this->context->getProperty('domain');
		if ($this->context->getProperty('service.type') == 'domain')
			$isRegisteredHost = empty($this->uri['isHostMismatch']) && ($_SERVER['HTTP_HOST'] === $serviceDomain
				|| (isset($blog['name']) && $_SERVER['HTTP_HOST'] === $blog['name'] . '.' . $serviceDomain)
				|| (!empty($blog['secondaryDomain']) && $_SERVER['HTTP_HOST'] === $blog['secondaryDomain']));
		else
			$isRegisteredHost = ($_SERVER['HTTP_HOST'] === $serviceDomain);
		$isRegisteredHost = $isRegisteredHost || in_array($_SERVER['HTTP_HOST'], $hostAliases, true);
		$requestPort = (string)$this->context->getProperty('port');
		$this->uri['isRegisteredOrigin'] = ($isRegisteredHost && $requestPort === (string)$this->context->getProperty('configuredPort'))
			|| in_array($_SERVER['HTTP_HOST'] . ($requestPort !== '' ? ':' . $requestPort : ''), $hostAliases, true);
		$this->uri['blog'] = $this->uri['path'].$this->__getFancyURLpostfix();
		$this->uri['folder'] = rtrim($this->uri['blog'] . $suri['directive'], '/');
		$this->uri['permalink'] = rtrim($this->uri['default'].rtrim($this->suri['directive'],'/').(empty($this->suri['id']) ? '/'.URL::encode($this->suri['value']) : '/'.$this->suri['id']),'/');
		$this->uri['basicblog'] = $this->uri['blog'];
		if (defined('__TEXTCUBE_MOBILE__')) {
			$this->uri['blog'] .= '/m';
			$_SESSION['mode'] = 'mobile';
		} else if (defined('__TEXTCUBE_IPHONE__')) {
			$this->uri['blog'] .= '/i';
			$_SESSION['mode'] = 'mobile';
		}
		$this->blog = $blog;
		$this->skin = $skinSetting;
		$this->updateContext();
	}

	function updateContext($ns = null) {
		$this->context = Model_Context::getInstance();
		if(!is_null($ns)) {
			$info = array($ns);
		} else {
			$info = array('uri','blog','skin','suri');
		}
		foreach ($info as $namespace) {
			if(!empty($this->$namespace) && is_array($this->$namespace)) {
				foreach ($this->$namespace as $key => $value) {
					$this->context->setProperty($key,$value,$namespace);
				}
			}
		}
	}
	private function __getBlogIdByName($name) {
		$query = DBModel::getInstance();
		$query->reset('BlogSettings');
		$query->setQualifier('name','equals','name',true);
		$query->setQualifier('value', 'equals', $name, true);
		return $query->getCell('blogid');
		return false;
	}

	private function __getBlogIdBySecondaryDomain($domain) {
		// Host 헤더 값 → escape 필수. setQualifierSet 은 같은 컬럼을 컬럼명 키로 덮어쓰므로 hasoneof(IN) 사용
		$query = DBModel::getInstance();
		$query->reset('BlogSettings');
		$query->setQualifier('name', 'equals', 'secondaryDomain', true);
		$query->setQualifier('value', 'hasoneof', array($domain, (substr($domain, 0, 4) == 'www.' ? substr($domain, 4) : 'www.' . $domain)), true);
		return $query->getCell('blogid');
	}

	private function __getFancyURLpostfix() {
		switch($this->context->getProperty('service.fancyURL')) {
			case 0: return '/index.php?';
			case 1: return '/?';
			case 2:default: return '';
		}
	}

	function __destruct() {
		// Nothing to do: destruction of this class means the end of execution
	}
}

/** Support functions for legacy support */

function getBlogId() {
	$uri = Model_URIHandler::getInstance();
	return intval($uri->blogid);
}

?>
