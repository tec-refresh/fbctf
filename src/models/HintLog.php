<?php declare(strict_types=1);

class HintLog extends Model {

  protected static string $MC_KEY = 'hintlog:';

  protected static array
    $MC_KEYS = ['USED_HINTS' => 'hint_level_teams'];

  private function __construct(
    private int $id,
    private string $ts,
    private int $level_id,
    private int $team_id,
    private int $penalty,
  ) {}

  public function getId(): int {
    return $this->id;
  }

  public function getTs(): string {
    return $this->ts;
  }

  public function getLevelId(): int {
    return $this->level_id;
  }

  public function getTeamId(): int {
    return $this->team_id;
  }

  public function getPenalty(): int {
    return $this->penalty;
  }

  private static function hintlogFromRow(array $row): HintLog {
    return new HintLog(
      intval(must_have_idx($row, 'id')),
      must_have_idx($row, 'ts'),
      intval(must_have_idx($row, 'level_id')),
      intval(must_have_idx($row, 'team_id')),
      intval(must_have_idx($row, 'penalty')),
    );
  }

  // Log hint request hint.
  public static function logGetHint(
    int $level_id,
    int $team_id,
    int $penalty,
  ): void {
    $db = Db::getInstance();
    $db->query(
      'INSERT INTO hints_log (ts, level_id, team_id, penalty) VALUES (NOW(), ?, ?, ?)',
      [$level_id, $team_id, $penalty],
    );
    self::invalidateMCRecords(); // Invalidate Memcached HintLog data.
  }

  public static function resetHints(): void {
    $db = Db::getInstance();
    $db->query('DELETE FROM hints_log WHERE id > 0');
  }

  // Check if there is a previous hint.
  public static function previousHint(
    int $level_id,
    int $team_id,
    bool $any_team,
    bool $refresh = false,
  ): bool {
    $mc_result = self::getMCRecords('USED_HINTS');
    if (!$mc_result || (is_countable($mc_result) && count($mc_result) === 0) || $refresh) {
      $db = Db::getInstance();
      $hints_used = [];
      $result = $db->query('SELECT level_id, team_id FROM hints_log');
      foreach ($result->fetchAll() as $row) {
        $hints_used[intval($row['level_id'])][] = intval($row['team_id']);
      }
      self::setMCRecords('USED_HINTS', $hints_used);
      if (isset($hints_used[$level_id])) {
        if ($any_team) {
          $hints_used_teams = $hints_used[$level_id];
          $team_id_key = array_search($team_id, $hints_used_teams);
          if ($team_id_key !== false) {
            unset($hints_used_teams[$team_id_key]);
          }
          return count($hints_used_teams) > 0;
        } else {
          $hints_used_teams = $hints_used[$level_id];
          $team_id_key = array_search($team_id, $hints_used_teams);
          return $team_id_key !== false;
        }
      } else {
        return false;
      }
    } else {
      if (!(is_array($mc_result))) { throw new RuntimeException('hints_used should be of type array'); }
      if (isset($mc_result[$level_id])) {
        if ($any_team) {
          $hints_used_teams = $mc_result[$level_id];
          $team_id_key = array_search($team_id, $hints_used_teams);
          if ($team_id_key !== false) {
            unset($hints_used_teams[$team_id_key]);
          }
          return count($hints_used_teams) > 0;
        } else {
          $hints_used_teams = $mc_result[$level_id];
          $team_id_key = array_search($team_id, $hints_used_teams);
          return $team_id_key !== false;
        }
      } else {
        return false;
      }
    }
  }

  // Get all hints.
  public static function allHints(): array {
    $db = Db::getInstance();
    $result = $db->query('SELECT * FROM hints_log ORDER BY ts DESC');

    $hints = [];
    foreach ($result->fetchAll() as $row) {
      $hints[] = self::hintlogFromRow($row);
    }

    return $hints;
  }

  // Get all hints by team.
  public static function allHintsByTeam(
    int $team_id,
  ): array {
    $db = Db::getInstance();
    $result = $db->query(
      'SELECT * FROM hints_log WHERE team_id = ? ORDER BY ts DESC',
      [$team_id],
    );

    $hints = [];
    foreach ($result->fetchAll() as $row) {
      $hints[] = self::hintlogFromRow($row);
    }

    return $hints;
  }

  // Get all hints by level.
  public static function allHintsByLevel(
    int $level_id,
  ): array {
    $db = Db::getInstance();
    $result = $db->query(
      'SELECT * FROM hints_log WHERE level_id = ?',
      [$level_id],
    );

    $hints = [];
    foreach ($result->fetchAll() as $row) {
      $hints[] = self::hintlogFromRow($row);
    }

    return $hints;
  }
}
