<?php declare(strict_types=1);

require_once ($_SERVER['DOCUMENT_ROOT'].'/../vendor/autoload.php');

class AnnouncementsModuleController extends ModuleController {
  public function render(): string {

    SessionUtils::sessionStart();
    SessionUtils::enforceLogin();

    tr_start();
    $announcements = Announcement::allAnnouncements();
    $items = '';
    if ($announcements) {
      foreach ($announcements as $announcement) {
        $items .=
          '<li>' .
            '[ ' . htmlspecialchars(time_ago($announcement->getTs())) . ' ]' .
            '<span class="announcement-highlight">' .
              htmlspecialchars($announcement->getAnnouncement()) .
            '</span>' .
          '</li>';
      }
    }

    return
      '<div>' .
        '<header class="module-header">' .
          '<h6>' . htmlspecialchars(tr('Announcements')) . '</h6>' .
        '</header>' .
        '<div class="module-content">' .
          '<div class="fb-section-border">' .
            '<div class="module-top"></div>' .
            '<div class="module-scrollable">' .
              '<ul class="activity-stream announcements-list">' . $items . '</ul>' .
            '</div>' .
          '</div>' .
        '</div>' .
      '</div>';
  }
}

$announcements_generated = new AnnouncementsModuleController();
$announcements_generated->sendRender();
