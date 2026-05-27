<?php declare(strict_types=1);

require_once ($_SERVER['DOCUMENT_ROOT'].'/../vendor/autoload.php');

class LeaderboardModuleController extends ModuleController {
  public function render(): string {

    SessionUtils::sessionStart();
    SessionUtils::enforceLogin();

    tr_start();
    $items = '';

    $my_team = MultiTeam::team(SessionUtils::sessionTeam());
    $my_rank = Team::myRank(SessionUtils::sessionTeam());
    $gameboard = Configuration::get('gameboard');

    // If refresing is enabled, do the needful
    if ($gameboard->getValue() === '1') {
      $leaders = MultiTeam::leaderboard();
      $rank = 1;
      $l_max = (count($leaders) > 5) ? 5 : count($leaders);
      for ($i = 0; $i < $l_max; $i++) {
        $team = $leaders[$i];

        // TODO also duplicated in modules/teams.php. Needs to be un-duplicated.
        $logo_model = $team->getLogoModel();
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

        $xlink_href = '#icon--badge-' . $team->getLogo();
        $items .=
          '<li class="fb-user-card">' .
            '<div class="user-avatar">' .
              $image .
            '</div>' .
            '<div class="player-info">' .
              '<h6>' . htmlspecialchars($team->getName()) . '</h6>' .
              '<span class="player-rank">' . htmlspecialchars(tr('Rank')) . '&nbsp;' . $rank . '</span>' .
              '<br>' .
              '<span class="player-score">' .
                htmlspecialchars(strval($team->getPoints())) . '&nbsp;' . htmlspecialchars(tr('pts')) .
              '</span>' .
            '</div>' .
          '</li>';
        $rank++;
      }
    }

    if ($my_team->getVisible() === true) {
      $leaderboard_limit = Configuration::get('leaderboard_limit');
      if ($my_rank >= intval($leaderboard_limit->getValue())) {
        $my_rank = intval($leaderboard_limit->getValue()) + 1 . "+";
      }
    } else {
      $my_rank = "N/A";
    }

    return
      '<div>' .
        '<header class="module-header">' .
          '<h6>' . htmlspecialchars(tr('Leaderboard')) . '</h6>' .
        '</header>' .
        '<div class="module-content">' .
          '<div class="fb-section-border">' .
            '<div class="module-top player-info">' .
              '<h5 class="player-name">' . htmlspecialchars($my_team->getName()) . '</h5>' .
              '<span class="player-rank">' . htmlspecialchars(tr('Your Rank')) . ': ' . htmlspecialchars(strval($my_rank)) . '</span>' .
              '<br>' .
              '<span class="player-score">' .
                htmlspecialchars(tr('Your Score')) . ': ' . htmlspecialchars(strval($my_team->getPoints())) . '&nbsp;' .
                htmlspecialchars(tr('pts')) .
              '</span>' .
            '</div>' .
            '<div class="module-scrollable leaderboard-info">' .
              '<ul>' . $items . '</ul>' .
            '</div>' .
          '</div>' .
        '</div>' .
      '</div>';
  }
}

$leaderboard_generated = new LeaderboardModuleController();
$leaderboard_generated->sendRender();
