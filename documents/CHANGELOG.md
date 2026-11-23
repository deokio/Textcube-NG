# 개정이력 — Textcube-NG 1.10.10 (PHP 8.5)

Textcube 1.10.10 을 PHP 8.5 에서 동작하도록 이식하고 보안을 보강한 판의 개정이력이다. 판은 r1·r2·r3·r4 순서로 올라간다. 원본과 달라진 동작과 이전·되돌리기 방법은 [MIGRATION.md](MIGRATION.md), 보안 항목은 [SECURITY.md](SECURITY.md) 를 본다.

## r4 (2026-11-24)

### 보안
- 분류 피드: 공개 상위 분류의 RSS·ATOM 피드에 비공개 하위 분류의 공개 글이 방문자에게 나가던 문제를 고쳤다. 글을 비공개로 바꾸거나 지우거나 다른 분류로 옮기면 그 분류와 상위 분류의 목록·피드 페이지 캐시를 지운다(전에는 피드 캐시에 글이 남았다).
- 출력 인코딩: 휴지통 화면의 분류 선택 상자가 분류 이름을 JS 문자열에 escape 없이 넣어, 편집자가 만든 이름이 관리자 화면에서 스크립트로 실행될 수 있던 문제를 고쳤다. 분류 body id 는 영문·숫자·`_`·`-` 20자 이하만 저장하고, 스킨의 `<body id>`·글 목록 class 로 나갈 때도 escape 한다(백업 가져오기의 분류 body id·공개 값도 같은 규칙).
- 스팸 필터: SQLite 3.44 미만에서 이름·홈페이지·본문 차단과 화이트리스트가 동작하지 않던 문제, PostgreSQL·Cubrid 에서 손님 IP 차단이 늘 꺼져 있던 문제를 고쳤다. PostgreSQL·Cubrid 어댑터가 따옴표가 많은 질의를 변환할 때 따옴표 수의 제곱만큼 시간이 걸리던 문제를 고쳤다.
- `.user.ini` 차단: `AllowOverride FileInfo` 만 허용한 Apache 서버에서도 동작하는 RewriteRule 로 바꿨다(전 규칙은 이런 서버에서 모든 요청이 500). 경로 뒤에 PATH_INFO 가 붙은 요청(`/.user.ini/x`)도 막고, 설치 마법사의 IIS 템플릿도 같은 범위로 맞췄다.
- 플러그인 데이터 관리: 코어 표인 휴지통 두 표(`TrashComments`·`TrashRemoteResponses`)가 미등록 표로 보여 지울 수 있던 문제를 고쳤다.
- 정적 자산: CodeMirror 의 node.js 전용 파일(`package.json`·`bin/source-highlight`·`addon/runmode/runmode.node.js`, 편집기는 쓰지 않음)을 뺐다. 덮어써 올린 설치는 `cleanup_legacy_assets.php` 가 지운다.

### 기능 추가
- 이모지(4바이트 문자): MySQL 5.6 이상·MariaDB 10.0 이상에 새로 설치하면 표와 연결을 utf8mb4 로 만든다(콜레이션 `utf8mb4_unicode_520_ci`, MySQL 8.0 은 `utf8mb4_0900_ai_ci`). 이미 설치한 사이트는 서비스 관리자가 `/checkup` 의 '데이터베이스 문자셋(이모지)' 절에서 표를 하나씩 바꾼다(변환 전 백업 필요, 되돌리는 화면 없음, 같은 값이 되는 중복은 먼저 목록을 보고 정리). MySQL 의 긴 문자 열 색인 7곳과 플러그인 표 키는 앞 191자로 비교한다.
- 분류(글갈래) 3단계 이상: 분류 관리에서 최대 깊이(기본 3, 2~10)를 정한다. 상위 분류의 글 목록·피드·글 수는 모든 하위 분류를 포함하고, 주소는 `/category/상위/중간/하위` 경로를 쓴다(번호 주소도 그대로). '상위 분류' 선택 상자로 분류를 옮기고, 위·아래 단추는 같은 단계 안 순서만 바꾼다. 하위 분류나 글이 있는 분류는 지우지 못한다. 백업 파일은 분류를 깊이만큼 담고 분류의 공개 설정·body id 도 담는다(전에는 복원하면 비공개 분류가 공개로 돌아갔다). CL_Moblog·ST_MediaLog 의 분류 선택도 여러 단계를 보인다.

### 버그 수정·호환
- 설치 마법사가 PHP 8.5.0 미만에서는 설치를 막는다.
- 글쓰기 화면마다 `upload_max_filesize` 숫자 경고가 나던 문제를 고쳤다.
- 분류: PostgreSQL 에서 하위 분류가 트리에서 빠지고 위·아래 옮기기가 실패하던 문제, 분류 RSS 번호 주소(`/rss/category/<번호>`)가 500 오류이던 문제를 고쳤다. 블로그 API·백업 가져오기·Moblog 로 글을 추가·삭제·수정하거나 분류를 바꿀 때 분류 글 수가 어긋나던 문제(예약·비공개·보호 글 포함)를 고쳤다.
- 태그 없는 글을 지울 때 빈 `IN ( )` 질의가 실패하던 문제를 고쳤다. 손님 이름·홈페이지 기억 쿠키가 동작하지 않던 문제를 고쳤다.
- 스킨 설정: 백업 내보내기의 스킨 설정이 빈 값이던 문제와 가져오기에서 '최근 글 길이'가 복원되지 않던 문제를 고쳤다.
- 설치: 테이블 접두어를 비우면 기본값 `tc_` 를 쓴다(전에는 빈 접두어로 설치되어 MySQL 에서 `Lines` 표가 만들어지지 않았다). 새 설치 `.htaccess` 가 한 글자 이름의 캐시 파일도 막는다.
- MySQL 8(STRICT)에서 `/checkup` 이 `Lines`·`Widgets` 표를 만들지 못하던 문제를 고쳤다.

### 문서
- [MIGRATION.md](MIGRATION.md) 에 r3 에서 r4 로 올릴 때의 주의(10절)를 더했다. README 요구 사항에 이모지·분류 깊이 안내를 더했다.
- `cleanup_legacy_assets.php` 목록에 CodeMirror node.js 전용 파일 3개를 더했다. `manual_requirements.txt` 의 PHP 표기를 이 판에 맞췄다.
- PHP 5.5~7.3 을 쓰는 호스팅을 위한 레거시판을 같은 r4 로 따로 배포한다(`Textcube-NG-1.10.10+php55.r4.zip`).

## r3 (2026-11-19)

### 보안
- 인증·세션: 로그인할 때 세션 ID 를 바꾸고 이전 익명 세션에 로그인 정보를 남기지 않는다. URL(`?TSSESSION=`, `/login?session=`)로 세션을 고르는 경로를 없앴다. 계정별 로그인 실패 잠금과 비밀번호 확인 실패 한도를 두었다. 접속 IP 별 로그인 잠금(`$service['loginLockScope'] = 'ip'`)을 고를 수 있고, 블로그 API(XML-RPC) 로그인도 시도 횟수를 제한한다. DB·memcached 세션은 접속 주소를 확인하고, memcached 에 연결하지 못하면 DB 세션을 쓴다.
- 비밀번호: 저장 방식을 `$service['passwordHash']` 로 고른다. 기본은 원본과 같은 md5 이고, `'multi'`·`'migrate'` 로 두면 password_hash 로 저장한다.
- 재인증: e-mail·API 키·블로그 주소·도메인 변경, 오픈아이디·소셜 연결과 해제, 임시 암호 발급, 블로그 소유자 변경 전에 현재 비밀번호를 다시 묻는다.
- 인증 토큰: 초대 메일 링크와 임시 암호는 유효 시간(`$service['authTokenLifetime']`, 기본 7일)이 있고 한 번만 쓸 수 있다. 토큰·비밀번호 생성기를 CSPRNG 로 바꿨다.
- 권한: 비정규 경로로 관리 화면에 들어가던 권한 우회를 막고, 권한 표에 없는 관리 경로는 기본 거부한다. `/control` 은 서비스 관리자만 쓴다. BlogAPI 는 블로그 구성원만 인증한다. 데이터 최적화, 첨부, 팀블로그 화면, 플러그인 설정 저장, 점검(checkup) 작업의 권한을 바로잡았다.
- 비공개 글: 글관리 권한이 없는 구성원과 방문자에게 다른 사람의 비공개·예약 글이 목록·검색·캐시로 보이던 문제를 고쳤다.
- CSRF: 관리 화면 요청에 블로그별 토큰을 쓴다. 상태를 바꾸는 GET 주소를 POST 로 바꿨다. 관리 화면과 로그인 폼에 `X-Frame-Options: SAMEORIGIN` 을 보낸다.
- SQL 삽입: 도메인 모드 Host 조회, 위도·경도, 분류 번호, 사용자 찾기, 통계, 위젯 매개변수, 가져오기 값 등을 정수 변환·escape·준비 문장으로 고쳤다. MySQL 외 DB(SQLite3·PostgreSQL·Cubrid)도 준비 문장을 지원한다.
- XSS: 관리 화면·사이드바·표지·대시보드·리퍼러·Moblog·GoogleMap·편집기 번역 문자열·블로그 머리 출력에 출력 인코딩을 더했다. 리더 본문은 허용 목록 HTML 정리를 거친다.
- 서버가 보내는 요청: 리더 피드·OPML, 이미지 프록시, 백업 웹 가져오기, 링크 사이트 정보, 트랙백, 댓글 알림, OIDC·소셜 로그인 요청은 공인 주소의 http/https 표준 포트로만 보낸다.
- 파일: 서버 백업 파일의 웹 노출, 첨부 쓰기 경로 순회, 임시 파일(pickle) 경로를 막았다. 플러그인·리소스 locale, 플러그인·스킨 `index.xml`, `.user.ini` 를 웹에서 직접 열 수 없다.
- 요청 정보: `X-Forwarded-For`·`Client-IP`·`X-Forwarded-Proto` 는 신뢰 프록시(`$service['trustedProxies']`)에서 온 것만 믿는다. Host 헤더를 검증한다.
- 설치·설정: 설치 마법사의 config.php 코드 주입과 값 출력, 서버 설정 저장의 값 escape 를 고쳤다. 로그인·OpenID·소셜 로그인의 오픈 리다이렉트를 막았다.
- 소셜 로그인: 서비스 주소를 `//호스트` 형식으로 두었을 때 위조한 Host 헤더로 콜백 주소(`redirect_uri`)가 바뀌던 문제를 고쳤다. 콜백은 설정한 호스트로 고정된다. 서비스 주소를 `http(s)://` 형식으로 둔 경우도 그 주소로 고정한다(도메인 모드 블로그는 기존 동작).
- 정적 자산: jQuery 3.7.1·jQuery UI 1.13.3·Lodash 4.18.1·TinyMCE 7.9.3(media 플러그인 XSS 수정 반영)·CodeMirror 5.65.21·Bootstrap 3.4.1 로 올렸다. 관리 화면의 Dojo·MooTools 를 jQuery 로 바꾸고 라이브러리 파일과 쓰지 않는 옛 자산을 지웠다(덮어써 올린 설치는 `cleanup_legacy_assets.php`).
- 그 밖: OpenID 댓글 고정 비밀번호, Moblog 발신자 확인·첨부 저장, POP3/IMAP 인증서 이름 검증, 팀블로그 초대 링크의 해시 노출을 고쳤다.
- 실행 차단: `plugins/`·`attach/` 아래 PHP 파일(`php[0-9]*`·`phps`·`phtml`·`phar`, `a.php.jpg` 처럼 확장자 뒤에 이름이 이어지는 경우 포함)을 웹에서 실행하지 못한다. 기존 `.htaccess` 에는 `/checkup` 이 규칙을 더한다.
- 첨부: 올린 SVG 에서 스크립트·이벤트 속성·`javascript:`·외부 참조·CSS `attr(` 를 지우고, 정화에 실패한 SVG 는 `.xxx` 이름의 일반 파일로 저장한다. SVG·`/attachment/` 응답에 nosniff·CSP 를 붙이고, `attach/` 의 `.html`·`.htm`·`.xhtml` 은 내려받기로 보낸다. 블로그 API 로 같은 이름에 다시 올릴 때 옛 이름이 실행형이면 새 난수 이름으로 만든다.
- 요청 검사: 입력 변수가 너무 많으면 413, 허용하지 않는 Content-Type 으로 본문을 보낸 POST 는 415, 형식이 깨진 요청은 400 으로 거절한다.
- 바깥 요청: 서버가 받아 오는 응답에 크기·시간 상한(`$service['httpRequestMax*']`)을 두었다. 글 발행 알림(ping)은 공인 주소로만 보낸다. API 키 자리에 계정 비밀번호를 쓰지 못한다(`$service['allowPasswordAsAPIKey']`).
- 피드·비공개 분류: 등록하지 않은 Host 의 요청은 피드 캐시를 읽거나 쓰지 않는다. 비공개 분류 글의 태그·위치를 방문자에게 보이지 않는다.
- 요청 방식: CL_Moblog 로그 지우기와 관리 화면의 플러그인 켜기·끄기는 POST + 토큰으로만 받는다.
- 출력 인코딩·SQL: 관리 화면 로그인 ID, Jukebox 모바일 출력, 첨부 동영상, 편집기 삽입 주소, RSS·ATOM 출력의 escape 와 slogan 중복 검사·세션 정리·사용자 삭제·블로그 소유자·블로그 설정 지우기·게스트 세션 질의의 escape·정수 변환을 고쳤다.
- 댓글·플러그인: '소셜 계정으로 글쓰기' 댓글은 서버가 소셜 신원을 확인하고 글쓴이 이름을 정한다. 플러그인 설정을 풀 때 객체를 만들지 않는다. 이미지 리사이저의 크기 인자를 검사한다. 손님 스킨 캐시에 로그인 사용자 이름이 남지 않는다.

### 기능 추가
- DB: MySQL·MariaDB SSL/TLS 연결. `$database['driver']` 로 native·PDO 드라이버 선택(MySQL·MariaDB·PostgreSQL·SQLite3·Cubrid).
- 로그인: 소셜 로그인 플러그인(Kakao·Naver·Facebook), OIDC 로그인. OpenID 와 소셜 로그인 신원을 구분해 표시한다.
- 플러그인: 신뢰 프록시·로드밸런서(Cloudflare 대응), 검색 최적화(SearchOptimizer: 메타·Open Graph·JSON-LD), 페이지(ST_Pages), 미디어로그(ST_MediaLog).
- 서비스 관리: 플러그인 화면(파일 무결성·PDO 호환성 검사, 허용 목록), 플러그인 데이터 관리, NG 설정 표시, 대시보드 플러그인 차단 알림.
- 서비스 주소: `$serviceURL` 을 `//호스트` 형식으로 쓸 수 있다. 화면(HTML)의 주소는 `//` 그대로 두고, RSS·메일·리더 referer 처럼 밖으로 나가는 주소는 `$service['useSSL']` 에 맞춘 절대 주소로 내보낸다. https 로 서비스하면(프록시에서 TLS 를 끝내는 경우 포함) `useSSL` 을 true 로 둔다.
- 서버 설정 화면(`/control/server`): 다른 설정 때문에 쓰이지 않는 항목을 흐리게 표시하고 이유를 안내한다(페이지 캐시·DB SSL·로그인 잠금 범위·서비스 리소스 경로·포트·SMTP).
- 첨부 내려받기: 부분 요청(206/416)·HEAD·UTF-8 파일 이름.
- 태그 목록 개수 설정. 판 번호 `TEXTCUBE_NG_VERSION` 표시. 코어 스크립트 주소에 캐시 무효화 값을 붙인다.
- HSTS: `$service['hsts']` 를 켜면 https 요청에 `Strict-Transport-Security` 를 보낸다(`hstsMaxAge`·`hstsIncludeSubDomains`·`hstsPreload`).
- 그림 첨부: svg·webp·avif 의 형식과 썸네일을 지원한다. 그림 크기 상한(`imageMaxBytes`, svg 1MB·가로세로 4096), SVG 의 PNG 변환(`svgRasterize`), 그림 다시 저장(`imageReencode`)을 고를 수 있다.
- 사이트맵 `/sitemap`(공개 블로그의 공개 글). 글 대표 주소를 내는 스킨 치환자 `article_rep_url`.
- memcached: 캐시 항목 만료(`$memcached['cache_ttl']`)와 `unix://` 소켓.
- 플러그인: 끄고 다시 켜도 설정을 지킨다(보관 설정). 차단된 플러그인은 [다시 검사하고 풀기]가 끄지 않고 다시 검사한다. 설정 [초기화]·[보관 설정 지우기]를 더했다. 같은 블로그의 플러그인 작업은 하나씩만 한다.
- 소셜 로그인: 댓글·방명록 입력 폼에 로그인 버튼을 자동으로 보인다(CL_SocialLogin 설정 `comment_form_auto`, 기본 켬).
- 모바일 화면: 모바일 기기에서 `?mode=desktop` 을 고르면 브라우저 세션 동안 데스크톱 화면을 유지한다(`TC_DISPLAYMODE` 쿠키, `?mode=mobile` 로 해제).
- `useSSL`: true 로 두면 서비스 주소를 https 로 만든다(원본 동작).
- 관리 화면 공지 피드의 기본 주소를 Textcube-NG 릴리즈 Atom 피드로 했다(`$service['noticeFeedURL']`, `''` 이면 끔).

### 버그 수정·호환
- PHP 8.0~8.5 의 TypeError·ValueError·deprecated 경고를 일으키던 코드를 고쳤다(배열·null 입력, 정수·소수 변환, 비정적 메서드 정적 호출, `strftime` 대체 등).
- 백업: TTXML 가져오기·내보내기 오류, 첨부·배너 이름과 본문 참조, 블로그 번호가 바뀐 복원의 첨부 경로, 분류 내보내기 오류를 고쳤다.
- DB: 호환 변환(`__compatQuery`)이 문자열 리터럴 안을 바꾸던 문제, 질의 캐시, PostgreSQL·Cubrid·SQLite3 어댑터 결함, 빈 문장 처리, 댓글 알림 대기열 인덱스를 고쳤다.
- 화면: 로그인 리디렉션 루프, 트랙백 받기 오류, 편집기 폭·첨부 영역, 너무 잦은 자동 저장, 사이드바·표지 미리보기, 영어 '켜짐' 번역, 분류 이름 바꾸기, 페이지 번호 처리, 서비스 관리 블로그 추가 시 주소 대소문자를 고쳤다. 여러 언어의 번역 문구를 정리했다.
- 운영: 서버 설정 저장 때 사용자 키가 사라지던 문제, `disable_functions` 환경, ModSecurity 같은 웹 방화벽에서 막히던 요청 형식을 고쳤다.
- 편집기: 오브젝트 크기를 바꿀 때 주소 안의 `width=` 까지 바꾸던 문제, 속성 창의 코드·크기 표시와 같은 그림 판정을 고쳤다. 표지 문자열이 든 오브젝트 주소는 넣지 않는다.
- 피드·캐시: 태그·검색·분류·작성자·보관함 피드와 피드 캐시는 늘 데스크톱 본문으로 만든다. 스킨 캐시는 서비스 주소마다 따로 둔다.
- 플러그인: SB_Spacer 설정 저장, 없는 플러그인을 끌 때의 500 오류를 고쳤다. 데이터 전체 삭제가 블로그 로고·플러그인 무결성 기준값·보관 설정도 지운다.
- 그 밖: 카카오 로그인 동의 항목(`account_email` 뺌), 일본어·번체 중국어 번역, 서버 설정 화면이 보내는 SMTP 포트 값의 인코딩, 팀블로그 설정 화면 경고, 블로그 설정 지우기 메서드 이름, slogan 의 '·' 처리를 고쳤다.
- 메일: 메일 발송은 PHPMailer 6.9.2 만 쓴다. 쓰지 않던 PHPMailer 5.x 파일(`library/contrib/phpmailer/` 의 `class.phpmailer.php`·`class.smtp.php`·`class.pop3.php`·`ChangeLog.txt`·`codeworxtech.html`·`README`)을 지웠다. 덮어써 올린 설치는 `cleanup_legacy_assets.php` 가 함께 지운다(내용이 바뀐 파일은 건너뛰므로 직접 지운다).

### 문서
- 이전 안내 [MIGRATION.md](MIGRATION.md), ModSecurity 설정 안내(`setup_modsecurity.txt`)를 더했다.
- 설치 마법사가 만드는 config.php 예시와 `manual_config.txt` 에 서비스 주소(`//호스트`)와 `useSSL` 안내를 더했다. 서버 설정 화면의 안내는 6개 언어로 넣었다.
- `manual_config.txt` 에 Textcube-NG 설정 키를 더했다. `cleanup_legacy_assets.php` 목록에 CodeMirror 데모 폴더(`mode/rpm/changes`), PHPMailer 5.x 파일, OpenID 2.0 라이브러리(`library/contrib/phpopenid/`), jpgraph 그래프(`plugins/StatGraph/count/`)를 더했다. `setup_modsecurity.txt` 에 요청 형식 검사 안내를 더했다.

## r2 (2026-05-31)

### 보안
- 사용자 찾기(`/control/action/user/suggest`)의 SQL 삽입과 XSS 를 고쳤다.
- 댓글·알림·글 관리 화면의 반사형 XSS 를 고쳤다.
- 도움말(`/owner/help`) 언어 값의 경로 순회를 막았다.
- 관리 화면 상태 변경 요청의 CSRF 검사를 보강했다.
- 쿠키에 HttpOnly·SameSite·Secure 속성을 붙였다.
- 지원이 끝난 OpenID 2.0 을 없애고 OIDC 로 다시 구현했다.

### 그 밖
- 통계 그래프를 jpgraph(QPL) 대신 외부 의존 없는 SVG 그래프로 바꿨다.
- HTTP 에서도 주소 복사가 되도록 했다.
- MySQLi 어댑터를 객체지향 API 로 바꿨다.

## r1 (2026-05-26)

### PHP 8.5 이식
- PHP 7.x·8.x 에서 없어지거나 deprecated 된 함수와 문법을 권고에 따라 바꿨다(PHP4 스타일 생성자, `${}` 보간, 동적 프로퍼티, nullable 인자, `strftime`, `utf8_encode` 등).
- magic_quotes 처리를 같은 동작의 입력 정규화로 바꿨다.
- phpmailer 6.9 로 올리고 GD·memcache 의 PHP 8 동작을 맞췄다.
- 정적으로 호출되는 legacy 메서드에 `static` 을 붙였다.

### 보안
- 로그인·글·댓글·BlogAPI·검색의 주요 쿼리를 준비 문장으로 바꿨다.
- 난수 생성을 CSPRNG 로, 문자열 비교를 `hash_equals()` 로 바꿨다.
- 서버 설정·사용자 추가·삭제 화면에 권한 검사를 더했다.
- memcached 세션 ID 생성과 세션 고착을 고쳤다.

### 버그 수정
- 새 블로그의 기본 편집기, 비표준 포트에서의 요청 출처 비교, 숫자 검사, 도메인 모드 블로그 주소 표기를 고쳤다.

---

<sub>Modifications documented herein by @deokio (2026), performed with AI assistance (Anthropic Claude) under human review.
No additional copyright is asserted. Licensed under GPL (same as the rest of the project).</sub>
