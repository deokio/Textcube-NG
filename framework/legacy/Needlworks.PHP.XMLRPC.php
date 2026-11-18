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
	function replace_num_entity($ord)
	{
		$ord = $ord[1];
		if (preg_match('/^x([0-9a-f]+)$/i', $ord, $match))
		{
			$ord = hexdec($match[1]);
		}
		else
		{
			$ord = intval($ord);
		}

		$no_bytes = 0;
		$byte = array();
		if ($ord < 128)
		{
			return chr($ord);
		}
		elseif ($ord < 2048)
		{
			$no_bytes = 2;
		}
		elseif ($ord < 65536)
		{
			$no_bytes = 3;
		}
		elseif ($ord < 1114112)
		{
			$no_bytes = 4;
		}
		else
		{
			return;
		}
		switch($no_bytes)
		{
			case 2:
			{
				$prefix = array(31, 192);
				break;
			}
			case 3:
			{
				$prefix = array(15, 224);
				break;
			}
			case 4:
			{
				$prefix = array(7, 240);
				break;
			}
			default:
			{
				// Textcube-NG: $prefix 미정의 경로 방지(위 else와 같이 null 반환), case 4 에 break 추가(r3-8)
				return;
			}
		}
		for ($i = 0; $i < $no_bytes; $i++)
		{
			$byte[$no_bytes - $i - 1] = (($ord & (63 * pow(2, 6 * $i))) / pow(2, 6 * $i)) & 63 | 128;
		}
		$byte[0] = ($byte[0] & $prefix[0]) | $prefix[1];
		$ret = '';
		for ($i = 0; $i < $no_bytes; $i++)
		{
			$ret .= chr($byte[$i]);
		}
		return $ret;
	}
		
#[AllowDynamicProperties]
class XMLRPC {
	var $url, $async = false, $methodName, $params, $result, $fault;
	// Textcube-NG: 요청을 공인 주소로만 보낼지(HTTPRequest::$publicOnly 로 넘김). 기본 false = 기존 동작(v3.156)
	var $publicOnly = false;
	
	var $useOldXmlRPC = false; // for 2003-before-version

	function __construct() {
		$this->_registry = array();
	}
	
	function call() {
		if (func_num_args() < 1)
			return false;
		$request = new HTTPRequest();
		$request->method = 'POST';
		$request->url = $this->url;
		$request->contentType = 'text/xml';
		$request->async = $this->async;
		$request->publicOnly = $this->publicOnly;
		ob_start();
		echo '<?xml version="1.0" encoding="utf-8"?><methodCall><methodName>' . htmlspecialchars((string)func_get_arg(0), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') . '</methodName><params>';
		for ($i = 1; $i < func_num_args(); $i++) {
			echo '<param>';
			$this->_encodeValue(func_get_arg($i)); // Textcube-NG: _encodeValue는 직접 출력하고 값을 돌려주지 않음(r3-8)
			echo '</param>';
		}
		echo '</params></methodCall>';
		$request->content = ob_get_contents();
		ob_end_clean();
		if (!$request->send()) {
			return false;
		}
		if ($this->async) {
			return true;
		}
		if ((!is_null($request->getResponseHeader('Content-Type'))) && ($request->getResponseHeader('Content-Type') != 'text/xml'))  {
			return false;
		}
		$xmls = new XMLStruct();
		$request->responseText = preg_replace_callback('/&#([0-9a-fx]+);/mi', 'replace_num_entity', $request->responseText);
		$xmls->open($request->responseText);
		if ($xmls->error) {
			return false;
		}
			
		if (isset($xmls->struct['methodResponse'][0]['fault'][0]['value']))
			$this->fault = $this->_decodeValue($xmls->struct['methodResponse'][0]['fault'][0]['value'][0]);
		else if (isset($xmls->struct['methodResponse'][0]['params'][0]['param'][0]['value']))
			$this->result = $this->_decodeValue($xmls->struct['methodResponse'][0]['params'][0]['param'][0]['value'][0]);
		else
			return false;
		return true;
	}
	
	function registerMethod($method, $function) {
		if( function_exists( $function ) ) {
		$this->_registry[$method] = $function;
	}
	}
	
	function receive($xml = null) {
		if (empty($xml)) {
			// $GLOBALS['HTTP_RAW_POST_DATA'] 는 PHP 7.0에서 제거됨 → file_get_contents('php://input') 로 대체.
			// Content-Type 비교 시 '; charset=utf-8' 등 파라미터가 붙는 경우를 허용하도록 prefix 비교로 변경.
			$rawPost = file_get_contents('php://input');
			if (empty($_SERVER['CONTENT_TYPE']) || empty($rawPost))
				return false;
			$contentType = strtolower(trim(explode(';', $_SERVER['CONTENT_TYPE'])[0]));
			if ($contentType !== 'text/xml')
				return false;
			$xmls = new XMLStruct();
			if ($xmls->open($rawPost) == false) {
				return false;
			}
		} else {
			$xmls = new XMLStruct();
			if ($xmls->open($xml) == false) {
				return false;
			}
		}
		if ($xmls->error) {
			return false;
		}
		if (!isset($xmls->struct['methodCall'][0]['methodName'][0]['.value'])) {
			return false;
		}
		$this->methodName = $xmls->struct['methodCall'][0]['methodName'][0]['.value'];
		$params = $xmls->selectNodes('/methodCall/params/param');
		// Textcube-NG: 인자가 없으면 selectNodes() 가 null — count(null) 은 PHP 7.2 부터 경고, PHP 8.0 부터 TypeError. 원본(PHP 5)처럼 0회 반복하도록 빈 배열로 둔다(v3.181)
		if (!is_array($params)) $params = array();
		$this->params = array();
		for ($i = 0; $i < count($params); $i++) {
			if (!isset($params[$i]['value']))
				return false;
			array_push($this->params, $this->_decodeValue($params[$i]['value'][0]));
			
		}
		
		if (isset($this->_registry[$this->methodName])) {
			$result = call_user_func_array($this->_registry[$this->methodName], $this->params);
			if (is_a($result, 'XMLRPCFault'))
				$this->sendFault($result->code, $result->string);
			else
				$this->sendResponse($result);
		} else {
			$this->sendFault(1, 'Method was not found');
		}
		return true;
	}
	
	function sendResponse($param = null) {
		header('Content-Type: text/xml');
		echo '<?xml version="1.0" encoding="utf-8"?><methodResponse><params>';
		if (!is_null($param)) {
			echo '<param>';
			$this->_encodeValue($param);
			echo '</param>';
		}
		echo '</params></methodResponse>';
	}
	
	static function sendFault($code = 0, $string = 'Error') {
		header('Content-Type: text/xml');
		echo '<?xml version="1.0" encoding="utf-8"?>';
		echo '<methodResponse><fault><value><struct><member><name>faultCode</name><value><i4>';
		echo $code;
		echo '</i4></value></member><member><name>faultString</name><value><string>';
		echo $string;
		echo '</string></value></member></struct></value></fault></methodResponse>';
	}
	
	function _encodeValue($value) {
		echo '<value>';
		if (is_a($value, 'XMLCustomType')) {
			echo "<{$value->name}>";
			echo $value->value;
			echo "</{$value->name}>";
		} else if (is_int($value)) {
			echo '<i4>';
			echo $value;
			echo '</i4>';
		} else if (is_double($value)) {
			echo '<double>';
			echo $value;
			echo '</double>';
		} else if (is_bool($value)) {
			echo '<boolean>';
			echo ($value ? '1' : '0');
			echo '</boolean>';
		} else if (is_array($value)) {
			for ($i = 0; $i < count($value); $i++) {
				if (!isset($value[$i]))
					break;
			}
			if ($i < count($value)) {
				echo '<struct>';
				foreach ($value as $name => $data) {
					echo '<member><name>';
					echo $name;
					echo '</name>';
					$this->_encodeValue($data);
					echo '</member>';
				}
				echo '</struct>';
			} else {
				echo '<array><data>';
				for ($i = 0; $i < count($value); $i++)
					$this->_encodeValue($value[$i]);
				echo '</data></array>';
			}
		} else if ((strlen($value) == 17) && ($value[8] == 'T') && ($value[11] == ':') && ($value[14] == ':')) {
			echo '<dateTime.iso8601>';
			echo $value;
			echo '</dateTime.iso8601>';
		} else {
			echo '<string>';
			if ($this->useOldXmlRPC == true) {
				echo XMLRPC::encodingStringEx($value);
			} else {
			echo htmlspecialchars($value);
			}
			echo '</string>';
		}
		echo '</value>';
	}
	
	function _decodeValue(&$value) {
		if (isset($value[0])) {
			return null;
		}
		list($type) = array_keys($value);
		switch ($type) {
			case '.value':
				return $value['.value'];
			case 'i4':
			case 'int':
				return intval($value[$type][0]['.value']);
			case 'boolean':
				return ($value[$type][0]['.value'] == '1');
			case 'string':
			case 'dateTime.iso8601':
				return $value[$type][0]['.value'];
			case 'double':
				return doubleval($value[$type][0]['.value']);
			case 'base64':
				return base64_decode($value[$type][0]['.value']);
			case 'array':
				if (!isset($value['array'][0]['data'][0]['value']))
					return null;
				if (isset($value['array'][0]['data'][0]['value'][0])) {
					$array = array();
					for ($i = 0; $i < count($value['array'][0]['data'][0]['value']); $i++)
						array_push($array, $this->_decodeValue($value['array'][0]['data'][0]['value'][$i]));
					return $array;
				} else {
					return array($this->_decodeValue($value['array'][0]['data'][0]['value']));
				}
			case 'struct':
				if (!isset($value['struct'][0]['member']))
					return null;
				if (isset($value['struct'][0]['member'][0])) {
					$struct = array();
					for ($i = 0; $i < count($value['struct'][0]['member']); $i++) {
						if (!isset($value['struct'][0]['member'][$i]['name'][0]['.value']) || !isset($value['struct'][0]['member'][$i]['value']))
							return null;
						$struct[$value['struct'][0]['member'][$i]['name'][0]['.value']] = $this->_decodeValue($value['struct'][0]['member'][$i]['value'][0]);
					}
					return $struct;
				} else {
					if (!isset($value['struct'][0]['member']['name'][0]['.value']) || !isset($value['struct'][0]['member']['value'])) // Textcube-NG: 단일 member(목록 아님) 처리로 정리, 정의되지 않은 $i 제거(r3-8)
						return null;
					// Textcube-NG: 참조 인자(&$value)에는 식(삼항)을 넘길 수 없어(PHP 7+ Error) 변수에 받아 넘긴다(r3-8 보충, 481)
					$memberValue = isset($value['struct'][0]['member']['value'][0]) ? $value['struct'][0]['member']['value'][0] : $value['struct'][0]['member']['value'];
					return array($value['struct'][0]['member']['name'][0]['.value'] => $this->_decodeValue($memberValue));
				}
		}
		return null;
	}
	
 	function encodingStringEx($text)
    {
		$l = strlen($text);
		$retString = '';
        // ### TODO: Use a buffer rather than going character by
        // ### character to scale better for large text sizes.
        //char[] buf = new char[32];
        for ($i = 0; $i < $l; $i++)
        {
            $c = $text[$i];
            switch ($c)
            {
            case '\t':
            case '\n':
                $retString .= $c;
                break;
            case '\r':
                // Avoid normalization of CR to LF.                
                $retString .= "&#" . ord($c) . ';';
                break;
            case '<':
                $retString .= '&lt;';
                break;
            case '>':
                $retString .= '&gt;';
                break;
            case '&':
                $retString .= '&amp;';
                break;
            default:
                // Though the XML spec requires XML parsers to support
                // Unicode, not all such code points are valid in XML
                // documents.  Additionally, previous to 2003-06-30
                // the XML-RPC spec only allowed ASCII data (in
                // <string> elements).  For interoperability with
                // clients rigidly conforming to the pre-2003 version
                // of the XML-RPC spec, we entity encode characters
                // outside of the valid range for ASCII, too.

                // Replace the code point with a character reference.
				$high = ord($text[$i]);
				$corrected = '';
				if ($high < 0x20) { // Special Characters.
					$corrected = '?';
				} else if ($high < 0x80) { // 1byte.
					$corrected = $text[$i];
				} else if ($high <= 0xC1) {
					$corrected = '?';
				} else if ($high < 0xE0) { // 2byte.
					if (($i + 1 >= $l) || (($text[$i + 1] & "\xC0") != "\x80"))
						$corrected = '?';
					else
						$corrected = '&#' . ((ord($text[$i]) & 0x1f) * 0x40 + (ord($text[$i + 1]) & 0x3f)) . ';'; 
					$i += 1;
				} else if ($high < 0xF0) { // 3byte.
					if (($i + 2 >= $l) || (($text[$i + 1] & "\xC0") != "\x80") || (($text[$i + 2] & "\xC0") != "\x80"))
						$corrected = '?';
					else
						$corrected = '&#' . (((ord($text[$i]) & 0x0f) * 0x40 + (ord($text[$i + 1])& 0x3f))  * 0x40 + (ord($text[$i + 2]) & 0x3f)) . ';'; 
					$i += 2;
				} else if ($high < 0xF5) { // 4byte.
					if (($i + 3 >= $l) || (($text[$i + 1] & "\xC0") != "\x80") || (($text[$i + 2] & "\xC0") != "\x80") || (($text[$i + 3] & "\xC0") != "\x80"))
						$corrected = '?';
					else
						$corrected = '&#' . ((((ord($text[$i]) & 0x07) * 0x40 + (ord($text[$i + 1]) & 0x3f)) * 0x40 + (ord($text[$i + 2]) & 0x3f ) ) * 0x40 + (ord($text[$i + 3]) & 0x3f)) . ';'; 
					$i += 3;
				} else { // F5~FF is invalid by RFC3629.
					$corrected = '?';
				}
                
                $retString .= $corrected;
            }
        }
        
        return $retString;
    }   
}

class XMLRPCFault {
	var $code, $string;

	function __construct($code = 0, $string = 'Error') {
		$this->code = $code;
		$this->string = $string;
	}
}

class XMLCustomType {
	var $value, $name;
	
	function __construct($varString, $varName) {
		$this->name = $varName;
		$this->value = $varString;
	}
}
?>
