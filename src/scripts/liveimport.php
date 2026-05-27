<?php declare(strict_types=1);

if (php_sapi_name() !== 'cli') {
  http_response_code(405); // method not allowed
  exit(0);
}

require_once (__DIR__.'/../Db.php');
require_once (__DIR__.'/../Utils.php');
require_once (__DIR__.'/../models/Model.php');
require_once (__DIR__.'/../models/Cache.php');
require_once (__DIR__.'/../models/Importable.php');
require_once (__DIR__.'/../models/Exportable.php');
require_once (__DIR__.'/../models/Level.php');
require_once (__DIR__.'/../models/Team.php');
require_once (__DIR__.'/../models/ScoreLog.php');
require_once (__DIR__.'/../models/HintLog.php');
require_once (__DIR__.'/../models/Category.php');
require_once (__DIR__.'/../models/Configuration.php');
require_once (__DIR__.'/../models/Country.php');
require_once (__DIR__.'/../models/Control.php');
require_once (__DIR__.'/../models/MultiTeam.php');
require_once (__DIR__.'/../models/Integration.php');
require_once (__DIR__.'/../models/Model.php');
require_once
  (__DIR__.'/../../vendor/facebook/graph-sdk/src/Facebook/autoload.php')
;

$long_opts = ['url:', 'sleep:', 'disable-ssl-verification', 'debug'];
$options = getopt('', $long_opts);

if (array_key_exists('url', $options) === false) {
  print
    "Usage:\n".
    "  php ".
    $argv[0].
    " \n".
    "    --url <Sync URL> [Switched allowed multiple times.  Optionally provide custom HTTP headers after URL, pipe delimited] \n".
    "    --sleep <Time to Sleep Between Cycles> [Default: 300] \n".
    "    --disable-ssl-verification [Optional: Disables SSL Certification Verification] \n".
    "    --debug [Optional: Enables Debug Output]\n"
  ;
  exit;
}

if (is_array($options['url']) === false) {
  $urls = [$options['url']];
} else {
  $urls = $options['url'];
}

$sleep =
  (array_key_exists('sleep', $options)) ? intval($options['sleep']) : 300;
$check_certificates =
  (array_key_exists('disable-ssl-verification', $options)) ? false : true;
$debug = (array_key_exists('debug', $options)) ? true : false;

class LiveSyncImport {
  public static function process(
    array $urls,
    bool $check_certificates,
    bool $debug,
  ): void {
    foreach ($urls as $url) {
      $json = self::downloadData($url, $check_certificates);
      $data = json_decode($json);
      if (empty($data) === false) {
        if ((!is_array($data)) && (property_exists($data, 'error'))) {
          self::debug(true, $url, '!!!', strval($data->error));
          continue;
        }
        foreach ($data as $level) {
          $mandatories_set = self::mandatoriesSet($level);
          if ($mandatories_set === false) {
            self::debug(true, $url, '!!!', 'Mandatory Values Not Set');
            continue;
          }
          $level = self::defaults($level);
          $level_id = self::level($url, $level, $debug);
          $teams =
            self::teamCaptures($url, $level, $level_id, $debug);
          self::recalculateScores(
            $url,
            $level,
            $level_id,
            $teams,
            $debug,
          );
        }
      } else {
        self::debug(
          true,
          $url,
          '!!!',
          'JSON Missing or Invalid: '.json_last_error_msg(),
        );
      }
    }
  }

  public static function mandatoriesSet(
    stdClass $level,
  ): bool {
    if (property_exists($level, 'title') === false) {
      return false;
    }
    if (property_exists($level, 'description') === false) {
      return false;
    }
    if (property_exists($level, 'points') === false) {
      return false;
    }
    return true;
  }

  public static function defaults(
    stdClass $level,
  ): stdClass {
    if (property_exists($level, 'active') === false) {
      $level->active = true;
    }
    if (property_exists($level, 'type') === false) {
      $level->type = 'flag';
    }
    if (property_exists($level, 'entity_iso_code') === false) {
      $countries = Country::allAvailableCountries();
      $country = $countries[array_rand($countries)];
      $country_id = $country->getId();
      $level->entity_iso_code = $country->getIsoCode();
      $level->random_country = true;
    }
    if (property_exists($level, 'category') === false) {
      $level->category = 'None';
    }
    if (property_exists($level, 'bonus') === false) {
      $level->bonus = 0;
    }
    if (property_exists($level, 'bonus_dec') === false) {
      $level->bonus_dec = 0;
    }
    if (property_exists($level, 'penalty') === false) {
      $level->penalty = 0;
    }
    if (property_exists($level, 'teams') === false) {
      $level->teams = new stdClass();
    }
    return $level;
  }

  public static function downloadData(
    string $url,
    bool $check_certificates,
  ): string {
    $headers = [];
    if (strpos($url, '|')) {
      $url_options = explode("|", $url);
      $url = array_shift($url_options);
      $headers = $url_options;
    }
    $curl = curl_init();
    curl_setopt($curl, CURLOPT_URL, $url);
    curl_setopt($curl, CURLOPT_RETURNTRANSFER, 1);
    if (isset($headers)) {
      curl_setopt($curl, CURLOPT_HTTPHEADER, $headers);
    }
    if ($check_certificates === false) {
      curl_setopt($curl, CURLOPT_SSL_VERIFYHOST, 0);
      curl_setopt($curl, CURLOPT_SSL_VERIFYPEER, 0);
    }
    $json = curl_exec($curl);
    curl_close($curl);
    if ($json === false) {
      self::debug(true, $url, '!!!', 'Download Error: '.curl_error($curl));
      return '';
    }
    $json = preg_replace('/^[^[]*\[/', '[', $json, 1);
    return $json;
  }

  public static function level(
    string $url,
    stdClass $level,
    bool $debug,
  ): int {
    $level_exists = self::levelExists($level);
    if ($level_exists === false) {
      $level->entity_iso_code = self::country($url, $level, $debug);
    }
    $level_exists = self::levelExists($level);
    if ($level_exists === false) {
      $category_id = self::category($url, $level, $debug);
      $country = Country::getCountry(strval($level->entity_iso_code));
      $country_id = $country->getId();
      $level_id = Level::create(
        strval($level->type),
        strval($level->title),
        strval($level->description),
        $country_id,
        $category_id,
        intval($level->points),
        intval($level->bonus),
        intval($level->bonus_dec),
        intval($level->bonus),
        Team::generateHash(random_bytes(100)),
        '',
        intval($level->penalty),
      );
      $level_active = (intval($level->active) === 1) ? true : false;
      Level::setStatus($level_id, $level_active);
      self::debug(
        $debug,
        $url,
        '+++',
        'Level Created: '.strval($level->title),
      );
    } else {
      $level_id = Level::getLevelIdByTypeTitleCountry(
        strval($level->type),
        strval($level->title),
        strval($level->entity_iso_code),
      );
      $level_active = (intval($level->active) === 1) ? true : false;
      Level::setStatus($level_id, $level_active);
      self::debug(
        $debug,
        $url,
        '===',
        'Level Exists: '.strval($level->title),
      );
    }
    return intval($level_id);
  }

  public static function levelExists(
    stdClass $level,
  ): bool {
    $level_exists = Level::alreadyExist(
      strval($level->type),
      strval($level->title),
      strval($level->entity_iso_code),
    );
    return $level_exists;
  }

  public static function country(
    string $url,
    stdClass $level,
    bool $debug,
  ): string {
    $country = Country::getCountry(strval($level->entity_iso_code));
    $country_used = $country->getUsed();
    if (($country_used === true) ||
        ((property_exists($level, 'random_country')) &&
         ($level->random_country === true))) {
      $level_exists = Level::alreadyExistUnknownCountry(
        strval($level->type),
        strval($level->title),
        strval($level->description),
        intval($level->points),
      );
      if ($level_exists === false) {
        $countries = Country::allAvailableCountries();
        $new_country = $countries[array_rand($countries)];
        self::debug(
          $debug,
          $url,
          '+++',
          'Country Selected: '.
          strval($level->title).
          ' - '.
          strval($new_country->getIsoCode()),
        );
        return strval($new_country->getIsoCode());
      } else {
        $level_exists = true;
        $existing_level = Level::levelUnknownCountry(
          strval($level->type),
          strval($level->title),
          strval($level->description),
          intval($level->points),
        );
        $new_country = Country::get($existing_level->getEntityId());
        self::debug(
          $debug,
          $url,
          '===',
          'Country Found: '.
          strval($level->title).
          ' - '.
          strval($new_country->getIsoCode()),
        );
        return strval($new_country->getIsoCode());
      }
    } else {
      $level_exists = Level::alreadyExistUnknownCountry(
        strval($level->type),
        strval($level->title),
        strval($level->description),
        intval($level->points),
      );
      if ($level_exists === true) {
        $existing_level = Level::levelUnknownCountry(
          strval($level->type),
          strval($level->title),
          strval($level->description),
          intval($level->points),
        );
        $new_country = Country::get($existing_level->getEntityId());
        self::debug(
          $debug,
          $url,
          '===',
          'Country Found: '.
          strval($level->title).
          ' - '.
          strval($new_country->getIsoCode()),
        );
        return strval($new_country->getIsoCode());
      }
    }
    return strval($level->entity_iso_code);
  }

  public static function category(
    string $url,
    stdClass $level,
    bool $debug,
  ): int {
    $category_exists =
      Category::checkExists(strval($level->category));
    if ($category_exists === false) {
      $category_id =
        Category::create(strval($level->category), false);
      self::debug(
        $debug,
        $url,
        '+++',
        'Category Created: '.strval($level->category),
      );
    } else {
      $category =
        Category::singleCategoryByName(strval($level->category));
      $category_id = $category->getId();
      self::debug(
        $debug,
        $url,
        '===',
        'Category Exists: '.strval($level->category),
      );
    }
    return intval($category_id);
  }

  public static function teamCaptures(
    string $url,
    stdClass $level,
    int $level_id,
    bool $debug,
  ): array {
    $teams = json_decode(json_encode($level->teams), true);
    uasort(
      $teams,
      function($a, $b) {
        return strtotime($a['timestamp']) - strtotime($b['timestamp']);
      },
    );
    $teams_array = [];
    $teams = self::teamDefaults($teams);
    foreach ($teams as $team_livesync_key => $team_data) {
      list($type, $username, $key) = explode(':', $team_livesync_key);
      if ($type === 'general') {
        continue;
      }
      $team_exists =
        Team::liveSyncKeyExists(strval($team_livesync_key));
      if ($team_exists === true) {
        $team =
          Team::teamFromLiveSyncKey(strval($team_livesync_key));
        $team_id = $team->getId();
        $hint_used = self::logHint(
          $url,
          $level,
          $level_id,
          $team_id,
          intval($team_data['hint']),
          $debug,
        );
        self::scoreLevel(
          $url,
          $level,
          $level_id,
          $team_id,
          intval($team_data['capture']),
          strval($team_data['timestamp']),
          $debug,
        );
        self::updateTeamScores(
          $url,
          $level,
          $team_id,
          intval($team_data['hint']),
          $hint_used,
          $debug,
        );
        $teams_array[$team_id] = $team_data;
      } else {
        self::debug(
          $debug,
          $url,
          '!!!',
          'Team Not Found: Key: ('.
          strval($team_livesync_key).
          ') '.
          strval($level->title),
        );
      }
    }
    return $teams_array;
  }

  public static function teamDefaults(
    array $teams,
  ): array {
    foreach ($teams as $team_key => $team) {
      if (array_key_exists('capture', $team) === false) {
        $teams[$team_key]['capture'] = false;
      }
      if (array_key_exists('hint', $team) === false) {
        $teams[$team_key]['hint'] = false;
      }
    }
    return $teams;
  }

  public static function logHint(
    string $url,
    stdClass $level,
    int $level_id,
    int $team_id,
    int $hint,
    bool $debug,
  ): bool {
    $team = MultiTeam::team($team_id);
    $team_name = $team->getName();
    $hint_used = false;
    if ($hint === 1) {
      $hint_used = HintLog::previousHint($level_id, $team_id, false);
      if ($hint_used === false) {
        HintLog::logGetHint(
          $level_id,
          $team_id,
          intval($level->penalty),
        );
        self::debug(
          $debug,
          $url,
          '+++',
          'Hint Used: '.strval($team_name).' - '.strval($level->title),
        );
        return true;
      } else {
        self::debug(
          $debug,
          $url,
          '===',
          'Hint Already Used: '.
          strval($team_name).
          ' - '.
          strval($level->title),
        );
        return false;
      }
    }
    return false;
  }

  public static function scoreLevel(
    string $url,
    stdClass $level,
    int $level_id,
    int $team_id,
    int $capture,
    string $timestamp,
    bool $debug,
  ): void {
    if ($capture === 1) {
      $team = MultiTeam::team($team_id);
      $level_capture = Level::scoreLevel($level_id, $team_id);
      $team_name = $team->getName();

      if ($level_capture === true) {
        $scorelog = ScoreLog::levelScoreByTeam($team_id, $level_id);
        ScoreLog::scoreLogUpdate(
          $level_id,
          $team_id,
          $scorelog->getPoints(),
          $level->type,
          $timestamp,
        );
      }
      if ($level_capture === true) {
        self::debug(
          $debug,
          $url,
          '+++',
          'Level Captured: '.strval($team_name).' - '.strval($level->title),
        );
      } else {
        self::debug(
          $debug,
          $url,
          '===',
          'Level Already Captured: '.
          strval($team_name).
          ' - '.
          strval($level->title),
        );
      }
    }
  }

  public static function updateTeamScores(
    string $url,
    stdClass $level,
    int $team_id,
    int $hint,
    bool $hint_used,
    bool $debug,
  ): void {
    if (($hint === 1) && ($hint_used === true)) {
      $team = MultiTeam::team($team_id);
      $team_name = $team->getName();
      Team::update(
        strval($team_name),
        $team->getLogo(),
        $team->getPoints() - intval($level->penalty),
        $team_id,
      );
    }
  }

  public static function recalculateScores(
    string $url,
    stdClass $level,
    int $level_id,
    array $teams,
    bool $debug,
  ): void {
    $level_captured = 0;
    $current_level = Level::get($level_id);
    foreach ($teams as $team_id => $team_data) {
      if (intval($team_data['capture']) === 0) {
        continue;
      }
      $team = MultiTeam::team($team_id);
      $team_name = $team->getName();
      $current_bonus =
        $level->bonus - (intval($level->bonus_dec) * $level_captured);
      if ($current_bonus < 0) {
        $current_bonus = 0;
      }
      $points = intval($level->points) + $current_bonus;
      $scorelog = ScoreLog::levelScoreByTeam($team_id, $level_id);
      $existing_points = $scorelog->getPoints();
      $total_points = $team->getPoints();
      $total_points += $points - $existing_points;
      Team::teamUpdatePoints($team_id, $total_points);
      ScoreLog::updateScoreLogBonus($level_id, $team_id, $points);
      $level_captured++;
    }
    if ($level_captured > 0) {
      self::debug(
        $debug,
        $url,
        '+++',
        'Level Bonuses Recalculated: '.strval($level->title),
      );
    } else {
      self::debug(
        $debug,
        $url,
        '===',
        'Level Bonuses Correct: '.strval($level->title),
      );
    }
  }

  public static function debug(
    bool $debug,
    string $url,
    string $indicator,
    string $message,
  ): void {
    if ($debug === true) {
      print "[".date('D M j G:i:s Y')."] [$url] $indicator $message\n";
    }
  }
}

while (1) {
  LiveSyncImport::process($urls, $check_certificates, $debug);
  sleep($sleep);

  // Flush the local cache before the next import cycle to ensure we get up-to-date team and level data (the script runs continuously).
  Model::deleteLocalCache();
}
