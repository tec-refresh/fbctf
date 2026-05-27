<?php declare(strict_types=1);

class FailureLog extends Model {
  private function __construct(
    private int $id,
    private string $ts,
    private int $team_id,
    private int $level_id,
    private string $flag,
  ) {}

  public function getId(): int {
    return $this->id;
  }

  public function getTs(): string {
    return $this->ts;
  }

  public function getTeamId(): int {
    return $this->team_id;
  }

  public function getLevelId(): int {
    return $this->level_id;
  }

  public function getFlag(): string {
    return $this->flag;
  }

  private static function failurelogFromRow(
    array $row,
  ): FailureLog {
    return new FailureLog(
      intval(must_have_idx($row, 'id')),
      must_have_idx($row, 'ts'),
      intval(must_have_idx($row, 'team_id')),
      intval(must_have_idx($row, 'level_id')),
      must_have_idx($row, 'flag'),
    );
  }

  // Log attempt on score.
  public static function logFailedScore(
    int $level_id,
    int $team_id,
    string $flag,
  ): void {
    $db = Db::getInstance();
    $db->query(
      'INSERT INTO failures_log (ts, level_id, team_id, flag) VALUES(NOW(), ?, ?, ?)',
      [$level_id, $team_id, $flag],
    );
  }

  // Reset all failures.
  public static function resetFailures(): void {
    $db = Db::getInstance();
    $db->query('DELETE FROM failures_log WHERE id > 0');
  }

  // Get all scores.
  public static function allFailures(): array {
    $db = Db::getInstance();
    $result =
      $db->query('SELECT * FROM failures_log ORDER BY ts DESC');

    $failures = [];
    foreach ($result->fetchAll() as $row) {
      $failures[] = self::failurelogFromRow($row);
    }

    return $failures;
  }

  // Get all scores by team.
  public static function allFailuresByTeam(
    int $team_id,
  ): array {
    $db = Db::getInstance();
    $result = $db->query(
      'SELECT * FROM failures_log WHERE team_id = ? ORDER BY ts DESC',
      [$team_id],
    );

    $failures = [];
    foreach ($result->fetchAll() as $row) {
      $failures[] = self::failurelogFromRow($row);
    }

    return $failures;
  }
}
