Textcube-NG: Brand Yourself - Personalized web publishing platform
==================================================================

**Version**: `1.10.10+php85.r4` · [한국어](./README.ko.md)

> ## ⚠️ Security Notice — Read Before Deployment
>
> **One HIGH-severity item remains open by default: passwords are stored as unsalted MD5**
> (kept from upstream for drop-in compatibility with existing Textcube data).
> To store passwords with `password_hash()`, set `$service['passwordHash']` in `config.php` to
> `'multi'` (new or changed passwords only) or `'migrate'` (also converts MD5 passwords at login).
> Accounts stored that way cannot log in to upstream Textcube — read sections 5–6 of
> [MIGRATION.md](./documents/MIGRATION.md) before moving a database back.
>
> For a public site, turn on `password_hash()` storage as above and serve the blog over HTTPS.
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

## Textcube-NG

Textcube-NG is a community-maintained continuation of Textcube 1.10.10, porting the codebase from
PHP 5.6 (the last version supported by upstream) to PHP 8.5. Ports for PHP 7.4, 8.2 and 8.4 are maintained
alongside this one.
This package requires PHP 8.5 (the installer checks for 8.5.0 or later). The PHP 8.4 port differs only in
handling what PHP 8.5 deprecates.

A **legacy edition for existing hosting on PHP 5.5 – 7.3** (MySQL 5.0+ / MariaDB 5.1+) is released separately
with the same changes as `Textcube-NG-1.10.10+php55.r4.zip`.

### Key Changes from Upstream Textcube 1.10.10

- **PHP 8.5 compliance**: deprecated/removed syntax fixed across the codebase (PHP4-style constructors,
  dynamic properties, `${}` interpolation, `strftime()`, brace array access, mysqli procedural API, etc.).
- **Security fixes**: see [SECURITY.md](./documents/SECURITY.md) for the items resolved in r1–r4
  (authorization, CSRF, SQL injection, XSS, SSRF, session handling, upload execution and more).
- **Emoji (4-byte characters)**: new installs on MySQL 5.6+ / MariaDB 10.0+ use utf8mb4. Existing sites can
  convert from the checkup page (back up the database first; see section 10 of [MIGRATION.md](./documents/MIGRATION.md)).
- **Categories**: three or more levels (maximum depth set in category management, default 3), with category
  lists, feeds and post counts covering all subcategories.
- **Libraries**: PHPMailer 5.x → 6.9 (existing mail calls keep working); OpenID 2.0 removed and
  OpenID Connect added (opt-in, off by default); jpgraph → inline SVG chart; jQuery 3.7.1,
  jQuery UI 1.13.3, Lodash 4.18.1, TinyMCE 7.9.3, CodeMirror 5.65.21; Dojo and MooTools removed.
- **DBMS**: MySQL/MariaDB, PostgreSQL, Cubrid and SQLite 3 with native or PDO drivers
  (`$database['driver'] = 'pdo'`).

Behavior that differs from upstream (schema additions, plugin and extension API, DBMS limits, backup import,
trusted proxies, OIDC identifiers, etc.) is listed in section 8 of [MIGRATION.md](./documents/MIGRATION.md)
(Korean). The full modification history is in [CHANGELOG.md](./documents/CHANGELOG.md).

### Upgrading an Existing Installation

Existing installations can move to Textcube-NG by replacing the files; the database, `config.php`,
skins, plugins and attachments carry over. After replacing the files:

1. Run the checkup (`{blog URL}/checkup`). It applies the additive schema changes and adds the
   `plugins/` and `attach/` execution-blocking rules to an existing `.htaccess`.
2. Run `php cleanup_legacy_assets.php` in the root from the command line to preview, then `--apply`.
   It removes files that only older versions had (old static assets, PHPMailer 5.x, the OpenID 2.0
   library, the jpgraph `plugins/StatGraph/count/` folder, CodeMirror's node.js-only files). Files whose
   content was changed are skipped; delete those by hand. The script can be deleted afterwards.

Coming from an earlier Textcube-NG release (r3 or before): also follow section 10 of
[MIGRATION.md](./documents/MIGRATION.md) — the `.user.ini` and cache rules in an existing `.htaccess` are not
rewritten automatically, and CL_Moblog, ST_MediaLog (and ED_tinyMCE after the cleanup script) need
"re-check and unblock" once on the plugin page.

Step-by-step procedures (drop-in, migrating via backup import, moving back to upstream, checklist) are in
[MIGRATION.md](./documents/MIGRATION.md) (Korean).

## Security Status (Summary)

| Severity | Open | Item |
|----------|------|------|
| HIGH     | 1    | Unsalted MD5 password storage by default (opt-in `password_hash()`) |
| MEDIUM   | 0    | — |
| LOW      | 1    | Static asset residual risk (Bootstrap 3.4.1 in two skins is end-of-life; reCAPTCHA and Google Maps scripts cannot carry SRI) |
| INFO     | 0    | — |

Resolved items, grouped by type and marked as vulnerability patches or newly added defenses, are in
[SECURITY.md](./documents/SECURITY.md) (Korean).

## REQUIREMENTS (CURRENT VERSION — PHP 8.5 Port)

* Web servers (Need at least one environment)
  * Apache 2.4 or above
    * fancyURL support with mod_rewrite module
  * Nginx 1.1 or above
  * IIS 7.0 or above
    * with URL Rewrite Module 2.0
* Language
  * **PHP 8.5**
    * Required extensions: iconv, GD, mbstring, json, mysqli or pdo_mysql
* Database Management System (Need at least one environment)
  * **MySQL 5.7.6+ or 8.0+** with UTF-8 character set and collation setting
  * MariaDB 10.2 or above
  * (MySQL / MariaDB) new installs store emoji (utf8mb4); existing installs convert from the checkup page
  * PostgreSQL, Cubrid, SQLite 3 (with limits; SQLite is not recommended for production —
    see section 8 of [MIGRATION.md](./documents/MIGRATION.md))
    * For the Cubrid PHP 8.x modules (native and PDO), see
      [deokio/php8_cubrid](https://github.com/deokio/php8_cubrid).

For massive service / heavy load environments, the following are strongly recommended:

* **OPcache** — built-in since PHP 5.5, replaces APC/XCache (both removed/abandoned)
* **pecl/memcache** with a Memcached server — for session storage and query cache
  * The official pecl/memcache does not build on PHP 8.5; use the
    [websupport-sk fork](https://github.com/websupport-sk/pecl-memcache) (confirmed working;
    build from source or install it with the Debian/Ubuntu package manager).
  * Enable in `config.php`: `$service['memcached'] = true;` and set
    `$memcached['server'] = 'hostname';` / `$memcached['port'] = 11211;`.
  * Behind NAT or port-forwarding, set `$serviceURL` in `config.php` to the *external* URL
    (including the port). Cached skin fragments and feeds store the service URL and are shared via
    memcached; otherwise the internal host/port can appear in responses.

### Tested Environments

* Full-site tests: PHP 7.4 / 8.2 / 8.4 / 8.5 with MySQL 5.7 and Apache 2.4.
* Database adapter tests (native and PDO drivers, including malicious-input cases):
  Cubrid 11.4.6, PostgreSQL 16, MariaDB 11.4, SQLite 3.
* **Not tested with this port**: IIS, Nginx (configuration is documented but untested).

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

### THIS PORT
* [MIGRATION.md](./documents/MIGRATION.md) — upgrading, moving back, behavior differences (Korean)
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
