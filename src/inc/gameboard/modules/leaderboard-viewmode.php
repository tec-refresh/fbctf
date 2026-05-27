<?php declare(strict_types=1);

require_once ($_SERVER['DOCUMENT_ROOT'].'/../vendor/autoload.php');

class LeaderboardModuleViewController extends ModuleController {
  public function render(): string {
    tr_start();
    $items = '';

    $rank = 1;
    $leaderboard = MultiTeam::leaderboard();
    foreach ($leaderboard as $team) {
      $xlink_href = '#icon--badge-' . htmlspecialchars($team->getLogo());
      $items .=
        '<li class="fb-user-card">' .
          '<div class="user-avatar">' .
            '<svg class="icon--badge">' .
              '<use href="' . $xlink_href . '"></use>' .
            '</svg>' .
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

    return
      '<div>' .
        '<header class="module-header">' .
          '<h6>' . htmlspecialchars(tr('Leaderboard')) . '</h6>' .
        '</header>' .
        '<div class="module-content module-scrollable leaderboard-viewmode">' .
          '<ul>' . $items . '</ul>' .
        '</div>' .
      '</div>';
  }
}

$leaderboard_generated = new LeaderboardModuleViewController();
$leaderboard_generated->sendRender();
