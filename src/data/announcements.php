<?php declare(strict_types=1);

require_once ($_SERVER['DOCUMENT_ROOT'].'/../vendor/autoload.php');

class AnnouncementsDataController extends DataController {
  public function generateData(): void {

    SessionUtils::sessionStart();
    SessionUtils::enforceLogin();

    $data = [];

    $all_announcements = Announcement::allAnnouncements();
    foreach ($all_announcements as $announcement) {
      array_push($data, $announcement->getAnnouncement());
    }

    $this->jsonSend($data);
  }
}

$announcementsData = new AnnouncementsDataController();
$announcementsData->sendData();
