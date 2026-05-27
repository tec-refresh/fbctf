<?php declare(strict_types=1);

class Token extends Model {
  private function __construct(
    private int $id,
    private int $used,
    private int $team_id,
    private string $token,
    private string $created_ts,
    private string $use_ts,
  ) {}

  public function getId(): int {
    return $this->id;
  }

  public function getUsed(): bool {
    return $this->used === 1;
  }

  public function getTeamId(): int {
    return $this->team_id;
  }

  public function getToken(): string {
    return $this->token;
  }

  public function getCreatedTs(): string {
    return $this->created_ts;
  }

  private static function tokenFromRow(array $row): Token {
    return new Token(
      intval(must_have_idx($row, 'id')),
      intval(must_have_idx($row, 'used')),
      intval(must_have_idx($row, 'team_id')),
      must_have_idx($row, 'token'),
      must_have_idx($row, 'created_ts'),
      must_have_idx($row, 'use_ts'),
    );
  }

  private static function generate(): string {
    $token_len = 15;
    return md5(base64_encode(random_bytes($token_len)));
  }

  // Create token.
  public static function create(): void {
    $db = Db::getInstance();
    $tokens = [];
    $query = [];
    $token_number = 50;
    for ($i = 0; $i < $token_number; $i++) {
      $token = self::generate();
      $db->query(
        'INSERT INTO registration_tokens (token, created_ts, used, team_id) VALUES (?, NOW(), 0, 0)',
        [$token],
      );
    }
  }

  public static function export(): void {
    $db = Db::getInstance();
    $result = $db->query(
      'SELECT token FROM registration_tokens WHERE used = 0',
      [],
    );

    $tokens = array_map(fn($m) => $m['token'], $result->fetchAll());

    header('Content-Type: application/json;charset=utf-8');
    header('Content-Disposition: attachment; filename=tokens.json');
    print json_encode($tokens, JSON_PRETTY_PRINT);
    exit();
  }

  public static function delete(string $token): void {
    $db = Db::getInstance();
    $result = $db->query(
      'DELETE from registration_tokens WHERE token = ? LIMIT 1',
      [$token],
    );
  }

  // Get all tokens.
  public static function allTokens(): array {
    $db = Db::getInstance();
    $result = $db->query('SELECT * FROM registration_tokens', []);

    $tokens = [];
    foreach ($result->fetchAll() as $row) {
      $tokens[] = self::tokenFromRow($row);
    }

    return $tokens;
  }

  // Get all available tokens.
  public static function allAvailableTokens(): array {
    $db = Db::getInstance();
    $result = $db->query(
      'SELECT * FROM registration_tokens WHERE used = 0',
      [],
    );

    $tokens = [];
    foreach ($result->fetchAll() as $row) {
      $tokens[] = self::tokenFromRow($row);
    }

    return $tokens;
  }

  public static function check(string $token): bool {
    $db = Db::getInstance();

    $result = $db->query(
      'SELECT COUNT(*) FROM registration_tokens WHERE used = 0 AND token = ?',
      [$token],
    );

    if ($result->rowCount() > 0) {
      if (!($result->rowCount() === 1)) { throw new RuntimeException('Expected exactly one result'); }
      return (intval($result->fetch()['COUNT(*)']) > 0);
    } else {
      return false;
    }
  }

  // Use a token for a team registration.
  public static function useToken(string $token, int $team_id): void {
    $db = Db::getInstance();
    $db->query(
      'UPDATE registration_tokens SET used = 1, team_id = ?, use_ts = NOW() WHERE token = ? LIMIT 1',
      [$team_id, $token],
    );
  }
}
