<?php declare(strict_types=1);

abstract class AjaxController {
  abstract protected function getFilters(): array;
  abstract protected function getActions(): array;

  abstract protected function handleAction(
    string $action,
    array $params,
  ): string;

  public function handleRequest(): string {
    [$action, $params] = $this->processRequest();
    return $this->handleAction($action, $params);
  }

  private function processRequest(): array {
    $input_methods = ['POST' => INPUT_POST, 'GET' => INPUT_GET];
    $method = must_have_string(Utils::getSERVER(), 'REQUEST_METHOD');

    $filter = idx($this->getFilters(), $method);
    if ($filter === null) {
      // Method not supported
      return ['none', []];
    }

    $input_method = must_have_idx($input_methods, $method);
    $parameters = filter_input_array($input_method, $filter);

    $action = idx($parameters, 'action', 'main');
    if (!in_array($action, $this->getActions())) {
      $page = 'none';
    }

    return [$action, $parameters];
  }
}
