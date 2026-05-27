<?php declare(strict_types=1);

class Country extends Model {

  protected static string $MC_KEY = 'country:';

  protected static array $MC_KEYS = [
    'ALL_COUNTRIES' => 'all_countries',
    'ALL_COUNTRIES_BY_ID' => 'all_countries_by_id',
    'ALL_COUNTRIES_FOR_MAP' => 'all_countries_for_map',
    'ALL_ENABLED_COUNTRIES' => 'all_enabled_countries',
    'ALL_ENABLED_COUNTRIES_FOR_MAP' => 'all_enabled_countries_for_map',
    'ALL_AVAILABLE_COUNTRIES' => 'ALL_AVAILABLE_COUNTRIES',
  ];

  private function __construct(
    private int $id,
    private string $iso_code,
    private string $name,
    private int $used,
    private int $enabled,
    private string $d,
    private string $transform,
  ) {}

  public function getId(): int {
    return $this->id;
  }

  public function getIsoCode(): string {
    return $this->iso_code;
  }

  public function getName(): string {
    return $this->name;
  }

  public function getUsed(): bool {
    return $this->used === 1;
  }

  public function getEnabled(): bool {
    return $this->enabled === 1;
  }

  public function getD(): string {
    return $this->d;
  }

  public function getTransform(): string {
    return $this->transform;
  }

  // Make sure all the countries used field is good
  public static function usedAdjust(): void {
    $db = Db::getInstance();
    $db->query('UPDATE countries SET used = 1 WHERE id IN (SELECT entity_id FROM levels)');
    $db->query('UPDATE countries SET used = 0 WHERE id NOT IN (SELECT entity_id FROM levels)');
    self::invalidateMCRecords();
  }

  // Enable or disable a country
  public static function setStatus(
    int $country_id,
    bool $status,
  ): void {
    $db = Db::getInstance();
    $db->query(
      'UPDATE countries SET enabled = ? WHERE id = ?',
      [$status ? 1 : 0, $country_id],
    );
    self::invalidateMCRecords();
  }

  // Set the used flag for a country
  public static function setUsed(
    int $country_id,
    bool $status,
  ): void {
    $db = Db::getInstance();
    $db->query(
      'UPDATE countries SET used = ? WHERE id = ? LIMIT 1',
      [$status ? 1 : 0, $country_id],
    );
    self::invalidateMCRecords();
  }

  private static function all(
    string $sql,
  ): array {
    $db = Db::getInstance();
    $all_countries = [];
    $db_result = $db->query($sql);
    $rows = $db_result->fetchAll();

    foreach ($rows as $row) {
      $all_countries[intval($row['id'])] = self::countryFromRow($row);
    }

    $countries = array_values($all_countries);

    usort(
      $countries,
      function($a, $b) {
        return strcmp($a->name, $b->name);
      },
    );

    return $countries;
  }

  public static function allCountries(
    bool $refresh = false,
  ): array {
    $mc_result = self::getMCRecords('ALL_COUNTRIES');
    if (!$mc_result || (is_countable($mc_result) && count($mc_result) === 0) || $refresh) {
      $all_countries =
        self::all('SELECT * FROM countries ORDER BY iso_code');
      self::setMCRecords('ALL_COUNTRIES', $all_countries);
      return $all_countries;
    } else {
      if (!(is_array($mc_result))) {
        throw new RuntimeException('cache return should be an array of Country');
      }
      return $mc_result;
    }
  }

  public static function allCountriesForMap(
    bool $refresh = false,
  ): array {
    $mc_result = self::getMCRecords('ALL_COUNTRIES_FOR_MAP');
    if (!$mc_result || (is_countable($mc_result) && count($mc_result) === 0) || $refresh) {
      $all_countries =
        self::all('SELECT * FROM countries ORDER BY CHAR_LENGTH(d)');
      self::setMCRecords('ALL_COUNTRIES_FOR_MAP', $all_countries);
      return $all_countries;
    } else {
      if (!(is_array($mc_result))) {
        throw new RuntimeException('cache return should be an array of Country');
      }
      return $mc_result;
    }
  }

  public static function allEnabledCountries(
    bool $refresh = false,
  ): array {
    $mc_result = self::getMCRecords('ALL_ENABLED_COUNTRIES');
    if (!$mc_result || (is_countable($mc_result) && count($mc_result) === 0) || $refresh) {
      $all_countries =
        self::all('SELECT * FROM countries WHERE enabled = 1');
      self::setMCRecords('ALL_ENABLED_COUNTRIES', $all_countries);
      return $all_countries;
    } else {
      if (!(is_array($mc_result))) {
        throw new RuntimeException('cache return should be an array of Country');
      }
      return $mc_result;
    }
  }

  // All enabled countries. The weird sorting is because SVG lack of z-index
  // and things looking like shit in the map. See issue #20.
  public static function allEnabledCountriesForMap(
    bool $refresh = false,
  ): array {
    $mc_result = self::getMCRecords('ALL_ENABLED_COUNTRIES_FOR_MAP');
    if (!$mc_result || (is_countable($mc_result) && count($mc_result) === 0) || $refresh) {
      $all_countries = self::all(
        'SELECT * FROM countries WHERE enabled = 1 ORDER BY CHAR_LENGTH(d)',
      );
      self::setMCRecords('ALL_ENABLED_COUNTRIES_FOR_MAP', $all_countries);
      return $all_countries;
    } else {
      if (!(is_array($mc_result))) {
        throw new RuntimeException('cache return should be an array of Country');
      }
      return $mc_result;
    }
  }

  // All enabled and unused countries
  public static function allAvailableCountries(
    bool $refresh = false,
  ): array {
    $mc_result = self::getMCRecords('ALL_AVAILABLE_COUNTRIES');
    if (!$mc_result || (is_countable($mc_result) && count($mc_result) === 0) || $refresh) {
      $all_countries = self::all(
        'SELECT * FROM countries WHERE enabled = 1 AND used = 0',
      );
      self::setMCRecords('ALL_AVAILABLE_COUNTRIES', $all_countries);
      return $all_countries;
    } else {
      if (!(is_array($mc_result))) {
        throw new RuntimeException('cache return should be an array of Country');
      }
      return $mc_result;
    }
  }

  // Check if country is in an active level
  public static function isActiveLevel(
    int $country_id,
  ): bool {
    return Level::whoUses($country_id) !== null;
  }

  // Get a country by id.
  public static function get(
    int $country_id,
    bool $refresh = false,
  ): Country {
    $mc_result = self::getMCRecords('ALL_COUNTRIES_BY_ID');
    if (!$mc_result || (is_countable($mc_result) && count($mc_result) === 0) || $refresh) {
      $db = Db::getInstance();
      $all_countries = [];
      $result = $db->query('SELECT * FROM countries ORDER BY id');
      foreach ($result->fetchAll() as $row) {
        $all_countries[intval($row['id'])] = self::countryFromRow($row);
      }
      self::setMCRecords('ALL_COUNTRIES_BY_ID', $all_countries);
      if (!array_key_exists($country_id, $all_countries)) {
        throw new RuntimeException('country not found');
      }
      $country = $all_countries[$country_id];
      if (!($country instanceof Country)) {
        throw new RuntimeException('country should be of type Country');
      }
      return $country;
    } else {
      if (!(is_array($mc_result))) {
        throw new RuntimeException('cache return should be an array of Country by Id');
      }
      if (!array_key_exists($country_id, $mc_result)) {
        throw new RuntimeException('country not found');
      }
      $country = $mc_result[$country_id];
      if (!($country instanceof Country)) {
        throw new RuntimeException('country should be of type Country');
      }
      return $country;
    }
  }

  // Get a country by iso_code.
  public static function country(
    string $country,
  ): Country {
    $db = Db::getInstance();
    $result = $db->query(
      'SELECT * FROM countries WHERE iso_code = ? LIMIT 1',
      [$country],
    );

    if (!($result->rowCount() === 1)) {
      throw new RuntimeException('Expected exactly one result');
    }
    return self::countryFromRow($result->fetch());
  }

  // Get a random enabled, unused country ID
  public static function randomAvailableCountryId(): int {
    $db = Db::getInstance();

    $result = $db->query(
      'SELECT id FROM countries WHERE enabled = 1 AND used = 0 ORDER BY RAND() LIMIT 1',
    );

    if (!($result->rowCount() === 1)) {
      throw new RuntimeException('Expected exactly one result');
    }
    return intval(firstx($result->fetchAll())['id']);
  }

  private static function countryFromRow(array $row): Country {
    $config = Configuration::get('language');
    $language = $config->getValue();
    $translated_name = locale_get_display_region(
      '-'.must_have_idx($row, 'iso_code'),
      $language,
    );
    return new Country(
      intval(must_have_idx($row, 'id')),
      must_have_idx($row, 'iso_code'),
      $translated_name,
      intval(must_have_idx($row, 'used')),
      intval(must_have_idx($row, 'enabled')),
      must_have_idx($row, 'd'),
      must_have_idx($row, 'transform'),
    );
  }

  // Check if a country already exists, by iso_code
  public static function checkExists(
    string $country,
  ): bool {
    $db = Db::getInstance();

    $result = $db->query(
      'SELECT COUNT(*) FROM countries WHERE iso_code = ?',
      [$country],
    );

    if ($result->rowCount() > 0) {
      if (!($result->rowCount() === 1)) {
        throw new RuntimeException('Expected exactly one result');
      }
      return (intval(idx($result->fetch(), 'COUNT(*)')) > 0);
    } else {
      return false;
    }
  }

  // Check if a country already exists, by id
  public static function checkExistsById(
    int $entity_id,
  ): bool {
    $db = Db::getInstance();

    $result = $db->query(
      'SELECT COUNT(*) FROM countries WHERE id = ?',
      [$entity_id],
    );

    if ($result->rowCount() > 0) {
      if (!($result->rowCount() === 1)) {
        throw new RuntimeException('Expected exactly one result');
      }
      return (intval(idx($result->fetch(), 'COUNT(*)')) > 0);
    } else {
      return false;
    }
  }

}
