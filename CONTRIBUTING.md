# Contributing to Textcube-NG

This is a community fork of [Textcube](https://github.com/Needlworks/Textcube).
Contributions are welcome, especially for the areas listed below.

## Priority Areas

- **Security hardening** — see open items in [SECURITY.md](./documents/SECURITY.md):
  - MD5 password hashing — intentionally retained for drop-in compatibility with existing Textcube
    data; any migration to `password_hash()` / `password_verify()` must include a careful rehashing
    strategy (e.g., on-login rehash) to avoid breaking existing user logins
  - Remaining raw SQL → prepared statement conversions
  - Cookie attribute hardening (`HttpOnly`, `SameSite`, `Secure`)
  - Bundled library CVE review (phpopenid, phpxpath, jpgraph)
- **Test coverage** — currently 33 tests in `tc_full_test.sh`; more scenarios welcome
- **Environment compatibility** — PostgreSQL, Nginx, MariaDB, IIS testing
- **Plugin and skin compatibility** — verification with existing Textcube plugins/skins

## How to Contribute

1. Fork this repository and create a feature branch.
2. Make your changes following the existing code style.
3. Test against at least one PHP version supported by this edition (5.5, 5.6, 7.0, 7.2, or 7.3).
4. Submit a Pull Request with a clear description of what changed and why.

All contributions must be GPL-compatible.
By submitting a PR, you agree your contribution is licensed under GPL.

## AI-Assisted Development

This project uses AI-assisted development (Anthropic Claude under human review).
Contributors are welcome to use AI tools as well, but must review all
AI-generated changes themselves before submitting.

## Reporting Security Issues

Please **do not** open public issues for unpatched vulnerabilities.
Use [GitHub Private Vulnerability Reporting](../../security/advisories/new)
or email `textcube-ng@deok.io` for reporters without a GitHub account.

See [SECURITY.md](./documents/SECURITY.md) for details.
