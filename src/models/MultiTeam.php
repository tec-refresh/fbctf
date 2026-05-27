<?php declare(strict_types=1);

class MultiTeam extends Team {

  protected static string $MC_KEY = 'multiteam:';

  protected static array
    $MC_KEYS = [
      'ALL_TEAMS' => 'all_teams',
      'LEADERBOARD' => 'leaderboard_teams',
      'LEADERBOARD_LIMIT' => 'leaderboard_limit',
      'POINTS_BY_TYPE' => 'points_by_type',
      'ALL_ACTIVE_TEAMS' => 'active_teams',
      'ALL_VISIBLE_TEAMS' => 'visible_teams',
      'TEAMS_BY_LOGO' => 'logo_teams',
      'TEAMS_BY_LEVEL' => 'level_teams',
      'TEAMS_NAMES_BY_LEVEL' => 'level_teams_names',
      'TEAMS_FIRST_CAP' => 'capture_teams',
    ];

  private static function teamArrayFromDB(
    string $query,
    int $limit = 0,
  ): array {
    $db = Db::getInstance();
    if ($limit !== 0) {
      $query .= ' LIMIT '.intval($limit);
    }

    $result = $db->query($query);

    return $result->fetchAll();
  }

  public static function allAdmins(): array {
    $admins = MultiTeam::teamArrayFromDB(
      'SELECT * FROM teams WHERE admin = 1',
    );
    $admin_teams = [];
    foreach ($admins as $admin) {
      $admin_teams[] = Team::teamFromRow($admin);
    }
    return $admin_teams;
  }

  // All teams.
  public static function allTeamsCache(
    bool $refresh = false,
  ): array {
    $mc_result = self::getMCRecords('ALL_TEAMS');
    if (!$mc_result || (is_countable($mc_result) && count($mc_result) === 0) || $refresh) {
      $all_teams = [];
      $teams = self::teamArrayFromDB('SELECT * FROM teams');
      foreach ($teams as $team) {
        $all_teams[intval($team['id'])] = Team::teamFromRow($team);
      }
      self::setMCRecords('ALL_TEAMS', $all_teams);
      return $all_teams;
    } else {
      if (!(is_array($mc_result))) { throw new RuntimeException('cache return should be of type array and not null'); }
      return $mc_result;
    }
  }

  public static function team(
    int $team_id,
    bool $refresh = false,
  ): Team {
    $all_teams = self::allTeamsCache($refresh);
    $team = $all_teams[$team_id] ?? null;
    if (!($team instanceof Team)) { throw new RuntimeException('team should be of type Team and not null'); }
    return $team;
  }

  public static function leaderboardLimit(): int {
    $limit = false;
    $limit = self::getMCRecords('LEADERBOARD_LIMIT');
    if (!$limit) {
      $limit = self::setLeaderboardLimitValue();
    }
    return intval($limit);
  }

  public static function setLeaderboardLimitValue(): int {
    $leaderboard_limit = Configuration::get('leaderboard_limit');
    self::setMCRecords('LEADERBOARD_LIMIT', $leaderboard_limit->getValue());
    return intval($leaderboard_limit->getValue());
  }

  // Leaderboard order.
  public static function leaderboard(
    bool $limit = true,
    bool $refresh = false,
  ): array {
    $leaderboard_limit = Configuration::get('leaderboard_limit');
    $leaderboard_limit_cache = self::leaderboardLimit();
    $visible_teams = self::allVisibleTeams();

    $teams_count = count($visible_teams);
    $mc_result = self::getMCRecords('LEADERBOARD');
    if (!$mc_result ||
        (is_countable($mc_result) && count($mc_result) === 0) ||
        $leaderboard_limit_cache !== intval($leaderboard_limit->getValue()) ||
        ($limit === false && is_countable($mc_result) && (count($mc_result) !== $teams_count)) ||
        $refresh) {
      if ($limit === true) {
        $teams =
          self::teamArrayFromDB(
            'SELECT * FROM teams WHERE active = 1 AND visible = 1 ORDER BY points DESC, last_score ASC',
            intval($leaderboard_limit->getValue()),
          );
      } else {
        $teams =
          self::teamArrayFromDB(
            'SELECT * FROM teams WHERE active = 1 AND visible = 1 ORDER BY points DESC, last_score ASC',
          );
      }
      $team_leaderboard = [];
      foreach ($teams as $team) {
        $team_leaderboard[] = Team::teamFromRow($team);
      }
      self::setMCRecords('LEADERBOARD', $team_leaderboard);
      return $team_leaderboard;
    } else {
      if (!(is_array($mc_result))) { throw new RuntimeException('cache return should be an array of Team and not null'); }
      return $mc_result;
    }
  }

  // Get points by type.
  public static function pointsByType(
    int $team_id,
    string $type,
    bool $refresh = false,
  ): int {
    $mc_result = self::getMCRecords('POINTS_BY_TYPE');
    if (!$mc_result || (is_countable($mc_result) && count($mc_result) === 0) || $refresh) {
      $points_by_type = [];
      $teams =
        self::teamArrayFromDB(
          'SELECT teams.id, scores_log.type, IFNULL(SUM(scores_log.points), 0) AS points FROM teams LEFT JOIN scores_log ON teams.id = scores_log.team_id GROUP BY teams.id, scores_log.type',
        );
      foreach ($teams as $team) {
        if ($team['type'] !== null) {
          if (isset($points_by_type[intval($team['id'])])) {
            $points_by_type[intval($team['id'])][$team['type']] = intval($team['points']);
          } else {
            $points_by_type[intval($team['id'])] = [$team['type'] => intval($team['points'])];
          }
        } else {
          $points_by_type[intval($team['id'])] = ['quiz' => 0, 'flag' => 0, 'base' => 0];
        }
      }
      self::setMCRecords('POINTS_BY_TYPE', $points_by_type);
      if (isset($points_by_type[$team_id])) {
        $team_points_by_type = $points_by_type[$team_id];
        if (isset($team_points_by_type[$type])) {
          return intval($team_points_by_type[$type]);
        } else {
          return 0;
        }
      } else {
        return 0;
      }
    } else {
      if (!(is_array($mc_result))) { throw new RuntimeException('cache return should be of type array and not null'); }
      if (isset($mc_result[$team_id])) {
        $team_points_by_type = $mc_result[$team_id];
        if (isset($team_points_by_type[$type])) {
          return intval($team_points_by_type[$type]);
        } else {
          return 0;
        }
      } else {
        return 0;
      }
    }
  }

  // All active teams.
  public static function allActiveTeams(
    bool $refresh = false,
  ): array {
    $mc_result = self::getMCRecords('ALL_ACTIVE_TEAMS');
    if (!$mc_result || (is_countable($mc_result) && count($mc_result) === 0) || $refresh) {
      $all_active_teams = [];
      $teams = self::teamArrayFromDB(
        'SELECT * FROM teams WHERE active = 1 ORDER BY id',
      );
      foreach ($teams as $team) {
        $all_active_teams[] = Team::teamFromRow($team);
      }
      self::setMCRecords('ALL_ACTIVE_TEAMS', $all_active_teams);
      return $all_active_teams;
    } else {
      if (!(is_array($mc_result))) { throw new RuntimeException('cache return should be an array of Team and not null'); }
      return $mc_result;
    }
  }

  // All visible teams.
  public static function allVisibleTeams(
    bool $refresh = false,
  ): array {
    $mc_result = self::getMCRecords('ALL_VISIBLE_TEAMS');
    if (!$mc_result || (is_countable($mc_result) && count($mc_result) === 0) || $refresh) {
      $all_visible_teams = [];
      $teams = self::teamArrayFromDB(
        'SELECT * FROM teams WHERE visible = 1 AND active = 1 ORDER BY id',
      );
      foreach ($teams as $team) {
        $all_visible_teams[] = Team::teamFromRow($team);
      }
      self::setMCRecords('ALL_VISIBLE_TEAMS', $all_visible_teams);
      return $all_visible_teams;
    } else {
      if (!(is_array($mc_result))) { throw new RuntimeException('cache return should be an array of Team and not null'); }
      return $mc_result;
    }
  }

  // Retrieve how many teams are using one logo.
  public static function whoUses(
    string $logo,
    bool $refresh = false,
  ): array {
    $mc_result = self::getMCRecords('TEAMS_BY_LOGO');
    if (!$mc_result || (is_countable($mc_result) && count($mc_result) === 0) || $refresh) {
      $db = Db::getInstance();
      $all_teams = self::allTeamsCache();
      $teams_by_logo = [];
      foreach ($all_teams as $team) {
        $teams_by_logo[$team->getLogo()][] = $team;
      }
      self::setMCRecords('TEAMS_BY_LOGO', $teams_by_logo);
      if ((count($teams_by_logo) !== 0) &&
          (isset($teams_by_logo[$logo]))) {
        $teams = $teams_by_logo[$logo];
        if (!(is_array($teams))) { throw new RuntimeException('teams should be an array of Team and not null'); }
        return $teams;
      } else {
        return [];
      }
    } else {
      if (!(is_array($mc_result))) { throw new RuntimeException('cache return should be of type array'); }
      if ((count($mc_result) !== 0) && (isset($mc_result[$logo]))) {
        $teams = $mc_result[$logo];
        if (!(is_array($teams))) { throw new RuntimeException('cache return should be an array of Team and not null'); }
        return $teams;
      } else {
        return [];
      }
    }
  }

  public static function completedLevel(
    int $level_id,
    bool $refresh = false,
  ): array {
    $mc_result = self::getMCRecords('TEAMS_BY_LEVEL');
    if (!$mc_result || (is_countable($mc_result) && count($mc_result) === 0) || $refresh) {
      $teams_by_completed_level = [];
      $scores =
        self::teamArrayFromDB(
          'SELECT level_id, team_id FROM scores_log WHERE level_id IS NOT NULL ORDER BY ts',
        );
      $team_scores = [];
      foreach ($scores as $score) {
        $team = self::team(intval($score['team_id']));
        $team_scores[$score['level_id']] = $team;
      }

      foreach ($team_scores as $level_id_key => $team) {
        if ($team->getActive() === true && $team->getVisible() === true) {
          $teams_by_completed_level[intval($level_id_key)][] = $team;
        }
      }
      self::setMCRecords(
        'TEAMS_BY_LEVEL',
        $teams_by_completed_level,
      );
      if (isset($teams_by_completed_level[$level_id])) {
        $teams = $teams_by_completed_level[$level_id];
        if (!(is_array($teams))) { throw new RuntimeException('teams should be an array of Team and not null'); }
        return $teams;
      } else {
        return [];
      }
    } else {
      if (!(is_array($mc_result))) { throw new RuntimeException('cache return should be of type array and not null'); }
      if (isset($mc_result[$level_id])) {
        $teams = $mc_result[$level_id];
        if (!(is_array($teams))) { throw new RuntimeException('cache return should be an array of Team and not null'); }
        return $teams;
      } else {
        return [];
      }
    }
  }

  public static function allCompletedLevels(
    bool $refresh = false,
  ): array {
    $mc_result = self::getMCRecords('TEAMS_BY_LEVEL');
    if (!$mc_result || (is_countable($mc_result) && count($mc_result) === 0) || $refresh) {
      $teams_by_completed_level = [];
      $scores =
        self::teamArrayFromDB(
          'SELECT level_id, team_id FROM scores_log WHERE level_id IS NOT NULL ORDER BY ts',
        );
      $teams_by_level = [];
      foreach ($scores as $score) {
        $teams_by_level[intval($score['level_id'])][] = self::team(intval($score['team_id']));
      }

      foreach ($teams_by_level as $level_id_key => $teams_vector) {
        foreach ($teams_vector as $team) {
          if ($team->getActive() === true && $team->getVisible() === true) {
            $teams_by_completed_level[intval($level_id_key)][] = $team;
          }
        }
      }
      self::setMCRecords(
        'TEAMS_BY_LEVEL',
        $teams_by_completed_level,
      );
      if (!(is_array($teams_by_completed_level))) { throw new RuntimeException('teams_by_completed_level should be an array of Team'); }
      return $teams_by_completed_level;
    } else {
      if (!(is_array($mc_result))) { throw new RuntimeException('cache return should be of type array and not null'); }
      return $mc_result;
    }
  }

  public static function completedLevelTeamNames(
    int $level_id,
    bool $refresh = false,
  ): array {
    $mc_result = self::getMCRecords('TEAMS_NAMES_BY_LEVEL');
    if (!$mc_result || (is_countable($mc_result) && count($mc_result) === 0) || $refresh) {
      $team_names = [];
      $teams = self::allCompletedLevels();
      if (!(is_array($teams))) { throw new RuntimeException('teams should be an array of Team'); }
      foreach ($teams as $level => $completed_arr) {
        if (!(is_array($completed_arr))) { throw new RuntimeException('completed_arr should be an array of Team'); }
        foreach ($completed_arr as $team_obj) {
          if (!($team_obj instanceof Team)) { throw new RuntimeException('team_obj should be of type Team'); }
          $team_names[$level][] = $team_obj->getName();
        }
      }
      self::setMCRecords('TEAMS_NAMES_BY_LEVEL', $team_names);
      if (isset($team_names[$level_id])) {
        $team_name = $team_names[$level_id];
        if (!(is_array($team_name))) { throw new RuntimeException('team_name should be an array of string and not null'); }
        return $team_name;
      } else {
        return [];
      }
    } else {
      if (!(is_array($mc_result))) { throw new RuntimeException('cache return should be of type array and not null'); }
      if (isset($mc_result[$level_id])) {
        $team_name = $mc_result[$level_id];
        if (!(is_array($team_name))) { throw new RuntimeException('cache return should be an array of string and not null'); }
        return $team_name;
      } else {
        return [];
      }
    }
  }

  public static function firstCapture(
    int $level_id,
    bool $refresh = false,
  ): Team {
    $mc_result = self::getMCRecords('TEAMS_FIRST_CAP');
    if (!$mc_result || (is_countable($mc_result) && count($mc_result) === 0) || $refresh) {
      $first_team_captured_by_level = [];
      $captures =
        self::teamArrayFromDB(
          'SELECT sl.level_id, sl.team_id FROM (SELECT level_id, MIN(ts) ts FROM scores_log LEFT JOIN teams ON team_id = teams.id WHERE teams.visible = 1 AND teams.active = 1 GROUP BY level_id) sl2 JOIN scores_log sl ON sl.level_id = sl2.level_id AND sl.ts = sl2.ts;',
        );
      $team_scores = [];
      foreach ($captures as $capture) {
        $team_scores[$capture['level_id']] = self::team(intval($capture['team_id']));
      }

      foreach ($team_scores as $level_id_key => $team) {
        $first_team_captured_by_level[intval($level_id_key)] = $team;
      }
      self::setMCRecords(
        'TEAMS_FIRST_CAP',
        $first_team_captured_by_level,
      );
      $team = $first_team_captured_by_level[$level_id] ?? null;
      if (!($team instanceof Team)) { throw new RuntimeException('team should be of type Team and not null'); }
      return $team;
    } else {
      if (!(is_array($mc_result))) { throw new RuntimeException('cache return should be of type array and not null'); }
      $team = $mc_result[$level_id] ?? null;
      if (!($team instanceof Team)) { throw new RuntimeException('team return should be of type Team and not null'); }
      return $team;
    }
  }

  public static function myTeamRank(
    int $team_id,
  ): array {
    $team = false;
    $rank = 1;
    $leaderboard = MultiTeam::leaderboard();
    foreach ($leaderboard as $team) {
      if ($team_id === $team->getId()) {
        return [$team, $rank];
      }
      $rank++;
    }

    if (!($team instanceof Team)) { throw new RuntimeException('team return should be of type Team and not null'); }

    return [$team, $rank];
  }

}
