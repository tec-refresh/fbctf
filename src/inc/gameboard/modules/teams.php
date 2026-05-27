<?php declare(strict_types=1);

require_once ($_SERVER['DOCUMENT_ROOT'].'/../vendor/autoload.php');

class TeamModuleController extends ModuleController {
  public function render(): string {

    SessionUtils::sessionStart();
    SessionUtils::enforceLogin();

    tr_start();
    $leaderboard = MultiTeam::leaderboard();
    $rank = 1;

    $list_items = '';

    $gameboard = Configuration::get('gameboard');
    if ($gameboard->getValue() === '1') {
      $leaderboard_size = count($leaderboard);
      $leaderboard_limit = Configuration::get('leaderboard_limit');
      $leaderboard_limit_value = intval($leaderboard_limit->getValue());

      if (($leaderboard_size <= $leaderboard_limit_value) ||
          ($leaderboard_limit_value === 0)) {
        $leaderboard_count = $leaderboard_size;
      } else {
        $leaderboard_count = $leaderboard_limit_value;
      }
      for ($i = 0; $i < $leaderboard_count; $i++) {
        $leader = $leaderboard[$i];
        $logo_model = $leader->getLogoModel();
        if ($logo_model->getCustom()) {
          $image =
            '<img class="icon--badge" src="' . htmlspecialchars($logo_model->getLogo()) . '">';
        } else {
          $iconbadge = '#icon--badge-' . htmlspecialchars($logo_model->getName());
          $image =
            '<svg class="icon--badge">' .
              '<use href="' . htmlspecialchars($iconbadge) . '" />' .
            '</svg>';
        }
        $list_items .=
          '<li>' .
            '<a href="#" data-team="' . htmlspecialchars($leader->getName()) . '">' .
              $image .
              '<h6>' . htmlspecialchars($leader->getName()) . '</h6>' .
            '</a>' .
          '</li>';
      }
    }

    return
      '<div>' .
        '<header class="module-header">' .
          '<h6>' . htmlspecialchars(tr('Teams')) . '</h6>' .
        '</header>' .
        '<div class="module-content">' .
          '<div class="fb-section-border">' .
            '<!--' .
              ' Removing the option for people to select their own team for now' .
            ' -->' .
            '<div class="module-scrollable">' .
              '<ul class="grid-list">' . $list_items . '</ul>' .
            '</div>' .
          '</div>' .
        '</div>' .
      '</div>';
  }
}

$teams_generated = new TeamModuleController();
$teams_generated->sendRender();
