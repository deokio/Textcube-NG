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

// Define basic signatures.
define('TEXTCUBE_NAME', 'Textcube');
define('TEXTCUBE_VERSION_ID', '1.10.10');
define('TEXTCUBE_REVISION', 'root-main-branch1.10-r43');
define('TEXTCUBE_CODENAME', 'Tempo primo');
define('TEXTCUBE_VERSION', TEXTCUBE_VERSION_ID.' : '.TEXTCUBE_CODENAME);
// PHP port(Textcube-NG): NG 점검(checkup) 항목의 개정 번호. 점검 항목을 추가·변경하면 올린다(cache/CHECKUP 2행, Checkup 클래스). 점검 때 캐시를 비워야 하는 변경이 있을 때도 올린다.
// 개정 이력: 2 = PostgreSQL 호환 함수 확인·재생성 추가(기존 설치에서 점검을 한 번 다시 요청함).
// 개정 이력: 3 = 글 내용 캐시 키 라벨 정정(library/view/view.php, v3.181) — 점검 성공 시 clearCache() 로 옛 페이지 캐시를 비우기 위해 점검을 한 번 다시 요청함.
// 개정 이력: 4 = PostgreSQL 호환 함수 점검식에 from_unixtime(bigint)·(numeric) 오버로드 확인 추가(interface/blog/checkup.php, v3.183) — 기존 설치에서 점검을 한 번 다시 요청해 호환 SQL 을 다시 실행(오버로드 생성)함.
// 개정 이력: 5 = 댓글 알림 대기열 고유 인덱스를 (blogid, commentid)로 바꾸는 점검 추가(interface/blog/checkup.php·resources/setup/initialize.*.sql, v3.185) — 기존 설치에서 점검을 한 번 다시 요청해 인덱스를 바꿈.
// 개정 이력: 6 = 세션 테이블 address 색인 추가·.htaccess 에 plugins/ 아래 PHP 직접 요청 차단 규칙을 넣고 옛 스킨 index.xml 차단 규칙 줄을 바꾸는 점검 추가(interface/blog/checkup.php·resources/setup/initialize.*.sql, r3-6) — 기존 설치에서 점검을 한 번 다시 요청해 색인·규칙을 넣음.
define('TEXTCUBE_NG_CHECKUP_REVISION', 6);
// PHP port(Textcube-NG): NG 판 번호. TEXTCUBE_VERSION·TEXTCUBE_VERSION_ID 는 점검(cache/CHECKUP)·플러그인 호환성 판정·쿠키 이름에 쓰이므로 바꾸지 않고 따로 둔다.
// 관리 화면 footer·정보 화면·checkup·피드 generator·블로그 화면 머리 주석에 덧붙여 출력한다. VERSION 파일을 읽지 않고 트리별로 고정한다(파일이 오염되면 출력도 오염되므로).
define('TEXTCUBE_NG_VERSION', '1.10.10+php85.r3');
define('TEXTCUBE_COPYRIGHT', 'Copyright &copy; 2004-2016. Needlworks / Tatter Network Foundation. All rights reserved. Licensed under the GPL.');
define('TEXTCUBE_HOMEPAGE', 'http://www.textcube.org/');
define('TEXTCUBE_RESOURCE_URL', 'http://resources.textcube.org/1.10.9');
// Textcube-NG: 관리 화면 알림판의 공지 피드는 아래 $service['noticeFeedURL'] 을 쓴다. 이 상수는 외부 플러그인 호환을 위해 남긴다(v3.220)
define('TEXTCUBE_NOTICE_URL','http://feeds.feedburner.com/textcube/');
define('CRLF', "\r\n");
define('TAB', "	");
define('INT_MAX',2147483647);
// PHP port(Textcube-NG): 정적 자산 갱신 — jQuery 1.11.2→3.7.1, jQuery UI 1.11.2→1.13.3, Lodash 2.4.1→4.18.1
// (공개 CVE 대응, SECURITY.md 참조). 이전 판 파일은 resources/script 에 남겨 둠.
define('JQUERY_VERSION','3.7.1.min');
define('JQUERY_UI_VERSION','1.13.3.min');
define('JQUERY_BPOPUP_VERSION','0.10.0.min');
define('LODASH_VERSION','4.18.1.min');

if( strstr( PHP_OS, "WIN") !== false ) {
	define('DS', "\\");
} else {
	define('DS', "/");
}
define( "XPATH_LIBRARY_ROOT", ROOT . "/library/contrib/phpxpath/" );
define( "OPENID_PASSWORD", "-OPENID-" );

// Define global variable.
global $database, $service, $blog, $memcache;

$database['server'] = 'localhost';
$database['database'] = '';
$database['username'] = '';
$database['password'] = '';
$database['prefix'] = '';
// DB SSL/TLS (MySQL·MariaDB·PostgreSQL·Cubrid). ssl 를 true 로 바꾸면 SSL 연결, false(기본)면 기존 연결 방식(PostgreSQL 은 libpq 기본값).
// PostgreSQL: ssl_verify=true 면 sslmode=verify-full(서버 이름까지 검증), false 면 sslmode=require. 클라이언트 키 파일 권한은 0600.
// Cubrid: 암호화만 지원(브로커 SSL=ON 필요). ssl_verify=true 면 연결하지 않으며 ssl_ca·ssl_cert·ssl_key 는 쓰지 않음.
$database['ssl'] = false;                            // SSL/TLS 사용 (true 로 활성화)
$database['ssl_ca'] = '';                            // CA 인증서 경로 (서버 SSL). 예: /etc/mysql/ssl/ca-cert.pem
$database['ssl_cert'] = '';                          // 클라이언트 인증서 (상호 TLS 시). 예: /etc/mysql/ssl/client-cert.pem
$database['ssl_key'] = '';                           // 클라이언트 키 (상호 TLS 시). 예: /etc/mysql/ssl/client-key.pem
$database['ssl_verify'] = false;                     // 서버 인증서·서버 이름 검증 (false=검증 안 함, 자체서명 CA 시 / true 이고 ssl_ca 가 비면 시스템 CA)
// Textcube-NG: DB 드라이버 — Cubrid·MySQL·MySQLi·PostgreSQL·SQLite3 공통. 'native'(기본) 또는 'pdo', 비우면 native, 그 밖의 값이면 연결하지 않고 error_log 를 남김.
// native 는 cubrid·mysqli·pgsql·sqlite3 확장, pdo 는 PDO_CUBRID·PDO_MYSQL·PDO_PGSQL·PDO_SQLITE 를 씀(v3.218)
$database['driver'] = '';
$service['timeout'] = 900;
$service['type'] = 'single';
$service['domain'] = '';
$service['path'] = '';
$service['language'] = 'ko';
$service['timezone'] = 'Asia/Seoul';
$service['encoding'] = 'UTF-8';
$service['umask'] = 0;
$service['skin'] = 'periwinkle';
if(defined('__TEXTCUBE_NO_FANCY_URL__')) $service['fancyURL'] = 1;
else $service['fancyURL'] = 2;
$service['useEncodedURL'] = false;
$service['debugmode'] = false;
$service['reader'] = true;
$service['flashclipboardpoter'] = false;
$service['allowBlogVisibilitySetting'] = true;
$service['disableEolinSuggestion'] = true;
$service['interface'] = 'detail';	// 'simple' or 'detail'. Default is 'detail'
$service['pagecache'] = true;
$service['codecache'] = false;
$service['skincache'] = true;
$service['externalresources'] = false;
$service['favicon_daily_traffic'] = 10;
$service['flashuploader'] = false;
$service['debug_session_dump'] = false;
$service['debug_rewrite_module'] = false;
$service['useNumericURLonRSS'] = false;
// Textcube-NG: 블로그 스킨 출력에 넣는 주소. true 면 서비스 주소(도메인 포함, $serviceURL), false 면 도메인 없는 서비스 경로($service['path'])를 쓴다(/control/server, v3.206)
//   useServiceURLonSkin: 스킨 상대 경로(./), useServiceURLonAttach: 블로그 로고(attach), useServiceURLonResources: 로고가 없을 때의 빈 이미지(resources)
$service['useServiceURLonSkin'] = true;
$service['useServiceURLonAttach'] = true;
$service['useServiceURLonResources'] = true;
$service['forceinstall'] = false;
$service['useSSL'] = false;
// Textcube-NG: HSTS(Strict-Transport-Security). true 면 https 요청(신뢰 프록시 규칙)에만 헤더를 보낸다. 기본 false(r3-20)
//   hstsMaxAge: 초(기본 31536000 = 1년, 0 이면 브라우저가 기억한 HSTS 를 지움), hstsIncludeSubDomains: 하위 도메인 포함,
//   hstsPreload: preload 표시(브라우저 목록 등록은 max-age 31536000 이상·includeSubDomains 필요, 한 번 등록하면 되돌리기 어려움)
$service['hsts'] = false;
$service['hstsMaxAge'] = 31536000;
$service['hstsIncludeSubDomains'] = false;
$service['hstsPreload'] = false;
$service['cookie_prefix'] = '';
// 신뢰 프록시(IP 또는 CIDR 배열). 이 주소에서 온 요청에 한해 X-Forwarded-For / Client-IP 헤더로 방문자 IP 를 결정한다.
// 설치(setup.php) 시 config.php 에 127.0.0.1 과 설치 당시 서버 주소(SERVER_ADDR)가 기본값으로 기록된다.
// 아래 빈 배열은 config.php 에 이 항목이 없을 때(이전 설치본 등)의 기본값이며, 헤더를 무시하고 접속 주소를 사용한다.
// 별도 프록시·로드밸런서 뒤라면 config.php 에 추가: 예) $service['trustedProxies'] = array('127.0.0.1', '10.0.0.0/8');
$service['trustedProxies'] = array();
// 계정 비밀번호 저장 방식. 기본 'md5' 는 원본과 같은 md5 저장(기존 DB·플러그인·외부 도구와 호환).
// 'multi'   = 새로 설정·변경하는 비밀번호만 password_hash() 로 저장(기존 md5 값은 유지, 두 형식 공존).
// 'migrate' = 'multi' + 기존 md5 값을 로그인 성공 시 password_hash() 로 변환.
// 로그인은 방식과 무관하게 두 형식을 모두 확인한다. 'md5' 로 되돌리면 password_hash 값은 로그인 시 md5 로 다시 저장된다.
// 'multi'·'migrate' 로 바꾸면 로그인·관리 화면에 점검 안내가 나온다(Checkup). MySQL 은 점검(블로그 주소/checkup)이 Users.password 열을
// varchar(255) 로 넓힌다(열이 짧으면 md5 로 저장). password_hash 값으로 저장된 계정은 원본 Textcube 등 md5 로 비교하는 코드에서 로그인할 수 없다.
// 예: $service['passwordHash'] = 'multi';
$service['passwordHash'] = 'md5';
// 인증 토큰(초대 메일 링크·관리 화면 '임시 암호')의 유효 시간(초). 기본 604800(7일), 0 이면 만료 없음.
// 토큰은 유효 시간과 관계없이 로그인에 한 번 쓰면 지워진다(1회용). 예: $service['authTokenLifetime'] = 86400;
$service['authTokenLifetime'] = 604800;
// OIDC·소셜 로그인의 서버 요청(curl)에 환경 변수 프록시(https_proxy 등)를 쓸지. 기본 false = 프록시 없이 확인한 IP 로 직접 연결.
// true(불리언)일 때만 켜짐. 켜도 주소 검사(https·443·이 서버가 이름을 공인 IPv4 로 해석)는 그대로이고, 프록시를 거치면 IP 고정은 프록시가 맡는다.
// curl 이 없는 대체 경로는 프록시를 쓰지 않는다. PHP-FPM 은 풀 설정에 env[https_proxy] 가 있어야 한다. 예: $service['useLoginProxy'] = true;
$service['useLoginProxy'] = false;
// Textcube-NG: 로그인 시도 제한(v3.202). loginFailureLimit: 같은 계정의 로그인이 이 횟수만큼 실패하면 마지막 실패에서 15분 동안
// 로그인을 받지 않는다. 숫자가 아니거나 1보다 작으면 5. 예: $service['loginFailureLimit'] = 10;
$service['loginFailureLimit'] = 5;
// passwordConfirmFailureLimit: 데이터 전체 삭제·비밀번호 변경에서 현재 비밀번호가 이 횟수만큼 틀리면(세션당, 재인증 실패와 합산) 로그아웃한다.
// 숫자가 아니거나 1보다 작으면 5. 예: $service['passwordConfirmFailureLimit'] = 3;
$service['passwordConfirmFailureLimit'] = 5;
// Textcube-NG: 로그인 잠금 방식(r3-14). loginLockScope: 'account'(기본)는 위 계정별 잠금만 한다. 'ip' 는 계정별 잠금과 함께 접속 IP 별로도
// 실패를 세어, 한 IP 의 실패가 loginIpFailureLimit 에 이르면 마지막 실패에서 15분 동안 그 IP 의 모든 로그인을 받지 않는다(IPv6 는 주소 하나
// 단위). 기록은 ServiceSettings 에 남고, 기한이 지난 기록은 누군가 로그인에 성공할 때와 새 IP 의 실패를 기록하기 전에 지운다. 접속 주소가
// IP 형식이 아니면 IP 잠금을 하지 않는다. 프록시·로드밸런서 뒤라면 trustedProxies 를 먼저 설정한다(설정하지 않으면 모든 방문자가 프록시 IP
// 하나로 세어진다). 'ip' 가 아닌 값은 'account'.
// loginLockShowRemaining: 'ip' 에서 잠긴 IP 의 로그인 화면에 남은 시간(분)을 보인다. true(불리언)일 때만 켜짐.
// loginIpFailureLimit: 'ip' 에서 한 IP 의 허용 실패 횟수(기본 5, 숫자가 아니거나 1보다 작으면 5).
// loginIpRecordLimit: 'ip' 에서 남기는 IP 실패 기록의 최대 개수(기본 500, 1~10000 의 정수가 아니면 500). 기한 지난 기록을 지우고도 가득
// 차 있으면 새 IP 의 실패는 기록하지 않고 계정별 잠금만 적용한다(이미 기록이 있는 IP 는 계속 센다). 동시 요청이 많으면 잠시 넘을 수 있다.
// 예: $service['loginLockScope'] = 'ip'; $service['loginIpFailureLimit'] = 10; $service['loginLockShowRemaining'] = true;
$service['loginLockScope'] = 'account';
$service['loginLockShowRemaining'] = false;
$service['loginIpFailureLimit'] = 5;
$service['loginIpRecordLimit'] = 500;
// Textcube-NG: Host 헤더 검사(v3.205). 요청 Host 헤더는 문법(RFC 3986 reg-name·IPv6 리터럴, 포트 1~65535)이 맞지 않으면 400 으로 거절한다.
// hostAliases: domain 밖에 이 서비스로 받을 Host 이름. 'host:port' 로 적으면 그 포트에서만 받는다. 등록된 Host 이고 포트가 $service['port'] 와
// 같은 요청(또는 'host:port' 로 등록한 요청)만 페이지 캐시를 쓴다. 예: $service['hostAliases'] = array('www.example.com', '127.0.0.1:8080');
$service['hostAliases'] = array();
// hostMismatchBehavior: single·path 형식에서 Host 가 domain·hostAliases 와 다를 때. '' = 지금처럼 처리, 'redirect' = GET·HEAD 를 domain 주소로 302
// (그 밖의 요청은 지금처럼 처리), '404' = 찾을 수 없음. 예: $service['hostMismatchBehavior'] = 'redirect';
$service['hostMismatchBehavior'] = '';
// useConfigPort: true 이고 $service['port'] 를 적었으면 요청 포트로 덮어쓰지 않고 그 포트로 주소를 만든다. 예: $service['useConfigPort'] = true;
$service['useConfigPort'] = false;
// Textcube-NG: 공지 피드·도움말 주소(v3.220). noticeFeedURL: 관리 화면 알림판 '공지사항' 칸이 읽을 RSS 피드의 전체 주소. {lang} 은 블로그 언어(없으면 ko)로 바뀐다.
// 비어 있으면 원격 요청을 하지 않고 '공지 피드 주소가 설정되지 않았습니다.' 를 보인다. 가져오기는 리더와 같이 http·https 의 80/443 포트와
// 공인 IPv4 주소만 허용하고(getRemoteFeed) <rss> 문서와 Atom <feed> 문서를 읽는다(r3-5). 주소를 바꾸면 저장된 공지 목록 대신 새 주소에서 다시 가져온다.
// 예: $service['noticeFeedURL'] = 'https://example.com/{lang}/feed.xml';
$service['noticeFeedURL'] = 'https://github.com/deokio/Textcube-NG/releases.atom';	// Textcube-NG: 기본값은 Textcube-NG 릴리즈 Atom 피드다. 끄려면 config.php 에서 '' 로 둔다(r3-16)
// helpURL: 관리 화면 '도우미' 링크의 앞부분. 뒤에 메뉴 경로(예: center/dashboard)가 붙고 {lang} 은 블로그 언어로 바뀐다. 비어 있으면 아래 기본 주소를 쓴다.
// 예: $service['helpURL'] = 'https://example.com/{lang}/menu/';
$service['helpURL'] = 'http://help.tattertools.com/{lang}/Textcube/Menu/';
// Textcube-NG: 서버가 다른 주소에서 받아 오는 응답(HTTPRequest)의 상한. 바이트·초, 0 이면 상한 없음, 넘으면 그 요청은 실패한다(r3-7, 결정 446b)
// MaxSize·MaxTime 은 화면에 쓰는 응답(핑·RSS·리더 등), MaxSaveSize·MaxSaveTime 은 파일로 받는 응답(백업 웹 가져오기 등)
$service['httpRequestMaxSize'] = 10485760;
$service['httpRequestMaxTime'] = 60;
$service['httpRequestMaxSaveSize'] = 536870912;
$service['httpRequestMaxSaveTime'] = 600;
// Textcube-NG: true 면 예약 발행(cron)이 자기 블로그 주소를 부를 때도 공인 IPv4 80/443 만 연결한다(기본 false = 원본처럼 자기 주소 호출)(r3-7, 결정 446d)
$service['cronRequestPublicOnly'] = false;
// Textcube-NG: false 면 글 발행 알림(XML-RPC ping)을 내부 주소로도 보낸다(기본 true = 공인 IPv4 80/443 만)(r3-7, 결정 446f)
$service['pingPublicOnly'] = true;
// Textcube-NG: true 면 API 키를 정하지 않은 계정이 API 키 자리에 계정 비밀번호를 쓸 수 있다(원본 동작). API 요청(ttxml·댓글 알리미 RSS/Atom)에만 쓰고 화면 로그인과는 무관.
// 기본 false = 비밀번호 대체 불가, API 키가 없으면 403(r3-7, 결정 461j)
$service['allowPasswordAsAPIKey'] = false;
// Textcube-NG: memcached 캐시 항목 만료(초). 0 = 만료 없음(원본 동작), 최대 2592000(30일)(r3-20)
$memcached['cache_ttl'] = 0;
// Textcube-NG: 그림 첨부 검사(r3-20). 이미지 확장자(jpg·jpeg·png·gif·webp·avif·svg) 업로드에만 걸고 백업 가져오기에는 걸지 않는다
//   imageMaxBytes: 일반 그림 크기 상한(바이트, 기본 10485760 = 10MB, 0 = 검사 안 함). 넘으면 업로드를 거부한다
//   svg 1MB·가로세로 4096 상한은 고정이다(Attachment::SVG_MAX_BYTES·IMAGE_MAX_DIMENSION)
$service['imageMaxBytes'] = 10485760;
// Textcube-NG: SVG 정화 한도(r3-20). 요소 깊이·요소 수를 넘거나 남은 <use> 가 svgMaxUse 개를 넘으면 정화 실패로 본다(0 이하면 기본값)
$service['svgMaxDepth'] = 30;
$service['svgMaxNodes'] = 20000;
$service['svgMaxUse'] = 256;
// Textcube-NG: true 면 정화한 SVG 를 Imagick 으로 PNG 변환해 cache/thumbnail/{blogid}/{n}.svg.png 에 둔다. 기본 false(r3-20)
//   svgRasterizeTimeLimit: Imagick 시간 한도(초, 0 = 걸지 않음). ImageMagick policy.xml 은 설치 안내를 따른다
$service['svgRasterize'] = false;
$service['svgRasterizeTimeLimit'] = 10;
// Textcube-NG: true 면 업로드한 일반 그림(jpg·png·gif·webp·avif)을 GD 로 다시 저장한다. 기본 false(r3-20)
//   애니메이션 GIF 는 첫 장만 남고, JPEG 의 EXIF(회전 정보 포함)는 사라진다
$service['imageReencode'] = false;
//$service['adminskin'] = 'whitedream';
?>
