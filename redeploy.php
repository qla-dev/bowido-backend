<?php

declare(strict_types=1);

set_time_limit(0);
ini_set('memory_limit', '512M');

$baseDir = __DIR__;
chdir($baseDir);

$commands = [
    [
        'label' => 'Installing Composer dependencies',
        'command' => 'composer install --no-interaction --prefer-dist --no-progress',
    ],
    [
        'label' => 'Running database migrations',
        'command' => 'php artisan migrate --force',
    ],
];

foreach ($commands as $step) {
    echo "\n=== {$step['label']} ===\n";

    $exitCode = 0;
    $command = $step['command'];

    $descriptorSpec = [
        0 => ['pipe', 'r'],
        1 => ['pipe', 'w'],
        2 => ['pipe', 'w'],
    ];

    $process = proc_open($command, $descriptorSpec, $pipes, $baseDir);

    if (!is_resource($process)) {
        fwrite(STDERR, "Failed to start command: {$command}\n");
        exit(1);
    }

    fclose($pipes[0]);

    while ($line = fgets($pipes[1])) {
        fwrite(STDOUT, $line);
    }

    while ($line = fgets($pipes[2])) {
        fwrite(STDERR, $line);
    }

    fclose($pipes[1]);
    fclose($pipes[2]);

    $exitCode = proc_close($process);

    if ($exitCode !== 0) {
        fwrite(STDERR, "{$step['label']} failed with exit code {$exitCode}.\n");
        exit($exitCode);
    }
}

echo "\nRedeploy completed successfully.\n";
