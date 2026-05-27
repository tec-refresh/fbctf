<?php declare(strict_types=1);

abstract class DataController {

  abstract public function generateData(): void;

  public function sendData(): void {
    try {
      $this->generateData();
    } catch (RedirectException $e) {
      if (get_class($this) === "SessionController") {
        error_log(
          'RedirectException: ('.get_class($e).') '.$e->getTraceAsString(),
        );
        http_response_code($e->getStatusCode());
        Utils::redirect($e->getPath());
      } else {
        $this->jsonSend([]);
      }
    }
  }

  public function jsonSend(mixed $data): void {
    header('Content-Type: application/json');
    print json_encode($data);
  }

  public function downloadSend(string $name, mixed $data): void {
    header('Content-Type: application/octet-stream');
    header("Content-Transfer-Encoding: Binary");
    header('Content-disposition: attachment; filename="'.$name.'"');
    print $data;
  }
}
