<?php declare(strict_types=1);

class GameLog extends Model {
  private function __construct(
    private string $ts,
    private string $entry,
    private int $team_id,
    private int $level_id,
    private int $points,
    private string $type,
    private string $flag,
  ) {}

  public function getTs(): string {
    return $this->ts;
  }

  public function getEntry(): string {
    return $this->entry;
  }

  public function getTeamId(): int {
    return $this->team_id;
  }

  public function getLevelId(): int {
    return $this->level_id;
  }

  public function getPoints(): int {
    return $this->points;
  }

  public function getType(): string {
    return $this->type;
  }

  public function getFlag(): string {
    return $this->flag;
  }

  private static function gamelogFromRow(array $row): GameLog {
    return new GameLog(
      must_have_idx($row, 'ts'),
      must_have_idx($row, 'entry'),
      intval(must_have_idx($row, 'team_id')),
      intval(must_have_idx($row, 'level_id')),
      intval(must_have_idx($row, 'points')),
      must_have_idx($row, 'type'),
      must_have_idx($row, 'flag'),
    );
  }

  // Get all game scores.
  public static function gameLog(): array {
    $db = Db::getInstance();
    $result = $db->query(
      'SELECT ts, ? AS entry, team_id, level_id, points, type, ? AS flag FROM scores_log UNION SELECT ts, ? AS entry, team_id, level_id, 0 AS points, ? AS type, flag FROM failures_log ORDER BY ts DESC',
      ['score', '', 'failure', ''],
    );

    $gamelog = [];
    foreach ($result->fetchAll() as $row) {
      $gamelog[] = self::gamelogFromRow($row);
    }

    return $gamelog;
  }
}
