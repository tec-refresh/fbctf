<?php declare(strict_types=1);

class Team extends Model implements Importable, Exportable {
  private function __construct(
    private int $id,
    private int $active,
    private int $admin,
    private int $protected,
    private int $visible,
    private string $name,
    private string $password_hash,
    private int $points,
    private string $last_score,
    private string $logo,
    private string $created_ts,
  ) {}

  public function getId(): int {
    return $this->id;
  }

  public function getActive(): bool {
    return $this->active === 1;
  }

  public function getAdmin(): bool {
    return $this->admin === 1;
  }

  public function getProtected(): bool {
    return $this->protected === 1;
  }

  public function getVisible(): bool {
    return $this->visible === 1;
  }

  public function getName(): string {
    return mb_convert_encoding($this->name, 'UTF-8');
  }

  public function getPasswordHash(): string {
    return $this->password_hash;
  }

  public function getPoints(): int {
    return $this->points;
  }

  public function getLastScore(): string {
    return $this->last_score;
  }

  public function getLogo(): string {
    return $this->logo;
  }

  public function getLogoModel(): Logo {
    $logo = Logo::byName($this->logo);
    return $logo;
  }

  public function getCreatedTs(): string {
    return $this->created_ts;
  }

  protected static function teamFromRow(array $row): Team {
    return new Team(
      intval(must_have_idx($row, 'id')),
      intval(must_have_idx($row, 'active')),
      intval(must_have_idx($row, 'admin')),
      intval(must_have_idx($row, 'protected')),
      intval(must_have_idx($row, 'visible')),
      must_have_idx($row, 'name'),
      must_have_idx($row, 'password_hash'),
      intval(must_have_idx($row, 'points')),
      must_have_idx($row, 'last_score'),
      must_have_idx($row, 'logo'),
      must_have_idx($row, 'created_ts'),
    );
  }

  // Import teams.
  public static function importAll(
    array $elements,
  ): bool {
    foreach ($elements as $team) {
      $name = must_have_string($team, 'name');
      $exist = self::teamExist($name);
      if (!$exist) {
        $team_id = self::createAll(
          (bool) must_have_idx($team, 'active'),
          $name,
          must_have_string($team, 'password_hash'),
          must_have_int($team, 'points'),
          must_have_string($team, 'logo'),
          (bool) must_have_idx($team, 'admin'),
          (bool) must_have_idx($team, 'protected'),
          (bool) must_have_idx($team, 'visible'),
        );
      }
      Logo::setUsed(must_have_string($team, 'logo'), true);
    }
    return true;
  }

  // Export teams.
  public static function exportAll(
  ): array {
    $all_teams_data = [];
    $all_teams = self::allTeams();

    foreach ($all_teams as $team) {
      $team_data = self::teamData($team->getId());
      $one_team = [
        'name' => $team->getName(),
        'active' => $team->getActive(),
        'admin' => $team->getAdmin(),
        'protected' => $team->getProtected(),
        'visible' => $team->getVisible(),
        'password_hash' => $team->getPasswordHash(),
        'points' => $team->getPoints(),
        'logo' => $team->getLogo(),
        'data' => $team_data,
      ];
      array_push($all_teams_data, $one_team);
    }
    return ['teams' => $all_teams_data];
  }

  // Retrieve how many teams are using one logo.
  public static function whoUses(
    string $logo,
  ): array {
    $db = Db::getInstance();
    $result = $db->query('SELECT * FROM teams WHERE logo = ?', [$logo]);

    $teams = [];
    foreach ($result->fetchAll() as $row) {
      $teams[] = self::teamFromRow($row);
    }
    return $teams;
  }

  // Generate salted hash.
  public static function generateHash(string $password): string {
    $options = ['cost' => 12];
    return strval(password_hash($password, PASSWORD_DEFAULT, $options));
  }

  // Checks if hash need refreshing.
  public static function regenerateHash(string $password_hash): bool {
    $options = ['cost' => 12];
    return (bool) password_needs_rehash(
      $password_hash,
      PASSWORD_DEFAULT,
      $options,
    );
  }

  // Verify if login is valid.
  public static function verifyCredentials(
    int $team_id,
    string $password,
  ): ?Team {
    $db = Db::getInstance();
    $result =
      $db->query(
        'SELECT * FROM teams WHERE id = ? AND (active = 1 OR admin = 1) LIMIT 1',
        [$team_id],
      );

    if ($result->rowCount() > 0) {
      if (!($result->rowCount() === 1)) { throw new RuntimeException('Expected exactly one result'); }
      $team = self::teamFromRow($result->fetch());

      // Check if ldap is enabled and verify credentials if successful
      // An exception is admin user, which is verified locally
      $ldap = Configuration::get('ldap');
      if ($ldap->getValue() === '1' && !$team->getAdmin()) {
        // Get server information from configuration
        $ldap_server = Configuration::get('ldap_server');
        $ldap_port = Configuration::get('ldap_port');
        $ldap_domain_suffix = Configuration::get('ldap_domain_suffix');
        $ldapconn = ldap_connect(
          $ldap_server->getValue(),
          intval($ldap_port->getValue()),
        );
        if (!$ldapconn)
          return null;
        $team_name = trim($team->getName());
        $bind = ldap_bind(
          $ldapconn,
          $team_name.$ldap_domain_suffix->getValue(),
          $password,
        );
        if (!$bind)
          return null;
        //Successful Login via LDAP
        return $team;
      }

      if (password_verify($password, $team->getPasswordHash())) {
        if (self::regenerateHash($team->getPasswordHash())) {
          $new_hash = self::generateHash($password);
          self::updateTeamPassword($new_hash, $team->getId());
        }
        return $team;
      } else {
        return null;
      }
    } else {
      return null;
    }
  }

  // Check to see if the team is active.
  public static function checkTeamStatus(
    int $team_id,
  ): bool {
    $db = Db::getInstance();
    $result = $db->query(
      'SELECT COUNT(*) FROM teams WHERE id = ? AND active = 1 LIMIT 1',
      [$team_id],
    );

    if ($result->rowCount() > 0) {
      if (!($result->rowCount() === 1)) { throw new RuntimeException('Expected exactly one result'); }
      return (intval(idx($result->fetch(), 'COUNT(*)')) > 0);
    } else {
      return false;
    }
  }

  // Create a team and return the created team id.
  public static function create(
    string $name,
    string $password_hash,
    string $logo,
  ): int {
    $db = Db::getInstance();

    // Create team
    $db->query(
      'INSERT INTO teams (name, password_hash, logo, created_ts) VALUES (?, ?, ?, NOW())',
      [$name, $password_hash, $logo],
    );
    Logo::setUsed($logo, true);

    // Return newly created team_id
    $result =
      $db->query(
        'SELECT id FROM teams WHERE name = ? AND password_hash = ? AND logo = ? LIMIT 1',
        [$name, $password_hash, $logo],
      );

    Logo::invalidateMCRecords();
    // Delay rebuilding all cache for the new team, as they won't have any scoring data yet anyway.
    MultiTeam::invalidateMCRecords('ALL_TEAMS');
    MultiTeam::invalidateMCRecords('ALL_ACTIVE_TEAMS');
    MultiTeam::invalidateMCRecords('ALL_VISIBLE_TEAMS');
    MultiTeam::invalidateMCRecords('TEAMS_BY_LOGO');

    if (!($result->rowCount() === 1)) { throw new RuntimeException('Expected exactly one result'); }
    return intval($result->fetch()['id']);
  }

  // Create a team (all the fields) and return the created team id.
  public static function createAll(
    bool $active,
    string $name,
    string $password_hash,
    int $points,
    string $logo,
    bool $admin,
    bool $protected,
    bool $visible,
  ): int {
    $db = Db::getInstance();

    // Create team
    $db->query(
      'INSERT INTO teams (name, password_hash, points, logo, active, admin, protected, visible, created_ts) VALUES (?, ?, ?, ?, ?, ?, ?, ?, NOW())',
      [
        $name,
        $password_hash,
        $points,
        $logo,
        $active ? 1 : 0,
        $admin ? 1 : 0,
        $protected ? 1 : 0,
        $visible ? 1 : 0,
      ],
    );
    Logo::setUsed($logo, true);

    // Return newly created team_id
    $result =
      $db->query(
        'SELECT id FROM teams WHERE name = ? AND password_hash = ? AND logo = ? LIMIT 1',
        [$name, $password_hash, $logo],
      );

    Logo::invalidateMCRecords();
    MultiTeam::invalidateMCRecords(); // Invalidate Memcached MultiTeam data.
    if (!($result->rowCount() === 1)) { throw new RuntimeException('Expected exactly one result'); }
    return intval($result->fetch()['id']);
  }

  // Add data to a team.
  public static function addTeamData(
    string $name,
    string $email,
    int $team_id,
  ): void {
    $db = Db::getInstance();
    $db->query(
      'INSERT INTO teams_data (name, email, team_id, created_ts) VALUES (?, ?, ?, NOW())',
      [$name, $email, $team_id],
    );
    MultiTeam::invalidateMCRecords('ALL_TEAMS'); // Invalidate Memcached MultiTeam data.
    MultiTeam::invalidateMCRecords('ALL_ACTIVE_TEAMS');
    MultiTeam::invalidateMCRecords('ALL_VISIBLE_TEAMS');
    MultiTeam::invalidateMCRecords('TEAMS_BY_LOGO');
  }

  // Get a team data.
  public static function teamData(
    int $team_id,
  ): array {
    $db = Db::getInstance();
    $result = $db->query(
      'SELECT * FROM teams_data WHERE team_id = ?',
      [$team_id],
    );
    return $result->fetchAll();
  }

  // Update team.
  public static function update(
    string $name,
    string $logo,
    int $points,
    int $team_id,
  ): void {
    $db = Db::getInstance();

    // Get and set old logo to unused
    $result =
      $db->query('SELECT logo FROM teams WHERE id = ?', [$team_id]);
    if (!($result->rowCount() === 1)) { throw new RuntimeException('Expected exactly one result'); }
    $logo_old = strval($result->fetch()['logo']);

    $db->query(
      'UPDATE teams SET name = ?, logo = ? , points = ? WHERE id = ? LIMIT 1',
      [$name, $logo, $points, $team_id],
    );
    Logo::setUsed($logo_old, false);
    Logo::setUsed($logo, true);
    Logo::invalidateMCRecords();
    MultiTeam::invalidateMCRecords(); // Invalidate Memcached MultiTeam data.
    ActivityLog::invalidateMCRecords('ALL_ACTIVITY'); // Invalidate Memcached ActivityLog data.
  }

  // Update team password.
  public static function updateTeamPassword(
    string $password_hash,
    int $team_id,
  ): void {
    $db = Db::getInstance();
    $db->query(
      'UPDATE teams SET password_hash = ? WHERE id = ? LIMIT 1',
      [$password_hash, $team_id],
    );
    MultiTeam::invalidateMCRecords('ALL_TEAMS'); // Invalidate Memcached MultiTeam data.
    MultiTeam::invalidateMCRecords('ALL_ACTIVE_TEAMS');
    MultiTeam::invalidateMCRecords('ALL_VISIBLE_TEAMS');
    MultiTeam::invalidateMCRecords('TEAMS_BY_LOGO');
    Session::deleteByTeam($team_id);
  }

  // Delete team.
  public static function delete(int $team_id): void {
    $db = Db::getInstance();
    $result =
      $db->query('SELECT logo FROM teams WHERE id = ?', [$team_id]);
    if (!($result->rowCount() === 1)) { throw new RuntimeException('Expected exactly one result'); }
    $logo = strval($result->fetch()['logo']);

    Logo::setUsed($logo, false);

    $db->query('DELETE FROM teams WHERE id = ? AND protected = 0 LIMIT 1', [$team_id]);
    $db->query('DELETE FROM registration_tokens WHERE team_id = ?', [$team_id]);
    $db->query('DELETE FROM teams_oauth WHERE team_id = ?', [$team_id]);
    $db->query('DELETE FROM scores_log WHERE team_id = ?', [$team_id]);
    $db->query('DELETE FROM hints_log WHERE team_id = ?', [$team_id]);
    $db->query('DELETE FROM failures_log WHERE team_id = ?', [$team_id]);
    $db->query('DELETE FROM activity_log WHERE subject = ?', ["Team:$team_id"]);
    MultiTeam::invalidateMCRecords(); // Invalidate Memcached MultiTeam data.
    ActivityLog::invalidateMCRecords('ALL_ACTIVITY'); // Invalidate Memcached ActivityLog data.
    ScoreLog::invalidateMCRecords(); // Invalidate Memcached ScoreLog data.
    HintLog::invalidateMCRecords(); // Invalidate Memcached ScoreLog data.
    Session::deleteByTeam($team_id);
  }

  public static function setTeamName(
    int $team_id,
    string $team_name,
  ): bool {
    $db = Db::getInstance();

    $team_name = trim($team_name);

    if ($team_name === '') {
      return false;
    }

    $shortname = substr($team_name, 0, 20);

    $team_exists = Team::teamExist($shortname);
    if ($team_exists === true) {
      return false;
    } else {
      $team = self::team($team_id);
      self::update(
        $shortname,
        $team->getLogo(),
        $team->getPoints(),
        $team_id,
      );
      MultiTeam::invalidateMCRecords('ALL_TEAMS'); // Invalidate Memcached MultiTeam data.
      MultiTeam::invalidateMCRecords('ALL_ACTIVE_TEAMS');
      MultiTeam::invalidateMCRecords('ALL_VISIBLE_TEAMS');
      MultiTeam::invalidateMCRecords('TEAMS_BY_LOGO');
      ScoreLog::invalidateMCRecords(); // Invalidate Memcached ScoreLog data.
      ActivityLog::invalidateMCRecords(); // Invalidate Memcached ActivityLog data.
      return true;
    }
  }

  // Enable or disable teams by passing 1 or 0.
  public static function setStatus(
    int $team_id,
    bool $status,
  ): void {
    $db = Db::getInstance();
    $db->query(
      'UPDATE teams SET active = ? WHERE id = ? LIMIT 1',
      [$status ? 1 : 0, $team_id],
    );
    if ($status === false) {
      Session::deleteByTeam($team_id);
    }
    MultiTeam::invalidateMCRecords(); // Invalidate Memcached MultiTeam data.
  }

  // Enable or disable all teams by passing 1 or 0.
  public static function setStatusAll(bool $status): void {
    $db = Db::getInstance();
    $db->query(
      'UPDATE teams SET active = ? WHERE id > 0 AND protected = 0',
      [$status ? 1 : 0],
    );
    if ($status === false) {
      Session::deleteAllUnprotected();
    }
    MultiTeam::invalidateMCRecords(); // Invalidate Memcached MultiTeam data.
  }

  // Sets toggles team protection status.
  public static function setProtected(
    int $team_id,
    bool $protect,
  ): void {
    $db = Db::getInstance();
    $db->query(
      'UPDATE teams SET protected = ? WHERE id = ? LIMIT 1',
      [$protect ? 1 : 0, $team_id],
    );
    MultiTeam::invalidateMCRecords(); // Invalidate Memcached MultiTeam data.
  }

  // Sets toggles team admin status.
  public static function setAdmin(
    int $team_id,
    bool $admin,
  ): void {
    $db = Db::getInstance();
    $db->query(
      'UPDATE teams SET admin = ? WHERE id = ? AND protected = 0 LIMIT 1',
      [$admin ? 1 : 0, $team_id],
    );
    MultiTeam::invalidateMCRecords(); // Invalidate Memcached MultiTeam data.
    Session::deleteByTeam($team_id); // Delete all sessions for team in question
  }

  // Enable or disable team visibility by passing 1 or 0.
  public static function setVisible(
    int $team_id,
    bool $visible,
  ): void {
    $db = Db::getInstance();
    $db->query(
      'UPDATE teams SET visible = ? WHERE id = ? LIMIT 1',
      [$visible ? 1 : 0, $team_id],
    );
    MultiTeam::invalidateMCRecords(); // Invalidate Memcached MultiTeam data.
    ActivityLog::invalidateMCRecords('ALL_ACTIVITY'); // Invalidate Memcached ActivityLog data.
  }

  // Check if a team name is already created.
  public static function teamExist(
    string $team_name,
  ): bool {
    $db = Db::getInstance();

    $result = $db->query(
      'SELECT COUNT(*) FROM teams WHERE name = ?',
      [$team_name],
    );

    if ($result->rowCount() > 0) {
      if (!($result->rowCount() === 1)) { throw new RuntimeException('Expected exactly one result'); }
      return (intval(idx($result->fetch(), 'COUNT(*)')) > 0);
    } else {
      return false;
    }
  }

  // Check if a team name is already created.
  public static function teamExistById(
    int $team_id,
  ): bool {
    $db = Db::getInstance();

    $result =
      $db->query('SELECT COUNT(*) FROM teams WHERE id = ?', [$team_id]);

    if ($result->rowCount() > 0) {
      if (!($result->rowCount() === 1)) { throw new RuntimeException('Expected exactly one result'); }
      return (intval(idx($result->fetch(), 'COUNT(*)')) > 0);
    } else {
      return false;
    }
  }

  // All active teams.
  public static function allActiveTeams(): array {
    $db = Db::getInstance();

    $result =
      $db->query('SELECT * FROM teams WHERE active = 1 ORDER BY id');

    $teams = [];
    foreach ($result->fetchAll() as $row) {
      $teams[] = self::teamFromRow($row);
    }

    return $teams;
  }

  // All visible teams.
  public static function allVisibleTeams(): array {
    $db = Db::getInstance();

    $result = $db->query(
      'SELECT * FROM teams WHERE visible = 1 AND active = 1 ORDER BY id',
    );

    $teams = [];
    foreach ($result->fetchAll() as $row) {
      $teams[] = self::teamFromRow($row);
    }

    return $teams;
  }

  // Leaderboard order.
  public static function leaderboard(): array {
    $db = Db::getInstance();

    $result =
      $db->query(
        'SELECT * FROM teams WHERE active = 1 AND visible = 1 ORDER BY points DESC, last_score ASC',
      );

    $teams = [];
    foreach ($result->fetchAll() as $row) {
      $teams[] = self::teamFromRow($row);
    }

    return $teams;
  }

  // All teams.
  public static function allTeams(): array {
    $db = Db::getInstance();

    $result = $db->query('SELECT * FROM teams ORDER BY points DESC');

    $teams = [];
    foreach ($result->fetchAll() as $row) {
      $teams[] = self::teamFromRow($row);
    }

    return $teams;
  }

  // Get a single team.
  public static function team(int $team_id): Team {
    $db = Db::getInstance();

    $result = $db->query(
      'SELECT * FROM teams WHERE id = ? LIMIT 1',
      [$team_id],
    );

    if (!($result->rowCount() === 1)) { throw new RuntimeException('Expected exactly one result'); }
    $team = self::teamFromRow($result->fetch());

    return $team;
  }

  // Get a single team, by name.
  public static function teamByName(
    string $team_name,
  ): Team {
    $db = Db::getInstance();

    $result = $db->query(
      'SELECT * FROM teams WHERE name = ? LIMIT 1',
      [$team_name],
    );

    if (!($result->rowCount() === 1)) { throw new RuntimeException('Expected exactly one result'); }
    $team = self::teamFromRow($result->fetch());

    return $team;
  }

  // Get points by type.
  public static function pointsByType(
    int $team_id,
    string $type,
  ): int {
    $db = Db::getInstance();

    $result =
      $db->query(
        'SELECT IFNULL(SUM(points), 0) AS points FROM scores_log WHERE type = ? AND team_id = ?',
        [$type, $team_id],
      );

    if (!($result->rowCount() === 1)) { throw new RuntimeException('Expected exactly one result'); }
    return intval(idx($result->fetch(), 'points'));
  }

  // Get healthy status for points.
  public static function pointsHealth(int $team_id): bool {
    $db = Db::getInstance();

    $result =
      $db->query(
        'SELECT IFNULL(t.points, 0) AS points, IFNULL(SUM(s.points), 0) AS sum FROM teams AS t, scores_log AS s WHERE t.id = ? AND s.team_id = ?',
        [$team_id, $team_id],
      );

    if (!($result->rowCount() === 1)) { throw new RuntimeException('Expected exactly one result'); }
    $value = $result->fetch();

    return (intval($value['points']) === intval($value['sum']));
  }

  // Update the last_score field.
  public static function lastScore(int $team_id): void {
    $db = Db::getInstance();
    $db->query(
      'UPDATE teams SET last_score = NOW() WHERE id = ? LIMIT 1',
      [$team_id],
    );
    MultiTeam::invalidateMCRecords(); // Invalidate Memcached MultiTeam data.
  }

  // Set all points to zero for all teams.
  public static function resetAllPoints(): void {
    $db = Db::getInstance();
    $db->query('UPDATE teams SET points = 0 WHERE id > 0');
    MultiTeam::invalidateMCRecords(); // Invalidate Memcached MultiTeam data.
    ActivityLog::invalidateMCRecords('ALL_ACTIVITY'); // Invalidate Memcached ActivityLog data.
  }

  // Teams total number.
  public static function teamsCount(): int {
    $db = Db::getInstance();

    $result = $db->query('SELECT COUNT(*) AS count FROM teams');

    if (!($result->rowCount() === 1)) { throw new RuntimeException('Expected exactly one result'); }
    return intval(idx($result->fetch(), 'COUNT(*)'));
  }

  public static function firstCapture(
    int $level_id,
  ): Team {
    $db = Db::getInstance();
    $result =
      $db->query(
        'SELECT * FROM teams WHERE id = (SELECT team_id FROM scores_log WHERE level_id = ? AND team_id IN (SELECT id FROM teams WHERE visible = 1 AND active = 1) ORDER BY ts LIMIT 0,1)',
        [$level_id],
      );
    return self::teamFromRow($result->fetch());
  }

  public static function completedLevel(
    int $level_id,
  ): array {
    $db = Db::getInstance();

    $result =
      $db->query(
        'SELECT * FROM teams WHERE id IN (SELECT team_id FROM scores_log WHERE level_id = ? ORDER BY ts) AND visible = 1 AND active = 1',
        [$level_id],
      );

    $teams = [];
    foreach ($result->fetchAll() as $row) {
      $teams[] = self::teamFromRow($row);
    }

    return $teams;
  }

  // Get rank position for a team
  public static function myRank(int $team_id): int {
    $rank = 1;
    $leaderboard = MultiTeam::leaderboard();
    foreach ($leaderboard as $team) {
      if ($team_id === $team->getId()) {
        return $rank;
      }
      $rank++;
    }

    return $rank;
  }

  public static function teamUpdatePoints(
    int $team_id,
    int $points,
  ): void {
    $db = Db::getInstance();
    $db->query(
      'UPDATE teams SET last_score = last_score, points = ? WHERE id = ?',
      [$points, $team_id],
    );
    MultiTeam::invalidateMCRecords(); // Invalidate Memcached MultiTeam data.
    Control::invalidateMCRecords('ALL_ACTIVITY'); // Invalidate Memcached Control data.
  }

  public static function authTokenExists(
    string $type,
    string $token,
  ): bool {
    $db = Db::getInstance();

    $team_id_result = $db->query(
      'SELECT team_id FROM teams_oauth WHERE type = ? AND token = ?',
      [$type, $token],
    );

    if ($team_id_result->rowCount() === 1) {
      return true;
    } else {
      return false;
    }
  }

  public static function teamOAuthTokenExists(
    string $type,
    int $team_id,
  ): bool {
    $db = Db::getInstance();

    $team_id_result = $db->query(
      'SELECT id FROM teams_oauth WHERE type = ? AND team_id = ?',
      [$type, $team_id],
    );

    if ($team_id_result->rowCount() === 1) {
      return true;
    } else {
      return false;
    }
  }

  public static function teamFromOAuthToken(
    string $type,
    string $token,
  ): Team {
    $db = Db::getInstance();

    $team_id_result = $db->query(
      'SELECT team_id FROM teams_oauth WHERE type = ? AND token = ?',
      [$type, $token],
    );

    $team_id =
      intval(must_have_idx($team_id_result->fetch(), 'team_id'));
    $team = self::team($team_id);
    return $team;
  }

  public static function setOAuthToken(
    int $team_id,
    string $type,
    string $token,
  ): bool {
    $db = Db::getInstance();

    $oauth_exists_result = $db->query(
      'SELECT id FROM teams_oauth WHERE type = ? AND token = ?',
      [$type, $token],
    );
    $current_id_result = $db->query(
      'SELECT id FROM teams_oauth WHERE team_id = ? AND type = ?',
      [$team_id, $type],
    );

    if ($oauth_exists_result->rowCount() > 0) {
      return false;
    }

    if ($current_id_result->rowCount() === 1) {
      $result = $db->query(
        'UPDATE teams_oauth SET token = ? WHERE id = ?',
        [$token, intval(must_have_idx($current_id_result->fetch(), 'id'))],
      );
      if ($result) {
        return true;
      }
    } else {
      $result = $db->query(
        'INSERT INTO teams_oauth (type, team_id, token) VALUES (?, ?, ?)',
        [$type, $team_id, $token],
      );
      if ($result) {
        return true;
      }
    }
    return false;

  }

  public static function getLiveSyncKey(
    int $team_id,
    string $type,
  ): string {
    $db = Db::getInstance();
    if ($type === 'general') {
      $team = self::team($team_id);
      $username = $team->getName();
      $key = '';
    } else {
      $result = $db->query(
        'SELECT * FROM livesync WHERE team_id = ? AND type = ?',
        [$team_id, $type],
      );
      if (!($result->rowCount() === 1)) { throw new RuntimeException('Expected exactly one result'); }

      $row = $result->fetch();
      $username = strval(must_have_idx($row, 'username'));
      $key_from_db = strval(must_have_idx($row, 'sync_key'));

      switch ($type) {
        case 'fbctf':
          $key = self::generateHash($key_from_db);
          break;
        case 'facebook_oauth':
          $key = $key_from_db;
          $username = '';
          break;
        case 'google_oauth':
          $key = $key_from_db;
          $username = '';
          break;
          // FALLTHROUGH
        default:
          $key = $key_from_db;
          break;
      }
    }

    return strval($type.":".$username.":".$key);
  }

  public static function liveSyncExists(
    int $team_id,
    string $type,
  ): bool {
    $db = Db::getInstance();
    $result = $db->query(
      'SELECT id FROM livesync WHERE team_id = ? AND type = ?',
      [$team_id, $type],
    );
    if ($result->rowCount() === 1) {
      return true;
    }
    return false;
  }

  public static function setLiveSyncPassword(
    int $team_id,
    string $type,
    string $username,
    string $password,
  ): bool {
    $db = Db::getInstance();

    if (($username === '') || ($password === '')) {
      return false;
    }

    switch ($type) {
      case 'fbctf':
        $key = hash("sha256", $password);
        $team = self::team($team_id);
        if (password_verify($password, $team->getPasswordHash())) {
          return false;
        }
        break;
        // FALLTHROUGH
      default:
        $key = $password;
        break;
    }

    $username_result =
      $db->query(
        'SELECT id FROM livesync WHERE username = ? AND type = ? AND team_id != ?',
        [$username, $type, $team_id],
      );
    if ($username_result->rowCount() > 0) {
      return false;
    }

    $current_id_result = $db->query(
      'SELECT id FROM livesync WHERE team_id = ? AND type = ?',
      [$team_id, $type],
    );
    if ($current_id_result->rowCount() === 1) {
      $result = $db->query(
        'UPDATE livesync SET username = ?, sync_key = ? WHERE id = ?',
        [$username, $key, intval(must_have_idx($current_id_result->fetch(), 'id'))],
      );
      if ($result) {
        return true;
      }
    } else {
      $result =
        $db->query(
          'INSERT INTO livesync (type, team_id, username, sync_key) VALUES (?, ?, ?, ?)',
          [$type, $team_id, $username, $key],
        );
      if ($result) {
        return true;
      }
    }
    return false;
  }

  public static function liveSyncKeyExists(
    string $key,
  ): bool {
    $db = Db::getInstance();

    if (strpos($key, ':') === false) {
      return false;
    }
    list($type, $username, $key) = explode(':', $key);

    switch ($type) {
      case 'fbctf':
        $result = $db->query(
          'SELECT * FROM livesync WHERE username = ? AND type = ?',
          [$username, $type],
        );
        break;
      case 'facebook_oauth':
        return Integration::facebookThirdPartyExists($key);
        break;
      case 'google_oauth':
        $result = $db->query(
          'SELECT * FROM livesync WHERE sync_key = ? AND type = ?',
          [$key, $type],
        );
        break;
        // FALLTHROUGH
      default:
        $result = $db->query(
          'SELECT * FROM livesync WHERE sync_key = ?',
          [$key],
        );
        break;
    }

    if ($result->rowCount() > 0) {
      $team_id = 0;
      foreach ($result->fetchAll() as $row) {
        $type = strval(must_have_idx($row, 'type'));
        $username = strval(must_have_idx($row, 'username'));
        $key_from_db = strval(must_have_idx($row, 'sync_key'));

        switch ($type) {
          case 'fbctf':
            if (password_verify($key_from_db, $key)) {
              return true;
            }
            break;
            // FALLTHROUGH
          default:
            if (strval($key) === strval($key_from_db)) {
              return true;
            }
            break;
        }
      }
    }
    return false;
  }

  public static function teamFromLiveSyncKey(
    string $key,
  ): Team {
    $db = Db::getInstance();
    $email = '';

    if (!(strpos($key, ':'))) { throw new RuntimeException("Invalid live sync key"); }
    list($type, $username, $key) = explode(':', $key);

    switch ($type) {
      case 'fbctf':
        $result = $db->query(
          'SELECT * FROM livesync WHERE username = ? AND type = ?',
          [$username, $type],
        );
        if (!($result->rowCount() > 0)) { throw new RuntimeException('Expected at least one result'); }
        break;
      case 'facebook_oauth':
        $email = Integration::facebookThirdPartyEmail($key);
        if (!($email !== '')) { throw new RuntimeException('Expected an email from facebookThirdPartyEmail, non returned.'); }
        $result = $db->query(
          'SELECT * FROM livesync WHERE username = ? AND type = ?',
          [$email, $type],
        );
        break;
      case 'google_oauth':
        $result = $db->query(
          'SELECT * FROM livesync WHERE sync_key = ? AND type = ?',
          [$key, $type],
        );
        break;
        // FALLTHROUGH
      default:
        $result = $db->query(
          'SELECT * FROM livesync WHERE sync_key = ?',
          [$key],
        );
        if (!($result->rowCount() > 0)) { throw new RuntimeException('Expected at least one result'); }
        break;
    }

    $team_id = 0;
    foreach ($result->fetchAll() as $row) {
      $type = strval(must_have_idx($row, 'type'));
      $username = strval(must_have_idx($row, 'username'));
      $key_from_db = strval(must_have_idx($row, 'sync_key'));

      switch ($type) {
        case 'fbctf':
          if (password_verify($key_from_db, $key)) {
            $team_id = intval(must_have_idx($row, 'team_id'));
            $team = self::team($team_id);
            return $team;
          }
          break;
        case 'facebook_oauth':
          if (strval($email) === strval($username)) {
            $team_id = intval(must_have_idx($row, 'team_id'));
            $team = self::team($team_id);
            return $team;
          }
          break;
          // FALLTHROUGH
        default:
          if (strval($key) === strval($key_from_db)) {
            $team_id = intval(must_have_idx($row, 'team_id'));
            $team = self::team($team_id);
            return $team;
          }
          break;
      }
    }
    if (!($team_id !== 0)) { throw new RuntimeException('team_id not found'); }
    $team = self::team($team_id);
    return $team;
  }

}
