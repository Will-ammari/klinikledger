<?php

/**
 * One isolated PHP process per HTTP request. For the MySQL concurrency test only.
 * Never use this worker with a production database or live credentials.
 */

use Illuminate\Contracts\Http\Kernel;
use Illuminate\Http\Request;

require dirname(__DIR__, 3).'/vendor/autoload.php';

$app = require dirname(__DIR__, 3).'/bootstrap/app.php';

/** @var Kernel $kernel */
$kernel = $app->make(Kernel::class);
$kernel->bootstrap();

$database = (string) config('database.connections.mysql.database');
if (
    ! app()->environment('testing')
    || config('database.default') !== 'mysql'
    || $database !== 'klinikledger_concurrency_test'
    || getenv('KLINIK_CONCURRENCY_TEST') !== '1'
) {
    fwrite(STDERR, "Refusing to use anything other than the isolated MySQL concurrency test database.\n");
    exit(2);
}

$directory = $argv[1] ?? '';
$workerId = $argv[2] ?? '';
$doctorId = $argv[3] ?? '';
$patientId = $argv[4] ?? '';
$token = getenv('KLINIK_TEST_BEARER_TOKEN');

if (
    ! is_dir($directory)
    || ! in_array($workerId, ['a', 'b'], true)
    || ! ctype_digit($doctorId)
    || ! ctype_digit($patientId)
    || ! is_string($token)
    || $token === ''
) {
    fwrite(STDERR, "Invalid test worker parameters.\n");
    exit(2);
}

file_put_contents($directory.'/ready-'.$workerId, '1');
$deadline = microtime(true) + 20;
while (! is_file($directory.'/go')) {
    if (microtime(true) > $deadline) {
        fwrite(STDERR, "Timed out waiting for test barrier.\n");
        exit(3);
    }

    usleep(10_000);
}

file_put_contents($directory.'/started-'.$workerId, '1');

$request = Request::create(
    '/api/appointments',
    'POST',
    [],
    [],
    [],
    [
        'HTTP_ACCEPT' => 'application/json',
        'CONTENT_TYPE' => 'application/json',
        'HTTP_AUTHORIZATION' => 'Bearer '.$token,
    ],
    json_encode([
        'doctor_id' => (int) $doctorId,
        'patient_id' => (int) $patientId,
        'starts_at' => '2030-06-03 09:00:00',
        'reason' => 'Isolated concurrency probe',
    ], JSON_THROW_ON_ERROR)
);

try {
    $response = $kernel->handle($request);
    $kernel->terminate($request, $response);
    $body = json_decode((string) $response->getContent(), true);

    echo json_encode([
        'status' => $response->getStatusCode(),
        'validation' => is_array($body) ? array_keys($body['errors'] ?? []) : [],
    ], JSON_THROW_ON_ERROR);
} catch (Throwable $exception) {
    // Never log token/connection details. Fail test with class name only.
    fwrite(STDERR, 'Worker exception: '.get_class($exception)."\n");
    exit(4);
}
