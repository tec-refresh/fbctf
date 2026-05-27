<?php declare(strict_types=1);

class Progressive extends Model {

  protected static string $MC_KEY = 'progressive:';

  protected static array $MC_KEYS = [
    'ITERATION_COUNT' => 'iterations_count',
    'PROGRESSIVE_POINTS' => 'points_by_teamname',
  ];

  private function __construct(
    private int $id,
    private string $ts,
    private string $team_name,
    private int $points,
    private int $iteration,
  ) {}

  public function getId(): int {
    return $this->id;
  }

  public function getTs(): string {
    return $this->ts;
  }

  public function getTeamName(): string {
    return $this->team_name;
  }

  public function getPoints(): int {
    return $this->points;
  }

  public function getIteration(): int {
    return $this->iteration;
  }

  public static function gameStatus(): bool {
    $config = Configuration::get('game');
    return $config->getValue() === '1';
  }

  public static function cycle(): int {
    $config = Configuration::get('progressive_cycle');
    return intval($config->getValue());
  }

  private static function progressiveFromRow(
    array $row,
  ): Progressive {
    return new Progressive(
      intval(must_have_idx($row, 'id')),
      must_have_idx($row, 'ts'),
      must_have_idx($row, 'team_name'),
      intval(must_have_idx($row, 'points')),
      intval(must_have_idx($row, 'iteration')),
    );
  }

  // Progressive points.
  public static function progressiveScoreboard(
    string $team_name,
    bool $refresh = false,
  ): array {
    $mc_result = self::getMCRecords('PROGRESSIVE_POINTS');
    if (!$mc_result || (is_countable($mc_result) && count($mc_result) === 0) || $refresh) {
      $db = Db::getInstance();
      $progressive = [];
      $result = $db->query(
        'SELECT MAX(id) as id, MAX(ts) as ts, team_name, MAX(points) as points, iteration FROM progressive_log GROUP BY team_name, iteration, id ORDER BY points ASC',
        [],
      );
      foreach ($result->fetchAll() as $row) {
        $progressive[$row['team_name']][] =
          self::progressiveFromRow($row);
      }
      self::setMCRecords('PROGRESSIVE_POINTS', $progressive);
      if (array_key_exists($team_name, $progressive)) {
        $team_progressive = $progressive[$team_name];
        if (!is_array($team_progressive)) { throw new RuntimeException('team_progressive should not an array of Progressive'); }
        return $team_progressive;
      } else {
        return [];
      }
    } else {
      if (!is_array($mc_result)) { throw new RuntimeException('cache return should be of type array'); }
      if (array_key_exists($team_name, $mc_result)) {
        $team_progressive = $mc_result[$team_name];
        if (!is_array($team_progressive)) { throw new RuntimeException('team_progressive should not an array of Progressive'); }
        return $team_progressive;
      } else {
        return [];
      }
    }
  }

  // Count how many iterations of the progressive scoreboard we have.
  public static function count(
    bool $refresh = false,
  ): int {
    $mc_result = self::getMCRecords('ITERATION_COUNT');
    if (!$mc_result || (is_countable($mc_result) && count($mc_result) === 0) || $refresh) {
      $db = Db::getInstance();
      $result = $db->query(
        'SELECT COUNT(DISTINCT(iteration)) AS C FROM progressive_log',
        [],
      );
      if (!($result->rowCount() === 1)) { throw new RuntimeException('Expected exactly one result'); }
      $row = $result->fetch();
      self::setMCRecords(
        'ITERATION_COUNT',
        intval($row['C']),
      );
      return intval($row['C']);
    } else {
      return intval($mc_result);
    }
  }

  // Acquire the data for one iteration of the progressive scoreboard.
  public static function take(): void {
    $db = Db::getInstance();
    $db->query(
      'INSERT INTO progressive_log (ts, team_name, points, iteration) (SELECT NOW(), name, points, (SELECT IFNULL(MAX(iteration)+1, 1) FROM progressive_log) FROM teams)',
      [],
    );
    self::invalidateMCRecords(); // Invalidate Memcached Progressive data.
  }

  // Reset the progressive scoreboard.
  public static function reset(): void {
    $db = Db::getInstance();
    $db->query('DELETE FROM progressive_log WHERE id > 0', []);
    self::invalidateMCRecords(); // Invalidate Memcached Progressive data.
  }

  // Kick off the progressive scoreboard in the background.
  public static function run(): void {
    $document_root = must_have_string(Utils::getSERVER(), 'DOCUMENT_ROOT');
    $cmd =
      'php '.
      $document_root.
      '/scripts/progressive.php > /dev/null 2>&1 & echo $!';
    $pid = shell_exec($cmd);
    Control::startScriptLog(intval($pid), 'progressive', $cmd);
  }

  // Stop the progressive scoreboard process in the background
  public static function stop(): void {
    // Kill running process
    $pid = Control::scriptPid('progressive');
    if ($pid > 0) {
      exec('kill -9 '.escapeshellarg(strval($pid)));
    }
    // Mark process as stopped
    Control::stopScriptLog($pid);
  }
}
