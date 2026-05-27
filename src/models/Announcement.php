<?php declare(strict_types=1);

class Announcement extends Model {

  protected static string $MC_KEY = 'announcement:';

  protected static array $MC_KEYS = ['ALL_ANNOUNCEMENTS' => 'all_announcements'];

  private function __construct(
    private int $id,
    private string $announcement,
    private string $ts,
  ) {}

  public function getId(): int {
    return $this->id;
  }

  public function getAnnouncement(): string {
    return mb_convert_encoding($this->announcement, 'UTF-8');
  }

  public function getTs(): string {
    return $this->ts;
  }

  private static function announcementFromRow(
    array $row,
  ): Announcement {
    return new Announcement(
      intval(must_have_idx($row, 'id')),
      must_have_idx($row, 'announcement'),
      must_have_idx($row, 'ts'),
    );
  }

  public static function create(
    string $announcement,
  ): void {
    $db = Db::getInstance();
    $db->query(
      'INSERT INTO announcements_log (ts, announcement) (SELECT NOW(), ?) LIMIT 1',
      [$announcement],
    );

    self::invalidateMCRecords(); // Invalidate Memcached Announcement data.
  }

  public static function createAuto(
    string $announcement,
  ): void {
    $config_game = Configuration::get('game');
    $config_pause = Configuration::get('game_paused');
    if ((intval($config_game->getValue()) === 1) &&
        (intval($config_pause->getValue()) === 0)) {
      $auto_announce = Configuration::get('auto_announce');
      if ($auto_announce->getValue() === '1') {
        $db = Db::getInstance();
        $db->query(
          'INSERT INTO announcements_log (ts, announcement) (SELECT NOW(), ?) LIMIT 1',
          [$announcement],
        );
        self::invalidateMCRecords(); // Invalidate Memcached Announcement data.
      }
    }
  }

  public static function delete(
    int $announcement_id,
  ): void {
    $db = Db::getInstance();
    $db->query(
      'DELETE FROM announcements_log WHERE id = ? LIMIT 1',
      [$announcement_id],
    );

    self::invalidateMCRecords(); // Invalidate Memcached Announcement data.
  }

  public static function deleteAll(): void {
    $db = Db::getInstance();
    $db->query('TRUNCATE TABLE announcements_log', []);

    self::invalidateMCRecords(); // Invalidate Memcached Announcement data.
  }

  // Get all tokens.
  public static function allAnnouncements(
    bool $refresh = false,
  ): array {
    $mc_result = self::getMCRecords('ALL_ANNOUNCEMENTS');
    if (!$mc_result || count($mc_result) === 0 || $refresh) {
      $db = Db::getInstance();
      $announcements = [];
      $db_result = $db->query(
        'SELECT * FROM announcements_log ORDER BY ts DESC',
        [],
      );
      foreach ($db_result->fetchAll() as $row) {
        $announcements[] = self::announcementFromRow($row);
      }
      self::setMCRecords('ALL_ANNOUNCEMENTS', $announcements);
      return $announcements;
    }
    if (!is_array($mc_result)) { throw new RuntimeException('cached return should be an array of Announcement'); }
    return $mc_result;
  }
}
