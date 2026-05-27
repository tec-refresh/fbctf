<?php declare(strict_types=1);

require_once ($_SERVER['DOCUMENT_ROOT'].'/../vendor/autoload.php');

class ScoresDataController extends DataController {
  public function generateData(): void {

    SessionUtils::sessionStart();
    SessionUtils::enforceLogin();

    $data = [];

    $leaderboard = MultiTeam::leaderboard(false);
    foreach ($leaderboard as $team) {
      $values = [];
      $i = 1;
      $progressive_scoreboard =
        Progressive::progressiveScoreboard($team->getName());
      foreach ($progressive_scoreboard as $progress) {
        $score =
          (object) ['time' => $i, 'score' => $progress->getPoints()];
        array_push($values, $score);
        $i++;
      }
      $color = substr(md5($team->getName()), 0, 6);
      $element = (object) [
        'team' => $team->getName(),
        'color' => '#'.$color,
        'values' => $values,
      ];
      array_push($data, $element);
    }

    $this->jsonSend($data);
  }
}

$scoresData = new ScoresDataController();
$scoresData->sendData();
