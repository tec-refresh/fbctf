<?php declare(strict_types=1);

class Attachment extends Model {
  // TODO: Configure this
  const string attachmentsDir = '/var/www/fbctf/attachments/';

  protected static string $MC_KEY = 'attachments:';

  protected static array $MC_KEYS = [
    'LEVELS_COUNT' => 'attachment_levels_count',
    'LEVEL_ATTACHMENTS' => 'attachment_levels',
    'ATTACHMENTS' => 'attachments_by_id',
    'LEVEL_ATTACHMENTS_NAMES' => 'attachment_file_names',
    'LEVEL_ATTACHMENTS_LINKS' => 'attachment_file_links',
  ];

  private function __construct(
    private int $id,
    private int $levelId,
    private string $filename,
    private string $link,
    private string $type,
  ) {}

  public function getId(): int {
    return $this->id;
  }

  public function getFilename(): string {
    return $this->filename;
  }

  public function getFileLink(): string {
    return $this->link;
  }

  public function getType(): string {
    return $this->type;
  }

  public function getLevelId(): int {
    return $this->levelId;
  }

  // Create attachment for a given level.
  public static function create(
    string $file_param,
    string $filename,
    int $level_id,
  ): bool {
    $db = Db::getInstance();
    $type = '';
    $file_path = self::attachmentsDir;
    $local_filename = '';

    $files = Utils::getFILES();
    $server = Utils::getSERVER();
    // First we put the file in its place
    if (array_key_exists($file_param, $files)) {
      $tmp_name = $files[$file_param]['tmp_name'];
      $type = $files[$file_param]['type'];
      $md5_str = md5_file($tmp_name);

      // Extract extension and name
      $parts = explode('.', $filename, 2);
      $local_filename .=
        mb_convert_encoding($parts[0], 'UTF-8').'_'.$md5_str;

      $extension = $parts[1] ?? null;
      if ($extension !== null) {
        $local_filename .= '.'.mb_convert_encoding($extension, 'UTF-8');
      }

      // Remove all non alphanum characters from filename - allow international chars, dash, underscore, and period
      $local_filename =
        preg_replace('/[^\p{L}\p{N}_\-.]+/u', '_', $local_filename);

      move_uploaded_file($tmp_name, $file_path.$local_filename);

      // Force 0600 Permissions
      $chmod = chmod($file_path.$local_filename, 0600);
      if (!($chmod === true)) { throw new RuntimeException('Failed to set attachment file permissions to 0600'); }

      // Force ownership to www-data
      $chown = chown($file_path.$local_filename, 'www-data');
      if (!($chown === true)) { throw new RuntimeException('Failed to set attachment file ownership to www-data'); }

    } else {
      return false;
    }

    // Then database shenanigans
    $db->query(
      'INSERT INTO attachments (filename, type, level_id, created_ts) VALUES (?, ?, ?, NOW())',
      [$local_filename, (string) $type, $level_id],
    );

    self::invalidateMCRecords(); // Invalidate Memcached Attachment data.

    return true;
  }

  // Modify existing attachment.
  public static function update(
    int $id,
    int $level_id,
    string $filename,
  ): void {
    $db = Db::getInstance();
    $db->query(
      'UPDATE attachments SET filename = ?, level_id = ? WHERE id = ? LIMIT 1',
      [$filename, $level_id, $id],
    );
    self::invalidateMCRecords(); // Invalidate Memcached Attachment data.
  }

  // Delete existing attachment.
  public static function delete(int $attachment_id): void {
    $db = Db::getInstance();
    $server = Utils::getSERVER();

    // Copy file to deleted folder
    $attachment = self::get($attachment_id);
    $filename = self::attachmentsDir.$attachment->getFilename();
    $parts = pathinfo($filename);
    error_log(
      'Copying from '.
      $filename.
      ' to '.
      $parts['dirname'].
      '/deleted/'.
      $parts['basename'],
    );
    $origin = $filename;
    $dest = $parts['dirname'].'/deleted/'.$parts['basename'];
    copy($origin, $dest);

    // Delete file.
    unlink($origin);

    // Delete from table.
    $db->query(
      'DELETE FROM attachments WHERE id = ? LIMIT 1',
      [$attachment_id],
    );
    self::invalidateMCRecords(); // Invalidate Memcached Attachment data.
  }

  // Get all attachments for a given level.
  public static function allAttachments(
    int $level_id,
    bool $refresh = false,
  ): array {
    $mc_result = self::getMCRecords('LEVEL_ATTACHMENTS');
    if (!$mc_result || count($mc_result) === 0 || $refresh) {
      $db = Db::getInstance();
      $attachments = [];
      $result = $db->query('SELECT * FROM attachments', []);
      foreach ($result->fetchAll() as $row) {
        $attachments[$row['level_id']][] = self::attachmentFromRow($row);
      }
      self::setMCRecords('LEVEL_ATTACHMENTS', $attachments);
      if (array_key_exists($level_id, $attachments)) {
        $attachment = $attachments[$level_id];
        if (!is_array($attachment)) { throw new RuntimeException('attachment should be an array of Attachment'); }
        return $attachment;
      } else {
        return [];
      }
    } else {
      if (!is_array($mc_result)) { throw new RuntimeException('cache return should be of type array'); }
      if (array_key_exists($level_id, $mc_result)) {
        $attachment = $mc_result[$level_id];
        if (!is_array($attachment)) { throw new RuntimeException('attachment should be an array of Attachment'); }
        return $attachment;
      } else {
        return [];
      }
    }
  }

  public static function allAttachmentsForGame(
    bool $refresh = false,
  ): array {
    $mc_result = self::getMCRecords('LEVEL_ATTACHMENTS');
    if (!$mc_result || count($mc_result) === 0 || $refresh) {
      $db = Db::getInstance();
      $attachments = [];
      $result = $db->query('SELECT * FROM attachments', []);
      foreach ($result->fetchAll() as $row) {
        $attachments[intval($row['level_id'])][] =
          self::attachmentFromRow($row);
      }
      self::setMCRecords('LEVEL_ATTACHMENTS', $attachments);
      if (!is_array($attachments)) { throw new RuntimeException('attachments should be an array of Attachment'); }
      return $attachments;
    } else {
      if (!is_array($mc_result)) { throw new RuntimeException('cache return should be of type array'); }
      return $mc_result;
    }
  }

  public static function allAttachmentsFileNames(
    int $level_id,
    bool $refresh = false,
  ): array {
    $mc_result = self::getMCRecords('LEVEL_ATTACHMENTS_NAMES');
    if (!$mc_result || count($mc_result) === 0 || $refresh) {
      $db = Db::getInstance();
      $filenames = [];
      $attachments = self::allAttachmentsForGame();
      if (!is_array($attachments)) { throw new RuntimeException('attachments should be an array of Attachment'); }
      foreach ($attachments as $level => $attachment_arr) {
        if (!is_array($attachment_arr)) { throw new RuntimeException('attachment_arr should be an array of Attachment'); }
        foreach ($attachment_arr as $attach_obj) {
          if (!($attach_obj instanceof Attachment)) { throw new RuntimeException('link_obj should be of type Attachment'); }
          $filenames[$level][] = $attach_obj->getFilename();
        }
      }
      self::setMCRecords('LEVEL_ATTACHMENTS_NAMES', $filenames);
      if (array_key_exists($level_id, $filenames)) {
        $filename = $filenames[$level_id];
        if (!is_array($filename)) { throw new RuntimeException('filename should be an array of string'); }
        return $filename;
      } else {
        return [];
      }
    } else {
      if (!is_array($mc_result)) { throw new RuntimeException('cache return should be of type array'); }
      if (array_key_exists($level_id, $mc_result)) {
        $filename = $mc_result[$level_id];
        if (!is_array($filename)) { throw new RuntimeException('filename should be an array of string'); }
        return $filename;
      } else {
        return [];
      }
    }
  }

  public static function allAttachmentsFileLinks(
    int $level_id,
    bool $refresh = false,
  ): array {
    $mc_result = self::getMCRecords('LEVEL_ATTACHMENTS_LINKS');
    if (!$mc_result || count($mc_result) === 0 || $refresh) {
      $db = Db::getInstance();
      $attachment_links = [];
      $attachments = self::allAttachmentsForGame();
      if (!is_array($attachments)) { throw new RuntimeException('attachments should be an array of Attachment'); }
      foreach ($attachments as $level => $attachment_arr) {
        if (!is_array($attachment_arr)) { throw new RuntimeException('attachment_arr should be an array of Attachment'); }
        foreach ($attachment_arr as $attach_obj) {
          if (!($attach_obj instanceof Attachment)) { throw new RuntimeException('link_obj should be of type Attachment'); }
          $attachment_links[$level][] = $attach_obj->getFileLink();
        }
      }
      self::setMCRecords('LEVEL_ATTACHMENTS_LINKS', $attachment_links);
      if (array_key_exists($level_id, $attachment_links)) {
        $attachment_link = $attachment_links[$level_id];
        if (!is_array($attachment_link)) { throw new RuntimeException('attachment link should be an array of string'); }
        return $attachment_link;
      } else {
        return [];
      }
    } else {
      if (!is_array($mc_result)) { throw new RuntimeException('cache return should be of type array'); }
      if (array_key_exists($level_id, $mc_result)) {
        $attachment_link = $mc_result[$level_id];
        if (!is_array($attachment_link)) { throw new RuntimeException('attachment_link should be an array of string'); }
        return $attachment_link;
      } else {
        return [];
      }
    }
  }

  public static function allAttachmentsFileNamesLinks(
    int $level_id,
    bool $refresh = false,
  ): array {
    $filenames_links = [];
    $file_names = self::allAttachmentsFileNames($level_id);
    $file_links = self::allAttachmentsFileLinks($level_id);

    foreach ($file_names as $idx => $file_name) {
      if (($file_links[$idx] ?? null) !== null) {
        $filenames_links[$idx]['filename'] = $file_name;
        $filenames_links[$idx]['file_link'] = $file_links[$idx];
      }
    }
    return $filenames_links;
  }

  // Get a single attachment.
  public static function get(
    int $attachment_id,
    bool $refresh = false,
  ): Attachment {
    $mc_result = self::getMCRecords('ATTACHMENTS');
    if (!$mc_result || count($mc_result) === 0 || $refresh) {
      $db = Db::getInstance();
      $attachments = [];
      $result = $db->query('SELECT * FROM attachments', []);
      foreach ($result->fetchAll() as $row) {
        $attachments[intval($row['id'])] = self::attachmentFromRow($row);
      }
      self::setMCRecords('ATTACHMENTS', $attachments);
      if (array_key_exists($attachment_id, $attachments)) {
        $attachment = $attachments[$attachment_id];
        if (!($attachment instanceof Attachment)) { throw new RuntimeException('attachment should be of type Attachment'); }
        return $attachment;
      }
    } else {
      if (!is_array($mc_result)) { throw new RuntimeException('cache return should be of type array'); }
      if (array_key_exists($attachment_id, $mc_result)) {
        $attachment = $mc_result[$attachment_id];
        if (!($attachment instanceof Attachment)) { throw new RuntimeException('attachment should be of type Attachment'); }
        return $attachment;
      }
    }
    throw new RuntimeException('attachment not found');
  }

  public static function checkActive(
    int $attachment_id
  ): bool {
    $db = Db::getInstance();
    $result = $db->query('SELECT active FROM levels
    WHERE id=(SELECT level_id FROM attachments WHERE id=?) AND active = 1', [$attachment_id]);
    $rows = $result->rowCount();
    if ($rows) {
      return true;
    }
    return false;
  }

  public static function checkExists(
    int $attachment_id,
    bool $refresh = false,
  ): bool {
    $mc_result = self::getMCRecords('ATTACHMENTS');
    if (!$mc_result || count($mc_result) === 0 || $refresh) {
      $db = Db::getInstance();
      $attachments = [];
      $result = $db->query('SELECT * FROM attachments', []);
      foreach ($result->fetchAll() as $row) {
        $attachments[intval($row['id'])] = self::attachmentFromRow($row);
      }
      self::setMCRecords('ATTACHMENTS', $attachments);
      return array_key_exists($attachment_id, $attachments);
    } else {
      if (!is_array($mc_result)) { throw new RuntimeException('cache return should be of type array'); }
      return array_key_exists($attachment_id, $mc_result);
    }
  }

  // Check if a level has attachments.
  public static function hasAttachments(
    int $level_id,
    bool $refresh = false,
  ): bool {
    $mc_result = self::getMCRecords('LEVELS_COUNT');
    if (!$mc_result || count($mc_result) === 0 || $refresh) {
      $db = Db::getInstance();
      $attachment_count = [];
      $result = $db->query(
        'SELECT levels.id as level_id, COUNT(attachments.id) as count FROM levels LEFT JOIN attachments ON levels.id = attachments.level_id GROUP BY levels.id',
        [],
      );
      foreach ($result->fetchAll() as $row) {
        $attachment_count[intval($row['level_id'])] = intval($row['count']);
      }
      self::setMCRecords('LEVELS_COUNT', $attachment_count);
      if (array_key_exists($level_id, $attachment_count)) {
        $level_attachment_count = $attachment_count[$level_id];
        return intval($level_attachment_count) > 0;
      } else {
        return false;
      }
    } else {
      if (!is_array($mc_result)) { throw new RuntimeException('attachments should be of type array'); }
      if (array_key_exists($level_id, $mc_result)) {
        $level_attachment_count = $mc_result[$level_id];
        return intval($level_attachment_count) > 0;
      } else {
        return false;
      }
    }
  }

  public static function importAttachments(
    int $level_id,
    string $filename,
    string $type,
  ): bool {
    $db = Db::getInstance();
    $db->query(
      'INSERT INTO attachments (filename, type, level_id, created_ts) VALUES (?, ?, ?, NOW())',
      [$filename, (string) $type, $level_id],
    );

    return true;
  }

  private static function attachmentFromRow(
    array $row,
  ): Attachment {
    return new Attachment(
      intval(must_have_idx($row, 'id')),
      intval(must_have_idx($row, 'level_id')),
      must_have_idx($row, 'filename'),
      strval('/data/attachment.php?id='.intval(must_have_idx($row, 'id'))),
      must_have_idx($row, 'type'),
    );
  }
}
