<?php declare(strict_types=1);

require_once ($_SERVER['DOCUMENT_ROOT'].'/../vendor/autoload.php');

class StatsController extends DataController {

  public function generateData(): void {

    SessionUtils::sessionStart();
    SessionUtils::enforceLogin();
    SessionUtils::enforceAdmin();

    $stats = [];

    $team_stats = MultiTeam::allTeamsCache();
    $session_stats = Session::allSessions();
    $level_stats = Level::allLevels();
    $active_level_stats = Level::allActiveLevels();
    $hint_stats = HintLog::allHints();
    $capture_stats = ScoreLog::allScores();

    // Number of teams
    $stats['teams'] = count($team_stats);

    // Number of active sessions
    $stats['sessions'] = count($session_stats);

    // Number of levels
    $stats['levels'] = count($level_stats);

    // Number of active levels
    $stats['active_levels'] = count($active_level_stats);

    // Number of captures
    $stats['hints'] = count($hint_stats);

    // Number of hints
    $stats['captures'] = count($capture_stats);

    // AsyncMysqlConnectionPool Stats
    $stats['database'] = Db::getDatabaseStats();

    // Memcached Stats
    $stats['memcached'] = Model::getMemcachedStats();

    // System load average
    $stats['load'] = sys_getloadavg();

    // System CPU stats
    $cpu_stats_1 = file('/proc/stat');
    sleep(1);
    $cpu_stats_2 = file('/proc/stat');
    $cpu_info_1 = explode(" ", preg_replace("!cpu +!", "", $cpu_stats_1[0]));
    $cpu_info_2 = explode(" ", preg_replace("!cpu +!", "", $cpu_stats_2[0]));
    $cpu_diff = [];
    $cpu_diff['user'] = $cpu_info_2[0] - $cpu_info_1[0];
    $cpu_diff['nice'] = $cpu_info_2[1] - $cpu_info_1[1];
    $cpu_diff['sys'] = $cpu_info_2[2] - $cpu_info_1[2];
    $cpu_diff['idle'] = $cpu_info_2[3] - $cpu_info_1[3];
    $cpu_total = array_sum($cpu_diff);
    $cpu_stats = [];
    foreach ($cpu_diff as $x => $y)
      $cpu_stats[$x] = round($y / $cpu_total * 100, 1);
    $stats['cpu'] = $cpu_stats;

    $this->jsonSend($stats);
  }
}

$statsController = new StatsController();
$statsController->sendData();
