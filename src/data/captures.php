<?php declare(strict_types=1);

require_once ($_SERVER['DOCUMENT_ROOT'].'/../vendor/autoload.php');

class CapturesController extends DataController {
  public function generateData(): void {

    SessionUtils::sessionStart();
    SessionUtils::enforceLogin();

    $data = [];

    $my_team_id = SessionUtils::sessionTeam();

    $captures = ScoreLog::allScoresByTeam($my_team_id);

    foreach ($captures as $capture) {
      $data[] = $capture->getLevelId();
    }

    $this->jsonSend($data);
  }
}

$capturesData = new CapturesController();
$capturesData->sendData();
