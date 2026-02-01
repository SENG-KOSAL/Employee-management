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

$employeeId = (int) ($_SERVER['argv'][1] ?? 48);

$employee = \App\Models\Employee::find($employeeId);
if (! $employee) {
    fwrite(STDERR, "Employee {$employeeId} not found" . PHP_EOL);
    exit(2);
}

$user = \App\Models\User::where('role', 'admin')->orderBy('id')->first();
if (! $user) {
    fwrite(STDERR, "No admin user found (needed to authenticate)" . PHP_EOL);
    exit(3);
}

$token = $user->createToken('photo-test')->plainTextToken;

$png = base64_decode(
    'iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAQAAAC1HAwCAAAAC0lEQVR42mP8/x8AAwMCAO1+1qkAAAAASUVORK5CYII=',
    true
);
if ($png === false) {
    fwrite(STDERR, "Failed to create PNG payload" . PHP_EOL);
    exit(4);
}

$tmpPath = tempnam(sys_get_temp_dir(), 'emp-photo-');
if ($tmpPath === false) {
    fwrite(STDERR, "Failed to create temp file" . PHP_EOL);
    exit(5);
}

file_put_contents($tmpPath, $png);

$upload = new \Illuminate\Http\UploadedFile(
    $tmpPath,
    'photo-test.png',
    'image/png',
    null,
    true
);

$request = \Illuminate\Http\Request::create(
    "/api/v1/employees/{$employeeId}/photo",
    'POST',
    [],
    [],
    ['photo' => $upload]
);

$request->headers->set('Accept', 'application/json');
$request->headers->set('Authorization', 'Bearer ' . $token);

/** @var \Illuminate\Contracts\Http\Kernel $kernel */
$kernel = $app->make(\Illuminate\Contracts\Http\Kernel::class);
$response = $kernel->handle($request);
$kernel->terminate($request, $response);

@unlink($tmpPath);

echo 'STATUS=' . $response->getStatusCode() . PHP_EOL;
echo $response->getContent() . PHP_EOL;
