# FBCTF v2

## What is FBCTF?

The Facebook CTF is a platform to host Jeopardy and "King of the Hill" style Capture the Flag competitions.

<div align="center"><img src="screencapture.gif" /></div>

## How do I use FBCTF?

* Organize a competition. This can be done with as few as two participants, all the way up to several hundred. The participants can be physically present, active online, or a combination of the two.
* Follow setup instructions below to spin up platform infrastructure.
* Enter challenges into admin page
* Have participants register as teams
* Enjoy!

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

## Quick Start (Docker Compose)

```bash
git clone https://github.com/tec-refresh/fbctf.git
cd fbctf
docker compose up -d
```

The platform will be available at `https://localhost`. Default admin credentials: `admin` / `password`.

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

## Changes from Original (v1)

This is a modernized fork of the [original Facebook CTF platform](https://github.com/facebookarchive/fbctf).

Key changes in v2:
- **PHP 8.3** replaces HHVM/Hack (all 107 source files ported from Hack to PHP)
- **PDO** replaces AsyncMysqlConnectionPool
- **Plain PHP HTML** replaces XHP templates
- **Docker Compose v2** with official multi-arch images
- **MySQL 8.0** with utf8mb4 charset
- **Node.js 20 LTS** with Dart Sass (replaces node-sass)
- **TLS 1.2/1.3** only (TLS 1.0/1.1 dropped)
- **ARM64 support** (Apple Silicon, AWS Graviton)
- Runs on any current Linux/macOS — no longer tied to Ubuntu 16.04

## Reporting an Issue

First, ensure the issue was not already reported by doing a search. If you cannot find an existing issue, create a new issue. Make the title and description as clear as possible, and include a test case or screenshot to reproduce or illustrate the problem if possible.

## License

This source code is licensed under the Creative Commons Attribution-NonCommercial 4.0 International license. View the license [here](https://github.com/tec-refresh/fbctf/blob/master/LICENSE).
