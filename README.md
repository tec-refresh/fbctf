# FBCTF v2

A modernized fork of Facebook's Capture the Flag platform, fully ported from Hack/HHVM to PHP 8.3 and containerized with Docker Compose.

<div align="center"><img src="screencapture.gif" /></div>

## What is FBCTF?

The Facebook CTF is a platform to host Jeopardy and "King of the Hill" style Capture the Flag competitions.

* Organize a competition with anywhere from two to several hundred participants
* Spin up the platform with a single `docker compose up` command
* Create quiz, flag, and base challenges via the admin panel
* Teams register, compete on an interactive world map, and track scores in real time

## Quick Start

```bash
git clone https://github.com/tec-refresh/fbctf.git
cd fbctf
docker compose up -d
```

The platform will be available at `http://localhost`. Default admin login: **admin** / **password**.

## Stack

| Component | Version |
|-----------|---------|
| PHP | 8.3 (FPM) |
| MySQL | 8.0 |
| nginx | stable |
| Memcached | 1.6 |
| Node.js | 20 LTS |
| Grunt | 1.6+ |

**Architecture:** Multi-arch (ARM64 + x86_64). Runs on Apple Silicon, AWS Graviton, and standard x86 hardware.

## Installation Options

### Docker Compose (Recommended)

Multi-container setup with separate services for PHP-FPM, nginx, MySQL, and Memcached:

```bash
docker compose up -d
```

### Single Container

All-in-one container for quick deployments:

```bash
docker build -t fbctf .
docker run -p 80:80 -p 443:443 fbctf
```

### Bare Metal (Ubuntu 22.04+)

```bash
git clone https://github.com/tec-refresh/fbctf.git
cd fbctf
source ./extra/lib.sh
quick_setup install prod
```

## Development

```bash
# Start in dev mode
docker compose up -d

# Install frontend dependencies and build
npm install
grunt
```

## What Changed in v2

This is a modernized fork of the [original Facebook CTF platform](https://github.com/facebookarchive/fbctf), which was archived and only ran on Ubuntu 16.04 with HHVM. The v2 branch is a complete modernization that makes the platform run on any current OS.

### Language & Runtime

- **Hack to PHP 8.3** — All 107 source files were ported from Facebook's Hack language (`<?hh // strict`) to standard PHP 8 (`<?php declare(strict_types=1)`)
- **HHVM removed** — Replaced with PHP-FPM 8.3. All HHVM-specific APIs replaced with PHP equivalents
- **Async to synchronous** — Hack's `async`/`Awaitable` patterns (609 occurrences) converted to synchronous PHP calls
- **XHP to HTML** — Facebook's XHP templating DSL replaced with plain PHP HTML string output across all controllers and views

### Database

- **AsyncMysqlConnectionPool to PDO** — HHVM's async MySQL driver replaced with PDO prepared statements
- **MySQL 5.5 to 8.0** — Schema updated for MySQL 8.0 strict mode (timestamp defaults, utf8mb4 charset)
- **Parameterized queries** — All `queryf()` calls with `%s`/`%d` placeholders converted to `?` parameter binding

### Infrastructure

- **Docker Compose v2** — Multi-container setup with official images (php:8.3-fpm, nginx:stable, mysql:8.0, memcached:1.6)
- **Multi-arch** — All Docker images support ARM64 and x86_64 (Apple Silicon, AWS Graviton, standard x86)
- **Frontend build** — Multi-stage Docker builds compile SCSS and JavaScript (Dart Sass replaces node-sass, Babel 7 replaces Babel 6)
- **No Ubuntu 16.04 dependency** — Runs on any OS with Docker, or bare metal on Ubuntu 22.04+

### Frontend

- **jQuery 2 to 3** — Updated jQuery with `.error()` to `.fail()` migration for removed APIs
- **Node.js 10 to 20 LTS** — Updated build toolchain
- **node-sass to Dart Sass** — Native binary dependency replaced with pure JS implementation (fixes ARM64 builds)
- **Babel 6 to 7** — `@babel/core`, `@babel/preset-react`, Flow type stripping

### PHP 8 Compatibility Fixes

- Strict type enforcement (`declare(strict_types=1)` on all files)
- `count()` guards for non-Countable values from Memcached
- `htmlspecialchars()` string casts for int arguments
- `date()`/`gmdate()` int casts for string timestamps
- Invalid regex character class fixes (`[\w-]` to `[\w\-]`)
- `FILTER_SANITIZE_STRING` replaced with `FILTER_UNSAFE_RAW`
- Session handler methods made static

### Removed / Simplified

- **LiveSync UI** — Removed from admin and gameboard (backend API retained)
- **HHVM config** — `.hhconfig`, `hhvm.conf`, HHVM Dockerfiles removed
- **Travis CI** — Removed (was HHVM-specific)
- **XHP components** — Custom XHP classes (`Fbbranding`, `Custombranding`, `EmblemCarousel`, `Svg`) removed
- **Python 2 dependencies** — Removed from provisioning scripts

### End-to-End Verified

The full game loop has been tested:

1. Admin login and configuration
2. Team creation with emblem selection
3. Quiz/flag/base level creation and activation
4. Game start with scoring enabled
5. Player registration and login
6. Answering challenges (wrong answer rejected, correct answer scores points)
7. Leaderboard and scoreboard with progressive chart
8. Admin team management, sessions, and database reset

## Reporting an Issue

First, ensure the issue was not already reported by doing a search. If you cannot find an existing issue, create a new issue. Make the title and description as clear as possible, and include a test case or screenshot to reproduce or illustrate the problem if possible.

## License

This source code is licensed under the Creative Commons Attribution-NonCommercial 4.0 International license. View the license [here](https://github.com/tec-refresh/fbctf/blob/v2/LICENSE).
