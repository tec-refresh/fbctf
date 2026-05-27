<?php declare(strict_types=1);

require_once ($_SERVER['DOCUMENT_ROOT'].'/../vendor/autoload.php');

class LeaderboardDataController extends DataController {
  public function generateData(): void {

    SessionUtils::sessionStart();
    SessionUtils::enforceLogin();

    $leaderboard_data = (object) [];

    // If refresing is disabled, exit
    $gameboard = Configuration::get('gameboard');
    if ($gameboard->getValue() === '0') {
      $this->jsonSend($leaderboard_data);
      exit(1);
    }

    $leaders = MultiTeam::leaderboard();
    [$my_team, $my_rank] = MultiTeam::myTeamRank(SessionUtils::sessionTeam());
    $leaderboard_limit = Configuration::get('leaderboard_limit');

    $leaderboard_limit_value = intval($leaderboard_limit->getValue());
    if ($my_rank >= $leaderboard_limit_value) {
      $my_rank = $leaderboard_limit_value."+";
    }

    $my_team_data = (object) [
      'badge' => $my_team->getLogo(),
      'points' => $my_team->getPoints(),
      'rank' => $my_rank,
    ];
    $leaderboard_data->{'my_team'} = $my_team_data;

    $teams_data = (object) [];
    $rank = 1;
    $l_max = (count($leaders) > 5) ? 5 : count($leaders);
    for ($i = 0; $i < $l_max; $i++) {
      $team = $leaders[$i];
      $team_data = (object) [
        'badge' => $team->getLogo(),
        'points' => $team->getPoints(),
        'rank' => $rank,
      ];
      if ($team->getName()) {
        $teams_data->{$team->getName()} = $team_data;
      }
      $rank++;
    }
    $leaderboard_data->{'leaderboard'} = $teams_data;

    $this->jsonSend($leaderboard_data);
  }
}

$leaderboardData = new LeaderboardDataController();
$leaderboardData->sendData();
