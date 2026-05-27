<?php declare(strict_types=1);

abstract class ModuleController {

  abstract public function render(): string;

  public function sendRender(): void {
    try {
      echo $this->render();
    } catch (RedirectException $e) {
      echo '';
    }
  }
}
