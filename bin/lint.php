<?php

declare(strict_types=1);

$root = dirname(__DIR__);
$failures = 0;
$count = 0;
foreach (['bin', 'config', 'public', 'src', 'tests'] as $directory) {
    $files = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($root . '/' . $directory));
    foreach ($files as $file) {
        if (!$file instanceof SplFileInfo || !$file->isFile() || $file->getExtension() !== 'php') {
            continue;
        }
        $process = proc_open([PHP_BINARY, '-l', $file->getPathname()], [1 => ['pipe', 'w'], 2 => ['pipe', 'w']], $pipes);
        if (!is_resource($process)) {
            fwrite(STDERR, 'Could not launch PHP lint.' . PHP_EOL);
            exit(1);
        }
        $output = stream_get_contents($pipes[1]);
        $error = stream_get_contents($pipes[2]);
        fclose($pipes[1]);
        fclose($pipes[2]);
        if (proc_close($process) !== 0) {
            fwrite(STDERR, $output . $error);
            ++$failures;
        }
        ++$count;
    }
}
fwrite(STDOUT, sprintf('PHP lint: %d files, %d failures.%s', $count, $failures, PHP_EOL));
exit($failures === 0 ? 0 : 1);
