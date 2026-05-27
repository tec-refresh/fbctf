<?php declare(strict_types=1);

header('Error-Redirect: true');

$document_root = $_SERVER['DOCUMENT_ROOT'] ?? '';
$error_page = $document_root . '/static/html/error.html';

if (file_exists($error_page)) {
    include($error_page);
} else {
    echo '<h1>Error</h1><p>An error occurred.</p>';
}
