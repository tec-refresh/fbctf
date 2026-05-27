<?php declare(strict_types=1);

abstract class ModalController {
  public abstract function render(string $modal): string;
}
