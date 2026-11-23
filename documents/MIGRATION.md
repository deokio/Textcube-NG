# MIGRATION — Textcube 1.10.10 → Textcube-NG 이전 안내

원본 Textcube 1.10.10 에서 Textcube-NG PHP 5.5 ~ 7.3 레거시판으로 옮기거나 원본으로 되돌릴 때의 절차와 주의사항. PHP 5.5 판만의 주의는 11절.
원본과 달라진 동작 전체는 8절, 이전 NG 판(r3 이하)에서 r4 로 올릴 때의 주의는 10절, 변경 내용은 [CHANGELOG.md](CHANGELOG.md), 보안 현황은 [SECURITY.md](SECURITY.md) 참조.
아래 `{접두어}` 는 `config.php` 의 `$database['prefix']`, `{블로그 주소}` 는 블로그 첫 화면 주소.

---

## 1. 이전 방법

| 방법 | 내용 | 맞는 경우 |
|------|------|-----------|
| A. 파일 교체(drop-in) | 원본 설치 위치의 PHP 파일을 NG 로 바꾸고 기존 DB·`config.php`·첨부를 그대로 사용 | 같은 서버·같은 DB 로 계속 운영 |
| B. 백업 가져오기 | 원본에서 백업 파일(TTXML) 내보내기 → NG 새 설치 → 가져오기 | 서버·DB 를 옮기거나 원본 설치를 그대로 남겨 둘 때, 블로그 번호가 바뀌는 이전 |

- 기준 버전은 원본 1.10.10. 더 낮은 버전의 DB 도 checkup 이 원본의 단계별 변환을 실행하지만(MySQL·MySQLi 만, 원본과 같음) 이 이식본에서는 검증하지 않음.
- 요구 사항(PHP 버전·확장·DBMS)은 각 패키지 README 의 `REQUIREMENTS` 절 참조. PostgreSQL·Cubrid·SQLite 는 제한 사항이 있음(8절).

## 2. 이전 전 준비

- 백업(두 방법 공통):
  - DB 전체 덤프.
  - `attach/`(첨부), `config.php`, `.htaccess`, `skin/blog/customize/`(관리 화면에서 편집한 스킨), 직접 추가한 스킨·플러그인 디렉토리.
  - 원본 코어·번들 플러그인 PHP 파일을 직접 고쳤다면 그 차이. NG 파일로 덮어쓰면 사라지고, 원본 코드를 그대로 다시 넣으면 NG 의 보안 수정이 빠지고 PHP 7.x 에서 오류가 날 수 있음 → NG 코드에 다시 적용.
- 관리 화면 백업 파일(TTXML)에는 플러그인 설정이 그대로 들어감(OIDC·소셜 로그인 Client Secret 등 비밀 키 포함 가능). 보관·전달 주의.
- 서드파티 플러그인·스킨: 쓰는 PHP 판(5.5 ~ 7.3)에서 동작하는지 확인. 확장 API 차이는 8절. 관리 화면 스크립트 `setUserSetting()`(화면 설정 저장)으로 기본 제공 키 외의 설정을 저장하는 플러그인은 저장되지 않음(8절).

## 3. 파일 교체(drop-in) 절차

1. 위 2절 백업.
2. NG 파일 복사. 다음은 덮어쓰지 않고 기존 것을 유지:
   - `config.php`, `.htaccess`, `attach/`, `cache/`, `skin/blog/customize/`, 직접 추가한 스킨·플러그인.
   - 작업 사본을 그대로 복사하는 경우 `.gitignore` 의 개발용 파일(`chkport.php`·`opcache_flush.php`)은 운영 서버에 올리지 말 것(배포 대상 아님).
3. 원본·이전 판에만 있고 NG 에서 없앤 파일 삭제 — 덮어쓰기로는 지워지지 않음. 아래 파일은 모두 NG 루트의 `cleanup_legacy_assets.php` 가 지움(실행 방법은 이 번호 끝):
   - `library/contrib/phpopenid/` — OpenID 2.0 라이브러리(v3.53 에서 제거).
   - `plugins/StatGraph/count/` — jpgraph 그래프(v3.52 에서 SVG 로 대체). 남아 있으면 `count.php` 를 웹에서 직접 호출할 수 있음. `plugins/StatGraph/` 의 다른 파일은 지우지 말 것.
   - `plugins/ED_tinyMCE/tinymce/plugins/codemirror/CodeMirror/` 의 node.js 전용 파일 `package.json`·`bin/source-highlight`·`addon/runmode/runmode.node.js`(r4 에서 제거, 편집기는 쓰지 않음). 지운 뒤 ED_tinyMCE 플러그인은 무결성 검사에 한 번 막히므로 플러그인 화면에서 '다시 검사하고 풀기'(10절).
   - 미사용 레거시 정적 자산(8.4).
   - 실행: NG 루트의 `cleanup_legacy_assets.php` 를 명령줄에서 실행(전체 목록은 스크립트 머리 주석과 `$files`).
     - `php cleanup_legacy_assets.php` 는 미리보기(지우지 않음), `--apply` 로 지움. `--backup=DIR`(미리 만든 웹 루트 밖 디렉토리)을 붙이면 복사한 뒤 지움.
     - 배포 내용과 sha256 이 같은 일반 파일만 지우고, 고친 파일·심볼릭 링크는 건너뛰어 목록만 보여 줌. 목록의 디렉토리는 비었을 때만 지움. 웹 요청은 404.
     - 명령줄 PHP 를 쓸 수 없으면 스크립트 안 `$files` 목록의 파일을 FTP 등으로 지움(고친 파일은 확인 후). 정리한 뒤 스크립트는 지워도 됨.
   - 남는 파일이 없도록 하려면 NG 를 새 디렉토리에 풀고 2번의 유지 항목만 옮긴 뒤 웹 루트를 바꾸는 방법이 확실함.
4. OPcache 를 쓰면 PHP-FPM·웹서버를 재시작(이전 코드 캐시 제거).
5. 관리자로 로그인한 상태에서 `{블로그 주소}/checkup` 을 한 번 실행(캐시 비우기는 소유자(`doesHaveOwnership()`)일 때, 또는 `cache/CHECKUP` 이 없거나 내용이 다르고 점검이 모두 성공했을 때 실행되며, 이때 모든 블로그의 휴지통 댓글·트랙백(`isfiltered > 0`)도 지움 — 원본 동작, 8절).
   - 원본과 같은 처리:
     - 기록된 버전이 현재와 다르면 MySQL·MySQLi 에서 원본의 단계별 테이블 변환.
     - `.htaccess` 규칙이 기대와 다르면 다시 씀(기존 파일은 `.htaccess_backup_YYYYMMDD`). 직접 추가한 규칙은 백업 파일에서 옮길 것.
   - NG 추가 처리(버전과 무관하게 매번 확인, 이미 적용됐으면 건너뜀):
     - MySQL·MySQLi: `{접두어}BlogSettings` 에 색인 `idx_name_value (name, value(64))` 가 없으면 추가. 행이 많으면 오래 걸릴 수 있음.
     - `$service['passwordHash']` 가 `'multi'`·`'migrate'` 이고 MySQL·MySQLi 이면 `{접두어}Users.password` 가 255자보다 짧을 때 `varchar(255) default NULL` 로 넓힘(5절).
     - PostgreSQL: 호환 함수(`year()`·`month()`·`dayofmonth()`·`unix_timestamp()`·`from_unixtime()`)가 없거나 결과가 다르면 `resources/setup/compatibility.PostgreSQL.sql` 로 다시 만듦(8.3).
   - 모든 단계가 성공하면 `cache/CHECKUP` 기록: 1행 원본 버전(`1.10.10`), 2행 NG 점검 표시 `ng:{점검 개정 번호}`(md5 외 방식이면 뒤에 ` password_hash`), 3행 NG 버전 `ngversion:{NG 버전}`(표시용, 점검 필요 여부는 1·2행만 보고 정함).
   - 실패한 단계가 있으면 `CHECKUP` 을 쓰지 않아 로그인·관리 화면의 점검 안내가 계속 나옴. DB 권한(`ALTER`·`INDEX`)·`cache/`·`.htaccess` 쓰기 권한을 고친 뒤 다시 실행.
   - 이후 NG 를 갱신해 점검 개정 번호가 오르거나 `passwordHash` 를 md5 외 방식으로 바꾸면 안내가 다시 나옴 → checkup 다시 실행.
   - 관리자로 로그인할 수 없을 때(NG 추가, 원본에 없음, r3-11): 일회용 점검 키로 checkup 을 블로그 소유자 점검으로 한 번 실행할 수 있음.
     - `config.php` 와 같은 폴더에 `checkup.key.php` 를 두 줄로 만듦. 1행은 정확히 `<?php exit; ?>`, 2행은 16자 이상 임의 문자열(키). 3행부터는 보지 않고, UTF-8 BOM·CRLF·2행 앞뒤 공백은 무시. 심볼릭 링크는 쓰지 않음.
     - 파일을 저장한 때부터 30분 안에 `{블로그 주소}/checkup` 화면의 키 칸에 키를 넣어 보냄(POST). 맞으면 그 요청 하나만 소유자 점검으로 실행하고, 이때만 '데이터베이스 테이블 복구(REPAIR)'를 고를 수 있음(MySQL·MySQLi 는 CHECK TABLE 뒤 MyISAM·Aria·ARCHIVE·CSV 만 REPAIR, PostgreSQL·SQLite 는 REINDEX, 그 밖 DB 는 지원하지 않음).
     - 키를 받으면 파일을 지움. 지우지 못하면 `cache/CHECKUP_KEY_USED` 를 남기고 같은 파일은 다시 받지 않으므로 직접 지울 것. 다시 쓰려면 새 키로 파일을 다시 저장.
     - 키는 로그인을 만들지 않음(관리 화면은 여전히 로그인 필요). 주소(GET)·쿠키로 보낸 키는 받지 않음.
     - `cache/CHECKUP` 이 현재 버전과 맞으면 checkup 은 작업 없이 다시 점검할지 묻고, 다시 점검은 로그인한 소유자(POST)나 맞는 점검 키로만 실행됨.
6. 확인: 점검 안내가 사라졌는지, 로그인·글 보기·쓰기·첨부(이미지·내려받기)·댓글·피드·관리 화면.

## 4. 새 설정 키 (`config.php`)

기존 `config.php` 에는 아래 키가 없으므로 기본값으로 동작(`authTokenLifetime` 외에는 원본과 같은 동작). 원본은 이 키들을 무시. 다만 `httpRequestMax*`·`pingPublicOnly`·`allowPasswordAsAPIKey`·`imageMaxBytes` 는 기본값이 원본 동작과 다르다.

| 키 | 없을 때 기본값 | 내용 |
|----|----------------|------|
| `$service['passwordHash']` | `'md5'` | 계정 비밀번호 저장 방식(5절) |
| `$service['trustedProxies']` | `array()` | `X-Forwarded-For`·`Client-IP` 를 믿을 프록시 주소(IP·CIDR 배열). 비어 있으면 헤더를 무시하고 접속 주소 사용. 이 목록에 속한 연결에서만 `X-Forwarded-Proto` 를 읽음(v3.151) |
| `$service['authTokenLifetime']` | `604800`(7일) | 인증 토큰(초대 메일 링크·관리 화면 임시 암호) 유효 시간(초). `0` 이면 만료 없음(원본은 만료 없음). 기간과 관계없이 로그인에 한 번 쓰면 지워짐(v3.106) |
| `$service['useLoginProxy']` | `false` | OIDC·소셜 로그인 서버 요청(discovery·token·jwks)에 환경 변수 프록시(`https_proxy` 등)를 쓸지. 불리언 `true` 일 때만 curl 이 프록시를 따르고 대상 IP 고정은 프록시가 맡음. 켜도 주소 검사(https·443·공인 IPv4)는 그대로, curl 이 없는 서버는 프록시를 쓰지 않음(v3.157 보완) |
| `$database['ssl']` | `false` | DB SSL/TLS 연결(MySQL·MariaDB·PostgreSQL·Cubrid). `false` 면 기존 연결(PostgreSQL 은 libpq 기본값) |
| `$database['ssl_ca']`·`['ssl_cert']`·`['ssl_key']` | `''` | CA 인증서·클라이언트 인증서·키 경로(Cubrid 는 쓰지 않음). PostgreSQL 키 파일 권한 0600 |
| `$database['ssl_verify']` | `false` | 서버 인증서·서버 이름 검증. `ssl_ca` 가 비면 시스템 CA. Cubrid 는 지원 안 함(`true` 면 연결 안 함) |
| `$database['driver']` | `''`(native) | DB 드라이버: `'native'`(각 DBMS 확장 — cubrid·mysqli·pgsql·sqlite3) 또는 `'pdo'`(PDO_CUBRID·PDO_MYSQL·PDO_PGSQL·PDO_SQLITE), 대소문자 무시. 그 밖의 값이면 연결 안 함(Cubrid v3.117, MySQL·MariaDB·PostgreSQL·SQLite3 v3.118). PDO 모드 차이는 8절 |
| `$service['loginFailureLimit']` | `5` | 같은 계정 로그인 실패 한도. 이르면 마지막 실패부터 15분 동안 그 계정 로그인을 받지 않는다. 숫자가 아니거나 1보다 작으면 5 (v3.202) |
| `$service['passwordConfirmFailureLimit']` | `5` | 데이터 전체 삭제·비밀번호 변경의 현재 비밀번호 확인 실패 한도(세션당, 재인증 실패와 합산), 숫자가 아니거나 1보다 작으면 5. 이르면 로그아웃 (v3.202) |
| `$service['hostAliases']` | `array()` | 등록 Host 밖에 받을 별칭('host' 또는 'host:port' — 포트를 적으면 그 포트만). 등록 Host 와 같은 요청만 페이지 캐시를 쓴다 (v3.205) |
| `$service['hostMismatchBehavior']` | `''` | single·path 형식에서 등록되지 않은 Host 처리: `''` 현행, `'redirect'` GET·HEAD 를 domain 주소로 302, `'404'` (v3.205) |
| `$service['useConfigPort']` | `false` | true 이고 `$service['port']` 를 적었으면 그 포트로 주소를 만든다 (v3.205) |
| `$service['useServiceURLonSkin']` | `true` | 블로그 스킨 출력의 스킨 경로(KeywordUI 키로그 스킨의 플러그인 경로 포함)에 서비스 주소를 쓴다(false 면 `$service['path']`) (v3.206) |
| `$service['useServiceURLonAttach']` | `true` | 스킨 출력의 블로그 로고(attach) 주소에 서비스 주소를 쓴다(false 면 `$service['path']`) (v3.206) |
| `$service['useServiceURLonResources']` | `true` | 로고가 없을 때 넣는 빈 이미지(resources) 주소에 서비스 주소를 쓴다(false 면 `$service['path']`) (v3.206) |
| `$service['hsts']` | `false` | https 요청에 `Strict-Transport-Security` 를 보낸다. `hstsMaxAge`(기본 `31536000`, `0` 이면 브라우저가 기억한 HSTS 를 지움)·`hstsIncludeSubDomains`(`false`)·`hstsPreload`(`false`)와 함께 쓴다. 정적 파일과 `setup.php` 에는 보내지 않는다 |
| `$service['loginLockScope']` | `'account'` | `'ip'` 면 계정별 잠금과 함께 접속 IP 별로 실패를 세어 `loginIpFailureLimit`(기본 `5`)에 이르면 마지막 실패부터 15분 동안 그 IP 의 로그인을 받지 않는다. `loginLockShowRemaining`(`false`)은 남은 시간 표시, `loginIpRecordLimit`(기본 `500`, 1~10000)은 IP 실패 기록 개수. 프록시 뒤면 `trustedProxies` 를 먼저 설정 |
| `$service['noticeFeedURL']` | Textcube-NG 릴리즈 Atom 피드 | 관리 화면 알림판 '공지사항' 이 읽을 피드 주소(`{lang}` 은 블로그 언어). `''` 이면 원격 요청을 하지 않는다 |
| `$service['helpURL']` | `'http://help.tattertools.com/{lang}/Textcube/Menu/'` | 관리 화면 '도우미' 링크의 앞부분(뒤에 메뉴 경로가 붙음) |
| `$service['httpRequestMaxSize']`·`['httpRequestMaxTime']` | `10485760`·`60` | 서버가 받아 오는 응답(핑·RSS·리더 등)의 바이트·초 상한. `0` 이면 상한 없음, 넘으면 그 요청은 실패 |
| `$service['httpRequestMaxSaveSize']`·`['httpRequestMaxSaveTime']` | `536870912`·`600` | 파일로 받는 응답(백업 웹 가져오기 등)의 바이트·초 상한 |
| `$service['cronRequestPublicOnly']` | `false` | `true` 면 예약 발행이 자기 블로그 주소를 부를 때도 공인 IPv4 80/443 만 연결 |
| `$service['pingPublicOnly']` | `true` | 글 발행 알림(XML-RPC ping)을 공인 IPv4 80/443 로만 보낸다. `false` 면 내부 주소로도 보냄 |
| `$service['allowPasswordAsAPIKey']` | `false` | `true` 면 API 키를 정하지 않은 계정이 API 키 자리에 계정 비밀번호를 쓸 수 있다(원본 동작). `false` 면 API 키가 없을 때 403 |
| `$memcached['cache_ttl']` | `0` | memcached 캐시 항목 만료(초). `0` = 만료 없음(원본 동작), 최대 `2592000` |
| `$service['imageMaxBytes']` | `10485760` | 그림 첨부 크기 상한(바이트, `0` = 검사 안 함). svg 1MB·가로세로 4096 상한은 고정. 그림 확장자 업로드에만 걸고 백업 가져오기에는 걸지 않음 |
| `$service['svgMaxDepth']`·`['svgMaxNodes']`·`['svgMaxUse']` | `30`·`20000`·`256` | SVG 정화 한도. 넘으면 정화 실패 |
| `$service['svgRasterize']`·`['svgRasterizeTimeLimit']` | `false`·`10` | `true` 면 정화한 SVG 를 Imagick 으로 PNG 변환(시간 한도 초). ImageMagick 설정은 `setup_nginx.txt` |
| `$service['imageReencode']` | `false` | `true` 면 올린 일반 그림을 GD 로 다시 저장(애니메이션 GIF 는 첫 장만, JPEG 의 EXIF 는 사라짐) |

- `trustedProxies`: 새 설치(setup.php)는 `127.0.0.1` + 설치 당시 서버 주소를 기록. 기존 설치는 변경 없음 → 리버스 프록시·로드밸런서 뒤라면 직접 추가(예: `$service['trustedProxies'] = array('127.0.0.1');`). 추가하지 않으면 방문자 IP 대신 프록시 IP 가 기록·필터에 쓰임. 같은 서버 주소에서 다른 사용자가 요청할 수 있는 공유 호스팅이면 서버 주소는 넣지 말 것.
- 신뢰 프록시 플러그인(v3.151, `ST_TrustedProxies`): config 키를 추가하지 않음 — 설정은 DB `ServiceSettings`(`trustedProxyEnabled`·`trustedProxyDevices`·`trustedProxyCloudflare`·`trustedProxyCfState`·`trustedProxyCfAttempt`·`trustedProxyCfRanges`). 서비스 관리자가 플러그인을 켜고 설정 메뉴 '신뢰 프록시'에서 장치(CIDR·헤더 스위치)·Cloudflare 대역을 등록. 판정은 config 처리 뒤라 `trustedProxies` 목록도 신뢰 장치로 봄. 끄거나 비활성이면 현행과 같음.
- 관리 화면에서 서버 설정을 저장하면(`writeConfigFile()`, v3.104) `config.php` 에서 화면의 키 값만 그 자리에서 바꿈: 주석·다른 키·줄바꿈 유지, 없는 키는 마지막 닫는 태그 앞에 추가, 문자열 값은 `var_export()` 형식(원본·이전 NG 는 파일을 다시 만들어 주석·일부 키 유실).
  - 'RSS 주소를 숫자로' 는 `$service['useNumericURLonRSS']` 로 기록. 원본·이전 NG 가 기록한 `$service['useNumericRSS']` 줄은 쓰이지 않음(지워도 됨).
  - `config.php` 에 문법 오류가 있으면 저장하지 않고 오류 줄·PHP 파서 내용을 표시(v3.113, 이전은 '실패했습니다.') → 파일을 먼저 고침.
- DB SSL(`$database['ssl']`, v3.103):
  - PostgreSQL: `ssl_verify=false` → `sslmode=require`, `true` → `verify-full`(서버 이름까지 검증). `ssl_ca` 가 비면 libpq 16 이상은 시스템 CA, 그 이하는 `~/.postgresql/root.crt`.
  - Cubrid: 암호화만(브로커 `cubrid_broker.conf` 의 `SSL=ON` 필요, PDO 모드도 같음). 서버·DB 이름에 `:`·`?`·`&` 가 있으면 연결 안 함(PDO 모드는 SSL 과 무관하게 `;`·`=` 포함).
  - 이전 NG 에서 MySQL·MySQLi 에 `ssl_verify=true` 이고 `ssl_ca` 를 비웠다면 검증 없이 연결됐으나 이제 시스템 CA 로 검증 → 자체서명 CA 면 `ssl_ca` 지정.
  - 이전 NG 의 MySQLi SSL 연결은 결과 값이 정수·실수였으나 평문 연결과 같은 문자열로 바뀜.
  - 연결 실패 원인은 화면에 내지 않고 PHP 오류 로그(`error_log`)에 기록.

## 5. 비밀번호 저장 방식 (`$service['passwordHash']`)

| 값 | 새로 설정·변경하는 비밀번호 | 기존 md5 값 |
|----|----------------------------|-------------|
| `'md5'`(기본) | md5(원본과 같음) | 유지. password_hash 로 저장된 값은 로그인 시 md5 로 다시 저장 |
| `'multi'` | password_hash | 유지(두 형식 공존) |
| `'migrate'` | password_hash | 로그인 성공 시 password_hash 로 변환 |

- 로그인은 어느 값이든 두 형식을 모두 확인.
- 바꾸는 절차:
  1. PostgreSQL·Cubrid 는 열을 먼저 넓힘(아래 SQL).
  2. `config.php` 에 `$service['passwordHash'] = 'multi';`(또는 `'migrate'`).
  3. `{블로그 주소}/checkup` 실행. MySQL·MySQLi 는 여기서 열을 넓힘. 점검 표시에 `password_hash` 가 기록되어야 안내가 사라짐.
- 열이 좁은 동안에는 저장 후 다시 읽어 확인하고, 실패하거나 잘렸으면 md5 로 저장(로그인 유지).
- DBMS 별 열 넓히기(원본 `varchar(32)`, NG 새 설치 `varchar(255)`):

| DBMS | 방법 | 확인 |
|------|------|------|
| MySQL·MySQLi | checkup 이 처리(직접: `ALTER TABLE {접두어}Users MODIFY password varchar(255) default NULL;`) | checkup 코드 |
| PostgreSQL | `ALTER TABLE {접두어}Users ALTER COLUMN password TYPE varchar(255);` | PostgreSQL 16 에서 확인(기존 md5 값·기본값 NULL 유지, 넓히기 전 60자 저장 실패·넓힌 뒤 저장) |
| Cubrid | `ALTER TABLE {접두어}Users MODIFY password varchar(255) default NULL;` | CUBRID 11.4 에서 확인(원본 정의 표, 기존 md5 값·NULL·기본값 NULL·고유 색인 유지, 넓히기 전 60자 저장 실패·넓힌 뒤 저장, v3.117) |
| SQLite | 필요 없음(열 길이를 강제하지 않음) | SQLite 3.46 에서 `varchar(32)` 열에 60자 값 저장 확인 |

- 넓힌 열을 32 로 되돌리지 말 것(password_hash 값이 있으면 실패하거나 잘려 해당 계정 로그인 불가).
- password_hash 로 저장된 계정은 원본 Textcube·md5 로 비교하는 플러그인·외부 도구에서 로그인 불가. DB 를 그런 프로그램과 함께 쓰면 `'md5'` 유지.

## 6. 원본으로 되돌리기

1. `config.php` 의 `$service['passwordHash']` 를 `'md5'` 로(또는 줄 삭제).
2. password_hash 로 저장된 계정 확인:
   `SELECT userid, loginid FROM {접두어}Users WHERE LENGTH(password) > 32;`
   → 해당 사용자가 NG 에서 한 번 로그인(md5 로 다시 저장)하거나 비밀번호 재설정. 결과가 0행이 된 뒤 진행.
3. 원본 파일로 복원: NG 전용 파일이 남지 않도록 원본을 새 디렉토리에 풀고 `config.php`·`.htaccess`·`attach/`·`skin/blog/customize/`·직접 추가한 스킨·플러그인을 옮기는 방법 권장. 원본은 PHP 5.x 에서만 동작.
4. 원본은 점검 안내를 한 번 표시(`cache/CHECKUP` 2행 형식을 모름) → 원본의 `{블로그 주소}/checkup` 실행.
5. 그대로 두어도 되는 NG 변경: `Users.password` 255자 열, `BlogSettings` 색인 `idx_name_value`.
6. 원본으로 돌아가면 달라지는 점:
   - 새 글 화면을 열 때 원본이 임시 첨부(`parent = 0`, 아직 글에 연결되지 않은 첨부)를 삭제. NG 와 원본이 같은 DB 를 함께 쓰면 작성 중 첨부가 지워질 수 있음. NG v3.133 부터 새 임시 첨부는 사용자별 `parent = -(userid+1)`(`-1` 은 블로그 로고)로 저장되므로 원본에서는 보이지 않고 남음. 남은 행 확인: `SELECT COUNT(*) FROM {접두어}Attachments WHERE parent < -1;`
   - NG 에서 쓴 OpenID 댓글(비밀번호 `''` 저장)은 원본 코드로 수정·삭제 불가.
   - OIDC·소셜 로그인 기능 없음(원본). `$database['ssl']` 등 NG 설정 키 무시 → SSL 을 요구하는 DB 서버에는 연결되지 않음.
   - NG 가져오기에서 바뀐 첨부 이름(`난수.확장자`)은 그대로.
   - NG 에서 발급한 인증 토큰은 원본에서 다시 만료 없음(`AuthTokenIssued` 행은 쓰이지 않음).

## 7. 백업 가져오기로 이전 (방법 B)

1. 원본 관리 화면 데이터 관리(`/owner/data`)에서 백업 파일(TTXML) 저장.
2. NG 새 설치(`setup.php`).
3. NG 관리 화면 데이터 관리(`/owner/data`)에서 복원. 기본은 이 블로그의 기존 데이터를 지우고 복원(원본과 같음).

- 백업에 없는 것: 계정·비밀번호(새 설치에서 만든 계정 사용), `config.php`, 스킨·플러그인 파일(`skin/`·`plugins/` 디렉토리 — 직접 복사). 스킨 설정·플러그인 설정 값은 들어 있음.
- 백업의 블로그 이름·2차 도메인은 블로그 설정에 반영하지 않음(원본과 같음). NG 는 이 값을 첨부 경로 판별에만 사용.
- 블로그 번호가 달라도 됨: 본문의 `attach/{원래 번호}/{이름}` 경로는 이번에 가져온 첨부면 `[##_ATTACH_PATH_##]/{이름}` 로 바꿈. 주소(호스트)가 붙은 경로는 그 호스트가 이 블로그 주소일 때만 바꿈.
- 다른 서버의 백업에서 단일·경로형 블로그의 원래 서비스 도메인이 붙은 절대 경로는 유지됨(백업에 그 도메인이 없음) → 필요하면 가져온 뒤 직접 수정.
- 형식에 맞지 않는 첨부·배너 이름은 `난수.확장자`(부적합·실행형 확장자는 `xxx`)로 바뀌고 본문 참조도 바뀜. 한 글에 같은 이름 첨부가 여럿이면 본문은 먼저 가져온 첨부를 가리킴.
- 상세: 8.7 '백업 가져오기'.

## 8. 운영 차이 요약

원본 1.10.10 과 동작·데이터가 달라진 점을 고칠 곳별로 묶었다. 운영·이전·원본으로 되돌리기·플러그인 호환 때 확인한다.

### 8.1 `config.php` 설정

- `trustedProxies`(4절): 목록에 속한 연결(또는 신뢰 프록시 플러그인이 신뢰한 연결)에서만 `X-Forwarded-For`·`Client-IP`·`X-Forwarded-Proto` 를 읽음. 새 설치 기본값에 IPv6 루프백 `::1` 은 없음(필요하면 직접 추가). https 를 끝내는 프록시가 목록 밖이면 서비스 주소가 `http://` 로 계산되므로 목록·플러그인에 등록하거나 `$serviceURL` 을 고정.
- `useSSL`: `true` 면 서비스 주소를 https 로 만듦(원본 동작). https 로 서비스하지 않는데 켜면 스킨·첨부 주소가 깨짐. `false` 면 요청이 https 인지(신뢰 프록시의 `X-Forwarded-Proto` 포함)로 정함.
- 서비스 주소: `/control/server` 에서 서비스 주소(`serviceurl`)를 빈 값으로 저장할 수 있음(빈 값이면 자동). 입력란에는 설정 파일로 강제한 값만 표시.
- `$service['hostAliases']`·`port`·`useConfigPort`: 다른 이름·포트로 들어오는 주소는 블로그 domain 을 고치거나 `hostAliases` 에 적음. `port` 가 없고 요청이 비표준 포트로 오거나 Host 가 domain·블로그 주소·`hostAliases` 와 다르면 페이지 캐시를 쓰지 않음(성능만 영향) → 캐시를 쓰려면 `port`(필요하면 `useConfigPort`) 또는 `hostAliases` 를 적음.
- `$service['useLoginProxy']`(4절): OIDC·소셜 로그인 요청은 서버의 환경 변수 프록시(`https_proxy` 등)를 기본으로 쓰지 않음 → 프록시로만 밖에 나갈 수 있는 서버는 `true` 로 두면 curl 이 환경 변수 프록시를 따름(PHP-FPM 은 8.2). 켜도 주소 검사(8.6)는 그대로라 서버가 IdP 이름을 공인 IPv4 로 해석할 수 있어야 하고, 대상 연결(IP 고정)은 프록시가 맡음. curl 이 없는 서버는 설정과 관계없이 프록시를 쓰지 않음.
- `$service['loginFailureLimit']`·`passwordConfirmFailureLimit`(기본 5): 동작은 8.7 '로그인'.
- `$database['driver']`(4절): `''`·`'native'`(기본, 각 DBMS 확장) 또는 `'pdo'`, 대소문자 무시. 그 밖의 값이면 연결 안 함(오류 로그만 기록). 원본·이전 NG 로 되돌리면 이 키는 무시되어 각 확장으로 연결 → PDO 드라이버만 있는 서버는 연결 불가. 모드별 차이는 8.3.
- `authTokenLifetime`(4절): 인증 토큰 유효 기간(기본 7일, 8.7 '로그인').

### 8.2 웹 서버 설정(`.htaccess`·nginx·Apache·PHP-FPM)

- 실행 차단: `plugins/`·`attach/` 아래 PHP 류 파일 요청은 403. 기존 `.htaccess` 에는 `/checkup` 이 이 규칙을 더하고 `.htaccess_backup_<날짜>` 를 남김(쓸 수 없으면 "확인 필요"). `attach/` 의 `.html`·`.htm`·`.xhtml` 은 내려받기로 나감.
- 기존 루트 `.htaccess` 는 업그레이드 때 다시 쓰지 않음(릴리즈 zip 에도 없음) → `.user.ini`·스킨 `index.xml` 차단 규칙은 `setup.php` 템플릿과 같은 줄을 직접 옮김. r4 에서 `.user.ini` 차단 줄의 꼴과 캐시 차단 줄이 바뀌었으므로 10절 1번을 따름.
- nginx: `setup_nginx.txt` 의 `location` 규칙을 옮김.
- Apache + PHP-FPM(`ProxyPassMatch`): `ProxyPassMatch "^/(.*\.php(/.*)?)$" fcgi://…` 는 `.htaccess` 규칙보다 먼저 `.php` 요청을 PHP-FPM 으로 넘기므로, `attach` 아래 `.php`류 이름을 `.htaccess` 의 `RewriteRule` 로 막지 못할 수 있음. `ProxyPassMatch "^/(?!attach/)(.*\.php(/.*)?)$"` 처럼 `attach` 를 제외하거나 `attach` 폴더에서 PHP 핸들러를 끄고 `security.limit_extensions` 로 제한할 것(앱 코드로는 막을 수 없음).
- Host 헤더:
  - Host 헤더 없는 요청(HTTP/1.0, HTTP/3 등 웹서버가 넘기지 않는 경우)은 호스트를 `''` 로 처리하고 `SERVER_NAME` 으로 대체하지 않음 → 도메인·멀티블로그 운영 시 웹서버가 Host 를 넘기도록 설정(nginx: `fastcgi_param HTTP_HOST $host;`).
  - 문법이 틀린 Host(포트 0·65536 이상 포함)는 400.
  - 프록시 뒤에서는 Host 를 보존(Apache `ProxyPreserveHost On`, nginx `proxy_set_header Host $host`)하고 X-Forwarded-Host 는 쓰지 않음. 다른 이름·포트는 8.1 `hostAliases`.
- 접속 주소 바꾸기(Apache mod_remoteip 등): 웹 서버가 접속 주소를 먼저 방문자 IP 로 바꾸면 신뢰 프록시 플러그인은 그 연결을 신뢰하지 않아 헤더(CF-Visitor 스킴 포함)를 쓰지 않음 → 플러그인으로 판정할 때는 웹 서버의 접속 주소 바꾸기를 끌 것(화면에도 안내).
- Cloudflare: IPv6 방문자 주소 저장은 추후 지원(지금은 접속 주소 유지). 대시보드 Network 의 Pseudo IPv4 를 Overwrite Headers 로 두면 IPv6 방문자도 `CF-Connecting-IP` 에 IPv4 형식(Class E) 주소가 들어와 그 주소로 기록.
- PHP-FPM: `clear_env` 기본값 때문에 셸 환경 변수가 PHP 에 전달되지 않으니 `useLoginProxy`(8.1)를 쓰면 풀 설정에 `env[https_proxy]` 를 둠.

### 8.3 DB 와 `/checkup`

- `/checkup`(3절 5번):
  - 캐시를 비우는 조건에서 모든 블로그의 휴지통 댓글·트랙백(`isfiltered > 0`)도 지움(원본 동작) → 남길 항목은 미리 복원.
  - PostgreSQL: 원본 `compatibility.PostgreSQL.sql` 은 PostgreSQL 12 이상에서 함수가 만들어지지 않아 보관 목록·달력 질의가 실패(`year()` 도 두 자리) → checkup 이 함수를 확인해 다시 만듦. 점검 개정 번호가 올라 기존 NG 설치도 점검 안내가 한 번 다시 나옴(PostgreSQL 이 아니면 추가로 바뀌는 것 없음).
  - 점검 개정 5: 댓글 알림 대기열 고유 인덱스가 (blogid, commentid) 로 바뀜. 코드만 되돌려도 인덱스는 그대로 남음.
  - `.htaccess` 실행 차단 규칙 추가는 8.2.
  - MySQL 8(STRICT): r4 부터 checkup 이 다시 만드는 `Lines`·`Widgets` 표의 TEXT 열에 기본값을 붙이지 않음(설치 SQL 과 같음, 전에는 1101 오류로 표를 만들지 못했음). 이모지(utf8mb4) 변환은 10절 3번.
- 임시 첨부: 새 글의 첨부는 사용자별 `parent = -(userid+1)` 로 저장되고, 글을 처음 저장할 때 자기 것만 글로 옮김.
  - 새 글 화면에서 임시 첨부를 자동 삭제하지 않음. 원본은 새 글 화면에서 삭제하므로 같은 DB 를 원본과 함께 쓰면 작성 중 첨부가 지워질 수 있음.
  - 업그레이드 전 임시 첨부(`parent = 0`)는 `group.administrators` 만 정리(`interface/owner/entry/attachmulti/orphancheck`·`orphandelete`).
  - 팀 구성원을 제거해도 그 사용자의 임시 첨부는 남음. 원본으로 되돌리면 음수 행은 보이지 않고 남음(6절).
- DB SSL: MySQL·MariaDB·PostgreSQL·Cubrid(Cubrid 는 암호화만, 4절).
- 어댑터 공통:
  - MySQL 래퍼는 오류 시 false(예외 아님). MySQLi 연결 실패 원인은 화면에 내지 않고 PHP 오류 로그에만 기록, DB 선택 실패도 `die()` 대신 false.
  - 질의 캐시: MySQLi·SQLite3 도 같은 요청 안 같은 SELECT 를 질의 캐시에서 다시 씀(원본은 캐시 미사용). 쓰기 문장(`query()`·`execute()`·prepared)·`rollback()`·DDL 뒤에는 비우지만, 다른 연결의 변경 뒤에는 같은 요청 안에서 이전 결과(MySQL·PostgreSQL 과 같음). 실패한 SELECT 는 질의 캐시·`*WithDBCache` 영구 캐시에 남기지 않고, DDL 뒤 질의 캐시·표 목록을 비움(MySQL·MySQLi prepared DDL 은 질의 캐시만).
  - 서버 정보 화면 DB 버전은 어댑터 이름·버전(MySQLi 설정이면 'MySQLi: …', 원본은 항상 'MySQL: '). DB 상태는 PostgreSQL 'Connection OK'/'Connection bad', SQLite3 'SQLite 버전', Cubrid 서버 정보 문자열(원본은 없는 함수·메서드 호출로 Error).
  - PostgreSQL 디버그 모드에서 실패한 질의도 오류로 기록(원본은 TypeError).
- SQLite3:
  - 원본의 PHP 에 없는 함수·메서드 호출을 고침 → 세션 저장·DB 점검(`owner/data/correct`)·설치 일부·서버 정보 화면이 Error 없이 동작(Cubrid·MySQLi 어댑터도 같은 종류 수정). `num_rows()` 는 결과를 처음부터 세고 처음으로 되돌림. 존재 확인(`queryExistence()`)은 결과 행으로 판단(원본·이전 NG 는 직전 변경 행 수로 판단해 틀릴 수 있었음).
  - 잠긴 DB 를 5초까지 기다림(`busyTimeout(5000)`, PDO `ATTR_TIMEOUT` 5).
  - 설치 시 DB 경로: 원본은 정의되지 않은 상수(`__TEXTCUBE_CACHE_DIR__`)를 써서 PHP 8 은 설치가 Error 로 멈추고 PHP 7.x 는 엉뚱한 폴더(`__TEXTCUBE_CACHE_DIR__`)에 DB 를 만듦 → `cache/` 에 만듦(이전 NG 도 같은 결함, 드라이버 선택과 무관).
- PDO 모드(`$database['driver'] = 'pdo'`, 8.1):
  - 설치(`setup.php`): 설치된 드라이버만 선택지로 표시, `pdo` 를 고를 때만 config.php 에 기록.
  - 연결 값 검사: MySQL·MariaDB 는 서버·DB 이름에 `;`·NUL, PostgreSQL 은 연결 값에 `;`·NUL·`user=`·`password=` 가 있으면 연결 안 함. 계정·암호는 DSN 에 넣지 않고 별도 인자로 넘김.
  - prepared: 자리표시자는 `?` 만. PDO 가 PostgreSQL·SQLite3 와 다르게 해석하는 문장 틀은 실행하지 않고 거부(아래).
  - 공통 주의: SQL 오류에 PHP 경고가 나지 않음(오류 번호·문구는 같음). 연결 실패 로그가 짧음(MySQL SSL 실패 상세 없음, PostgreSQL `pg_connect()` 접두어 없음).
  - MySQL·MariaDB: 한 번에 한 문장. prepared 값 개수·형식 문자 오류는 예외 대신 false(오류 2031).
  - PostgreSQL: `:이름`·`??`·`$`·주석·`E''` 가 든 prepared 문장 틀은 실패(Textcube 호출부 없음, 서드파티 플러그인은 확인). 리터럴에 `\'` 가 든 문장 틀도 실패(예: `\` 로 끝나는 OpenID 의 댓글 수정). bytea 는 `\x` 16진수(`bytea_output=escape` 서버는 네이티브와 다름). pgsql 확장이 없으면 연결 전 `escapeString()` 은 `\`·`'` 를 겹침.
  - SQLite3: 같은 DB 파일. 문자열·주석 밖에 `?번호`·`:`·`@`·`$`·`#` 가 든 prepared 문장 틀은 실패(Textcube 호출부 없음, 서드파티 플러그인은 확인). 숫자 열 값은 문자열로 돌려줌(PHP 8.1 미만 PDO SQLite 동작). prepared `d` 값은 문자열로 바인딩.
  - 상수: 이 판은 `PDO::MYSQL_ATTR_*` 등 기존 상수를 쓰고, PHP 5.5 에 없을 수 있는 상수(`PDO::MYSQL_ATTR_MULTI_STATEMENTS`·SSL 관련 등)와 정의되지 않을 수 있는 상수(`__TEXTCUBE_CACHE_DIR__`·`PGSQL_CONNECTION_OK` 등)는 `defined()` 로 먼저 확인.
- Cubrid:
  - 이 판은 PostgreSQL·Cubrid 를 PHP 5.5 ~ 7.3 에서 시험하지 않았다(아래는 이식본 공통 동작).
  - 확장: `$database['driver']` 를 넣지 않으면 기존과 같이 cubrid 확장. 업스트림 cubrid 확장(PHP 7+)의 `cubrid_close_request()` 는 다른 질의를 실행한 뒤 닫은 요청의 resource 를 잘못 해제(해제된 메모리 접근)하고, Textcube(원본 포함)가 결과 해제·prepared 닫기에 쓰므로 패치 확장([https://github.com/deokio/php8_cubrid](https://github.com/deokio/php8_cubrid) 참고) 또는 PDO 모드 사용. `'pdo'` 면 PDO_CUBRID — PHP 8 지원 패치판 필요(같은 저장소 참고, 업스트림은 PHP 8 미지원·암호를 연결 URL 에 넣어 `:`·`?` 가 든 암호로 연결 실패). 원본으로 되돌리면 `driver` 키는 무시되어 cubrid 확장으로 연결.
  - prepared 문장은 드라이버 바인딩으로 실행(값을 SQL 에 넣지 않음), 디버그 기록은 `?` 문장 틀. `query()` 경로는 `no_backslash_escapes=yes`(Cubrid 기본값) 전제.
  - PDO 모드: 백슬래시가 든 문자열 뒤 `:이름` 이 있는 SELECT 는 실행하지 않음, `ssl_verify` 미지원, int32 밖 정수·실수는 문자열로 바인딩. 결과 행은 네이티브와 같은 키 순서(BOTH 는 열마다 번호 키 → 이름 키)·NUL 처리(문자열 값은 첫 NUL 앞까지). BLOB·CLOB 은 PDO 가 스트림으로 돌려줌(Textcube 스키마에는 없음).
  - 패치 확장의 LOB·요청 재사용(Textcube 는 LOB 함수 미사용, 어댑터 경로 해당 없음): 업스트림 `cubrid_lob_close()`·`cubrid_lob2_close()` 도 `cubrid_close_request()` 와 같은 종류 결함이며 패치 확장에서 고침. `cubrid_lob_close()`·`cubrid_lob2_close()` 로 닫은 LOB resource 의 형식은 'Unknown', 다시 쓰면 경고와 false. `cubrid_lob_close()` 는 LOB 가 아닌 원소에서 멈춤(앞 원소는 닫힘). 결과를 끝까지 읽은 뒤·`cubrid_next_result()`·연결을 닫은 뒤의 LOB 는 'Invalid CUBRID-Lob2 resource' 경고와 false(업스트림 규칙). 연결(`cubrid_pconnect` 포함)을 닫은 뒤 남은 요청은 CCI 오류 경고와 false(`cubrid_affected_rows()` 는 -1), 열 정보 함수는 열 0개. prepared 요청을 다시 실행하면 이전 결과 마지막 행의 LOB 는 'Invalid CUBRID-Lob2 resource' 경고와 false(`cubrid_next_result()` 와 같음).

### 8.4 서드파티 플러그인·외부 스크립트

- PHP 코드:
  - PHP 8 정적 호출 오류 때문에 `static` 으로 선언한 legacy 메서드(`Tag`, `Base64Stream`, `Moblog::decorate_log`, `Skin::cutSkinTag`, `XMLRPC::sendFault` 등)를 비정적으로 재정의한 하위 클래스는 PHP 오류.
  - 서드파티 DB 어댑터는 `IAdapter` 의 `prepare()`·`bindAndExecute()`·`fetchAllStmt()` 구현 필요(로그인·댓글·글 저장이 사용). `fetchAllStmt()` 는 결과 집합이 없는 문장에 null. 어댑터 `fieldType()` 은 형식 이름(원본은 항상 null).
  - 플러그인 테이블: TEXT·BLOB·JSON 열은 DEFAULT 없이 생성(MySQL strict 오류 회피, `common.plugin.php`). 그 열 값을 빼고 넣는 플러그인은 DBMS·SQL 모드에 따라 NULL·빈 값·오류.
  - `DBModel` `create()`·`discard()` 가 실제로 동작(트리 안 호출 없음, 플러그인만 해당), MySQL·MySQLi `vartext` 형식은 varchar.
  - `Transaction::unpickle()` 은 객체를 복원하지 않고(`__PHP_Incomplete_Class`), tid 를 만든 세션에서 한 번만 읽힘 — 다른 세션에서 읽던 코드는 `false` 를 받음.
  - `Timezone::set()` 은 시간대 식별자가 아니면 false(원본은 true).
- `index.xml`:
  - `privilege` 가 `administrator` 인 플러그인은 서비스 관리자만 켜고 끄며 메뉴·관리 화면도 서비스 관리자에게만(원본은 목록에서만 숨김).
  - `<topMenu>control</topMenu>` 플러그인 관리 화면은 creators 만 열 수 있음(그 밖은 404). 전에는 topMenu 가 'plugin' 으로 바뀌어 블로그 관리 화면에 붙었음.
- 요청 보내기(플러그인 JavaScript·외부 스크립트·북마크):
  - CSRF 토큰: 관리 화면의 GET·HEAD 가 아닌 요청은 블로그별 토큰 필요. fetch/XHR 를 직접 쓰면 `X-CSRF-Token` 헤더나 본문 `csrfToken`(전역 변수 `csrfToken`)을 넣음(EAF4 요청과 POST 폼은 자동). 없으면 412.
  - GET → POST: 관리 화면의 댓글·트랙백·알림 삭제, 휴지통 삭제·복원·비우기, 필터 변경, 트랙백 보내기·기록 삭제, 글 공개 전환·별표·삭제, 링크 삭제, 리더 갱신, 데이터 정리·최적화, 블로그 설정(방명록·언어·시간대)·편집기 설정, 스킨 변경·관리 스킨 설정, 사이드바·표지 삭제·순서·초기화, 서비스 관리(control)의 블로그·사용자 동작 등 42개 주소와 블로그 주소 단독 변경 `/owner/setting/domain/default/{0|1}`·`primary`(`name`)·`secondary`(`domain`), 단건 첨부 삭제 `/owner/entry/detach/{글 번호}`(`name`), `/line/` 쓰기, 플러그인 켜기·끄기는 POST + 토큰만 받음(GET 은 405 `Allow: POST`, 값은 POST 본문, 필수 입력이 없으면 404). 공개 `/trackback/delete` 도 POST + 토큰(없으면 412). 사이드바·표지 순서 바꾸기를 XHR 로 보낼 때는 `ajaxcall=1`. `owner/setting/openid/delegate` 는 GET 이면 405(핸들러는 원래 항상 실패 응답). 관리 화면은 도메인 세 곳·단건 첨부 삭제 주소를 쓰지 않음(블로그 주소는 `domain/set`, 편집기 첨부 삭제는 `detach/multi`). 이 주소들을 GET 으로 부르던 북마크·외부 스크립트·플러그인은 실패.
  - 재인증: e-mail 변경(`/owner/setting/account`, 필명만 바꿀 때는 필요 없음), API 키 저장, 블로그 주소 저장(`/owner/setting/blog`·`domain/set`, domain·path 모드)과 위 도메인 세 곳, 오픈아이디·소셜 계정 연결·해제, 서비스 관리(`/control`)의 임시 암호 발급·블로그 소유자 변경(창설자 자신의 비밀번호)은 POST `confirmativePassword` 필요(관리 화면은 입력란 제공, 틀리면 오류 9). `/owner/setting/account/openid` 의 `mode=del`(해제)·`mode=prepare`(연결 준비)는 POST + 토큰 + 비밀번호(GET 은 405), 연결(`mode=add`)은 prepare 뒤 5분 안에 한 번만 — prepare 없이 부르면 거부. 사용자 쪽 동작은 8.7 '로그인'.
  - 배열 입력: 입력 규칙이 있는 값에 배열(`x[]=`)을 보내면 실패(페이지 404, 댓글 "Illegal parameters"). `'file'` 규칙은 여러 파일 형식(`x[]`)을 받지 않음. 여러 값은 쉼표로 이은 문자열(list 규칙)로 보냄.
  - 화면 설정 저장(`/owner/setting/userSetting/set`, 관리 화면 스크립트 `setUserSetting()`): 리더 패널 표시·높이, 편집기 속성 창 위치 고정(`readerPannelVisibility`·`readerPannelHeight`·`editorPropertyPositionFix`) 3개와 정수 값만 저장. 다른 키는 404, 범위 밖 값은 -1 응답으로 저장 안 함 → 다른 키를 이 경로로 저장하던 플러그인·스킨은 플러그인 설정 화면 등 별도 저장 방식으로 바꿔야 함. 글쓴이·편집자도 저장 가능(원본은 관리자 이상만 — 편집기 위치 고정 저장이 실패했음), 다른 `/owner/setting` 화면 권한은 그대로. 원본에서 이 경로로 저장된 값은 DB 에 그대로 남음(각 설정 화면에서 다시 저장하면 정상 값).
- 플러그인 켜기·끄기와 설정:
  - 같은 블로그에서 처리 중인 플러그인 작업이 있으면 다음 요청을 거절. 차단된 플러그인의 '다시 검사하고 풀기'는 끄지 않고 다시 검사(설정 유지).
  - 관리 화면에서 플러그인을 끄면 설정을 블로그 설정 `pluginSettingsKept` 에 남기고 다시 켜면 되돌림. 보관 묶음이 65000바이트를 넘으면 끄지 않음(설정을 초기화한 뒤 끔). 자동으로 끄는 경우와 데이터 삭제는 원본처럼 설정을 지움.
- JavaScript·정적 자산(`config.default.php` 상수 `JQUERY_VERSION` `3.7.1.min`, `JQUERY_UI_VERSION` `1.13.3.min`, `LODASH_VERSION` `4.18.1.min`):
  - jQuery 3 에서 제거된 API(`.load(fn)`·`.unload()`·`.error()`·`.size()`·`$.browser`·`.live()`/`.die()`·`.andSelf()`·`.toggle(fn, fn)`)와 `removeAttr('checked')`·`removeAttr('selected')`(→ `prop(…, false)`)를 쓰는 플러그인·스킨은 수정. 3.x deprecated API(`jQuery.isFunction`·`jQuery.proxy`·`bind`·`delegate` 등)는 3.7.1 에서 동작.
  - Lodash 2 → 4: `_.pluck` → `_.map`, `_.contains` → `_.includes`, `_.first(배열, n)` → `_.take`, `_.where` → `_.filter`, `_.indexBy` → `_.keyBy` 등.
  - Dojo 0.4.3·MooTools 1.0/1.2b2: 관리 화면이 싣지 않음(사이드바·표지 편집은 jQuery UI draggable·droppable·`<dialog>`, 도움말(MOOdalBox)·simple 모드 접는 패널은 jQuery). MooTools(`$`·`$$`·`Element`·`Fx`·`Ajax`, 네이티브 프로토타입 확장)·Dojo(`dojo.*`)를 쓰던 플러그인, 관리 스킨 `custom.js`, `$service['admin_script']` 로 싣는 스크립트는 동작하지 않음 → `jQuery(…)`·네이티브 DOM 으로 수정. 관리 화면의 전역 `$` 는 정의되지 않음(원본은 MooTools `$`, jQuery 는 `noConflict()`).
  - 동작 차이: 끌기는 8px 이상 움직여야 시작(Dojo 는 7px 초과), 플러그인 설정 대화상자는 `<dialog>`(2022년 이후 브라우저, 없으면 일반 요소로 표시), 도움말은 304 응답도 성공으로 처리하고 `evalScripts`·`evalResponse` 옵션을 쓰지 않음(원본 기본값도 꺼짐), 접는 패널을 감싸는 요소가 생기지 않음.
  - 제거한 파일(직접 참조하는 플러그인·스킨(`skin/blog/customize/` 포함)은 수정, 덮어써 올린 설치는 3절 3번 `cleanup_legacy_assets.php`): `jquery-1.11.2.min.js`·`jquery.ui.*-1.11.2.min.js`·`lodash.2.4.1.min.js`, periwinkle `jquery-ui-1.10.3.custom.min.js`, Dojo·MooTools 라이브러리(`resources/script/dojo/` 26개 파일·`mootools10.js`·`mootools.js`·`helpdialog.js`), TinyMCE 4.1 잔여(`plugins/layer`·`media/moxieplayer.swf`·`skins/light`), CodeMirror 5.2.0 데모·테스트·문서·`mode/jade`·`mode/kotlin`, Flash SWF·FLA 와 부속(`resources/script/gallery/`·`jukebox/`·`uploader/`·`clipboardPoter/`, `flash.js`, `detectFlash.inc` — Flash 는 HTML5 로 대체, CHANGELOG.md r1), `jquery.json.js`, periwinkle 의 쓰지 않는 CSS·JS·`less/`.
  - 편집기: TinyMCE 7.9.3(스킨 oxide, 파일 주소 `?v=7.9.3-bd1ab5e` — 배포 직후 옛 파일이 캐시에 있으면 새로고침)·CodeMirror 5.65.21. 커스텀 `TTMLsupport`·`codemirror` 는 7.x API 로 고침, 언어 파일은 그대로.

### 8.5 스킨(`skin/`·`skin/blog/customize/`)

- `skin/blog/customize/` 사용자 사본은 옛 CDN 줄(`//` 주소, SRI 없음, Bootstrap 3.1.1·3.3.2)이 그대로 → https + `integrity` + `crossorigin` 줄로 바꿈(예: lucid). `integrity` 는 파일 해시라 주소·버전을 바꾸면 함께 바꿀 것. lucid 사본은 `//code.jquery.com/jquery.js` 줄 삭제(코어가 jQuery 를 이미 로드, 두 번째 로드가 `jQuery`·`$` 를 덮어씀).
  ```html
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@3.4.1/dist/css/bootstrap.min.css" rel="stylesheet" integrity="sha384-HSMxcRTRxnN+Bdg0JdbxYKrThecOKuH5zCYotlSAcp1+c8xmyTe9GYg1l9a69psu" crossorigin="anonymous">
  <script src="https://cdn.jsdelivr.net/npm/bootstrap@3.4.1/dist/js/bootstrap.min.js" integrity="sha384-aJ21OjlMXNL5UyIl/XNwTMqvzeRMZH2w8c5cRVpzpU8Y5bApTppSuUkhZXN0VxHd" crossorigin="anonymous"></script>
  ```
- periwinkle: Bootstrap JS 3.4.1·bootstrap-select 1.13.18(CDN). `css/bootstrap.css`(3.1.0 IE8 수정본)는 그대로. 스킨을 고쳐 `selectpicker()` 를 쓰면 1.13 은 자체 CSS(`bootstrap-select.min.css`)가 필요하고 마크업이 바뀌었으며 `menuStyle` 옵션 없음.
- 로그인 치환자: 소셜 로그인 버튼은 방문자에게 스킨 치환자 `[##_SocialLogin_##]`(오픈아이디는 `[##_OpenIDLogin_##]`)로만 보임 — 키로그 블록 밖에 둘 것(SKIN_GUIDE 7.1). 스킨에 `[##_SocialLogin_##]` 를 넣었으면 CL_SocialLogin 의 댓글 폼 자동 표시(`comment_form_auto`, 8.6)를 끔.
- 댓글 오픈아이디 전용 블록: `<s_rp_input_form_openid_only>`·`<s_guest_input_form_openid_only>` 블록이 있으면 쓸 수 없는 방문자에게 입력 폼 대신 이 블록을 보임(없으면 폼 그대로, 저장은 서버가 막음).
- 스킨 캐시: 서비스 주소마다 따로 만듦. 위 블록·치환자를 넣은 뒤에는 스킨 캐시를 비워야 표시됨 — 블로그 설정의 공개 정책 저장(댓글 오픈아이디 전용 설정 저장이 성공하면 그 블로그의 스킨 캐시를 비움) 또는 3절 5번. 비우기 전에도 화면은 정상(기존 폼 유지).
- 이름이 `-` 인 스킨도 미리보기 가능.

### 8.6 외부 서비스·IdP

- OpenID 2.0: 로그인·라이브러리(phpopenid)·클래스 제거 → 기존 OpenID 2.0 사용자는 인증 불가. OIDC·소셜 로그인은 설정했을 때만.
- OIDC(CL_OpenID): issuer 와 그 discovery 문서의 token_endpoint·jwks_uri 는 https·포트 443·공인 IPv4 주소만 씀 — 사설망·443 외 포트(8443 등)·IPv6 전용 IdP 는 쓸 수 없음(로그인 시작이 `discovery` 오류). discovery·token·jwks 주소가 리다이렉트되면 실패. 프록시는 8.1 `useLoginProxy`. curl 이 없는 서버의 요청 시간 제한은 10초.
- 소셜 로그인(`CL_SocialLogin`, 원본에 없는 플러그인):
  - Redirect URI 스킴을 신뢰 프록시 판정(플러그인이 신뢰한 연결·`trustedProxies` 에 속한 연결의 `X-Forwarded-Proto`) → 서버 변수 `HTTPS` → `service.useSSL` 순으로 정함. https 로 운영하면서 `useSSL` 을 켜지 않았던 설치는 콜백 주소가 `http://` 에서 `https://` 로 바뀌므로, 제공자 앱에 http 주소를 등록했다면 설정 창에 표시되는 주소로 다시 등록. 호스트·경로는 바뀌지 않음.
  - 제공자 주소가 고정이라 환경 변수 프록시 문제(8.1 `useLoginProxy`)만 OIDC 와 같음.
  - 댓글·방명록 입력 폼에 로그인 버튼을 자동으로 넣음(설정 `comment_form_auto`, 기본 켬). 카카오는 동의 항목에서 `account_email` 을 뺐음.
- 서버가 보내는 요청: 댓글 답글 알림(부모 댓글의 홈페이지 주소)·트랙백 보내기·트랙백 답장 주소 읽기·EAS 스팸 검사 서버(`fas_domain`)·FM_Textile 이미지 크기 조회는 http/https·포트 80/443·공인 IPv4 주소로만 보냄.
  - 인트라넷·사설 주소(서비스 주소가 사설 IP 로 해석되는 환경의 같은 서비스 다른 블로그 포함), 80/443 외 포트, IPv6 전용 호스트는 대상이 되지 않음 — 트랙백 보내기·답장은 실패 응답, 답글 알림은 보내지 않고 큐에서 지움.
  - 사설망·다른 포트의 자체 FAS 서버는 로컬 검사로 동작(설정 창에 안내).
  - Textile 이미지는 자기 블로그 첨부는 첨부 폴더 파일에서 크기를 읽고, 상대 주소·`//host` 주소·다른 스킴은 너비·높이 속성이 붙지 않음(이미지 표시는 같음), 원격 조회는 글 하나에 10초까지.
  - 답글 알림 큐: 각 블로그 방문이 자기 블로그 알림만 보내고 지움(다른 블로그 방문으로 지워지던 알림이 남음). 방문이 적은 블로그는 알림이 늦게 나감.
- 링크 사이트 정보: 링크의 사이트 정보 읽기(`get_remotefile()`)는 http/https·포트 80/443·공인 주소만 허용하고 리다이렉트 3회·응답 1MB·10초로 제한, https 지원. 내부망 주소는 읽지 않음(링크 추가는 됨).
- 리더 피드·OPML: 내부·사설 주소와 80/443 이 아닌 포트의 피드는 추가 실패(오류 2, OPML 은 오류 1). 이미 저장된 그런 피드는 갱신이 실패하고 modified 가 0 이 됨. 같은 서버의 다른 블로그 피드를 내부 이름으로 구독했다면 공인 도메인·80/443 주소로 다시 구독(판정은 피드 주소의 호스트·포트만 봄). OPML 주소가 빈 값이면 404, OPML 파일 필드가 없거나 배열이면 404. `update/{id}` 는 현재 블로그 피드만 갱신. alternate 링크는 3단계까지.
- IPv6(보류): 위 검사와 `get_remotefile()`·리더 피드는 IPv4 만 해석함. IPv6 전용 서버는 보류 중인 지원 대상.

### 8.7 관리 화면·블로그 동작

- 로그인:
  - 실패 잠금: 같은 계정이 `loginFailureLimit`(8.1)번 실패하면 마지막 실패부터 15분 동안 맞는 비밀번호로도 로그인되지 않음. 데이터 전체 삭제·비밀번호 변경에서 현재 비밀번호를 `passwordConfirmFailureLimit`번 틀리면 로그아웃.
  - 재인증(8.4): 해당 작업은 현재 비밀번호를 다시 입력해야 함. 세션당 5회 틀리면 로그아웃(path 모드는 같은 세션의 다른 블로그도 로그아웃). 비밀번호가 없는 계정(소셜·OIDC 로그인만 쓰는 계정)은 이 작업을 할 수 없음.
  - 관리자 로그인 화면(`/login`): OpenID 블록은 `CL_OpenID` 를 켜고 OIDC 를 설정했을 때만 보이며, 주소 입력식 OpenID 식별자 입력란과 '오픈아이디 저장' 쿠키는 없어짐. 소셜 로그인 버튼은 자동으로 들어감. 댓글·방명록 패널의 [로그인] 링크도 OIDC 를 설정했을 때만 나옴.
  - 로그인 뒤 이동(원본 동작 변경): 주소창으로 연 로그인 화면(`/login`·`/login/`·`/login/index.php`), `requestURI` 없는 로그인 링크·다른 사이트에서 연 로그인 화면에서 로그인하면 블로그 첫 화면으로 감(원본 single·domain 루트 설치는 로그인 화면이 자신으로 계속 되돌려 `ERR_TOO_MANY_REDIRECTS`). `?requestURI=` 가 붙은 로그인(관리 화면을 로그인 없이 열 때 등)은 전과 같이 그 화면으로 돌아감.
  - 인증 토큰(초대 메일 링크·관리 화면 임시 암호): 기본 7일 뒤 만료(`authTokenLifetime`, 8.1), 로그인에 한 번 쓰면 지워짐. 메일 링크는 로그인 입력란만 채우고 로그인 버튼으로 진행. 현재 비밀번호 없이 변경은 토큰으로 로그인한 세션에서 한 번. 만료된 초대는 초대 메일을 다시 보내면 새 토큰. 발급 시각 없는 기존 토큰은 처음 확인한 시각부터 계산(발급 시각은 `UserSettings` `AuthTokenIssued` 행, 테이블 구조 변경 없음).
  - '암호 초기화': 원본(upstream 동일)은 메일이 발송되지 않아 동작하지 않던 기능 → 동작함. 이 블로그 회원의 저장된 이메일로 인증 토큰 링크(초대 메일과 같은 형식, `authTokenLifetime`·1회용, 링크 주소는 설정 도메인 기준)를 보내고, 같은 계정은 5분 안에 다시 보내지 않음. 회원 여부와 관계없이 같은 안내, '암호 초기화' 선택란은 로그인하지 않은 상태에서 로그인에 실패하면 표시(원본은 이 블로그 회원 이메일일 때만). 메일 발송 설정이 필요.
  - 세션은 접속 주소에 묶이므로(`Textcube.Control.Session.php` 85·206) 신뢰 프록시(8.1·8.2) 플러그인을 처음 켜거나 장치를 바꾸면 로그인이 한 번 끊길 수 있음(화면에도 안내).
- CSRF·화면: 배포 직후 열려 있던 관리 화면, 로그인(소셜·OIDC 포함) 뒤 다른 탭에서 보낸 요청은 412 — 새로고침하면 됨. 관리 화면·로그인 폼은 다른 출처 iframe 에 표시되지 않음(`X-Frame-Options: SAMEORIGIN`). multipart 요청이 `post_max_size` 를 넘으면 412. path 모드에서 origin 까지만 보내는 Referer 는 Referer 만 보는 경로(comment/add, addopenid, comment/comment, stream.php, trackback/delete, GoogleMap)에서 412, Referer 를 보내지 않는 브라우저는 토큰으로 통과. 멀티블로그는 domain 모드 권장(path 모드는 같은 출처 다른 블로그 스크립트를 막지 못함). 사이드바·표지 관리 링크와 글 삭제 이동은 JavaScript 로 POST 를 보내므로 JavaScript 를 끄면 405.
- 요청 형식: 입력 변수가 너무 많으면 413, 본문이 있는 POST 의 Content-Type 이 허용 목록 밖이면 415, 형식이 깨지면 400. `text/xml` 은 `/api` 에서만 받음. 본문 없는 POST 는 그대로 받음.
- 신원·댓글:
  - 댓글·방명록 openid 값은 `oidc:{issuer}#{sub}`·`{provider}:{id}` 형식. 열 길이 128자를 넘는 값은 잘려 저장.
  - 신원 표시: OIDC 신원은 발급자, 소셜 신원은 공급자 이름으로 표시하고 식별자 원문은 화면·링크·입력값에 넣지 않음. 이름 claim 이 없으면 이메일 대신 공급자 이름으로 표시. 작성자 제목의 위장 구별 표지용 비밀값이 `ServiceSettings` `identityBadgeSecret` 행으로 처음 표시할 때 만들어짐.
  - 계정 연결: 계정 화면의 오픈아이디·소셜 계정 연결은 연결을 시작한 뒤 고른 공급자로 새로 인증한 신원만 받음. 공급자 사용자 식별자(OIDC sub·소셜 id)가 출력 가능한 ASCII 1~255자가 아니면 로그인 실패.
  - 댓글 오픈아이디 전용: 오픈아이디(OIDC)·소셜 로그인 신원만 통과하고, 화이트리스트에 걸리는 홈페이지를 입력해도 건너뛰지 않음(스킨 블록은 8.5).
  - 소셜 로그인으로 쓴 댓글은 '소셜 계정으로 글쓰기'로 저장되고 이름은 서버가 정하며 비밀번호는 비어 있음. NG 에서 쓴 OpenID 댓글은 비밀번호를 `''` 로 저장 → 원본 코드로는 그 댓글 수정·삭제 불가(6절).
  - 댓글·방명록 홈페이지의 `javascript:` 주소는 빈 값.
- 첨부:
  - 내려받기(`/attachment/{이름}`): 단일 바이트 범위 206/416·`Accept-Ranges`·`filename*` 지원, 여러 범위는 전체 200. 다운로드 수는 HEAD·이어받기(0 이 아닌 위치에서 시작하는 부분 요청)를 세지 않음(원본은 모든 요청을 셈).
  - 이름 어디에든 실행 확장자가 있으면 이름을 바꿈(`a.php.jpg` 등).
  - 그림: svg·webp·avif 를 그림으로 다룸. 그림 확장자 업로드는 크기·가로세로 상한과 실제 형식이 확장자와 맞는지 검사. SVG 는 정화한 뒤 저장하고, 정화에 실패하면 `.xxx` 이름의 일반 파일로 저장. 가로세로 4096 을 넘는 그림은 썸네일 대신 원본을 씀.
  - 편집 화면 첨부 목록: 업로드·삭제·목록 새로 고침이 실패하면 오류 메시지를 띄우고 목록을 다시 읽음(실패 응답은 HTTP 500). 첨부 처리는 그 블로그의 글이고 편집자(`group.editors`) 이상이거나 작성자일 때만 허용(아니면 403). 임시 첨부는 8.3.
- 설정 값:
  - 시간대: PHP 시간대 식별자만 저장(블로그 설정·서버 설정, 백업 가져오기는 식별자가 아니면 현재 설정 유지). DB 에 남은 식별자가 아닌 블로그 시간대는 서비스 기본 시간대로 동작 → 관리 화면에서 다시 선택.
  - 위젯 `int` 매개변수: 숫자가 아니면 그 매개변수를 빼고 저장, 숫자는 정수로 저장(`1e3` → 1000).
  - 위도·경도가 숫자가 아니면 NULL.
  - 새 임시 비밀번호·보호글 기본 비밀번호는 12자.
  - 태그 목록 개수: 태그 페이지의 태그 개수는 블로그 설정(`/owner/setting/blog`, `tagsOnTagPage`), 서비스 상한은 `/control/server`(`maxTagsOnTagPage`, 기본 2000, 1~100000, 범위 밖 값은 저장 거부, 읽을 때 상한 적용). 새 문구는 번역 없음. 태그 캐시 갱신과 `renameTag()` 결함 3개 수정.
- 글·목록:
  - 글 편집 화면: 자동 저장은 내용이 바뀌었을 때만 저장. 오류는 알림 창(alert) 대신 화면 메시지(`PM.showErrorMessage`)로 표시(확인 창 confirm 은 유지).
  - 작성자 조건: 글관리(editors) 권한 없는 구성원에게 다른 구성원의 비공개·예약 글이 목록·태그·키워드·보관함에 나오지 않음. 비공개 글의 댓글·트랙백은 방문자 검색 결과에서 빠짐. writers 의 `/search` 댓글·트랙백은 자기 글 것만. 글관리 권한이 없는 구성원의 태그 목록·무작위 태그는 초안만 있는 글의 태그를 세지 않음.
  - 링크 분류 정리 `DELETE` 는 분류 목록이 있을 때만 실행(원본은 빈 목록이면 MySQL·PostgreSQL 문법 오류, SQLite 는 전체 삭제).
  - Moblog: 본문 escape, MMS 헤더로만 통과한 메일은 비공개 별도 글.
- 캐시·출력:
  - 페이지 캐시: 파일 이름이 sha256 으로 바뀌어 `cache/pageCache/<블로그>/<숫자>.cache` 파일은 더 이상 쓰이지 않음 — 지워도 되고 관리 화면 캐시 비우기로도 정리됨. 글관리(editors) 권한이 없는 구성원은 사용자별 파일을 씀(파일 수 증가).
  - 사이트맵: `/sitemap` 은 공개 블로그의 공개 글만 `application/xml` 로 냄. 등록하지 않은 Host·비공개 블로그·잘못된 페이지 번호는 404.
  - 피드: 등록하지 않은 Host 의 요청은 피드 캐시를 쓰지 않고 매번 만듦. 피드 본문은 모바일 요청에도 데스크톱 화면으로 만듦.
  - 모바일 화면: 블로그의 모바일 화면 설정이 켜져 있으면 모바일 기기에서 `?mode=desktop` 을 고른 뒤 브라우저 세션 동안 `TC_DISPLAYMODE=desktop` 쿠키(HttpOnly·SameSite Lax)로 데스크톱 화면을 유지. `?mode=mobile` 이면 쿠키를 지움.
- GoogleMap 플러그인: 캐시에 없는 위치는 관리자 외 방문자에게 서울시청 좌표로 표시, 위치 캐시 기록은 관리자(블로그 관리자·글쓴이)만 → 플러그인 사용 시 관리자가 지역로그를 한 번 열어 캐시. 위치·글 제목·마커 제목/설명은 escape 해 글자 그대로 표시(값에 넣은 HTML 은 태그로 동작하지 않음). 위치 캐시 기록 요청은 같은 블로그 화면에서 보낸 것만 받음(Referer 가 없으면 412, 관리 화면과 같음).
- 백업 가져오기(7절):
  - 형식에 맞지 않는 첨부·배너 이름은 `난수.확장자`(부적합·실행형 확장자는 `xxx`)로 바뀌고 본문 참조도 바뀜. 빈 이름은 건너뜀.
  - 한 글에 같은 이름 첨부가 여럿이면 본문은 먼저 가져온 첨부를 가리키고 나머지는 참조 없이 남음(완전히 같은 중복은 사본이 하나 더 생김).
  - 블로그 번호가 다른 `attach/{번호}/{이름}` 경로는 이번에 가져온 첨부면 `[##_ATTACH_PATH_##]/{이름}` 로 변환. 주소(호스트)가 붙은 경로는 호스트가 이 블로그 주소(현재 서비스 도메인·서비스 주소·현재 이름 도메인형·현재/백업 2차 도메인·백업 이름 `원래 이름.`)일 때만 변환하고 다른 호스트는 유지.
  - 다른 서버의 백업에서 단일·경로형 블로그의 원래 서비스 도메인이 붙은 절대 경로는 백업에 그 도메인이 없어 유지됨(주소 없는 경로는 변환). 도메인형 `원래 이름.` 으로 시작하는 호스트는 모두 이 블로그로 봄.
  - 변환하지 않음: `/attachment/{이름}` 절대 주소의 호스트, 같은 번호 경로에서 다른 글이 원래 이름으로 가리키는 바뀐 이름, 공백·`,;=` 가 든 이름.

## 9. 점검 목록

- [ ] DB 덤프·`attach/`·`config.php`·`.htaccess`·`skin/blog/customize/`·추가 스킨·플러그인 백업
- [ ] PHP 버전·확장, 서드파티 플러그인·스킨 동작 확인(화면 설정 저장 키 제한, 8절)
- [ ] 서드파티 플러그인·스킨의 jQuery 3·Lodash 4 호환, Dojo·MooTools 사용(관리 스킨 `custom.js`·`admin_script` 포함), 제거한 옛 자산 파일 직접 참조(8.4), `skin/blog/customize/` CDN 줄 교체(8.5)
- [ ] NG 파일 복사(유지 항목은 덮어쓰지 않음), `php cleanup_legacy_assets.php --apply`(3절 — phpopenid·StatGraph `count/`·옛 정적 자산·CodeMirror node.js 파일, 건너뛴 파일은 직접 삭제)
- [ ] OPcache 사용 시 PHP-FPM·웹서버 재시작
- [ ] `{블로그 주소}/checkup` 실행 — 모든 단계 성공, 점검 안내 사라짐 (모든 블로그의 휴지통 댓글·트랙백도 비워짐, 3·8절)
- [ ] 블로그 시간대가 의도한 값인지 확인(식별자가 아닌 값은 서비스 기본 시간대로 동작, 8절)
- [ ] 리버스 프록시 뒤면 `$service['trustedProxies']` 설정
- [ ] DB SSL 사용 시 `ssl_verify`·`ssl_ca`·인증서 파일 권한 확인(4절)
- [ ] PDO 드라이버만 있는 서버면 `$database['driver'] = 'pdo'`(Cubrid·MySQL·MariaDB·PostgreSQL·SQLite3, 4·8절)
- [ ] 인증 토큰 유효 시간(`authTokenLifetime`) 확인, GoogleMap 사용 시 관리자로 지역로그 한 번 열기(4·8절)
- [ ] 서비스 주소(`serviceurl`)와 웹서버의 `HTTP_HOST` 전달 확인 — Host 헤더 없는 요청 대비(8절)
- [ ] 업그레이드 전 임시 첨부(`parent = 0`) 정리, 태그 목록 개수(`/owner/setting/blog`·`/control/server`) 확인(8절)
- [ ] (선택) `passwordHash` 변경: PostgreSQL·Cubrid 열 먼저 넓히기 → 설정 → checkup
- [ ] 로그인·글·첨부·댓글·피드·관리 화면 확인
- [ ] 플러그인: fetch/XHR 로 관리 동작을 보내면 CSRF 토큰(`X-CSRF-Token`) 추가, 배열(`x[]=`)로 보내던 값은 쉼표 문자열로(8절)
- [ ] 리더: 내부 주소·비표준 포트로 구독한 피드가 있으면 공인 주소로 다시 구독(8절)
- [ ] 멀티블로그 path 모드면 domain 모드 전환 검토(8절)
- [ ] 배포 후 열려 있던 관리 화면은 새로고침(412 대비, 8절)
- [ ] 상태 변경 주소(8.4)를 GET 으로 부르는 북마크·외부 스크립트·플러그인이 있으면 POST + 토큰으로 바꾸고, 관리 화면은 JavaScript 를 켠 브라우저로 사용
- [ ] 비밀번호 없이 소셜·OIDC 로그인만 쓰는 계정은 e-mail·API 키·블로그 주소·연결 변경을 할 수 없음을 확인, 이 작업을 부르는 외부 스크립트·플러그인은 `confirmativePassword` 추가(8.4·8.7)
- [ ] https 를 끝내는 프록시·로드밸런서·Cloudflare 뒤면 `trustedProxies` 또는 신뢰 프록시 플러그인에 등록(또는 `$serviceURL` 고정)했는지 확인, 플러그인을 켠 직후 다시 로그인(8.1·8.2·8.7)
- [ ] 소셜 로그인(`CL_SocialLogin`)을 https 로 운영하면 설정 창의 Redirect URI 가 `https://` 인지, 제공자 앱에 같은 주소가 등록되어 있는지 확인(8.6)
- [ ] 스킨에 `[##_OpenIDLogin_##]`·`[##_SocialLogin_##]`·오픈아이디 전용 블록을 넣었으면 키로그 블록 밖인지, 스킨 캐시를 비웠는지 확인(8.5)
- [ ] `/owner/setting/domain/default`·`primary`·`secondary`·`/owner/entry/detach` 를 GET 으로 부르던 외부 스크립트·플러그인은 POST + 토큰으로, 도메인 세 곳은 `confirmativePassword` 도 함께(8.4)
- [ ] 사설망·80/443 외 포트의 자체 FAS 서버, 같은 서비스 안(서비스 주소가 사설 IP 로 해석되는 환경)으로 보내던 트랙백·답글 알림이 있으면 공인 주소·80/443 으로 옮기거나 로컬 검사로 충분한지 확인(8.6)
- [ ] CL_OpenID OIDC 를 쓰면 issuer 가 https·443·공인 주소이고 discovery 주소가 리다이렉트되지 않는지, 서버가 환경 변수 프록시 없이 밖으로 나갈 수 있는지(프록시가 필요하면 `useLoginProxy`·PHP-FPM `env[https_proxy]`) 확인(OIDC·소셜 로그인, 4절·8.1·8.6)
- [ ] 업그레이드 뒤 `/checkup` 실행(점검 개정 5) (8.3)
- [ ] 기존 `.htaccess` 에 `.user.ini`·스킨 `index.xml` 차단 규칙을 옮김 (8.2)
- [ ] 로그인 실패 한도·비밀번호 확인 한도를 사용자에게 안내 (8.7)
- [ ] 프록시 Host 보존·비표준 포트·별칭 설정 확인 (8.1·8.2)
- [ ] `<topMenu>control</topMenu>` 플러그인 사용 여부 확인 (8.4)
- [ ] 플러그인 파일을 바꿔 배포하면(직접 고친 플러그인·서드파티 플러그인 갱신 포함) 남긴 무결성 기록과 달라 그 플러그인이 차단될 수 있음 → 플러그인 화면(목록 보기)의 '다시 검사하고 풀기'로 풀거나, 서비스 관리자가 '무결성 검사 없이 항상 허용'을 고름
- [ ] 업그레이드 뒤 `/checkup` 실행 — 기존 `.htaccess` 에 `plugins/`·`attach/` 실행 차단 규칙이 더해졌는지 확인, nginx 는 `setup_nginx.txt` 의 규칙을 옮김(8절)
- [ ] https 운영이면 `useSSL`·`hsts` 설정 검토(4·8절)
- [ ] 프록시 뒤에서 IP 잠금(`loginLockScope = 'ip'`)을 쓰면 `trustedProxies` 를 먼저 설정(4절)
- [ ] 계정 비밀번호로 API 를 쓰던 도구가 있으면 API 키를 정함(4절 `allowPasswordAsAPIKey`)
- [ ] 플러그인 켜기·끄기를 GET 으로 부르던 외부 스크립트는 POST + 토큰으로(8절)
- [ ] 스킨에 `[##_SocialLogin_##]` 가 있으면 CL_SocialLogin 의 댓글 폼 자동 표시를 끔(8절)
- [ ] 배포 뒤 ED_tinyMCE·FM_Modern 이 차단되면 '다시 검사하고 풀기'(8절)
- [ ] (r3 이하 NG 에서 올릴 때) 10절 — 기존 `.htaccess` 의 `.user.ini`·캐시 차단 줄 바꾸기, `/checkup`, ED_tinyMCE·CL_Moblog·ST_MediaLog '다시 검사하고 풀기'
- [ ] 이모지를 쓰려면(MySQL 5.6+·MariaDB 10.0+) DB 백업 뒤 `/checkup` 의 '데이터베이스 문자셋(이모지)' 에서 변환, 중복 목록이 나오면 먼저 정리(10절 3번)
- [ ] 분류 최대 깊이(분류 관리, 기본 3) 확인. 새 백업을 r3 이하 판으로 가져오지 않음(10절 5번)
- [ ] 원본으로 되돌릴 가능성이 있으면 6절 먼저 확인

## 10. r4 에서 달라진 점(다른 NG 판에서 옮겨 올 때 포함)

원본에서 오는 경우는 1번의 캐시 차단 줄과 3·5·6번이, 다른 Textcube-NG 판(r3 이하)에서 옮겨 오는 경우는 모두 해당한다.

1. 기존 `.htaccess`(자동으로 바뀌지 않음)
   - `.user.ini` 차단: r3 의 `<Files ".user.ini">` 블록(안의 `Require`·`Order`)은 `AllowOverride FileInfo` 만 허용한 Apache 에서 모든 요청을 500 으로 만든다. 이 블록을 지우고 attach PHP 차단 줄 뒤에 다음 줄을 넣는다.
     `RewriteRule ^(.+/+)?\.user\.ini(/|$) - [NC,F,L]`
   - 캐시 차단: `RewriteRule ^(cache)+/+(.+[^/])\.(cache|xml|txt|log)$ - [NC,F,L]` 줄이면 `(.+[^/])` 를 `(.*[^/])` 로 바꾸면 한 글자 이름의 캐시 파일도 막힌다(새 설치 템플릿은 r4 에서 바뀜).
   - IIS(`web.config`)는 설치 마법사의 IIS 템플릿과 같은 줄로 맞춘다.
2. `/checkup` 을 한 번 실행한다(3절 5번). MySQL 8(STRICT)에서 표를 다시 만드는 단계가 이제 성공한다.
3. 이모지(4바이트 문자) — MySQL·MariaDB 만
   - 새 설치는 MySQL 5.6+·MariaDB 10.0+ 이면 처음부터 utf8mb4 다. 이미 설치한 사이트는 자동으로 바뀌지 않는다. MySQL 5.0~5.5·MariaDB 5.1~5.5 는 utf8 그대로라 이모지를 저장하지 못한다(버튼 대신 안내만 보임).
   - 바꾸려면 **DB 를 백업한 뒤** 서비스 관리자로 로그인해 `{블로그 주소}/checkup` 의 '데이터베이스 문자셋(이모지)' 절에서 버튼을 누른다. 요청 하나에 표 하나씩 바꾸며 '끝날 때까지 이어서' 를 고르면 자동으로 이어 간다. 중간에 끊겨도 다시 누르면 남은 표부터 잇는다. 모든 표가 끝나면 다음 요청부터 이모지를 저장한다.
   - 되돌리는 화면은 없다. 되돌리려면 변환 전 백업을 복원한다.
   - 긴 주소·필터·태그는 앞 191자만 비교하고, 새 콜레이션은 대소문자·악센트·`ß`/`ss`·전각/반각 차이를 같은 값으로 본다. 그렇게 같아지는 값이 있으면 버튼 대신 '변환 전에 정리할 중복' 목록이 나온다 — 관리 화면에서 그 태그·링크·필터·계정 이름을 고치거나 지운 뒤 다시 연다(자동으로 고치지 않음).
   - 표를 바꾸는 동안 그 표에 쓰기가 막히고, 글이 많은 사이트는 요청마다 몇 초가 더 걸린다. 변환 중(표시가 켜지기 전)에 쓴 이모지는 잘린다.
   - 플러그인이 등록하지 않은 표는 바꾸지 않고 목록만 보인다. 그런 표의 열에 이모지 조건을 거는 질의는 콜레이션 혼합 오류(1267)가 날 수 있다.
4. 플러그인 '다시 검사하고 풀기': CL_Moblog·ST_MediaLog(분류 다단계로 파일이 바뀜)를 켜 둔 사이트는 덮어쓴 뒤, ED_tinyMCE 는 `cleanup_legacy_assets.php` 로 CodeMirror node.js 파일을 지운 뒤 무결성 검사에 한 번 막힌다 → 플러그인 화면(목록 보기)에서 '다시 검사하고 풀기'.
5. 분류(글갈래)
   - 분류 관리에 '분류 깊이'(기본 3, 2~10)와 '상위 분류' 선택 상자가 생겼다. 위·아래 단추는 같은 단계 안의 순서만 바꾼다(전에는 단계도 바꿈).
   - 하위 분류가 있거나 글(임시 저장 글 제외)이 있는 분류는 지울 수 없다.
   - 분류 경로(`상위/중간/하위`)가 255자를 넘으면 만들거나 이름을 바꿀 수 없다.
   - r4 에서 만든 백업에는 분류가 깊이만큼 들어간다. 이 백업을 r3 이하 판(또는 원본)으로 가져오면 3단 이하 분류가 빠지고 그 글은 분류 없음이 된다.
   - 분류 RSS 번호 주소(`/rss/category/<번호>`)의 제목에 분류 이름이 들어간다(ATOM·경로 주소와 같아짐).
   - 다른 언어 화면에서는 새 문구(분류 깊이·상위 분류·body id 안내 등)가 한국어로 보인다(번역 없음).
6. 분류 body id: 영문·숫자·`_`·`-` 20자 이하만 저장한다. 예전에 다른 문자로 넣은 값은 그대로 출력되지만(escape 됨) 고치려면 규칙에 맞는 값으로 다시 넣는다. 이름과 함께 저장하면 이름은 저장되고 body id 만 거부된다.
7. 테이블 접두어: 설치 때 비우면 `tc_` 를 쓴다. 예전에 빈 접두어로 설치한 PostgreSQL·SQLite·Cubrid 사이트는 `setup.php` 로 다시 설정하면 빈 칸이 `tc_` 로 바뀌어 기존 표를 찾지 못한다 — 이런 사이트는 다시 설정하지 말고 `config.php` 를 직접 고친다.
8. 손님 이름·홈페이지 기억: 전 판은 쓰는 쿠키 이름과 읽는 이름이 달라 기억이 동작하지 않았다. 업데이트 뒤 손님이 댓글을 한 번 쓰면 그때부터 기억한다. 예전 쿠키(`guestName`·`guestHomepage`)는 30일 뒤 만료된다.
9. 그 밖: 휴지통 화면에서 줄바꿈이 든 분류 이름은 줄바꿈 없이 보인다. 블로그 API 등으로 글 분류·공개 값을 바꾸면 블로그 전체 분류 글 수를 다시 세므로 글이 아주 많은 블로그는 그 저장이 느려질 수 있다.

## 11. PHP 5.5 ~ 7.3 판 주의

- 설치 마법사는 PHP 5.5.0 미만에서 멈춘다. PHP 7.4 이상은 이 판의 지원 범위 밖이다(설치는 막지 않지만 시험하지 않음). PHP 8.5 를 쓸 수 있으면 PHP 8.5 패키지를 쓴다.
- `config.php` 새 키(4절 표 밖, 이 판 전용):

  | 키 | 없을 때 기본값 | 내용 |
  |----|----------------|------|
  | `$service['tls_cafile']` | `''` | PHP 5.5 가 다른 사이트 HTTPS 인증서를 검증할 CA 묶음 파일 경로(예: `/etc/ssl/certs/ca-certificates.crt`). 비면 흔한 시스템 경로를 찾고, 못 찾으면 HTTPS 요청을 거부(오류 로그에 안내) |
  | `$service['tls_capath']` | `''` | CA 인증서 디렉토리(`tls_cafile` 대신) |

- 난수원: PHP 5.5·5.6 은 `/dev/urandom` 을 읽거나 mcrypt 확장이 있어야 로그인·폼 토큰을 만든다. `open_basedir` 를 쓰면 `/dev/urandom` 을 허용 목록에 넣는다.
- MySQL 8.0:
  - PHP 7.2.8 미만은 계정 인증 방식이 `mysql_native_password` 여야 한다.
  - PHP 5.5·5.6 은 서버 기본 문자셋(utf8mb4_0900_ai_ci)을 몰라 연결하지 못한다(오류 2054) → MySQL 서버 설정에 `character-set-server=utf8`(바꾼 뒤 서버 재시작). PHP 7.0 ~ 7.3 은 필요 없다.
- mysqlnd 가 없는 PHP 는 준비 문장 결과를 `bind_result()` 로 읽는다(동작 같음, 느릴 수 있음).
- PDO: PHP 5.5.0~5.5.20 은 다중 문장 차단이 없다. DB SSL 서버 인증서 검증(`ssl_verify`)은 지원하지 않는 PHP 판에서 켜면 연결하지 않는다.
- 이모지: MySQL 5.6+·MariaDB 10.0+ 만. 그보다 낮은 서버는 checkup 에 안내만 나오고 변환 버튼이 없다.
- 메일은 PHPMailer 5.x(이 판에 들어 있는 고친 판)로 보낸다. `library/contrib/phpmailer/` 를 지우지 말 것.
- 보안 한계는 [SECURITY.md](SECURITY.md) '[NOTE] PHP 5.5 판 한계'.

---

<sub>Modifications documented herein by @deokio (2026), performed with AI assistance (Anthropic Claude) under human review.
No additional copyright is asserted. Licensed under GPL (same as the rest of the project).</sub>
