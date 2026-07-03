<?php

declare(strict_types=1);

set_time_limit(0);
ini_set('memory_limit', '512M');
ini_set('output_buffering', '0');
ini_set('zlib.output_compression', '0');

$baseDir = __DIR__;
chdir($baseDir);
putenv('COMPOSER_ALLOW_SUPERUSER=1');
putenv('COMPOSER_NO_INTERACTION=1');

if (PHP_SAPI !== 'cli') {
    header('Content-Type: text/plain; charset=utf-8');
    header('Cache-Control: no-cache');
    header('X-Accel-Buffering: no');
}

while (ob_get_level() > 0) {
    ob_end_flush();
}
ob_implicit_flush(true);

$startedAt = time();

$write = static function (string $message): void {
    echo $message;
    flush();
};

$shellArg = static fn (string $value): string => escapeshellarg($value);

$commandExists = static function (string $command): bool {
    $checkCommand = PHP_OS_FAMILY === 'Windows'
        ? 'where ' . escapeshellarg($command)
        : 'command -v ' . escapeshellarg($command) . ' >/dev/null 2>&1';

    exec($checkCommand, $output, $exitCode);

    return $exitCode === 0;
};

$findExecutable = static function (array $commands): ?string {
    foreach ($commands as $command) {
        if (str_contains($command, DIRECTORY_SEPARATOR) && is_file($command) && is_executable($command)) {
            return $command;
        }

        $checkCommand = PHP_OS_FAMILY === 'Windows'
            ? 'where ' . escapeshellarg($command)
            : 'command -v ' . escapeshellarg($command);

        $output = [];
        exec($checkCommand, $output, $exitCode);

        if ($exitCode === 0 && isset($output[0]) && $output[0] !== '') {
            return trim($output[0]);
        }
    }

    return null;
};

$findCliPhp = static function (callable $findExecutable, callable $write): string {
    $candidates = [
        'php',
        '/usr/local/bin/php',
        '/usr/bin/php',
        '/opt/cpanel/ea-php82/root/usr/bin/php',
        '/opt/cpanel/ea-php83/root/usr/bin/php',
        '/opt/cpanel/ea-php84/root/usr/bin/php',
    ];

    if (PHP_SAPI === 'cli' && PHP_BINARY) {
        array_unshift($candidates, PHP_BINARY);
    }

    $php = $findExecutable($candidates);

    if ($php === null) {
        $write("Could not find CLI PHP. Ask hosting support for the CLI PHP path and add it to redeploy.php.\n");
        exit(127);
    }

    return $php;
};

$downloadComposer = static function (string $targetFile, callable $write): bool {
    $write("Composer was not found on PATH. Downloading local composer.phar...\n");

    $composerUrl = 'https://getcomposer.org/download/latest-stable/composer.phar';
    $composerPhar = @file_get_contents($composerUrl);

    if ($composerPhar === false) {
        $write("Could not download Composer from {$composerUrl}.\n");
        $write("Install Composer globally on the server, or upload composer.phar to this folder:\n");
        $write(dirname($targetFile) . "\n");
        return false;
    }

    if (@file_put_contents($targetFile, $composerPhar) === false) {
        $write("Downloaded Composer, but could not write it to {$targetFile}.\n");
        return false;
    }

    $write("Saved local Composer to {$targetFile}.\n");
    return true;
};

$phpCommand = $shellArg($findCliPhp($findExecutable, $write));
$composerPhar = $baseDir . DIRECTORY_SEPARATOR . 'composer.phar';
$composerCommand = null;

if (is_file($composerPhar)) {
    $composerCommand = $phpCommand . ' ' . $shellArg($composerPhar);
} elseif ($commandExists('composer')) {
    $composerCommand = 'composer';
} elseif ($downloadComposer($composerPhar, $write)) {
    $composerCommand = $phpCommand . ' ' . $shellArg($composerPhar);
}

if ($composerCommand === null) {
    exit(127);
}

$runCommand = static function (string $command, string $cwd, callable $write): int {
    $descriptorSpec = [
        0 => ['pipe', 'r'],
        1 => ['pipe', 'w'],
        2 => ['pipe', 'w'],
    ];

    $process = proc_open($command, $descriptorSpec, $pipes, $cwd);

    if (!is_resource($process)) {
        $write("Failed to start command: {$command}\n");
        return 1;
    }

    fclose($pipes[0]);
    stream_set_blocking($pipes[1], false);
    stream_set_blocking($pipes[2], false);

    $lastOutputAt = time();
    $exitCode = null;

    do {
        foreach ([1, 2] as $pipeNumber) {
            while (($line = fgets($pipes[$pipeNumber])) !== false) {
                $lastOutputAt = time();
                $write($line);
            }
        }

        $status = proc_get_status($process);

        if (!$status['running']) {
            $exitCode = $status['exitcode'];
            break;
        }

        if (time() - $lastOutputAt >= 15) {
            $lastOutputAt = time();
            $write('... still running at ' . date('H:i:s') . "\n");
        }

        usleep(100000);
    } while (true);

    foreach ([1, 2] as $pipeNumber) {
        while (($line = fgets($pipes[$pipeNumber])) !== false) {
            $write($line);
        }
    }

    fclose($pipes[1]);
    fclose($pipes[2]);

    $closeCode = proc_close($process);

    return is_int($exitCode) && $exitCode >= 0 ? $exitCode : $closeCode;
};

$commands = [
    [
        'label' => 'Installing Composer dependencies',
        'command' => $composerCommand . ' install --no-interaction --prefer-dist --no-progress --optimize-autoloader',
    ],
    [
        'label' => 'Running database migrations',
        'command' => $phpCommand . ' artisan migrate --force',
    ],
];

foreach ($commands as $step) {
    $write("\n=== {$step['label']} ===\n");
    $write("Command: {$step['command']}\n");

    $exitCode = $runCommand($step['command'], $baseDir, $write);

    if ($exitCode !== 0) {
        $write("{$step['label']} failed with exit code {$exitCode}.\n");
        exit($exitCode);
    }
}

$elapsed = time() - $startedAt;
$write("\nRedeploy completed successfully in {$elapsed}s.\n");
