<?php declare(strict_types=1);

require_once ($_SERVER['DOCUMENT_ROOT'].'/../vendor/autoload.php');

class SessionController extends DataController {
  public function generateData(): void {

    SessionUtils::sessionStart();
    SessionUtils::enforceLogin();

    $data = ['true'];
    $this->jsonSend($data);
  }
}

$sessionControler = new SessionController();
$sessionControler->sendData();
