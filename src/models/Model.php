<?php declare(strict_types=1);

abstract class Model {

  protected static ?Db $db = null;
  protected static ?Memcached $mc = null;
  protected static ?Memcached $mc_write = null;
  protected static string $MC_KEY = '';
  protected static int $MC_EXPIRE = 0; // Defaults to indefinite cache life

  // Used to temporarily store data (like, results from the DB/MC) locally in memory per request
  protected static ?Cache $CACHE = null;

  protected static array $MC_KEYS = [];

  protected static function getDb(): PDO {
    if (self::$db === null) {
      self::$db = Db::getInstance();
    }
    return self::$db->getConnection();
  }

  /**
   * @codeCoverageIgnore
   */
  protected static function getMc(): Memcached {
    if (self::$mc === null) {
      $config = parse_ini_file(__DIR__ . '/../../settings.ini');
      $cluster = must_have_idx($config, 'MC_HOST');
      $port = (int)must_have_idx($config, 'MC_PORT');
      if (is_array($cluster)) {
        $host = $cluster[array_rand($cluster)];
      } else {
        $host = $cluster;
      }
      self::$mc = new Memcached();
      self::$mc->addServer($host, $port);
    }
    return self::$mc;
  }

  public static function getMemcachedStats(): mixed {
    $stats = array();
    $mc = self::getMcWrite();
    foreach ($mc->getServerList() as $node) {
      $mc_node = new Memcached();
      $mc_node->addServer($node['host'], $node['port']);
      $stats[$node['host']] = $mc_node->getStats();
    }
    return $stats;
  }

  /**
   * @codeCoverageIgnore
   */
  protected static function getMcWrite(): Memcached {
    if (self::$mc_write === null) {
      $config = parse_ini_file(__DIR__ . '/../../settings.ini');
      $cluster = must_have_idx($config, 'MC_HOST');
      $port = (int)must_have_idx($config, 'MC_PORT');
      self::$mc_write = new Memcached();
      if (is_array($cluster)) {
        foreach ($cluster as $node) {
          self::$mc_write->addServer($node, $port);
        }
      } else {
        self::$mc_write->addServer($cluster, $port);
      }
    }
    return self::$mc_write;
  }

  protected static function setMCRecords(string $key, mixed $records): void {
    self::getCacheClassObject();
    $cache_key = static::$MC_KEY . (static::$MC_KEYS[$key] ?? '');

    self::writeMCCluster($cache_key, $records);
    self::$CACHE->setCache($cache_key, $records);
  }

  protected static function getMCRecords(string $key): mixed {
    self::getCacheClassObject();
    $cache_key = static::$MC_KEY . (static::$MC_KEYS[$key] ?? '');

    $local_cache_result = self::$CACHE->getCache($cache_key);
    if ($local_cache_result !== false) {
      return $local_cache_result;
    } else {
      $mc = self::getMc();
      $mc_result = $mc->get($cache_key);
      if ($mc_result !== false) {
        self::$CACHE->setCache($cache_key, $mc_result);
      }

      return $mc_result;
    }
  }

  public static function invalidateMCRecords(?string $key = null): void {
    self::getCacheClassObject();

    if ($key === null) {
      foreach (static::$MC_KEYS as $key_name => $mc_key) {
        $cache_key = static::$MC_KEY . (static::$MC_KEYS[$key_name] ?? '');
        self::invalidateMCCluster($cache_key);
        self::$CACHE->deleteCache($cache_key);
      }
    } else {
      $cache_key = static::$MC_KEY . (static::$MC_KEYS[$key] ?? '');
      self::invalidateMCCluster($cache_key);
      self::$CACHE->deleteCache($cache_key);
    }
  }

  public static function flushMCCluster(): bool {
    $mc = self::getMcWrite();
    $status = false;
    foreach ($mc->getServerList() as $node) {
      $mc_node = new Memcached();
      $mc_node->addServer($node['host'], $node['port']);
      $flush_status = $mc_node->flush(0);
      if ($flush_status === true) {
        $status = true;
      }
    }
    return $status;
  }

  protected static function writeMCCluster(
    string $cache_key,
    mixed $records,
  ): void {
    $mc = self::getMcWrite();
    foreach ($mc->getServerList() as $node) {
      $mc_node = new Memcached();
      $mc_node->addServer($node['host'], $node['port']);
      $mc_node->set($cache_key, $records, static::$MC_EXPIRE);
    }
  }

  protected static function invalidateMCCluster(string $cache_key): void {
    $mc = self::getMcWrite();
    foreach ($mc->getServerList() as $node) {
      $mc_node = new Memcached();
      $mc_node->addServer($node['host'], $node['port']);
      $mc_node->delete($cache_key);
    }
  }

  public static function getCacheClassObject(): Cache {
    if (self::$CACHE === null) {
      self::$CACHE = new Cache();
    }
    return self::$CACHE;
  }

  public static function deleteLocalCache(?string $key = null): void {
    self::getCacheClassObject();

    if (get_called_class() === 'Model') {
      self::$CACHE->flushCache();
    } else if ($key === null) {
      foreach (static::$MC_KEYS as $key_name => $mc_key) {
        $cache_key = static::$MC_KEY . (static::$MC_KEYS[$key_name] ?? '');
        self::$CACHE->deleteCache($cache_key);
      }
    } else {
      $cache_key = static::$MC_KEY . (static::$MC_KEYS[$key] ?? '');
      self::$CACHE->deleteCache($cache_key);
    }
  }
}
