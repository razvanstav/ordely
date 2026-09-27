<?php

declare(strict_types=1);

$baseUrl = rtrim($argv[1] ?? 'http://127.0.0.1:8080', '/');
$checks = [
    ['GET', '/health', 200, '{"status":"ok"}'],
    ['GET', '/ready', 200, '{"status":"ready"}'],
    ['HEAD', '/health', 200, ''],
    ['POST', '/health', 405, '{"error":"method_not_allowed"}'],
    ['GET', '/.env', 404, '{"error":"not_found"}'],
    ['GET', '/../composer.json', 404, '{"error":"not_found"}'],
];
foreach ($checks as [$method, $path, $expectedStatus, $expectedBody]) {
    $context = stream_context_create(['http' => [
        'method' => $method,
        'timeout' => 5,
        'ignore_errors' => true,
        'follow_location' => 0,
    ]]);
    $http_response_header = [];
    $body = @file_get_contents($baseUrl . $path, false, $context);
    $headers = $http_response_header;
    preg_match('/\AHTTP\/\S+ (\d{3})/', $headers[0] ?? '', $statusMatch);
    $status = (int) ($statusMatch[1] ?? 0);
    if ($status !== $expectedStatus || $body !== $expectedBody) {
        fwrite(STDERR, sprintf('HTTP smoke failed: %s %s, expected %d, received %d.%s', $method, $path, $expectedStatus, $status, PHP_EOL));
        exit(1);
    }
}
fwrite(STDOUT, sprintf('HTTP smoke: %d checks passed.%s', count($checks), PHP_EOL));
