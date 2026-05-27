<?php declare(strict_types=1);

class ScoreLog extends Model {

  protected static string $MC_KEY = 'scorelog:';

  protected static array
    $MC_KEYS = [
      'LEVEL_CAPTURES' => 'capture_teams',
      'ALL_SCORES' => 'all_scores',
      'SCORES_BY_TEAM' => 'scores_by_team',
      'ALL_LEVEL_CAPTURES' => 'all_capture_teams',
    ];

  private function __construct(
    private int $id,
    private string $ts,
    private int $team_id,
    private int $points,
    private int $level_id,
    private string $type,
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

  public function getPoints(): int {
    return $this->points;
  }

  public function getLevelId(): int {
    return $this->level_id;
  }

  public function getType(): string {
    return $this->type;
  }

  private static function scorelogFromRow(array $row): ScoreLog {
    return new ScoreLog(
      intval(must_have_idx($row, 'id')),
      must_have_idx($row, 'ts'),
      intval(must_have_idx($row, 'team_id')),
      intval(must_have_idx($row, 'points')),
      intval(must_have_idx($row, 'level_id')),
      must_have_idx($row, 'type'),
    );
  }

  // Get all scores.
  public static function allScores(): array {
    $db = Db::getInstance();
    $mc_result = self::getMCRecords('ALL_SCORES');
    if (!$mc_result || count($mc_result) === 0) {
      $result =
        $db->query('SELECT * FROM scores_log ORDER BY ts DESC');

      $scores = [];
      foreach ($result->fetchAll() as $row) {
        $scores[] = self::scorelogFromRow($row);
      }

      self::setMCRecords('ALL_SCORES', $scores);
      return $scores;
    } else {
      if (!(is_array($mc_result))) { throw new RuntimeException('cache return should be an array of type ScoreLog and not null'); }
      return $mc_result;
    }
  }

  // Reset all scores.
  public static function resetScores(): void {
    $db = Db::getInstance();
    $db->query('DELETE FROM scores_log WHERE id > 0');
    self::invalidateMCRecords(); // Invalidate Memcached ScoreLog data.
    ActivityLog::invalidateMCRecords('ALL_ACTIVITY'); // Invalidate Memcached ActivityLog data.
    MultiTeam::invalidateMCRecords('ALL_TEAMS'); // Invalidate Memcached MultiTeam data.
    MultiTeam::invalidateMCRecords('POINTS_BY_TYPE'); // Invalidate Memcached MultiTeam data.
    MultiTeam::invalidateMCRecords('LEADERBOARD'); // Invalidate Memcached MultiTeam data.
    MultiTeam::invalidateMCRecords('TEAMS_BY_LEVEL'); // Invalidate Memcached MultiTeam data.
    MultiTeam::invalidateMCRecords('TEAMS_FIRST_CAP'); // Invalidate Memcached MultiTeam data.
  }

  // Check if there is a previous score. - honors team visibility
  public static function previousScore(
    int $level_id,
    int $team_id,
    bool $any_team,
    bool $refresh = false,
  ): bool {
    $mc_result = self::getMCRecords('LEVEL_CAPTURES');
    if (!$mc_result || count($mc_result) === 0 || $refresh) {
      $db = Db::getInstance();
      $level_captures = [];
      $result =
        $db->query(
          'SELECT level_id, team_id FROM scores_log LEFT JOIN teams ON scores_log.team_id = teams.id WHERE teams.visible = 1',
        );
      foreach ($result->fetchAll() as $row) {
        $level_captures[intval($row['level_id'])][] = intval($row['team_id']);
      }
      self::setMCRecords('LEVEL_CAPTURES', $level_captures);
      if (isset($level_captures[$level_id])) {
        if ($any_team) {
          $level_capture_teams = $level_captures[$level_id];
          $team_id_key = array_search($team_id, $level_capture_teams);
          if ($team_id_key !== false) {
            unset($level_capture_teams[$team_id_key]);
          }
          return count($level_capture_teams) > 0;
        } else {
          $level_capture_teams = $level_captures[$level_id];
          $team_id_key = array_search($team_id, $level_capture_teams);
          return $team_id_key !== false;
        }
      } else {
        return false;
      }
    }
    if (!(is_array($mc_result))) { throw new RuntimeException('cache return should be of type array and not null'); }
    if (isset($mc_result[$level_id])) {
      if ($any_team) {
        $level_capture_teams = $mc_result[$level_id];
        $team_id_key = array_search($team_id, $level_capture_teams);
        if ($team_id_key !== false) {
          unset($level_capture_teams[$team_id_key]);
        }
        return count($level_capture_teams) > 0;
      } else {
        $level_capture_teams = $mc_result[$level_id];
        $team_id_key = array_search($team_id, $level_capture_teams);
        return $team_id_key !== false;
      }
    } else {
      return false;
    }
  }

  // Check if there is a previous score. - ignores team visibility
  public static function allPreviousScore(
    int $level_id,
    int $team_id,
    bool $any_team,
    bool $refresh = false,
  ): bool {
    $mc_result = self::getMCRecords('ALL_LEVEL_CAPTURES');
    if (!$mc_result || count($mc_result) === 0 || $refresh) {
      $db = Db::getInstance();
      $level_captures = [];
      $result = $db->query('SELECT level_id, team_id FROM scores_log');
      foreach ($result->fetchAll() as $row) {
        $level_captures[intval($row['level_id'])][] = intval($row['team_id']);
      }
      self::setMCRecords('ALL_LEVEL_CAPTURES', $level_captures);
      if (isset($level_captures[$level_id])) {
        if ($any_team) {
          $level_capture_teams = $level_captures[$level_id];
          $team_id_key = array_search($team_id, $level_capture_teams);
          if ($team_id_key !== false) {
            unset($level_capture_teams[$team_id_key]);
          }
          return count($level_capture_teams) > 0;
        } else {
          $level_capture_teams = $level_captures[$level_id];
          $team_id_key = array_search($team_id, $level_capture_teams);
          return $team_id_key !== false;
        }
      } else {
        return false;
      }
    }
    if (!(is_array($mc_result))) { throw new RuntimeException('cache return should be of type array and not null'); }
    if (isset($mc_result[$level_id])) {
      if ($any_team) {
        $level_capture_teams = $mc_result[$level_id];
        $team_id_key = array_search($team_id, $level_capture_teams);
        if ($team_id_key !== false) {
          unset($level_capture_teams[$team_id_key]);
        }
        return count($level_capture_teams) > 0;
      } else {
        $level_capture_teams = $mc_result[$level_id];
        $team_id_key = array_search($team_id, $level_capture_teams);
        return $team_id_key !== false;
      }
    } else {
      return false;
    }
  }

  // Get all scores by team.
  public static function allScoresByTeam(
    int $team_id,
    bool $refresh = false,
  ): array {
    $db = Db::getInstance();
    $mc_result = self::getMCRecords('SCORES_BY_TEAM');
    if (!$mc_result || count($mc_result) === 0) {
      $scores = [];
      $result =
        $db->query('SELECT * FROM scores_log ORDER BY ts DESC');
      foreach ($result->fetchAll() as $row) {
        $scores[$row['team_id']][] = self::scorelogFromRow($row);
      }
      self::setMCRecords('SCORES_BY_TEAM', $scores);
      $team_scores = [];
      if (isset($scores[$team_id])) {
        $team_scores = $scores[$team_id];
        if (!(is_array($team_scores))) { throw new RuntimeException('team_scores should be an array and not null'); }
        return $team_scores;
      }
      return $team_scores;
    } else {
      if (!(is_array($mc_result))) { throw new RuntimeException('cache return should be an array of type ScoreLog and not null'); }
      $team_scores = [];
      if (isset($mc_result[$team_id])) {
        $team_scores = $mc_result[$team_id];
        if (!(is_array($team_scores))) { throw new RuntimeException('team_scores should be an array and not null'); }
        return $team_scores;
      }
      return $team_scores;
    }
  }

  // Get all scores by type.
  public static function allScoresByType(
    string $type,
  ): array {
    $db = Db::getInstance();
    $result = $db->query(
      'SELECT * FROM scores_log WHERE type = ? ORDER BY ts DESC',
      [$type],
    );

    $scores = [];
    foreach ($result->fetchAll() as $row) {
      $scores[] = self::scorelogFromRow($row);
    }

    return $scores;
  }

  // Get all scores by level.
  public static function allScoresByLevel(
    int $level_id,
  ): array {
    $db = Db::getInstance();
    $result = $db->query(
      'SELECT * FROM scores_log WHERE level_id = ?',
      [$level_id],
    );

    $scores = [];
    foreach ($result->fetchAll() as $row) {
      $scores[] = self::scorelogFromRow($row);
    }

    return $scores;
  }

  // Log successful score.
  public static function logValidScore(
    int $level_id,
    int $team_id,
    int $points,
    string $type,
  ): bool {
    $db = Db::getInstance();
    $result =
      $db->query(
        'INSERT INTO scores_log (ts, level_id, team_id, points, type) SELECT NOW(), ?, ?, ?, ? FROM DUAL WHERE NOT EXISTS (SELECT * FROM scores_log WHERE level_id = ? AND team_id = ?)',
        [$level_id, $team_id, $points, $type, $level_id, $team_id],
      );

    $captured = $result->numRowsAffected() > 0 ? true : false;

    if ($captured === true) {
      ActivityLog::captureLog($team_id, $level_id);
      self::invalidateMCRecords(); // Invalidate Memcached ScoreLog data.
      ActivityLog::invalidateMCRecords('ALL_ACTIVITY'); // Invalidate Memcached ActivityLog data.
      MultiTeam::invalidateMCRecords('ALL_TEAMS'); // Invalidate Memcached MultiTeam data.
      MultiTeam::invalidateMCRecords('POINTS_BY_TYPE'); // Invalidate Memcached MultiTeam data.
      MultiTeam::invalidateMCRecords('LEADERBOARD'); // Invalidate Memcached MultiTeam data.
      $completed_level = MultiTeam::completedLevel($level_id);
      if (count($completed_level) === 0) {
        MultiTeam::invalidateMCRecords('TEAMS_FIRST_CAP'); // Invalidate Memcached MultiTeam data.
      }
      MultiTeam::invalidateMCRecords('TEAMS_BY_LEVEL'); // Invalidate Memcached MultiTeam data.
    }

    return $captured;
  }

  public static function scoreLogUpdate(
    int $level_id,
    int $team_id,
    int $points,
    string $type,
    string $timestamp,
  ): void {
    $db = Db::getInstance();
    $db->query(
      'UPDATE scores_log SET ts = ?, level_id = ?, team_id = ?, points = ?, type = ? WHERE level_id = ? AND team_id = ?',
      [$timestamp, $level_id, $team_id, $points, $type, $level_id, $team_id],
    );
    self::invalidateMCRecords(); // Invalidate Memcached ScoreLog data.
    Control::invalidateMCRecords('ALL_ACTIVITY'); // Invalidate Memcached Control data.
    MultiTeam::invalidateMCRecords('ALL_TEAMS'); // Invalidate Memcached MultiTeam data.
    MultiTeam::invalidateMCRecords('POINTS_BY_TYPE'); // Invalidate Memcached MultiTeam data.
    MultiTeam::invalidateMCRecords('LEADERBOARD'); // Invalidate Memcached MultiTeam data.
    MultiTeam::invalidateMCRecords('TEAMS_BY_LEVEL'); // Invalidate Memcached MultiTeam data.
    MultiTeam::invalidateMCRecords('TEAMS_FIRST_CAP'); // Invalidate Memcached MultiTeam data.
  }

  public static function updateScoreLogBonus(
    int $level_id,
    int $team_id,
    int $points,
  ): void {
    $db = Db::getInstance();
    $db->query(
      'UPDATE scores_log SET ts = ts, points = ? WHERE level_id = ? AND team_id = ?',
      [$points, $level_id, $team_id],
    );
    self::invalidateMCRecords(); // Invalidate Memcached ScoreLog data.
    Control::invalidateMCRecords('ALL_ACTIVITY'); // Invalidate Memcached Control data.
    MultiTeam::invalidateMCRecords('ALL_TEAMS'); // Invalidate Memcached MultiTeam data.
    MultiTeam::invalidateMCRecords('POINTS_BY_TYPE'); // Invalidate Memcached MultiTeam data.
    MultiTeam::invalidateMCRecords('LEADERBOARD'); // Invalidate Memcached MultiTeam data.
    MultiTeam::invalidateMCRecords('TEAMS_BY_LEVEL'); // Invalidate Memcached MultiTeam data.
    MultiTeam::invalidateMCRecords('TEAMS_FIRST_CAP'); // Invalidate Memcached MultiTeam data.
  }

  public static function levelScores(
    int $level_id,
  ): array {
    $db = Db::getInstance();
    $result = $db->query(
      'SELECT * FROM scores_log WHERE level_id = ? ORDER BY ts ASC',
      [$level_id],
    );

    $scores = [];
    foreach ($result->fetchAll() as $row) {
      $scores[] = self::scorelogFromRow($row);
    }

    return $scores;
  }

  public static function levelScoreByTeam(
    int $team_id,
    int $level_id,
  ): ScoreLog {
    $db = Db::getInstance();
    $result = $db->query(
      'SELECT * FROM scores_log WHERE team_id = ? AND level_id = ?',
      [$team_id, $level_id],
    );

    return self::scorelogFromRow($result->fetch());
  }
}
