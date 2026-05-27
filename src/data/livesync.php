<?php declare(strict_types=1);

require_once ($_SERVER['DOCUMENT_ROOT'].'/../vendor/autoload.php');

class LiveSyncDataController extends DataController {

  public function generateData(): void {
    $data = [];
    tr_start();
    $input_auth_key = idx(Utils::getGET(), 'auth', '');
    $livesync_enabled = Configuration::get('livesync');
    $livesync_auth_key = Configuration::get('livesync_auth_key');

    if ($livesync_enabled->getValue() === '1' &&
        hash_equals(
          strval($livesync_auth_key->getValue()),
          strval($input_auth_key),
        )) {

      $all_teams = Team::allTeams();
      $all_scores = ScoreLog::allScores();
      $all_hints = HintLog::allHints();
      $all_levels = Level::allLevels();

      if (!is_array($all_teams)) {
        throw new RuntimeException('all_teams should be an array and not null');
      }

      if (!is_array($all_scores)) {
        throw new RuntimeException('all_scores should be an array and not null');
      }

      if (!is_array($all_hints)) {
        throw new RuntimeException('all_hints should be an array and not null');
      }

      if (!is_array($all_levels)) {
        throw new RuntimeException('all_levels should be an array and not null');
      }

      $data = [];
      $teams_array = [];
      $team_livesync_exists = [];
      $team_livesync_key = [];
      foreach ($all_teams as $team) {
        $team_livesync_types = [];
        $team_id = $team->getId();

        $team_livesync_types['fbctf'] = Team::liveSyncExists($team_id, 'fbctf');
        $team_livesync_types['facebook_oauth'] = Team::liveSyncExists($team_id, 'facebook_oauth');
        $team_livesync_types['google_oauth'] = Team::liveSyncExists($team_id, 'google_oauth');

        $team_livesync_exists[$team_id] = $team_livesync_types;
      }

      foreach ($team_livesync_exists as $team_id => $livesync_types) {
        $team_livesync_keys = [];
        foreach ($livesync_types as $livesync_type => $livesync_exists) {
          if (boolval($livesync_exists) === true) {
            $team_livesync_keys[$livesync_type] = Team::getLiveSyncKey($team_id, $livesync_type);
          }
        }
        $team_livesync_keys['general'] = Team::getLiveSyncKey($team_id, 'general');
        $team_livesync_key[$team_id] = $team_livesync_keys;
      }
      $teams_array = $team_livesync_key;

      $scores_array = [];
      $scored_teams = [];

      foreach ($all_scores as $score) {
        if (in_array($score->getTeamId(), array_keys($teams_array)) ===
            false) {
          continue;
        }
        $team_livesync_array_scores =
          $team_livesync_key[$score->getTeamId()] ?? null;
        if (!is_array($team_livesync_array_scores)) {
          throw new RuntimeException('team_livesync_array_scores should be of type array and not null');
        }
        foreach ($team_livesync_array_scores as
                 $livesync_type => $livesync_key) {
          $scores_array[$score->getLevelId()][$livesync_key]['timestamp'] =
            $score->getTs();
          $scores_array[$score->getLevelId()][$livesync_key]['capture'] =
            true;
          $scores_array[$score->getLevelId()][$livesync_key]['hint'] = false;
          $scored_teams[$score->getLevelId()][] = $score->getTeamId();
        }
      }
      foreach ($all_hints as $hint) {
        if ($hint->getPenalty()) {
          if (in_array($hint->getTeamId(), array_keys($teams_array)) ===
              false) {
            continue;
          }
          $team_livesync_array_hints =
            $team_livesync_key[$hint->getTeamId()] ?? null;
          if (!is_array($team_livesync_array_hints)) {
            throw new RuntimeException('team_livesync_array_hints should be of type array and not null');
          }
          foreach ($team_livesync_array_hints as
                   $livesync_type => $livesync_key) {
            $scores_array[$hint->getLevelId()][$livesync_key]['hint'] = true;
            if (in_array(
                  $hint->getTeamId(),
                  $scored_teams[$hint->getLevelId()],
                ) ===
                false) {
              $scores_array[$hint->getLevelId()][$livesync_key]['capture'] =
                false;
              $scores_array[$hint->getLevelId()][$livesync_key]['timestamp'] =
                $hint->getTs();
            }
          }
        }
      }

      $levels_array = [];
      $entities = [];
      $categories = [];
      foreach ($all_levels as $level) {
        $level_id = $level->getId();
        $entities[$level_id] = Country::get($level->getEntityId());
        $categories[$level_id] = Category::singleCategory($level->getCategoryId());
      }

      foreach ($all_levels as $level) {
        $level_id = $level->getId();
        $entity = $entities[$level_id] ?? null;
        if (!($entity instanceof Country)) {
          throw new RuntimeException('entity should be of type Country and not null');
        }

        $category = $categories[$level_id] ?? null;
        if (!($category instanceof Category)) {
          throw new RuntimeException('category should be of type Category and not null');
        }

        if (array_key_exists($level->getId(), $scores_array)) {
          $score_level_array = $scores_array[$level_id];
        } else {
          $score_level_array = [];
        }
        $one_level = [
          'active' => $level->getActive(),
          'type' => $level->getType(),
          'title' => $level->getTitle(),
          'description' => $level->getDescription(),
          'entity_iso_code' => $entity->getIsoCode(),
          'category' => $category->getCategory(),
          'points' => $level->getPoints(),
          'bonus' => $level->getBonusFix(),
          'bonus_dec' => $level->getBonusDec(),
          'penalty' => $level->getPenalty(),
          'teams' => $score_level_array,
        ];
        $levels_array[] = $one_level;
      }

      $data = $levels_array;
    } else if ($livesync_enabled->getValue() === '0') {
      $data['error'] = tr(
        'LiveSync is disabled, please contact the administrator for access.',
      );
    } else if (strval($input_auth_key) !==
               strval($livesync_auth_key->getValue())) {
      $data['error'] =
        tr(
          'LiveSync auth key is invalid, please contact the administrator for access.',
        );
    } else {
      $data['error'] = tr(
        'LiveSync failed, please contact the administrator for assistance.',
      );
    }
    $this->jsonSend($data);
  }

}

$syncData = new LiveSyncDataController();
$syncData->sendData();
