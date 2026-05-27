<?php declare(strict_types=1);

require_once ($_SERVER['DOCUMENT_ROOT'].'/../vendor/autoload.php');

SessionUtils::sessionStart();

class IntegrationLogin {
  public static function processLogin(): void {
    $type = idx(Utils::getGET(), 'type');

    if (!is_string($type)) {
      $type = "none";
    }

    switch ($type) {
      case "facebook":
        self::processFacebookLogin();
        break;
      case "google":
        self::processGoogleLogin();
        break;
        // FALLTHROUGH
      default:
        header('Location: /index.php?page=login');
        exit;
        break;
    }
  }

  public static function processFacebookLogin(): void {
    $enabled = Integration::facebookLoginEnabled();
    if ($enabled === true) {
      $url = Integration::facebookLogin();
      header('Location: '.filter_var($url, FILTER_SANITIZE_URL));
      exit;
    } else {
      header('Location: /index.php?page=login');
      exit;
    }
  }

  public static function processGoogleLogin(): void {
    $enabled = Integration::googleLoginEnabled();
    if ($enabled === true) {
      $url = Integration::googleLogin();
      header('Location: '.filter_var($url, FILTER_SANITIZE_URL));
      exit;
    } else {
      header('Location: /index.php?page=login');
      exit;
    }
  }
}

$integration_login = new IntegrationLogin();
$integration_login->processLogin();
