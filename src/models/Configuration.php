<?php declare(strict_types=1);

class Configuration extends Model {

  protected static string $MC_KEY = 'configuration:';

  protected static array $MC_KEYS = [
    'CONFIGURATION' => 'config_field',
    'FACEBOOK_INTEGRATION_APP_ID' => 'integration_facebook_app_id',
    'FACEBOOK_INTEGRATION_APP_SECRET' =>
      'integration_facebook_app_secret',
    'GOOGLE_INTEGRATION_FILE' => 'integration_google_file',
  ];

  private function __construct(
    private int $id,
    private string $field,
    private string $value,
    private string $description,
  ) {}

  public function getId(): int {
    return $this->id;
  }

  public function getField(): string {
    return $this->field;
  }

  public function getValue(): string {
    return $this->value;
  }

  public function getDescription(): string {
    return $this->description;
  }

  // Get configuration entry.
  public static function get(
    string $field,
    bool $refresh = false,
  ): Configuration {
    $mc_result = self::getMCRecords('CONFIGURATION');
    if (!$mc_result || (is_countable($mc_result) && count($mc_result) === 0) || $refresh) {
      $db = Db::getInstance();
      $config_values = [];
      $result = $db->query('SELECT * FROM configuration');
      foreach ($result->fetchAll() as $row) {
        $config_values[strval($row['field'])] = self::configurationFromRow($row);
      }
      self::setMCRecords('CONFIGURATION', $config_values);
      if (!array_key_exists($field, $config_values)) {
        throw new RuntimeException(
          sprintf('config value not found (db): %s', $field),
        );
      }
      $config = $config_values[$field];
      if (!($config instanceof Configuration)) {
        throw new RuntimeException('config cache value should of type Configuration and not null');
      }
      return $config;
    } else {
      if (!is_array($mc_result)) {
        throw new RuntimeException('config cache return should be of type array and not null');
      }
      if (!array_key_exists($field, $mc_result)) {
        throw new RuntimeException(
          sprintf('config value not found (cache): %s', $field),
        );
      }
      $config = $mc_result[$field];
      if (!($config instanceof Configuration)) {
        throw new RuntimeException('config cache value should of type Configuration and not null');
      }
      return $config;
    }
  }

  // Change configuration field.
  public static function update(
    string $field,
    string $value,
  ): void {
    $db = Db::getInstance();
    $db->query(
      'UPDATE configuration SET value = ? WHERE field = ? LIMIT 1',
      [$value, $field],
    );
    if ($field === 'login' && intval($value) === 0) {
      Session::deleteAllUnprotected();
    }

    self::invalidateMCRecords(); // Invalidate Configuration data.
  }

  // Check if field is valid.
  public static function validField(string $field): bool {
    $db = Db::getInstance();
    $result = $db->query(
      'SELECT COUNT(*) FROM configuration WHERE field = ?',
      [$field],
    );

    if (!($result->rowCount() === 1)) {
      throw new RuntimeException('Expected exactly one result');
    }

    return intval(idx(firstx($result->fetchAll()), 'COUNT(*)')) > 0;
  }

  // All the password types.
  public static function allPasswordTypes(): array {
    $db = Db::getInstance();
    $result = $db->query('SELECT * FROM password_types');

    $types = [];
    foreach ($result->fetchAll() as $row) {
      $types[] = self::configurationFromRow($row);
    }

    return $types;
  }

  // Current password type.
  public static function currentPasswordType(): Configuration {
    $db = Db::getInstance();
    $db_result = $db->query(
      'SELECT * FROM password_types WHERE field = (SELECT value FROM configuration WHERE field = ?) LIMIT 1',
      ['password_type'],
    );

    if (!($db_result->rowCount() === 1)) {
      throw new RuntimeException('Expected exactly one result');
    }
    $result = firstx($db_result->fetchAll());

    return self::configurationFromRow($result);
  }

  // All the configuration.
  public static function allConfiguration(): array {
    $db = Db::getInstance();
    $result = $db->query('SELECT * FROM configuration');

    $configuration = [];
    foreach ($result->fetchAll() as $row) {
      $configuration[] = self::configurationFromRow($row);
    }

    return $configuration;
  }

  private static function configurationFromRow(
    array $row,
  ): Configuration {
    return new Configuration(
      intval(must_have_idx($row, 'id')),
      must_have_idx($row, 'field'),
      must_have_idx($row, 'value'),
      must_have_idx($row, 'description'),
    );
  }

  public static function getFacebookOAuthSettingsExists(
    bool $refresh = false,
  ): bool {
    return (self::getFacebookOAuthSettingsAppId($refresh) !== '' &&
            self::getFacebookOAuthSettingsAppSecret($refresh) !== '');
  }

  public static function getFacebookOAuthSettingsAppId(
    bool $refresh = false,
  ): string {
    $mc_result = self::getMCRecords('FACEBOOK_INTEGRATION_APP_ID');
    if (!$mc_result || (is_countable($mc_result) && count($mc_result) === 0) || $refresh) {
      $settings_file = __DIR__ . '/../../settings.ini';
      $config = parse_ini_file($settings_file);
      $app_id = '';
      if (array_key_exists('FACEBOOK_OAUTH_APP_ID', $config) === true) {
        $app_id = strval($config['FACEBOOK_OAUTH_APP_ID']);
      }
      self::setMCRecords('FACEBOOK_INTEGRATION_APP_ID', $app_id);
      return $app_id;
    } else {
      return strval($mc_result);
    }
  }

  public static function getFacebookOAuthSettingsAppSecret(
    bool $refresh = false,
  ): string {
    $mc_result = self::getMCRecords('FACEBOOK_INTEGRATION_APP_SECRET');
    if (!$mc_result || (is_countable($mc_result) && count($mc_result) === 0) || $refresh) {
      $settings_file = __DIR__ . '/../../settings.ini';
      $config = parse_ini_file($settings_file);
      $app_secret = '';
      if (array_key_exists('FACEBOOK_OAUTH_APP_SECRET', $config) === true) {
        $app_secret = strval($config['FACEBOOK_OAUTH_APP_SECRET']);
      }
      self::setMCRecords('FACEBOOK_INTEGRATION_APP_SECRET', $app_secret);
      return $app_secret;
    } else {
      return strval($mc_result);
    }
  }

  public static function getGoogleOAuthFileExists(
    bool $refresh = false,
  ): bool {
    return (self::getGoogleOAuthFile($refresh) !== '');
  }

  public static function getGoogleOAuthFile(bool $refresh = false): string {
    $mc_result = self::getMCRecords('GOOGLE_INTEGRATION_FILE');
    if (!$mc_result || (is_countable($mc_result) && count($mc_result) === 0) || $refresh) {
      $settings_file = __DIR__ . '/../../settings.ini';
      $config = parse_ini_file($settings_file);
      $oauth_file = '';
      if ((array_key_exists('GOOGLE_OAUTH_FILE', $config) === true) &&
          (file_exists($config['GOOGLE_OAUTH_FILE']) === true)) {
        $oauth_file = strval($config['GOOGLE_OAUTH_FILE']);
      }
      self::setMCRecords('GOOGLE_INTEGRATION_FILE', $oauth_file);
      return $oauth_file;
    } else {
      return strval($mc_result);
    }
  }
}
