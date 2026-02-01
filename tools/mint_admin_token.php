<?php

declare(strict_types=1);

$autoload = __DIR__ . '/../vendor/autoload.php';
if (! file_exists($autoload)) {
    fwrite(STDERR, "Missing vendor/autoload.php (run composer install)" . PHP_EOL);
    exit(1);
}
require $autoload;

$app = require __DIR__ . '/../bootstrap/app.php';

/** @var \Illuminate\Contracts\Console\Kernel $console */
$console = $app->make(\Illuminate\Contracts\Console\Kernel::class);
$console->bootstrap();

$label = (string) ($_SERVER['argv'][1] ?? 'frontend-test');

$user = \App\Models\User::where('role', 'admin')->orderBy('id')->first();
if (! $user) {
    fwrite(STDERR, "No admin user found" . PHP_EOL);
    exit(2);
}

echo $user->createToken($label)->plainTextToken;
