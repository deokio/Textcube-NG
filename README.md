Textcube-NG: Brand Yourself - Personalized web publishing platform
==================================================================

**Version**: `1.10.10+php55.r4` (legacy edition for PHP 5.5 – 7.3) · [한국어](./README.ko.md)

> ## ⚠️ Security Notice — Read Before Deployment
>
> **One HIGH-severity item remains open by default: passwords are stored as unsalted MD5**
> (kept from upstream for drop-in compatibility with existing Textcube data).
> To store passwords with `password_hash()`, set `$service['passwordHash']` in `config.php` to
> `'multi'` (new or changed passwords only) or `'migrate'` (also converts MD5 passwords at login).
> Accounts stored that way cannot log in to upstream Textcube — read sections 5–6 of
> [MIGRATION.md](./documents/MIGRATION.md) before moving a database back.
>
> PHP 5.5 – 7.3 no longer receive security updates from the PHP project. Use this edition to keep an
> existing host running, and move to the PHP 8.5 package when the host allows it.
>
> ## ℹ️ Project Status and Provenance
>
> This is an **unofficial community fork** of [Textcube](https://github.com/Needlworks/Textcube),
> originally developed by Needlworks / Tatter Network Foundation (TNF) under GPL.
> It is **not affiliated with or endorsed by Needlworks/TNF**.
>
> The port consists predominantly of mechanical migrations following PHP upgrade documentation,
> **performed with the assistance of an AI coding assistant (Anthropic Claude) under human review**.
> The maintainer **asserts no additional copyright over these modifications**; the original
> Needlworks/TNF copyright is preserved in all source file headers as required by GPL.
> See [NOTICE](./documents/NOTICE).

## DESCRIPTION

Textcube is an opensource tool to archive and share the experiences, ideas, opinions and thoughts.

Supports import/export individual data via XML compatible with other solutions in 'Tattertools Project'

* Strong support of non-latin compatibility including Korean/Japanese/Chinese
* Supports various installation environments (webservers,databases and languages)
* Provides and extensible plugin and skin architecture
* Expandable from individual blog to blog service platform.
* Supports easy backup and restore via TTXML format, which is supported by various platforms of 'Project Tattertools.'

## HISTORY

Textcube is based on online publishing platform 'Tattertools,' started by JH in 2004, developed by TNC in 2005 and GPLized in 2006. Tatter Network Foundation (TNF) developed Tattertools with TNC from Apr. 2006. Needlworks/TNF was dedicated to Tattertools' development from Nov. 2006, and started developing 'Project S2' as its successor. 'Textcube' was named by YJ Park, and made its debut in Aug. 2007.

## Textcube-NG (PHP 5.5 – 7.3 legacy edition)

Textcube-NG is a community-maintained continuation of Textcube 1.10.10. The main package targets PHP 8.5
(`Textcube-NG-1.10.10+php85.r4.zip`). This **legacy edition** carries the same fixes and features, lowered to
run from one tree on **PHP 5.5 – 7.3**, for existing hosting that cannot move to a newer PHP
yet. The installer requires PHP 5.5.0 or later.

### Key Changes from Upstream Textcube 1.10.10

- **PHP 5.5 – 7.3 in one tree**: code that upstream wrote for PHP 5 runs unchanged in behavior on PHP 7.0 – 7.3,
  and features that need newer PHP APIs (secure random numbers, constant-time comparison, TLS certificate checks,
  safe unserialize) have compatible fallbacks on PHP 5.5 / 5.6.
- **Security fixes**: see [SECURITY.md](./documents/SECURITY.md) for the items resolved in r1–r4
  (authorization, CSRF, SQL injection, XSS, SSRF, session handling, upload execution and more), and for the
  limits that remain on PHP 5.5.
- **Emoji (4-byte characters)**: new installs on MySQL 5.6+ / MariaDB 10.0+ use utf8mb4. Existing sites can
  convert from the checkup page (back up the database first; see section 10 of [MIGRATION.md](./documents/MIGRATION.md)).
  MySQL 5.0 – 5.5 and MariaDB 5.1 – 5.5 keep utf8 and cannot store emoji.
- **Categories**: three or more levels (maximum depth set in category management, default 3), with category
  lists, feeds and post counts covering all subcategories.
- **Libraries**: PHPMailer 5.x kept (patched for PHP 7; the PHP 8.5 package uses 6.9); OpenID 2.0 removed and
  OpenID Connect added (opt-in, off by default); jpgraph → inline SVG chart; jQuery 3.7.1,
  jQuery UI 1.13.3, Lodash 4.18.1, TinyMCE 7.9.3, CodeMirror 5.65.21; Dojo and MooTools removed.

Behavior that differs from upstream (schema additions, plugin and extension API, DBMS limits, backup import,
trusted proxies, OIDC identifiers, etc.) is listed in section 8 of [MIGRATION.md](./documents/MIGRATION.md)
(Korean), PHP 5.5-specific notes are in section 11. The full modification history is in
[CHANGELOG.md](./documents/CHANGELOG.md).

### Upgrading an Existing Installation

Existing installations of upstream Textcube 1.10.10 can move to this edition by replacing the files; the
database, `config.php`, skins, plugins and attachments carry over. After replacing the files:

1. Run the checkup (`{blog URL}/checkup`). It applies the additive schema changes and adds the
   `plugins/` and `attach/` execution-blocking rules to an existing `.htaccess`.
2. Run `php cleanup_legacy_assets.php` in the root from the command line to preview, then `--apply`.
   It removes files that only older versions had (old static assets, the OpenID 2.0 library, the jpgraph
   `plugins/StatGraph/count/` folder, CodeMirror's node.js-only files). It does not touch PHPMailer, which this
   edition still uses. Files whose content was changed are skipped; delete those by hand. The script can be
   deleted afterwards.
3. On PHP 5.5, if HTTPS requests to other sites fail (feeds, trackbacks, OpenID Connect), set the CA bundle path
   in `config.php` (`$service['tls_cafile']`, see section 11 of [MIGRATION.md](./documents/MIGRATION.md)).

Step-by-step procedures (drop-in, migrating via backup import, moving back to upstream, checklist) are in
[MIGRATION.md](./documents/MIGRATION.md) (Korean).

## Security Status (Summary)

| Severity | Open | Item |
|----------|------|------|
| HIGH     | 1    | Unsalted MD5 password storage by default (opt-in `password_hash()`) |
| MEDIUM   | 0    | — |
| LOW      | 1    | Static asset residual risk (Bootstrap 3.4.1 in two skins is end-of-life; reCAPTCHA and Google Maps scripts cannot carry SRI) |
| NOTE     | 1    | PHP 5.5 limits: TLS checks the certificate CN only and needs a CA bundle; no token issuance without a secure random source |

Resolved items, grouped by type and marked as vulnerability patches or newly added defenses, are in
[SECURITY.md](./documents/SECURITY.md) (Korean).

## REQUIREMENTS (CURRENT VERSION — PHP 5.5 – 7.3 Legacy Edition)

* Web servers (Need at least one environment)
  * Apache 2.4 or above
    * fancyURL support with mod_rewrite module
  * Nginx 1.1 or above
  * IIS 7.0 or above
    * with URL Rewrite Module 2.0
* Language
  * **PHP 5.5 – 7.3** (one tree; for PHP 8.5 use the main package)
    * Required extensions: iconv, GD, mbstring, json, mysqli or pdo_mysql (PDO driver: `$database['driver'] = 'pdo'`)
    * mysqlnd is recommended; without it prepared-statement results use the `bind_result()` path.
    * On PHP 5.5 / 5.6 a secure random source is needed: `/dev/urandom` readable (not blocked by `open_basedir`)
      or the mcrypt extension. Without one, login and form tokens cannot be issued.
    * On PHP 5.5, HTTPS certificate checks need a CA bundle — set `$service['tls_cafile']` if it is not found
      automatically.
* Database Management System (Need at least one environment)
  * **MySQL 5.0+ (including 8.0)** with UTF-8 character set and collation setting
    * MySQL 8.0 with PHP older than 7.2.8: the account must use `mysql_native_password`.
    * MySQL 8.0 with PHP 5.5 / 5.6: their mysqlnd does not know the server's default character set
      utf8mb4_0900_ai_ci and the connection fails (error 2054) — set `character-set-server=utf8` in the MySQL 8
      server configuration. PHP 7.0 – 7.3 connect without it.
  * MariaDB 5.1 or above
  * Emoji need MySQL 5.6+ / MariaDB 10.0+ (see above).
  * SQLite 3 (limited feature set; not recommended for production)

For massive service / heavy load environments, the following are strongly recommended:

* **OPcache** — built-in since PHP 5.5, replaces APC/XCache (both removed/abandoned)
* **pecl/memcache** with a Memcached server — for session storage and query cache
  * Enable in `config.php`: `$service['memcached'] = true;` and set
    `$memcached['server'] = 'hostname';` / `$memcached['port'] = 11211;`.
  * Behind NAT or port-forwarding, set `$serviceURL` in `config.php` to the *external* URL
    (including the port). Cached skin fragments and feeds store the service URL and are shared via
    memcached; otherwise the internal host/port can appear in responses.

### Tested Environments

* Full-site tests: PHP 5.5 with MySQL 5.0, 5.1, 5.5, 5.6, 8.0 and MariaDB 5.1, 5.5, 10.0, 10.1, 10.11;
  PHP 7.3 with MySQL 5.5, 8.0 and MariaDB 10.1; SQLite 3.
* Syntax checks on PHP 5.5, 5.6, 7.0, 7.2 and 7.3.
* **Not tested with this edition**: IIS, Nginx, PostgreSQL, Cubrid.

## REQUIREMENTS (OLD VERSIONS)

* Web servers (Need at least one environment)
  * Apache 1.3 or above
    * fancyURL support with mod_rewrite module
* Language
  * (Textcube 1.8–1.10) PHP 5.2–5.6
    * with iconv / gd module
    * APC or XCache recommended for performance
  * (Till Textcube 1.7) PHP 4.3–5.1
    * with iconv / gd module
* Database Management System (Need at least one environment)
  * (Textcube 1.8–1.10) MySQL 5.0+ / MariaDB 5.1+ with UTF-8 character set
  * (Till Textcube 1.7) MySQL 4.1+ / MariaDB 5+ (lower version with UTF-8 emulation routine in Textcube)

## INSTALLATION

Before you start, you need to

* know the port / username / password of your database
* have the permission to modify webserver configuration.

Uncompress the downloaded file, locate them to the web-accessible location. Assume that the textcube location is /var/www/textcube.

This is apache setting.

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

With Apache + php-fpm (`ProxyPassMatch`), exclude `attach/` from the PHP handler — see section 8 of
[MIGRATION.md](./documents/MIGRATION.md).

For nginx, use the rules in [setup_nginx.txt](./documents/setup_nginx.txt). They include the blocks that
stop PHP files under `plugins/` and `attach/` from running.

If the accessible URL is http://www.example.org, run the installation program by accessing http://www.example.org/setup.php. Follow the setup procedure.

* [Installation (Korean)](http://help.tattertools.com/ko/index.php?title=Getting_Started)

## DOCUMENTATION

### THIS EDITION
* [MIGRATION.md](./documents/MIGRATION.md) — upgrading, moving back, behavior differences, PHP 5.5 notes (Korean)
* [SECURITY.md](./documents/SECURITY.md) — security status and reporting
* [CHANGELOG.md](./documents/CHANGELOG.md) — modification history

### USERS
* [Shortcuts](https://github.com/Needlworks/Textcube/wiki/shortCutList)

### SPECIFICATIONS AND STRUCTURES

* [Requirements](https://github.com/Needlworks/Textcube/wiki/requirements)

* [Upgrade instruction from 1.7 to 1.8 or higher version](https://github.com/Needlworks/Textcube/wiki/attentionOnInstallation)
* [Configurable options](https://github.com/Needlworks/Textcube/wiki/configOptions)

* [Skin](https://github.com/Needlworks/Textcube/wiki/SkinDocs)
* [Skin replacer list](https://github.com/Needlworks/Textcube/wiki/replacer)
* [Predefined styles](https://github.com/Needlworks/Textcube/wiki/skinpredefined)
* [Skin information file specification](https://github.com/Needlworks/Textcube/wiki/skin/index_xml)
* [Tattertools/Textcube online manual wiki](http://help.tattertools.com)

* [Plugins](https://github.com/Needlworks/Textcube/wiki/PluginDocs)
* [Plugin Creation](https://github.com/Needlworks/Textcube/wiki/PluginIntroduction)
* [Plugin Specification](https://github.com/Needlworks/Textcube/wiki/pluginSpec)
* [Plugin Event Listeners](https://github.com/Needlworks/Textcube/wiki/pluginEvents)

* [TTXML format specification](https://github.com/Needlworks/Textcube/wiki/TTXML)
* [WPI package creation](https://github.com/Needlworks/Textcube/wiki/WPI)

### DEVELOPMENT
* [Ticketing process](https://github.com/Needlworks/Textcube/wiki/ticketProcess)
* [Coding guideline](https://github.com/Needlworks/Textcube/wiki/codingGuideline)
* [Commiter/Reporter List](https://github.com/Needlworks/Textcube/wiki/contributorList)

* [Developing references](https://github.com/Needlworks/Textcube/wiki/devReference)
* [Textcube 1.8 changes for Plugin Developers](http://docs.google.com/View?id=dgc24tzr_136ckbg4ngn)
* [Textcube 1.8 changes for Skin Designers](http://docs.google.com/View?id=dgc24tzr_138hhfbmwdg)
* [Textcube 1.8 changes for Server administrators and service hosts / maintainers](http://docs.google.com/View?id=dgc24tzr_137gr9xpdfb)
* [Textcube 1.8 changes for coding geeks](http://docs.google.com/View?id=dgc24tzr_140c9wz6nc5)

## EXTERNAL LINKS

* [Textcube notice blog](http://notice.textcube.org/ko)
* [Needlworks](http://www.needlworks.org)
* [Needlworks Blog](http://blog.needlworks.org)
* [Tatter Network Foundation forum](http://forum.tattersite.com/ko)

---

<sub>Modifications by @deokio (2026), performed with AI assistance (Anthropic Claude) under human review.
No additional copyright is asserted. Licensed under GPL (same as the rest of the project).</sub>
