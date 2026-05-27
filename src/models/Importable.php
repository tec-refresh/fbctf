<?php declare(strict_types=1);

interface Importable {
  public static function importAll(array $elements): bool;
}
