<?php declare(strict_types=1);

require_once ($_SERVER['DOCUMENT_ROOT'].'/../vendor/autoload.php');

class AttachmentDataController extends DataController {
  public function generateData(): void {

    SessionUtils::sessionStart();
    SessionUtils::enforceLogin();

    tr_start();

    $data = tr('File Does Not Exist');
    $filename = tr('error');

    $attachment_id = idx(Utils::getGET(), 'id', '');
    if (intval($attachment_id) !== 0) {
      $attachment_exists =
        Attachment::checkExists(intval($attachment_id));
      $active = Attachment::checkActive(intval($attachment_id));
      if ($attachment_exists === true && $active === true) {
        $attachment = Attachment::get(intval($attachment_id));
        $filename = $attachment->getFilename();

        // Remove all non alpahnum characters from filename - allow international chars, dash, underscore, and period
        $filename = preg_replace('/[^\p{L}\p{N}_\-.]+/u', '_', $filename);

        $data = file_get_contents(Attachment::attachmentsDir.$filename);
      }
    }

    $this->downloadSend($filename, $data);
  }
}

$attachmentData = new AttachmentDataController();
$attachmentData->sendData();
