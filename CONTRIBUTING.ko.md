# Textcube-NG 기여 안내

이 프로젝트는 [Textcube](https://github.com/Needlworks/Textcube)의 커뮤니티 포크입니다.
아래에 열거된 영역을 중심으로 기여를 환영합니다.

## 우선 기여 영역

- **보안 강화** — [SECURITY.md](./documents/SECURITY.md)의 미해결 항목 참조:
  - MD5 패스워드 해싱 — 기존 호스팅 환경과의 단순 이식 호환성을 위해 의도적으로 유지; `password_hash()` / `password_verify()` 방식으로 이전 시 기존 사용자 로그인 불가 방지를 위한 신중한 재해싱 전략(예: 로그인 시 점진적 재해싱)이 필수
  - 잔존 raw SQL → prepared statement 변환
  - Cookie 속성 강화 (`HttpOnly`, `SameSite`, `Secure`)
  - 번들 라이브러리 CVE 검토 (phpopenid, phpxpath, jpgraph)
- **테스트 커버리지** — 현재 `tc_full_test.sh`에 33개 테스트 포함; 추가 시나리오 환영
- **실행 환경 호환성** — PostgreSQL, Nginx, MariaDB, IIS 환경 테스트
- **플러그인 및 스킨 호환성** — 기존 Textcube 플러그인/스킨 호환 검증

## 기여 방법

1. 이 저장소를 Fork하고 기능 브랜치를 생성합니다.
2. 기존 코드 스타일에 맞게 변경 사항을 작성합니다.
3. 이 판이 지원하는 PHP 버전(5.5, 5.6, 7.0, 7.2, 또는 7.3) 중 최소 하나의 환경에서 테스트합니다.
4. 변경 내용과 변경 이유를 명확히 설명한 Pull Request를 제출합니다.

모든 기여는 GPL과 호환되어야 합니다.
PR을 제출함으로써 귀하의 기여가 GPL 하에 라이선스됨에 동의합니다.

## AI 보조 개발

이 프로젝트는 AI 보조 개발(Anthropic Claude, 사람의 검토 하에 사용)을 활용합니다.
기여자도 AI 도구를 사용할 수 있으나, 제출 전 AI가 생성한 모든 변경 사항을
직접 검토해야 합니다.

## 보안 이슈 신고

미패치 취약점에 대해서는 공개 이슈를 **열지 마십시오**.
[GitHub Private Vulnerability Reporting](../../security/advisories/new)을 이용하거나,
GitHub 계정이 없는 경우 `textcube-ng@deok.io`로 이메일을 보내 주십시오.

자세한 내용은 [SECURITY.md](./documents/SECURITY.md)를 참조하십시오.
