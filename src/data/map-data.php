<?php declare(strict_types=1);

require_once ($_SERVER['DOCUMENT_ROOT'].'/../vendor/autoload.php');

class MapDataController extends DataController {
  public function generateData(): void {

    SessionUtils::sessionStart();
    SessionUtils::enforceLogin();

    $map_data = (object) [];

    $my_team_id = SessionUtils::sessionTeam();
    $my_name = SessionUtils::sessionTeamName();

    $all_levels = Level::allLevelsCountryMap();
    $enabled_countries = Country::allEnabledCountriesForMap();

    foreach ($enabled_countries as $country) {
      $country_level = $all_levels[$country->getId()] ?? null;
      $is_active_level =
        $country_level !== null && $country_level->getActive();
      $active = ($country->getUsed() && $is_active_level) ? 'active' : '';
      if ($country_level) {
        $my_previous_score = ScoreLog::allPreviousScore(
          $country_level->getId(),
          $my_team_id,
          false,
        );
        $other_previous_score = ScoreLog::previousScore(
          $country_level->getId(),
          $my_team_id,
          true,
        );

        // If my team has scored
        if ($my_previous_score) {
          $captured_by = 'you';
          $data_captured = $my_name;
          // If any other team has scored
        } else if ($other_previous_score) {
          $captured_by = 'opponent';
          $completed_by =
            MultiTeam::completedLevel($country_level->getId());
          $data_captured = '';
          foreach ($completed_by as $c) {
            $data_captured .= ' '.$c->getName();
          }
        } else {
          $captured_by = 'no';
          $data_captured = 'no';
        }
      } else {
        $captured_by = 'no';
        $data_captured = 'no';
      }
      $country_data = (object) [
        'status' => $active,
        'captured' => $captured_by,
        'datacaptured' => $data_captured,
      ];
      $map_data->{$country->getIsoCode()} = $country_data;
    }

    $this->jsonSend($map_data);
  }
}

$map = new MapDataController();
$map->sendData();
