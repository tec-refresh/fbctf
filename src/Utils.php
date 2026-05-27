<?php declare(strict_types=1);

/**
 * Hack stdlib compatibility: idx() returns the value at $idx in $arr,
 * or $default if the key is missing or $arr is null.
 */
function idx(?array $arr, string|int $idx, mixed $default = null): mixed {
  if ($arr === null) {
    return $default;
  }
  return array_key_exists($idx, $arr) ? $arr[$idx] : $default;
}

function must_have_idx(?array $arr, string|int $idx): mixed {
  if ($arr === null) {
    throw new \RuntimeException('Container is null');
  }
  $result = idx($arr, $idx);
  if ($result === null) {
    throw new \RuntimeException(sprintf('Index %s not found in container', (string) $idx));
  }
  return $result;
}

function must_have_string(?array $arr, string $idx): string {
  $result = must_have_idx($arr, $idx);
  if (!is_string($result)) {
    throw new \RuntimeException(sprintf('Expected %s to be a string', $idx));
  }
  return $result;
}

function must_have_int(?array $arr, string $idx): int {
  $result = must_have_idx($arr, $idx);
  if (!is_int($result)) {
    throw new \RuntimeException(sprintf('Expected %s to be an int', $idx));
  }
  return $result;
}

function must_have_bool(?array $arr, string $idx): bool {
  $result = must_have_idx($arr, $idx);
  if (!is_bool($result)) {
    throw new \RuntimeException(sprintf('Expected %s to be a bool', $idx));
  }
  return $result;
}

function firstx(iterable $t): mixed {
  foreach ($t as $v) {
    return $v;
  }
  throw new \RuntimeException('Expected non-empty collection');
}

function starts_with(string $haystack, string $needle): bool {
  return str_starts_with($haystack, $needle);
}

function ends_with(string $haystack, string $needle): bool {
  return str_ends_with($haystack, $needle);
}

function time_ago(string $ts): string {
  $ts_epoc = strtotime($ts);
  $elapsed = time() - $ts_epoc;

  if ($elapsed < 1) {
    return tr('just now');
  }

  $w = [
    24 * 60 * 60 => tr('d'),
    60 * 60 => tr('hr'),
    60 => tr('min'),
    1 => tr('sec'),
  ];
  $w_s = [
    tr('d') => tr('ds'),
    tr('hr') => tr('hrs'),
    tr('min') => tr('mins'),
    tr('sec') => tr('secs'),
  ];
  foreach ($w as $secs => $str) {
    $d = $elapsed / $secs;
    if ($d >= 1) {
      $r = round($d);
      return $r . ' ' . ($r > 1 ? $w_s[$str] : $str) . ' ' . tr('ago');
    }
  }
  return '';
}

class Utils {
  private function __construct() {}

  private function __clone(): void {}

  public static function getGET(): array {
    return $_GET;
  }

  public static function getPOST(): array {
    return $_POST;
  }

  public static function getSERVER(): array {
    return $_SERVER;
  }

  public static function getFILES(): array {
    return $_FILES;
  }

  public static function redirect(string $location): void {
    header('Location: ' . $location);
  }

  public static function request_response(
    string $result,
    string $msg,
    string $redirect,
  ): string {
    $response_data = [
      'result' => $result,
      'message' => $msg,
      'redirect' => $redirect,
    ];
    return json_encode($response_data);
  }

  public static function hint_response(string $msg, string $result): string {
    $response_data = ['hint' => $msg, 'result' => $result];
    return json_encode($response_data);
  }

  public static function ok_response(string $msg, string $redirect): string {
    return self::request_response('OK', $msg, $redirect);
  }

  public static function error_response(
    string $msg,
    string $redirect,
  ): string {
    return self::request_response('ERROR', $msg, $redirect);
  }
}
