<?php

requireComponent( "Needlworks.Mail.Pop3" );
requireModel( "common.setting" );

class Moblog
{
	function __construct( $options )
	{
		global $pop3logs;
		if( !isset($debugLogs) ) {
			$pop3logs = array();
		}
		foreach( $options as $k => $v ) {
			$this->$k = $v;
		}

		$this->recentCount = 100;
		$visibilities = array( "private", "protected", "public", "syndicated" );
		$this->visibility = $visibilities[$this->visibility];
		// 빈 문자열 항목 제거 — preg_split('', '') 은 [''] 을 반환하며 strstr($from, '') 가 PHP 8 에서 ValueError 를 일으킴
		$this->allow = array_values(array_filter(preg_split("@[,\s]+@", $this->allow), 'strlen'));

		// 프로토콜에 따라 POP3 또는 IMAP 클라이언트 생성 — 외부 인터페이스 동일
		if (!empty($this->protocol) && $this->protocol === 'imap') {
			$this->pop3 = new Imap();
			$folder = !empty($this->imapfolder) ? $this->imapfolder : 'INBOX';
			$this->pop3->setFolder($folder);
		} else {
			$this->pop3 = new Pop3();
		}
		$this->pop3->setLogger( array(&$this,'log') );
		$this->pop3->setStatCallback( array(&$this,'statCallback') );
		$this->pop3->setUidFilter( array(&$this,'checkUid') );
		$this->pop3->setSizeFilter( array(&$this,'checkSize') );
		$this->pop3->setRetrCallback( array(&$this,'retrieveCallback') );

		// Textcube-NG: 확인 기록(uid)은 블로그별 cache/moblog/{blogid}.uidl.txt. 없으면 기존 공용 cache/pop3uidl.txt 내용으로 한 번 채운다
		// (비워 두면 서버에 남은 메일이 모두 다시 게시된다). 채운 내용은 다음 saveUidl() 때 블로그별 파일에 저장된다(v3.204)
		$this->uidl_file = Moblog::dataFile(".uidl", true);
		$legacy_uidl_file = ROOT.DS."cache".DS."pop3uidl.txt";

		if( file_exists( $this->uidl_file ) ) {
			$this->stored_uidl = file_get_contents( $this->uidl_file );
		} else if( file_exists( $legacy_uidl_file ) ) {
			$this->stored_uidl = file_get_contents( $legacy_uidl_file );
		} else {
			$this->stored_uidl = '';
		}

		global $service;
		$fallback_charsets = array( 'ko' => 'euc-kr' );
		if( isset( $fallback_charsets[$service['language']] ) ) {
			$this->pop3->setFallbackCharset( $fallback_charsets[$service['language']] );
		}
	}

	static function decorate_log($s)
	{
		global $blogURL;
		$s = htmlspecialchars($s, ENT_QUOTES | ENT_SUBSTITUTE);
		$s = preg_replace( "/\(SLOGAN:([^)]+)\)/", "<a href=\"$blogURL/entry/\\1\" target=\"_blank\">click</a>", $s );
		return $s;
	}

	// Textcube-NG: 로그·확인 기록을 서비스 공용 파일 하나에 써서 다른 블로그 관리자가 메일 계정·발신자·제목·상대 서버 응답을 읽을 수 있었고
	// 확인 기록(uid)이 블로그 사이에 섞였다. cache/moblog/{blogid}.txt·{blogid}.uidl.txt 로 나눈다(v3.204)
	static function dataFile($suffix, $create = false)
	{
		$dir = ROOT.DS."cache".DS."moblog";
		if( $create ) {
			if( !is_dir($dir) ) @mkdir($dir);
			// Textcube-NG: 이미 있던 폴더(예전 0755)도 0700 으로 맞춘다. 원본 흐름은 새로 만들 때만 chmod 했다(r3-5)
			// PHP port(Textcube-NG): r3-10 R-308 실제 경로(realpath)가 cache 폴더 아래일 때만 권한을 바꾼다 — cache/moblog 가 심볼릭 링크 등으로 cache 밖을 가리키면
			// chmod 하지 않음. realpath 가 실패하면 건너뛰고, Windows 는 구분자(\ → /)·대소문자를 맞춰 비교한다
			if( is_dir($dir) ) {
				$realDir = @realpath($dir);
				$realCache = @realpath(ROOT.DS."cache");
				if( $realDir !== false && $realCache !== false ) {
					$realDir = rtrim(str_replace('\\', '/', $realDir), '/');
					$realCache = rtrim(str_replace('\\', '/', $realCache), '/');
					if( DIRECTORY_SEPARATOR === '\\' ) {
						$realDir = strtolower($realDir);
						$realCache = strtolower($realCache);
					}
					if( $realDir !== $realCache && strpos($realDir.'/', $realCache.'/') === 0 && (@fileperms($dir) & 0777) !== 0700 ) @chmod($dir, 0700);	// 웹 서버 사용자만(백업 폴더 관례)
				}
			}
		}
		return $dir.DS.(int)getBlogId().$suffix.".txt";
	}

	function log($msg)
	{
		// Textcube-NG: 블로그별 로그 파일(v3.204)
		$f = fopen( Moblog::dataFile("", true), "a" );
		fwrite( $f, date('Y-m-d H:i:s')." $msg\r\n" );
		fclose($f);
		if( $msg[0] == '*' ) {
			global $pop3logs;
			array_push( $pop3logs, Moblog::decorate_log(substr($msg,2)) );
		}
	}

	function saveUidl()
	{
		$f = fopen( $this->uidl_file, "w" );
		fwrite( $f, $this->stored_uidl );
		fclose($f);
		return true;
	}

	// Textcube-NG: 메일 서버 주소·포트는 블로그 관리자가 정하는 값이라 서버 안쪽의 임의 포트(DB·캐시 등)로 연결해 POP3/IMAP 명령을 보내고
	// 상대 응답(배너·오류)을 로그로 볼 수 있었다. 포트는 POP3·IMAP 표준(110/995/143/993)만, 호스트는 이름·IPv4 표기 문자만(v3.204)
	static function isAllowedServer($host, $port)
	{
		return is_string($host) && preg_match('/^[A-Za-z0-9.-]{1,253}\z/', $host)
			&& (string)(int)$port === (string)$port && in_array((int)$port, array(110, 995, 143, 993), true);
	}

	function check()
	{
		$sslLabels = array('none' => '', 'ssl' => '(SSL/TLS)', 'starttls' => '(STARTTLS)');	// Textcube-NG: PHP 5.5 에는 ?? 가 없어 isset 으로(r4-4)
		$sslLabel = isset($sslLabels[$this->ssl]) ? $sslLabels[$this->ssl] : '';
		// Textcube-NG: 허용하지 않는 서버는 연결하지 않는다. 로그 값은 허용 문자 밖을 ? 로 바꿔 로그 줄 위조(CR/LF)를 막는다(v3.204)
		if( !Moblog::isAllowedServer( $this->host, $this->port ) ) {
			$this->log( "* "._t("접속 실패")." : ".preg_replace('/[^A-Za-z0-9.:-]/', '?', $this->host.":".$this->port).$sslLabel );
			return false;
		}
		if( !$this->pop3->connect( $this->host, $this->port, $this->ssl ) ) {
			$err = $this->pop3->getLastError();
			$this->log( "* "._t("접속 실패")." : ".$this->host.":".$this->port.$sslLabel.($err ? " [$err]" : "") );
			return false;
		}
		$this->log( "* "._t("접속 성공")." : ".$this->host.":".$this->port.$sslLabel );
		if( !$this->pop3->authorize( $this->username, $this->password ) ) {
			$this->log( "* "._t("인증 실패") );
			return false;
		}
		$this->log( "* "._t("인증 성공") );

		$this->pop3->run();

		if( !$this->pop3->quit() ) {
			return false;
		}
		return true;
	}

	function appendUid( $uid )
	{
		$today = date( 'Y-m-d H:i:s' );
		$this->stored_uidl .= "[$uid] $today\r\n";
		$this->saveUidl();
	}

	function checkUid( $uid, $number )
	{
		$ret = !!strstr( $this->stored_uidl, "[$uid]" );
		if( $ret ) {
			$this->log( "Msg $number: "._t("이미 확인한 메일")." : $uid" );
		}
		return $ret;
	}

	function checkSize( $size, $number, $total )
	{
		if( $number < $total - $this->recentCount ) {
			return true;
		}
		$ret = $size < $this->minsize;
		if( $ret ) {
			$this->log( "Msg $number: "._t("메일크기가 작음")." : $size" );
		}
		return $ret;
	}

	function isAllowed( & $mail )
	{
		$mail['unverified'] = false;
		// 실제 발신 주소($mail['from'])만 허용 목록과 대조 — 표시 이름($mail['sender'])은 발신자가 임의로 설정 가능하므로 검사 제외
		/* [보안] 원본은 부분 문자열 비교(strstr)라 허용 항목을 포함한 다른 주소도 통과했다 → 정확히 일치할 때만 허용(matchAllow) */
		if( isset( $mail['from'] ) && !empty($this->allow) ) {
			foreach( $this->allow as $a ) {
				if( Moblog::matchAllow( $mail['from'], $a ) ) return true;
			}
		}
		if( $this->allowonly ) {
			$this->logMail( $mail, "SKIP, Not in ALLOW list" );
			return false;
		}

		/* [보안] X-MMS-*·Return-Path 헤더는 발신자가 임의로 넣을 수 있어 발신자 확인 근거가 되지 못한다 →
		   허용 목록으로 확인되지 않은 메일로 표시하고 비공개 별도 글로만 저장(retrieveCallback) */
		if( !empty($mail['mms']) ) {
			$mail['unverified'] = true;
			return true;
		}
		if( isset($mail['return_path']) && strstr( $mail['return_path'], 'mms' ) ) {
			/* KTF: ktfmms, SKT: vmms */
			$mail['unverified'] = true;
			return true;
		}
		return false;
	}

	/* 허용 목록 항목과 발신 주소 비교(대소문자 무시, 정확히 일치):
	   '@' 로 시작하는 항목은 도메인, '@' 가 들어간 항목은 주소 전체, 그 외(전화번호 등)는 주소의 '@' 앞부분 */
	static function matchAllow( $from, $allow )
	{
		$from = strtolower( trim( (string)$from ) );
		$allow = strtolower( trim( (string)$allow ) );
		if( $from === '' || $allow === '' ) return false;
		$at = strrpos( $from, '@' );
		if( $allow[0] == '@' ) {
			return $at !== false && substr( $from, $at ) === $allow;
		}
		if( strpos( $allow, '@' ) !== false ) {
			return $from === $allow;
		}
		return ( $at === false ? $from : substr( $from, 0, $at ) ) === $allow;
	}

	/* 첨부 내용이 허용 형식 이미지이면 그 형식의 확장자('.jpg' 등), 아니면 false */
	static function imageExtension( $data )
	{
		if( !is_string($data) || $data === '' ) return false;
		$info = @getimagesizefromstring( $data );
		if( $info === false ) return false;
		$types = array( IMAGETYPE_GIF => '.gif', IMAGETYPE_JPEG => '.jpg', IMAGETYPE_PNG => '.png',
			IMAGETYPE_BMP => '.bmp', IMAGETYPE_WEBP => '.webp' );
		return isset( $types[$info[2]] ) ? $types[$info[2]] : false;
	}

	function _getDecoratedContent( & $mail, $docid ) {
		if( !empty($mail['text']) && strstr($mail['text'],'magicn.com') ) {
			$mail['text'] = preg_replace( '@.*<BODY>(.*?)<style.*@smi', '$1', $mail['text']);
			$mail['text'] = preg_replace( '@<IMG[^>]*?cid:[^>]*?>@smi', '', $mail['text']);
			$mail['text'] = preg_replace( '@<BR>\s*<BR>\s*<BR>@smi', '<BR>', $mail['text']);
		}
		$text = "<h3 class=\"moblog-title\" id=\"$docid\">$docid</h3>\r\n";
		//$text .= empty($mail['subject']) ? '' : ("<p>".$mail['subject']."</p>\r\n");
		if( !empty($mail['text']) ) {
			$content = $mail['text'];
			/* [보안] 메일 본문은 외부(발신자) 입력이다. 원본은 본문을 그대로 글에 넣어 스크립트가 공개 글에 저장될 수 있었다 →
			   text/plain 은 HTML 인코딩 후 줄바꿈 변환, 그 외(HTML)는 허용 목록 sanitizer(sanitizeHTML)로 정리 */
			if($mail['content_type'] == 'text/plain') {
				$content = htmlspecialchars($content, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
				$newLineFrom = array("￦n","\\n");
				$newLineTo = "<br />\r\n";
				$content = str_replace($newLineFrom, $newLineTo, $content); 
			} else {
				if (!function_exists('sanitizeHTML'))
					requireLibrary('function/html');
				$content = sanitizeHTML($content);
			}
			$text .= "<div class=\"moblog-body\">".$content."</div>\r\n";
		}
		return $text;
	}

	function statCallback( $total, $totalsize )
	{
		$this->log( "* ".sprintf( _t("총 %d개의 메시지"),$total) );
		$lastStat = Setting::getBlogSettingGlobal( 'MmsPop3stat', '' );
		$stat = "$total $totalsize";
		if( $stat == $lastStat ) {
			$this->log( "* "._t("새로운 메시지가 없습니다") );
			return false;
		}
		setBlogSetting( 'MmsPop3stat', $stat );
		return true;
	}

	function logMail( $mail, $result )
	{
		$date = isset( $mail['date_string'] ) ? "{$mail['date_string']} {$mail['time_string']} " : '';
		$from = isset( $mail['from'] ) ? "({$mail['sender']} <{$mail['from']}>) " : '';
		$this->log( "* "._t("메일").": {$mail['subject']} {$date} {$from}[{$result}]" );
	}

	/**
	 * Extract tags from {tag1,tag2,tag3,...} format string in the mms message.
	 * $mail will be changed to be removed tags.
	 */
	function extractTags( & $mail )
	{
		$p = '/{([^}:;]*)}/';
		$tags = array();
		if( preg_match( $p, $mail['text'], $m ) ) {
			$tags = explode( ',', $m[1] );
			$mail['text'] = preg_replace( $p, '', $mail['text'] );
		}
		return $tags;
	}

	function retrieveCallback( $lines, $uid )
	{
		$this->appendUid( $uid );
		$mail = $this->pop3->parse( $lines );

		if( isset( $mail['date_string'] ) ) {
			$slogan = $mail['date_string'];
			$docid = $mail['time_string'];
		} else {
			$slogan = date( "Y-m-d" );
			$docid = date( "H:i:s" );
		}
		if( in_array( $mail['subject'], array( '제목없음' ) ) ) {
			$mail['subject'] = '';
		}
		if( !$this->isAllowed($mail) ) {
			return false;
		}
		if( false && empty($mail['attachments']) ) {
			$this->logMail( $mail, "SKIP" );
			return false;
		}
		requireComponent( "Textcube.Data.Post" );

		$post = new Post();
		$moblog_begin = "\n<div class=\"moblog-entry\">";
		$moblog_end = "\n</div>\n";

		/* [보안] 허용 목록으로 확인되지 않은 메일(MMS 헤더만으로 통과)은 원본처럼 공개 글로 게시·같은 날짜 글에 추가하지 않고
		   별도 슬로건의 비공개 글로 저장 — 관리자가 확인 후 공개. 슬로건을 나눠 이후 확인된 메일이 이 글에 합쳐져 공개되지 않게 한다 */
		$visibility = $this->visibility;
		if( !empty($mail['unverified']) ) {
			$slogan .= '-' . str_replace( ':', '', $docid );
			$visibility = 'private';
			$this->log( "* ". _t("허용 목록에 없는 발신자 — 비공개 글로 저장합니다.") );
		}

		if( empty($mail['unverified']) && $post->open( "slogan = '$slogan'" ) ) {
			$post->loadTags();
			$this->log( "* ". _t("기존 글을 엽니다.")." (SLOGAN:$slogan)" );
			if( empty($post->tags) ) {
				$post->tags = array();
			}
			$tags = $this->extractTags( $mail ); /* mail content will be changed */
			$post->tags = array_merge( $post->tags, $tags );
			$post->content .= $moblog_begin.$this->_getDecoratedContent( $mail, $docid );
			$post->modified = $mail['date'];
			$post->visibility = $this->visibility;
		} else {
			$this->log( "* ". _t("새 글을 작성합니다.")." (SLOGAN:$slogan)" );
			if( isset( $mail['date_year'] ) ) {
				$post->title = str_replace( array('%Y','%M','%D'), 
					array( $mail['date_year'], $mail['date_month'], $mail['date_day'] ), $this->subject );
			} else {
				$post->title = str_replace( array('%Y','%M','%D'), 
					array( date("Y"), date("m"), date("d") ), $this->subject );
			}
			$post->userid = $this->userid;
			$post->category = $this->category;
			$post->tags = $this->extractTags( $mail ); /* Go with csv string, Tag class supports both string and array */
			$post->content = $moblog_begin.$this->_getDecoratedContent( $mail, $docid );
			$post->contentformatter = getDefaultFormatter();
			$post->contenteditor = getDefaultEditor();
			$post->created = time();
			$post->acceptcomment = true;
			$post->accepttrackback = true;
			$post->visibility = $visibility;
			$post->published = time();
			$post->modified = $mail['date'];
			$post->slogan = $slogan;
			if( !$post->add() ) {
				$this->logMail( $mail, "ERROR" );
				$this->log( _t("실패: 글을 추가하지 못하였습니다")." : " . $post->error );
				return false;
			} else {
				CacheControl::flushCategory($post->category);
			}
		}
		/* 슬로건을 지워야만 문제가 발생하지 않습니다. */
		//unset($post->slogan);

		if( isset( $mail['attachments'] ) && count($mail['attachments']) ) {
			requireModel( "blog.api" );
			$post->content .= "<div class=\"moblog-attachments\">\n";
			foreach( $mail['attachments'] as $mail_att ) {
				$mail_att['filename'] = isset( $mail_att['filename'] ) ? (string)$mail_att['filename'] : '';
				$this->log( "* ". _t("첨부")." : {$mail_att['filename']}" );
				/* [보안] 첨부 파일명·내용은 발신자 입력 — 원본은 MIME 이름의 확장자(.phtml·.html·.svg 등)를 그대로 attach/ 에 저장했다 →
				   내용이 허용 형식 이미지인 것만 받고, 이미지 확장자가 아니면 확인된 형식의 확장자를 붙인다 */
				$imageExt = Moblog::imageExtension( isset( $mail_att['decoded_content'] ) ? $mail_att['decoded_content'] : '' );
				if( $imageExt === false ) {
					$this->log( "* ". _t("첨부 제외(허용된 이미지 형식 아님)")." : {$mail_att['filename']}" );
					continue;
				}
				if( !in_array( Path::getExtension( $mail_att['filename'] ), array( '.gif', '.jpg', '.jpeg', '.png', '.bmp', '.webp' ), true ) ) {
					$mail_att['filename'] .= $imageExt;
				}
				$att = api_addAttachment( getBlogId(), $post->id, 
							array( 
									'name' => $mail_att['filename'], 
									'content' => $mail_att['decoded_content'], 
									'size' => $mail_att['length']
							) 
					);
				if( !$att ) {
					$this->logMail( $mail, "ERROR" );
					$this->log( _t("실패: 첨부파일을 추가하지 못하였습니다")." : " . $post->error );
					return false;
				}
				$alt = htmlentities($mail_att['filename'],ENT_QUOTES,'utf-8');
				$content ='[##_1C|$FILENAME|width="$WIDTH" height="$HEIGHT" alt="'.$alt.'"|_##]';
				$content = str_replace( '$FILENAME', $att['name'], $content );
				$content = str_replace( '$WIDTH', $att['width'], $content );
				$content = str_replace( '$HEIGHT', $att['height'], $content );
				$post->content .= $content;
			}
			$post->content .= "\n</div>";
		}
		$post->content .= $moblog_end;

		if( !$post->update() ) {
			$this->logMail( $mail, "ERROR" );
			$this->log( _t("실패: 첨부파일을 본문에 연결하지 못하였습니다").". : " . $post->error );
			return false;
		}
		$this->logMail( $mail, "OK" );
		return true;
	}
}

function moblog_check()
{
	if( isset($_GET['check']) && $_GET['check'] == 1 ) {
		echo "<html><body style=\"font-size:0.9em\">";
		/* [보안] 로그(메일함 계정·서버·발신자·제목)는 관리자만 열람 — 원본은 권한 검사 없이 누구에게나 출력(로그삭제와 같은 검사) */
		if( !Acl::check('group.administrators') ) {
			echo "<p style=\"color:red;\">" . _t('권한이 없습니다.') . "</p>";
			echo "</body></html>";
			exit;
		}
		echo "<style>.emplog{color:red}.oklog{color:blue}</style>";
		echo "<ul>";
		// Textcube-NG: 블로그별 로그 파일. 읽기는 폴더를 만들지 않는다(v3.204)
		$logFile = Moblog::dataFile("");
		$logContent = file_exists($logFile) ? file_get_contents($logFile) : '';
		echo join( "",
			array_map(
				function($li) {
					return preg_match('/^\S+\s+\S+\s+\*/', $li) ?
						(preg_match('/\[OK\]/', $li) ? "<li class=\"oklog\">$li</li>" : "<li class=\"emplog\">$li</li>")
						: "<li>$li</li>";
				},
				explode( "\n", Moblog::decorate_log($logContent) )
			)
		);
		echo "</ul>";
		echo "</body></html>";
		exit;
	}

	if( isset($_GET['clearlog']) && $_GET['clearlog'] == 1 ) {
		echo "<html><body style=\"font-size:0.9em\">";
		// Textcube-NG: r3-27 R-423 — 로그 삭제는 관리자 GET 한 번으로 되던 것을 POST + CSRF 토큰(getCSRFToken·__isCSRFTokenValid, preprocessor 가 POST csrfToken 을 보관)으로 바꾼다
		if( Acl::check('group.administrators') && !(isset($_SERVER['REQUEST_METHOD']) && strtoupper((string) $_SERVER['REQUEST_METHOD']) === 'POST' && __isCSRFTokenValid()) ) {
			echo "<p style=\"color:red;\">" . _t('요청이 올바르지 않습니다.') . "</p>";
		} else if( Acl::check('group.administrators') ) {
			// Textcube-NG: 블로그별 로그 파일. 비우기는 폴더를 만들지 않고 파일이 있을 때만 비운다(v3.204)
			$logFile = Moblog::dataFile("");
			if( file_exists($logFile) ) file_put_contents($logFile, '');
			echo "<p style=\"color:green;\">&#10003; " . _t('로그가 삭제되었습니다.') . "</p>";
		} else {
			echo "<p style=\"color:red;\">" . _t('권한이 없습니다.') . "</p>";
		}
		echo "</body></html>";
		exit;
	}

	header( "Content-type: text/html; charset:utf-8" );
	echo '<html>';
	echo '<head><meta http-equiv="content-type" content="text/html; charset=utf-8" />';
	echo '<body style="font-size:0.9em"><ul><li>';
	$moblog = new Moblog(
		array(
			'username'   => Setting::getBlogSettingGlobal( 'MmsPop3Username', '' ),
			'password'   => Setting::getBlogSettingGlobal( 'MmsPop3Password', '' ),
			'host'       => Setting::getBlogSettingGlobal( 'MmsPop3Host', 'localhost' ),
			'port'       => Setting::getBlogSettingGlobal( 'MmsPop3Port', 110 ),
			'ssl'        => Setting::getBlogSettingGlobal( 'MmsPop3Ssl', 'none' ),
			'protocol'   => Setting::getBlogSettingGlobal( 'MmsPop3Protocol', 'pop3' ),
			'imapfolder' => Setting::getBlogSettingGlobal( 'MmsPop3ImapFolder', 'INBOX' ),
			'userid'     => Setting::getBlogSettingGlobal( 'MmsPop3Fallbackuserid', 1 ),
			'minsize'    => Setting::getBlogSettingGlobal( 'MmsPop3MinSize', 0 )*1024,
			'visibility' => Setting::getBlogSettingGlobal( 'MmsPop3Visibility', '2' ),
			'category'   => Setting::getBlogSettingGlobal( 'MmsPop3Category', 0 ),
			'allowonly'  => Setting::getBlogSettingGlobal( 'MmsPop3AllowOnly', '0' ),
			'allow'      => Setting::getBlogSettingGlobal( 'MmsPop3Allow', '' ),
			'subject'    => Setting::getBlogSettingGlobal( 'MmsPop3Subject', '%Y-%M-%D' ) )
	);
	$moblog->log( "--BEGIN--" );
	$moblog->check();
	$moblog->log( "-- END --" );
	if( Acl::check( 'group.administrators' ) ) {
		global $pop3logs;
		print join("</li>\n<li>",$pop3logs);
	}
	echo "</li>\n</ul></body></html>";
	return true;
}

function moblog_logrotate()
{
	requireLibrary( "function/logrotate" );
	// Textcube-NG: 블로그별 파일(cache/moblog/*.txt). 기존 공용 파일은 옮기거나 지우지 않고 더 쓰지 않는다(v3.204)
	$files = glob( ROOT.DS."cache".DS."moblog".DS."*.txt" );
	if( is_array($files) ) {
		foreach( $files as $file ) cutlog( $file, 1024*1024 );
	}
}

function moblog_manage()
{
	global $blogURL;
	if( Acl::check('group.administrators') && $_SERVER['REQUEST_METHOD'] == 'POST' ) {
		Setting::setBlogSettingGlobal( 'MmsPop3Email', isset($_POST['pop3email']) ? $_POST['pop3email'] : '' );
		Setting::setBlogSettingGlobal( 'MmsPop3Host', isset($_POST['pop3host']) ? $_POST['pop3host'] : '' );
		$pop3portVal = (int)(isset($_POST['pop3port']) ? $_POST['pop3port'] : 110);
		// Textcube-NG: 포트는 POP3·IMAP 표준(110/995/143/993)만 저장한다. 그 밖의 값은 프로토콜·SSL 에 맞는 기본값(v3.204)
		if (!in_array($pop3portVal, array(110, 995, 143, 993), true)) {
			$isImap = (isset($_POST['pop3protocol']) && $_POST['pop3protocol'] === 'imap');
			$isSsl = (isset($_POST['pop3ssl']) && $_POST['pop3ssl'] === 'ssl');
			$pop3portVal = $isImap ? ($isSsl ? 993 : 143) : ($isSsl ? 995 : 110);
		}
		Setting::setBlogSettingGlobal( 'MmsPop3Port', $pop3portVal );
		$sslVal = in_array(isset($_POST['pop3ssl']) ? $_POST['pop3ssl'] : '', ['ssl', 'starttls'], true) ? $_POST['pop3ssl'] : 'none';
		Setting::setBlogSettingGlobal( 'MmsPop3Ssl', $sslVal );
		Setting::setBlogSettingGlobal( 'MmsPop3Username', isset($_POST['pop3username']) ? $_POST['pop3username'] : '' );
		Setting::setBlogSettingGlobal( 'MmsPop3Password', isset($_POST['pop3password']) ? $_POST['pop3password'] : '' );
		$visibilityVal = (int)(isset($_POST['pop3visibility']) ? $_POST['pop3visibility'] : 2);
		if (!in_array($visibilityVal, [0, 1, 2, 3], true)) $visibilityVal = 2;
		Setting::setBlogSettingGlobal( 'MmsPop3Visibility', $visibilityVal );
		Setting::setBlogSettingGlobal( 'MmsPop3Category', max(0, (int)(isset($_POST['pop3category']) ? $_POST['pop3category'] : 0)) );
		Setting::setBlogSettingGlobal( 'MmsPop3Fallbackuserid', getUserId() );
		Setting::setBlogSettingGlobal( 'MmsPop3MinSize', 0 );
		Setting::setBlogSettingGlobal( 'MmsPop3AllowOnly', !empty($_POST['pop3allowonly'])?1:0 );
		Setting::setBlogSettingGlobal( 'MmsPop3Allow', substr(trim(isset($_POST['pop3allow']) ? $_POST['pop3allow'] : ''), 0, 512) );
		Setting::setBlogSettingGlobal( 'MmsPop3Subject', isset($_POST['pop3subject']) ? $_POST['pop3subject'] : '' );
		$protocolVal = (isset($_POST['pop3protocol']) && $_POST['pop3protocol'] === 'imap') ? 'imap' : 'pop3';
		Setting::setBlogSettingGlobal( 'MmsPop3Protocol', $protocolVal );
		$folderVal = substr(trim(isset($_POST['pop3imapfolder']) ? $_POST['pop3imapfolder'] : 'INBOX'), 0, 64);
		Setting::setBlogSettingGlobal( 'MmsPop3ImapFolder', $folderVal ?: 'INBOX' );
	}
	$pop3email = Setting::getBlogSettingGlobal( 'MmsPop3Email', '' );
	$pop3host = Setting::getBlogSettingGlobal( 'MmsPop3Host', 'localhost' );
	$pop3port = Setting::getBlogSettingGlobal( 'MmsPop3Port', 110 );
	$pop3ssl = Setting::getBlogSettingGlobal( 'MmsPop3Ssl', 'none' );
	if (!in_array($pop3ssl, ['ssl', 'starttls'], true)) $pop3ssl = 'none';
	$pop3username = Setting::getBlogSettingGlobal( 'MmsPop3Username', '' );
	$pop3password = Setting::getBlogSettingGlobal( 'MmsPop3Password', '' );
	$pop3minsize = Setting::getBlogSettingGlobal( 'MmsPop3MinSize', 0 );
	$pop3category = Setting::getBlogSettingGlobal( 'MmsPop3Category', 0 );
	$pop3fallheadercharset = Setting::getBlogSettingGlobal( 'MmsPop3Fallbackcharset', 'euc-kr' );
	$pop3visibility = Setting::getBlogSettingGlobal( 'MmsPop3Visibility', '2' );
	$pop3mmsallowonly = Setting::getBlogSettingGlobal( 'MmsPop3AllowOnly', '0' );
	$pop3mmsallow = Setting::getBlogSettingGlobal( 'MmsPop3Allow', '' );
	$pop3subject = Setting::getBlogSettingGlobal( 'MmsPop3Subject', '%Y-%M-%D' );
	$pop3protocol = Setting::getBlogSettingGlobal( 'MmsPop3Protocol', 'pop3' );
	$pop3imapfolder = Setting::getBlogSettingGlobal( 'MmsPop3ImapFolder', 'INBOX' );
?>
						<hr class="hidden" />
						
						<div id="part-setting-editor" class="part">
							<h2 class="caption"><span class="main-text"><?php echo _t('MMS 메시지 확인용 메일 환경 설정');?></span></h2>
<?php if( !Acl::check( "group.administrators" ) ): ?>
								<div id="editor-section" class="section">
										<dl id="formatter-line" class="line">
											<dt><span class="label"><?php echo _t('MMS 수신 이메일');?></span></dt>
											<dd>
											<?php if( empty($pop3email) ): ?>
												<?php echo _t('비공개') ?>
											<?php else: ?>
												<?php echo $pop3email;?>
											<?php endif ?>
											</dd>
											<dd>
											<?php if( empty($pop3email) ): ?>
												<?php echo _t('이동전화에서 보내는 이메일을 수신할 주소가 공개되지 않았습니다'); ?>
											<?php else: ?>
												<?php echo _t('이동전화를 이용하여 위 메일로 이메일을 보내면 블로그에 게시됩니다'); ?>
											<?php endif ?>
											</dd>
										</dl>
								</div>
<?php else: ?>
							<script type="text/javascript">
							// 프로토콜 라디오에 따라 IMAP 폴더 입력 행 표시/숨김
							function updateProtocolUI(proto) {
								var line = document.getElementById('imap-folder-line');
								if (line) line.style.display = (proto === 'imap') ? '' : 'none';
							}
							// 미리 설정된 서버 선택 시 호스트·포트·암호화·프로토콜을 한 번에 적용
							// 값 형식: "host:port:sslmode:protocol"  sslmode = none | ssl | starttls
							function changehost(packedhost)
							{
								var h = packedhost.split( ':' );
								document.forms['editor-form']['pop3host'].value = h[0];
								document.forms['editor-form']['pop3port'].value = h[1];
								var sslMode = h[2] || 'none';
								var sslRadios = document.querySelectorAll('input[name="pop3ssl"]');
								for( var i = 0; i < sslRadios.length; i++ ) {
									sslRadios[i].checked = (sslRadios[i].value === sslMode);
								}
								if( h[3] ) {
									var protos = document.querySelectorAll('input[name="pop3protocol"]');
									for( var i = 0; i < protos.length; i++ ) {
										protos[i].checked = (protos[i].value === h[3]);
									}
									updateProtocolUI(h[3]);
								}
							}
							// 값 형식: "표시이름:host:port:sslmode:protocol"  sslmode = none | ssl | starttls
							function renderhosts()
							{
								var hosts = "<?php echo _t('PREDEFINED POP3 HOSTS') ?>";
								if( hosts == 'PREDEFINED POP3 HOSTS' ) {
									hosts = "gmail POP3:pop.gmail.com:995:ssl:pop3"
										+ "/gmail IMAP:imap.gmail.com:993:ssl:imap"
										+ "/hanmail POP3:pop.hanmail.net:995:ssl:pop3"
										+ "/hanmail IMAP:imap.daum.net:993:ssl:imap"
										+ "/naver POP3:pop.naver.com:995:ssl:pop3"
										+ "/naver IMAP:imap.naver.com:993:ssl:imap"
										+ "/nate POP3:mail.nate.com:110:none:pop3";
								}
								hosts = "Localhost POP3:localhost:110:none:pop3/" + hosts;
								hosts = hosts.split('/');
								for( var i=0; i<hosts.length; i++ ) {
									var h = hosts[i];
									var n = h.split(':')[0];
									var v = h.substr(n.length+1);
									document.write( "<option value=\""+v+"\">"+n+"</option>" );
								}
							}
							</script>
							<form id="editor-form" class="data-inbox" method="post" action="<?php echo $blogURL;?>/owner/plugin/adminMenu?name=CL_Moblog/moblog_manage">
								<div id="editor-section" class="section">
									<fieldset class="container">
										<legend><?php echo _t('MMS 환경을 설정합니다');?></legend>
										
										<dl id="formatter-line" class="line">
											<dt><span class="label"><?php echo _t('MMS용 이메일');?></span></dt>
											<dd>
												<input type="text" style="width:14em" class="input-text" name="pop3email" value="<?php echo htmlspecialchars($pop3email, ENT_QUOTES, 'UTF-8');?>" />
												<?php echo _t('(필진에 공개 됩니다. 사진을 찍어 이메일로 보내면 포스팅이 됩니다)'); ?>
											</dd>
										</dl>
										<dl id="editor-line" class="line">
											<dt><span class="label"><?php echo _t('프로토콜');?></span></dt>
											<dd>
												<input type="radio" id="proto_pop3" name="pop3protocol" value="pop3"
													<?php echo ($pop3protocol !== 'imap') ? 'checked="checked"' : ''; ?>
													onclick="updateProtocolUI('pop3')" />
												<label for="proto_pop3">POP3</label>
												&nbsp;&nbsp;
												<input type="radio" id="proto_imap" name="pop3protocol" value="imap"
													<?php echo ($pop3protocol === 'imap') ? 'checked="checked"' : ''; ?>
													onclick="updateProtocolUI('imap')" />
												<label for="proto_imap">IMAP</label>
											</dd>
										</dl>
										<dl id="imap-folder-line" class="line" style="<?php echo ($pop3protocol === 'imap') ? '' : 'display:none'; ?>">
											<dt><span class="label"><?php echo _t('IMAP 폴더');?></span></dt>
											<dd>
												<input type="text" style="width:14em" class="input-text" name="pop3imapfolder"
													value="<?php echo htmlspecialchars($pop3imapfolder, ENT_QUOTES, 'UTF-8');?>" />
												<span style="color:#888;"><?php echo _t('(기본값: INBOX)');?></span>
											</dd>
										</dl>
										<dl id="formatter-line" class="line">
											<dt><span class="label"><?php echo _t('메일 서버');?></span></dt>
											<dd>
												<input type="text" style="width:14em" class="input-text" name="pop3host" value="<?php echo htmlspecialchars($pop3host, ENT_QUOTES, 'UTF-8');?>" />
												<select onchange="changehost(this.value)">
												<option value=""><?php echo _t('선택하세요') ?></option>
												<script type="text/javascript">renderhosts()</script>
												</select>
											</dd>
										</dl>
										<dl id="editor-line" class="line">
											<dt><span class="label"><?php echo _t('암호화');?></span></dt>
											<dd>
												<input type="radio" name="pop3ssl" id="ssl_none" value="none"
												<?php echo ($pop3ssl === 'none')     ? 'checked="checked"' : ''; ?> />
												<label for="ssl_none"><?php echo _t('없음');?></label>
												&nbsp;&nbsp;
												<input type="radio" name="pop3ssl" id="ssl_ssl" value="ssl"
												<?php echo ($pop3ssl === 'ssl')      ? 'checked="checked"' : ''; ?> />
												<label for="ssl_ssl">SSL/TLS</label>
												&nbsp;&nbsp;
												<input type="radio" name="pop3ssl" id="ssl_starttls" value="starttls"
												<?php echo ($pop3ssl === 'starttls') ? 'checked="checked"' : ''; ?> />
												<label for="ssl_starttls">STARTTLS</label>
											</dd>
										</dl>
										<dl id="editor-line" class="line">
											<dt><span class="label"><?php echo _t('서버 포트');?></span></dt>
											<dd>
												<input type="text" style="width:14em" class="input-text" name="pop3port" value="<?php echo htmlspecialchars((string)$pop3port, ENT_QUOTES, 'UTF-8');?>" />
												<span style="color:#888;"><?php echo _t('(POP3: 110 없음/STARTTLS, 995 SSL · IMAP: 143 없음/STARTTLS, 993 SSL)');?></span>
											</dd>
										</dl>
										<dl id="editor-line" class="line">
											<dt><span class="label"><?php echo _t('사용자 아이디');?></span></dt>
											<dd>
												<input type="text" style="width:14em" class="input-text" name="pop3username" value="<?php echo htmlspecialchars($pop3username, ENT_QUOTES, 'UTF-8');?>" />
											</dd>
										</dl>
										<dl id="editor-line" class="line">
											<dt><span class="label"><?php echo _t('비밀번호');?></span></dt>
											<dd>
												<input type="password" style="width:14em" class="input-text" name="pop3password" value="<?php echo htmlspecialchars($pop3password, ENT_QUOTES, 'UTF-8');?>" />
											</dd>
										</dl>
									<div class="button-box">
										<input type="submit" class="save-button input-button wide-button" value="<?php echo _t('저장하기');?>"  />
									</div>
								</div>
							<h2 class="caption"><span class="main-text"><?php echo _t('글 쓰기 환경 설정');?></span></h2>
								<div id="editor-section" class="section">
										<dl id="editor-line" class="line">
											<dt><span class="label"><?php echo _t('제목');?></span></dt>
											<dd>
												<input type="text" style="width:24em" class="input-text" id="pop3subject" name="pop3subject" value="<?php echo htmlspecialchars($pop3subject, ENT_QUOTES, 'UTF-8');?>" />
												(<?php echo _t('%Y:년, %M:월, %D:일');?>) <input type="button" value="<?php echo _t("초기화");?>" onclick="document.getElementById('pop3subject').value='%Y-%M-%D';return false;" />
											</dd>
										</dl>
										<dl id="editor-line" class="line">
											<dt><span class="label"><?php echo _t('공개여부');?></span></dt>
											<dd>
													<span id="status-private" class="status-private"><input type="radio" id="visibility_private" class="radio" name="pop3visibility" value="0"<?php echo ($pop3visibility == 0 ? ' checked="checked"' : '');?> /><label for="visibility_private"><?php echo _t('비공개');?></label></span>
													<span id="status-protected" class="status-protected"><input type="radio" id="visibility_protected" class="radio" name="pop3visibility" value="1"<?php echo ($pop3visibility == 1 ? ' checked="checked"' : '');?> /><label for="visibility_protected"><?php echo _t('보호');?></label></span>
													<span id="status-public" class="status-public"><input type="radio" id="visibility_public" class="radio" name="pop3visibility" value="2"<?php echo ($pop3visibility == 2 ? ' checked="checked"' : '');?> /><label for="visibility_public"><?php echo _t('공개');?></label></span>
													<span id="status-syndicated" class="status-syndicated"><input type="radio" id="visibility_syndicated" class="radio" name="pop3visibility" value="3"<?php echo ($pop3visibility == 3 ? ' checked="checked"' : '');?> /><label for="visibility_syndicated"><?php echo _t('발행');?></label></span>
											</dd>
										</dl>
										<dl id="editor-line" class="line">
											<dt><span class="label"><?php echo _t('분류');?></span></dt>
											<dd>
												<select id="category" name="pop3category">
													<optgroup class="category" label="<?php echo _t('분류');?>">
			<?php foreach (getCategories(getBlogId()) as $category): ?>
			<?php 	if ($category['id'] != 0): ?>
														<option value="<?php echo $category['id'];?>" <?php echo $category['id'] == $pop3category ? 'selected':''?> >
														<?php echo ($category['visibility'] > 1 ? '' : _t('(비공개)')).htmlspecialchars($category['name'], ENT_QUOTES | ENT_SUBSTITUTE);?></option>
			<?php	endif ?>
			<?php 	foreach (getCategoryDescendantRows(getBlogId(), $category['id']) as $child): ?>
			<?php 		if ($category['id'] != 0): ?>
														<option value="<?php echo $child['id'];?>" <?php echo $child['id'] == $pop3category ? 'selected':''?> >&nbsp;― <?php echo str_repeat('&nbsp;― ', $child['depth'] - 2).($child['hiddenByAncestor'] ? _t('(비공개)') : '').htmlspecialchars($child['name'], ENT_QUOTES | ENT_SUBSTITUTE);?></option>
			<?php 		endif ?>
			<?php 	endforeach ?>
			<?php endforeach ?>
													</optgroup>
												</select>
											</dd>
										</dl>
									<div class="button-box">
										<input type="submit" class="save-button input-button wide-button" value="<?php echo _t('저장하기');?>"  />
									</div>
								</div>
							<h2 class="caption"><span class="main-text"><?php echo _t('메일 필터링 설정');?></span></h2>
								<div id="editor-section" class="section">
										<dl id="formatter-line" class="line">
											<dt><span class="label"><?php echo _t('허용 목록');?></span></dt>
											<dd>
												<input type="radio" id="pop3allowonly" name="pop3allowonly" value="1" <?php echo $pop3mmsallowonly ? 'checked="checked"':'' ?> />
												<label for="pop3allowonly"><?php echo _t('아래 목록의 송신자에게서 온 메일만 MMS로 처리합니다 (목록에 없는 송신자의 메일은 무시됩니다)') ?></label>
											</dd>
											<dd>
												<input type="radio" id="pop3allowlist" name="pop3allowonly" value="0" <?php echo $pop3mmsallowonly ? '':'checked="checked"' ?> />
												<label for="pop3allowlist"><?php echo _t('아래 목록의 송신자에게서 온 메일을 MMS로 처리합니다 (목록에 없는 송신자의 MMS 메일은 비공개 글로 저장되며, 확인 후 공개할 수 있습니다)'); ?></label>
											</dd>
											<dd>
												<input type="text" maxlength="512" name="pop3allow" value="<?php echo htmlspecialchars($pop3mmsallow, ENT_QUOTES, 'UTF-8');?>" style="width:90%" />
											</dd>
											<dd>
												<?php echo _t('전화번호 또는 이메일 주소를 쉼표(,)나 공백으로 구분하여 입력합니다. 정확히 일치해야 하며, 전화번호는 메일 주소의 @ 앞부분, @도메인은 도메인 전체와 비교합니다. 예: 01012345678, user@example.com, @example.com') ?>
											</dd>
										</dl>
									<div class="button-box">
										<input type="submit" class="save-button input-button wide-button" value="<?php echo _t('저장하기');?>"  />
									</div>
								</div>
							</form>
							<h2 class="caption"><span class="main-text"><?php echo _t('MMS 메시지 테스트');?></span></h2>
								<div id="editor-section" class="section">
									<dl id="formatter-line" class="line">
										<dt><span class="label"><?php echo _t('명령');?></span></dt>
										<dd>
											<input type="button" class="save-button input-button wide-button" value="<?php echo _t('로그보기');?>"
												onclick="document.getElementById('pop3_debug').src='<?php echo $blogURL?>/plugin/moblog/check?check=1&amp;rnd='+((new Date()).getTime())" />
											<input type="button" class="save-button input-button wide-button" value="<?php echo _t('시험하기');?>"
												onclick="document.getElementById('pop3_debug').src='<?php echo $blogURL?>/plugin/moblog/check?rnd='+((new Date()).getTime())" />
											<input type="button" class="input-button wide-button" value="<?php echo _t('로그삭제');?>"
												style="background:#c0392b;color:#fff;border-color:#922b21;"
												<?php /* Textcube-NG: 아래 onclick 의 확인창을 페이지 위 안내 줄의 [확인]/[취소]로 바꿈(r3-17, R-396) */ ?>
												onclick="tcConfirm('<?php echo htmlspecialchars(_t('로그를 삭제하시겠습니까?'), ENT_QUOTES, 'UTF-8');?>', function () {document.getElementById('moblog_clearlog_form').submit();});" />
										</dd>
									</dl>
								</div>
								<?php /* Textcube-NG: r3-27 R-423 — 로그삭제 버튼은 GET 주소 대신 이 숨은 POST 폼(CSRF 토큰 포함)을 iframe 으로 보낸다 */ ?>
								<form id="moblog_clearlog_form" method="post" action="<?php echo $blogURL?>/plugin/moblog/check?clearlog=1" target="pop3_debug" style="display:none"><input type="hidden" name="csrfToken" value="<?php echo htmlspecialchars(getCSRFToken(), ENT_QUOTES, 'UTF-8');?>" /></form>
								<?php /* Textcube-NG: r3-27 보충 — 위 로그삭제 폼(target="pop3_debug")이 이 iframe 을 찾도록 name 을 더함(target 은 id 가 아니라 name 으로 찾음) */ ?>
								<iframe src="about:blank" class="debug_message" id="pop3_debug" name="pop3_debug" style="width:100%; height:400px">
								</iframe>
<?php endif ?>
						</div>
<?php
}
?>
