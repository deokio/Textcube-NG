<?php
function TextcubeBirthday_TextcubeBirthday($target) {
	global $configVal;
	requireComponent('Textcube.Function.misc');
	requireModel('blog.api');
	$data = Setting::fetchConfigVal( $configVal);
	$month = 3;
	$day = 13;
	if( !is_null( $data ) ){
		$month = $data['month'];
		$day = $data['day'];
	}
	// Textcube-NG: 설정값을 원본(PHP 5) gmmktime() 과 같은 정수로 바꾼다 — 바꿀 수 없으면 원본처럼 gmmktime() 결과 false(= 0)로 계산한다.
	// PHP 8.0 부터 비숫자 문자열은 TypeError, null 은 현재 날짜(원본 0), PHP 7.0 부터 '0x..' 는 0(v3.181)
	$monthArg = TextcubeBirthday_toLong($month);
	$dayArg = TextcubeBirthday_toLong($day);
	$validArg = ($monthArg !== false && $dayArg !== false);
	$dDay = intval((($validArg ? gmmktime(0, 0, 0, $monthArg, $dayArg) : 0) - time()) / 86400);
	if ($dDay < 0)
		$dDay = intval((($validArg ? gmmktime(0, 0, 0, $monthArg, $dayArg, (int)gmdate('Y') + 1) : 0) - time()) / 86400);
	if ($dDay == 0)
		$message = '<span class="congratu">'._t("탄생을 축하합니다!").'</span>';
	else
		$message = "<span>"._f("앞으로 %1일 남음", $dDay)."</span>";
	ob_start();
?>
      <div class="listbox">
        <h3><?php echo _t("텍스트큐브 생일");?></h3>
        <div style="text-align:center"><?php echo _f("%1월 %2일:", (int)$month, (int)$day);?> <?php echo $message;?></div>
      </div>
<?php
	$target = ob_get_contents();
	ob_end_clean();
	return $target;
}
function TextcubeBirthdayDataSet($DATA){
	requireComponent('Textcube.Function.misc');
	$cfg = Setting::fetchConfigVal( $DATA );
	// if( $cfg['month'] != 날짜냐?) return "잘못된 날짜입니다.";
	// 등등등등 여기서 원하는 검증을 하시고 검증 실패시 사용자에게 보여줄 에러메세지를 보내주심 됩니다.
	// 성공하면 그냥 true
	return true;
}
/* Textcube-NG: PHP 5 의 정수 인자 변환(zpp 'l')과 같은 정수를 돌려준다. 바꿀 수 없으면 false — 원본(PHP 5)에서는 이때 경고 뒤
   mktime()·gmmktime()·gmstrftime() 이 false 를 돌려줬다. PHP 7.0 부터 '0x..' 는 숫자 문자열이 아니고, PHP 8.0 부터 비숫자·앞부분만 숫자인 문자열은 TypeError. 정수 범위를 넘는 정수 문자열은 PHP 5(is_numeric_string: 앞 0 을 뺀 자릿수를 PHP_INT_MIN 의 자릿수와 비교하고, 같으면 그 첫 자리부터 NUL 앞까지 strcmp — 숫자 뒤가 [eE][+-] 면 한 칸 밀린 자리부터)처럼 double 을 거쳐 바꾼다(v3.181) */
function TextcubeBirthday_toLong( $value )
{
	if( is_int( $value ) )
		return $value;
	if( is_null( $value ) || is_bool( $value ) )
		return (int)$value;
	if( is_float( $value ) )
		return api_floatToLong( $value );
	if( !is_string( $value ) )
		return false;
	$body = ltrim( $value, " \t\n\r\x0B\f" );
	if( strlen( $body ) > 2 && $body[0] === '0' && ( $body[1] === 'x' || $body[1] === 'X' ) )
		return api_floatToLong( hexdec( substr( $body, 2, strspn( $body, '0123456789abcdefABCDEF', 2 ) ) ) );
	if( preg_match( '/^[+-]?(?:[0-9]+(?:\.[0-9]*)?|\.[0-9]+)(?:[eE][+-]?[0-9]+)?/', $body, $m ) ) {
		if( strpbrk( $m[0], '.eE' ) !== false )
			return api_floatToLong( (float)$m[0] );
		$digits = ltrim( $m[0], '+-0' );
		$limit = substr( (string)PHP_INT_MIN, 1 );
		$cmp = strlen( $digits ) - strlen( $limit );
		if( $cmp == 0 ) {
			$tail = substr( $body, strlen( $m[0] ) - strlen( $digits ) + preg_match( '/^[eE][+-]/', substr( $body, strlen( $m[0] ) ) ) );
			$cmp = strcmp( substr( $tail, 0, strcspn( $tail, "\0" ) ), $limit );
		}
		return ( $cmp < 0 || ( $cmp == 0 && $m[0][0] === '-' ) ) ? (int)$m[0] : api_floatToLong( (float)$m[0] );
	}
	return false;
}
?>
