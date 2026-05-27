<?php declare(strict_types=1);

class GameboardController extends Controller {
  protected function getTitle(): string {
    $custom_org = Configuration::get('custom_org');
    return tr($custom_org->getValue()).' '.tr('CTF').' | '.tr('Gameboard');
  }
  protected function getFilters(): array {
    return [
      'GET' => [
        'page' => [
          'filter' => FILTER_VALIDATE_REGEXP,
          'options' => ['regexp' => '/^[\w-]+$/'],
        ],
      ],
    ];
  }
  protected function getPages(): array {
    return ['main', 'viewmode'];
  }

  public function renderMainContent(): string {
    if (SessionUtils::sessionAdmin()) {
      $admin_link = '<li>' . '<a href="index.php?p=admin">' . htmlspecialchars(tr('Admin')) . '</a>' . '</li>';
    } else {
      $admin_link = null;
    }
    $branding_gen = $this->renderBranding();
    return
      '<div id="fb-gameboard" class="fb-gameboard">' . '<div class="gameboard-header">' . '<nav class="fb-navigation fb-gameboard-nav">' . '<ul class="nav-left">' . '<li>' . '<a>' . htmlspecialchars(tr('Navigation')) . '</a>' . '<ul class="subnav">' . '<!-- <li><a href="/index.php?p=view">{tr(\'View Mode\')}</a></li> -->' . '<li>' . '<a href="#" class="fb-init-tutorial">' . htmlspecialchars(tr('Tutorial')) . '</a>' . '</li>' . '<li>' . '<a href="#" class="js-account-modal">' . htmlspecialchars(tr('Account')) . '</a>' . '</li>' . $admin_link . '<li>' . '<a href="/index.php?page=rules" target="_blank">' . htmlspecialchars(tr('Rules')) . '</a>' . '</li>' . '<li>' . '<a href="#" class="js-prompt-logout">' . htmlspecialchars(tr('Logout')) . '</a>' . '</li>' . '</ul>' . '</li>' . '</ul>' . '<div class="branding">' . '<a href="index.php?p=game">' . '<div class="branding-rules">' . $branding_gen . '</div>' . '</a>' . '</div>' . '<ul class="nav-right">' . '<li>' . '<a href="#" class="js-launch-modal" data-modal="scoreboard">' . htmlspecialchars(tr('Scoreboard')) . '</a>' . '</li>' . '</ul>' . '</nav>' . '<div class="radio-tabs fb-map-select">' . '<input type="radio" name="fb--map-select" id="fb--map-select--you" value="your-team" />' . '<label for="fb--map-select--you" class="click-effect">' . '<span class="your-name">' . '<svg class="icon icon--team-indicator your-team">' . '<use href="#icon--team-indicator" />' . '</svg>' . htmlspecialchars(tr('You')) . '</span>' . '</label>' . '<input type="radio" name="fb--map-select" id="fb--map-select--enemy" value="opponent-team" />' . '<label for="fb--map-select--enemy" class="click-effect">' . '<span class="opponent-name">' . '<svg class="icon icon--team-indicator opponent-team">' . '<use href="#icon--team-indicator" />' . '</svg>' . htmlspecialchars(tr('Others')) . '</span>' . '</label>' . '<input type="radio" name="fb--map-select" id="fb--map-select--all" value="all" />' . '<label for="fb--map-select--all" class="click-effect">' . '<span>' . htmlspecialchars(tr('All')) . '</span>' . '</label>' . '</div>' . '</div>' . '<div class="fb-map">' . '</div>' . '<div class="fb-listview">' . '</div>' . '<div class="fb-module-container container--column column-left">' . '<aside data-name="' . htmlspecialchars(tr('Leaderboard')) . '" data-module="leaderboard">' . '</aside>' . '<aside data-name="' . htmlspecialchars(tr('Announcements')) . '" data-module="announcements">' . '</aside>' . '</div>' . '<div class="fb-module-container container--column column-right">' . '<aside data-name="' . htmlspecialchars(tr('Teams')) . '" data-module="teams">' . '</aside>' . '<aside data-name="' . htmlspecialchars(tr('Filter')) . '" data-module="filter">' . '</aside>' . '</div>' . '<div class="fb-module-container container--row">' . '<aside data-name="' . htmlspecialchars(tr('Activity')) . '" class="module--outer-left" data-module="activity">' . '</aside>' . '<aside data-name="' . htmlspecialchars(tr('Game Clock')) . '" class="module--outer-right" data-module="game-clock">' . '</aside>' . '</div>' . '</div>';
  }

  public function renderPage(string $page): string {
    switch ($page) {
      case 'main':
        return $this->renderMainContent();
        break;
      default:
        return $this->renderMainContent();
        break;
    }
  }
  public function renderBody(string $page): string {
    $rendered_page = $this->renderPage($page);
    return
      '<body data-section="gameboard">' . '<input type="hidden" name="csrf_token" value="' . htmlspecialchars(SessionUtils::CSRFToken()) . '" />' . '<div class="fb-sprite" id="fb-svg-sprite">' . '</div>' . '<div id="fb-main-content" class="fb-page">' . $rendered_page . '</div>' . '<script type="text/javascript" src="static/dist/js/app.js">' . '</script>' . '</body>';
  }
}
