<?php declare(strict_types=1);

require_once ($_SERVER['DOCUMENT_ROOT'].'/../vendor/autoload.php');

class ActivityModuleController extends ModuleController {
  public function render(): string {

    SessionUtils::sessionStart();
    SessionUtils::enforceLogin();

    tr_start();
    $items = '';

    $all_activity = ActivityLog::allActivity();
    $config = Configuration::get('language');
    $language = $config->getValue();
    $activity_count = count($all_activity);
    $activity_limit = ($activity_count > 100) ? 100 : $activity_count;
    for ($i = 0; $i < $activity_limit; $i++) {
      $activity = $all_activity[$i];
      $subject = $activity->getSubject();
      $entity = $activity->getEntity();
      $ts = $activity->getTs();
      $visible = $activity->getVisible();
      if ($visible === false) {
        continue;
      }
      if (($subject !== '') && ($entity !== '')) {
        $class_li = '';
        $class_span = '';
        list($subject_type, $subject_id) =
          explode(':', $activity->getSubject());
        list($entity_type, $entity_id) = explode(':', $activity->getEntity());
        if ($subject_type === 'Team') {
          if (intval($subject_id) === SessionUtils::sessionTeam()) {
            $class_li = 'your-team';
            $class_span = 'your-name';
          } else {
            $class_li = 'opponent-team';
            $class_span = 'opponent-name';
          }
        }
        if ($entity_type === 'Country') {
          $formatted_entity = locale_get_display_region(
            '-' . $activity->getFormattedEntity(),
            $language,
          );
        } else {
          $formatted_entity = $activity->getFormattedEntity();
        }
        $items .=
          '<li class="' . htmlspecialchars($class_li) . '">' .
            '[ ' . htmlspecialchars(time_ago($ts)) . ' ]' .
            '<span class="' . htmlspecialchars($class_span) . '">' .
              htmlspecialchars($activity->getFormattedSubject()) .
            '</span>&nbsp;' . htmlspecialchars(tr($activity->getAction())) . '&nbsp;' .
            htmlspecialchars($formatted_entity) .
          '</li>';
      } else {
        $items .=
          '<li class="opponent-team">' .
            '[ ' . htmlspecialchars(time_ago($ts)) . ' ]' .
            '<span class="opponent-name">' .
              htmlspecialchars($activity->getFormattedMessage()) .
            '</span>' .
          '</li>';
      }
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

$activity_generated = new ActivityModuleController();
$activity_generated->sendRender();
