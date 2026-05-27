<?php declare(strict_types=1);

class BinaryImporterController {
  public static function getFilename(string $file_name): mixed {
    $file = Utils::getFILES();
    if (isset($file[$file_name])) {
      $input_filename = $file[$file_name]['tmp_name'];
      return $input_filename;
    }
    return false;
  }
}
