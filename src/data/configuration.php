<?php declare(strict_types=1);

require_once ($_SERVER['DOCUMENT_ROOT'].'/../vendor/autoload.php');

class ConfigurationController extends DataController {

  public function generateData(): void {

    SessionUtils::sessionStart();
    SessionUtils::enforceLogin();

    $conf_data = (object) [];

    $control = new Control();

    $gameboard = Configuration::get('gameboard');
    $gameboard_cycle = Configuration::get('gameboard_cycle');
    $conf_cycle = Configuration::get('conf_cycle');

    $conf_data->{'currentTeam'} = SessionUtils::sessionTeamName();
    $conf_data->{'gameboard'} = $gameboard->getValue();
    $conf_data->{'refreshTeams'} = ($gameboard_cycle->getValue()) * 1000;
    $conf_data->{'refreshMap'} = ($gameboard_cycle->getValue()) * 1000;
    $conf_data->{'refreshConf'} = ($conf_cycle->getValue()) * 1000;
    $conf_data->{'refreshCmd'} = ($conf_cycle->getValue()) * 1000;
    $conf_data->{'progressiveCount'} = Progressive::count();

    $this->jsonSend($conf_data);
  }
}

$confController = new ConfigurationController();
$confController->sendData();
