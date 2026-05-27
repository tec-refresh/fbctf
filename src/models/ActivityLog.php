<?php declare(strict_types=1);

class ActivityLog extends Model {

  protected static string $MC_KEY = 'activitylog:';

  protected static array $MC_KEYS = ['ALL_ACTIVITY' => 'activity'];

  private function __construct(
    private int $id,
    private string $subject,
    private string $action,
    private string $entity,
    private string $message,
    private string $arguments,
    private string $ts,
    private string $formatted_subject = '',
    private string $formatted_entity = '',
    private string $formatted_message = '',
    private bool $visible = true,
  ) {
    $formatted_subject = self::formatString("%s", $this->subject);
    $this->formatted_subject = $formatted_subject;
    $formatted_entity = self::formatString("%s", $this->entity);
    $this->formatted_entity = $formatted_entity;
    $formatted_message = self::formatString($this->message, $this->arguments);
    $this->formatted_message = $formatted_message;
    $visible = self::logEntryVisible($this->subject, $this->action);
    $this->visible = $visible;
  }

  public function getId(): int {
    return $this->id;
  }

  public function getSubject(): string {
    return $this->subject;
  }

  public function getAction(): string {
    return $this->action;
  }

  public function getEntity(): string {
    return $this->entity;
  }

  public function getMessage(): string {
    return $this->message;
  }

  public function getArguments(): string {
    return $this->arguments;
  }

  public function getFormattedSubject(): string {
    return $this->formatted_subject;
  }

  public function getFormattedEntity(): string {
    return $this->formatted_entity;
  }

  public function getFormattedMessage(): string {
    return $this->formatted_message;
  }

  public function getTs(): string {
    return $this->ts;
  }

  public function getVisible(): bool {
    return $this->visible;
  }

  private static function activitylogFromRow(
    array $row,
  ): ActivityLog {
    return new ActivityLog(
      intval(must_have_idx($row, 'id')),
      must_have_idx($row, 'subject'),
      must_have_idx($row, 'action'),
      must_have_idx($row, 'entity'),
      must_have_idx($row, 'message'),
      must_have_idx($row, 'arguments'),
      must_have_idx($row, 'ts'),
    );
  }

  public static function formatString(
    string $string,
    string $arguments,
  ): string {
    if ($arguments !== '') {
      $variables = [];
      $values_array = explode(',', $arguments);
      foreach ($values_array as $value) {
        list($class, $id) = explode(':', $value);
        switch ($class) {
          case "Team":
            $team_exists = Team::teamExistById(intval($id));
            if ($team_exists === true) {
              $team = MultiTeam::team(intval($id));
              $variables[] = $team->getName();
            } else {
              return '';
            }
            break;
          case "Level":
            $level_exists = Level::alreadyExistById(intval($id));
            if ($level_exists === true) {
              $level = Level::get(intval($id));
              $variables[] = $level->getTitle();
            } else {
              return '';
            }
            break;
          case "Country":
            $country_exists = Country::checkExistsById(intval($id));
            if ($country_exists === true) {
              $country = Country::get(intval($id));
              $variables[] = $country->getIsoCode();
            } else {
              return '';
            }
            break;
            // FALLTHROUGH
          default:
            return '';
            break;
        }
      }
      $formatted = vsprintf($string, $variables);
      return $formatted;
    }
    return $string;
  }

  public static function logEntryVisible(
    string $subject,
    string $action,
  ): bool {
    if ($subject === '') {
      return true;
    }
    list($class, $id) = explode(':', $subject);
    if ($class === 'Team' && $action === 'captured') {
      $team_exists = Team::teamExistById(intval($id));
      if ($team_exists === true) {
        $team = MultiTeam::team(intval($id));
        return $team->getVisible();
      } else
        return false;
    }
    return true;
  }

  public static function create(
    string $subject,
    string $action,
    string $entity,
    string $message,
    string $arguments,
  ): void {
    $db = Db::getInstance();
    $db->query(
      'INSERT INTO activity_log (ts, subject, action, entity, message, arguments) (SELECT NOW(), ?, ?, ?, ?, ?) LIMIT 1',
      [$subject, $action, $entity, $message, $arguments],
    );

    self::invalidateMCRecords(); // Invalidate Memcached ActivityLog data.
  }

  public static function captureLog(
    int $team_id,
    int $level_id,
  ): void {
    $country_id = Level::countryIdForLevel($level_id);
    self::createActionLog(
      "Team",
      $team_id,
      "captured",
      "Country",
      $country_id,
    );
  }

  public static function createActionLog(
    string $subject_class,
    int $subject_id,
    string $action,
    string $entity_class,
    int $entity_id,
  ): void {
    self::create(
      "$subject_class:$subject_id",
      $action,
      "$entity_class:$entity_id",
      '',
      '',
    );
  }

  public static function createGameActionLog(
    string $subject_class,
    int $subject_id,
    string $action,
    string $entity_class,
    int $entity_id,
  ): void {
    $config_game = Configuration::get('game');
    $config_pause = Configuration::get('game_paused');
    if ((intval($config_game->getValue()) === 1) &&
        (intval($config_pause->getValue()) === 0)) {
      self::create(
        "$subject_class:$subject_id",
        $action,
        "$entity_class:$entity_id",
        '',
        '',
      );
    }
  }

  public static function adminLog(
    string $action,
    string $entity_class,
    int $entity_id,
  ): void {
    if (SessionUtils::sessionActive() === false) {
      return;
    }
    self::createGameActionLog(
      "Team",
      SessionUtils::sessionTeam(),
      $action,
      $entity_class,
      $entity_id,
    );
  }

  public static function createGenericLog(
    string $message,
    string $arguments = '',
  ): void {
    self::create('', '', '', $message, $arguments);
  }

  public static function delete(
    int $activity_log_id,
  ): void {
    $db = Db::getInstance();
    $db->query(
      'DELETE FROM activity_log WHERE id = ? LIMIT 1',
      [$activity_log_id],
    );

    self::invalidateMCRecords(); // Invalidate Memcached Announcement data.
  }

  public static function deleteAll(): void {
    $db = Db::getInstance();
    $db->query('TRUNCATE TABLE activity_log', []);

    self::invalidateMCRecords(); // Invalidate Memcached Announcement data.
  }

  public static function allActivity(
    bool $refresh = false,
  ): array {
    $mc_result = self::getMCRecords('ALL_ACTIVITY');
    if (!$mc_result || count($mc_result) === 0 || $refresh) {
      $db = Db::getInstance();
      $activity_log_lines = [];
      $result = $db->query(
        'SELECT * FROM activity_log ORDER BY ts DESC LIMIT 100',
        [],
      );
      foreach ($result->fetchAll() as $row) {
        $activity_log = self::activitylogFromRow($row);
        if (($activity_log->getFormattedMessage() !== '') ||
            (($activity_log->getFormattedSubject() !== '') &&
             ($activity_log->getFormattedEntity() !== ''))) {
          $activity_log_lines[] = $activity_log;
        }
      }
      self::setMCRecords('ALL_ACTIVITY', $activity_log_lines);
      return $activity_log_lines;
    }
    if (!is_array($mc_result)) { throw new RuntimeException('cache return should be an array of ActivityLog'); }
    return $mc_result;
  }
}
