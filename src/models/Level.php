<?php declare(strict_types=1);

class Level extends Model implements Importable, Exportable {

  protected static string $MC_KEY = 'level:';

  protected static array $MC_KEYS = [
    'LEVEL_BY_COUNTRY' => 'level_by_country',
    'ALL_LEVELS' => 'all_levels',
    'ALL_ACTIVE_LEVELS' => 'active_levels',
    'ALL_LEVELS_COUNTRY_MAP' => 'all_levels_country_map',
  ];

  private function __construct(
    private int $id,
    private int $active,
    private string $type,
    private string $title,
    private string $description,
    private int $entity_id,
    private int $category_id,
    private int $points,
    private int $bonus,
    private int $bonus_dec,
    private int $bonus_fix,
    private string $flag,
    private string $hint,
    private int $penalty,
    private string $created_ts,
  ) {}

  public function getId(): int {
    return $this->id;
  }

  public function getActive(): bool {
    return $this->active === 1;
  }

  public function getType(): string {
    return $this->type;
  }

  public function getTitle(): string {
    return mb_convert_encoding($this->title, 'UTF-8');
  }

  public function getDescription(): string {
    return mb_convert_encoding($this->description, 'UTF-8');
  }

  public function getEntityId(): int {
    return $this->entity_id;
  }

  public function getCategoryId(): int {
    return $this->category_id;
  }

  public function getPoints(): int {
    return $this->points;
  }

  public function getBonus(): int {
    return $this->bonus;
  }

  public function getBonusDec(): int {
    return $this->bonus_dec;
  }

  public function getBonusFix(): int {
    return $this->bonus_fix;
  }

  public function getFlag(): string {
    return $this->flag;
  }

  public function getHint(): string {
    return $this->hint;
  }

  public function getPenalty(): int {
    return $this->penalty;
  }

  public function getCreatedTs(): string {
    return $this->created_ts;
  }

  private static function levelFromRow(array $row): Level {
    return new Level(
      intval(must_have_idx($row, 'id')),
      intval(must_have_idx($row, 'active')),
      must_have_idx($row, 'type'),
      must_have_idx($row, 'title'),
      must_have_idx($row, 'description'),
      intval(must_have_idx($row, 'entity_id')),
      intval(must_have_idx($row, 'category_id')),
      intval(must_have_idx($row, 'points')),
      intval(must_have_idx($row, 'bonus')),
      intval(must_have_idx($row, 'bonus_dec')),
      intval(must_have_idx($row, 'bonus_fix')),
      must_have_idx($row, 'flag'),
      must_have_idx($row, 'hint'),
      intval(must_have_idx($row, 'penalty')),
      must_have_idx($row, 'created_ts'),
    );
  }

  // Retrieve the level that is using one country
  public static function whoUses(
    int $country_id,
    bool $refresh = false,
  ): ?Level {
    $mc_result = self::getMCRecords('LEVEL_BY_COUNTRY');
    if (!$mc_result || count($mc_result) === 0 || $refresh) {
      $db = Db::getInstance();
      $level_by_country = [];
      $result = $db->query('SELECT * FROM levels WHERE active = 1', []);
      foreach ($result->fetchAll() as $row) {
        $level_by_country[intval($row['entity_id'])] = self::levelFromRow($row);
      }
      self::setMCRecords('LEVEL_BY_COUNTRY', $level_by_country);
      if (array_key_exists($country_id, $level_by_country)) {
        return $level_by_country[$country_id];
      } else {
        return null;
      }
    } else {
      if (!is_array($mc_result)) { throw new RuntimeException('cache return should be of type array'); }
      if (array_key_exists($country_id, $mc_result)) {
        return $mc_result[$country_id];
      } else {
        return null;
      }
    }
  }

  // Import levels.
  public static function importAll(
    array $elements,
  ): bool {
    foreach ($elements as $level) {
      $title = must_have_string($level, 'title');
      $type = must_have_string($level, 'type');
      $entity_iso_code = must_have_string($level, 'entity_iso_code');
      $c = must_have_string($level, 'category');
      $exist = self::alreadyExist($type, $title, $entity_iso_code);
      $entity_exist = Country::checkExists($entity_iso_code);
      $category_exist = Category::checkExists($c);
      if (!$exist && $entity_exist && $category_exist) {
        $entity = Country::country($entity_iso_code);
        $category = Category::singleCategoryByName($c);
        $level_id = self::create(
          $type,
          $title,
          must_have_string($level, 'description'),
          $entity->getId(),
          $category->getId(),
          must_have_int($level, 'points'),
          must_have_int($level, 'bonus'),
          must_have_int($level, 'bonus_dec'),
          must_have_int($level, 'bonus_fix'),
          must_have_string($level, 'flag'),
          must_have_string($level, 'hint'),
          must_have_int($level, 'penalty'),
        );
        if (array_key_exists('links', $level)) {
          $links = must_have_idx($level, 'links');
          if (!is_array($links)) { throw new RuntimeException('links must be of type array'); }
          foreach ($links as $link) {
            Link::create($link, $level_id);
          }
        }
        if (array_key_exists('attachments', $level)) {
          $attachments = must_have_idx($level, 'attachments');
          if (!is_array($attachments)) { throw new RuntimeException('attachments must be of type array'); }
          foreach ($attachments as $attachment) {
            Attachment::importAttachments(
              $level_id,
              $attachment['filename'],
              $attachment['type'],
            );
          }
        }
      }
    }
    return true;
  }

  // Export levels.
  public static function exportAll(): array {
    $all_levels_data = [];
    $all_levels = self::allLevels();

    foreach ($all_levels as $level) {
      $entity = Country::get($level->getEntityId());
      $category = Category::singleCategory($level->getCategoryId());
      $links = Link::allLinks($level->getId());
      $attachments = Attachment::allAttachments($level->getId());

      $link_array = [];
      foreach ($links as $link) {
        $link_array[] = $link->getLink();
      }
      $attachment_array = [];
      foreach ($attachments as $attachment) {
        $attachment_array[] = [
          'filename' => $attachment->getFilename(),
          'type' => $attachment->getType(),
        ];
      }
      $one_level = [
        'type' => $level->getType(),
        'title' => $level->getTitle(),
        'active' => $level->getActive(),
        'description' => $level->getDescription(),
        'entity_iso_code' => $entity->getIsoCode(),
        'category' => $category->getCategory(),
        'points' => $level->getPoints(),
        'bonus' => $level->getBonus(),
        'bonus_dec' => $level->getBonusDec(),
        'bonus_fix' => $level->getBonusFix(),
        'flag' => $level->getFlag(),
        'hint' => $level->getHint(),
        'penalty' => $level->getPenalty(),
        'links' => $link_array,
        'attachments' => $attachment_array,
      ];
      array_push($all_levels_data, $one_level);
    }
    return ['levels' => $all_levels_data];
  }

  // Check to see if the level is active.
  public static function checkStatus(
    int $level_id,
    bool $refresh = false,
  ): bool {
    $mc_result = self::getMCRecords('ALL_ACTIVE_LEVELS');
    if (!$mc_result || count($mc_result) === 0 || $refresh) {
      $db = Db::getInstance();
      $active_levels = [];
      $result = $db->query(
        'SELECT * FROM levels WHERE active = 1 ORDER BY id',
        [],
      );
      foreach ($result->fetchAll() as $row) {
        $active_levels[intval($row['id'])] = self::levelFromRow($row);
      }
      self::setMCRecords('ALL_ACTIVE_LEVELS', $active_levels);
      if (array_key_exists($level_id, $active_levels)) {
        return true;
      } else {
        return false;
      }
    } else {
      if (!is_array($mc_result)) { throw new RuntimeException('cache return should be of type array'); }
      if (array_key_exists($level_id, $mc_result)) {
        return true;
      } else {
        return false;
      }
    }
  }

  // Check to see if the level is a base.
  public static function checkBase(
    int $level_id,
    bool $refresh = false,
  ): bool {
    $mc_result = self::getMCRecords('ALL_ACTIVE_LEVELS');
    if (!$mc_result || count($mc_result) === 0 || $refresh) {
      $db = Db::getInstance();
      $active_levels = [];
      $result = $db->query(
        'SELECT * FROM levels WHERE active = 1 ORDER BY id',
        [],
      );
      foreach ($result->fetchAll() as $row) {
        $active_levels[intval($row['id'])] = self::levelFromRow($row);
      }
      self::setMCRecords('ALL_ACTIVE_LEVELS', $active_levels);
      if (array_key_exists($level_id, $active_levels)) {
        $level = $active_levels[$level_id];
        if (!($level instanceof Level)) { throw new RuntimeException('level should be type of Level'); }
        if ($level->type == 'base') {
          return true;
        } else {
          return false;
        }
      } else {
        return false;
      }
    } else {
      if (!is_array($mc_result)) { throw new RuntimeException('cache return should be of type array'); }
      if (array_key_exists($level_id, $mc_result)) {
        $level = $mc_result[$level_id];
        if (!($level instanceof Level)) { throw new RuntimeException('level should be type of Level'); }
        if ($level->type === 'base') {
          return true;
        } else {
          return false;
        }
      } else {
        return false;
      }
    }
  }

  // Create a team and return the created level id.
  public static function create(
    string $type,
    string $title,
    string $description,
    int $entity_id,
    int $category_id,
    int $points,
    int $bonus,
    int $bonus_dec,
    int $bonus_fix,
    string $flag,
    string $hint,
    int $penalty,
  ): int {
    $db = Db::getInstance();

    if ($entity_id === 0) {
      $ent_id = Country::randomAvailableCountryId();
    } else {
      $ent_id = $entity_id;
    }
    $db->query(
      'INSERT INTO levels '.
      '(type, title, description, entity_id, category_id, points, bonus, bonus_dec, bonus_fix, flag, hint, penalty, active, created_ts) '.
      'VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, NOW())',
      [$type, $title, $description, $ent_id, $category_id, $points, $bonus, $bonus_dec, $bonus_fix, $flag, $hint, $penalty, 0],
    );

    // Mark entity as used
    Country::setUsed($ent_id, true);

    // Return the newly created level_id
    $result = $db->query(
      'SELECT id FROM levels WHERE title = ? AND description = ? AND entity_id = ? AND flag = ? AND category_id = ? LIMIT 1',
      [$title, $description, $ent_id, $flag, $category_id],
    );

    self::invalidateMCRecords();
    if (!($result->rowCount() === 1)) { throw new RuntimeException('Expected exactly one result'); }

    $row = $result->fetch();
    $new_level_id = intval(must_have_idx($row, 'id'));

    $country_id = self::countryIdForLevel($new_level_id);
    $country = Country::get($country_id);
    Announcement::createAuto($country->getName()." added!");
    ActivityLog::adminLog("added", "Country", $country_id);

    ActivityLog::invalidateMCRecords('ALL_ACTIVITY');

    return $new_level_id;
  }

  // Create a flag level.
  public static function createFlag(
    string $title,
    string $description,
    string $flag,
    int $entity_id,
    int $category_id,
    int $points,
    int $bonus,
    int $bonus_dec,
    string $hint,
    int $penalty,
  ): int {
    return self::create(
      'flag',
      $title,
      $description,
      $entity_id,
      $category_id,
      $points,
      $bonus,
      $bonus_dec,
      $bonus,
      $flag,
      $hint,
      $penalty,
    );
  }

  // Update a flag level.
  public static function updateFlag(
    string $title,
    string $description,
    string $flag,
    int $entity_id,
    int $category_id,
    int $points,
    int $bonus,
    int $bonus_dec,
    string $hint,
    int $penalty,
    int $level_id,
  ): void {
    self::update(
      $title,
      $description,
      $entity_id,
      $category_id,
      $points,
      $bonus,
      $bonus_dec,
      $bonus,
      $flag,
      $hint,
      $penalty,
      $level_id,
    );
  }

  // Create a quiz level.
  public static function createQuiz(
    string $title,
    string $question,
    string $answer,
    int $entity_id,
    int $points,
    int $bonus,
    int $bonus_dec,
    string $hint,
    int $penalty,
  ): int {
    $db = Db::getInstance();

    $result = $db->query(
      'SELECT id FROM categories WHERE category = ? LIMIT 1',
      ['Quiz'],
    );

    $category_id = intval(must_have_idx($result->fetch(), 'id'));
    return self::create(
      'quiz',
      $title,
      $question,
      $entity_id,
      $category_id,
      $points,
      $bonus,
      $bonus_dec,
      $bonus,
      $answer,
      $hint,
      $penalty,
    );
  }

  // Update a quiz level.
  public static function updateQuiz(
    string $title,
    string $question,
    string $answer,
    int $entity_id,
    int $points,
    int $bonus,
    int $bonus_dec,
    string $hint,
    int $penalty,
    int $level_id,
  ): void {
    $db = Db::getInstance();

    $result = $db->query(
      'SELECT id FROM categories WHERE category = ? LIMIT 1',
      ['Quiz'],
    );

    $category_id = intval(must_have_idx($result->fetch(), 'id'));
    self::update(
      $title,
      $question,
      $entity_id,
      $category_id,
      $points,
      $bonus,
      $bonus_dec,
      $bonus,
      $answer,
      $hint,
      $penalty,
      $level_id,
    );
  }

  // Create a base level.
  public static function createBase(
    string $title,
    string $description,
    int $entity_id,
    int $category_id,
    int $points,
    int $bonus,
    string $hint,
    int $penalty,
  ): int {
    return self::create(
      'base',
      $title,
      $description,
      $entity_id,
      $category_id,
      $points,
      $bonus,
      0,
      $bonus,
      '',
      $hint,
      $penalty,
    );
  }

  // Update a base level.
  public static function updateBase(
    string $title,
    string $description,
    int $entity_id,
    int $category_id,
    int $points,
    int $bonus,
    string $hint,
    int $penalty,
    int $level_id,
  ): void {
    self::update(
      $title,
      $description,
      $entity_id,
      $category_id,
      $points,
      $bonus,
      0,
      $bonus,
      '',
      $hint,
      $penalty,
      $level_id,
    );
  }

  // Update level.
  public static function update(
    string $title,
    string $description,
    int $entity_id,
    int $category_id,
    int $points,
    int $bonus,
    int $bonus_dec,
    int $bonus_fix,
    string $flag,
    string $hint,
    int $penalty,
    int $level_id,
  ): void {
    $db = Db::getInstance();

    if ($entity_id === 0) {
      $ent_id = Country::randomAvailableCountryId();
    } else {
      $ent_id = $entity_id;
    }

    $result = $db->query(
      'UPDATE levels SET title = ?, description = ?, entity_id = ?, category_id = ?, points = ?, '.
      'bonus = ?, bonus_dec = ?, bonus_fix = ?, flag = ?, hint = ?, '.
      'penalty = ? WHERE id = ? LIMIT 1',
      [$title, $description, $ent_id, $category_id, $points, $bonus, $bonus_dec, $bonus_fix, $flag, $hint, $penalty, $level_id],
    );

    // Make sure entities are consistent
    Country::usedAdjust();

    if ($result->rowCount() > 0) {
      $country_id = self::countryIdForLevel($level_id);
      $country = Country::get($country_id);
      ActivityLog::adminLog("updated", "Country", $country_id);
      Announcement::createAuto($country->getName()." updated!");
      self::invalidateMCRecords(); // Invalidate Memcached Level data.
      ActivityLog::invalidateMCRecords('ALL_ACTIVITY'); // Invalidate Memcached ActivityLog data.
    }
  }

  // Delete level.
  public static function delete(int $level_id): void {
    $db = Db::getInstance();

    // Free country first.
    $level = self::get($level_id);
    Country::setUsed($level->getEntityId(), false);

    // Remove team points for level
    $scores = ScoreLog::allScoresByLevel($level_id);
    $level_delete_queries = [];
    foreach ($scores as $score) {
      $team_id = $score->getTeamId();
      $points = $score->getPoints();
      $level_delete_queries[] = sprintf(
        'UPDATE teams SET points = points - %d WHERE id = %d',
        $points,
        $team_id,
      );
    }

    // Remove hint penalties from teams points for level
    $hints = HintLog::allHintsByLevel($level_id);
    foreach ($hints as $hint) {
      $team_id = $hint->getTeamId();
      $penalty = $hint->getPenalty();
      $level_delete_queries[] = sprintf(
        'UPDATE teams SET points = points + %d WHERE id = %d',
        $penalty,
        $team_id,
      );
    }

    // Delete all references to level
    $level_delete_queries[] = sprintf('DELETE FROM levels WHERE id = %d LIMIT 1', $level_id);
    $level_delete_queries[] = sprintf('DELETE FROM hints_log WHERE level_id = %d', $level_id);
    $level_delete_queries[] = sprintf('DELETE FROM scores_log WHERE level_id = %d', $level_id);
    $level_delete_queries[] = sprintf('DELETE FROM failures_log WHERE level_id = %d', $level_id);
    $db->multiQuery($level_delete_queries);

    self::invalidateMCRecords();
    Control::invalidateMCRecords();
    MultiTeam::invalidateMCRecords();
    HintLog::invalidateMCRecords();
    ScoreLog::invalidateMCRecords();
  }

  // Enable or disable level by passing 1 or 0.
  public static function setStatus(
    int $level_id,
    bool $active,
  ): void {
    $db = Db::getInstance();

    $result = $db->query(
      'UPDATE levels SET active = ? WHERE id = ? LIMIT 1',
      [(int) $active, $level_id],
    );

    if ($result->rowCount() > 0) {
      $action = ($active === true) ? "enabled" : "disabled";
      $country_id = self::countryIdForLevel($level_id);
      $country = Country::get($country_id);
      ActivityLog::adminLog($action, "Country", $country_id);
      Announcement::createAuto($country->getName().' '.$action.'!');
      self::invalidateMCRecords();
      ActivityLog::invalidateMCRecords('ALL_ACTIVITY');
    }
  }

  // Enable or disable levels by type.
  public static function setStatusType(
    bool $active,
    string $type,
  ): void {
    $db = Db::getInstance();

    $results = $db->query(
      'UPDATE levels SET active = ? WHERE type = ?',
      [(int) $active, $type],
    );

    if ($results->rowCount() > 0) {
      self::invalidateMCRecords();
    }
  }

  // Enable or disable all levels.
  public static function setStatusAll(
    bool $active,
    string $type,
  ): void {
    $db = Db::getInstance();

    if ($type === 'all') {
      $result = $db->query(
        'SELECT id FROM levels WHERE active = ? AND id >0',
        [(int) !$active],
      );
    } else {
      $result = $db->query(
        'SELECT id FROM levels WHERE active = ? AND type = ?',
        [(int) !$active, $type],
      );
    }
    foreach ($result->fetchAll() as $row) {
      self::setStatus(intval($row['id']), $active);
    }
  }

  // All levels.
  public static function allLevels(
    bool $refresh = false,
  ): array {
    $mc_result = self::getMCRecords('ALL_LEVELS');
    if (!$mc_result || count($mc_result) === 0 || $refresh) {
      $db = Db::getInstance();
      $all_levels = [];
      $result = $db->query('SELECT * FROM levels ORDER BY id', []);
      foreach ($result->fetchAll() as $row) {
        $all_levels[intval($row['id'])] = self::levelFromRow($row);
      }
      self::setMCRecords('ALL_LEVELS', $all_levels);
      return array_values($all_levels);
    } else {
      if (!is_array($mc_result)) { throw new RuntimeException('cache return should be of type array'); }
      return array_values($mc_result);
    }
  }

  public static function allLevelsCountryMap(
    bool $refresh = false,
  ): array {
    $mc_result = self::getMCRecords('ALL_LEVELS_COUNTRY_MAP');
    if (!$mc_result || count($mc_result) === 0 || $refresh) {
      $db = Db::getInstance();
      $all_levels = [];
      $result = $db->query('SELECT * FROM levels ORDER BY id', []);
      foreach ($result->fetchAll() as $row) {
        $all_levels[intval($row['entity_id'])] = self::levelFromRow($row);
      }
      self::setMCRecords('ALL_LEVELS_COUNTRY_MAP', $all_levels);
      return $all_levels;
    } else {
      if (!is_array($mc_result)) { throw new RuntimeException('cache return should be of type array'); }
      return $mc_result;
    }
  }

  // All levels by status.
  public static function allActiveLevels(
    bool $refresh = false,
  ): array {
    $mc_result = self::getMCRecords('ALL_ACTIVE_LEVELS');
    if (!$mc_result || count($mc_result) === 0 || $refresh) {
      $db = Db::getInstance();
      $active_levels = [];
      $result = $db->query(
        'SELECT * FROM levels WHERE active = 1 ORDER BY id',
        [],
      );
      foreach ($result->fetchAll() as $row) {
        $active_levels[intval($row['id'])] = self::levelFromRow($row);
      }
      self::setMCRecords('ALL_ACTIVE_LEVELS', $active_levels);
      return array_values($active_levels);
    } else {
      if (!is_array($mc_result)) { throw new RuntimeException('cache return should be of type array'); }
      return array_values($mc_result);
    }
  }

  // All levels by status.
  public static function allActiveBases(
    bool $refresh = false,
  ): array {
    $mc_result = self::getMCRecords('ALL_ACTIVE_LEVELS');
    if (!$mc_result || count($mc_result) === 0 || $refresh) {
      $db = Db::getInstance();
      $active_levels = [];
      $result = $db->query(
        'SELECT * FROM levels WHERE active = 1 ORDER BY id',
        [],
      );
      foreach ($result->fetchAll() as $row) {
        $active_levels[intval($row['id'])] = self::levelFromRow($row);
      }
      self::setMCRecords('ALL_ACTIVE_LEVELS', $active_levels);
      $levels = array_values($active_levels);
      $bases = [];
      foreach ($levels as $level) {
        if ($level->type === 'base') {
          $bases[] = $level;
        }
      }
      return $bases;
    } else {
      if (!is_array($mc_result)) { throw new RuntimeException('cache return should be of type array'); }
      $levels = array_values($mc_result);
      $bases = [];
      foreach ($levels as $level) {
        if ($level->type === 'base') {
          $bases[] = $level;
        }
      }
      return $bases;
    }
  }

  // All levels by type.
  public static function allTypeLevels(
    string $type,
    bool $refresh = false,
  ): array {
    $mc_result = self::getMCRecords('ALL_LEVELS');
    if (!$mc_result || count($mc_result) === 0 || $refresh) {
      $db = Db::getInstance();
      $all_levels = [];
      $result = $db->query('SELECT * FROM levels ORDER BY id', []);
      foreach ($result->fetchAll() as $row) {
        $all_levels[intval($row['id'])] = self::levelFromRow($row);
      }
      self::setMCRecords('ALL_LEVELS', $all_levels);
      $levels = array_values($all_levels);
      $type_levels = [];
      foreach ($levels as $level) {
        if ($level->type === $type) {
          $type_levels[] = $level;
        }
      }
      return $type_levels;
    } else {
      if (!is_array($mc_result)) { throw new RuntimeException('cache return should be of type array'); }
      $levels = array_values($mc_result);
      $type_levels = [];
      foreach ($levels as $level) {
        if ($level->type === $type) {
          $type_levels[] = $level;
        }
      }
      return $type_levels;
    }
  }

  // All quiz levels.
  public static function allQuizLevels(): array {
    return self::allTypeLevels('quiz');
  }

  // All base levels.
  public static function allBaseLevels(): array {
    return self::allTypeLevels('base');
  }

  // All flag levels.
  public static function allFlagLevels(): array {
    return self::allTypeLevels('flag');
  }

  // Get a single level.
  public static function get(
    int $level_id,
    bool $refresh = false,
  ): Level {
    $mc_result = self::getMCRecords('ALL_LEVELS');
    if (!$mc_result || count($mc_result) === 0 || $refresh) {
      $db = Db::getInstance();
      $all_levels = [];
      $result = $db->query('SELECT * FROM levels ORDER BY id', []);
      foreach ($result->fetchAll() as $row) {
        $all_levels[intval($row['id'])] = self::levelFromRow($row);
      }
      self::setMCRecords('ALL_LEVELS', $all_levels);
      if (!array_key_exists($level_id, $all_levels)) { throw new RuntimeException('level not found'); }
      $level = $all_levels[$level_id];
      if (!($level instanceof Level)) { throw new RuntimeException('level should be of type Level'); }
      return $level;
    } else {
      if (!is_array($mc_result)) { throw new RuntimeException('cache return should be of type array'); }
      if (!array_key_exists($level_id, $mc_result)) { throw new RuntimeException('level not found'); }
      $level = $mc_result[$level_id];
      if (!($level instanceof Level)) { throw new RuntimeException('level should be of type Level'); }
      return $level;
    }
  }

  // Check if flag is correct.
  public static function checkAnswer(
    int $level_id,
    string $answer,
  ): bool {
    $level = self::get($level_id);
    $type = $level->getType();
    if ($type === "flag") {
      return trim($level->getFlag()) === trim($answer); // case sensitive
    } else {
      return
        strtoupper(trim($level->getFlag())) === strtoupper(trim($answer)); // case insensitive
    }
  }

  // Adjust bonus.
  public static function adjustBonus(int $level_id): void {
    $db = Db::getInstance();

    $db->query(
      'UPDATE levels SET bonus = GREATEST(bonus - bonus_dec, 0) WHERE id = ? LIMIT 1',
      [$level_id],
    );

    self::invalidateMCRecords(); // Invalidate Memcached Level data.
  }

  // Log base request.
  public static function logBaseEntry(
    int $level_id,
    int $code,
    string $response,
  ): void {
    $db = Db::getInstance();

    $db->query(
      'INSERT INTO bases_log (ts, level_id, code, response) VALUES (NOW(), ?, ?, ?)',
      [$level_id, $code, $response],
    );

    self::invalidateMCRecords(); // Invalidate Memcached Level data.
  }

  private static function withLock(
    string $lock_name,
    int $team_id,
    callable $thunk,
  ): mixed {
    $lock_name =
      sprintf('%s/%s_%d', sys_get_temp_dir(), $lock_name, $team_id);
    $lock = fopen($lock_name, 'w');

    if ($lock === false) {
      error_log('Failed to open lock file '.$lock_name);
      return null;
    }
    if (!flock($lock, LOCK_EX)) {
      fclose($lock);
      return null;
    }

    $result = $thunk();

    // Release the scoring lock
    flock($lock, LOCK_UN);
    fclose($lock);

    return $result;
  }

  // Score level. Works for quiz and flags.
  public static function scoreLevel(
    int $level_id,
    int $team_id,
  ): bool {
    $result =
      self::withLock(
        'score_level_lock',
        $team_id,
        function() use ($level_id, $team_id) {
          $db = Db::getInstance();

          // Check if team has already scored this level
          $previous_score =
            ScoreLog::allPreviousScore($level_id, $team_id, false);
          if ($previous_score) {
            return false;
          }

          $level = self::get($level_id);

          // Calculate points to give
          $points = $level->getPoints() + $level->getBonus();

          // Log the score
          $captured = ScoreLog::logValidScore(
            $level_id,
            $team_id,
            $points,
            $level->getType(),
          );

          if ($captured === true) {
            // Adjust bonus
            self::adjustBonus($level_id);

            // Score!
            $db->query(
              'UPDATE teams SET points = points + ?, last_score = NOW() WHERE id = ? LIMIT 1',
              [$points, $team_id],
            );
          }

          self::invalidateMCRecords(); // Invalidate Memcached Level data.

          return true;
        },
      );

    return boolval($result);
  }

  // Score base.
  public static function scoreBase(
    int $level_id,
    int $team_id,
  ): bool {
    $result =
      self::withLock(
        'score_base_lock',
        $team_id,
        function() use ($level_id, $team_id) {
          $db = Db::getInstance();

          $level = self::get($level_id);

          // Calculate points to give
          $score =
            ScoreLog::allPreviousScore($level_id, $team_id, false);
          if ($score) {
            $points = $level->getPoints();
          } else {
            $points = $level->getPoints() + $level->getBonus();
          }

          // Score!
          $db->query(
            'UPDATE teams SET points = points + ?, last_score = NOW() WHERE id = ? LIMIT 1',
            [$points, $team_id],
          );

          // Log the score...
          ScoreLog::logValidScore(
            $level_id,
            $team_id,
            $points,
            $level->getType(),
          );

          self::invalidateMCRecords();

          return true;
        },
      );

    return boolval($result);
  }

  // Get hint.
  public static function levelHint(
    int $level_id,
    int $team_id,
  ): ?string {
    $result =
      self::withLock(
        'hint_lock',
        $team_id,
        function() use ($level_id, $team_id) {
          $db = Db::getInstance();

          $level = self::get($level_id);
          $penalty = $level->getPenalty();

          // Check if team has already gotten this hint or if the team has scored this already
          // If so, hint is free
          $hint = HintLog::previousHint($level_id, $team_id, false);
          $score = ScoreLog::allPreviousScore($level_id, $team_id, false);
          if ($hint || $score) {
            $penalty = 0;
          }

          // Make sure team has enough points to pay
          $team = MultiTeam::team($team_id);
          if ($team->getPoints() < $penalty) {
            return null;
          }

          // Adjust points and log the hint
          $db->query(
            'UPDATE teams SET points = points - ? WHERE id = ? LIMIT 1',
            [$penalty, $team_id],
          );
          HintLog::logGetHint($level_id, $team_id, $penalty);

          ActivityLog::invalidateMCRecords('ALL_ACTIVITY'); // Invalidate Memcached ActivityLog data.
          MultiTeam::invalidateMCRecords('ALL_TEAMS'); // Invalidate Memcached MultiTeam data.
          MultiTeam::invalidateMCRecords('POINTS_BY_TYPE'); // Invalidate Memcached MultiTeam data.
          MultiTeam::invalidateMCRecords('LEADERBOARD'); // Invalidate Memcached MultiTeam data.
          $completed_level = MultiTeam::completedLevel($level_id);
          if (count($completed_level) === 0) {
            MultiTeam::invalidateMCRecords('TEAMS_FIRST_CAP'); // Invalidate Memcached MultiTeam data.
          }
          MultiTeam::invalidateMCRecords('TEAMS_BY_LEVEL'); // Invalidate Memcached MultiTeam data.

          // Hint!
          return $level->getHint();
        },
      );

    return $result !== null ? strval($result) : null;
  }

  // Get the IP from a base level.
  public static function baseIP(int $base_id): string {
    $links = Link::allLinks($base_id);
    $link = $links[0];
    $ip = explode(':', $link->getLink())[0];

    return $ip;
  }

  // Request all bases
  public static function getBasesResponses(
    array $bases,
  ): array {
    // Iterates and request all the bases endpoints for owner
    $responses = [];
    $curl_handlers = [];
    $multi_handler = curl_multi_init();

    // Create the list of request handlers
    foreach ($bases as $base) {
      $base_id = intval(must_have_idx($base, 'id'));
      $base_url = must_have_idx($base, 'url');
      $curl_handlers[$base_id] = curl_init();
      curl_setopt($curl_handlers[$base_id], CURLOPT_URL, $base_url);
      curl_setopt($curl_handlers[$base_id], CURLOPT_HEADER, 0);
      curl_setopt($curl_handlers[$base_id], CURLOPT_RETURNTRANSFER, 1);
      curl_setopt($curl_handlers[$base_id], CURLOPT_PORT, 12345);
      curl_setopt($curl_handlers[$base_id], CURLOPT_TIMEOUT, 3);
      curl_multi_add_handle($multi_handler, $curl_handlers[$base_id]);
    }

    // Run each request by executing all the handlers
    $running = 0;
    do {
      curl_multi_exec($multi_handler, $running);
    } while ($running > 0);

    // Get responses and remove handlers
    foreach ($curl_handlers as $id => $c) {
      $r = [
        'id' => intval($id),
        'response' => curl_multi_getcontent($c),
      ];
      curl_multi_remove_handle($multi_handler, $c);
      array_push($responses, $r);
    }

    curl_multi_close($multi_handler);

    // Return the responses
    return $responses;
  }

  // Bases processing and scoring.
  public static function baseScoring(): void {
    $document_root = must_have_string(Utils::getSERVER(), 'DOCUMENT_ROOT');
    $cmd =
      'hhvm -vRepo.Central.Path=/var/run/hhvm/.hhvm.hhbc_bases '.
      $document_root.
      '/scripts/bases.php > /dev/null 2>&1 & echo $!';
    $pid = shell_exec($cmd);
    Control::startScriptLog(intval($pid), 'bases', $cmd);
  }

  // Stop bases processing and scoring process.
  public static function stopBaseScoring(): void {
    // Kill running process
    $pid = Control::scriptPid('bases');
    if ($pid > 0) {
      exec('kill -9 '.escapeshellarg(strval($pid)));
    }
    // Mark process as stopped
    Control::stopScriptLog($pid);
  }

  // Check if a level already exists by type, title and entity.
  public static function alreadyExist(
    string $type,
    string $title,
    string $entity_iso_code,
  ): bool {
    $db = Db::getInstance();

    $result = $db->query(
      'SELECT COUNT(*) FROM levels WHERE type = ? AND title = ? AND entity_id IN (SELECT id FROM countries WHERE iso_code = ?)',
      [$type, $title, $entity_iso_code],
    );

    if ($result->rowCount() > 0) {
      if (!($result->rowCount() === 1)) { throw new RuntimeException('Expected exactly one result'); }
      return (intval(idx($result->fetch(), 'COUNT(*)')) > 0);
    } else {
      return false;
    }
  }

  // Check if a level already exists by type, title and entity.
  public static function alreadyExistById(
    int $level_id,
  ): bool {
    $db = Db::getInstance();

    $result = $db->query(
      'SELECT COUNT(*) FROM levels WHERE id = ?',
      [$level_id],
    );

    if ($result->rowCount() > 0) {
      if (!($result->rowCount() === 1)) { throw new RuntimeException('Expected exactly one result'); }
      return (intval(idx($result->fetch(), 'COUNT(*)')) > 0);
    } else {
      return false;
    }
  }

  // Check if a level already exists by type, title and entity.
  public static function countryIdForLevel(
    int $level_id,
  ): int {
    $level = self::get($level_id);
    return $level->getEntityId();
  }

  public static function getLevelIdByTypeTitleCountry(
    string $type,
    string $title,
    string $entity_iso_code,
  ): int {
    $db = Db::getInstance();

    $result = $db->query(
      'SELECT id FROM levels WHERE type = ? AND title = ? AND entity_id IN (SELECT id FROM countries WHERE iso_code = ?)',
      [$type, $title, $entity_iso_code],
    );

    if (!($result->rowCount() === 1)) { throw new RuntimeException('Expected exactly one result'); }
    return intval(must_have_idx($result->fetch(), 'id'));
  }

  public static function alreadyExistUnknownCountry(
    string $type,
    string $title,
    string $description,
    int $points,
  ): bool {
    $db = Db::getInstance();
    $result = $db->query(
      'SELECT COUNT(*) FROM levels WHERE type = ? AND title = ? AND description = ? AND points = ?',
      [$type, $title, $description, $points],
    );
    if ($result->rowCount() > 0) {
      if (!($result->rowCount() === 1)) { throw new RuntimeException('Expected exactly one result'); }
      return (intval(idx($result->fetch(), 'COUNT(*)')) > 0);
    } else {
      return false;
    }
  }

  public static function levelIdUnknownCountry(
    string $type,
    string $title,
    string $description,
    int $points,
  ): int {
    $db = Db::getInstance();

    $result = $db->query(
      'SELECT id FROM levels WHERE type = ? AND title = ? AND description = ? AND points = ?',
      [$type, $title, $description, $points],
    );

    if (!($result->rowCount() === 1)) { throw new RuntimeException('Expected exactly one result'); }
    return intval(must_have_idx($result->fetch(), 'id'));
  }

  public static function levelUnknownCountry(
    string $type,
    string $title,
    string $description,
    int $points,
  ): Level {

    $level_id = self::levelIdUnknownCountry(
      $type,
      $title,
      $description,
      $points,
    );

    $level = self::get($level_id);
    return $level;
  }
}
