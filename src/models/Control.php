<?php declare(strict_types=1);

class Control extends Model {

  protected static string $MC_KEY = 'control:';

  protected static array $MC_KEYS = ['ALL_ACTIVITY' => 'activity'];

  public static function serverAddr(): string {
    $host = gethostname();
    $ip = gethostbyname($host);
    return strval($ip);
  }

  public static function startScriptLog(
    int $pid,
    string $name,
    string $cmd,
  ): void {
    $db = Db::getInstance();
    $host = Control::serverAddr();
    $db->query(
      'INSERT INTO scripts (ts, pid, name, host, cmd, status) VALUES (NOW(), ?, ?, ?, ?, 1)',
      [$pid, $name, $host, $cmd],
    );
  }

  public static function stopScriptLog(int $pid): void {
    $db = Db::getInstance();
    $host = Control::serverAddr();
    $db->query(
      'UPDATE scripts SET status = 0, host = ? WHERE pid = ? LIMIT 1',
      [$host, $pid],
    );
  }

  public static function scriptPid(string $name): int {
    $db = Db::getInstance();
    $host = Control::serverAddr();
    $result = $db->query(
      'SELECT pid FROM scripts WHERE name = ? AND host = ? AND status = 1 LIMIT 1',
      [$name, $host],
    );
    $pid = 0;
    if ($result->rowCount() > 0) {
      $pid = intval(must_have_idx($result->fetch(), 'pid'));
    }
    return $pid;
  }

  public static function clearScriptLog(): void {
    $db = Db::getInstance();
    $host = Control::serverAddr();
    $db->query(
      'DELETE FROM scripts WHERE id > 0 AND status = 0 AND host = ?',
      [$host],
    );
  }

  public static function begin(): void {
    Announcement::deleteAll(); // Clear announcements log
    ActivityLog::deleteAll(); // Clear activity log
    Team::resetAllPoints(); // Reset all points
    ScoreLog::resetScores(); // Clear scores log
    HintLog::resetHints(); // Clear hints log
    FailureLog::resetFailures(); // Clear failures log
    self::resetBases(); // Clear bases log
    self::clearScriptLog();
    Configuration::update('registration', '0'); // Disable registration

    Announcement::createAuto('Game has started!'); // Announce game starting
    ActivityLog::createGenericLog('Game has started!'); // Log game starting
    Configuration::update('game', '1'); // Mark game as started
    Configuration::update('scoring', '1'); // Enable scoring

    // Take timestamp of start
    $start_ts = time();
    Configuration::update('start_ts', strval($start_ts));

    // Calculate timestamp of the end or game duration
    $config_end_ts = Configuration::get('end_ts');
    $end_ts = intval($config_end_ts->getValue());
    if ($end_ts === 0) {
      $config_value = Configuration::get('game_duration_value');
      $config_unit = Configuration::get('game_duration_unit');
      $duration_value = intval($config_value->getValue());
      $duration_unit = $config_unit->getValue();
      $duration = 0;
      switch ($duration_unit) {
        case 'd':
          $duration = $duration_value * 60 * 60 * 24;
          break;
        case 'h':
          $duration = $duration_value * 60 * 60;
          break;
        case 'm':
          $duration = $duration_value * 60;
          break;
      }
      $end_ts = $start_ts + $duration;
      Configuration::update('end_ts', strval($end_ts));
    } else {
      $duration_length = ($end_ts - $start_ts) / 60;
      Configuration::update(
        'game_duration_value',
        strval($duration_length),
      );
      Configuration::update('game_duration_unit', 'm');
    }

    Configuration::update('pause_ts', '0'); // Set pause to zero
    Configuration::update('game_paused', '0'); // Set game to not paused
    Configuration::update('timer', '1'); // Kick off timer
    Progressive::reset(); // Reset and kick off progressive scoreboard

    Progressive::run();
    Level::baseScoring(); // Kick off scoring for bases
  }

  public static function end(): void {
    Announcement::createAuto('Game has ended!'); // Announce game ending
    ActivityLog::createGenericLog('Game has ended!'); // Log game ending
    Configuration::update('game', '0'); // Mark game as finished and stop progressive scoreboard
    Configuration::update('scoring', '0'); // Disable scoring
    Configuration::update('start_ts', '0'); // Put timestamps to zero
    Configuration::update('end_ts', '0');
    Configuration::update('next_game', '0');
    Configuration::update('pause_ts', '0'); // Set pause to zero
    Configuration::update('timer', '0'); // Stop timer

    $pause = Configuration::get('game_paused');
    $game_paused = $pause->getValue() === '1';

    if (!$game_paused) {
      // Stop bases scoring process
      // Stop progressive scoreboard process
      Level::stopBaseScoring();
      Progressive::stop();
    } else {
      // Set game to not paused
      Configuration::update('game_paused', '0');
    }
  }

  public static function pause(): void {
    Announcement::createAuto('Game has been paused!'); // Announce game paused
    ActivityLog::createGenericLog('Game has been paused!'); // Log game paused
    Configuration::update('scoring', '0'); // Disable scoring

    $pause_ts = time();
    Configuration::update('pause_ts', strval($pause_ts)); // Set pause timestamp
    Configuration::update('game_paused', '1'); // Set gane to paused
    Configuration::update('timer', '0'); // Stop timer
    Level::stopBaseScoring(); // Stop bases scoring process
    Progressive::stop(); // Stop progressive scoreboard process
  }

  public static function unpause(): void {
    Configuration::update('scoring', '1'); // Enable scoring
    $config_pause_ts = Configuration::get('pause_ts'); // Get pause time
    $config_start_ts = Configuration::get('start_ts'); // Get start time
    $config_end_ts = Configuration::get('end_ts'); // Get end time
    $pause_ts = intval($config_pause_ts->getValue());
    $start_ts = intval($config_start_ts->getValue());
    $end_ts = intval($config_end_ts->getValue());

    // Calulcate game remaining
    $game_duration = $end_ts - $start_ts;
    $game_played_duration = $pause_ts - $start_ts;
    $remaining_duration = $game_duration - $game_played_duration;
    $end_ts = time() + $remaining_duration;

    Configuration::update('end_ts', strval($end_ts)); // Set new endtime
    Configuration::update('pause_ts', '0'); // Set pause to zero
    Configuration::update('game_paused', '0'); // Set gane to not paused
    Configuration::update('timer', '1'); // Start timer
    Progressive::run(); // Kick off progressive scoreboard
    Level::baseScoring(); // Kick off scoring for bases
    Announcement::createAuto('Game has resumed!'); // Announce game resumed
    ActivityLog::createGenericLog('Game has resumed!'); // Log game resumed
  }

  public static function autoBegin(): void {
    // Prevent autorun.php from storing timestamps in local cache, forever (the script runs continuously).
    Configuration::deleteLocalCache('CONFIGURATION');
    $config_start_ts = Configuration::get('start_ts'); // Get start time
    $config_end_ts = Configuration::get('end_ts'); // Get end time
    $config_game_paused = Configuration::get('game_paused'); // Get paused status
    $start_ts = intval($config_start_ts->getValue());
    $end_ts = intval($config_end_ts->getValue());
    $game_paused = intval($config_game_paused->getValue());

    if (($game_paused === 0) && ($start_ts <= time()) && ($end_ts > time())) {
      Control::begin(); // Start the game
    }
  }

  public static function autoEnd(): void {
    // Prevent autorun.php from storing timestamps in local cache, forever (the script runs continuously).
    Configuration::deleteLocalCache('CONFIGURATION');
    $config_start_ts = Configuration::get('start_ts'); // Get start time
    $config_end_ts = Configuration::get('end_ts'); // Get end time
    $config_game_paused = Configuration::get('game_paused'); // Get paused status
    $start_ts = intval($config_start_ts->getValue());
    $end_ts = intval($config_end_ts->getValue());
    $game_paused = intval($config_game_paused->getValue());

    if (($game_paused === 0) && ($end_ts <= time())) {
      Control::end(); // End the game
    }
  }

  public static function autoRun(): void {
    // Prevent autorun.php from storing timestamps in local cache, forever (the script runs continuously).
    Configuration::deleteLocalCache('CONFIGURATION');
    $config_game = Configuration::get('game'); // Get start time
    $game = intval($config_game->getValue());

    if ($game === 0) {
      Control::autoBegin(); // Check and start the game
    } else {
      Control::autoEnd(); // Check and stop the game
    }
  }

  public static function runAutoRunScript(): void {
    $autorun_status = Control::checkScriptRunning('autorun');
    if ($autorun_status === false) {
      $autorun_location = escapeshellarg(
        must_have_string(Utils::getSERVER(), 'DOCUMENT_ROOT').
        '/scripts/autorun.php',
      );
      $cmd =
        'php '.
        $autorun_location.
        ' > /dev/null 2>&1 & echo $!';
      $pid = shell_exec($cmd);
      Control::startScriptLog(intval($pid), 'autorun', $cmd);
    }
  }

  public static function checkScriptRunning(
    string $name,
  ): bool {
    $db = Db::getInstance();
    $host = Control::serverAddr();
    $result = $db->query(
      'SELECT pid FROM scripts WHERE name = ? AND host = ? AND status = 1',
      [$name, $host],
    );
    $status = false;
    if ($result->rowCount() >= 1) {
      foreach ($result->fetchAll() as $row) {
        $pid = intval(must_have_idx($row, 'pid'));
        $status = file_exists("/proc/$pid");
        if ($status === false) {
          Control::stopScriptLog($pid);
          Control::clearScriptLog();
        }
      }
      return $status;
    } else {
      return false;
    }
  }

  public static function importGame(): bool {
    $data_game = JSONImporterController::readJSON('game_file');
    if (is_array($data_game)) {
      $logos = array_pop(must_have_idx($data_game, 'logos'));
      if (!$logos) {
        return false;
      }
      $logos_result = Logo::importAll($logos);
      if (!$logos_result) {
        return false;
      }
      $teams = array_pop(must_have_idx($data_game, 'teams'));
      if (!$teams) {
        return false;
      }
      $teams_result = Team::importAll($teams);
      if (!$teams_result) {
        return false;
      }
      $categories = array_pop(must_have_idx($data_game, 'categories'));
      if (!$categories) {
        return false;
      }
      $categories_result = Category::importAll($categories);
      if (!$categories_result) {
        return false;
      }
      $levels = array_pop(must_have_idx($data_game, 'levels'));
      if (!$levels) {
        return false;
      }
      $levels_result = Level::importAll($levels);
      if (!$levels_result) {
        return false;
      }
      self::flushMemcached();
      return true;
    }
    return false;
  }

  public static function importTeams(): bool {
    $data_teams = JSONImporterController::readJSON('teams_file');
    if (is_array($data_teams)) {
      $teams = must_have_idx($data_teams, 'teams');
      self::flushMemcached();
      return Team::importAll($teams);
    }
    return false;
  }

  public static function importLogos(): bool {
    $data_logos = JSONImporterController::readJSON('logos_file');
    if (is_array($data_logos)) {
      $logos = must_have_idx($data_logos, 'logos');
      self::flushMemcached();
      return Logo::importAll($logos);
    }
    return false;
  }

  public static function importLevels(): bool {
    $data_levels = JSONImporterController::readJSON('levels_file');
    if (is_array($data_levels)) {
      $levels = must_have_idx($data_levels, 'levels');
      self::flushMemcached();
      return Level::importAll($levels);
    }
    return false;
  }

  public static function importCategories(): bool {
    $data_categories = JSONImporterController::readJSON('categories_file');
    if (is_array($data_categories)) {
      $categories = must_have_idx($data_categories, 'categories');
      self::flushMemcached();
      return Category::importAll($categories);
    }
    return false;
  }

  public static function importAttachments(): bool {
    $output = [];
    $status = 0;
    $filename =
      strval(BinaryImporterController::getFilename('attachments_file'));
    $document_root = must_have_string(Utils::getSERVER(), 'DOCUMENT_ROOT');
    $directory = Attachment::attachmentsDir;
    $cmd = "tar -zx --mode=600 -C $directory -f $filename";
    exec($cmd, $output, $status);
    if (intval($status) !== 0) {
      return false;
    }
    $directory_files = array_slice(scandir($directory), 2);
    foreach ($directory_files as $file) {
      if (is_dir($file) === true) {
        continue;
      }
      $chmod = chmod($directory.$file, 0600);
      if (!($chmod === true)) { throw new RuntimeException('Failed to set attachment file permissions to 0600'); }
    }
    self::flushMemcached();
    return true;
  }

  public static function restoreDb(): bool {
    $output = [];
    $status = 0;
    $filename =
      strval(BinaryImporterController::getFilename('database_file'));
    $cmd = "cat $filename | gunzip - ";
    exec($cmd, $output, $status);
    if (intval($status) !== 0) {
      return false;
    }
    $cmd = "cat $filename | gunzip - | ".Db::getInstance()->getRestoreCmd();
    exec($cmd, $output, $status);
    if (intval($status) !== 0) {
      return false;
    }
    self::flushMemcached();
    return true;
  }

  public static function exportGame(): void {
    $game = [];
    $game['logos'] = Logo::exportAll();
    $game['teams'] = Team::exportAll();
    $game['categories'] = Category::exportAll();
    $game['levels'] = Level::exportAll();

    $output_file = 'fbctf_game.json';
    JSONExporterController::sendJSON($game, $output_file);
    exit();
  }

  public static function exportTeams(): void {
    $teams = Team::exportAll();
    $output_file = 'fbctf_teams.json';
    JSONExporterController::sendJSON($teams, $output_file);
    exit();
  }

  public static function exportLogos(): void {
    $logos = Logo::exportAll();
    $output_file = 'fbctf_logos.json';
    JSONExporterController::sendJSON($logos, $output_file);
    exit();
  }

  public static function exportLevels(): void {
    $levels = Level::exportAll();
    $output_file = 'fbctf_levels.json';
    JSONExporterController::sendJSON($levels, $output_file);
    exit();
  }

  public static function exportCategories(): void {
    $categories = Category::exportAll();
    $output_file = 'fbctf_categories.json';
    JSONExporterController::sendJSON($categories, $output_file);
    exit();
  }

  public static function exportAttachments(): void {
    $filename = 'fbctf-attachments-'.date("d-m-Y").'.tgz';
    header('Content-Type: application/x-tgz');
    header('Content-Disposition: attachment; filename="'.$filename.'"');
    $document_root = must_have_string(Utils::getSERVER(), 'DOCUMENT_ROOT');
    $directory = Attachment::attachmentsDir;
    $cmd = "tar -cz -C $directory . ";
    passthru($cmd);
    exit();
  }

  public static function backupDb(): void {
    $filename = 'fbctf-backup-'.date("d-m-Y").'.sql.gz';
    header('Content-Type: application/x-gzip');
    header('Content-Disposition: attachment; filename="'.$filename.'"');
    $cmd = Db::getInstance()->getBackupCmd().' | gzip --best';
    passthru($cmd);
    exit();
  }

  public static function allActivity(
    bool $refresh = false,
  ): array {
    $mc_result = self::getMCRecords('ALL_ACTIVITY');
    if (!$mc_result || (is_countable($mc_result) && count($mc_result) === 0) || $refresh) {
      $db = Db::getInstance();
      $result = $db->query(
        'SELECT scores_log.ts AS time, teams.name AS team, countries.iso_code AS country, scores_log.team_id AS team_id FROM scores_log, levels, teams, countries WHERE scores_log.level_id = levels.id AND levels.entity_id = countries.id AND scores_log.team_id = teams.id AND teams.visible = 1 ORDER BY time DESC LIMIT 50',
        [],
      );
      $rows = $result->fetchAll();
      self::setMCRecords('ALL_ACTIVITY', $rows);
      return $rows;
    }
    if (!is_array($mc_result)) { throw new RuntimeException('cache return should be of type array'); }
    return $mc_result;
  }

  public static function resetBases(): void {
    $db = Db::getInstance();
    $db->query('DELETE FROM bases_log WHERE id > 0', []);
  }

  public static function flushMemcached(): bool {
    return self::flushMCCluster();
  }

  private static function loadDatabaseFile(
    string $file,
  ): bool {
    $contents = file_get_contents($file);
    if ($contents) {
      $db = Db::getInstance();
      $conn = $db->getConnection();
      $conn->exec($contents);
      return true;
    }
    return false;
  }

  public static function resetDatabase(): bool {
    $admins = MultiTeam::allAdmins();
    $base = __DIR__ . '/../../';
    $schema = self::loadDatabaseFile($base . 'database/schema.sql');
    $countries = self::loadDatabaseFile($base . 'database/countries.sql');
    $logos = self::loadDatabaseFile($base . 'database/logos.sql');
    $admin_seed = self::loadDatabaseFile($base . 'database/admin.sql');
    if ($schema && $countries && $logos) {
      if (!$admin_seed) {
        foreach ($admins as $admin) {
          $team_id = Team::create(
            $admin->getName(),
            $admin->getPasswordHash(),
            $admin->getLogo(),
          );
          Team::setAdmin($team_id, true);
          if ($admin->getProtected() === true) {
            Team::setProtected($team_id, true);
          }
        }
      }
      self::flushMemcached();
      return true;
    }
    return false;
  }
}
