<?php declare(strict_types=1);

class Link extends Model {

  protected static string $MC_KEY = 'links:';

  protected static array $MC_KEYS = [
    'LEVELS_COUNT' => 'link_levels_count',
    'LEVEL_LINKS' => 'link_levels',
    'LINKS' => 'link_by_id',
    'LEVEL_LINKS_VALUES' => 'link_level_values',
  ];

  private function __construct(
    private int $id,
    private int $levelId,
    private string $link,
  ) {}

  public function getId(): int {
    return $this->id;
  }

  public function getLevelId(): int {
    return $this->levelId;
  }

  public function getLink(): string {
    return mb_convert_encoding($this->link, 'UTF-8');
  }

  // Create link for a given level.
  public static function create(
    string $link,
    int $level_id,
  ): void {
    $db = Db::getInstance();
    $db->query(
      'INSERT INTO links (link, level_id, created_ts) VALUES (?, ?, NOW())',
      [$link, $level_id],
    );
    self::invalidateMCRecords(); // Invalidate Memcached Links data.
  }

  // Modify existing link.
  public static function update(
    string $link,
    int $level_id,
    int $link_id,
  ): void {
    $db = Db::getInstance();
    $db->query(
      'UPDATE links SET link = ?, level_id = ? WHERE id = ? LIMIT 1',
      [$link, $level_id, $link_id],
    );
    self::invalidateMCRecords(); // Invalidate Memcached Links data.
  }

  // Delete existing link.
  public static function delete(int $link_id): void {
    $db = Db::getInstance();
    $db->query('DELETE FROM links WHERE id = ? LIMIT 1', [$link_id]);
    self::invalidateMCRecords(); // Invalidate Memcached Links data.
  }

  // Get all links for a given level.
  public static function allLinks(
    int $level_id,
    bool $refresh = false,
  ): array {
    $mc_result = self::getMCRecords('LEVEL_LINKS');
    if (!$mc_result || count($mc_result) === 0 || $refresh) {
      $db = Db::getInstance();
      $links = [];
      $result = $db->query('SELECT * FROM links', []);
      foreach ($result->fetchAll() as $row) {
        $links[$row['level_id']][] = self::linkFromRow($row);
      }
      self::setMCRecords('LEVEL_LINKS', $links);
      if (array_key_exists($level_id, $links)) {
        $link_array = $links[$level_id];
        if (!is_array($link_array)) { throw new RuntimeException('$link_array should be an array of Link'); }
        return $link_array;
      } else {
        return [];
      }
    } else {
      if (!is_array($mc_result)) { throw new RuntimeException('links should be of type array'); }
      if (array_key_exists($level_id, $mc_result)) {
        $link_array = $mc_result[$level_id];
        if (!is_array($link_array)) { throw new RuntimeException('$link_array should be an array of Link'); }
        return $link_array;
      } else {
        return [];
      }
    }
  }

  public static function allLinksForGame(
    bool $refresh = false,
  ): array {
    $mc_result = self::getMCRecords('LEVEL_LINKS');
    if (!$mc_result || count($mc_result) === 0 || $refresh) {
      $db = Db::getInstance();
      $links = [];
      $result = $db->query('SELECT * FROM links', []);
      foreach ($result->fetchAll() as $row) {
        $links[intval($row['level_id'])][] = self::linkFromRow($row);
      }
      self::setMCRecords('LEVEL_LINKS', $links);
      if (!is_array($links)) { throw new RuntimeException('links should be an array of Link'); }
      return $links;
    } else {
      if (!is_array($mc_result)) { throw new RuntimeException('links should be of type array'); }
      return $mc_result;
    }
  }

  public static function allLinksValues(
    int $level_id,
    bool $refresh = false,
  ): array {
    $mc_result = self::getMCRecords('LEVEL_LINKS_VALUES');
    if (!$mc_result || count($mc_result) === 0 || $refresh) {
      $db = Db::getInstance();
      $link_values = [];
      $links = self::allLinksForGame();
      if (!is_array($links)) { throw new RuntimeException('link should be an array of Link'); }
      foreach ($links as $level => $link_arr) {
        if (!is_array($link_arr)) { throw new RuntimeException('link_arr should be an array of Link'); }
        foreach ($link_arr as $link_obj) {
          if (!($link_obj instanceof Link)) { throw new RuntimeException('link_obj should be of type Link'); }
          $link_values[$level][] = $link_obj->getLink();
        }
      }
      self::setMCRecords('LEVEL_LINKS_VALUES', $link_values);
      if (array_key_exists($level_id, $link_values)) {
        $link_array = $link_values[$level_id];
        if (!is_array($link_array)) { throw new RuntimeException('link_array should be an array of string'); }
        return $link_array;
      } else {
        return [];
      }
    } else {
      if (!is_array($mc_result)) { throw new RuntimeException('links should be of type array'); }
      if (array_key_exists($level_id, $mc_result)) {
        $link_array = $mc_result[$level_id];
        if (!is_array($link_array)) { throw new RuntimeException('link_array should be an array of string'); }
        return $link_array;
      } else {
        return [];
      }
    }
  }

  // Get a single link.
  public static function get(
    int $link_id,
    bool $refresh = false,
  ): Link {
    $mc_result = self::getMCRecords('LINKS');
    if (!$mc_result || count($mc_result) === 0 || $refresh) {
      $db = Db::getInstance();
      $links = [];
      $result = $db->query('SELECT * FROM links', []);
      foreach ($result->fetchAll() as $row) {
        $links[intval($row['id'])] = self::linkFromRow($row);
      }
      self::setMCRecords('LINKS', $links);
      if (!array_key_exists($link_id, $links)) { throw new RuntimeException('link not found'); }
      $link = $links[$link_id];
      if (!($link instanceof Link)) { throw new RuntimeException('link should be of type Link'); }
      return $link;
    } else {
      if (!is_array($mc_result)) { throw new RuntimeException('links should be of type array'); }
      if (!array_key_exists($link_id, $mc_result)) { throw new RuntimeException('link not found'); }
      $link = $mc_result[$link_id];
      if (!($link instanceof Link)) { throw new RuntimeException('link should be of type Link'); }
      return $link;
    }
  }

  // Check if a level has links.
  public static function hasLinks(
    int $level_id,
    bool $refresh = false,
  ): bool {
    $mc_result = self::getMCRecords('LEVELS_COUNT');
    if (!$mc_result || count($mc_result) === 0 || $refresh) {
      $db = Db::getInstance();
      $link_count = [];
      $result = $db->query(
        'SELECT levels.id as level_id, COUNT(links.id) as count FROM levels LEFT JOIN links ON levels.id = links.level_id GROUP BY levels.id',
        [],
      );
      foreach ($result->fetchAll() as $row) {
        $link_count[intval($row['level_id'])] = intval($row['count']);
      }
      self::setMCRecords('LEVELS_COUNT', $link_count);
      if (array_key_exists($level_id, $link_count)) {
        $level_link_count = $link_count[$level_id];
        return intval($level_link_count) > 0;
      } else {
        return false;
      }
    } else {
      if (!is_array($mc_result)) { throw new RuntimeException('link_count should be of type array'); }
      if (array_key_exists($level_id, $mc_result)) {
        $level_link_count = $mc_result[$level_id];
        return intval($level_link_count) > 0;
      } else {
        return false;
      }
    }
  }

  private static function linkFromRow(array $row): Link {
    return new Link(
      intval(must_have_idx($row, 'id')),
      intval(must_have_idx($row, 'level_id')),
      must_have_idx($row, 'link'),
    );
  }
}
