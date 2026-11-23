# pageCache 설계 변경 vs. memcached 캐시 TTL 도입 — 판단 및 권고

## Context

NAT 환경의 `uri.service` 포트 누락 버그를 이식본 모두에서 수정했다 (`framework/model/URIHandler.php`: `$_SERVER['HTTP_HOST']` 뒤에 `service.port` 합성). 캐시 OFF 상태에서는 정상 작동을 확인했고, 캐시 ON(pagecache/skincache/codecache 각각)에서도 동일 호스트 기준 hit/miss 정합성은 확보됐다.

그러나 다음 두 가지가 미해결로 남아있다:

1. **pageCache / skinCache / 각종 \*Cache 의 키 설계가 호스트/포트를 포함하지 않는다.**
   - 같은 blogid 라도 다른 외부 호스트(예: `example.com` vs `example.com:8085` vs `blog.example.com`)로 접근하면 캐시에 박힌 절대 URL 이 다른 호스트로 새어나갈 잠재성이 있다.
   - 특히 `skinCache` 는 `library/blog.skin.php:200` 에서 스킨 조각 안의 `./` 를 `$serviceURL` 기준 절대 URL 로 치환한 뒤 `get_object_vars($this)` 직렬화로 통째 저장 — 한 번 캐시되면 다른 호스트로 접근해도 그 호스트가 박힌 사본이 그대로 나온다.
2. **memcached 사용 시 캐시 TTL 을 설정할 방법이 없다.**
   - `Cache_Memcache::set()` 의 4번째 인자(memcached TTL)는 기본 0 (무한) 으로만 호출됨.
   - 운영 측면에서 stale 누적 방지를 위한 만료 정책이 부재.

본 문서는 두 사안 각각에 대한 변경 비용/이득과 권고를 정리한다.

---

## 1. pageCache 설계 변경

### 현재 키 도식

- `framework/legacy/Needlworks.Cache.PageCache.Legacy.php:109-121` 의 `getFileName()`
  - `realNameOwner = name . "_" . blogid . "_owner"`
  - `realNameGuest = name . "_" . blogid`
  - 파일명: `abs(crc32(realName)).'.cache'`
- 즉 **호스트/포트/스킴 미포함**. blogid + ownership 만으로 결정.
- `setPageCacheLog()` (line 144 부근) 가 DB 의 `PageCacheLog` 테이블에 키를 기록 — 동일 네이밍.
- 무효화: `CacheControl::flush*()` 들이 `PageCacheLog` 를 LIKE 로 스캔해서 매칭되는 파일을 지움.
  - 현재 LIKE 패턴이 `_` 구분자 가정 — 실제 키 일부는 `-` 가 섞여 있어 누락이 발생 중 (선행 드리프트 버그).

### 캐시되는 콘텐츠의 호스트 의존성

| 캐시 종류 | 호스트 의존 여부 | 근거 |
|---|---|---|
| `skinCache` (skincache) | **강함** | `blog.skin.php:200` `str_replace('./', "$serviceURL/$name/", $sval)` 로 절대 URL 베이크 후 `get_object_vars()` 직렬화 |
| `entryCache-` / `categoryList-` / RSS·ATOM | 중간 | 본문에 절대 URL/permalink 가 포함 (`uri.default`, `uri.host` 기반) |
| `pageCache` (HTML body) | 중간 | 위 모든 것이 합쳐진 최종 HTML |
| `codeCache` (코드캐시) | **없음** | PHP 라이브러리 소스를 직렬 머지 — URL 비종속 |

### 변경 방안 A — 키에 호스트/포트 포함

**Pros**
- NAT/멀티 호스트 환경에서 정확성 보장 (호스트별 캐시 분리).
- 한 호스트의 캐시가 다른 호스트로 새지 않음.
- 변경 범위가 비교적 명확 (키 생성 지점 + LIKE 패턴 + DB 칼럼 길이 검토).

**Cons**
- 캐시 히트율 하락 — 호스트 N 개면 캐시 N 배.
- 무효화 로직(LIKE) 도 호스트 prefix/suffix 패턴을 반영하도록 모두 손봐야 함.
- 키 길이 증가로 DB `PageCacheLog.name` 칼럼 길이 점검 필요.
- 선행 `_`/`-` 구분자 드리프트 버그까지 같이 정리해야 일관성 확보 — 작업 폭이 커짐.

**Touch 포인트**
- `framework/legacy/Needlworks.Cache.PageCache.Legacy.php` (`getFileName`, `setPageCacheLog`, `CacheControl::flush*` 전부)
- `library/blog.skin.php` (스킨 캐시 키)
- `framework/cache/*.php` (memcache 경로의 키 일관성)
- DB 마이그레이션 (칼럼 길이/인덱스)

### 변경 방안 B — 콘텐츠 내 URL 을 placeholder 로 캐시 후 렌더 시 치환

**Pros**
- 캐시 히트율 유지(키는 그대로).
- 호스트별 누수 원천 차단.

**Cons**
- 영향 범위가 매우 큼: skin compile, entry render, RSS/ATOM, comment/trackback feed 모두 placeholder 도입 필요.
- 모든 출력 경로의 마지막 단계에 `str_replace` 통과 보장 필요(누락 시 placeholder 노출).
- 1.10.10 본가의 디자인 컨벤션을 넘는 큰 리팩토링.

### 변경 방안 C — `$serviceURL` 명시 권장(현 기본값 정책 유지)

**Pros**
- 코드 변경 0.
- 운영자가 `config.php` 의 `$serviceURL` 을 명시하면 NAT 외부 URL 이 항상 일관되게 박힘.
- 캐시 히트율 손실 없음.

**Cons**
- 한 인스턴스를 두 외부 호스트(예: `example.com` 와 `blog.example.com`)로 동시에 노출하는 경우는 여전히 어느 한쪽 호스트의 URL 만 박힘 → 다른 호스트 접근 시 cross-host URL 노출 가능.
- secondaryDomain 같은 멀티 호스트 정식 기능과 일부 상충.

### 권고 — **방안 C(현상 유지)** 를 디폴트로, 단 다음 조건 충족 시 방안 A로 격상

조건:
- 한 인스턴스를 2개 이상의 외부 호스트로 동시 서빙하는 운영 시나리오가 실제로 요구되는가?
- secondaryDomain 기능을 적극 사용하는가?

이 조건이 명확히 "예" 가 아니라면, 본 마이그레이션 프로젝트의 범위(= PHP 버전 이식)를 넘어가므로 **별도 트랙으로 분리** 권고. 이 이식 프로젝트의 "동일한 기능을 하는 코드로 변경" 원칙에도 부합.

---

## 2. memcached 캐시 TTL 도입

### 현재 상태

- `framework/cache/Memcache.php`
  - `Cache_Memcache::set($key, $value, $expirationDue=0)` — 시그니처는 이미 TTL 지원 (line 33).
  - 내부: `$this->memcache->set($key, $value, 0, $expirationDue)` — memcached 의 4번째 인자(만료 초)로 전달 (line 41).
- `framework/model/Config.php`
  - `Model_Config::updateContext()` (line 70-76) 가 `$memcached[*]` 의 임의 키를 `memcached.*` 컨텍스트 프로퍼티로 자동 노출 — **새 키 추가 시 별도 보일러플레이트 불필요**.
- 갭: IModel 계층 (`framework/cache/IModel.php` 의 `insert()/replace()`, line 88-96) 이 하드코딩 `set($k,$v,0)` 로 호출 → TTL 0(무한) 이 강제됨.
- 세션 TTL 은 별도 (`service.timeout`) — 영향 없음.

### 도입 가능성 — **가능, 소규모**

필요 작업:
1. `config.php` 또는 `default.config.php` 가이드 코멘트에 `$memcached['cache_ttl'] = 0;` (0 = 기존 동작) 키 노출.
2. `Cache_Memcache::__construct` 에서 `Model_Context::getInstance()->getProperty('memcached.cache_ttl', 0)` 를 멤버 `$this->defaultTTL` 로 보관.
3. `Cache_Memcache::set()` 호출자가 TTL 미명시 + 멤버 TTL > 0 이면 멤버 TTL 적용.
4. IModel 계층의 `insert()/replace()` 도 동일하게 멤버 TTL 사용.
5. 회귀 방지: pageCache 등 의도적으로 무한 보관해야 하는 경로가 있다면 그쪽은 호출 시 명시적으로 0 을 넘기게 정리.

### 적용 범위

Textcube-NG 이식본(PHP 5.5 ~ 7.3 레거시판 포함) 모두 같은 내용이다. 원본 Textcube 1.10.10 은 바꾸지 않는다.

### Pros / Cons

**Pros**
- stale 누적 방지 — 메모리 압박 시 LRU 가 아닌 명시적 만료로도 정리.
- 운영자가 캐시 정책을 환경 변수처럼 조정 가능.
- 코드 변경 표면 작음(파일 2~3개) — 회귀 위험 낮음.

**Cons**
- 기본값 0 을 유지하면 기존 동작 변경 없음 — 새 옵션을 실제로 활성화한 사이트에서만 영향.
- 매우 짧은 TTL 설정 시 캐시 효과 무력화 — 운영 가이드 필요.
- pageCache 처럼 명시적 invalidation 에 의존하던 항목이 TTL 만료 후 cold path 로 빠지면 일시적 부하 증가 가능.

### 권고 — **도입 권고 (옵트인, 기본값 0)**

본 마이그레이션 프로젝트 범위 내에서 "환경/운영 안정성 강화" 로 정당화 가능. PHP 버전 업그레이드와 함께 권고되는 메모리·캐시 위생 사항과 정합.

---

## 결정 — 방안 B (Placeholder 기반) **보류**

초기 검토에서 방안 B 가 의미적으로 정합하다고 판단했으나, 실제 영향 범위 인벤토리 결과 회귀 위험과 작업량이 본 마이그레이션 프로젝트의 범위(PHP 버전 이식)를 명백히 초과함이 드러났다. 이 위험성을 근거로 **방안 B 를 보류**하기로 결정.

보류 결정의 근거:
- 출력 경로 124곳/33파일에 걸친 광범위 변경.
- secondaryDomain 환경에서 `uri.host` 가 요청-가변이라 토큰 누락 시 host 누출 직결 (Severity: 높음).
- `LegacySupport` globals 가 `uri.*` 와 동기화되어 있어 dual-mode 일관 처리 누락 시 토큰화 무력화 (Severity: 높음).
- `ob_gzhandler` 와의 콜백 순서 의존성 — 잘못 등록 시 gzip 된 바이트열에 치환 적용되어 본문 손상.
- 캐시 작성 경로의 어떤 한 지점이라도 토큰화 누락 시 `{__TC_*__}` 토큰이 그대로 클라이언트에 노출.
- `plugins/*` 의 캐시 작성 사각지대 미확정.
- 본 작업이 PHP 버전 이식 외 별도 기능 변경에 해당하므로 이 이식 프로젝트의 "동일한 기능을 하는 코드로 변경" 원칙과 정면으로 어긋남.

→ 본 마이그레이션 프로젝트 동안 **방안 B 는 시도하지 않는다.** 향후 별도 트랙(예: Textcube 정식 다중호스트 지원 기획) 에서 다룰 사안. 본 문서는 "왜 보류했는가" 의 근거 기록으로 보존하며, 아래 운영 가이드(섹션 3)로 NAT + memcached 환경에서의 실용적 우회를 제공한다.

### B-1. Placeholder 토큰 설계

캐시 본문에 박힐 토큰. URL/permalink 의 호스트·base 부분만 토큰화하고, querystring·suri·encoded id 등은 그대로 둔다.

| 토큰 | 치환 대상 | 원천 |
|---|---|---|
| `{__TC_SERVICE__}` | service 루트 절대 URL (스킴+호스트+포트+service.path) | `uri.service` |
| `{__TC_HOST__}` | 스킴+호스트+포트 (path 없음) | `uri.host` |
| `{__TC_BLOG_PATH__}` | 현재 요청 호스트에서 본 블로그의 base path | `uri.base` 의 path 부분 |
| `{__TC_BLOG__}` | 현재 요청 호스트 기준 블로그 절대 URL | `uri.default` 또는 `uri.base` (요청 호스트와 일치 여부에 따라) |

토큰명은 HTML/CSS/JS 어느 컨텍스트에 박혀도 의도치 않게 매칭/이스케이프되지 않도록 `{__TC_*__}` 형태 채택 (영문 + underscore + 영문).

### B-2. 캐시 기록 시점에 토큰을 박는 지점

| 위치 | 변경 내용 |
|---|---|
| `library/blog.skin.php:200` 부근 | `str_replace('./', "$serviceURL/$name/", $sval)` 의 `$serviceURL` 위치에 `{__TC_SERVICE__}` 박음. 스킨 객체 직렬화는 그대로. |
| permalink/URL 생성 헬퍼 (`framework/model/URIHandler.php::__URIvariableParser` 결과 사용처) | 캐시 대상 본문(entry, RSS, ATOM, comment/trackback feed) 의 absolute URL 출력 지점에서 `uri.default`/`uri.host`/`uri.base` 대신 대응 토큰 사용. 비캐시 출력 경로는 영향 없도록 toggle. |
| `framework/legacy/Needlworks.Cache.PageCache.Legacy.php` (pageCache 본문 저장) | 저장 직전 final HTML 에 추가 가공 불필요 — 위 두 지점이 이미 토큰을 박았으므로. |

### B-3. 출력 직전 치환(렌더 시 swap)

단일 지점에서 4개 토큰을 `str_replace` 1회로 일괄 치환. 후보:
- `Respond::PrintPage()` 또는 출력 직전 출력 버퍼 핸들러 (`ob_start(callback)`).
- pageCache hit 경로(파일 read → echo) 의 echo 직전.

토큰 누락(치환 안 된 토큰이 클라이언트에 노출) 방지 검증을 위해 비프로덕션 모드에선 출력 후 `{__TC_` 잔존 여부 assert 권장.

### B-4. 적용 범위

Textcube-NG 이식본(PHP 5.5 ~ 7.3 레거시판 포함) 모두 같은 내용이다. 원본 Textcube 1.10.10 은 바꾸지 않는다.

### B-5. 영향 분석

| 항목 | 영향 |
|---|---|
| 캐시 히트율 | host 별 분리 없음 → 현행 유지 |
| secondaryDomain 동작 | primary/secondary 모두에서 동일 캐시 사용, URL 만 호스트별로 정확 |
| Path 모드 다중 블로그 | base path 토큰화로 정합 |
| 기존 캐시와의 호환성 | 토큰 미포함 구캐시는 그대로 절대 URL 박힌 채 서빙 → 한 번 flush 필요. 마이그레이션 노트에 명시. |
| 회귀 위험 | 중간 — 누락 지점(특히 RSS/ATOM/comment feed/trackback) 식별 누락 시 호스트 누출 또는 토큰 노출. 별도 플랜에서 모든 출력 경로를 grep 으로 인벤토리화 필요. |

### B-6. 실제 영향 범위 (코드베이스 인벤토리 결과)

호출 카운트 (이식본 모두 같은 구조):

| 항목 | 영향 |
|---|---|
| `getProperty('uri.*')` 전체 (`default`/`host`/`base`/`service`/`blog`/`permalink`) | **124 occurrences / 33 files** |
| `getProperty('uri.service')` + `'uri.blog'` | 107 occurrences / 25 files |
| `$blog['primaryBlogURL']` / `$blog['secondaryBlogURL']` | 11 라인 (전부 `framework/model/URIHandler.php:205-239`) |
| `$context->getProperty('domain')` 직접 사용 | 50 occurrences / 3 files |

핵심 수정 지점:

- **Skin compile (1순위 진앙)**: `library/blog.skin.php:200,202-205,597,599,631,633,634-660` — `$serviceURL` / `$blogURL` / `$defaultURL` 기반 12+ 종 절대 URL (blog_link, keylog_link, localog_link, taglog_link, guestbook_link, response_rss_url, comment_rss_url, trackback_rss_url, response_atom_url, comment_atom_url, trackback_atom_url, owner_url) 가 스킨 객체에 베이크됨.
- **Entry/list/cover/paging 캐시**: `interface/common/blog/entries.php:191,195`, `cover.php:12,28,31`, `list.php:38-39,45`, `end.php:33,88-93,108-109`, `line.php:105`, `siteTags.php:22`, `library/view/view.php:469-470`.
- **Feed 캐시**: `library/model/blog.feed.php` 의 `publishRSS` (line 572-626) / `publishATOM` (line 636-) 와 `interface/rss/*/index.php`, `interface/atom/*/index.php` (총 ~22 라인) — `<link>`/`<guid>`/`<id>`/`<atom:link>` 모두 절대 URL.
- **LegacySupport globals**: `framework/model/LegacySupport.php:21-29` 가 `$serviceURL`/`$pathURL`/`$defaultURL`/`$baseURL`/`$hostURL`/`$folderURL`/`$blogURL` 를 `uri.*` 에서 동기화 — 토큰화 시 여기서 토큰값을 globals 로 흘려야 skin.php:200 의 `str_replace` 가 실값 대신 토큰을 박음.
- **secondaryDomain 흐름**: `framework/model/URIHandler.php:204-244` 에서 primary/secondary 분기. `uri.host` 는 요청-가변 (`$_SERVER['HTTP_HOST']` 직조), 캐시에 박히면 누설. `primaryBlogURL`, `secondaryBlogURL` 모두 토큰 매핑에 포함되어야 함.

출력 단일 hook 후보 (placeholder → 실값 치환):
- `interface/common/blog/end.php:142` `print $view;` — 블로그 본문 최종.
- `interface/index.php:117` `print $view;` — dispatcher fallback.
- `interface/rss/index.php:22` / `interface/atom/index.php:24` `echo fireEvent('ViewRSS'|'ViewATOM', …)` — feed 최종.
- `interface/rss/*/index.php` / `interface/atom/*/index.php` `echo fireEvent('ViewCommentRSS'|'ViewCommentATOM'|…, $cache->contents);` — 각 feed.
- `framework/legacy/Needlworks.PHP.OutputWriter.php:15,23` `ob_start('ob_gzhandler')` — **가장 바깥**. 여기 콜백에 치환을 묶는 것이 단일 hook 후보.
- `Respond::PrintPage()` 는 **존재하지 않음** (`Respond::PrintResult`/`PrintValue` 만). 단일 emit 함수가 없는 구조 → 출력 버퍼 콜백 기반이 가장 견고.

### B-7. 회귀 위험 (식별된 항목)

1. **호스트 누출 (Severity: 높음)**: secondary↔primary 캐시 공유 시 잘못된 host emit. `uri.host` 가 요청-가변이라 토큰화 누락 시 secondary 사용자에게 primary URL (또는 반대) 노출. → `uri.host`/`primaryBlogURL`/`secondaryBlogURL` 모두 토큰 매핑 필수.
2. **토큰 노출 (Severity: 중간)**: 출력 경로 중 하나라도 치환 hook 을 통과 안 하면 `{__TC_SERVICE__}` 가 그대로 HTML/RSS/ATOM 에 노출. 특히 RSS/ATOM 의 `interface/rss/*/index.php` 22 라인 모두 커버 필요. → `OutputWriter` 의 `ob_start` 콜백 + `ViewRSS`/`ViewATOM` 이벤트 hook 이중 안전망 권장.
3. **`ob_gzhandler` 순서 (Severity: 중간)**: `ob_gzhandler` 적용 후 gzip 된 바이트열에서 치환이 깨짐. → placeholder→real 치환은 `ob_gzhandler` **보다 안쪽 (먼저 실행되는)** 콜백으로 걸어야 함. 콜백 등록 순서 검증 필수.
4. **LegacySupport globals 오염 (Severity: 높음)**: `$serviceURL`/`$blogURL`/`$defaultURL`/`$hostURL` globals 가 토큰이 아닌 실값을 유지하면 skin.php:200 의 `str_replace` 단계에서 실 URL 이 스킨 캐시에 박혀버려 토큰화가 무력화. → LegacySupport 도 dual-mode (캐시 작성 컨텍스트에선 토큰, 그 외엔 실값) 또는 단일 토큰 모드 + 출력 hook 으로 일관 처리.
5. **flushEntry 의 `_` vs `-` 드리프트 (Severity: 낮음, 독립 버그)**: `framework/legacy/Needlworks.Cache.PageCache.Legacy.php:393` flushEntry 가 `commentRSS_${entryId}` LIKE 로 찾는데, `interface/rss/comment/index.php:17,21` 는 `'commentRSS-'.$suri['id']` (하이픈) 으로 reset → flush 미스. 방안 B 와 직접 무관하지만 캐시 키 정규화 작업과 같이 처리하면 효율적.
6. **기존 캐시 호환성 (Severity: 낮음)**: 토큰 미포함 구캐시는 그대로 실 URL 박힌 채 서빙 → 배포 시 1회 flush 마이그레이션 노트 필수.
7. **3rd-party plugin (Severity: 미확정)**: `plugins/*` 가 `uri.default` 등 실 URL 을 자체적으로 캐시에 박을 가능성. 모든 in-tree plugin 의 캐시 작성 지점도 grep 필요.

### B-8. 향후 착수 시 다룰 항목

- 위 인벤토리 기반 출력 경로 전수 토큰화 (skin, entry/list/cover/paging, RSS/ATOM feed, LegacySupport globals).
- placeholder 토큰의 이스케이프 안전성 (HTML attr / CSS url() / JS string / XML CDATA 컨텍스트 검토).
- `OutputWriter` 의 `ob_start` 콜백에 치환 함수 inject + ob_gzhandler 순서 보장.
- 비프로덕션 모드에서 출력 후 `{__TC_` 잔존 assert (회귀 안전망).
- 배포 시 기존 캐시 강제 flush 마이그레이션 스텝.
- `commentRSS_` / `commentRSS-` 드리프트 버그 정리 (같이 또는 별건).
- `plugins/*` 의 캐시 작성 지점 인벤토리 (방안 B 의 사각지대 확인).

---

## 3. NAT + memcached 환경 운영 가이드

**포트가 다른 NAT 환경에서 memcached(공유 캐시) 를 사용하는 경우 `$serviceURL` 을 명시적으로 세팅** 하도록 아래 3개 문서/파일을 갱신했다.

### 갱신 대상

| 파일(패키지 루트 기준) | 내용 |
|---|---|
| `README.md` | memcached 절의 NAT 주의 |
| `config.php`(설치 때 만들어짐) | `$serviceURL` 주석 |
| `setup.php` | 설치 때 만드는 `config.php` 템플릿의 같은 주석 |

원본 Textcube 1.10.10 은 바꾸지 않는다.

### 갱신 내용

**`config.php` / `setup.php`** — `$serviceURL` 주석에 memcached + NAT 환경에서 필수임을 명시하는 4줄 추가:

```php
//$serviceURL = 'http://example.com' ; // Override service base URL (skin/plugin/resource paths).
                                      // Required when behind NAT/port-forwarding: e.g. 'http://example.com:8080'
                                      // *Especially required* when $service['memcached']=true on a NAT/port-forwarded
                                      // host: skin/feed caches bake the absolute URL at write time and are shared
                                      // across requests via memcached, so the external URL must be pinned here to
                                      // avoid leaking the internal hostname/port back to clients.
```

**`README.md`** — memcached 섹션 아래 NAT/port-forwarding 환경 주의 블록쿼트 삽입:

```markdown
> **NAT / port-forwarding note**: When `$service['memcached'] = true` on a host behind NAT or
> port-forwarding (external port differs from internal port), you **must** set `$serviceURL` in
> `config.php` to the *external* URL (including the external port). Cached skin fragments and feeds
> bake the service URL at write time and are shared across requests via memcached; without this,
> the internal hostname/port can leak into responses served to clients on the external URL.
```

---

## 4. memcached 캐시 TTL 도입 — 별건 보류

현 단계에서는 도입하지 않음. 차후 운영 필요 시점에 별도 플랜으로 처리.

---

## 5. 완료된 실행 항목

- `config.php`: `$serviceURL` 주석 NAT + memcached 필수 설명 추가.
- `setup.php`: 설치 시 생성되는 config.php 템플릿에 동일 가이드 추가.
- `README.md`: memcached 섹션에 NAT 주의 블록쿼트 삽입.
- 디버그용 임시 파일은 배포본에 넣지 않았다.

---

## 결론 요약

| 항목 | 결정 | 비고 |
|---|---|---|
| pageCache Placeholder 기반 URL (방안 B) | **보류** (위험성 근거) | 본 문서에 보류 사유 보존 |
| NAT + memcached 환경 `$serviceURL` 명시 가이드 | **완료** | 3파일 갱신 |
| memcached TTL 옵트인 도입 | **별건 보류** | 추후 별도 결정 |
