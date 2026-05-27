<?php declare(strict_types=1);

require_once ($_SERVER['DOCUMENT_ROOT'].'/../vendor/autoload.php');

class ActivityViewModeModuleController extends ModuleController {
  public function render(): string {
    tr_start();
    $items = '';

    $all_activity = Control::allActivity();
    $config = Configuration::get('language');
    $language = $config->getValue();
    foreach ($all_activity as $score) {
      $translated_country =
        locale_get_display_region('-' . $score['country'], $language);
      $items .=
        '<li class="opponent-team">' .
          '[ ' . htmlspecialchars(time_ago($score['time'])) . ' ]' .
          '<span class="opponent-name">' . htmlspecialchars($score['team']) . '</span>&nbsp;' .
          htmlspecialchars(tr('captured')) . '&nbsp;' . htmlspecialchars($translated_country) .
        '</li>';
    }

    return
      '<div>' .
        '<header class="module-header">' .
          '<h6>' . htmlspecialchars(tr('Activity')) . '</h6>' .
        '</header>' .
        '<div class="module-content">' .
          '<div class="fb-section-border">' .
            '<div class="module-scrollable">' .
              '<ul class="activity-stream">' . $items . '</ul>' .
            '</div>' .
          '</div>' .
        '</div>' .
      '</div>';
  }
}

$activity_generated = new ActivityViewModeModuleController();
$activity_generated->sendRender();
