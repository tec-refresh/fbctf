<?php declare(strict_types=1);

class Router {
  public static function route(): string {
    tr_start();
    $page = idx(Utils::getGET(), 'p');
    if (!is_string($page)) {
      $page = 'index';
    }
    $ajax = (Utils::getGET()['ajax'] ?? null) === 'true';
    $modal = Utils::getGET()['modal'] ?? null;

    if ($ajax) {
      return self::routeAjax($page);
    } else if ($modal !== null) {
      return self::routeModal($page, strval($modal));
    } else {
      Control::runAutoRunScript();
      return self::routeNormal($page);
    }
  }

  private static function routeModal(
    string $page,
    string $modal,
  ): string {
    SessionUtils::sessionStart();
    switch ($page) {
      case 'action':
        return (new ActionModalController())->render($modal);
      case 'tutorial':
        return (new TutorialModalController())->render($modal);
      case 'country':
        return (new CountryModalController())->render($modal);
      case 'scoreboard':
        return (new ScoreboardModalController())->render($modal);
      case 'team':
        return (new TeamModalController())->render($modal);
      case 'command-line':
        return (new CommandLineModalController())->render($modal);
      case 'choose-logo':
        return (new ChooseLogoModalController())->render($modal);
      default:
        throw new NotFoundRedirectException();
    }
  }

  private static function routeAjax(string $page): string {
    SessionUtils::sessionStart();
    switch ($page) {
      case 'index':
        return (new IndexAjaxController())->handleRequest();
      case 'admin':
        SessionUtils::enforceLogin();
        SessionUtils::enforceAdmin();
        return (new AdminAjaxController())->handleRequest();
      case 'game':
        SessionUtils::enforceLogin();
        return (new GameAjaxController())->handleRequest();
      default:
        throw new NotFoundRedirectException();
    }
  }

  private static function routeNormal(string $page): string {
    SessionUtils::sessionStart();
    switch ($page) {
      case 'admin':
        SessionUtils::enforceLogin();
        SessionUtils::enforceAdmin();
        return (new AdminController())->render();
      case 'index':
        return (new IndexController())->render();
      case 'game':
        SessionUtils::enforceLogin();
        return (new GameboardController())->render();
      case 'view':
        return (new ViewModeController())->render();
      case 'logout':
        // TODO: Make a confirmation modal?
        SessionUtils::sessionLogout();
        throw new RuntimeException('should not reach here');
      default:
        throw new NotFoundRedirectException();
    }
  }

  public static function getRequestedPage(): string {
    $page = idx(Utils::getGET(), 'page') ?: idx(Utils::getGET(), 'p');
    if (!is_string($page)) {
      $page = 'index';
    }

    return strval($page);
  }

  public static function isRequestAjax(): bool {
    return (Utils::getGET()['ajax'] ?? null) === 'true';
  }

  public static function isRequestModal(): bool {
    return (Utils::getGET()['modal'] ?? null) !== null;
  }

  // Check to see if the request is going through the router
  public static function isRequestRouter(): bool {
    return self::getRequestedPage() !== "index";
  }
}
