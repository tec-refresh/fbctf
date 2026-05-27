# FBCTF PHP 8.x Modernization — Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Port FBCTF from Hack/HHVM 3.21 on Ubuntu 16.04 to PHP 8.3 on modern, architecture-agnostic Docker Compose infrastructure.

**Architecture:** Replace HHVM with PHP-FPM 8.3, convert all 107 Hack source files to PHP 8, replace AsyncMysqlConnectionPool with PDO, convert XHP templates to plain PHP HTML output, modernize all Docker images to use official multi-arch images (ARM64+x86_64), and update the frontend build to Node.js 20 + Dart Sass.

**Tech Stack:** PHP 8.3-FPM, nginx (stable), MySQL 8.0, Memcached 1.6, Node.js 20 LTS, Grunt, Docker Compose v2

**Branch:** `v2` (will be set as default on GitHub)

**Multi-arch constraint:** All Docker images must use official multi-arch base images (`php:8.3-fpm`, `nginx:stable`, `mysql:8.0`, `memcached:1.6`) — no `amd64`-only or Ubuntu-based custom images. No architecture-specific binaries in provisioning scripts.

---

## Phase 1: Infrastructure & Foundation

### Task 1: MySQL 8.0 Schema Compatibility

MySQL 8.0 rejects `DEFAULT 0` on `TIMESTAMP` columns (unless `explicit_defaults_for_timestamps` is on) and the schema uses `latin1`. Fix the schema for MySQL 8.0 compatibility and upgrade charset to `utf8mb4`.

**Files:**
- Modify: `database/schema.sql`

- [ ] **Step 1: Fix timestamp defaults**

In `database/schema.sql`, replace all `DEFAULT 0` on timestamp columns with `DEFAULT CURRENT_TIMESTAMP`, and bare `timestamp NOT NULL` (with no default) with `timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP`. MySQL 8.0 strict mode rejects `0` as a timestamp default.

Apply these changes throughout the file:

```sql
-- BEFORE:
`created_ts` timestamp NOT NULL DEFAULT 0,
-- AFTER:
`created_ts` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,

-- BEFORE:
`last_score` timestamp NOT NULL,
-- AFTER:
`last_score` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,

-- BEFORE:
`ts` timestamp NOT NULL,
-- AFTER:
`ts` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,

-- BEFORE:
`ts` timestamp NULL,
-- AFTER (keep NULL):
`ts` timestamp NULL DEFAULT NULL,

-- BEFORE:
`use_ts` timestamp NOT NULL,
-- AFTER:
`use_ts` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,

-- BEFORE:
`last_access_ts` timestamp NOT NULL,
-- AFTER:
`last_access_ts` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
```

- [ ] **Step 2: Upgrade charset to utf8mb4**

Replace `DEFAULT CHARSET=latin1` with `DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci` on all CREATE TABLE statements. Also update the database creation line:

```sql
-- BEFORE:
CREATE DATABASE /*!32312 IF NOT EXISTS*/ `fbctf` /*!40100 DEFAULT CHARACTER SET latin1 */;
-- AFTER:
CREATE DATABASE /*!32312 IF NOT EXISTS*/ `fbctf` /*!40100 DEFAULT CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci */;
```

And update the SET NAMES at the top:
```sql
-- BEFORE:
/*!40101 SET NAMES utf8 */;
-- AFTER:
/*!40101 SET NAMES utf8mb4 */;
```

- [ ] **Step 3: Remove DROP DATABASE**

The `DROP DATABASE IF EXISTS` line is dangerous in a Docker entrypoint context (MySQL init scripts run on first start; a restart would wipe data). Remove it:

```sql
-- DELETE this line:
/*!40000 DROP DATABASE IF EXISTS `fbctf`*/;
```

- [ ] **Step 4: Commit**

```bash
git add database/schema.sql
git commit -m "fix: update schema for MySQL 8.0 compatibility

Replace timestamp DEFAULT 0 with DEFAULT CURRENT_TIMESTAMP, upgrade
charset from latin1 to utf8mb4, and remove dangerous DROP DATABASE."
```

---

### Task 2: Core PHP Foundation — HackCompat + Utils

Convert the Hack compatibility functions and Utils class to PHP 8.3. These are imported by every other file via autoload, so they must be converted first.

**Files:**
- Modify: `src/Utils.php`

- [ ] **Step 1: Convert Utils.php to PHP 8.3**

Replace the entire file with:

```php
<?php declare(strict_types=1);

function must_have_idx(?array $arr, string|int $idx): mixed {
    if ($arr === null) {
        throw new RuntimeException('Container is null');
    }
    if (!array_key_exists($idx, $arr)) {
        throw new RuntimeException(sprintf('Index %s not found in container', (string)$idx));
    }
    return $arr[$idx];
}

function must_have_string(?array $arr, string|int $idx): string {
    $result = must_have_idx($arr, $idx);
    if (!is_string($result)) {
        throw new RuntimeException(sprintf('Expected %s to be a string', (string)$idx));
    }
    return $result;
}

function must_have_int(?array $arr, string|int $idx): int {
    $result = must_have_idx($arr, $idx);
    if (!is_int($result)) {
        throw new RuntimeException(sprintf('Expected %s to be an int', (string)$idx));
    }
    return $result;
}

function must_have_bool(?array $arr, string|int $idx): bool {
    $result = must_have_idx($arr, $idx);
    if (!is_bool($result)) {
        throw new RuntimeException(sprintf('Expected %s to be a bool', (string)$idx));
    }
    return $result;
}

function idx(?array $arr, string|int $idx, mixed $default = null): mixed {
    if ($arr === null || !array_key_exists($idx, $arr)) {
        return $default;
    }
    return $arr[$idx];
}

function firstx(iterable $t): mixed {
    foreach ($t as $v) {
        return $v;
    }
    throw new RuntimeException('Expected non-empty collection');
}

function starts_with(string $haystack, string $needle): bool {
    return str_starts_with($haystack, $needle);
}

function ends_with(string $haystack, string $needle): bool {
    return str_ends_with($haystack, $needle);
}

function time_ago(string $ts): string {
    $ts_epoc = strtotime($ts);
    $elapsed = time() - $ts_epoc;

    if ($elapsed < 1) {
        return tr('just now');
    }

    $w = [
        24 * 60 * 60 => tr('d'),
        60 * 60 => tr('hr'),
        60 => tr('min'),
        1 => tr('sec'),
    ];
    $w_s = [
        tr('d') => tr('ds'),
        tr('hr') => tr('hrs'),
        tr('min') => tr('mins'),
        tr('sec') => tr('secs'),
    ];
    foreach ($w as $secs => $str) {
        $d = $elapsed / $secs;
        if ($d >= 1) {
            $r = round($d);
            return $r . ' ' . ($r > 1 ? $w_s[$str] : $str) . ' ' . tr('ago');
        }
    }
    return '';
}

class Utils {
    private function __construct() {}

    public static function getGET(): array {
        return $_GET;
    }

    public static function getPOST(): array {
        return $_POST;
    }

    public static function getSERVER(): array {
        return $_SERVER;
    }

    public static function getFILES(): array {
        return $_FILES;
    }

    public static function redirect(string $location): void {
        header('Location: ' . $location);
    }

    public static function request_response(
        string $result,
        string $msg,
        string $redirect,
    ): string {
        $response_data = [
            'result' => $result,
            'message' => $msg,
            'redirect' => $redirect,
        ];
        return json_encode($response_data);
    }

    public static function hint_response(string $msg, string $result): string {
        $response_data = ['hint' => $msg, 'result' => $result];
        return json_encode($response_data);
    }

    public static function ok_response(string $msg, string $redirect): string {
        return self::request_response('OK', $msg, $redirect);
    }

    public static function error_response(
        string $msg,
        string $redirect,
    ): string {
        return self::request_response('ERROR', $msg, $redirect);
    }
}
```

Key changes:
- `<?hh // strict` → `<?php declare(strict_types=1);`
- `MUST_MODIFY` const removed (no longer needed — will use `null`)
- `Map<>` in Utils methods → plain `array` (returns `$_GET` etc. directly)
- `idx()` function defined here (was a Hack built-in)
- Hack generic type params removed from `must_have_idx`, etc.
- `invariant()` calls → `throw new RuntimeException()`
- `starts_with`/`ends_with` → PHP 8's `str_starts_with`/`str_ends_with`

- [ ] **Step 2: Commit**

```bash
git add src/Utils.php
git commit -m "refactor: convert Utils.php and Hack stdlib functions to PHP 8.3

Replace Hack generics, Map types, invariant() calls, and MUST_MODIFY
sentinel with PHP 8 equivalents. Provides must_have_idx, idx, and other
compatibility functions used throughout the codebase."
```

---

### Task 3: Database Layer — Db.php

Replace HHVM's `AsyncMysqlConnectionPool` with a PDO singleton. Every model depends on this.

**Files:**
- Modify: `src/Db.php`

- [ ] **Step 1: Rewrite Db.php for PDO**

```php
<?php declare(strict_types=1);

class Db {
    private string $settings_file = '../settings.ini';
    private ?array $config = null;
    private static ?Db $instance = null;
    private ?PDO $conn = null;

    public static function getInstance(): Db {
        if (self::$instance === null) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    private function __construct() {
        $this->config = parse_ini_file($this->settings_file);
    }

    private function __clone(): void {}

    public static function getDatabaseStats(): array {
        $db = self::getInstance();
        $conn = $db->getConnection();
        return [
            'server_info' => $conn->getAttribute(PDO::ATTR_SERVER_INFO),
            'server_version' => $conn->getAttribute(PDO::ATTR_SERVER_VERSION),
        ];
    }

    public function getBackupCmd(): string {
        $usr = must_have_idx($this->config, 'DB_USERNAME');
        $pwd = must_have_idx($this->config, 'DB_PASSWORD');
        $db = must_have_idx($this->config, 'DB_NAME');
        $backup_cmd =
            'mysqldump --add-drop-database -u ' .
            escapeshellarg($usr) .
            ' --password=' .
            escapeshellarg($pwd) .
            ' ' .
            escapeshellarg($db);
        return $backup_cmd;
    }

    public function getRestoreCmd(): string {
        $usr = must_have_idx($this->config, 'DB_USERNAME');
        $pwd = must_have_idx($this->config, 'DB_PASSWORD');
        $db = must_have_idx($this->config, 'DB_NAME');
        $restore_cmd =
            'mysql -u ' .
            escapeshellarg($usr) .
            ' --password=' .
            escapeshellarg($pwd) .
            ' ' .
            escapeshellarg($db);
        return $restore_cmd;
    }

    public function getConnection(): PDO {
        if ($this->conn === null) {
            $this->connect();
        }
        return $this->conn;
    }

    public function disconnect(): void {
        $this->conn = null;
    }

    public function isConnected(): bool {
        return $this->conn !== null;
    }

    private function connect(): void {
        $host = must_have_idx($this->config, 'DB_HOST');
        $port = must_have_idx($this->config, 'DB_PORT');
        $db_name = must_have_idx($this->config, 'DB_NAME');
        $username = must_have_idx($this->config, 'DB_USERNAME');
        $password = must_have_idx($this->config, 'DB_PASSWORD');

        $dsn = sprintf('mysql:host=%s;port=%s;dbname=%s;charset=utf8mb4', $host, $port, $db_name);
        $this->conn = new PDO($dsn, $username, $password, [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES => false,
            PDO::ATTR_PERSISTENT => true,
        ]);
    }

    public function query(string $sql, array $params = []): PDOStatement {
        $conn = $this->getConnection();
        $stmt = $conn->prepare($sql);
        $stmt->execute($params);
        return $stmt;
    }

    public function lastInsertId(): string {
        return $this->getConnection()->lastInsertId();
    }

    public function escapeString(string $value): string {
        return trim($this->getConnection()->quote($value), "'");
    }
}
```

Key changes:
- `AsyncMysqlConnectionPool` → PDO with persistent connections
- `async genConnection(): Awaitable<AsyncMysqlConnection>` → `getConnection(): PDO`
- `genConnect()` async → `connect()` synchronous
- Added `query()` helper that prepares + executes (used by models)
- Added `lastInsertId()` for INSERT operations
- `MUST_MODIFY` sentinel → `null` with nullable types

- [ ] **Step 2: Commit**

```bash
git add src/Db.php
git commit -m "refactor: replace AsyncMysqlConnectionPool with PDO in Db.php

Synchronous PDO singleton with prepared statement helper. Persistent
connections via PDO::ATTR_PERSISTENT. UTF-8mb4 charset. All async
methods converted to synchronous equivalents."
```

---

### Task 4: Cache Model + Base Model

Convert the in-memory Cache class and the abstract Model base class that all 24 models inherit from.

**Files:**
- Modify: `src/models/Cache.php`
- Modify: `src/models/Model.php`

- [ ] **Step 1: Convert Cache.php**

```php
<?php declare(strict_types=1);

class Cache {
    private array $CACHE = [];

    public function __construct() {}

    public function setCache(string $key, mixed $value): void {
        $this->CACHE[$key] = $value;
    }

    public function getCache(string $key): mixed {
        if (array_key_exists($key, $this->CACHE)) {
            return $this->CACHE[$key];
        }
        return false;
    }

    public function deleteCache(string $key): void {
        unset($this->CACHE[$key]);
    }

    public function flushCache(): void {
        $this->CACHE = [];
    }
}
```

- [ ] **Step 2: Convert Model.php**

```php
<?php declare(strict_types=1);

abstract class Model {
    protected static ?Db $db = null;
    protected static ?Memcached $mc = null;
    protected static ?Memcached $mc_write = null;
    protected static string $MC_KEY = '';
    protected static int $MC_EXPIRE = 0;

    protected static ?Cache $CACHE = null;

    protected static array $MC_KEYS = [];

    protected static function getDb(): PDO {
        if (self::$db === null) {
            self::$db = Db::getInstance();
        }
        return self::$db->getConnection();
    }

    protected static function getMc(): Memcached {
        if (self::$mc === null) {
            $config = parse_ini_file('../../settings.ini');
            $cluster = must_have_idx($config, 'MC_HOST');
            $port = must_have_idx($config, 'MC_PORT');
            $host = is_array($cluster) ? $cluster[array_rand($cluster)] : $cluster;
            self::$mc = new Memcached();
            self::$mc->addServer($host, (int)$port);
        }
        return self::$mc;
    }

    public static function getMemcachedStats(): mixed {
        $stats = [];
        $mc = self::getMcWrite();
        foreach ($mc->getServerList() as $node) {
            $mc_node = new Memcached();
            $mc_node->addServer($node['host'], $node['port']);
            $stats[$node['host']] = $mc_node->getStats();
        }
        return $stats;
    }

    protected static function getMcWrite(): Memcached {
        if (self::$mc_write === null) {
            $config = parse_ini_file('../../settings.ini');
            $cluster = must_have_idx($config, 'MC_HOST');
            $port = must_have_idx($config, 'MC_PORT');
            self::$mc_write = new Memcached();
            if (is_array($cluster)) {
                foreach ($cluster as $node) {
                    self::$mc_write->addServer($node, (int)$port);
                }
            } else {
                self::$mc_write->addServer($cluster, (int)$port);
            }
        }
        return self::$mc_write;
    }

    protected static function setMCRecords(string $key, mixed $records): void {
        self::getCacheClassObject();
        $cache_key = static::$MC_KEY . (static::$MC_KEYS[$key] ?? '');

        self::writeMCCluster($cache_key, $records);
        self::$CACHE->setCache($cache_key, $records);
    }

    protected static function getMCRecords(string $key): mixed {
        self::getCacheClassObject();
        $cache_key = static::$MC_KEY . (static::$MC_KEYS[$key] ?? '');

        $local_cache_result = self::$CACHE->getCache($cache_key);
        if ($local_cache_result !== false) {
            return $local_cache_result;
        } else {
            $mc = self::getMc();
            $mc_result = $mc->get($cache_key);
            if ($mc_result !== false) {
                self::$CACHE->setCache($cache_key, $mc_result);
            }
            return $mc_result;
        }
    }

    public static function invalidateMCRecords(?string $key = null): void {
        self::getCacheClassObject();

        if ($key === null) {
            foreach (static::$MC_KEYS as $key_name => $mc_key) {
                $cache_key = static::$MC_KEY . (static::$MC_KEYS[$key_name] ?? '');
                self::invalidateMCCluster($cache_key);
                self::$CACHE->deleteCache($cache_key);
            }
        } else {
            $cache_key = static::$MC_KEY . (static::$MC_KEYS[$key] ?? '');
            self::invalidateMCCluster($cache_key);
            self::$CACHE->deleteCache($cache_key);
        }
    }

    public static function flushMCCluster(): bool {
        $mc = self::getMcWrite();
        $status = false;
        foreach ($mc->getServerList() as $node) {
            $mc_node = new Memcached();
            $mc_node->addServer($node['host'], $node['port']);
            $flush_status = $mc_node->flush(0);
            if ($flush_status === true) {
                $status = true;
            }
        }
        return $status;
    }

    protected static function writeMCCluster(
        string $cache_key,
        mixed $records,
    ): void {
        $mc = self::getMcWrite();
        foreach ($mc->getServerList() as $node) {
            $mc_node = new Memcached();
            $mc_node->addServer($node['host'], $node['port']);
            $mc_node->set($cache_key, $records, static::$MC_EXPIRE);
        }
    }

    protected static function invalidateMCCluster(string $cache_key): void {
        $mc = self::getMcWrite();
        foreach ($mc->getServerList() as $node) {
            $mc_node = new Memcached();
            $mc_node->addServer($node['host'], $node['port']);
            $mc_node->delete($cache_key);
        }
    }

    public static function getCacheClassObject(): Cache {
        if (self::$CACHE === null) {
            self::$CACHE = new Cache();
        }
        return self::$CACHE;
    }

    public static function deleteLocalCache(?string $key = null): void {
        self::getCacheClassObject();

        if (get_called_class() === 'Model') {
            self::$CACHE->flushCache();
        } else if ($key === null) {
            foreach (static::$MC_KEYS as $key_name => $mc_key) {
                $cache_key = static::$MC_KEY . (static::$MC_KEYS[$key_name] ?? '');
                self::$CACHE->deleteCache($cache_key);
            }
        } else {
            $cache_key = static::$MC_KEY . (static::$MC_KEYS[$key] ?? '');
            self::$CACHE->deleteCache($cache_key);
        }
    }
}
```

Key changes:
- `MUST_MODIFY` → `null` with nullable types
- `Map<string, string> $MC_KEYS` → `array $MC_KEYS`
- `async genDb(): Awaitable<AsyncMysqlConnection>` → `getDb(): PDO`
- `$MC_KEYS->get($key)` → `$MC_KEYS[$key] ?? ''`
- `Pair { }` in Cache → simple array assignment

- [ ] **Step 3: Commit**

```bash
git add src/models/Cache.php src/models/Model.php
git commit -m "refactor: convert Cache and Model base classes to PHP 8.3

Replace Hack Map/Pair types with arrays, MUST_MODIFY with null,
async genDb() with synchronous getDb() returning PDO."
```

---

### Task 5: RedirectException + SessionUtils + Language

Convert the exception classes, session handler, and translation system.

**Files:**
- Modify: `src/RedirectException.php`
- Modify: `src/SessionUtils.php`
- Modify: `src/language/language.php`

- [ ] **Step 1: Convert RedirectException.php**

```php
<?php declare(strict_types=1);

class RedirectException extends Exception {
    private string $path;
    private int $statusCode;

    public function __construct(string $path, int $statusCode) {
        $this->path = $path;
        $this->statusCode = $statusCode;
        parent::__construct();
    }

    public function getPath(): string {
        return $this->path;
    }

    public function getStatusCode(): int {
        return $this->statusCode;
    }
}

class AdminRedirectException extends RedirectException {
    public function __construct() {
        parent::__construct('/index.php?p=admin', 302);
    }
}

class IndexRedirectException extends RedirectException {
    public function __construct() {
        parent::__construct('/index.php', 302);
    }
}

class RegistrationRedirectException extends RedirectException {
    public function __construct() {
        parent::__construct('/index.php?page=registration', 302);
    }
}

class LoginRedirectException extends RedirectException {
    public function __construct() {
        parent::__construct('/index.php?page=login', 302);
    }
}

class InternalErrorRedirectException extends RedirectException {
    public function __construct() {
        parent::__construct('/index.php?page=error', 500);
    }
}

class NotFoundRedirectException extends RedirectException {
    public function __construct() {
        parent::__construct('/error.php', 404);
    }
}

class GameRedirectException extends RedirectException {
    public function __construct() {
        parent::__construct('/index.php', 302);
    }
}
```

- [ ] **Step 2: Convert SessionUtils.php**

```php
<?php declare(strict_types=1);

class SessionUtils {
    private static string $s_name = 'FBCTF';
    private static int $s_lifetime = 3600;
    private static bool $s_secure = true;
    private static bool $s_httponly = true;
    private static string $s_path = '/';

    private function __construct() {}

    public static function sessionStart(): void {
        Session::cleanup(self::$s_lifetime);
        session_set_save_handler(
            [__CLASS__, 'open'],
            [__CLASS__, 'close'],
            [__CLASS__, 'read'],
            [__CLASS__, 'write'],
            [__CLASS__, 'destroy'],
            [__CLASS__, 'gc'],
        );
        session_name(self::$s_name);
        session_set_cookie_params(
            self::$s_lifetime,
            self::$s_path,
            must_have_string(Utils::getSERVER(), 'SERVER_NAME'),
            self::$s_secure,
            self::$s_httponly,
        );
        session_start();
        setcookie(
            self::$s_name,
            session_id(),
            time() + self::$s_lifetime,
            self::$s_path,
            must_have_string(Utils::getSERVER(), 'SERVER_NAME'),
            self::$s_secure,
            self::$s_httponly,
        );
    }

    public static function sessionRefresh(): void {
        session_regenerate_id(true);
    }

    public function open(string $path, string $name): bool {
        return true;
    }

    public function close(): bool {
        return true;
    }

    public function read(string $cookie): string {
        return Session::sessionDataIfExist($cookie);
    }

    public function write(string $cookie, string $data): bool {
        $session_exists = Session::sessionExist($cookie);
        if ($session_exists) {
            Session::update($cookie, $data);
            if (strpos($data, 'team_id') !== false) {
                Session::setTeamId($cookie, $data);
            }
        } else {
            Session::create($cookie, $data);
        }
        return true;
    }

    public function destroy(string $cookie): bool {
        Session::delete($cookie);
        return true;
    }

    public function gc(int $maxlifetime): bool {
        Session::cleanup($maxlifetime);
        return true;
    }

    public static function sessionSet(string $name, string $value): void {
        $_SESSION[$name] = $value;
    }

    public static function sessionLogout(): void {
        $params = session_get_cookie_params();
        setcookie(
            session_name(),
            '',
            time() - 42000,
            $params["path"],
            $params["domain"],
            $params["secure"],
            $params["httponly"],
        );
        session_destroy();

        throw new IndexRedirectException();
    }

    public static function sessionActive(): bool {
        return array_key_exists('team_id', $_SESSION);
    }

    public static function enforceLogin(): void {
        if (!self::sessionActive()) {
            throw new LoginRedirectException();
        }
    }

    public static function enforceAdmin(): void {
        if (!array_key_exists('admin', $_SESSION)) {
            throw new LoginRedirectException();
        }
    }

    public static function sessionAdmin(): bool {
        return array_key_exists('admin', $_SESSION);
    }

    public static function sessionTeam(): int {
        return intval(must_have_string($_SESSION, 'team_id'));
    }

    public static function sessionTeamName(): string {
        return must_have_string($_SESSION, 'name');
    }

    public static function CSRFToken(): string {
        return must_have_string($_SESSION, 'csrf_token');
    }
}
```

Key changes:
- All `\HH\Asio\join(Session::genXxx())` → `Session::xxx()` (synchronous calls)
- `array()` syntax → `[]`
- Removed `HH_IGNORE_ERROR` comments

- [ ] **Step 3: Convert language.php**

```php
<?php declare(strict_types=1);

require_once(__DIR__ . '/../../vendor/autoload.php');

$lang = null;

function tr_start(): void {
    $config = Configuration::get('language');
    $language = $config->getValue();
    $document_root = must_have_string(Utils::getSERVER(), 'DOCUMENT_ROOT');
    if (preg_match('/^[^,;]+$/', $language) &&
        file_exists($document_root . "/language/lang_" . $language . ".php")) {
        include($document_root . "/language/lang_" . $language . ".php");
    } else {
        include($document_root . "/language/lang_en.php");
        error_log(
            "\nWarning: Selected language ({$language}) has no translation file. English used instead.",
        );
    }
    global $lang;
    $lang = $translations;
}

function tr(string $word): string {
    global $lang;
    if ($lang !== null && array_key_exists($word, $lang)) {
        return $lang[$word];
    } else {
        error_log("\nWarning: '{$word}' has no translation. Using English version.");
        return $word;
    }
}
```

- [ ] **Step 4: Commit**

```bash
git add src/RedirectException.php src/SessionUtils.php src/language/language.php
git commit -m "refactor: convert RedirectException, SessionUtils, and language system to PHP 8.3

Remove async/Awaitable, HH\\Asio\\join calls, Hack constructor promotion,
and HH_IGNORE_ERROR comments."
```

---

### Task 6: Router + index.php + error.php

Convert the request routing layer.

**Files:**
- Modify: `src/Router.php`
- Modify: `src/index.php`
- Modify: `src/error.php`

- [ ] **Step 1: Convert Router.php**

```php
<?php declare(strict_types=1);

class Router {
    public static function route(): string {
        tr_start();
        $page = idx(Utils::getGET(), 'p');
        if (!is_string($page)) {
            $page = 'index';
        }
        $ajax = ($page_ajax = Utils::getGET()['ajax'] ?? null) === 'true';
        $modal = Utils::getGET()['modal'] ?? null;

        if ($ajax) {
            return self::routeAjax($page);
        } else if ($modal !== null) {
            return self::routeModal($page, (string)$modal);
        } else {
            Control::runAutoRunScript();
            return self::routeNormal($page);
        }
    }

    private static function routeModal(
        string $page,
        string $modal,
    ): string {
        SessionUtils::sessionStart();
        switch ($page) {
            case 'action':
                return (new ActionModalController())->render($modal);
            case 'tutorial':
                return (new TutorialModalController())->render($modal);
            case 'country':
                return (new CountryModalController())->render($modal);
            case 'scoreboard':
                return (new ScoreboardModalController())->render($modal);
            case 'team':
                return (new TeamModalController())->render($modal);
            case 'command-line':
                return (new CommandLineModalController())->render($modal);
            case 'choose-logo':
                return (new ChooseLogoModalController())->render($modal);
            default:
                throw new NotFoundRedirectException();
        }
    }

    private static function routeAjax(string $page): string {
        SessionUtils::sessionStart();
        switch ($page) {
            case 'index':
                return (new IndexAjaxController())->handleRequest();
            case 'admin':
                SessionUtils::enforceLogin();
                SessionUtils::enforceAdmin();
                return (new AdminAjaxController())->handleRequest();
            case 'game':
                SessionUtils::enforceLogin();
                return (new GameAjaxController())->handleRequest();
            default:
                throw new NotFoundRedirectException();
        }
    }

    private static function routeNormal(string $page): string {
        SessionUtils::sessionStart();
        switch ($page) {
            case 'admin':
                SessionUtils::enforceLogin();
                SessionUtils::enforceAdmin();
                return (new AdminController())->render();
            case 'index':
                return (new IndexController())->render();
            case 'game':
                SessionUtils::enforceLogin();
                return (new GameboardController())->render();
            case 'view':
                return (new ViewModeController())->render();
            case 'logout':
                SessionUtils::sessionLogout();
                throw new RuntimeException('should not reach here');
            default:
                throw new NotFoundRedirectException();
        }
    }

    public static function getRequestedPage(): string {
        $page = idx(Utils::getGET(), 'page') ?: idx(Utils::getGET(), 'p');
        if (!is_string($page)) {
            $page = 'index';
        }
        return (string)$page;
    }

    public static function isRequestAjax(): bool {
        return (Utils::getGET()['ajax'] ?? null) === 'true';
    }

    public static function isRequestModal(): bool {
        return (Utils::getGET()['modal'] ?? null) !== null;
    }

    public static function isRequestRouter(): bool {
        return self::getRequestedPage() !== "index";
    }
}
```

Key changes:
- All `async function genXxx(): Awaitable<T>` → `function xxx(): T`
- `Awaitable<:xhp>` → `string` (controllers now return HTML strings)
- `$map->get('key')` → `$array['key'] ?? null`
- `tuple()` removed (only used in AjaxController, handled there)
- `invariant(false, ...)` → `throw new RuntimeException(...)`

- [ ] **Step 2: Convert index.php**

```php
<?php declare(strict_types=1);

require_once('../vendor/autoload.php');

function init(): void {
    try {
        $response = Router::route();
        echo $response;
    } catch (RedirectException $e) {
        error_log(
            'RedirectException: (' . get_class($e) . ') ' . $e->getTraceAsString(),
        );
        http_response_code($e->getStatusCode());
        Utils::redirect($e->getPath());
    }
}

init();
```

- [ ] **Step 3: Convert error.php**

```php
<?php declare(strict_types=1);

header('Error-Redirect: true');

$document_root = $_SERVER['DOCUMENT_ROOT'] ?? '';
$error_page = $document_root . '/static/html/error.html';

if (file_exists($error_page)) {
    include($error_page);
} else {
    echo '<h1>Error</h1><p>An error occurred.</p>';
}
```

- [ ] **Step 4: Commit**

```bash
git add src/Router.php src/index.php src/error.php
git commit -m "refactor: convert Router, index.php, and error.php to PHP 8.3

All async routing removed. Controllers now return HTML strings instead
of XHP objects. HH\\Asio\\join entry point replaced with direct call."
```

---

## Phase 2: Models

All 24 model files follow the same conversion pattern. The conversion rules are:

1. `<?hh // strict` → `<?php declare(strict_types=1);`
2. `async function genFoo(): Awaitable<T>` → `function foo(): T`
3. `$db = await self::genDb();` → `$db = Db::getInstance();`
4. `$result = await $db->queryf('SELECT ... WHERE x = %s', $val);` → `$result = $db->query('SELECT ... WHERE x = ?', [$val]);`
5. `$result->numRows()` → `$result->rowCount()`
6. `$result->mapRows()` → `$result->fetchAll()`
7. `$result->mapRows()[0]` → `$result->fetch()`
8. `await \HH\Asio\va($a, $b)` → sequential: `$a_result = ...; $b_result = ...;`
9. `Map<string, string>` → `array`
10. `Vector { ... }` → `[ ... ]`
11. `invariant($cond, $msg)` → `if (!$cond) { throw new RuntimeException($msg); }`
12. `<<__Override>>` → remove (PHP doesn't have this attribute in the same way; `#[\Override]` is PHP 8.3+ but optional)
13. `await $db->multiQuery($queries)` → loop with `$db->query()` for each
14. `$db->escapeString($val)` → use prepared statement params instead
15. Rename `gen` prefix: `genFoo` → `foo`, `genAllTeams` → `allTeams` or `getAllTeams`, etc.

### Task 7: Convert Models — Batch 1 (Core data models)

These models are imported by many others and should be converted first.

**Files:**
- Modify: `src/models/Configuration.php`
- Modify: `src/models/Session.php`
- Modify: `src/models/Logo.php`
- Modify: `src/models/Country.php`
- Modify: `src/models/Category.php`

- [ ] **Step 1: Convert all 5 files**

Apply the conversion rules above to each file. For each model:
- Replace `$db = await self::genDb()` → `$db = Db::getInstance()`
- Replace `await $db->queryf('... %s ...', $val)` → `$db->query('... ? ...', [$val])`
- Replace `$result->mapRows()` → `$result->fetchAll()`
- Replace `$result->mapRows()[0]` → `$result->fetch()`
- Replace `$result->numRows()` → `$result->rowCount()`
- Remove `async` and `Awaitable<>` from all function signatures
- Rename all `genXxx` methods to `xxx` (drop the `gen` prefix)
- Replace `Map<string, string>` parameter types with `array`
- Replace `static::$MC_KEYS->get($key)` with `static::$MC_KEYS[$key] ?? ''`

**IMPORTANT for Configuration model:** The `gen('field_name')` method is called extensively throughout the codebase (e.g., `Configuration::gen('language')`). Rename it to `Configuration::get('field_name')`.

**IMPORTANT for Session model:** The session handler in `SessionUtils.php` calls Session methods synchronously. All `genXxx` methods become `xxx` (e.g., `Session::genSessionExist` → `Session::sessionExist`, `Session::genCleanup` → `Session::cleanup`).

- [ ] **Step 2: Commit**

```bash
git add src/models/Configuration.php src/models/Session.php src/models/Logo.php src/models/Country.php src/models/Category.php
git commit -m "refactor: convert Configuration, Session, Logo, Country, Category models to PHP 8.3

Hack async/Awaitable removed, HHVM queryf replaced with PDO prepared
statements, Map types replaced with arrays."
```

---

### Task 8: Convert Models — Batch 2 (Team + MultiTeam + scoring)

**Files:**
- Modify: `src/models/Team.php`
- Modify: `src/models/MultiTeam.php`
- Modify: `src/models/ScoreLog.php`
- Modify: `src/models/HintLog.php`
- Modify: `src/models/FailureLog.php`

- [ ] **Step 1: Convert all 5 files**

Apply the same conversion rules as Task 7. Special attention for Team.php:
- `teamFromRow(Map<string, string> $row)` → `teamFromRow(array $row)`
- `await \HH\Asio\va($db->queryf(...), Logo::genSetUsed(...))` → two sequential calls
- `$queries = Vector { sprintf(...), sprintf(...) }; await $db->multiQuery($queries);` → loop each query through `$db->query()` with proper parameterized SQL (replace `sprintf` + `$db->escapeString()` with `?` params)
- `genTeamData` returns `Vector<Map<string, string>>` → returns `array`

For Team.php's `delete` method, convert the Vector of sprintf queries to individual prepared statements:

```php
public static function delete(int $team_id): void {
    $db = Db::getInstance();

    $result = $db->query('SELECT logo FROM teams WHERE id = ?', [$team_id]);
    $row = $result->fetch();
    if (!$row) { throw new RuntimeException('Expected exactly one result'); }
    $logo = (string)$row['logo'];

    Logo::setUsed($logo, false);

    $db->query('DELETE FROM teams WHERE id = ? AND protected = 0 LIMIT 1', [$team_id]);
    $db->query('DELETE FROM registration_tokens WHERE team_id = ?', [$team_id]);
    $db->query('DELETE FROM teams_oauth WHERE team_id = ?', [$team_id]);
    $db->query('DELETE FROM scores_log WHERE team_id = ?', [$team_id]);
    $db->query('DELETE FROM hints_log WHERE team_id = ?', [$team_id]);
    $db->query('DELETE FROM failures_log WHERE team_id = ?', [$team_id]);
    $db->query('DELETE FROM activity_log WHERE subject = ?', ["Team:$team_id"]);
    // ...cache invalidation calls...
}
```

- [ ] **Step 2: Commit**

```bash
git add src/models/Team.php src/models/MultiTeam.php src/models/ScoreLog.php src/models/HintLog.php src/models/FailureLog.php
git commit -m "refactor: convert Team, MultiTeam, and scoring models to PHP 8.3

Replace multiQuery with individual prepared statements, Vector with
arrays, async patterns with synchronous calls."
```

---

### Task 9: Convert Models — Batch 3 (remaining models)

**Files:**
- Modify: `src/models/Level.php`
- Modify: `src/models/Attachment.php`
- Modify: `src/models/Link.php`
- Modify: `src/models/Announcement.php`
- Modify: `src/models/ActivityLog.php`
- Modify: `src/models/GameLog.php`
- Modify: `src/models/Control.php`
- Modify: `src/models/Token.php`
- Modify: `src/models/Integration.php`
- Modify: `src/models/Progressive.php`
- Modify: `src/models/Exportable.php`
- Modify: `src/models/Importable.php`

- [ ] **Step 1: Convert all 12 files**

Apply the same conversion rules. `Exportable` and `Importable` are interfaces — just change the header and remove Hack-specific type syntax.

- [ ] **Step 2: Commit**

```bash
git add src/models/
git commit -m "refactor: convert remaining 12 models to PHP 8.3

Level, Attachment, Link, Announcement, ActivityLog, GameLog, Control,
Token, Integration, Progressive, Exportable, Importable."
```

---

## Phase 3: Controllers & Views

### Task 10: Abstract Controllers

Convert the base Controller and AjaxController classes. These establish the pattern for all concrete controllers.

**Files:**
- Modify: `src/controllers/Controller.php`
- Modify: `src/controllers/ajax/AjaxController.php`

- [ ] **Step 1: Convert Controller.php**

The key change: `genRender()` returned `Awaitable<:xhp>` (XHP objects). Now `render()` returns `string` (HTML).

```php
<?php declare(strict_types=1);

abstract class Controller {
    abstract protected function getTitle(): string;
    abstract protected function getFilters(): array;
    abstract protected function getPages(): array;

    abstract protected function renderBody(string $page): string;

    public function renderBranding(): string {
        $custom_branding = Configuration::get('custom_logo');
        $custom_byline = Configuration::get('custom_byline');
        $custom_logo_image = Configuration::get('custom_logo_image');

        if ($custom_branding->getValue() === '0') {
            $branding_text = htmlspecialchars(tr((string)$custom_byline->getValue()));
            return '<div class="branding"><span class="branding-text">' . $branding_text . '</span></div>';
        } else {
            $branding_text = htmlspecialchars((string)$custom_byline->getValue());
            $branding_logo = htmlspecialchars((string)$custom_logo_image->getValue());
            return '<div class="branding custom-branding">' .
                '<img src="' . $branding_logo . '" alt="Logo" />' .
                '<span class="branding-text">' . $branding_text . '</span></div>';
        }
    }

    public function render(): string {
        $page = $this->processRequest();
        $body = $this->renderBody($page);
        $config = Configuration::get('language');
        $language = $config->getValue();
        if (!preg_match('/^\w{2}$/', $language)) {
            $language = 'en';
        }
        $document_root = must_have_string(Utils::getSERVER(), 'DOCUMENT_ROOT');
        $language_style = '';
        if (file_exists(
            $document_root . '/static/css/locals/' . $language . '/style.css',
        )) {
            $language_style = '<link rel="stylesheet" href="static/css/locals/' .
                htmlspecialchars($language) . '/style.css" />';
        }
        $title = htmlspecialchars($this->getTitle());
        return '<!DOCTYPE html>' .
            '<html lang="' . htmlspecialchars($language) . '">' .
            '<head>' .
            '<meta http-equiv="Cache-control" content="no-cache" />' .
            '<meta http-equiv="Expires" content="-1" />' .
            '<meta charset="UTF-8" />' .
            '<meta name="viewport" content="width=device-width, initial-scale=1" />' .
            '<title>' . $title . '</title>' .
            '<link rel="icon" type="image/png" href="static/img/favicon.png" />' .
            '<link rel="stylesheet" href="static/css/fb-ctf.css" />' .
            $language_style .
            '</head>' .
            $body .
            '</html>';
    }

    private function processRequest(): string {
        $input_methods = ['POST' => INPUT_POST, 'GET' => INPUT_GET];
        $method = must_have_string(Utils::getSERVER(), 'REQUEST_METHOD');

        $filter = idx($this->getFilters(), $method);
        if ($filter === null) {
            return 'none';
        }

        $input_method = must_have_idx($input_methods, $method);
        $page = 'main';

        $parameters = filter_input_array($input_method, $filter);

        $page = idx($parameters, 'page', 'main');
        if (!in_array($page, $this->getPages())) {
            $page = 'main';
        }

        return $page;
    }
}
```

- [ ] **Step 2: Convert AjaxController.php**

```php
<?php declare(strict_types=1);

abstract class AjaxController {
    abstract protected function getFilters(): array;
    abstract protected function getActions(): array;

    abstract protected function handleAction(
        string $action,
        array $params,
    ): string;

    public function handleRequest(): string {
        [$action, $params] = $this->processRequest();
        return $this->handleAction($action, $params);
    }

    private function processRequest(): array {
        $input_methods = ['POST' => INPUT_POST, 'GET' => INPUT_GET];
        $method = must_have_string(Utils::getSERVER(), 'REQUEST_METHOD');

        $filter = idx($this->getFilters(), $method);
        if ($filter === null) {
            return ['none', []];
        }

        $input_method = must_have_idx($input_methods, $method);
        $parameters = filter_input_array($input_method, $filter);

        $action = idx($parameters, 'action', 'main');
        if (!in_array($action, $this->getActions())) {
            $action = 'none';
        }

        return [$action, $parameters];
    }
}
```

Key changes:
- `tuple($a, $b)` → `[$a, $b]`
- `genHandleAction` → `handleAction`
- `genHandleRequest` → `handleRequest`

- [ ] **Step 3: Commit**

```bash
git add src/controllers/Controller.php src/controllers/ajax/AjaxController.php
git commit -m "refactor: convert Controller and AjaxController base classes to PHP 8.3

XHP return types replaced with HTML strings. Async removed. Hack tuple
replaced with PHP array destructuring."
```

---

### Task 11: Convert Concrete Controllers

Convert all remaining controller files. Each one follows the same pattern:
- `genRenderBody(string $page): Awaitable<:xhp>` → `renderBody(string $page): string`
- XHP markup → HTML string concatenation with `htmlspecialchars()` for dynamic values
- `<<__Override>>` → remove
- `await` calls → direct calls
- `genHandleAction` → `handleAction`

**Files:**
- Modify: `src/controllers/AdminController.php`
- Modify: `src/controllers/GameboardController.php`
- Modify: `src/controllers/IndexController.php`
- Modify: `src/controllers/ViewModeController.php`
- Modify: `src/controllers/ajax/AdminAjaxController.php`
- Modify: `src/controllers/ajax/GameAjaxController.php`
- Modify: `src/controllers/ajax/IndexAjaxController.php`
- Modify: `src/controllers/exporters/JSONExporterController.php`
- Modify: `src/controllers/importers/BinaryImporterController.php`
- Modify: `src/controllers/importers/JSONImporterController.php`

- [ ] **Step 1: Convert AdminController.php and GameboardController.php**

These are the two largest controllers. AdminController.php is ~600 lines of XHP generation for the admin panel. GameboardController.php generates the game board UI.

For XHP-heavy sections, convert systematically:
```hack
// BEFORE (Hack XHP):
$select = <select class="not_configuration" name="entity_id" disabled={true} />;
$select->appendChild(<option value="0" selected={true}>{tr('Auto')}</option>);
return $select;

// AFTER (PHP string):
$html = '<select class="not_configuration" name="entity_id" disabled>';
$html .= '<option value="0" selected>' . htmlspecialchars(tr('Auto')) . '</option>';
return $html . '</select>';
```

- [ ] **Step 2: Convert remaining controllers**

Apply the same patterns to IndexController, ViewModeController, all Ajax controllers, exporters, and importers. The Ajax controllers return JSON strings (not XHP), so they're simpler — just remove async.

- [ ] **Step 3: Convert modal controllers**

**Files:**
- Modify: `src/controllers/modals/ModalController.php`
- Modify: `src/controllers/modals/ActionModalController.php`
- Modify: `src/controllers/modals/ChooseLogoModalController.php`
- Modify: `src/controllers/modals/CommandLineModalController.php`
- Modify: `src/controllers/modals/CountryModalController.php`
- Modify: `src/controllers/modals/ScoreboardModalController.php`
- Modify: `src/controllers/modals/TeamModalController.php`
- Modify: `src/controllers/modals/TutorialModalController.php`

Same conversion pattern. Each modal's `genRender($modal): Awaitable<:xhp>` becomes `render($modal): string`.

- [ ] **Step 4: Commit**

```bash
git add src/controllers/
git commit -m "refactor: convert all concrete controllers to PHP 8.3

Admin, Gameboard, Index, ViewMode controllers plus all Ajax, modal,
exporter, and importer controllers. XHP replaced with HTML strings."
```

---

### Task 12: Convert Views (gameboard modules) + Static + Scripts

**Files:**
- Modify: `src/inc/gameboard/modules/controller.php`
- Modify: `src/inc/gameboard/modules/leaderboard.php`
- Modify: `src/inc/gameboard/modules/leaderboard-viewmode.php`
- Modify: `src/inc/gameboard/modules/filter.php`
- Modify: `src/inc/gameboard/modules/game-clock.php`
- Modify: `src/inc/gameboard/modules/activity.php`
- Modify: `src/inc/gameboard/modules/activity-viewmode.php`
- Modify: `src/inc/gameboard/modules/announcements.php`
- Modify: `src/inc/gameboard/modules/teams.php`
- Modify: `src/inc/gameboard/listview.php`
- Modify: `src/inc/gameboard/loading.php`
- Modify: `src/static/svg/map/world.php`
- Modify: `src/static/svg/map/world-view.php`
- Modify: `src/scripts/autorun.php`
- Modify: `src/scripts/bases.php`
- Modify: `src/scripts/liveimport.php`
- Modify: `src/scripts/progressive.php`

- [ ] **Step 1: Convert ModuleController base class**

```php
<?php declare(strict_types=1);

abstract class ModuleController {
    abstract public function render(): string;

    public function sendRender(): void {
        echo $this->render();
    }
}
```

- [ ] **Step 2: Convert all gameboard modules**

Each module's `genRender(): Awaitable<:xhp>` becomes `render(): string`. Example for leaderboard.php:

```php
<?php declare(strict_types=1);

require_once($_SERVER['DOCUMENT_ROOT'] . '/../vendor/autoload.php');

class LeaderboardModuleController extends ModuleController {
    public function render(): string {
        SessionUtils::sessionStart();
        SessionUtils::enforceLogin();

        tr_start();

        $my_team = MultiTeam::team(SessionUtils::sessionTeam());
        $my_rank = Team::myRank(SessionUtils::sessionTeam());
        $gameboard = Configuration::get('gameboard');

        $leaderboard_items = '';
        if ($gameboard->getValue() === '1') {
            $leaders = MultiTeam::leaderboard();
            $rank = 1;
            $l_max = (count($leaders) > 5) ? 5 : count($leaders);
            for ($i = 0; $i < $l_max; $i++) {
                $team = $leaders[$i];
                $logo_model = $team->getLogoModel();
                if ($logo_model->getCustom()) {
                    $image = '<img class="icon--badge" src="' . htmlspecialchars($logo_model->getLogo()) . '" />';
                } else {
                    $iconbadge = '#icon--badge-' . htmlspecialchars($logo_model->getName());
                    $image = '<svg class="icon--badge"><use href="' . $iconbadge . '" /></svg>';
                }

                $team_name = htmlspecialchars($team->getName());
                $team_points = htmlspecialchars((string)$team->getPoints());
                $rank_label = htmlspecialchars(tr('Rank'));
                $pts_label = htmlspecialchars(tr('pts'));

                $leaderboard_items .=
                    '<li class="fb-user-card">' .
                    '<div class="user-avatar">' . $image . '</div>' .
                    '<div class="player-info">' .
                    '<h6>' . $team_name . '</h6>' .
                    '<span class="player-rank">' . $rank_label . '&nbsp;' . $rank . '</span>' .
                    '<br />' .
                    '<span class="player-score">' . $team_points . '&nbsp;' . $pts_label . '</span>' .
                    '</div></li>';
                $rank++;
            }
        }

        if ($my_team->getVisible() === true) {
            $leaderboard_limit = Configuration::get('leaderboard_limit');
            if ($my_rank >= intval($leaderboard_limit->getValue())) {
                $my_rank = intval($leaderboard_limit->getValue()) + 1 . "+";
            }
        } else {
            $my_rank = "N/A";
        }

        $my_team_name = htmlspecialchars($my_team->getName());
        $my_points = htmlspecialchars((string)$my_team->getPoints());

        return
            '<div>' .
            '<header class="module-header"><h6>' . htmlspecialchars(tr('Leaderboard')) . '</h6></header>' .
            '<div class="module-content">' .
            '<div class="fb-section-border">' .
            '<div class="module-top player-info">' .
            '<h5 class="player-name">' . $my_team_name . '</h5>' .
            '<span class="player-rank">' . htmlspecialchars(tr('Your Rank')) . ': ' . $my_rank . '</span>' .
            '<br />' .
            '<span class="player-score">' . htmlspecialchars(tr('Your Score')) . ': ' . $my_points . '&nbsp;' . htmlspecialchars(tr('pts')) . '</span>' .
            '</div>' .
            '<div class="module-scrollable leaderboard-info"><ul>' . $leaderboard_items . '</ul></div>' .
            '</div></div></div>';
    }
}

$leaderboard_generated = new LeaderboardModuleController();
$leaderboard_generated->sendRender();
```

Apply the same pattern to all other module files, world.php, world-view.php, and the scripts (autorun.php, bases.php, liveimport.php, progressive.php).

- [ ] **Step 3: Commit**

```bash
git add src/inc/ src/static/ src/scripts/
git commit -m "refactor: convert gameboard modules, SVG views, and scripts to PHP 8.3

XHP templates replaced with HTML string output. All async removed.
Module controllers now return strings via render()."
```

---

### Task 13: Convert Language Files

All 20 language files just have `<?hh` headers and define a `$translations` array. Minimal change needed.

**Files:**
- Modify: all `src/language/lang_*.php` files

- [ ] **Step 1: Convert all language files**

For each file, change only the header:
```php
// BEFORE:
<?hh
// AFTER:
<?php
```

The body of each file is just `$translations = array(...)` which is valid PHP.

```bash
find src/language -name 'lang_*.php' -exec sed -i '' 's/^<?hh$/<?php/' {} +
```

- [ ] **Step 2: Commit**

```bash
git add src/language/
git commit -m "refactor: convert language files from Hack to PHP headers"
```

---

## Phase 4: Infrastructure

### Task 14: Docker Compose + PHP-FPM Dockerfile

Create the modern multi-arch Docker infrastructure.

**Files:**
- Modify: `docker-compose.yml`
- Create: `extra/php-fpm/Dockerfile`
- Modify: `Dockerfile` (single-container)
- Modify: `extra/settings.ini.example`

- [ ] **Step 1: Write docker-compose.yml**

```yaml
services:
  php-fpm:
    restart: always
    build:
      context: .
      dockerfile: extra/php-fpm/Dockerfile
    depends_on:
      mysql:
        condition: service_healthy
      cache:
        condition: service_started
    volumes:
      - attachments:/var/www/fbctf/attachments
      - customlogos:/var/www/fbctf/src/data/customlogos
    environment:
      DB_HOST: mysql
      DB_PORT: "3306"
      DB_NAME: fbctf
      DB_USERNAME: ctf
      DB_PASSWORD: ctf
      MC_HOST: cache
      MC_PORT: "11211"
    expose:
      - "9000"

  nginx:
    restart: always
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
    restart: always
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
    expose:
      - "3306"
    healthcheck:
      test: ["CMD", "mysqladmin", "ping", "-h", "localhost"]
      interval: 5s
      timeout: 3s
      retries: 10

  cache:
    image: memcached:1.6
    restart: always
    expose:
      - "11211"

volumes:
  mysql_data:
  attachments:
  customlogos:
```

- [ ] **Step 2: Create extra/php-fpm/Dockerfile**

```dockerfile
FROM php:8.3-fpm

RUN apt-get update && apt-get install -y --no-install-recommends \
    libmemcached-dev \
    zlib1g-dev \
    libssl-dev \
    libldap2-dev \
    unzip \
    git \
    && pecl install memcached \
    && docker-php-ext-enable memcached \
    && docker-php-ext-install pdo_mysql ldap \
    && apt-get clean && rm -rf /var/lib/apt/lists/*

COPY --from=composer:latest /usr/bin/composer /usr/bin/composer

WORKDIR /var/www/fbctf
COPY composer.json composer.lock ./
RUN composer install --no-dev --optimize-autoloader

COPY src/ src/
COPY extra/settings.ini.docker settings.ini
COPY extra/hash.php extra/hash.php

RUN mkdir -p attachments attachments/deleted src/data/customlogos \
    && chown -R www-data:www-data attachments src/data/customlogos
```

All base images (`php:8.3-fpm`, `mysql:8.0`, `memcached:1.6`, `nginx:stable`) are official multi-arch images supporting both `linux/amd64` and `linux/arm64`.

- [ ] **Step 3: Create settings.ini.docker**

```ini
DB_HOST=mysql
DB_PORT=3306
DB_NAME=fbctf
DB_USERNAME=ctf
DB_PASSWORD=ctf
MC_HOST=cache
MC_PORT=11211
```

Save as `extra/settings.ini.docker`.

- [ ] **Step 4: Update the single-container Dockerfile**

```dockerfile
FROM php:8.3-fpm

ENV DEBIAN_FRONTEND=noninteractive

RUN apt-get update && apt-get install -y --no-install-recommends \
    nginx \
    memcached \
    supervisor \
    libmemcached-dev \
    zlib1g-dev \
    libssl-dev \
    libldap2-dev \
    openssl \
    unzip \
    git \
    && pecl install memcached \
    && docker-php-ext-enable memcached \
    && docker-php-ext-install pdo_mysql ldap \
    && apt-get clean && rm -rf /var/lib/apt/lists/*

COPY --from=composer:latest /usr/bin/composer /usr/bin/composer

WORKDIR /var/www/fbctf
COPY composer.json composer.lock ./
RUN composer install --no-dev --optimize-autoloader

COPY src/ src/
COPY extra/nginx.conf /etc/nginx/sites-available/fbctf.conf
COPY extra/supervisord.conf /etc/supervisor/conf.d/fbctf.conf
COPY extra/settings.ini.example settings.ini

RUN mkdir -p /etc/nginx/certs attachments attachments/deleted src/data/customlogos \
    && openssl req -nodes -newkey rsa:2048 \
       -keyout /etc/nginx/certs/dev.key \
       -out /etc/nginx/certs/dev.csr \
       -subj "/O=Facebook CTF" \
    && openssl x509 -req -days 365 \
       -in /etc/nginx/certs/dev.csr \
       -signkey /etc/nginx/certs/dev.key \
       -out /etc/nginx/certs/dev.crt \
    && openssl dhparam -out /etc/nginx/certs/dhparam.pem 2048 \
    && ln -sf /etc/nginx/sites-available/fbctf.conf /etc/nginx/sites-enabled/fbctf.conf \
    && rm -f /etc/nginx/sites-enabled/default \
    && chown -R www-data:www-data /var/www/fbctf

EXPOSE 80 443

CMD ["/usr/bin/supervisord", "-c", "/etc/supervisor/conf.d/fbctf.conf"]
```

- [ ] **Step 5: Commit**

```bash
git add docker-compose.yml extra/php-fpm/Dockerfile Dockerfile extra/settings.ini.docker
git commit -m "feat: modernize Docker infrastructure for PHP 8.3

Multi-arch Docker Compose with php-fpm, nginx, mysql 8.0, memcached.
All images use official multi-arch bases (ARM64 + x86_64).
Single-container Dockerfile also updated."
```

---

### Task 15: nginx Configuration

Update nginx config to work with PHP-FPM instead of HHVM, and modernize TLS settings.

**Files:**
- Modify: `extra/nginx.conf` (single-server config)
- Modify: `extra/nginx/nginx.conf` (multi-server config)
- Create: `extra/nginx/Dockerfile` (rewrite)

- [ ] **Step 1: Update extra/nginx.conf**

```nginx
server_tokens off;

add_header Content-Security-Policy "default-src 'self'; script-src 'self' 'unsafe-inline' 'unsafe-eval'; img-src 'self'; style-src 'self' 'unsafe-inline' https://fonts.googleapis.com; font-src 'self'; frame-src 'self'; object-src 'none'";

server {
  listen 80;
  return 301 https://$host$request_uri;
}

server {
  listen 443 ssl;
  http2 on;

  ssl_certificate CER_FILE;
  ssl_certificate_key KEY_FILE;

  ssl_prefer_server_ciphers on;
  ssl_protocols TLSv1.2 TLSv1.3;
  ssl_ciphers ECDHE-ECDSA-AES128-GCM-SHA256:ECDHE-RSA-AES128-GCM-SHA256:ECDHE-ECDSA-AES256-GCM-SHA384:ECDHE-RSA-AES256-GCM-SHA384;

  ssl_dhparam DHPARAM_FILE;

  ssl_session_cache shared:SSL:10m;
  ssl_stapling on;
  ssl_stapling_verify on;
  resolver 8.8.8.8 8.8.4.4 valid=300s;
  resolver_timeout 5s;

  add_header X-Frame-Options DENY;
  add_header X-Content-Type-Options nosniff;
  add_header Strict-Transport-Security "max-age=31536000; includeSubdomains;";

  add_header Cache-Control "no-cache, no-store";
  add_header Pragma "no-cache";
  expires -1;

  root CTFPATH;
  index index.php;

  location ~ \.php$ {
    try_files $uri =404;
    fastcgi_pass unix:/run/php/php-fpm.sock;
    fastcgi_intercept_errors on;
    fastcgi_index index.php;
    fastcgi_param SCRIPT_FILENAME $document_root$fastcgi_script_name;
    include fastcgi_params;
  }

  error_page 400 401 402 403 404 500 /error.php;
  client_max_body_size 25M;
}
```

- [ ] **Step 2: Update extra/nginx/nginx.conf (multi-server)**

Same as above but with `fastcgi_pass HHVMSERVER:9000;` (the placeholder will be replaced with the php-fpm container hostname via sed in provision scripts). Rename placeholder from `HHVMSERVER` to `PHPFPMSERVER`:

```nginx
  location ~ \.php$ {
    try_files $uri =404;
    fastcgi_pass PHPFPMSERVER:9000;
    fastcgi_intercept_errors on;
    fastcgi_index index.php;
    fastcgi_param SCRIPT_FILENAME $document_root$fastcgi_script_name;
    include fastcgi_params;
  }
```

- [ ] **Step 3: Rewrite extra/nginx/Dockerfile**

```dockerfile
FROM nginx:stable

RUN mkdir -p /etc/nginx/certs \
    && openssl req -nodes -newkey rsa:2048 \
       -keyout /etc/nginx/certs/dev.key \
       -out /etc/nginx/certs/dev.csr \
       -subj "/O=Facebook CTF" \
    && openssl x509 -req -days 365 \
       -in /etc/nginx/certs/dev.csr \
       -signkey /etc/nginx/certs/dev.key \
       -out /etc/nginx/certs/dev.crt \
    && openssl dhparam -out /etc/nginx/certs/dhparam.pem 2048

COPY extra/nginx/nginx.conf /etc/nginx/conf.d/fbctf.conf.template
COPY src/ /var/www/fbctf/src/

RUN rm -f /etc/nginx/conf.d/default.conf \
    && sed -e 's|CTFPATH|/var/www/fbctf/src|g' \
           -e 's|CER_FILE|/etc/nginx/certs/dev.crt|g' \
           -e 's|KEY_FILE|/etc/nginx/certs/dev.key|g' \
           -e 's|DHPARAM_FILE|/etc/nginx/certs/dhparam.pem|g' \
           -e 's|PHPFPMSERVER|php-fpm|g' \
           /etc/nginx/conf.d/fbctf.conf.template > /etc/nginx/conf.d/fbctf.conf
```

- [ ] **Step 4: Commit**

```bash
git add extra/nginx.conf extra/nginx/nginx.conf extra/nginx/Dockerfile
git commit -m "feat: update nginx config for PHP-FPM and modern TLS

Replace HHVM fastcgi_pass with PHP-FPM, drop TLSv1.0/1.1, remove
deprecated 'ssl on' directive, add HTTP/2."
```

---

### Task 16: Composer + NPM Dependencies

Update dependency files for PHP 8.3 and Node.js 20.

**Files:**
- Modify: `composer.json`
- Delete: `composer.lock` (regenerate)
- Modify: `package.json`

- [ ] **Step 1: Update composer.json**

```json
{
    "name": "facebook/fbctf",
    "description": "Facebook CTF platform",
    "license": "CC-BY-NC-4.0",
    "require": {
        "php": "^8.1",
        "google/apiclient": "^2.16"
    },
    "require-dev": {
        "phpunit/phpunit": "^10.0"
    },
    "autoload": {
        "classmap": ["src/", "tests/"],
        "files": ["src/Utils.php", "src/language/language.php"]
    }
}
```

Changes:
- Removed `facebook/xhp-lib` (XHP no longer used)
- Removed `facebook/graph-sdk` (can be re-added if Facebook OAuth is needed; it requires evaluation for PHP 8.3 compat)
- Updated `google/apiclient` to `^2.16`
- Removed `phpunit/dbunit` (deprecated), `phpunit/php-code-coverage` (bundled with PHPUnit 10), `phpunit/php-invoker`
- Added `php: ^8.1` requirement
- Updated PHPUnit to `^10.0`

- [ ] **Step 2: Delete composer.lock**

```bash
rm composer.lock
```

The lock file will be regenerated on first `composer install`.

- [ ] **Step 3: Update package.json**

```json
{
  "name": "fbctf",
  "version": "2.0.0",
  "private": true,
  "devDependencies": {
    "babel-eslint": "^10.1.0",
    "babel-preset-react": "^6.24.1",
    "babelify": "^10.0.0",
    "eslint": "^8.0.0",
    "eslint-plugin-flowtype": "^8.0.0",
    "grunt": "^1.6.0",
    "grunt-browserify": "^6.0.0",
    "grunt-contrib-copy": "^1.0.0",
    "grunt-contrib-uglify": "^5.2.0",
    "grunt-contrib-watch": "^1.1.0",
    "grunt-env": "^1.0.0",
    "grunt-eslint": "^24.0.0",
    "grunt-force-task": "^2.0.0",
    "grunt-run": "^0.8.0",
    "grunt-sass": "^3.1.0"
  },
  "dependencies": {
    "d3": "^3.5.16",
    "dropkickjs": "2.1.10",
    "hoverintent-jqplugin": "^0.2.1",
    "jquery": "^3.7.0",
    "keycode": "^2.2.1",
    "sass": "^1.77.0",
    "typed.js": "^1.1.1",
    "bxslider": "4.2.6"
  }
}
```

Changes:
- `node-sass` → `sass` (Dart Sass, multi-arch compatible, no native compilation)
- `grunt-sass` updated to `^3.1.0` (uses Dart Sass)
- `jquery` updated to `^3.7.0`
- Various dev deps updated to latest compatible
- Version bumped to `2.0.0`

- [ ] **Step 4: Update Gruntfile.js for Dart Sass**

In `Gruntfile.js`, if there's a sass configuration using `node-sass`, update the implementation:

Find the sass task configuration and ensure it uses the `sass` package instead of `node-sass`. In `grunt-sass` 3.x, the implementation is passed explicitly:

```js
const sass = require('sass');

// In grunt config:
sass: {
    options: {
        implementation: sass,
        // ...
    },
    // ...
}
```

- [ ] **Step 5: Commit**

```bash
git add composer.json package.json Gruntfile.js
git rm composer.lock
git commit -m "feat: update dependencies for PHP 8.3 and Node.js 20

Remove HHVM-specific deps (xhp-lib), update PHPUnit to 10.x,
replace node-sass with Dart Sass, bump jQuery to 3.x."
```

---

### Task 17: Provisioning Scripts

Update the provision scripts for bare-metal installs on Ubuntu 22.04+ and remove HHVM-specific functions.

**Files:**
- Modify: `extra/lib.sh`
- Modify: `extra/provision.sh`
- Delete: `extra/hhvm.conf` (no longer needed)
- Delete: `extra/hhvm/Dockerfile` (replaced by php-fpm)
- Delete: `extra/hhvm/hhvm_startup.sh`
- Modify: `extra/service_startup.sh`

- [ ] **Step 1: Update extra/lib.sh**

Remove these functions:
- `install_hhvm()` — replaced by `install_php()`
- `hhvm_performance()` — no longer needed
- `install_unison()` — no longer needed

Add this function:
```bash
function install_php() {
  local __path=$1

  log "Adding PHP repository"
  package software-properties-common
  sudo DEBIAN_FRONTEND=noninteractive add-apt-repository -y ppa:ondrej/php
  package_repo_update

  log "Installing PHP 8.3 and extensions"
  package php8.3-fpm
  package php8.3-mysql
  package php8.3-memcached
  package php8.3-ldap
  package php8.3-xml
  package php8.3-mbstring
  package php8.3-curl
  package php8.3-zip

  log "PHP as system default"
  sudo update-alternatives --set php /usr/bin/php8.3

  log "Restarting PHP-FPM"
  sudo service php8.3-fpm restart

  log "PHP Version:"
  php -v
}
```

Update `install_composer()`:
```bash
function install_composer() {
  local __path=$1

  cd $__path
  dl_pipe "https://getcomposer.org/installer" | php
  php composer.phar install
  sudo mv composer.phar /usr/bin
  sudo chmod +x /usr/bin/composer.phar
}
```

Update `install_nodejs()`:
```bash
function install_nodejs() {
  log "Downloading and setting node.js version 20.x repo information"
  dl_pipe "https://deb.nodesource.com/setup_20.x" | sudo -E bash -

  log "Installing node.js"
  package nodejs
}
```

Update `install_mysql()`:
```bash
function install_mysql() {
  local __pwd=$1

  log "Installing MySQL 8.0"
  sudo DEBIAN_FRONTEND=noninteractive apt-get install -y mysql-server

  sudo service mysql restart
}
```

- [ ] **Step 2: Update extra/provision.sh**

Replace references:
- `install_hhvm` → `install_php`
- `hhvm /usr/bin/composer.phar install` → `php /usr/bin/composer.phar install`
- `hhvm_performance` section → remove entirely
- `hhvm -f "$__path/extra/hash.php"` → `php "$__path/extra/hash.php"`
- Remove `HHVM_CONFIG_PATH` variable
- `python-all-dev`, `python-setuptools`, `python-pip` → remove (Python 2 packages)
- Keep `python3-pip` if needed, otherwise remove pip/mycli section
- Replace `package php7.0-cli` in multi-server MySQL section with `package php8.3-cli`

- [ ] **Step 3: Update extra/service_startup.sh (single-container)**

```bash
#!/bin/bash
set -e

if [[ -e /root/tmp/certbot.sh ]]; then
    /bin/bash /root/tmp/certbot.sh
fi

chown -R mysql:mysql /var/lib/mysql
chown -R mysql:mysql /var/run/mysqld
chown -R mysql:mysql /var/log/mysql
chown -R www-data:www-data /var/www/fbctf

service php8.3-fpm restart
service nginx restart
service mysql restart
service memcached restart

while true; do
    sleep 5
    service php8.3-fpm status
    service nginx status
    service mysql status
    service memcached status
done
```

- [ ] **Step 4: Remove HHVM files**

```bash
git rm extra/hhvm.conf extra/hhvm/Dockerfile extra/hhvm/hhvm_startup.sh
```

- [ ] **Step 5: Remove .hhconfig**

```bash
git rm .hhconfig src/scripts/.hhconfig
```

- [ ] **Step 6: Commit**

```bash
git add extra/lib.sh extra/provision.sh extra/service_startup.sh
git commit -m "feat: update provisioning for PHP 8.3 on Ubuntu 22.04+

Remove HHVM installation, add PHP 8.3 via ondrej/php PPA, update
Node.js to 20.x, MySQL to 8.0, remove Python 2 dependencies."
```

---

### Task 18: Remaining Docker + Config Cleanup

**Files:**
- Delete: `extra/mysql/Dockerfile` (using official mysql:8.0 image)
- Delete: `extra/mysql/mysql_startup.sh`
- Delete: `extra/cache/Dockerfile` (using official memcached:1.6 image)
- Delete: `extra/cache/cache_startup.sh`
- Create: `extra/supervisord.conf` (for single-container mode)
- Modify: `extra/hash.php` (update for PHP 8)
- Modify: `.travis.yml` → remove or update
- Modify: `.dockerignore`

- [ ] **Step 1: Remove obsolete Docker files**

```bash
git rm extra/mysql/Dockerfile extra/mysql/mysql_startup.sh
git rm extra/cache/Dockerfile extra/cache/cache_startup.sh
```

- [ ] **Step 2: Create extra/supervisord.conf**

```ini
[supervisord]
nodaemon=true
logfile=/var/log/supervisord.log

[program:php-fpm]
command=/usr/local/sbin/php-fpm --nodaemonize
autostart=true
autorestart=true

[program:nginx]
command=/usr/sbin/nginx -g "daemon off;"
autostart=true
autorestart=true

[program:memcached]
command=/usr/bin/memcached -u memcache -m 64
autostart=true
autorestart=true
```

- [ ] **Step 3: Update extra/hash.php**

```php
<?php
if ($argc < 2) {
    echo "Usage: php hash.php <password>\n";
    exit(1);
}
$options = ['cost' => 12];
echo password_hash($argv[1], PASSWORD_DEFAULT, $options);
```

- [ ] **Step 4: Update .dockerignore**

```
.git
node_modules
vendor
*.gif
*.md
.travis.yml
.eslintrc
.hhconfig
Vagrantfile*
tests/
docs/
demo_levels/
```

- [ ] **Step 5: Remove .travis.yml**

Travis CI config references HHVM and is obsolete. Remove it.

```bash
git rm .travis.yml
```

- [ ] **Step 6: Commit**

```bash
git add extra/supervisord.conf extra/hash.php .dockerignore
git commit -m "chore: clean up obsolete Docker/CI files, add supervisord config

Remove Ubuntu 16.04 MySQL/cache Dockerfiles, Travis CI config, .hhconfig.
Add supervisord for single-container mode. Update hash.php for PHP 8."
```

---

## Phase 5: Verification

### Task 19: Build Verification

Verify the Docker Compose setup builds and starts cleanly.

- [ ] **Step 1: Build images**

```bash
docker compose build --no-cache
```

Expected: all 4 services build successfully with no errors.

- [ ] **Step 2: Start services**

```bash
docker compose up -d
```

Expected: all containers start. Check with `docker compose ps` — all should be "Up".

- [ ] **Step 3: Verify MySQL init**

```bash
docker compose exec mysql mysql -u ctf -pctf fbctf -e "SHOW TABLES;"
```

Expected: all tables from schema.sql are created.

- [ ] **Step 4: Verify PHP-FPM**

```bash
docker compose exec php-fpm php -v
docker compose exec php-fpm php -m | grep -E "pdo_mysql|memcached|ldap"
```

Expected: PHP 8.3.x, extensions loaded.

- [ ] **Step 5: Verify nginx + HTTPS**

```bash
curl -k https://localhost/
```

Expected: HTML response from the FBCTF login page (or redirect to login).

- [ ] **Step 6: Check for PHP errors**

```bash
docker compose logs php-fpm | grep -i "fatal\|error\|warning" | head -20
```

Fix any PHP errors found. Common issues:
- Missing `require_once` paths
- Undefined function calls (method renames missed)
- Type errors from stricter PHP 8 type checking

- [ ] **Step 7: Commit any fixes**

```bash
git add -A
git commit -m "fix: resolve PHP runtime errors found during build verification"
```

---

### Task 20: Update README and Push

**Files:**
- Modify: `README.md`

- [ ] **Step 1: Update README.md**

Update the README to reflect the v2 stack:
- Mention PHP 8.3, MySQL 8.0, Docker Compose
- Update quick start instructions to `docker compose up`
- Remove references to HHVM, Ubuntu 16.04, Vagrant
- Note ARM64 support

- [ ] **Step 2: Final commit**

```bash
git add README.md
git commit -m "docs: update README for v2 - PHP 8.3, Docker Compose, multi-arch"
```

- [ ] **Step 3: Push v2 branch**

```bash
git push -u origin v2
```

- [ ] **Step 4: Set v2 as default branch on GitHub**

```bash
gh repo edit tec-refresh/fbctf --default-branch v2
```

---

## Conversion Reference

### Method Rename Map (gen* → *)

Throughout the codebase, all `gen`-prefixed async methods drop the prefix:

| Hack (old) | PHP (new) |
|---|---|
| `Model::genDb()` | `Model::getDb()` |
| `Configuration::gen('field')` | `Configuration::get('field')` |
| `Session::genSessionExist()` | `Session::sessionExist()` |
| `Session::genCleanup()` | `Session::cleanup()` |
| `Session::genSessionDataIfExist()` | `Session::sessionDataIfExist()` |
| `Session::genCreate()` | `Session::create()` |
| `Session::genUpdate()` | `Session::update()` |
| `Session::genDelete()` | `Session::delete()` |
| `Session::genSetTeamId()` | `Session::setTeamId()` |
| `Session::genDeleteByTeam()` | `Session::deleteByTeam()` |
| `Session::genDeleteAllUnprotected()` | `Session::deleteAllUnprotected()` |
| `Team::genTeam()` | `Team::team()` |
| `Team::genAllTeams()` | `Team::getAllTeams()` |
| `Team::genCreate()` | `Team::create()` |
| `Team::genDelete()` | `Team::delete()` |
| `Team::genVerifyCredentials()` | `Team::verifyCredentials()` |
| `MultiTeam::genTeam()` | `MultiTeam::team()` |
| `MultiTeam::genLeaderboard()` | `MultiTeam::leaderboard()` |
| `Logo::genByName()` | `Logo::byName()` |
| `Logo::genSetUsed()` | `Logo::setUsed()` |
| `Country::gen()` | `Country::get()` |
| `Country::genAllAvailableCountries()` | `Country::getAllAvailableCountries()` |
| `Category::genAllCategories()` | `Category::getAllCategories()` |
| `Control::genRunAutoRunScript()` | `Control::runAutoRunScript()` |
| `Integration::genFacebookThirdPartyExists()` | `Integration::facebookThirdPartyExists()` |
| `Controller::genRender()` | `Controller::render()` |
| `Controller::genRenderBody()` | `Controller::renderBody()` |
| `Controller::genRenderBranding()` | `Controller::renderBranding()` |
| `AjaxController::genHandleRequest()` | `AjaxController::handleRequest()` |
| `AjaxController::genHandleAction()` | `AjaxController::handleAction()` |
| `ModuleController::genRender()` | `ModuleController::render()` |

### Database Query Conversion

```hack
// BEFORE: HHVM queryf with %s/%d placeholders
$db = await self::genDb();
$result = await $db->queryf('SELECT * FROM teams WHERE id = %d AND name = %s', $id, $name);
$rows = $result->mapRows();
$count = $result->numRows();

// AFTER: PDO with ? placeholders
$db = Db::getInstance();
$result = $db->query('SELECT * FROM teams WHERE id = ? AND name = ?', [$id, $name]);
$rows = $result->fetchAll();
$count = $result->rowCount();
```
