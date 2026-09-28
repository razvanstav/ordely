<?php

declare(strict_types=1);

use Symfony\Component\Dotenv\Dotenv;

require dirname(__DIR__) . '/vendor/autoload.php';

date_default_timezone_set('UTC');

// The built-in HTTP server may omit OS variables from $_SERVER/$_ENV when
// variables_order excludes E. Explicit deployment values must still beat .env.
foreach (getenv() as $name => $value) {
    if (preg_match('/^(APP_|DB_|MYSQL_|ORDELY_|SHOPIFY_)/', $name)) {
        $_ENV[$name] = $value;
    }
}

$environmentFile = dirname(__DIR__) . '/.env';
if (is_file($environmentFile)) {
    (new Dotenv())->loadEnv($environmentFile);
}
