<?php declare(strict_types=1);

class RedirectException extends Exception {
  private string $path;
  private int $statusCode;

  public function __construct(string $path, int $statusCode) {
    $this->path = $path;
    $this->statusCode = $statusCode;
    parent::__construct();
  }

  public function getPath(): string {
    return $this->path;
  }

  public function getStatusCode(): int {
    return $this->statusCode;
  }
}

class AdminRedirectException extends RedirectException {
  public function __construct() {
    parent::__construct('/index.php?p=admin', 302);
  }
}

class IndexRedirectException extends RedirectException {
  public function __construct() {
    parent::__construct('/index.php', 302);
  }
}

class RegistrationRedirectException extends RedirectException {
  public function __construct() {
    parent::__construct('/index.php?page=registration', 302);
  }
}

class LoginRedirectException extends RedirectException {
  public function __construct() {
    parent::__construct('/index.php?page=login', 302);
  }
}

class InternalErrorRedirectException extends RedirectException {
  public function __construct() {
    parent::__construct('/index.php?page=error', 500);
  }
}

class NotFoundRedirectException extends RedirectException {
  public function __construct() {
    parent::__construct('/error.php', 404);
  }
}

class GameRedirectException extends RedirectException {
  public function __construct() {
    parent::__construct('/index.php', 302);
  }
}
