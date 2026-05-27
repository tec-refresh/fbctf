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
