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

/*--------- Basic functions -----------*/

function api_setHint( $hint, $value = true )
{
	global $arr_hint;
	if( !$arr_hint )
	{
		$arr_hint = array();
	}
	$arr_hint[$hint] = $value;
}

function api_getHint( $hint )
{
	global $arr_hint;
	if( $arr_hint && isset( $arr_hint[$hint] ) )
	{
		return $arr_hint[$hint];
	}
	return false;
}

function api_get_request_id( $id )
{
	if(array_key_exists('id', $_GET))
	{
		return $_GET["id"];
	}
	return $id;
}

function api_get_canonical_id( $id )
{
	$alias_file = ROOT . "/.htaliases";
	$canon = api_get_request_id( $id );
	
	if( $id == "" || !file_exists( $alias_file ) )
	{
		return $canon;
	}
	
	$fd = fopen( $alias_file, "r" );
	if( $fd == FALSE )
	{
		return $canon;
	}
	while( !feof($fd) )
	{
		$line = fgets( $fd, 1024 );
		if( substr($line,0,1) == "#" )
		{
			continue;
		}
		$match = preg_split( '/( |\t|\r|\n)+/', $line );
		if( $id == $match[0] )
		{
			$canon = $match[1];
			break;
		}
	}
	fclose( $fd );
	return $canon;
}

function api_login( $id, $password )
{
	$auth = new Auth;
	if( !$auth->login( $id, $password ) )
	{
		$canon_id = api_get_canonical_id($id);
		if( !$auth->login( $canon_id, $password ) )
		{
			return new XMLRPCFault( 1, "Authentication failed: $id($canon_id)" );
		}
	}
	/* [보안] 원본은 인증 성공만 확인해, 이 블로그의 구성원이 아닌 계정으로도 글 작성·수정·삭제가 가능했다.
	   현재 블로그의 관리자·작성자 권한(doesHaveOwnership)을 요구한다. */
	if( !doesHaveOwnership() )
	{
		return new XMLRPCFault( 1, "Permission denied" );
	}
	return false;
}

/* [보안] 글 수정·삭제 권한: 관리 화면(owner/entry)과 동일하게 편집자(group.editors)가 아니면 본인 글만 허용.
   원본 BlogAPI 는 같은 블로그의 다른 작성자 글도 수정·삭제할 수 있었다. 허용 시 false, 거부 시 XMLRPCFault. */
function api_check_entry_permission( $id )
{
	if( Acl::check('group.editors') )
	{
		return false;
	}
	requireModel('blog.entry');
	if( getUserIdOfEntry( getBlogId(), intval($id) ) == getUserId() )
	{
		return false;
	}
	return new XMLRPCFault( 1, "Permission denied" );
}

function api_utf8_substr($str,$start) 
{ 
	preg_match_all("/./u", $str, $ar); 
	
	if(func_num_args() >= 3) { 
		$end = func_get_arg(2); 
		return join("",array_slice($ar[0],$start,$end)); 
	} else { 
		return join("",array_slice($ar[0],$start)); 
	} 
} 

function api_get_title( $content )
{
	if( preg_match( "{<title>(.+)?</title>}", $content, $match ) )
	{
		return $match[1];
	}
	$title = preg_replace( "{<.*?>}", "", $content);
	$title = api_utf8_substr( $title, 0, 40 );
	return $title;
}

function api_escape_content( $content )
{
	$content = str_replace( "\r", '', $content );
	return htmlspecialchars($content);
}

// Textcube-NG: PHP 8.5 부터 int 로 나타낼 수 없는 float(범위 밖·NAN·INF)를 (int) 로 바꾸면 경고가 난다. PHP 7.0~8.4 의 (int) 와 같은 값(2^64 로 나눈 나머지를 부호 있는 정수로, NAN·INF 는 0)을 경고 없이 돌려준다. int(hexdec() 이 범위 안에서 돌려준 값)는 그대로 둔다(v3.222)
function api_floatToLong( $value )
{
	if( is_int( $value ) )
		return $value;
	if( !is_finite( $value ) )
		return 0;
	if( $value < -9223372036854775808.0 || $value >= 9223372036854775808.0 ) {
		$value = fmod( $value, 18446744073709551616.0 );
		if( $value < 0 )
			$value += 18446744073709551616.0;
		if( $value >= 9223372036854775808.0 )
			$value -= 18446744073709551616.0;
	}
	return (int)$value;
}

/* Textcube-NG: PHP 5 의 정수 인자 변환(zpp 'l')과 같은 정수를 돌려준다. 바꿀 수 없으면 false — 원본(PHP 5)에서는 이때 경고 뒤
   mktime()·gmmktime()·gmstrftime() 이 false 를 돌려줬다. PHP 7.0 부터 '0x..' 는 숫자 문자열이 아니고, PHP 8.0 부터 비숫자·앞부분만 숫자인 문자열은 TypeError. 정수 범위를 넘는 정수 문자열은 PHP 5(is_numeric_string: 앞 0 을 뺀 자릿수를 PHP_INT_MIN 의 자릿수와 비교하고, 같으면 그 첫 자리부터 NUL 앞까지 strcmp — 숫자 뒤가 [eE][+-] 면 한 칸 밀린 자리부터)처럼 double 을 거쳐 바꾼다(v3.181) */
function api_toLong( $value )
{
	if( is_int( $value ) )
		return $value;
	if( is_null( $value ) || is_bool( $value ) )
		return (int)$value;
	// Textcube-NG: float·double 을 거치는 변환은 api_floatToLong() 으로 PHP 8.5 경고 없이 바꾼다(v3.222)
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

function api_timestamp( $date8601 )
{
	// Textcube-NG: 문자열이 아니면 원본처럼 그대로 돌려준다 — 원본은 substr() 결과가 "T" 가 아니어서 그대로 반환했다(배열은 경고 뒤 반환).
	// PHP 8.0 배열 TypeError·PHP 8.1 substr(null) 지원 중단 경고를 피한다(v3.181)
	if( !is_string( $date8601 ) || substr( $date8601, 8,1 ) != "T" )
	{
		return $date8601;
	}
	// Textcube-NG: 원본(PHP 5)은 시작 위치가 길이 이상인 조각을 substr() 이 false 로 돌려 mktime() 이 0 으로 받았고, 정수로 바꿀 수 없는 조각이면
	// mktime() 이 경고 뒤 false 를 돌려 결과가 getOffset() + false(= 오프셋)였다. PHP 7.0 부터 시작 위치 = 길이는 '', PHP 8.0 부터 '' 는 TypeError(v3.181)
	$args = array();
	foreach( array( array( 9, 2 ), array( 12, 2 ), array( 15, 2 ), array( 4, 2 ), array( 6, 2 ), array( 0, 4 ) ) as $piece )
	{
		$value = ( $piece[0] >= strlen( $date8601 ) ) ? 0 : api_toLong( substr( $date8601, $piece[0], $piece[1] ) );
		if( $value === false )
			return Timezone::getOffset();
		$args[] = $value;
	}
	return Timezone::getOffset() + 
		mktime( $args[0], $args[1], $args[2], $args[3], $args[4], $args[5] );
}

function api_dateiso8601( $timestamp )
{
	// Textcube-NG: 원본 gmstrftime()(PHP 8.1 지원 중단 → v1.91 에서 gmdate())은 PHP 5 에서 null 을 0 으로, 정수로 바꿀 수 없는 값은 경고 뒤 false 로 처리했다.
	// gmdate() 는 null 이면 현재 시각이고 PHP 8.0 부터 비숫자 문자열은 TypeError, 8.1 부터 소수부가 있는 float 는 지원 중단 경고라 같은 정수로 바꿔 넘긴다(v3.181)
	$timestamp = api_toLong( $timestamp );
	if( $timestamp === false )
		return false;
	return gmdate('Ymd\TH:i:s', $timestamp);
}


function send_failure( $msg )
{
	print(  "<methodResponse>\n" .
			"<fault><value><struct>\n" .
			"<member>\n" .
			"<name>faultCode</name>\n" .
			"<value><int>1</int></value>\n" .
			"</member>\n" .
			"<member>\n" .
			"<name>faultString</name>\n" .
			"<value><string>" . api_escape_content($msg) . "</string></value>\n" .
			"</member>\n" .
			"</struct></value></fault>\n" .
			"</methodResponse>\n" );
}

function api_getCategoryIdByName( $name_array )
{
	if ($name_array === '') return 0;
	if (count($name_array) <= 0) return 0;
	$category = new Category();
	$category->open(false);
	
	$name = strtolower( $name_array[0] );
	$id = 0;
	$numeric_id = null;
	
	while(1)
	{
		if( strtolower($category->label) == $name ) {
			$id = $category->id;
			break;
		}
		if( $category->id == $name ) {
			$numeric_id = $category->id;
		}
		if( !$category->shift() )
		{
			break;
		}
	}

	if( $id == 0 && $numeric_id !== null ) {
		$id = $numeric_id;
	}
	
	$category->close();
	return $id;
	
}

function api_getCategoryNameById( $id )
{
	$category = new Category();
	$category->open();
	
	$name = $id;
	
	while(1)
	{
		if( $category->id == $id )
		{
			$name = $category->name;
			break;
		}
		if( !$category->shift() )
		{
			break;
		}
	}
	
	$category->close();
	return $name;
	
}

function api_fix_content( $content )
{
	$ctx = Model_Context::getInstance();
	$new_content = "";
	if( strstr( $_SERVER["HTTP_USER_AGENT"], 'Zoundry' ) )
	{
		$new_content = str_replace( "</ul>\n", "</ul>", $content );
		$new_content = str_replace( "</ol>\n", "</ol>", $new_content );
		$new_content = str_replace( "</li>\n", "</li>", $new_content );
		$new_content = str_replace( "</div>\n", "</div>", $new_content );
		$new_content = str_replace( "</p>\n", "</p>", $new_content );
	}
	else
	{
		$new_content = $content;
	}
	// Fix attachment URL into TTML-spcific address.
	/* Textcube-NG: 원본은 이 함수에 없는 $blogid 를 써서(PHP 8 Warning) '…/attach/' 까지만 바꿨다(newMediaObject 주소도 같은 이유로
	   '…/attach//이름' 이라 우연히 맞았음) → 두 곳 모두 getBlogId(). [##_ATTACH_PATH_##] 는 '…/attach/블로그id' 로 바뀜(common.module.php) */
	$new_content = str_replace($ctx->getProperty('uri.host').$ctx->getProperty('service.path')."/attach/".getBlogId(), '[##_ATTACH_PATH_##]',$new_content);
	return $new_content;
}

function api_make_post( $param, $ispublic, $postid = -1 )
{
	$post = new Post();
	if( $postid != -1 )
	{
		if( !$post->open( $postid ) )
		{
			return false;
		}
	}
	
	$post->content = api_fix_content( $param['description'] );
	$post->contentformatter = getDefaultFormatter();
	$post->contenteditor = getDefaultEditor();
	$post->title = $param['title'];
	
	//$param['mt_excerpt'] = array_key_exists('mt_excerpt', $param) ? $param['mt_excerpt'] : '';
	//$param['tagwords'] = array_key_exists('tagwords', $param) ? $param['tagwords'] : array();
	$param['categories'] = array_key_exists('categories', $param) ? $param['categories'] : '';
	$param['dateCreated'] = array_key_exists('dateCreated', $param) ? $param['dateCreated'] : api_dateiso8601(time());
	$param['mt_allow_comments'] = array_key_exists('mt_allow_comments', $param) ? $param['mt_allow_comments'] : '';
	$param['mt_allow_pings'] = array_key_exists('mt_allow_pings', $param) ? $param['mt_allow_pings'] : '';
	$param['mt_keywords'] = array_key_exists('mt_keywords', $param) ? $param['mt_keywords'] : '';

	if( api_getHint("TagsFromCategories") )
	{
		$post->tags = array_merge( $param['categories'], explode(',', $param['mt_excerpt']) , $param['tagwords'] );
	}
	else
	{
		//$post->tags = array_merge( explode(",", $param['mt_excerpt']) , $param['tagwords'] );
		$post->tags = explode(',', $param['mt_keywords']);
		$post->category = api_getCategoryIdByName( $param['categories'] );
	}

	$extra_category = api_getHint("Category");
	if( !empty($extra_category) ) {
		$post->category = api_getCategoryIdByName( array($extra_category) );
	}
	$post->created = api_timestamp( $param['dateCreated'] );
	$post->modified = api_timestamp( $param['dateCreated'] );
	$post->published = api_timestamp( $param['dateCreated'] );
	
	$post->acceptcomment = $param['mt_allow_comments'] !== 0 ? true : false;
	$post->accepttrackback = $param['mt_allow_pings'] !== 0 ? true : false;
	
	if( $ispublic )
	{
		$post->visibility = "public";
	}
	else
	{
		$post->visibility = "private";
	}
	
	return $post;
}

function api_get_post( $post, $type = "bl" )
{ 
	$post->loadTags();
	//$params = func_get_args();
	global $hostURL, $blogURL;
	return array( 
			"userid" => "",
			"dateCreated" => api_dateiso8601( $post->created ),
			"datePosted" => api_dateiso8601( $post->published ),
			"dateModified" => api_dateiso8601( $post->modified ),
			"title" =>  api_escape_content($post->title),
			"postid" => $post->id,
			"categories" => array( api_getCategoryNameById($post->category) ),
			"link" => $hostURL . $blogURL . "/" . $post->id ,
			"permaLink" => $hostURL . $blogURL . "/" . $post->id ,
			"description" => ($type == "mt" ? $post->content : "" ),
			"content" => $post->content,
			"mt_allow_comments" => $post->acceptcomment ? 1 : 0,
			"mt_allow_pings" => $post->accepttrackback ? 1 : 0,
			"mt_keywords" => join( ",", $post->tags )
			);
}

/* Copied from blog/owner/entry/attach/index.php:addAttachment */

function api_file_hash( $content )
{
	$md5sum = md5( $content );
	return sprintf( "ta%stt%ser%s", substr( $md5sum, 0, 7 ), substr( $md5sum, 7, 7 ), substr( $md5sum, 14, 7 ) );
}


function api_addAttachment($blogid,$parent,$file) {
	global $database;
	
	$attachment=array();
	$attachment['parent']=$parent?$parent:0;
	$attachment['label']=Path::getBaseName($file['name']);
	$label=POD::escapeString(UTF8::lessenAsEncoding($attachment['label'],64));
	$attachment['size']=$file['size'];
	$extension=Path::getExtension($attachment['label']);
	switch(strtolower($extension)) {
		case '.exe':
		case '.php':
		case '.sh':
		case '.com':
		case '.bat':
			$extension='.xxx';
			break;
	}
	/* [보안] 위 목록은 .phtml·.phar·.php5 등 서버가 실행할 수 있는 확장자를 놓친다 → Attachment::isExecutableName 기준으로 .xxx
	   (Moblog 메일 첨부·BlogAPI newMediaObject 경로) */
	if (Attachment::isExecutableName($extension))
		$extension = '.xxx';
	
	/* Create directory for owner */
	$path = __TEXTCUBE_ATTACH_DIR__."/$blogid";
	if(!is_dir($path)) {
		mkdir($path);
		if(!is_dir($path))
			return false;
		@chmod($path,0777);
	}
	
	$oldFile = POD::queryCell("SELECT name FROM {$database['prefix']}Attachments WHERE blogid=$blogid AND parent=$parent AND label = '$label'");
	
	// Textcube-NG: r3-27 R-453 — 같은 label 의 기존 이름($oldFile)이 서버가 실행할 수 있는 확장자(.php 등)이면 deleteAttachment() 로 옛 행·파일을 지우고 $oldFile = null 로 새 이름(위 $extension 이 .xxx 로 바꾼 값)을 만든다(label 중복·옛 실행 파일 잔존 방지)
	if ($oldFile !== null && Attachment::isExecutableName((string) $oldFile)) {
		deleteAttachment($blogid, $parent, $oldFile);
		$oldFile = null;
	}
	if ($oldFile !== null) {
		$attachment['name'] = $oldFile;
	} else {
		$attachment['name'] = random_int(1000000000, 9999999999) . $extension;
		
		while (Attachment::doesExist($attachment['name']))
		$attachment['name'] = random_int(1000000000, 9999999999) . $extension;
	}
	
	
	$attachment['path'] = "$path/{$attachment['name']}";
	
	/* [보안] deleteAttachment() 가 parent 를 함께 확인하므로 위에서 찾은 같은 이름의 기존 첨부($oldFile, parent=$parent)의 parent 로 지운다
	   (원본의 -1 은 이름만으로 지우던 때의 값 — 그대로 두면 기존 행이 남아 INSERT 가 기본 키 중복으로 실패) */
	deleteAttachment($blogid,$attachment['parent'],$attachment['name']);
	
	if( $file['content'] )
	{
		$f = fopen( $attachment['path'], "w" );
		if( !$f )
		{
			return false;
		}
		$attachment['size'] = fwrite( $f, $file['content'] );
		fclose( $f );
		$file['tmp_name'] = $attachment['path'];
	}
	
	// Textcube-NG: 그림 검사(r3-20) — addAttachment() 와 같은 기준(Attachment::classifyUpload). 거부되면 이 함수가 쓴 파일을 지우고 실패.
	// svg 정화에 실패하면 확장자를 .xxx 로 바꾼 새 이름으로 옮긴다(같은 label 의 기존 이름 $oldFile 을 다시 쓰면 .svg 일 수 있음). API 라 알림은 없다
	$imageCheck = array('ok' => true, 'error' => '', 'kind' => 'other', 'type' => '', 'width' => 0, 'height' => 0, 'svgExternal' => 0);
	if (isset($file['tmp_name']) && is_string($file['tmp_name']) && is_file($file['tmp_name']))
		$imageCheck = Attachment::classifyUpload($file['tmp_name'], $attachment['label'], $attachment['size'], false);
	if (!$imageCheck['ok']) {
		if ($file['tmp_name'] === $attachment['path'])
			@unlink($attachment['path']);
		return false;
	}
	if (($imageCheck['kind'] === 'svg') && ($imageCheck['error'] === 'svgInvalid')) {
		$extension = '.xxx';
		do {
			$newName = random_int(1000000000, 9999999999) . $extension;
		} while (Attachment::doesExist($newName) || file_exists("$path/$newName"));
		if ($file['tmp_name'] === $attachment['path']) {
			if (!@rename($attachment['path'], "$path/$newName")) {
				@unlink($attachment['path']);
				return false;
			}
			$file['tmp_name'] = "$path/$newName";
		}
		$attachment['name'] = $newName;
		$attachment['path'] = "$path/$newName";
	}
	
	if($imageAttributes=@getimagesize($file['tmp_name'])) {
		$attachment['mime']=$imageAttributes['mime'];
		$attachment['width']=$imageAttributes[0];
		$attachment['height']=$imageAttributes[1];
	}else{
		$attachment['mime']=Misc::getMIMEType($extension);
		$attachment['width']=0;
		$attachment['height']=0;
	}
	// Textcube-NG: 그림 검사 결과 반영(r3-20) — addAttachment() 와 같다(svg mime·가로세로, 재인코딩 옵트인, 크기 다시 재기, svg PNG 변환본 옵트인)
	$refreshSize = false;
	if ($imageCheck['kind'] === 'svg') {
		if ($imageCheck['error'] === 'svgInvalid') {
			$attachment['mime'] = 'application/octet-stream';
			$attachment['width'] = 0;
			$attachment['height'] = 0;
		} else {
			$attachment['mime'] = 'image/svg+xml';
			$attachment['width'] = (int)$imageCheck['width'];
			$attachment['height'] = (int)$imageCheck['height'];
			makeAttachmentSVGRaster($blogid, $file['tmp_name'], $attachment['name']);
		}
		$refreshSize = true;
	} else if (($imageCheck['kind'] === 'raster') && Attachment::reencodeImage($file['tmp_name'], $imageCheck['type'])) {
		$refreshSize = true;
	}
	if ($refreshSize) {
		clearstatcache(true, $file['tmp_name']);
		$fileSize = @filesize($file['tmp_name']);
		if ($fileSize !== false)
			$attachment['size'] = $fileSize;
	}
	
	$attachment['mime']=UTF8::lessenAsEncoding($attachment['mime'], 32);

	@chmod($attachment['path'],0666);
	// Prepared statement — INSERT with external user input (label from filename, SQL Injection 대응)
	$stmt = POD::prepare("INSERT INTO {$database['prefix']}Attachments VALUES (?,?,?,?,?,?,?,?,UNIX_TIMESTAMP(),0,0)");
	if (!$stmt) {
		@unlink($attachment['path']);
		return false;
	}
	// types: blogid(i) parent(i) name(s) label(s) mime(s) size(i) width(i) height(i) = 8 params
	POD::bindAndExecute($stmt, 'iisssiii',
		(int)$blogid,
		(int)$attachment['parent'],
		(string)$attachment['name'],
		(string)$attachment['label'],
		(string)$attachment['mime'],
		(int)$attachment['size'],
		(int)$attachment['width'],
		(int)$attachment['height']
	);
	$result = $stmt->affected_rows > 0 || $stmt->errno === 0;
	$stmt->close();
	if(!$result) {
		@unlink($attachment['path']);
		return false;
	}
	return $attachment;
}


/* Up to here, copied from blog/owner/entry/attach/index.php */

function api_get_attaches( $content)
{
	preg_match_all( "/attach\\/".getBlogId()."\\/(ta.{7}tt.{7}er.{7}\\.[a-z]{2,5})/", $content, $matches );
	return $matches[1];
}

function api_update_attaches( $parent, $attaches = null)
{
	global $database;
	/* [보안] 원본은 API 사용자와 관계없이 이 블로그의 새 글 임시 첨부(parent=0)를 모두 옮겼다
	   → API 로 로그인한 사용자의 임시 첨부(parent = -(userid+1), newMediaObject)만 옮긴다 */
	$temporary = getTemporaryAttachmentParent(getUserId());
	if ($temporary === false)
		return;
	if (is_null($attaches)) {
		POD::query( "update {$database['prefix']}Attachments set parent=$parent where blogid=".getBlogId()." and parent=$temporary");
	} else {
		// Prepared statement — name comes from content-parsed filenames (SQL Injection 대응)
		$blogid = getBlogId();
		foreach( $attaches as $att )
		{
			$stmt = POD::prepare("UPDATE {$database['prefix']}Attachments SET parent=? WHERE blogid=? AND parent=? AND name=?");
			if ($stmt) {
				POD::bindAndExecute($stmt, 'iiis', (int)$parent, (int)$blogid, (int)$temporary, (string)$att);
				$stmt->close();
			}
		}
	}
}

/**
 * @security raw-sql-escape
 * $newfile['label']은 DB에서 조회된 데이터로, SELECT 쿼리에서 POD::escapeString()으로 이스케이프됨.
 */
function api_update_attaches_with_replace($entryId)
{
	global $database;

	/* [보안] 새 글 임시 첨부는 API 로 로그인한 사용자의 것만(parent = -(userid+1)) — api_update_attaches() 와 같은 기준 */
	$temporary = getTemporaryAttachmentParent(getUserId());
	$newFiles = ($temporary === false) ? null : POD::queryAll("SELECT name, label FROM {$database['prefix']}Attachments WHERE blogid=".getBlogId()." AND parent=$temporary");
	if( $newFiles ) {
		foreach($newFiles as $newfile) {
			$newfile['label'] = POD::escapeString(UTF8::lessenAsEncoding($newfile['label'], 64));
			$oldFile = POD::queryCell("SELECT name FROM {$database['prefix']}Attachments WHERE blogid=".getBlogId()." AND parent=$entryId AND label='{$newfile['label']}'");
		
			if (!is_null($oldFile)) {
				deleteAttachment(getBlogId(), $entryId, $oldFile);
			}
		}
	}	
	api_update_attaches($entryId);
}

/*--------- API main ---------------*/
function api_BlogAPI()
{
	global $blogApiFunctions;
	// [SECURITY] post_max_size 기반 상한을 적용하여 대용량 페이로드로 인한 메모리 고갈을 1차 방어하나,
	// 근본적으로는 스트리밍 XML 파서 도입이 필요함 (장기 과제).
	// $GLOBALS['HTTP_RAW_POST_DATA'] 는 PHP 7.0에서 제거됨 → file_get_contents('php://input') 로 대체.
	$rawSize = trim(ini_get('post_max_size'));
	$maxSize = (int)$rawSize;
	if ($maxSize > 0) {
		$unit = strtolower(substr($rawSize, -1));
		if ($unit === 'g') $maxSize *= 1073741824;
		elseif ($unit === 'm') $maxSize *= 1048576;
		elseif ($unit === 'k') $maxSize *= 1024;
	} else {
		$maxSize = 8 * 1024 * 1024;
	}
	if (isset($_SERVER['CONTENT_LENGTH']) && (int)$_SERVER['CONTENT_LENGTH'] > $maxSize) {
		XMLRPC::sendFault(1, 'Request body too large');
		exit;
	}
	$xml = file_get_contents('php://input', false, null, 0, $maxSize);
	if (empty($xml)) {
		XMLRPC::sendFault(1, 'Invalid Method Call');
		exit;
	}

	$blogApiFunctions = array(
		"blogger.getUsersBlogs",
		"blogger.newPost",
		"blogger.editPost",
		"blogger.getTemplate",
		"blogger.getRecentPosts",
		"blogger.deletePost", 
		"blogger.getPost", 
//		"blogger.getTemplate", // doesn't supported
//		"blogger.setTemplate", // doesn't supported
		"metaWeblog.newPost",
		"metaWeblog.getPost",
		"metaWeblog.getCategories",
		"metaWeblog.getRecentPosts",
		"metaWeblog.editPost",
		"metaWeblog.newMediaObject",
		"mt.getPostCategories",
		"mt.setPostCategories",
		"mt.getCategoryList",
//		"mt.supportedTextFilters", //지원안함 // 텍스트 처리하는 플러그인 리스트
		"mt.supportedMethods", // XMLRPC 함수 리스트
		"mt.publishPost", // rebuild post
//		"mt.getTrackbackPings", // 인증을 안하므로 무시 // 받은 트랙백 리스트
		"mt.getRecentPostTitles" // getRecentPosts와 거의 동일, 트래픽 프랜들리 버전
		 );

	$xmlrpc = new XMLRPC;

	foreach( $blogApiFunctions as $func )
	{
		$callback = str_replace( ".", "_", $func );
		$xmlrpc->registerMethod( $func, $callback );
	}

	$otherMethods = fireEvent( 'RegisterRpcMethod', array() );
	foreach( $otherMethods as $func => $callback )
	{
		$xmlrpc->registerMethod( $func, $callback );
	}

	$xmlrpc->receive( $xml );
}

/*--------- Blogger API functions -----------*/

function blogger_getUsersBlogs()
{
	global $blog, $hostURL, $blogURL;

	$params = func_get_args();
	$result = api_login( $params[1], $params[2] );
	if( $result )
	{
		return $result;
	}

	$blogs = array( 
		array( 
				"url" => $hostURL . $blogURL,
				"blogid" => (string)getBlogId(),
				"blogName" => $blog['title'],
		) 
	);
	return $blogs;
}

function blogger_newPost()
{
	$params = func_get_args();
	$post = new Post();
	$post->content = $params[4];
	$post->contentformatter = getDefaultFormatter();
	$post->contenteditor = getDefaultEditor();
	$post->title = htmlspecialchars(api_get_title($params[4]));

	if( $params[5] )
	{
		$post->visibility = "public";
	}
	else
	{
		$post->visibility = "private";
	}

	$result = api_login( $params[2], $params[3] );
	if( $result )
	{
		return $result;
	}

	if( !$post->add($params[2]) )
	{
		$post->close();
		return new XMLRPCFault( 1, "Posting error" );
	}
	RSS::refresh();

	$id = "{$post->id}";
	fireEvent('AddPostByBlogAPI', $id, $post);
	$post->close();
	return $id;
}

function blogger_editPost()
{
	$params = func_get_args();

	$result = api_login( $params[2], $params[3] );
	if( $result )
	{
		return $result;
	}

	$result = api_check_entry_permission( $params[1] );	// [보안] 본인 글 또는 편집자만
	if( $result )
	{
		return $result;
	}

	$post = new Post();
	$post->title = htmlspecialchars(api_get_title( $params[4] ));
	$post->id = intval($params[1]);
	$post->content = $params[4];
	$post->contentformatter = getDefaultFormatter();
	$post->contenteditor = getDefaultEditor();

	if( $params[5] )
	{
		$post->visibility = "public";
	}
	else
	{
		$post->visibility = "private";
	}

	$ret = $post->update();
	fireEvent('UpdatePostByBlogAPI', $post->id, $post);
	$post->close();

	RSS::refresh();
	if($ret!=false) Setting::setBlogSettingGlobal('LatestEditedEntry',$post->id);
	return $ret ? true : false;
}

function blogger_deletePost()
{
	$params = func_get_args();
	$result = api_login( $params[2], $params[3] );
	if( $result )
	{
		return $result;
	}

	$result = api_check_entry_permission( $params[1] );	// [보안] 본인 글 또는 편집자만
	if( $result )
	{
		return $result;
	}

	$post = new Post();
	$id = intval( $params[1] );
	$ret = $post->open( $id );
	$ret = $post->remove();
	deleteAttachments(getBlogId(), $id );
	fireEvent('DeletePostByBlogAPI', $id, $post);

	return $ret ? true : false;

}

function blogger_getRecentPosts()
{
	$params = func_get_args();
	$result = api_login( $params[2], $params[3] );
	if( $result )
	{
		return $result;
	}

	$post = new Post();
	$post->open();
	$out = array();

	for($i=0; $i<$params[4]; $i++ )
	{
		array_push( $out, api_get_post( $post, "bl" ) );
		if( !$post->shift() )
		{
			break;
		}
	}
	$post->close();
	return $out;

}

function blogger_getPost()
{
	$params = func_get_args();
	$result = api_login( $params[2], $params[3] );
	if( $result )
	{
		return $result;
	}

	$post = new Post();
	$post->open( intval( $params[1] ) );

	$ret = api_get_post( $post );
	$post->close();

	return $ret;

}

function blogger_getTemplate()
{
	$params = func_get_args();
	$result = api_login( $params[2], $params[3] );
	if( $result )
	{
		return $result;
	}

	$file = ( $params[4] == "main" ? "template.main.tpl" : "template.archindex.tpl" );
	$template = "";
	if( file_exists( $file ) )
	{
		$fd = fopen( $file, "r" );
		while( !feof($fd) )
		{
			$template .= fgets( $fd, 4096 );
		}
		fclose( $fd );
	}
	return htmlspecialchars($template);
}

/*--------- MetaWebLog API functions -----------*/

function metaWeblog_getCategories()
{
	global $hostURL, $blogURL;
	$params = func_get_args();
	$result = api_login( $params[1], $params[2] );
	if( $result )
	{
		return $result;
	}

	$category = new Category();
	$category->open(false);


	$cat = array();
	while($category->id)
	{
		array_push( $cat, array( 
			'htmlUrl' => "$hostURL$blogURL/category/" . $category->label,
			//'rssUrl' => "",
			'categoryName' => $category->label,
			'description' => $category->label,
			'title' => $category->label,
			'categoryid' => $category->id,
			'isPrimary' => true 
			) );
			
		if( !$category->shift() )
		{
			break;
		}
	}

	$category->close();
	return $cat;

}


function mt_getCategoryList()
{
	$params = func_get_args();
	$result = api_login( $params[1], $params[2] );
	if( $result )
	{
		return $result;
	}

	$category = new Category();
	$category->open(false);

	$cat = array();
	while(1)
	{
		array_push( $cat, array( 
			'categoryName' => $category->label,
			'categoryId' => $category->id,
			'isPrimary' => true ) );
			
		if( !$category->shift() )
		{
			break;
		}
	}

	$category->close();
	return $cat;

}


function metaWeblog_getRecentPosts()
{
	$params = func_get_args();
	$result = api_login( $params[1], $params[2] );
	if( $result )
	{
		return $result;
	}

	$post = new Post();
	$post->open();
	$out = array();

	for($i=0; $post->_count > 0 && $i<$params[3]; $i++ )
	{
		array_push( $out, api_get_post( $post, "mt" ) );
		if( !$post->shift() )
		{
			break;
		}
	}

	$post->close();
	return $out;
}

function metaWeblog_getPost()
{
	$params = func_get_args();
	$result = api_login( $params[1], $params[2] );
	if( $result )
	{
		return $result;
	}

	$post = new Post();
	$post->open( intval( $params[0] ) );

	$ret = api_get_post( $post, "mt" );
	$post->close();

	return $ret;
}

function metaWeblog_newPost()
{
	$params = func_get_args();
	$result = api_login( $params[1], $params[2] );
	if( $result )
	{
		return $result;
	}

	$post = api_make_post( $params[3], $params[4] );
	
	if ($post === false) {
		return new XMLRPCFault( 1, "Textcube posting error" );
	}
	if( !$post->add($params[1]) )
	{
		$error = $post->error;
		$post->close();
		return new XMLRPCFault( 1, "Textcube invalid field: $error" );
	}

	api_update_attaches($post->id );
	fireEvent('AddPostByBlogAPI', $post->id, $post);
	RSS::refresh();


	$id = "{$post->id}";
	$post->close();
	if($id) Setting::setBlogSettingGlobal('LatestEditedEntry',$id);
	return $id;
}
 
function mt_setPostCategories()
{
	$params = func_get_args();

	$result = api_login( $params[1], $params[2] );
	if( $result )
	{
		return $result;
	}

	$result = api_check_entry_permission( $params[0] );	// [보안] 본인 글 또는 편집자만
	if( $result )
	{
		return $result;
	}

	$post = new Post();
	if( !$post->open( intval( $params[0] ) ) )	// [보안] intval: Post::open 은 숫자가 아닌 값을 SQL 조건으로 그대로 사용
	{
		return new XMLRPCFault( 1, "Posting error" );
	}

	$category = null;
	if (is_null($params[3])) $params[3] = array();
	foreach( $params[3] as $cat )
	{
		if(array_key_exists('isPrimary', $cat) && $cat['isPrimary'] )
		{
			$category = $cat['categoryId'];
		} else {
			if (is_null($category))
				$category = $cat['categoryId'];
		}
	}
	if( !is_null($category) )
	{
		$post->category = intval($category);
		$post->update();
	}
	$post->close();
	return true;
}

function mt_getPostCategories()
{
	$params = func_get_args();
	$result = api_login( $params[1], $params[2] );
	if( $result )
	{
		return $result;
	}

	$post = new Post();
	$post->open( intval( $params[0] ) );

	$cat = array( array( "categoryId" => $post->category, 
			"categoryName" => api_getCategoryNameById( $post->category ),
			"isPrimary" => true) );
	$post->close();


	return $cat;
}

function metaWeblog_editPost()
{
	$params = func_get_args();
	$result = api_login( $params[1], $params[2] );
	if( $result )
	{
		return $result;
	}

	$result = api_check_entry_permission( $params[0] );	// [보안] 본인 글 또는 편집자만
	if( $result )
	{
		return $result;
	}

	// [보안] intval: Post::open 은 숫자가 아닌 값을 SQL 조건으로 그대로 사용.
	//        실패(false) 확인을 속성 대입보다 먼저 수행(원본은 false 에 속성 대입)
	$post = api_make_post( $params[3], $params[4], intval( $params[0] ) );
	if( !$post )
	{
		return new XMLRPCFault( 1, "Textcube editing error" );
	}
	$post->created = null;

	$ret = $post->update();

	// 기존 글의 파일들 지우기 (잘 찾아서)
	// 새로 업로드 된 파일들 옮기기
	api_update_attaches_with_replace( $post->id );
	fireEvent('UpdatePostByBlogAPI', $id, $post);
	
	RSS::refresh();

	$post->close();
	if($ret!=false) Setting::setBlogSettingGlobal('LatestEditedEntry',$post->id);
	return $ret ? true : false;
}

function metaWeblog_newMediaObject()
{
	$ctx = Model_Context::getInstance();
	$params = func_get_args();
	$result = api_login( $params[1], $params[2] );
	if( $result )
	{
		return $result;
	}

	/* Textcube-NG: bits 는 XML-RPC base64 를 푼 문자열 — count() 는 PHP 7.2 부터 경고와 함께 1, PHP 8.0 부터 TypeError → strlen() */
	$file = array( 
		'name' => $params[3]['name'],
		'content' => $params[3]['bits'],
		'size' => strlen($params[3]['bits']) 
		);
		
	/* [보안] 원본은 새 글 임시 첨부를 parent=0(블로그 공용)에 넣었다 → API 로 로그인한 사용자의 임시 첨부(parent = -(userid+1)) */
	$temporary = getTemporaryAttachmentParent(getUserId());
	if( $temporary === false )
	{
		return new XMLRPCFault( 1, "Can't create file" );
	}
	$attachment = api_addAttachment( getBlogId(), $temporary, $file );
	if( !$attachment )
	{
		return new XMLRPCFault( 1, "Can't create file" );
	}
	
	/* Textcube-NG: 원본은 이 함수에 없는 $blogid 를 써서(PHP 8 Warning) '…/attach//이름' 이 됐다 → getBlogId()(api_fix_content() 와 함께) */
	$attachurl = array ( 'url' => $ctx->getProperty('uri.host').$ctx->getProperty('service.path')."/attach/".getBlogId()."/".$attachment['name']);
	return $attachurl;
}

function mt_supportedMethods()
{
	global $blogApiFunctions;
	return $blogApiFunctions;
}

function mt_publishPost() /* postid, username, password */
{
	// Only check whether the post exists or not
	$params = func_get_args();
	$result = api_login( $params[1], $params[2] );
	if( $result )
	{
		return $result;
	}

	$post = new Post();
	if (! $post->open( intval( $params[0] ) ) ) {
		return false;
	}

	return true;
}

function mt_getRecentPostTitles() /* blogid, username, password, count */
{
	$params = func_get_args();
	$result = api_login( $params[1], $params[2] );
	if( $result )
	{
		return $result;
	}

	$post = new Post();
	$post->open();
	$out = array();

	for($i=0; $post->_count > 0 && $i<$params[3]; $i++ )
	{
		array_push( $out, api_get_post( $post, "mt" ) );
		if( !$post->shift() )
		{
			break;
		}
	}

	$post->close();
	return $out;
}
?>
