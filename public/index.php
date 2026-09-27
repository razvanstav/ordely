<?php

declare(strict_types=1);

use Ordely\Infrastructure\Database\ConnectionFactory;
use Ordely\Infrastructure\Database\DatabaseConfig;
use Ordely\Infrastructure\Http\Application;
use Symfony\Component\HttpFoundation\Request;

try {
    require dirname(__DIR__) . '/config/bootstrap.php';

    $application = new Application(static fn (): PDO => (new ConnectionFactory(DatabaseConfig::fromEnvironment()))->connect());
    $application->handle(Request::createFromGlobals())->send();
} catch (Throwable $exception) {
    error_log('Ordely bootstrap failure: ' . $exception::class);
    http_response_code(500);
    header('Content-Type: application/json');
    header('Cache-Control: no-store');
    echo '{"error":"internal_error"}';
}
