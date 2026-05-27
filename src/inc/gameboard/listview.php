<?php declare(strict_types=1);

require_once ($_SERVER['DOCUMENT_ROOT'].'/../vendor/autoload.php');

class ListviewController extends ModuleController {
  public function render(): string {

    SessionUtils::sessionStart();
    SessionUtils::enforceLogin();

    tr_start();
    $table_rows = '';

    $active_levels = Level::allActiveLevels();
    foreach ($active_levels as $level) {
      $country = Country::get(intval($level->getEntityId()));
      $category = Category::singleCategory($level->getCategoryId());
      $previous_score = ScoreLog::allPreviousScore(
        $level->getId(),
        SessionUtils::sessionTeam(),
        false,
      );
      if ($previous_score) {
        $span_status =
          '<span class="fb-status status--yours">' . htmlspecialchars(tr('Captured')) . '</span>';
      } else {
        $span_status =
          '<span class="fb-status status--open">' . htmlspecialchars(tr('Open')) . '</span>';
      }
      $table_rows .=
        '<tr data-country="' . htmlspecialchars($country->getName()) . '">' .
          '<td style="width: 38%;">' .
            htmlspecialchars($country->getName()) . ' (' . htmlspecialchars($level->getTitle()) . ')' .
          '</td>' .
          '<td style="width: 10%;">' . htmlspecialchars(strval($level->getPoints())) . '</td>' .
          '<td style="width: 22%;">' . htmlspecialchars($category->getCategory()) . '</td>' .
          '<td style="width: 30%;">' . $span_status . '</td>' .
        '</tr>';
    }

    return '<div class="listview-container"><table>' . $table_rows . '</table></div>';
  }
}

$listview_generated = new ListviewController();
$listview_generated->sendRender();
