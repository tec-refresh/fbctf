<?php declare(strict_types=1);

class Session extends Model {

  protected static string $MC_KEY = 'sessions:';

  protected static array $MC_KEYS = ['SESSIONS' => 'active_sessions:'];

  private function __construct(
    private int $id,
    private string $cookie,
    private string $data,
    private int $team_id,
    private string $created_ts,
    private string $last_access_ts,
    private string $last_page_access,
  ) {}

  public function getId(): int {
    return $this->id;
  }

  public function getCookie(): string {
    return $this->cookie;
  }

  public function getData(): string {
    return $this->data;
  }

  public function getTeamId(): int {
    return $this->team_id;
  }

  public function getCreatedTs(): string {
    return $this->created_ts;
  }

  public function getLastAccessTs(): string {
    return $this->last_access_ts;
  }

  public function getLastPageAccess(): string {
    return $this->last_page_access;
  }

  private static function decodeTeamId(string $data): int {
    // This is a bit janky
    $delim = explode('team_id|', $data)[1];
    $serialized = explode('name|', $delim)[0];
    $unserialized = strval(unserialize($serialized));

    return intval($unserialized);
  }

  public static function setTeamId(
    string $cookie,
    string $data,
  ): void {
    self::setTeamIdCacheSession($cookie, $data);
    if (Router::isRequestModal() ||
        Router::isRequestAjax() ||
        !Router::isRequestRouter()) {
      return;
    }
    $team_id = self::decodeTeamId($data);
    $db = Db::getInstance();
    $db->query(
      'UPDATE sessions SET team_id = ? WHERE cookie = ? LIMIT 1',
      [$team_id, $cookie],
    );
  }

  public static function setTeamIdCacheSession(
    string $cookie,
    string $data,
  ): void {
    $sessions = [];
    $session_data = [];
    $mc_result = self::getMCSession($cookie);
    if ($mc_result) {
      if (!($mc_result instanceof Session)) {
        throw new RuntimeException('mc_result should be of type Session');
      }
      $mc_result->team_id = self::decodeTeamId($data);
      self::setMCSession($cookie, $mc_result);
    } else {
      self::createCacheSession($cookie);
    }
  }

  private static function sessionFromRow(array $row): Session {
    return new Session(
      intval(must_have_idx($row, 'id')),
      must_have_idx($row, 'cookie'),
      must_have_idx($row, 'data'),
      intval(must_have_idx($row, 'team_id')),
      must_have_idx($row, 'created_ts'),
      must_have_idx($row, 'last_access_ts'),
      must_have_idx($row, 'last_page_access'),
    );
  }

  // Create new session.
  public static function create(
    string $cookie,
    string $data,
  ): void {
    $db = Db::getInstance();
    $db->query(
      'INSERT INTO sessions (cookie, data, created_ts, last_access_ts, team_id, last_page_access) VALUES (?, ?, NOW(), NOW(), 0, ?)',
      [$cookie, $data, Router::getRequestedPage()],
    );
    if ($data !== '') {
      self::createCacheSession($cookie);
    }
  }

  // Create new session.
  public static function createCacheSession(
    string $cookie,
  ): void {
    $session_data = self::sessionExistGet($cookie, true);
    self::setMCSession($cookie, $session_data);
  }

  // Retrieve the session by cookie.
  public static function sessionExistGet(
    string $cookie,
    bool $refresh = false,
  ): Session {
    $mc_result = self::getMCSession($cookie);
    if (!$mc_result || count($mc_result) === 0 || $refresh) {
      $db = Db::getInstance();
      $result = $db->query(
        'SELECT * FROM sessions WHERE cookie = ? LIMIT 1',
        [$cookie],
      );

      if (!($result->rowCount() === 1)) {
        throw new RuntimeException('Expected exactly one result');
      }
      $session_data = self::sessionFromRow($result->fetch());
      self::setMCSession($cookie, $session_data);
      return $session_data;
    } else {
      if (!($mc_result instanceof Session)) {
        throw new RuntimeException('cache return should be of type Session');
      }
      return $mc_result;
    }
  }

  // Checks if session exists by cookie.
  public static function sessionExist(
    string $cookie,
    bool $refresh = false,
  ): bool {
    $mc_result = self::getMCSession($cookie);
    if (!$mc_result || count($mc_result) === 0 || $refresh) {
      $db = Db::getInstance();
      $result = $db->query(
        'SELECT COUNT(*) FROM sessions WHERE cookie = ?',
        [$cookie],
      );
      if (!($result->rowCount() === 1)) {
        throw new RuntimeException('Expected exactly one result');
      }
      if (intval(idx($result->fetch(), 'COUNT(*)')) > 0) {
        self::createCacheSession($cookie);
        return true;
      }
    }
    return self::getMCSession($cookie) !== false;
  }

  public static function sessionDataIfExist(
    string $cookie,
    bool $refresh = false,
  ): string {
    $mc_result = self::getMCSession($cookie);
    if (!$mc_result || count($mc_result) === 0 || $refresh) {
      $db = Db::getInstance();
      $result = $db->query(
        'SELECT * FROM sessions WHERE cookie = ? LIMIT 1',
        [$cookie],
      );

      if ($result->rowCount() === 1) {
        $session = self::sessionExistGet($cookie);
        return $session->getData();
      } else {
        return '';
      }
    }
    $session = self::getMCSession($cookie);
    if ($session) {
      if (!($session instanceof Session)) {
        throw new RuntimeException('session should be of type Session');
      }
      return $session->getData();
    } else {
      return '';
    }
  }

  // Update the session for a given cookie.
  public static function update(
    string $cookie,
    string $data,
    bool $refresh = false,
  ): void {
    self::updateCacheSession($cookie, $data);
    if (!$refresh &&
        (Router::isRequestModal() ||
         Router::isRequestAjax() ||
         !Router::isRequestRouter())) {
      return;
    }
    $db = Db::getInstance();
    $db->query(
      'UPDATE sessions SET last_access_ts = NOW(), data = ?, last_page_access = ? WHERE cookie = ? LIMIT 1',
      [$data, Router::getRequestedPage(), $cookie],
    );
  }

  // Update the cache version of a session for a given cookie.
  public static function updateCacheSession(
    string $cookie,
    string $data,
  ): void {
    if ($data === '') {
      return;
    }
    $mc_result = self::getMCSession($cookie);
    if ($mc_result) {
      if (!($mc_result instanceof Session)) {
        throw new RuntimeException('session should be of type Session');
      }
      $mc_result->last_access_ts = date("Y-m-d H:i:s");
      $mc_result->data = $data;
      $last_page_access = Router::getRequestedPage();
      if ($last_page_access !== 'index') {
        $mc_result->last_page_access = $last_page_access;
      }
      self::setMCSession($cookie, $mc_result);
    } else {
      self::createCacheSession($cookie);
    }
  }

  // Delete the session for a given cookie.
  public static function delete(string $cookie): void {
    $db = Db::getInstance();
    $db->query(
      'DELETE FROM sessions WHERE cookie = ? LIMIT 1',
      [$cookie],
    );
    self::invalidateMCSessions($cookie);
  }

  // Delete the session for a given a team id.
  public static function deleteByTeam(int $team_id): void {
    $db = Db::getInstance();
    $team_sessions = self::sessionsByTeam($team_id);
    $db->query('DELETE FROM sessions WHERE team_id = ?', [$team_id]);
    foreach ($team_sessions as $session) {
      self::invalidateMCSessions($session->getCookie());
    }
  }

  public static function sessionsByTeam(
    int $team_id,
  ): array {
    $db = Db::getInstance();
    $result = $db->query(
      'SELECT * FROM sessions WHERE team_id = ?',
      [$team_id],
    );

    $sessions = [];
    foreach ($result->fetchAll() as $row) {
      $sessions[] = self::sessionFromRow($row);
    }

    return $sessions;
  }

  // Delete all sessions for unprotected teams
  public static function deleteAllUnprotected(): void {
    $db = Db::getInstance();
    $team_sessions = self::unprotectedSessions();
    $db->query(
      'DELETE FROM sessions USING sessions, teams WHERE sessions.team_id = teams.id and teams.protected = 0',
    );
    foreach ($team_sessions as $session) {
      self::invalidateMCSessions($session->getCookie());
    }
  }

  // Does cleanup of cookies.
  public static function cleanup(int $maxlifetime): void {
    if (Router::isRequestModal() ||
        Router::isRequestAjax() ||
        !Router::isRequestRouter()) {
      return;
    }
    $db = Db::getInstance();
    $expired_sessions = self::expiredSessionsForCleanup($maxlifetime);
    $empty_sessions = self::emptySessionsForCleanup();

    foreach ($expired_sessions as $session) {
      $cached_session = self::getMCSession($session->getCookie());
      if ($cached_session === false) {
        continue;
      }
      if (!($cached_session instanceof Session)) {
        throw new RuntimeException('cached_session should be of type Session');
      }
      $cached_timestamp = strtotime($cached_session->last_access_ts);
      if (strtotime($cached_session->last_access_ts) <
          (time() - $maxlifetime)) {
        self::invalidateMCSessions($session->getCookie());
      } else {
        self::update(
          $session->getCookie(),
          $session->getData(),
          true,
        );
      }
    }
    foreach ($empty_sessions as $session) {
      $cached_session = self::getMCSession($session->getCookie());
      if ($cached_session === false) {
        continue;
      }
      if (!($cached_session instanceof Session)) {
        throw new RuntimeException('cached_session should be of type Session');
      }
      if ($cached_session->getData() === '') {
        self::invalidateMCSessions($session->getCookie());
      } else if ($cached_session->getData() !== '') {
        self::update(
          $session->getCookie(),
          $session->getData(),
          true,
        );
      }
    }
    // Clean up expired and empty sessions
    $queries = [
      sprintf(
        'DELETE FROM sessions WHERE UNIX_TIMESTAMP(last_access_ts) < %d',
        time() - $maxlifetime,
      ),
      'DELETE FROM sessions WHERE data IS NULL',
    ];
    $db->multiQuery($queries);
  }

  public static function unprotectedSessions(): array {
    $db = Db::getInstance();
    $sessions = [];
    $result = $db->query(
      'SELECT * FROM sessions, teams WHERE sessions.team_id = teams.id AND teams.protected = 0',
    );

    foreach ($result->fetchAll() as $row) {
      $sessions[] = self::sessionFromRow($row);
    }

    return $sessions;
  }

  public static function expiredSessionsForCleanup(
    int $maxlifetime,
  ): array {
    $db = Db::getInstance();
    $sessions = [];
    $result = $db->query(
      'SELECT * FROM sessions WHERE UNIX_TIMESTAMP(last_access_ts) < ?',
      [time() - $maxlifetime],
    );

    foreach ($result->fetchAll() as $row) {
      $sessions[] = self::sessionFromRow($row);
    }

    return $sessions;
  }

  public static function emptySessionsForCleanup(): array {
    $db = Db::getInstance();
    $sessions = [];
    $result = $db->query(
      'SELECT * FROM sessions WHERE IFNULL(data, ?) = ?',
      ['', ''],
    );

    foreach ($result->fetchAll() as $row) {
      $sessions[] = self::sessionFromRow($row);
    }

    return $sessions;
  }

  // All the sessions
  public static function allSessions(
    bool $refresh = false,
  ): array {
    if ($refresh) {
      $db = Db::getInstance();
      $sessions = [];
      $result = $db->query(
        'SELECT * FROM sessions ORDER BY last_access_ts DESC',
      );

      $sessions = [];
      foreach ($result->fetchAll() as $row) {
        $sessions[] = self::sessionFromRow($row);
      }
      return $sessions;
    } else {
      $mc = self::getMc();
      $sessions = [];
      $cached_sessions = [];
      $mc_keys = [];
      self::flushMCCluster();
      $all_sessions = preg_grep(
        '/'.self::$MC_KEY.self::$MC_KEYS['SESSIONS'].'/',
        $mc_keys,
      );
      foreach ($all_sessions as $session_key) {
        $session_key =
          substr(strstr(substr(strstr($session_key, ':'), 1), ':'), 1);
        $session = self::getMCSession($session_key);
        if ($session !== false &&
            $session instanceof Session &&
            $session->getTeamId() !== 0) {
          $cached_sessions[] = $session_key;
          $sessions[] = $session;
        }
      }
      $db = Db::getInstance();
      $result = $db->query('SELECT * FROM sessions');

      foreach ($result->fetchAll() as $row) {
        if (!in_array($row['cookie'], $cached_sessions)) {
          $sessions[] = self::sessionFromRow($row);
        }
      }
      if (!(is_array($sessions))) {
        throw new RuntimeException('$sessions should be an array of Session');
      }
      return $sessions;
    }
  }

  private static function setMCSession(string $key, mixed $records): void {
    $key = str_replace(' ', '', $key);
    self::writeMCCluster(
      self::$MC_KEY.self::$MC_KEYS['SESSIONS'].$key,
      $records,
    );
  }

  private static function getMCSession(string $key): mixed {
    $mc = self::getMc();
    $key = str_replace(' ', '', $key);
    $mc_result =
      $mc->get(static::$MC_KEY.static::$MC_KEYS['SESSIONS'].$key);
    return $mc_result;
  }

  public static function invalidateMCSessions(?string $key = null): void {
    $mc = self::getMc();
    $key = str_replace(' ', '', $key);
    if ($key === null) {
      $mc_keys = [];
      self::flushMCCluster();
      $all_sessions = preg_grep(
        '/'.self::$MC_KEY.self::$MC_KEYS['SESSIONS'].'/',
        $mc_keys,
      );
      foreach ($all_sessions as $session_key) {
        self::invalidateMCCluster($session_key);
      }
    } else {
      self::invalidateMCCluster(
        self::$MC_KEY.self::$MC_KEYS['SESSIONS'].$key,
      );
    }
  }
}
