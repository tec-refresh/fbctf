<?php declare(strict_types=1);

require_once ($_SERVER['DOCUMENT_ROOT'].'/../vendor/autoload.php');

class WorldMapController extends ModuleController {
  public function render(): string {

    SessionUtils::sessionStart();
    SessionUtils::enforceLogin();

    $worldMap = $this->renderWorldMap();
    return
      '<svg' .
        ' id="fb-gameboard-map"' .
        ' xmlns="http://www.w3.org/2000/svg"' .
        ' amcharts="http://amcharts.com/ammap"' .
        ' xlink="http://www.w3.org/1999/xlink"' .
        ' viewBox="0 0 1008 651"' .
        ' preserveAspectRatio="xMidYMid meet">' .
        '<defs>' .
          '<amcharts:ammap' .
            ' projection="mercator"' .
            ' leftLongitude="-169.6"' .
            ' topLatitude="83.68"' .
            ' rightLongitude="190.25"' .
            ' bottomLatitude="-55.55">' .
          '</amcharts:ammap>' .
        '</defs>' .
        '<g class="view-controller">' .
          $worldMap .
          '<g class="country-hover"></g>' .
        '</g>' .
      '</svg>';
  }

  public function renderWorldMap(): string {
    $svg_countries = '';

    $all_levels = Level::allLevels();
    $all_map_countries = Country::allCountriesForMap();

    $levels_map = [];
    foreach ($all_levels as $level) {
      $levels_map[$level->getEntityId()] = $level;
    }

    foreach ($all_map_countries as $country) {
      $gameboard = Configuration::get('gameboard');
      if ($gameboard->getValue() === '1') {
        $level = $levels_map[$country->getId()] ?? null;
        $is_active_level = $level !== null && $level->getActive();
        $path_class =
          ($country->getUsed() && $is_active_level) ? 'land active' : 'land';
        $map_indicator = 'map-indicator ';
        $data_captured = null;

        if ($level) {
          $my_previous_score = ScoreLog::allPreviousScore(
            $level->getId(),
            SessionUtils::sessionTeam(),
            false,
          );
          $other_previous_score = ScoreLog::previousScore(
            $level->getId(),
            SessionUtils::sessionTeam(),
            true,
          );
          if ($my_previous_score) {
            $map_indicator .= 'captured--you';
            $data_captured = SessionUtils::sessionTeamName();
          } else if ($other_previous_score) {
            $map_indicator .= 'captured--opponent';
            $completed_by =
              MultiTeam::completedLevel($level->getId());
            $data_captured = '';
            foreach ($completed_by as $c) {
              $data_captured .= ' ' . $c->getName();
            }
          }
        }
      } else {
        $path_class = 'land';
        $map_indicator = 'map-indicator ';
        $data_captured = null;
      }

      $data_captured_attr = '';
      if ($data_captured) {
        $data_captured_attr = ' data-captured="' . htmlspecialchars($data_captured) . '"';
      }
      $svg_countries .=
        '<g' . $data_captured_attr . '>' .
          '<path' .
            ' id="' . htmlspecialchars($country->getIsoCode()) . '"' .
            ' title="' . htmlspecialchars($country->getName()) . '"' .
            ' class="' . htmlspecialchars($path_class) . '"' .
            ' d="' . htmlspecialchars($country->getD()) . '">' .
          '</path>' .
          '<g transform="' . htmlspecialchars($country->getTransform()) . '" class="' . htmlspecialchars($map_indicator) . '">' .
            '<path d="M0,9.1L4.8,0h0.1l4.8,9.1v0L0,9.1L0,9.1z"></path>' .
          '</g>' .
        '</g>';
    }

    return '<g class="countries">' . $svg_countries . '</g>';
  }
}

$map = new WorldMapController();
$map->sendRender();
