<!-- Korean translation of README.md -->
Textcube-NG: Brand Yourself - 개인화된 웹 퍼블리싱 플랫폼
==========================================================

**버전**: `1.10.10+php85.r3` · [English](./README.md)

> ## ⚠️ 보안 공지 — 배포 전 반드시 확인하세요
>
> **기본 설정에서는 HIGH 항목 한 건이 남아 있습니다: 비밀번호를 솔트 없는 MD5로 저장합니다**
> (기존 Textcube 데이터와 drop-in 호환을 위해 원본 방식을 유지).
> `password_hash()` 로 저장하려면 `config.php` 의 `$service['passwordHash']` 를
> `'multi'`(새로 설정·변경하는 비밀번호만) 또는 `'migrate'`(로그인할 때 MD5 비밀번호도 변환)로 두세요.
> 이렇게 저장된 계정은 원본 Textcube에서 로그인할 수 없으므로, DB를 되돌리기 전에
> [MIGRATION.md](./documents/MIGRATION.md) 5·6절을 확인하세요.
>
> 공개 운영 환경에서는 위와 같이 `password_hash()` 저장을 켜고 HTTPS로 서비스하세요.
>
> ## ℹ️ 프로젝트 현황 및 출처
>
> 이 프로젝트는 GPL 라이선스 하에 Needlworks / Tatter Network Foundation (TNF)이
> 개발한 [Textcube](https://github.com/Needlworks/Textcube)의 **비공식 커뮤니티 fork**입니다.
> **Needlworks/TNF와 제휴 관계에 있지 않으며, 공식 승인을 받은 것도 아닙니다**.
>
> 이식 작업은 주로 PHP 업그레이드 문서를 따른 기계적 변환이며,
> **AI 코딩 어시스턴트(Anthropic Claude)의 도움을 받아 인간의 검토 하에 수행**되었습니다.
> 관리자는 **이러한 수정에 대해 추가적인 저작권을 주장하지 않습니다**. 원본 Needlworks/TNF
> 저작권은 GPL 요건에 따라 모든 소스 파일 헤더에 보존되어 있습니다.
> [NOTICE.ko](./documents/NOTICE.ko)를 참조하세요.

## 설명

Textcube는 경험, 아이디어, 의견, 생각을 기록하고 공유하기 위한 오픈소스 도구입니다.

'Tattertools Project'의 다른 솔루션과 호환되는 XML 형식(TTXML)으로 개인 데이터를 가져오거나 내보낼 수 있습니다.

* 한국어/일본어/중국어를 포함한 비 라틴 문자 강력 지원
* 다양한 설치 환경(웹서버, 데이터베이스, 언어) 지원
* 확장 가능한 플러그인 및 스킨 아키텍처 제공
* 개인 블로그부터 블로그 서비스 플랫폼까지 확장 가능
* 'Project Tattertools'의 다양한 플랫폼에서 지원하는 TTXML 형식으로 손쉬운 백업 및 복원 지원

## 역사

Textcube는 2004년 JH가 시작한 온라인 퍼블리싱 플랫폼 'Tattertools'를 기반으로 합니다. 2005년 TNC가 개발을 맡았고, 2006년에 GPL 라이선스로 공개되었습니다. Tatter Network Foundation (TNF)은 2006년 4월부터 TNC와 함께 Tattertools를 개발했습니다. Needlworks/TNF는 2006년 11월부터 Tattertools 개발에 전념하며 후속 프로젝트로 'Project S2'를 시작했습니다. 'Textcube'라는 이름은 YJ Park이 지었으며, 2007년 8월에 처음 출시되었습니다.

## Textcube-NG

Textcube-NG는 업스트림이 마지막으로 지원한 PHP 5.6에서 PHP 8.5로 코드베이스를 이식한,
Textcube 1.10.10의 커뮤니티 유지 관리 후속 프로젝트입니다. PHP 7.4·8.2·8.4 이식본도 함께 유지합니다.
PHP 8.4 이식본과 이 PHP 8.5 패키지는 PHP 8.5 에서 deprecated 된 부분의 대응만 다릅니다. 이 패키지는 PHP 8.5
전용 문법을 쓰지 않으므로 PHP 8.4 에서도 수정 없이 쓸 수 있습니다(PDO 드라이버 상수는 PHP 8.4 에서 도입된
`Pdo\Mysql`·`Pdo\Pgsql` 을 씁니다).

### 원본 Textcube 1.10.10과 달라진 점

- **PHP 8.5 호환**: 코드 전체의 deprecated·제거된 문법 수정(PHP4 방식 생성자, 동적 프로퍼티,
  `${}` 문자열 보간, `strftime()`, 중괄호 배열 접근, mysqli 절차형 API 등).
- **보안 수정**: r1~r3 에서 해소한 항목(권한, CSRF, SQL 삽입, XSS, SSRF, 세션, 업로드 파일 실행 등)은
  [SECURITY.md](./documents/SECURITY.md) 참조.
- **라이브러리**: PHPMailer 5.x → 6.9(기존 메일 호출은 그대로 동작), OpenID 2.0 제거·OpenID Connect 추가
  (선택, 기본 꺼짐), jpgraph → 인라인 SVG 차트, jQuery 3.7.1·jQuery UI 1.13.3·Lodash 4.18.1·
  TinyMCE 7.9.3·CodeMirror 5.65.21, Dojo·MooTools 제거.
- **DBMS**: MySQL/MariaDB, PostgreSQL, Cubrid, SQLite 3 — 네이티브 또는 PDO 드라이버
  (`$database['driver'] = 'pdo'`).

원본과 동작이 다른 부분(스키마 추가, 플러그인·확장 API, DBMS 제한, 백업 가져오기, 신뢰 프록시,
OIDC 식별자 등)은 [MIGRATION.md](./documents/MIGRATION.md) 8절에, 전체 변경 이력은
[CHANGELOG.md](./documents/CHANGELOG.md)에 있습니다.

### 기존 설치 업그레이드

기존 설치는 파일을 교체하는 방식으로 Textcube-NG로 옮길 수 있습니다. 데이터베이스, `config.php`,
스킨, 플러그인, 첨부파일은 그대로 씁니다. 파일을 교체한 뒤:

1. 점검(`{블로그 주소}/checkup`)을 실행합니다. 추가형 스키마 변경을 적용하고, 기존 `.htaccess` 에
   `plugins/`·`attach/` 실행 차단 규칙을 넣습니다.
2. 루트의 `cleanup_legacy_assets.php` 를 명령줄에서 실행합니다(`php cleanup_legacy_assets.php` 로
   미리보기 → `--apply`). 이전 판에만 있던 파일(옛 정적 자산, PHPMailer 5.x, OpenID 2.0 라이브러리,
   jpgraph `plugins/StatGraph/count/` 폴더)을 지웁니다. 내용이 바뀐 파일은 건너뛰므로 직접 지우세요.
   정리한 뒤 이 파일은 지워도 됩니다.

단계별 절차(파일 교체, 백업 가져오기로 이전, 원본으로 되돌리기, 점검 목록)는
[MIGRATION.md](./documents/MIGRATION.md)를 참조하세요.

## 보안 현황 (요약)

| 심각도   | 미해결 | 항목 |
|----------|--------|------|
| HIGH     | 1      | 기본값 솔트 없는 MD5 비밀번호 저장(`password_hash()` 선택 가능) |
| MEDIUM   | 0      | — |
| LOW      | 1      | 정적 자산 잔여 위험(스킨 두 개의 Bootstrap 3.4.1 유지보수 종료, reCAPTCHA·Google Maps 스크립트는 SRI 불가) |
| INFO     | 0      | — |

해소 항목(성격별, 취약점 패치와 새 방어 기능 도입을 구분)은 [SECURITY.md](./documents/SECURITY.md)에 있습니다.

## 요구 사항 (현재 버전 — PHP 8.5 이식본)

* 웹서버 (하나 이상의 환경 필요)
  * Apache 2.4 이상
    * mod_rewrite 모듈을 통한 fancyURL 지원
  * Nginx 1.1 이상
  * IIS 7.0 이상
    * URL Rewrite Module 2.0 포함
* 언어
  * **PHP 8.5**
    * 필수 확장: iconv, GD, mbstring, json, mysqli 또는 pdo_mysql
* 데이터베이스 관리 시스템 (하나 이상의 환경 필요)
  * **MySQL 5.7.6+ 또는 8.0+** (UTF-8 문자셋 및 정렬 설정 필요)
  * MariaDB 10.2 이상
  * PostgreSQL, Cubrid, SQLite 3 (제한 있음, SQLite는 운영 환경 비권장 —
    [MIGRATION.md](./documents/MIGRATION.md) 8절 참조)
    * Cubrid PHP 8.x 모듈(네이티브·PDO)은 [deokio/php8_cubrid](https://github.com/deokio/php8_cubrid)를 참고하세요.

대규모 서비스 / 고부하 환경에서는 다음을 강력히 권장합니다:

* **OPcache** — PHP 5.5부터 내장. APC/XCache를 대체합니다 (두 확장 모두 제거/개발 중단).
* **pecl/memcache** + Memcached 서버 — 세션 저장 및 쿼리 캐시 용도
  * 공식 pecl/memcache는 PHP 8.5에서 빌드되지 않습니다.
    [websupport-sk fork](https://github.com/websupport-sk/pecl-memcache)를 쓰세요(동작 확인 완료,
    소스 빌드 또는 Debian/Ubuntu 패키지 매니저로 설치).
  * `config.php`에서 `$service['memcached'] = true;` 로 켜고 `$memcached['server'] = 'hostname';` / `$memcached['port'] = 11211;` 을 설정합니다.
  * NAT·포트포워딩 환경에서는 `config.php` 의 `$serviceURL` 을 *외부* URL(포트 포함)로 설정하세요.
    캐시된 스킨 조각과 피드는 서비스 URL을 저장해 memcached로 공유하므로, 설정하지 않으면 내부
    호스트·포트가 응답에 나올 수 있습니다.

### 검증된 환경

* 전체 사이트 시험: PHP 7.4 / 8.2 / 8.4 / 8.5, MySQL 5.7, Apache 2.4.
* 데이터베이스 어댑터 시험(네이티브·PDO 드라이버, 악성 입력 사례 포함):
  Cubrid 11.4.6, PostgreSQL 16, MariaDB 11.4, SQLite 3.
* **이 이식본으로 시험하지 않음**: IIS, Nginx (설정 문서는 있으나 시험하지 않음).

## 요구 사항 (구버전)

* 웹서버 (하나 이상의 환경 필요)
  * Apache 1.3 이상
    * mod_rewrite 모듈을 통한 fancyURL 지원
* 언어
  * (Textcube 1.8–1.10) PHP 5.2–5.6
    * iconv / gd 모듈 필요
    * 성능을 위해 APC 또는 XCache 권장
  * (Textcube 1.7 이하) PHP 4.3–5.1
    * iconv / gd 모듈 필요
* 데이터베이스 관리 시스템 (하나 이상의 환경 필요)
  * (Textcube 1.8–1.10) MySQL 5.0+ / MariaDB 5.1+ (UTF-8 문자셋)
  * (Textcube 1.7 이하) MySQL 4.1+ / MariaDB 5+ (낮은 버전은 Textcube 내 UTF-8 에뮬레이션 루틴 사용)

## 설치

시작 전 다음 사항을 확인하세요:

* 데이터베이스의 포트 / 사용자명 / 비밀번호
* 웹서버 설정 수정 권한

다운로드한 파일을 압축 해제하고 웹 접근 가능한 위치에 배치합니다. Textcube 위치를 /var/www/textcube로 가정합니다.

Apache 설정 예시:

    <VirtualHost *:80>
        ServerName www.example.org
        ServerAlias www.example.org
        ServerAdmin admin@example.org
        DocumentRoot /var/www/textcube/
        <Directory /var/www/textcube>
            AllowOverride FileInfo
            Require all granted
        </Directory>
    </VirtualHost>

Apache + php-fpm(`ProxyPassMatch`) 구성에서는 `attach/` 를 PHP 핸들러에서 빼야 합니다 —
[MIGRATION.md](./documents/MIGRATION.md) 8절 참조.

Nginx는 [setup_nginx.txt](./documents/setup_nginx.txt)의 규칙을 쓰세요. `plugins/`·`attach/` 아래
PHP 파일 실행을 막는 블록이 들어 있습니다.

접근 URL이 http://www.example.org 라면, http://www.example.org/setup.php에 접속하여 설치 프로그램을 실행합니다. 설치 절차를 따르세요.

* [설치 가이드 (한국어)](http://help.tattertools.com/ko/index.php?title=Getting_Started)

## 문서

### 이 이식본
* [MIGRATION.md](./documents/MIGRATION.md) — 업그레이드, 되돌리기, 원본과 다른 동작
* [SECURITY.md](./documents/SECURITY.md) — 보안 현황과 취약점 신고
* [CHANGELOG.md](./documents/CHANGELOG.md) — 변경 이력

### 사용자
* [단축키 목록](https://github.com/Needlworks/Textcube/wiki/shortCutList)

### 사양 및 구조

* [요구 사항](https://github.com/Needlworks/Textcube/wiki/requirements)
* [1.7에서 1.8 이상 버전으로의 업그레이드 안내](https://github.com/Needlworks/Textcube/wiki/attentionOnInstallation)
* [설정 옵션](https://github.com/Needlworks/Textcube/wiki/configOptions)
* [스킨](https://github.com/Needlworks/Textcube/wiki/SkinDocs)
* [스킨 치환자 목록](https://github.com/Needlworks/Textcube/wiki/replacer)
* [사전 정의 스타일](https://github.com/Needlworks/Textcube/wiki/skinpredefined)
* [스킨 정보 파일 사양](https://github.com/Needlworks/Textcube/wiki/skin/index_xml)
* [Tattertools/Textcube 온라인 매뉴얼 위키](http://help.tattertools.com)
* [플러그인](https://github.com/Needlworks/Textcube/wiki/PluginDocs)
* [플러그인 제작](https://github.com/Needlworks/Textcube/wiki/PluginIntroduction)
* [플러그인 사양](https://github.com/Needlworks/Textcube/wiki/pluginSpec)
* [플러그인 이벤트 리스너](https://github.com/Needlworks/Textcube/wiki/pluginEvents)
* [TTXML 형식 사양](https://github.com/Needlworks/Textcube/wiki/TTXML)
* [WPI 패키지 생성](https://github.com/Needlworks/Textcube/wiki/WPI)

### 개발
* [티켓팅 프로세스](https://github.com/Needlworks/Textcube/wiki/ticketProcess)
* [코딩 가이드라인](https://github.com/Needlworks/Textcube/wiki/codingGuideline)
* [기여자/보고자 목록](https://github.com/Needlworks/Textcube/wiki/contributorList)
* [개발 참고 자료](https://github.com/Needlworks/Textcube/wiki/devReference)
* [플러그인 개발자를 위한 Textcube 1.8 변경 사항](http://docs.google.com/View?id=dgc24tzr_136ckbg4ngn)
* [스킨 디자이너를 위한 Textcube 1.8 변경 사항](http://docs.google.com/View?id=dgc24tzr_138hhfbmwdg)
* [서버 관리자 및 서비스 호스트/관리자를 위한 Textcube 1.8 변경 사항](http://docs.google.com/View?id=dgc24tzr_137gr9xpdfb)
* [개발자를 위한 Textcube 1.8 변경 사항](http://docs.google.com/View?id=dgc24tzr_140c9wz6nc5)

## 외부 링크

* [Textcube 공지 블로그](http://notice.textcube.org/ko)
* [Needlworks](http://www.needlworks.org)
* [Needlworks 블로그](http://blog.needlworks.org)
* [Tatter Network Foundation 포럼](http://forum.tattersite.com/ko)

---

<sub>@deokio의 수정 작업 (2026), AI 어시스턴트(Anthropic Claude)의 도움을 받아 인간의 검토 하에 수행됨.
추가적인 저작권을 주장하지 않습니다. GPL 라이선스 적용 (프로젝트 전체와 동일).</sub>
