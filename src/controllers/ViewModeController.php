<?php declare(strict_types=1);

class ViewModeController extends Controller {
  protected function getTitle(): string {
    $custom_org = Configuration::get('custom_org');
    return tr($custom_org->getValue()).' | '.tr('View mode');
  }
  protected function getFilters(): array {
    return [
      'GET' => [
        'page' => [
          'filter' => FILTER_VALIDATE_REGEXP,
          'options' => ['regexp' => '/^[\w\-]+$/'],
        ],
      ],
    ];
  }
  protected function getPages(): array {
    return ['main'];
  }

  public function renderMainContent(): string {
    $branding_gen = $this->renderBranding();
    return
      '<div id="fb-gameboard" class="fb-gameboard gameboard--viewmode">' . '<div class="gameboard-header">' . '<nav class="fb-navigation fb-gameboard-nav">' . '<div class="branding">' . '<a href="/">' . '<div class="branding-rules">' . $branding_gen . '</div>' . '</a>' . '</div>' . '</nav>' . '</div>' . '<div class="fb-map">' . '</div>' . '<div class="fb-module-container container--row">' . '<aside data-name="' . htmlspecialchars(tr('Leaderboard')) . '" class="module--outer-left active" data-module="leaderboard-viewmode">' . '</aside>' . '<aside data-name="' . htmlspecialchars(tr('Activity')) . '" class="module--inner-right activity-viewmode active" data-module="activity-viewmode">' . '</aside>' . '<aside data-name="' . htmlspecialchars(tr('Game Clock')) . '" class="module--outer-right active" data-module="game-clock">' . '</aside>' . '</div>' . '</div>';
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
      '<body data-section="viewer-mode">' . '<div class="fb-sprite" id="fb-svg-sprite">' . '</div>' . '<div id="fb-main-content" class="fb-page">' . $rendered_page . '</div>' . '<script type="text/javascript" src="static/dist/js/app.js">' . '</script>' . '</body>';
  }
}
