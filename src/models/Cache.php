<?php declare(strict_types=1);

class Cache {

  private array $CACHE = [];

  public function __construct() {}

  public function setCache(string $key, mixed $value): void {
    $this->CACHE[$key] = $value;
  }

  public function getCache(string $key): mixed {
    if (array_key_exists($key, $this->CACHE)) {
      return $this->CACHE[$key];
    } else {
      return false;
    }
  }

  public function deleteCache(string $key): void {
    if (array_key_exists($key, $this->CACHE)) {
      unset($this->CACHE[$key]);
    }
  }

  public function flushCache(): void {
    $this->CACHE = [];
  }

}
