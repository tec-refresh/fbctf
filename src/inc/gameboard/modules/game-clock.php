<?php declare(strict_types=1);

require_once ($_SERVER['DOCUMENT_ROOT'].'/../vendor/autoload.php');

class ClockModuleController extends ModuleController {
  private function generateIndicator(
    string $start_ts,
    string $end_ts,
  ): string {
    $seconds = intval($end_ts) - intval($start_ts);
    $s_each = intval($seconds / 10);
    $now = time();
    $current_s = intval($now) - intval($start_ts);
    if ($s_each === 0) {
      $current = 0;
    } else {
      $current = intval($current_s / $s_each);
    }
    $indicator = '';
    $game = Configuration::get('game');
    if ($game->getValue() === '1') {
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
        $indicator .= '<span class="' . htmlspecialchars($indicator_classes) . '"></span>';
      }
    } else {
      for ($i = 0; $i < 10; $i++) {
        $indicator .= '<span class="indicator-cell"></span>';
      }
    }
    return '<div class="indicator game-progress-indicator">' . $indicator . '</div>';
  }

  public function render(): string {

    SessionUtils::sessionStart();

    tr_start();
    $config_timer = Configuration::get('timer');
    $config_start_ts = Configuration::get('start_ts');
    $config_end_ts = Configuration::get('end_ts');
    $timer = $config_timer->getValue();
    $start_ts = $config_start_ts->getValue();
    $end_ts = $config_end_ts->getValue();

    $now = time();
    $init = intval($end_ts) - $now;

    if ($timer === '1') {
      $num_days = intval(floor($init / 86400));
      $days_int = ($num_days >= 0) ? $num_days : 0;
      $days = sprintf("%02d", $days_int);

      $num_hours = intval(intval($init / 3600) % 24);
      $hours_int = ($num_hours >= 0) ? $num_hours : 0;
      $hours = sprintf("%02d", $hours_int);

      $num_minutes = intval(intval($init / 60) % 60);
      $minutes_int = ($num_minutes >= 0) ? $num_minutes : 0;
      $minutes = sprintf("%02d", $minutes_int);

      $num_seconds = intval($init % 60);
      $seconds_int = ($num_seconds >= 0) ? $num_seconds : 0;
      $seconds = sprintf("%02d", $seconds_int);

      if ($init > 0) {
        $milli_int = rand(0, 99);
      } else {
        $milli_int = 0;
      }
      $milliseconds = sprintf("%02d", $milli_int);
    } else {
      $days_int = 0;
      $days = '--';
      $hours = '--';
      $minutes = '--';
      $seconds = '--';
      $milliseconds = '--';
    }

    if ($days_int > 99) {
      $clock_days_class = "clock-days three-digit";
    } else {
      $clock_days_class = "clock-days";
    }

    $indicator = $this->generateIndicator($start_ts, $end_ts);
    if ($days_int > 0) {
      return
        '<div>' .
          '<header class="module-header">' .
            '<h6>Game Clock</h6>' .
          '</header>' .
          '<div class="module-content module-scrollable">' .
            '<div class="game-clock fb-numbers">' .
              '<span class="' . htmlspecialchars($clock_days_class) . '">' . htmlspecialchars($days) . '</span>:' .
              '<span class="clock-hours">' . htmlspecialchars($hours) . '</span>:' .
              '<span class="clock-minutes">' . htmlspecialchars($minutes) . '</span>:' .
              '<span class="clock-seconds">' . htmlspecialchars($seconds) . '</span>' .
              '<span class="clock-milliseconds" style="display: none;">' .
                htmlspecialchars($milliseconds) .
              '</span>' .
            '</div>' .
            '<div class="game-progress fb-progress-bar">' .
              '<span class="label label--left">[Start]</span>' .
              '<span class="label label--right">[End]</span>' .
              $indicator .
            '</div>' .
          '</div>' .
        '</div>';
    } else {
      return
        '<div>' .
          '<header class="module-header">' .
            '<h6>Game Clock</h6>' .
          '</header>' .
          '<div class="module-content module-scrollable">' .
            '<div class="game-clock fb-numbers">' .
              '<span class="clock-days" style="display: none;">' . htmlspecialchars($days) . '</span>' .
              '<span class="clock-hours">' . htmlspecialchars($hours) . '</span>:' .
              '<span class="clock-minutes">' . htmlspecialchars($minutes) . '</span>:' .
              '<span class="clock-seconds">' . htmlspecialchars($seconds) . '</span>:' .
              '<span class="clock-milliseconds">' . htmlspecialchars($milliseconds) . '</span>' .
            '</div>' .
            '<div class="game-progress fb-progress-bar">' .
              '<span class="label label--left">[Start]</span>' .
              '<span class="label label--right">[End]</span>' .
              $indicator .
            '</div>' .
          '</div>' .
        '</div>';
    }
  }
}

$clock_generated = new ClockModuleController();
$clock_generated->sendRender();
