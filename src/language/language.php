<?php declare(strict_types=1);
require_once (__DIR__.'/../../vendor/autoload.php');

$lang = null;

function tr_start(): void {
  $config = Configuration::get('language');
  $language = $config->getValue();
  $document_root = must_have_string(Utils::getSERVER(), 'DOCUMENT_ROOT');
  if (preg_match('/^[^,;]+$/', $language) &&
      file_exists($document_root."/language/lang_".$language.".php")) {
    include ($document_root."/language/lang_".$language.".php");
  } else {
    include ($document_root."/language/lang_en.php");
    error_log(
      "\nWarning: Selected language ({$language}) has no translation file in the languages folder. English (languages/lang_en.php) is used instead.",
    );
  }
  global $lang;
  $lang = $translations;
}

function tr(string $word): string {
  global $lang;
  if ($lang !== null && array_key_exists($word, $lang)) {
    return $lang[$word];
  } else {
    error_log(
      "\nWarning: '{$word}' has no translation in the selected language. Using the English version instead.",
    );
    return $word;
  }
}
