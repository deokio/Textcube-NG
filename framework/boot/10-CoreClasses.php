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

/// Singleton implementation.
if(version_compare(PHP_VERSION, '5.3.0','>=')) { // >= 5.3 Singleton implementation 
	class Singleton {
		// If your model support higher than PHP 5.3, you do not implement getInstance method.
		// However, models for prior to PHP 5.3 should have getInstance method. 
		// See below (<5.3 Compatible singleton implementation) 
		private static $instances;

		public function __construct() {
			$c = get_class($this);
			if(isset(self::$instances[$c])) {
				throw new Exception('You can not create more than one copy of a singleton.');
			} else {
				self::$instances[$c] = $this;
			}
		}
		public static function _getInstance($p = null) {
			$c = get_called_class();
			if (!isset(self::$instances[$c])) {
				$args = func_get_args();
				$reflection_object = new ReflectionClass($c);
				self::$instances[$c] = $reflection_object->newInstanceArgs($args);
			}
			return self::$instances[$c];
		}
		public static function getInstance() {
			return self::_getInstance();
		}
		public function __clone() {
			throw new Exception('You can not clone a singleton.');
		}
	}
} else { //  < 5.3 Compatible Singleton implementation.
	class Singleton {
		private static $instances = array();

		protected function __construct() {
		}

		final protected static function _getInstance($className) {
			if (!array_key_exists($className, self::$instances)) {
				self::$instances[$className] = new $className();
			}
			return self::$instances[$className];
		}

		/*
		// If your model support prior to PHP 5.3, you should implement this method to the final class. 
		// (An example is below.)
		// This is mainly because "late static bindings" is supported after PHP 5.3.

		public static function getInstance() {
			return self::_getInstance(__CLASS__);
		}
		*/
		public static function getInstance(){}
	}
}

/// String manipulation class
final class StringUtil {
	static function endsWith($string, $end) {
		return substr_compare($string, $end, -strlen($end)) === 0;
	}

	static function startsWith($string, $start) {
		return substr_compare($string, $start, 0, strlen($start)) === 0;
	}
}

final class Validator {
	/**
		Date-Time		::= RFC-1123 (the modification of RFC-822)
		Language Code	::= ISO-639 2-letter
		Country Code	::= ISO-3166 alpha-2 country codes
		Language		::= RFC-1766 language tag & RFC-3066
							The used syntax in RFC-822 EBNF is:
								2*2ALPHA *( "-" 2*2ALPHA )
		Timezone		::= RFC-2822
							The used syntax in RFC-822 EBNF is:
								( "+" / "-" ) 4DIGIT
		E-Mail			::= RFC-2822
							The used syntax is
								addr-spec = local-part "@" domain
								local-part = dot-atom
	**/

	private static $queue;
	
	static function addRule($iv) {
		if(empty(self::$queue)) self::$queue = array('GET'=>array(),'POST'=>array(),'REQUEST'=>array(),'SERVER'=>array(),'FILES'=>array());
		if(isset($iv['GET'])) self::$queue['GET'] = array_merge(self::$queue['GET'],$iv['GET']);
		if(isset($iv['POST'])) self::$queue['POST'] = array_merge(self::$queue['POST'],$iv['POST']);
		if(isset($iv['REQUEST'])) self::$queue['REQUEST'] = array_merge(self::$queue['REQUEST'],$iv['REQUEST']);
		if(isset($iv['SERVER'])) self::$queue['SERVER'] = array_merge(self::$queue['SERVER'],$iv['SERVER']);
		if(isset($iv['FILES'])) self::$queue['FILES'] = array_merge(self::$queue['FILES'],$iv['FILES']);
	}
	static function isValid() {
		return self::validate(self::$queue);
	}
	
	static function validate(&$iv) {
		if (isset($iv['GET'])) {
			if (!Validator::validateArray($_GET, $iv['GET']))
				return false;
			foreach (array_keys($_GET) as $key) {
				if (!array_key_exists($key, $iv['GET']))
					unset($_GET[$key]);
			}
		} else {
			$_GET = array();
		}

		if (isset($iv['POST'])) {
			if (!Validator::validateArray($_POST, $iv['POST']))
				return false;
			foreach (array_keys($_POST) as $key) {
				if (!array_key_exists($key, $iv['POST']))
					unset($_POST[$key]);
			}
		} else {
			$_POST = array();
		}

		if (isset($iv['REQUEST'])) {
			if (!Validator::validateArray($_REQUEST, $iv['REQUEST']))
				return false;
			foreach (array_keys($_REQUEST) as $key) {
				if (!array_key_exists($key, $iv['REQUEST']))
					unset($_REQUEST[$key]);
			}
		} else {
			$_REQUEST = array();
		}

		if (isset($iv['SERVER'])) {
			if (!Validator::validateArray($_SERVER, $iv['SERVER']))
				return false;
		}

		if (isset($iv['FILES'])) {
			if (!Validator::validateArray($_FILES, $iv['FILES']))
				return false;
			foreach (array_keys($_FILES) as $key) {
				if (!array_key_exists($key, $iv['FILES']))
					unset($_FILES[$key]);
			}
		} else {
			$_FILES = array();
		}
		return true;
	}

	static function validateArray(&$array, &$rules) {
		// Workaround for non Fancy-URL user.
		$cropArray = array();
		foreach($array as $name => $value) {
			$doesHaveRequest = strpos($name,'?');
			if($doesHaveRequest !== false) {$name = substr($name,$doesHaveRequest+1);}
			$cropArray[$name] = $value;
		}
		$array = $cropArray;
		foreach ($rules as $key => $rule) {
			if (!isset($rule[0])) {
				trigger_error("Validator: The type of '$key' is not defined", E_USER_WARNING);
				continue;
			}
			// Textcube-NG: 배열을 받지 않는 type 에 배열(key[]=x, key[a][]=x, 빈 배열)이 오면 필수·선택·기본값 규칙 모두 검증 실패로 처리한다.
			// 원본은 아래 strlen() 에 배열을 넘겨 PHP 8 에서 TypeError(500)가 났고, PHP 7.4 는 strlen() 이 NULL(경고)이라 값이 없는 것으로
			// 처리되어 기본값이 없는 선택 입력은 배열이 검사 없이 그대로 남았다(v3.143)
			if (isset($array[$key]) && ($rule[0] !== 'file') && is_array($array[$key]))
				return false;
			if (isset($array[$key]) && (($rule[0] == 'file') || (strlen($array[$key]) > 0))) {
				$value = &$array[$key];
				if (isset($rule['min']))
					$rule[1] = $rule['min'];
				if (isset($rule['max']))
					$rule[2] = $rule['max'];
				if (isset($rule['bypass']))
					$rule[3] = $rule['bypass'];

				switch ($rule[0]) {
					case 'any':
						if (isset($rule[1]) && (strlen($value) < $rule[1]))
							return false;
						if (isset($rule[2]) && (strlen($value) > $rule[2]))
							return false;
						break;
					case 'bit':
						$array[$key] = Validator::getBit($value);
						break;
					case 'bool':
						$array[$key] = Validator::getBool($value);
						break;
					case 'number':
						if (!Validator::number($value, (isset($rule[1]) ? $rule[1] : null), (isset($rule[2]) ? $rule[2] : null), (isset($rule[3]) ? $rule[3] : false)))
							return false;
						break;
					case 'int':
						if (!Validator::isInteger($value, (isset($rule[1]) ? $rule[1] : -2147483648), (isset($rule[2]) ? $rule[2] : 2147483647), (isset($rule[3]) ? $rule[3] : false)))
							return false;
						break;
					case 'id':
						if (!Validator::id($value, (isset($rule[1]) ? $rule[1] : 1), (isset($rule[2]) ? $rule[2] : 2147483647)))
							return false;
						break;
					case 'url':
					case 'string':
						if (!UTF8::validate($value)) {
							$value = UTF8::bring($value);
							if (!UTF8::validate($value))
								return false;
						}
						$value = $array[$key] = UTF8::correct($value);

						if (isset($rule[1]) && (UTF8::length($value) < $rule[1]))
							return false;
						if (isset($rule[2]) && (UTF8::length($value) > $rule[2]))
							return false;
						break;
					case 'list':
						if (!Validator::isList($value))
							return false;
						break;
					case 'timestamp':
						if (!Validator::timestamp($value))
							return false;
						break;
					case 'period':
						if (!Validator::period($value))
							return false;
						break;
					case 'ip':
						if (!Validator::ip($value))
							return false;
						break;
					case 'domain':
						if (!Validator::domain($value))
							return false;
						break;
					case 'email':
						if (!Validator::email($value))
							return false;
						break;
					case 'language':
						if (!Validator::language($value))
							return false;
						break;
					case 'filename':
						if (!Validator::filename($value))
							return false;
						break;
					case 'directory':
						if (!Validator::directory($value))
							return false;
						break;
					case 'path':
						if (!Validator::path($value))
							return false;
						break;
					case 'file':
						// Textcube-NG: 여러 파일 업로드 형식(key[])이면 name 이 배열 — 단일 파일만 받으므로 검증 실패(원본은 PHP 8 에서 preg_match() TypeError,
						// PHP 7.4 는 NULL 이라 통과)(v3.143)
						if (!isset($value['name']) || !is_string($value['name']) || preg_match('@[/\\\\]@', $value['name']))
							return false;
						break;
					default:
						if (is_array($rule[0])) {
							if (!in_array($value, $rule[0]))
								return false;
						} else {
							trigger_error("Validator: The type of '$key' is unknown", E_USER_WARNING);
						}
						break;
				}

				if (isset($rule['check']))
					$rule[5] = $rule['check'];
				if (isset($rule[5])) {
					if (function_exists($rule[5])) {
						if (!call_user_func($rule[5], $value))
							return false;
					} else {
						trigger_error("Validator: The check function of '$key' is not defined", E_USER_WARNING);
					}
				}
			} else {
				if (array_key_exists(3, $rule))
					$array[$key] = $rule[3];
				else if (array_key_exists('default', $rule))
					$array[$key] = $rule['default'];
				else if ((!isset($rule[4]) || $rule[4]) && (!isset($rule['mandatory']) || $rule['mandatory']))
					return false;
			}
		}
		return true;
	}

	static function number($value, $min = null, $max = null, $bypass = false) {
		if (!is_numeric($value)) {
			if ($bypass === false)
				return false;
			return true;
		}
		if (isset($min) && ($value < $min))
			return false;
		if (isset($max) && ($value > $max))
			return false;
		return true;
	}

	static function isInteger($value, $min = -2147483648, $max = 2147483647, $bypass = false) {
		// Textcube-NG: [보안] $ 는 끝 줄바꿈 앞에서도 맞아 "1\n" 이 통과했다 — /D 로 막는다. 배열·객체는 preg_match() 에 넘기지 않고 false(원본: 배열·__toString 없는 객체는 PHP 7.4 경고, PHP 8.0 부터 TypeError. __toString 객체는 문자열로 검사되어 이 경우만 원본과 결과가 다르다), null 은 (string) 으로 '' 가 되어 false(PHP 8.1 null 인자 deprecation 없음)(v3.182)
		if (($bypass === false) && (is_array($value) || is_object($value) || !preg_match('/^(0|-?[1-9][0-9]{0,9})$/D', (string)$value)))
			return false;
		if(!is_null($value)) {
			// Textcube-NG: PHP 8.0 부터 숫자가 아닌 문자열과 정수의 비교는 문자열 비교라 bypass(true) 일 때 ''·'abc'·'5abc'·' ' 는 false, '-2147483649abc' 는 true 처럼 원본과 달랐다 — 원본(PHP 5~7.4)처럼 앞부분 숫자(없으면 0)로 비교한다(v3.221)
			$number = (is_string($value) && !is_numeric($value)) ? (float)$value : $value;
			if (($number < $min) || ($number > $max))
				return false;
		}
		return true;
	}

	static function id($value, $min = 1, $max = 2147483647) {
		return Validator::isInteger($value, $min, $max);
	}

	static function isList($value) {
		if (!preg_match('/^[1-9][0-9]{0,9}(,[1-9][0-9]{0,9})*,?$/', $value))
			return false;
		return true;
	}

	/**
	 *	Valid: Jan 1 1971 ~ Dec 31 2037 GMT
	 */
	
	static function timestamp($value) {
		return (Validator::isInteger($value) && ($value >= 31536000) && ($value < 2145916800));
	}

	static function period($value, $length = null) {
		if (preg_match('/\\d+/', $value)) {
			if (isset($length) && (strlen($value) != $length))
				return false;
			$year = 0;
			$month = 1;
			$day = 1;
			switch (strlen($value)) {
				case 8:
					$day = substr($value, 6, 2);
				case 6:
					$month = substr($value, 4, 2);
				case 4:
					$year = substr($value, 0, 4);
					return checkdate($month, $day, $year);
			}
		}
		return false;
	}

	static function ip($value) {
		return preg_match('/^\\d{1,3}(\\.\\d{1,3}){3}$/', $value);
	}

	static function domain($value) {
		return ((strlen($value) <= 64) && preg_match('/^([[:alnum:]]+(-[[:alnum:]]+)*\\.)+[[:alnum:]]+(-[[:alnum:]]+)*$/', $value));
	}

	static function email($value) {
		if (strlen($value) > 64)
			return false;
		$parts = explode('@', $value, 2);
		return ((count($parts) == 2) && preg_match('@[\\w!#\-\'*+/=?^`{-~-]+(\\.[\\w!#-\'*+/=?^`{-~-]+)*@', $parts[0]) && Validator::domain($parts[1]));
	}

	static function language($value) {
		return preg_match('/^[[:alpha:]]{2}(\-[[:alpha:]]{2})?$/', $value);
	}

	static function filename($value) {
		return preg_match('/^\w+(\.\w+)*$/D', $value);	// Textcube-NG: D — 끝 줄바꿈이 붙은 값은 받지 않는다(r3-15, R-324)
	}

	static function directory($value) {
		return preg_match('/^[\-\w]+( [\-\w]+)*$/D', $value);	// Textcube-NG: D — 끝 줄바꿈이 붙은 값은 받지 않는다(r3-15, R-370)
	}

	static function path($value) {
		return preg_match('/^[\-\w]+( [\-\w]+)*(\/[\-\w]+( [\-\w]+)*)*$/', $value);
	}

	static function getBit($value) {
		return (Validator::getBool($value) ? 1 : 0);
	}

	static function getBool($value) {
		return (!empty($value) && (!is_string($value) || (strcasecmp('false', $value) && strcasecmp('off', $value) && strcasecmp('no', $value))));
	}

	static function escapeXML($string, $escape = true) {
		if (is_null($string))
			return null;
		// Textcube-NG: $escape 가 false 인 분기의 preg_replace() 패턴('&quot;' 등)에 구분자가 없어 원본(PHP 5.6 포함)부터 경고(No ending delimiter '&') 뒤 NULL → 언제나 '' 였고, PHP 8.1 부터는 str_replace() 에 null 을 넘기는 지원 중단 경고도 났다 — 바깥 str_replace() 와 같은 문자열 치환으로 바로잡는다. 이 분기는 엔티티를 되돌린 원문을 돌려주므로 HTML/XML 출력에 그대로 쓰지 않는다(현재 호출 0곳)(v3.221)
		return ($escape ? htmlspecialchars($string) : str_replace('&amp;', '&', str_replace(array('&quot;', '&lt;', '&gt;'), array('"', '<', '>'), $string)));
	}
}

final class Timezone {
	static function isGMT() {
		return (date('Z') == 0);
	}

	static function get() {
		$mezone = getenv('TZ');
		if (empty($timezone))
			$timezone = date('T');
		return (empty($timezone) ? 'UTC' : $timezone);
	}

	static function getOffset() {
		return (int)date('Z');
	}

	static function getCanonical() {
		return sprintf("%+03d:%02d", intval(Timezone::getOffset() / 3600), abs((Timezone::getOffset() / 60) % 60));
	}

	static function getRFC822() {
		if (Timezone::isGMT())
			return 'GMT';
		else
			return sprintf("%+05d", intval(Timezone::getOffset() / 3600) * 100 + ((Timezone::getOffset() / 60) % 60));
	}

	static function getISO8601($timezone = null) {
		if (Timezone::isGMT())
			return 'Z';
		else
			return sprintf("%+03d:%02d", intval(Timezone::getOffset() / 3600), abs((Timezone::getOffset() / 60) % 60));
	}

	/* [보안] PHP 시간대 식별자(tz 데이터베이스, 하위 호환 이름 포함)인지 확인 — 블로그 시간대 값은 DB 세션 시간대 설정 SQL 과
	   TZ 환경변수에 쓰이므로 식별자 외 문자열(따옴표 등)을 받지 않는다. 대소문자는 date_default_timezone_set() 처럼 구분하지 않음 */
	static function isValid($timezone) {
		static $identifiers = null;
		if (!is_string($timezone) || ($timezone === ''))
			return false;
		if ($identifiers === null)
			$identifiers = array_change_key_case(array_flip(timezone_identifiers_list(DateTimeZone::ALL_WITH_BC)), CASE_LOWER);
		return isset($identifiers[strtolower($timezone)]);
	}

	static function set($timezone) {
		// [보안] 식별자가 아니면 거부(원본은 date_default_timezone_set() 실패를 무시하고 putenv() 결과(true)를 돌려줘 호출자가 그대로 저장)
		if (!Timezone::isValid($timezone))
			return false;
		return Timezone::_apply($timezone);
	}

	/* 원본 set() 본문. setRFC822()·setISO8601() 는 검사한 숫자로 만든 POSIX 오프셋(GMT±hh:mm·UTC±hh:mm, PHP 식별자 아님)을
	   넘기므로 원본 동작 그대로 이 경로를 쓴다(외부 입력은 set() 에서 검사) */
	private static function _apply($timezone) {
		// Textcube-NG: 빈 값(null·''·0·false)이면 기본 시간대(config.default.php $service['timezone'])를 쓴다(v3.182)
		if (in_array($timezone, array(null, '', 0, false), true))
			$timezone = 'Asia/Seoul';
		if ( isset( $_ENV['OS'] ) && strncmp($_ENV['OS'], 'Windows', 7) == 0)
			$timezone = Timezone::getAlternative($timezone);
		date_default_timezone_set($timezone);
		return putenv('TZ=' . $timezone);
	}

	static function setOffset($offset) {
		return Timezone::setISO8601(sprintf("%+02d:%02d", floor($offset / 3600), abs(($offset / 60) % 60)));
	}

	static function setRFC822($timezone) {
		if (($timezone == 'GMT') || ($timezone == 'UT'))
			return Timezone::set('GMT');
		else if (!is_numeric($timezone) || (strlen($timezone) != 5))
			return false;
		else if ($timezone[0] == '+')
			return Timezone::_apply('UTC-' . substr($timezone, 1, 2) . ':' . substr($timezone, 3, 2));
		else if ($timezone[0] == '-')
			return Timezone::_apply('UTC+' . substr($timezone, 1, 2) . ':' . substr($timezone, 3, 2));
		else
			return false;
	}

	static function setISO8601($timezone) {
		if ($timezone == 'Z')
			return Timezone::set('GMT');
		if (!preg_match('/^([-+])(\d{1,2})(:)?(\d{2})?$/', $timezone, $matches))
			return false;
		$matches[0] = 'GMT';
		$matches[1] = ($matches[1] == '+' ? '-' : '+');
		if (strlen($matches[2]) == 1)
			$matches[2] = '0' . $matches[2];
		if (empty($matches[3]))
			$matches[3] = ':';
		if (empty($matches[4]))
			$matches[4] = '00';
		return Timezone::_apply(implode('', $matches));
	}

	static function getList() {
		return array(
			_t_noop('Asia/Seoul'),
			_t_noop('Asia/Tokyo'),
			_t_noop('Asia/Shanghai'),
			_t_noop('Asia/Taipei'),
			_t_noop('Asia/Calcutta'),
			_t_noop('Europe/Berlin'),
			_t_noop('Europe/Paris'),
			_t_noop('Europe/London'),
			_t_noop('GMT'),
			_t_noop('America/New_York'),
			_t_noop('America/Chicago'),
			_t_noop('America/Denver'),
			_t_noop('America/Los_Angeles'),
			_t_noop('Australia/Sydney'),
			_t_noop('Australia/Melbourne'),
			_t_noop('Australia/Adelaide'),
			_t_noop('Australia/Darwin'),
			_t_noop('Australia/Perth'),
			);
	}

	static function getAlternative($timezone) {
		// Textcube-NG: PHP 8.0 부터 정수와 문자열 비교가 문자열 비교로 바뀌어 switch 에서 0 이 'Asia/Seoul' 에 맞지 않는다(Saner string to number comparisons) — 빈 값(null·''·0·false)은 'KST-9'(v3.182)
		if (in_array($timezone, array(null, '', 0, false), true))
			return 'KST-9';
		switch ($timezone) {
			case 'Asia/Seoul':
				return 'KST-9';
			case 'Asia/Tokyo':
				return 'JST-9';
			case 'Asia/Shanghai':
				return 'CST-8';
			case 'Asia/Taipei':
				return 'CST-8';
			case 'Asia/Calcutta':
				return 'UTC-5:30';
			case 'Europe/Berlin':
			case 'Europe/Paris':
				return 'UTC-1CES';
			case 'Europe/London':
				return 'UTC0BST';
			case 'America/New_York':
				return 'EST5EDT';
			case 'America/Chicago':
				return 'CST6CDT';
			case 'America/Denver':
				return 'MST7MDT';
			case 'America/Los_Angeles':
				return 'PST8PDT';
			case 'Australia/Sydney':
			case 'Australia/Melbourne':
				return 'EST-10EDT';
			case 'Australia/Adelaide':
			case 'Australia/Darwin':
				return 'CST-9:30';
			case 'Australia/Perth':
				return 'WST-8';
		}
		return $timezone;
	}
}


// PHP 8.1 strftime() / gmstrftime() deprecated 대응 (php.net/migration81.deprecated).
// 포맷 문자열을 문자 단위로 처리하여 date()/gmdate() 로 매핑.
// 로케일 의존 코드(%a,%b,%B,%c)는 영문 date() 동등값 사용.
function _strftime_apply(string $fmt, int $ts, bool $gmt): string {
	$result = '';
	$len = strlen($fmt);
	for ($i = 0; $i < $len; $i++) {
		if ($fmt[$i] !== '%' || $i + 1 >= $len) {
			$result .= $fmt[$i];
			continue;
		}
		$i++;
		$fn = $gmt ? 'gmdate' : 'date';
		// Textcube-NG: 지정자 매핑을 C 로케일 strftime() 결과(php:5.6 실측)에 맞춤 — %C·%D·%F·%G·%g·%h·%k·%l·%r·%s·%U·%x 추가, %c·%e·%W 교정(v3.182)
		$result .= match($fmt[$i]) {
			'A' => $fn('l', $ts),
			'a' => $fn('D', $ts),
			'B' => $fn('F', $ts),
			'b', 'h' => $fn('M', $ts),
			'C' => (string)intdiv((int)$fn('Y', $ts), 100),
			'c' => $fn('D M ', $ts) . sprintf('%2d', (int)$fn('j', $ts)) . $fn(' H:i:s ', $ts) . $fn('Y', $ts),
			'D', 'x' => $fn('m/d/y', $ts),
			'd' => $fn('d', $ts),
			'e' => sprintf('%2d', (int)$fn('j', $ts)),
			'F' => $fn('Y', $ts) . $fn('-m-d', $ts),
			'G' => $fn('o', $ts),
			'g' => sprintf('%02d', (int)$fn('o', $ts) % 100),
			'H' => $fn('H', $ts),
			'I' => $fn('h', $ts),
			'j' => sprintf('%03d', (int)$fn('z', $ts) + 1),
			'k' => sprintf('%2d', (int)$fn('G', $ts)),
			'l' => sprintf('%2d', (int)$fn('g', $ts)),
			'M' => $fn('i', $ts),
			'm' => $fn('m', $ts),
			'n' => "\n",
			'p' => $fn('A', $ts),
			'P' => $fn('a', $ts),
			'R' => $fn('H:i', $ts),
			'r' => $fn('h:i:s A', $ts),
			'S' => $fn('s', $ts),
			's' => $gmt ? (string)mktime((int)gmdate('G', $ts), (int)gmdate('i', $ts), (int)gmdate('s', $ts), (int)gmdate('n', $ts), (int)gmdate('j', $ts), (int)gmdate('Y', $ts)) : (string)$ts,
			'T' => $fn('H:i:s', $ts),
			't' => "\t",
			'U' => sprintf('%02d', intdiv((int)$fn('z', $ts) + 7 - (int)$fn('w', $ts), 7)),
			'u' => $fn('N', $ts),
			'V' => $fn('W', $ts),
			'W' => sprintf('%02d', intdiv((int)$fn('z', $ts) + 7 - ((int)$fn('w', $ts) + 6) % 7, 7)),
			'w' => $fn('w', $ts),
			'X' => $fn('H:i:s', $ts),
			'Y' => $fn('Y', $ts),
			'y' => $fn('y', $ts),
			'Z' => $gmt ? 'GMT' : date('T', $ts),
			'z' => $gmt ? '+0000' : date('O', $ts),
			'%' => '%',
			default => '%' . $fmt[$i],
		};
	}
	return $result;
}
function strftime_compat(string $format, ?int $timestamp = null): string {
	return _strftime_apply($format, $timestamp ?? time(), false);
}
function gmstrftime_compat(string $format, ?int $timestamp = null): string {
	return _strftime_apply($format, $timestamp ?? time(), true);
}

final class Timestamp {
	// Textcube-NG: 원본 strftime()·date() 는 timestamp 를 정수로 바꿔 받았다(PHP 7.4 기준 — 앞부분만 숫자인 문자열은 경고 뒤 그 숫자, 소수부는 버림). 바꿀 수 없는 값(''·'abc'·' '·배열·객체·정수 범위 밖)은 경고 뒤 false 를 돌려줬다. PHP 8.0 부터 strftime_compat(?int)·date() 는 이런 값에 TypeError, PHP 8.1 부터 소수는 암시적 변환 deprecation 이라 여기서 같은 정수로 바꾸고, 바꿀 수 없으면 false 를 돌려준다(경고는 내지 않는다)(v3.221)
	private static function toTimestamp($time) {
		if (is_null($time) || is_int($time))
			return $time;
		if (is_bool($time))
			return (int)$time;
		if (is_float($time))
			return (is_finite($time) && $time >= PHP_INT_MIN && $time < PHP_INT_MAX) ? (int)$time : false;
		if (is_string($time) && is_numeric($time))
			return self::toTimestamp($time + 0);
		if (is_string($time) && preg_match('/^[ \t\n\r\x0B\f]*[+-]?(?:[0-9]+(?:\.[0-9]*)?|\.[0-9]+)(?:[eE][+-]?[0-9]+)?/', $time, $m))
			return self::toTimestamp($m[0] + 0);
		return false;
	}

	// Textcube-NG: 원본 date()·gmdate() 는 timestamp 를 정수로 바꿔 받았고(PHP 7.4 기준), 바꿀 수 없는 값은 경고 뒤 false 를 돌려줬다. PHP 8.0 부터 이런 값은 TypeError, PHP 8.1 부터 소수는 암시적 변환 deprecation 이라 toTimestamp() 로 같은 정수로 바꿔 넘기고, 바꿀 수 없으면 false 를 돌려준다(경고는 내지 않는다)(v3.222)
	private static function dateOf($gmt, $format, $time) {
		if (($time = self::toTimestamp($time)) === false)
			return false;
		return $gmt ? gmdate($format, $time) : date($format, $time);
	}

	static function format($format = '%c', $time = null) {
		// Textcube-NG: 정수로 바꿀 수 없는 timestamp 는 원본처럼 false — toTimestamp() 참조(v3.221)
		if (($time = self::toTimestamp($time)) === false)
			return false;
		return strftime_compat(_t($format), $time);
	}

	static function formatGMT($format = '%c', $time = null) {
		// Textcube-NG: 정수로 바꿀 수 없는 timestamp 는 원본처럼 false — toTimestamp() 참조(v3.221)
		if (($time = self::toTimestamp($time)) === false)
			return false;
		return gmstrftime_compat(_t($format), $time);
	}

	static function format2($time) {
		// Textcube-NG: PHP 8.0 부터 date() 의 timestamp 가 nullable 이라 null 이면 현재 시각이 된다 — 원본(PHP 5~7.4)처럼 0(1970-01-01)으로 처리(v3.182)
		if ($time === null) $time = 0;
		// Textcube-NG: 정수로 바꿀 수 없는 timestamp 는 원본처럼 false — toTimestamp() 참조(v3.221)
		if (($time = self::toTimestamp($time)) === false)
			return false;
		if (date('Ymd', $time) == date('Ymd'))
			return strftime_compat(_t('%H:%M'), $time);
		else if (date('Y', $time) == date('Y', time()))
			return strftime_compat(_t('%m/%d'), $time);
		else
			return strftime_compat(_t('%Y'), $time);
	}

	static function format3($time) {
		// Textcube-NG: PHP 8.0 부터 date() 의 timestamp 가 nullable 이라 null 이면 현재 시각이 된다 — 원본(PHP 5~7.4)처럼 0(1970-01-01)으로 처리(v3.182)
		if ($time === null) $time = 0;
		// Textcube-NG: 정수로 바꿀 수 없는 timestamp 는 원본처럼 false — toTimestamp() 참조(v3.221)
		if (($time = self::toTimestamp($time)) === false)
			return false;
		if (date('Ymd', $time) == date('Ymd'))
			return strftime_compat(_t('%H:%M:%S'), $time);
		else
			return strftime_compat(_t('%Y/%m/%d'), $time);
	}

	static function format5($time = null) {
		// Textcube-NG: 정수로 바꿀 수 없는 timestamp 는 원본처럼 false — toTimestamp() 참조(v3.221)
		if (($time = self::toTimestamp($time)) === false)
			return false;
		return strftime_compat(_t('%Y/%m/%d %H:%M'), $time);
	}

	static function formatDate($time = null) {
		// Textcube-NG: 정수로 바꿀 수 없는 timestamp 는 원본처럼 false — toTimestamp() 참조(v3.221)
		if (($time = self::toTimestamp($time)) === false)
			return false;
		return strftime_compat(_t('%Y/%m/%d'), $time);
	}

	static function formatDate2($time = null) {
		// Textcube-NG: 정수로 바꿀 수 없는 timestamp 는 원본처럼 false — toTimestamp() 참조(v3.221)
		if (($time = self::toTimestamp($time)) === false)
			return false;
		return strftime_compat(_t('%Y/%m'), $time);
	}

	static function formatTime($time = null) {
		// Textcube-NG: 정수로 바꿀 수 없는 timestamp 는 원본처럼 false — toTimestamp() 참조(v3.221)
		if (($time = self::toTimestamp($time)) === false)
			return false;
		return strftime_compat(_t('%H:%M:%S'), $time);
	}

	static function get($format = 'YmdHis', $time = null) {
		// Textcube-NG: 정수로 바꿀 수 없는 timestamp 는 원본처럼 false — dateOf() 참조(v3.222)
		return (isset($time) ? self::dateOf(false, $format, $time) : date($format));
	}

	static function getGMT($format = 'YmdHis', $time = null) {
		// Textcube-NG: 정수로 바꿀 수 없는 timestamp 는 원본처럼 false — dateOf() 참조(v3.222)
		return (isset($time) ? self::dateOf(true, $format, $time) : gmdate($format));
	}

	static function getDate($time = null) {
		// Textcube-NG: 정수로 바꿀 수 없는 timestamp 는 원본처럼 false — dateOf() 참조(v3.222)
		return (isset($time) ? self::dateOf(false, 'Ymd', $time) : date('Ymd'));
	}

	static function getYearMonth($time = null) {
		// Textcube-NG: 정수로 바꿀 수 없는 timestamp 는 원본처럼 false — dateOf() 참조(v3.222)
		return (isset($time) ? self::dateOf(false, 'Ym', $time) : date('Ym'));
	}

	static function getYear($time = null) {
		// Textcube-NG: 정수로 바꿀 수 없는 timestamp 는 원본처럼 false — dateOf() 참조(v3.222)
		return (isset($time) ? self::dateOf(false, 'Y', $time) : date('Y'));
	}

	static function getTime($time = null) {
		// Textcube-NG: 정수로 바꿀 수 없는 timestamp 는 원본처럼 false — dateOf() 참조(v3.222)
		return (isset($time) ? self::dateOf(false, 'His', $time) : date('His'));
	}

	static function getRFC1123($time = null) {
		// Textcube-NG: 정수로 바꿀 수 없는 timestamp 는 원본처럼 false — dateOf() 참조(v3.222)
		return (isset($time) ? self::dateOf(false, 'r', $time) : date('r'));
	}

	static function getRFC1123GMT($time = null) {
		// Textcube-NG: 정수로 바꿀 수 없는 timestamp 는 원본처럼 false — dateOf() 참조(v3.222)
		return (isset($time) ? self::dateOf(true, 'D, d M Y H:i:s \G\M\T', $time) : gmdate('D, d M Y H:i:s \G\M\T'));
	}

	static function getRFC1036($time = null) {
		// Textcube-NG: 정수로 바꿀 수 없는 timestamp 는 원본처럼 false — dateOf() 참조(v3.222)
		return ((isset($time) ? self::dateOf(false, 'l, d-M-Y H:i:s ', $time) : date('l, d-M-Y H:i:s ')) . Timezone::getRFC822());
	}

	static function getISO8601($time = null) {
		// Textcube-NG: 정수로 바꿀 수 없는 timestamp 는 원본처럼 false — dateOf() 참조(v3.222)
		return ((isset($time) ? self::dateOf(false, 'Y-m-d\TH:i:s', $time) : date('Y-m-d\TH:i:s')) . Timezone::getISO8601());
	}

	static function getUNIXtime($time = null) {
		// Textcube-NG: 정수로 바꿀 수 없는 timestamp 는 원본처럼 false — dateOf() 참조(v3.222)
		return intval(isset($time) ? self::dateOf(false, 'U', $time) : date('U'));
	}
		
	static function getHumanReadable($time = null, $from = null) {
		if(is_null($from)) $deviation = Timestamp::getUNIXtime() - Timestamp::getUNIXtime($time);
		else $deviation = Timestamp::getUNIXtime($from) - Timestamp::getUNIXtime($time);

		if($deviation > 0) { // Past.
			if ($deviation < 60) {
				return _f('%1초 전',$deviation);		
			} else if ($deviation < 3600) {
				return _f('%1분 전',intval($deviation/60));
			} else if ($deviation < 86400) {
				return _f('%1시간 전',intval($deviation/3600));
			} else if ($deviation < 604800) {
				return _f('%1일 전',intval($deviation/86400));
			} else {
				return _f('%1주 전',intval($deviation/604800));
			}
		} else {
			$deviation = abs($deviation);
			if ($deviation < 60) {
				return _f('%1초 후',$deviation);		
			} else if ($deviation < 3600) {
				return _f('%1분 후',intval($deviation/60));
			} else if ($deviation < 86400) {
				return _f('%1시간 후',intval($deviation/3600));
			} else if ($deviation < 604800) {
				return _f('%1일 후',intval($deviation/86400));
			} else {
				return _f('%1주 후',intval($deviation/604800));
			}			
		}					
	}	
}

final class Timer {
	/**
		Original code is written by Crizin (crizin@gmail.com)
	**/
	private $start, $stop;
	function __construct() {
		$this->start();
	}
	public function start() {
		$this->start = $this->getMicroTime();
	}
	public function pause() {
		$this->stop = $this->getMicroTime();
	}
	public function resume() {
		$this->start += $this->getMicroTime() - $this->stop;
		$this->stop = 0;
	}
	public function fetch($decimalPlaces = 3) {
		return sprintf('%.3f', round(($this->getMicrotime() - $this->start), $decimalPlaces));
	}
	public static function getMicroTime() {
		list($usec, $sec) = explode(' ', microtime());
		return (float)$usec + (float)$sec;
	}
}

final class Path {
	static function getBaseName($path) {
		$pattern = (strncasecmp(PHP_OS, 'WIN', 3) ? '/([^\/]+)[\/]*$/' : '/([^\/\\\\]+)[\/\\\\]*$/');
		if (preg_match($pattern, $path, $matches))
			return $matches[1];
		return '';
	}

	static function getExtension($path) {
		if (preg_match('/.{1}(\.[[:alnum:]]+)$/', $path, $matches))
			return strtolower($matches[1]);
		else
			return '';
	}

	static function getExtension2($path) {
		if (preg_match('/.{1}(\.[[:alnum:]]+(\.[[:alnum:]]+)?)$/', $path, $matches))
			return strtolower($matches[1]);
		else
			return '';
	}

	static function combine($path) {
		$args = func_get_args();
		return implode('/', $args);
	}

	static function removeFiles($directory) {
		if (!is_dir($directory))
			return false;
		$dir = dir($directory);
		while (($file = $dir->read()) !== false) {
			if (is_file(Path::combine($directory, $file)))
				unlink(Path::combine($directory, $file));
		}
		return true;
	}
}


#[AllowDynamicProperties]
class XMLStruct {
	var $struct, $error;

	/* static helper function */

	/*@static@*/
	static function getValueByLocale($param)
	{
		if (!is_array($param)) return $param;
		for ($i = 0; $i < count($param); $i++) {
			if( isset($param[$i]['.attributes']['xml:lang'])) {
				$lang = $param[$i]['.attributes']['xml:lang'];
			} else {
				$lang = "";
			}
			$locale = Locales::getInstance();
			switch ($locale->match($lang)) {
				case 3:
					$matched = $param[$i];
					unset($secondBest);
					unset($thirdBest);
					$i = count($param); // for exit loop
					break;
				case 2:
					$secondBest = $param[$i];
					break;
				case 1:
					$thirdBest = $param[$i];
					break;
				case 0:
					if (!isset($thirdBest))
						$thirdBest = $param[$i];
					break;
			}
		}
		if (isset($secondBest)) {
			$matched = $secondBest;
		} else if (isset($thirdBest)) {
			$matched = $thirdBest;
		}

		if (!isset($matched))
			return null;

		if (isset($matched['.value']))
			return $matched['.value'];
		return null;
	}

	function __construct() {
		$this->ns = array();
		$this->baseindex = 0;
	}

	function setXPathBaseIndex($baseindex=1) {
		$this->baseindex = $baseindex;
	}

	function setNameSpacePrefix( $prefix, $url ) {
		$this->ns[$prefix] = $url;
	}

	function expandNS($item) {
		if( !$this->nsenabled ) {
			return $item;
		}
		foreach( $this->ns as $prefix => $url ) {
			if( substr( $item, 0, strlen($prefix) + 1) == "$prefix:" ) {
				return "$url:" . substr( $item, strlen($prefix) + 1 );
			}
		}
		return $item;
	}

	function open($xml, $encoding = null, $nsenabled = false) {
		if (!empty($encoding) && (strtolower($encoding) != 'utf-8') && !UTF8::validate($xml)) {
			if (preg_match('/^<\?xml[^<]*\s+encoding=["\']?([\w-]+)["\']?/', $xml, $matches)) {
				$encoding = $matches[1];
				$xml = preg_replace('/^(<\?xml[^<]*\s+encoding=)["\']?[\w-]+["\']?/', '$1"utf-8"', $xml, 1);
			}
			if (strcasecmp($encoding, 'utf-8')) {
				$xml = UTF8::bring($xml, $encoding);
				if (is_null($xml)) {
					$this->error = XML_ERROR_UNKNOWN_ENCODING;
					return false;
				}
			}
		} else {
			if (substr($xml, 0, 3) == "\xEF\xBB\xBF")
				$xml = substr($xml, 3);
		}
		$this->nsenabled = $nsenabled;
		if( $nsenabled ) {
			$p = xml_parser_create_ns();
		} else {
			$p = xml_parser_create();
		}
		// PHP 8.4: xml_set_object() 및 메서드명 문자열 핸들러 deprecated → 메서드 Closure 를 직접 등록
		xml_parser_set_option($p, XML_OPTION_CASE_FOLDING, 0);
		xml_set_element_handler($p, Closure::fromCallable(array($this, 'o')), Closure::fromCallable(array($this, 'c')));
		xml_set_character_data_handler($p, Closure::fromCallable(array($this, 'd')));
		xml_set_default_handler($p, Closure::fromCallable(array($this, 'x')));
		$this->struct = array();
		$this->_cursor = &$this->struct;
		$this->_path = array('');
		$this->_cdata = false;
		if (!xml_parse($p, $xml))
			return $this->_error($p);
		unset($this->_cursor);
		unset($this->_cdata);
		if (xml_get_error_code($p) != XML_ERROR_NONE)
			return $this->_error($p);
		return true;
	}

	function openFile($filename, $correct = false) {
		if (!$fp = fopen($filename, 'rb'))
			return false;
		$p = xml_parser_create();
		// PHP 8.4: xml_set_object() 및 메서드명 문자열 핸들러 deprecated → 메서드 Closure 를 직접 등록
		xml_parser_set_option($p, XML_OPTION_CASE_FOLDING, 0);
		xml_set_element_handler($p, Closure::fromCallable(array($this, 'o')), Closure::fromCallable(array($this, 'c')));
		xml_set_character_data_handler($p, Closure::fromCallable(array($this, 'd')));
		xml_set_default_handler($p, Closure::fromCallable(array($this, 'x')));
		$this->struct = array();
		$this->_cursor = &$this->struct;
		$this->_path = array('');
		$this->_cdata = false;
		if ($correct) {
			$remains = '';
			while (!feof($fp)) {
				$chunk = $remains . fread($fp, 10240);
				$remains = '';
				if (strlen($chunk) >= 10240) {
					for ($c = 1; $c <= 4; $c++) {
						switch ($chunk[strlen($chunk) - $c] & "\xC0") {
							case "\x00":
							case "\x40":
								if ($c > 1) {
									$remains = substr($chunk, strlen($chunk) - $c + 1);
									$chunk = substr($chunk, 0, strlen($chunk) - $c + 1);
								}
								$c = 5;
								break;
							case "\xC0":
								$remains = substr($chunk, strlen($chunk) - $c);
								$chunk = substr($chunk, 0, strlen($chunk) - $c);
								$c = 5;
								break;
						}
					}
				}
				if (!xml_parse($p, UTF8::correct($chunk, '?'), false)) {
					fclose($fp);
					return $this->_error($p);
				}
			}
			if (($remains !== '') && !xml_parse($p, UTF8::correct($remains, '?'), false)) {
				fclose($fp);
				return $this->_error($p);
			}
		} else {
			while (!feof($fp)) {
				if (!xml_parse($p, fread($fp, 10240), false)) {
					fclose($fp);
					return $this->_error($p);
				}
			}
		}
		fclose($fp);
		if (!xml_parse($p, '', true))
			return $this->_error($p);
		unset($this->_cursor);
		unset($this->_cdata);
		if (xml_get_error_code($p) != XML_ERROR_NONE)
			return $this->_error($p);
		return true;
	}

	function close() {
	}

	function setStream($path) {
		$this->_streams[$path] = true;
	}

	function setConsumer($consumer) {
		$this->_consumer = $consumer;
	}

	function & selectNode($path, $lang = null) {
		$path = explode('/', $path);
		if (array_shift($path) != '') {
			$null = null;
			return $null;
		}
		$cursor = &$this->struct;

		while (is_array($cursor) && ($step = array_shift($path))) {
			$step = $this->expandNS($step);
			if (!preg_match('/^([^[]+)(\[(\d+|lang\(\))\])?$/', $step, $matches)) {
				$null = null;
				return $null;
			}
			$name = $matches[1];
			if (!isset($cursor[$name][0])) {
				$null = null;
				return $null;
			}

			if (count($matches) != 4) { // Node name only.
				if (isset($cursor[$name][0])) {
					$cursor = &$cursor[$name][0];
				} else {
					$null = null;
					return $null;
				}
			} else if ($matches[3] != 'lang()') { // Position.
				/* see http://dev.textcube.org/ticket/430 */
				$index = $matches[3];
				$index -= $this->baseindex;

				if (isset($cursor[$name][$index])) {
					$cursor = &$cursor[$name][$index];
				} else {
					$null = null;
					return $null;
				}
			} else { // lang() expression.
				for ($i = 0; $i < count($cursor[$name]); $i++) {
					if( isset($cursor[$name][$i]['.attributes']['xml:lang'])) {
						$lang = $cursor[$name][$i]['.attributes']['xml:lang'];
					} else {
						$lang = "";
					}
					$locale = Locales::getInstance();

					switch ($locale->match($lang)) {
						case 3:
							$cursor = &$cursor[$name][$i];
							return $cursor;
						case 2:
							$secondBest = &$cursor[$name][$i];
							break;
						case 1:
							$thirdBest = &$cursor[$name][$i];
							break;
						case 0:
							if (!isset($thirdBest))
								$thirdBest = &$cursor[$name][$i];
							break;
					}
				}
				if (isset($secondBest)) {
					$cursor = &$secondBest;
				} else if (isset($thirdBest)) {
					$cursor = &$thirdBest;
				} else {
					$null = null;
					return $null;
				}
			}
		}
		return $cursor;
	}

	function & selectNodes($path) {
		/*
		if ($path[strlen($path) - 1] == ']') {
			$null = null;
			return $null;
		}
		*/
		$p = explode('/', $path);
		if (array_shift($p) != '') {
			$null = null;
			return $null;
		}
		$c = &$this->struct;

		while ($d = array_shift($p)) {
			$o = 0;
			if ($d[strlen($d) - 1] == ']') {
				@list($d, $o) = explode('[', $d, 2);
				if (is_null($o)) {
					$null = null;
					return $null;
				}
				$o = substr($o, 0, strlen($o) - 1);
				if (!is_numeric($o)) {
					$null = null;
					return $null;
				}

				$o -= $this->baseindex; /* see http://dev.textcube.org/ticket/430 */
			}
			$d = $this->expandNS($d);
			if (empty($p)) {
				if (isset($c[$d])) {
					return $c[$d];
				} else {
					$null = null;
					return $null;
				}
			}
			if (isset($c[$d][$o]))
				$c = &$c[$d][$o];
			else
				break;
		}
		$null = null;
		return $null;
	}

	function doesExist($path) {
		return (!is_null($this->selectNode($path)));
	}

	function getAttribute($path, $name, $default = null) {
		$n = &$this->selectNode($path);
		if ((!is_null($n)) && isset($n['.attributes'][$name]))
			return $n['.attributes'][$name];
		else
			return $default;
	}

	function getValue($path) {
		$n = &$this->selectNode($path);
		return (isset($n['.value']) ? $n['.value'] : null);
	}

	function getNodeCount($path) {
		// Textcube-NG: selectNodes() 가 실패하면 null — count(null) 은 PHP 7.2 부터 경고, PHP 8.0 부터 TypeError. 원본(PHP 5)처럼 0 을 돌려준다(v3.182)
		$nodes = $this->selectNodes($path);
		return is_array($nodes) ? count($nodes) : 0;
	}

	private function o($p, $n, $a) {
		if (!isset($this->_cursor[$n]))
			$this->_cursor[$n] = array();
		if (empty($a))
			$this->_cursor = &$this->_cursor[$n][array_push($this->_cursor[$n], array('.value' => '', '_' => &$this->_cursor)) - 1];
		else
			$this->_cursor = &$this->_cursor[$n][array_push($this->_cursor[$n], array('.attributes' => $a, '.value' => '', '_' => &$this->_cursor)) - 1];
		$this->_cdata = null;
		array_push($this->_path, $n);
		if (isset($this->_streams[implode('/', $this->_path)]))
			$this->_cursor['.stream'] = tmpfile();
	}

	private function c($p, $n) {
		if (count($this->_cursor) != (2 + isset($this->_cursor['.attributes'])))
			unset($this->_cursor['.value']);
		else
			$this->_cursor['.value'] = rtrim($this->_cursor['.value']);
		$c = &$this->_cursor;
		$this->_cursor = &$this->_cursor['_'];
		unset($c['_']);
		if (isset($this->_consumer)) {
			if (call_user_func($this->_consumer, implode('/', $this->_path), $c, xml_get_current_line_number($p))) {
				if (count($this->_cursor[$n]) == 1)
					unset($this->_cursor[$n]);
				else
					array_pop($this->_cursor[$n]);
			}
		}
		array_pop($this->_path);
	}

	private function d($p, $d) {
		if (count($this->_cursor) != (1 + isset($this->_cursor['.value']) + isset($this->_cursor['.attributes']) + isset($this->_cursor['.stream'])))
			return;
		if (!$this->_cdata) {
			if (isset($this->_cdata))
				$this->_cursor['.value'] = rtrim($this->_cursor['.value']);
			$this->_cdata = true;
			$d = ltrim($d);
		}
		if (strlen($d) == 0)
			return;
		if (empty($this->_cursor['.stream']))
			$this->_cursor['.value'] .= $d;
		else
			fwrite($this->_cursor['.stream'], $d);
	}

	private function x($p, $d) {
		if ($d == '<![CDATA[')
			$this->_cdata = true;
		else if (($d == ']]>') && $this->_cdata)
			$this->_cdata = false;
	}

	private function _error($p) {
		$this->error = array(
			'code' => xml_get_error_code($p),
			'offset' => xml_get_current_byte_index($p),
			'line' => xml_get_current_line_number($p),
			'column' => xml_get_current_column_number($p)
		);
		return false;
	}
}

final class URL {
	static function encode($url,$useEncodedURL = true) {
		$url = $url ?? '';
		$postfix = '';
		if(substr($url,strlen($url)-1) == '?') {
			$url = substr($url,0,strlen($url)-1);
			$postfix = '?';
		}
		if ($useEncodedURL == true)
			return str_replace('%2F', '/', rawurlencode($url)).$postfix;
		else
			return str_replace(array('%', ' ', '"', '#', '&', '\'', '<', '>', '?', '+'), array('%25', '%20', '%22', '%23', '%26', '%27', '%3C', '%3E', '%3F', '%2B'), $url).$postfix;
	}

	static function decode($url,$useEncodedURL = true) {
		$url = $url ?? '';
		if ($useEncodedURL == true)
			return rawurldecode($url);
		else
			return urldecode($url);
	}

	// Textcube-NG: 스킴 없는 서비스 주소(//host…)에 스킴을 붙여 절대 주소로 돌려준다. $scheme('https'·'http')이 있으면 그 값, 없으면 service.useSSL 기준(URIHandler 의 uri.host·uri.default 와 같은 규칙).
	//   그 밖의 값(빈 값·스킴이 있는 값·상대 경로·문자열 아님)은 그대로 돌려준다. 피드·메일·Referer·소셜 로그인 콜백 등 절대 주소가 필요한 외부 출력에서만 쓰고 화면 HTML 은 //host 그대로 둔다(r3-34)
	static function toAbsolute($url, $scheme = null) {
		if (!is_string($url) || strncmp($url, '//', 2) !== 0) return $url;
		if ($scheme !== 'https' && $scheme !== 'http')
			$scheme = Model_Context::getInstance()->getProperty('service.useSSL', false) ? 'https' : 'http';
		return $scheme . ':' . $url;
	}
}
?>
