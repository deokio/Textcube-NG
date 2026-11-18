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
class UserInfo {

	function __construct() {
		$this->reset();
	}
	var $_result;
	function reset() {
		$this->userid =
		$this->loginid =
		$this->password =
		$this->name =
		$this->created =
		$this->lastLogin =
		$this->host =
		$this->_result =
			null;
	}
	
	function getUser() {
		global $database;
		if (is_null($this->userid)) {
			$_result = fetchQueryRow("SELECT * FROM {$database['prefix']}Users WHERE userid = ".getUserId());
			foreach($_result as $key => $value) {
				if(!is_numeric($key)) {
					$this->$key = $value;
				}
			}
			$this->password = null;
			return ;
		}
	}
	
	function add() {
		global $database;
		if(isset($this->userid))
			return $this->_error('insert');
		if(!isset($this->password))		
			return $this->_error('password');
		if(!isset($this->name))		
			return $this->_error('name');
		if(!isset($this->created))		
			return $this->_error('created');
		if(!isset($this->lastLogin))		
			return $this->_error('lastLogin');
		if(!isset($this->host))		
			return $this->_error('host');		
		if (!$query = $this->_buildQuery())
			return $this->_error('query generate error');		
		if (!$query->insert())
			return $this->_error('insert');
		$this->id = null;	// Textcube-NG: DBModel::$id 는 protected 라 읽으면 Error, insert/update 뒤 값은 항상 null 이므로 같은 값을 둠(r3-30 R-510)
		
		$this->password = null;
		return true;
	}
	
	function update() {
		global $database;
		if(!isset($this->userid))		
			return $this->_error('insert');
		if (!$query = $this->_buildQuery())
			return $this->_error('query generate error');		
		if (!$query->update())
			return $this->_error('update');
		$this->id = null;	// Textcube-NG: DBModel::$id 는 protected 라 읽으면 Error, insert/update 뒤 값은 항상 null 이므로 같은 값을 둠(r3-30 R-510)
		
		$this->password = null;
		return true;
	}
	
	function getUserid() {
		if (is_null($this->userid)) 
			$this->getUser();
		return $this->userid;
	}
	
	function getLoginid() {
		if (is_null($this->loginid)) 
			$this->getUser();
		return $this->loginid;
	}
	
/*	function getPassword() { // deprecated
		exit;
		if (is_null($this->password)) 
			$this->getUser();
		return $this->password;
	}*/
	
	function getName() {
		if (is_null($this->name)) 
			$this->getUser();
		return $this->name;
	}
	
	function getCreated() {
		if (is_null($this->created)) 
			$this->getUser();
		return $this->created;
	}
	
	function getLastLogin() {
		if (is_null($this->lastLogin)) 
			$this->getUser();
		return $this->LastLogin;
	}
	
	function getHost() {
		if (is_null($this->host)) 
			$this->getUser();
		return $this->host;
	}
	
	function close() {
		if (isset($this->_result)) {
			POD::free($this->_result);
			unset($this->_result);
		}
		$this->_count = 0;
		$this->reset();
	}

	function _buildQuery() {
		$query = DBModel::getInstance();
		$query->reset('Users');
		// Textcube-NG: setQualifier() 의 둘째 인자는 조건이다. 원본은 조건을 빼고 값만 넘겨 값이 조건 자리로 밀리고 userid 값은 NULL 이 됐다. 아래 setQualifier 에 'equals' 를 넣는다(r3-29 R-479)
		$query->setQualifier('userid', 'equals', getUserId());
		
		if (isset($this->userid)) {
			if (!Validator::number($this->userid, 1))
				return $this->_error('userid');
			$query->setQualifier('userid', 'equals', $this->userid);
		}
		
		if (isset($this->loginid)) {
			$this->loginid = UTF8::lessenAsEncoding(trim($this->loginid), 64);
			if(empty($this->loginid))
				return $this->_error('loginid');
			$query->setAttribute('loginid', $this->loginid,true);
		}
		
		if (isset($this->password)) {
			$this->password = trim($this->password);
			if(empty($this->password))
				return $this->_error('password');
			$query->setAttribute('password', $this->password,true);
		}
	
		if (isset($this->name)) {
			$this->name = UTF8::lessenAsEncoding(trim($this->name), 32);
			if(empty($this->name))
				return $this->_error('name');
			$query->setAttribute('name', $this->name,true);
		}
		
		if (isset($this->created)) {
			if (!Validator::number($this->created, 0))
				return $this->_error('created');
			$query->setAttribute('created', $this->created);
		}
		
		if (isset($this->lastLogin)) {
			if (!Validator::number($this->lastLogin, 1))
				return $this->_error('lastLogin');
			$query->setAttribute('lastLogin', $this->lastLogin);
		}
		
		if (isset($this->host)) {
			if (!Validator::number($this->host, 0))
				return $this->_error('host');
			$query->setAttribute('host', $this->host);
		}
		return $query;
	}

	function _error($error) {
		$this->error = $error;
		return false;
	}
	
}
?>
