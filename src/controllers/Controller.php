<?php declare(strict_types=1);

abstract class Controller {
  abstract protected function getTitle(): string;
  abstract protected function getFilters(): array;
  abstract protected function getPages(): array;

  abstract protected function renderBody(string $page): string;

  public function renderBranding(): string {
    $custom_branding = Configuration::get('custom_logo');
    $custom_byline = Configuration::get('custom_byline');
    $custom_logo_image = Configuration::get('custom_logo_image');

    if ($custom_branding->getValue() === '0') {
      $text = htmlspecialchars(tr((string)$custom_byline->getValue()));
      return '<div class="branding"><span class="branding-text">' . $text . '</span></div>';
    } else {
      $text = htmlspecialchars((string)$custom_byline->getValue());
      $logo = htmlspecialchars((string)$custom_logo_image->getValue());
      return '<div class="branding custom-branding">' .
        '<img src="' . $logo . '" alt="Logo" />' .
        '<span class="branding-text">' . $text . '</span></div>';
    }
  }

  public function render(): string {
    $page = $this->processRequest();
    $body = $this->renderBody($page);
    $config = Configuration::get('language');
    $language = $config->getValue();
    if (!preg_match('/^\w{2}$/', $language)) {
      $language = 'en';
    }
    $document_root = must_have_string(Utils::getSERVER(), 'DOCUMENT_ROOT');
    $language_style = '';
    if (file_exists($document_root . '/static/css/locals/' . $language . '/style.css')) {
      $language_style = '<link rel="stylesheet" href="static/css/locals/' . htmlspecialchars($language) . '/style.css" />';
    }
    $title = htmlspecialchars($this->getTitle());
    return '<!DOCTYPE html>' .
      '<html lang="' . htmlspecialchars($language) . '">' .
      '<head>' .
      '<meta http-equiv="Cache-control" content="no-cache" />' .
      '<meta http-equiv="Expires" content="-1" />' .
      '<meta charset="UTF-8" />' .
      '<meta name="viewport" content="width=device-width, initial-scale=1" />' .
      '<title>' . $title . '</title>' .
      '<link rel="icon" type="image/png" href="static/img/favicon.png" />' .
      '<link rel="stylesheet" href="static/css/fb-ctf.css" />' .
      $language_style .
      '</head>' .
      $body .
      '</html>';
  }

  private function processRequest(): string {
    $input_methods = ['POST' => INPUT_POST, 'GET' => INPUT_GET];
    $method = must_have_string(Utils::getSERVER(), 'REQUEST_METHOD');

    $filter = idx($this->getFilters(), $method);
    if ($filter === null) {
      // Method not supported
      return 'none';
    }

    $input_method = must_have_idx($input_methods, $method);
    $page = 'main';

    $parameters = filter_input_array($input_method, $filter);

    $page = idx($parameters, 'page', 'main');
    if (!in_array($page, $this->getPages())) {
      $page = 'main';
    }

    return $page;
  }
}
