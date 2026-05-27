<?php declare(strict_types=1);

class ScoreboardModalController extends ModalController {
  public function generateIndicator(): string {
    $indicator = '<div class="indicator game-progress-indicator">';

    $game = Configuration::get('game');
    if ($game->getValue() === '1') {
      list($start_ts, $end_ts) = [
        Configuration::get('start_ts'),
        Configuration::get('end_ts'),
      ];
      $start_ts = $start_ts->getValue();
      $end_ts = $end_ts->getValue();

      $seconds = intval($end_ts) - intval($start_ts);
      $s_each = intval($seconds / 10);
      $now = time();
      $current_s = intval($now) - intval($start_ts);
      $current = intval($current_s / $s_each);

      for ($i = 0; $i < 10; $i++) {
        $indicator_classes = 'indicator-cell ';
        if ($current >= $i) {
          $indicator_classes .= 'active ';
        }
        $indicator .= '<span class="' . htmlspecialchars($indicator_classes) . '">' . '</span>';
      }
    } else {
      for ($i = 0; $i < 10; $i++) {
        $indicator .= '<span class="indicator-cell">' . '</span>';
        $indicator .= '</div>';
      }
    }
    return $indicator;
  }
  public function render(string $_): string {
    $scoreboard_tbody = '<tbody>';

    // If refresing is enabled, do the needful
    $gameboard = Configuration::get('gameboard');
    if ($gameboard->getValue() === '1') {
      $rank = 1;
      $leaderboard = MultiTeam::leaderboard(false);

      foreach ($leaderboard as $team) {
        $team_id = 'fb-scoreboard--team-'.strval($team->getId());
        $color = '#'.substr(md5($team->getName()), 0, 6).';';
        $style = 'color: '.$color.'; background:'.$color.';';
        list($quiz, $flag, $base) = [
          MultiTeam::pointsByType($team->getId(), 'quiz'),
          MultiTeam::pointsByType($team->getId(), 'flag'),
          MultiTeam::pointsByType($team->getId(), 'base'),
        ];
        $scoreboard_tbody .= '<tr>' . '<td style="width: 10%;" class="el--radio">' . '<input type="checkbox" name="fb-scoreboard-filter" id="' . htmlspecialchars($team_id) . '" value="' . htmlspecialchars($team->getName()) . '" checked />' . '<label class="click-effect" for="' . htmlspecialchars($team_id) . '">' . '<span style="' . htmlspecialchars($style) . '">' . 'FU' . '</span>' . '</label>' . '</td>' . '<td style="width: 10%;">' . htmlspecialchars((string)$rank) . '</td>' . '<td style="width: 40%;">' . htmlspecialchars($team->getName()) . '</td>' . '<td style="width: 10%;">' . htmlspecialchars(strval($quiz)) . '</td>' . '<td style="width: 10%;">' . htmlspecialchars(strval($flag)) . '</td>' . '<td style="width: 10%;">' . htmlspecialchars(strval($base)) . '</td>' . '<td style="width: 10%;">' . htmlspecialchars(strval($team->getPoints())) . '</td>' . '</tr>';
        $scoreboard_tbody .= '</tbody>';
        $rank++;
      }
    }

    $indicator = $this->generateIndicator();
    return
      '<div class="fb-modal-content fb-row-container">' . '<div class="modal-title row-fixed">' . '<h4>' . htmlspecialchars(tr('scoreboard_')) . '</h4>' . '<a href="#" class="js-close-modal">' . '<svg class="icon icon--close">' . '<use href="#icon--close" />' . '</svg>' . '</a>' . '</div>' . '<div class="scoreboard-graphic scoreboard-graphic-container">' . '<svg class="fb-graphic" data-file="data/scores.php" width="820" height="' . "220" . '">' . '</svg>' . '</div>' . '<div class="game-progress fb-progress-bar fb-cf row-fixed">' . $indicator . '<span class="label label--left">' . '[' . htmlspecialchars(tr('Start')) . ']' . '</span>' . '<span class="label label--right">' . '[' . htmlspecialchars(tr('End')) . ']' . '</span>' . '</div>' . '<div class="game-scoreboard fb-row-container">' . '<table class="row-fixed">' . '<thead>' . '<tr>' . '<th style="width: 10%;">' . htmlspecialchars(tr('filter_')) . '</th>' . '<th style="width: 10%;">' . htmlspecialchars(tr('rank_')) . '</th>' . '<th style="width: 40%;">' . htmlspecialchars(tr('team_name_')) . '</th>' . '<th style="width: 10%;">' . htmlspecialchars(tr('quiz_pts_')) . '</th>' . '<th style="width: 10%;">' . htmlspecialchars(tr('flag_pts_')) . '</th>' . '<th style="width: 10%;">' . htmlspecialchars(tr('base_pts_')) . '</th>' . '<th style="width: 10%;">' . htmlspecialchars(tr('total_pts_')) . '</th>' . '</tr>' . '</thead>' . '</table>' . '<div class="row-fluid main-data">' . '<table class="row-fixed">' . $scoreboard_tbody . '</table>' . '</div>' . '</div>' . '</div>';
  }
}
