<?php

// Composer can be run directly or through Laravel's community-kit installer.
// The latter runs its own interactive hook after dependency installation.
if (getenv('LARAVEL_INSTALLER_DEFER_HOOKS') === '1') {
    exit(0);
}

require __DIR__.'/../vendor/autoload.php';

use Symfony\Component\Process\Process;

$root = dirname(__DIR__);
$interactive = defined('STDIN') && stream_isatty(STDIN) && getenv('COMPOSER_NO_INTERACTION') !== '1';

if (! is_file($root.'/.env')) {
    copy($root.'/.env.example', $root.'/.env');
}

$commands = [];
if (! preg_match('/^APP_KEY=.+$/m', file_get_contents($root.'/.env'))) {
    $commands[] = ['key:generate'];
}

if (! is_file($root.'/.starter-kit-installed')) {
    $commands[] = ['starter-kit:install'];
}

if (! is_file($root.'/database/database.sqlite')) {
    touch($root.'/database/database.sqlite');
}

$commands[] = ['migrate', '--force'];

foreach ($commands as $arguments) {
    if (! $interactive) {
        $arguments[] = '--no-interaction';
    }
    $process = new Process([PHP_BINARY, 'artisan', ...$arguments], $root, null, null, null);
    if ($interactive && Process::isTtySupported()) {
        $process->setTty(true);
    }
    $process->run(fn ($type, $line) => fwrite($type === Process::ERR ? STDERR : STDOUT, $line));
    if (! $process->isSuccessful()) {
        exit($process->getExitCode() ?? 1);
    }
}
