<?php declare(strict_types=1);

require_once('../vendor/autoload.php');

function init(): void {
    try {
        $response = Router::route();
        echo $response;
    } catch (RedirectException $e) {
        error_log(
            'RedirectException: (' . get_class($e) . ') ' . $e->getTraceAsString(),
        );
        http_response_code($e->getStatusCode());
        Utils::redirect($e->getPath());
    }
}

init();
