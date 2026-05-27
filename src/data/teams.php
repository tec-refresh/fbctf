<?php declare(strict_types=1);

require_once ($_SERVER['DOCUMENT_ROOT'].'/../vendor/autoload.php');

class TeamDataController extends DataController {
  public function generateData(): void {

    SessionUtils::sessionStart();
    SessionUtils::enforceLogin();

    $rank = 1;
    $leaderboard = MultiTeam::leaderboard();
    $gameboard = Configuration::get('gameboard');
    $leaderboard_limit = Configuration::get('leaderboard_limit');

    $teams_data = (object) [];

    // If refresing is disabled, exit
    if ($gameboard->getValue() === '0') {
      $this->jsonSend($teams_data);
      exit(1);
    }

    $leaderboard_size = count($leaderboard);
    $leaderboard_limit_value = intval($leaderboard_limit->getValue());
    if (($leaderboard_size <= $leaderboard_limit_value) ||
        ($leaderboard_limit_value === 0)) {
      $leaderboard_count = $leaderboard_size;
    } else {
      $leaderboard_count = $leaderboard_limit_value;
    }
    for ($i = 0; $i < $leaderboard_count; $i++) {
      $team = $leaderboard[$i];
      $base = MultiTeam::pointsByType($team->getId(), 'base');
      $quiz = MultiTeam::pointsByType($team->getId(), 'quiz');
      $flag = MultiTeam::pointsByType($team->getId(), 'flag');

      $logo_model = $team->getLogoModel();

      $team_data = (object) [
        'logo' => [
          'path' => $logo_model->getLogo(),
          'name' => $logo_model->getName(),
          'custom' => $logo_model->getCustom(),
        ],
        'team_members' => [],
        'rank' => $rank,
        'points' => [
          'base' => $base,
          'quiz' => $quiz,
          'flag' => $flag,
          'total' => $team->getPoints(),
        ],
      ];
      if ($team->getName()) {
        $teams_data->{$team->getName()} = $team_data;
      }
      $rank++;
    }

    $this->jsonSend($teams_data);
  }
}

$teamsData = new TeamDataController();
$teamsData->sendData();
