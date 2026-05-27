# FBCTF PHP 8.x Modernization

## Goal

Port the Facebook CTF platform from Hack/HHVM 3.21 on Ubuntu 16.04 to standard PHP 8.3 on modern infrastructure, deployable via Docker Compose on any current OS.

## Current State

- **Language:** Hack (`<?hh // strict`) — 107 files, ~33K LOC
- **Runtime:** HHVM 3.21 on Ubuntu 16.04 (Xenial)
- **Stack:** nginx, MySQL 5.5, Memcached, Node.js 10.x, Grunt
- **Hack features used:** `async`/`Awaitable`, `Map<K,V>`, `Vector<T>`, XHP templates, `AsyncMysqlConnectionPool`, `\HH\Asio\va()`, `must_have_idx()`, `invariant()`, `MUST_MODIFY` sentinel

## Target State

- **Language:** PHP 8.3 (`<?php declare(strict_types=1);`)
- **Runtime:** PHP-FPM 8.3
- **Stack:** nginx (stable), MySQL 8.0, Memcached 1.6, Node.js 20 LTS, Grunt
- **Deployment:** Docker Compose v2 (multi-container)
- **Alternative:** Bare-metal on Ubuntu 22.04/24.04 via updated provision scripts

## Code Migration

### Language Conversion (all 107 files)

| Hack | PHP 8.3 |
|------|---------|
| `<?hh // strict` | `<?php declare(strict_types=1);` |
| `Map<K,V>` | `array` (associative) |
| `Vector<T>` | `array` (indexed) |
| `array<T>` | `array` |
| Typed properties `private string $x` | Same (PHP 8 supports this) |
| Return type `): int` | Same (PHP 8 supports this) |
| `?Type` nullable | Same (PHP 8 supports this) |
| `MUST_MODIFY` sentinel | `null` with `?Type` |
| `/* HH_IGNORE_ERROR[1002] */` | Remove |

### Async to Synchronous

All async code becomes synchronous. PHP 8 has no native async support equivalent to Hack's, and the codebase doesn't benefit from true async (it's a request-response web app).

| Hack | PHP 8.3 |
|------|---------|
| `async function genFoo(): Awaitable<T>` | `function foo(): T` |
| `await $expr` | `$expr` |
| `\HH\Asio\va($a, $b, $c)` | Sequential: `$a = ...; $b = ...; $c = ...;` |
| `\HH\Asio\join(genInit())` | `init()` |

### Database Layer

Replace HHVM's `AsyncMysqlConnectionPool` with PDO.

**Db.php** becomes a PDO singleton:
- `AsyncMysqlConnectionPool` → PDO with persistent connections
- `$db->queryf('SELECT ...')` → PDO prepared statements (`$db->prepare()` + `$stmt->execute()`)
- `$result->mapRows()` → `$stmt->fetchAll(PDO::FETCH_ASSOC)`
- `$result->mapRowsTyped()` → `$stmt->fetchAll(PDO::FETCH_ASSOC)` (types handled by PHP)

All 24 model files use Db for queries and must be updated.

### XHP Templates to Plain PHP

XHP (Facebook's JSX-like templating for Hack) is replaced with plain PHP HTML output.

| Pattern | Replacement |
|---------|-------------|
| `<div class="foo">{$bar}</div>` | `'<div class="foo">' . htmlspecialchars($bar) . '</div>'` |
| `$el->appendChild($child)` | String concatenation |
| Custom components (`<fbbranding />`) | PHP helper functions returning HTML strings |
| `<x:doctype>` | `'<!DOCTYPE html>'` |
| `Awaitable<:xhp>` return type | `string` return type |
| `genRender()` methods | `render(): string` methods |

Controllers and views (`genRender()` → `render()`) return HTML strings instead of XHP objects.

### Hack Compatibility Layer

A small `src/HackCompat.php` file provides PHP implementations of Hack stdlib functions used throughout the codebase:

- `must_have_idx(array $arr, string $key): mixed` — array access with exception on missing key
- `must_have_string(array $arr, string $key): string` — typed array access
- `idx(array $arr, string $key, mixed $default = null): mixed` — safe array access with default
- `invariant(bool $condition, string $msg): void` — assertion

This avoids touching all ~609 callsites of these functions.

## Infrastructure

### Docker Compose

4 services replacing the current 4 (mysql, cache, hhvm, nginx):

```yaml
services:
  php-fpm:
    build:
      context: .
      dockerfile: extra/php-fpm/Dockerfile
    depends_on:
      - mysql
      - cache
    volumes:
      - ./src:/var/www/fbctf/src
      - ./vendor:/var/www/fbctf/vendor
      - ./settings.ini:/var/www/fbctf/settings.ini

  nginx:
    build:
      context: .
      dockerfile: extra/nginx/Dockerfile
    depends_on:
      - php-fpm
    ports:
      - "80:80"
      - "443:443"

  mysql:
    image: mysql:8.0
    environment:
      MYSQL_ROOT_PASSWORD: root
      MYSQL_DATABASE: fbctf
      MYSQL_USER: ctf
      MYSQL_PASSWORD: ctf
    volumes:
      - ./database/schema.sql:/docker-entrypoint-initdb.d/01-schema.sql
      - ./database/countries.sql:/docker-entrypoint-initdb.d/02-countries.sql
      - ./database/logos.sql:/docker-entrypoint-initdb.d/03-logos.sql
      - mysql_data:/var/lib/mysql

  cache:
    image: memcached:1.6

volumes:
  mysql_data:
```

### PHP-FPM Dockerfile

```dockerfile
FROM php:8.3-fpm

RUN apt-get update && apt-get install -y \
    libmemcached-dev zlib1g-dev libssl-dev unzip git \
    && pecl install memcached \
    && docker-php-ext-enable memcached \
    && docker-php-ext-install pdo_mysql

COPY --from=composer:latest /usr/bin/composer /usr/bin/composer

WORKDIR /var/www/fbctf
COPY composer.json composer.lock ./
RUN composer install --no-dev --optimize-autoloader

COPY . .
```

### nginx Dockerfile

```dockerfile
FROM nginx:stable

COPY extra/nginx/nginx-modern.conf /etc/nginx/conf.d/default.conf
COPY extra/nginx/certs/ /etc/nginx/certs/
```

### nginx Config Updates

- `fastcgi_pass unix:/var/run/hhvm/sock` → `fastcgi_pass php-fpm:9000`
- Remove deprecated `ssl on;` directive
- `listen 443 ssl;` replaces `listen 443;` + `ssl on;`
- Drop TLSv1.0 and TLSv1.1 (`ssl_protocols TLSv1.2 TLSv1.3;`)
- Modern cipher suite

### Provision Scripts

- Remove: `install_hhvm()`, `hhvm_performance()`, HHVM config functions
- Add: `install_php()` using ondrej/php PPA for bare-metal installs
- Update: `install_mysql()` for MySQL 8.0 (different auth plugin handling)
- Update: `install_nodejs()` for Node.js 20 LTS
- Update: `install_composer()` to use PHP directly instead of HHVM

## Frontend Build

- Node.js 10.x → Node.js 20 LTS
- `node-sass` → `sass` (Dart Sass, drop-in compatible)
- Keep Grunt and Gruntfile.js structure
- Update npm dependencies to current compatible versions

## Dependency Updates

### composer.json

| Current | Updated |
|---------|---------|
| `facebook/xhp-lib: 2.x` | Remove (XHP no longer used) |
| `facebook/graph-sdk: 5.x` | Keep or update to latest |
| `google/apiclient: ^2.0` | Update to `^2.16` |
| `phpunit/phpunit: ^5.3` | `^10.0` |
| `phpunit/dbunit: ^2.0` | Remove (deprecated) |
| `phpunit/php-code-coverage: ^3.3` | Remove (bundled with PHPUnit 10) |

### package.json

- `node-sass: ^4.9.4` → `sass: ^1.77`
- `grunt-sass: ~1.2.0` → `grunt-sass: ^3.1.0` (uses Dart Sass)
- Other deps: update to latest compatible

## File Inventory

| Area | Files | LOC | Effort |
|------|-------|-----|--------|
| Models | 24 | ~8,600 | High |
| Controllers | 20 | ~6,000 | High |
| Views/inc | 11 | ~800 | Medium |
| Core (Db, Router, Utils, etc.) | 7 | ~500 | High |
| Language files | 20 | ~17,000 | Low (mostly data) |
| Scripts (progressive, autorun, etc.) | 5 | ~200 | Medium |
| SVG/static | 2 | ~800 | Medium |
| Infrastructure (Docker, nginx, provision) | ~15 | — | Medium |
| Frontend (package.json, Gruntfile) | 2 | — | Low |
| **Total** | **~107 PHP + ~15 infra** | **~33K** | |

## Risks

1. **MySQL 5.x → 8.0 schema compatibility:** Review `database/schema.sql` for deprecated syntax (e.g., `ENGINE=MyISAM`, `TIMESTAMP DEFAULT 0`). MySQL 8.0 is stricter.
2. **XHP conversion fidelity:** Manual review needed to ensure all HTML output matches original. XHP auto-escapes; PHP strings need explicit `htmlspecialchars()`.
3. **Async → sync performance:** The concurrent `\HH\Asio\va()` calls become sequential. For a CTF platform this is fine (low-traffic, not latency-critical), but queries in hot paths like the scoreboard should be reviewed.
4. **Composer dependency compatibility:** `facebook/xhp-lib` and `facebook/graph-sdk` may have Hack-only assumptions. Need to verify or replace.

## Out of Scope

- Rewriting the frontend JavaScript (React/D3.js stays as-is)
- Adding new features
- Changing the database schema (beyond compatibility fixes)
- Authentication/authorization changes
