<?php

declare(strict_types=1);

use Symfony\Component\Dotenv\Dotenv;

require dirname(__DIR__) . '/vendor/autoload.php';

date_default_timezone_set('UTC');

$environmentFile = dirname(__DIR__) . '/.env';
if (is_file($environmentFile)) {
    (new Dotenv())->loadEnv($environmentFile);
}
