<?php

namespace Tests\Integration;

use App\Enums\AppointmentStatus;
use App\Enums\AuditAction;
use App\Models\Appointment;
use App\Models\AuditLog;
use App\Models\Clinic;
use App\Models\Doctor;
use App\Models\DoctorWorkingHour;
use App\Models\Patient;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Symfony\Component\Process\Process;
use Tests\TestCase;

/**
 * Real InnoDB lock contention, using two separate Laravel processes and connections.
 * Run only with phpunit.mysql.xml and a dedicated test database.
 * No RefreshDatabase: its wrapping transaction is invisible to worker processes.
 */
class AppointmentConcurrencyMysqlTest extends TestCase
{
    public function test_two_simultaneous_http_bookings_create_exactly_one_appointment(): void
    {
        $this->assertIsolatedMysqlDatabase();

        $clinic = Clinic::factory()->create();
        $owner = User::factory()->owner()->for($clinic)->create();
        $doctor = Doctor::factory()->forClinic($clinic)->create([
            'appointment_duration_minutes' => 30,
            'is_active' => true,
        ]);
        DoctorWorkingHour::factory()->forDoctor($doctor)->mondayMorning()->create();
        $patient = Patient::factory()->forClinic($clinic)->create();
        $token = $owner->createToken('mysql-concurrency-probe')->plainTextToken;

        $directory = sys_get_temp_dir().DIRECTORY_SEPARATOR.'klinik-concurrency-'.bin2hex(random_bytes(8));
        if (! mkdir($directory, 0700) && ! is_dir($directory)) {
            $this->fail('Could not create the temporary worker barrier.');
        }

        /** @var array<string, Process> $processes */
        $processes = [];
        $lockHeld = false;

        try {
            // A stable doctor row serves as the serialization point. Hold it while
            // both HTTP requests are dispatched, ensuring real overlapping attempts.
            DB::beginTransaction();
            $lockHeld = true;
            Doctor::query()->whereKey($doctor->id)->lockForUpdate()->firstOrFail();

            foreach (['a', 'b'] as $workerId) {
                $process = new Process([
                    PHP_BINARY,
                    base_path('tests/Support/Concurrency/book_appointment_worker.php'),
                    $directory,
                    $workerId,
                    (string) $doctor->id,
                    (string) $patient->id,
                ], base_path(), $this->workerEnvironment($token));
                $process->setTimeout(35);
                $process->start();
                $processes[$workerId] = $process;
            }

            $this->awaitMarkers($directory, 'ready', $processes);
            file_put_contents($directory.'/go', '1');
            $this->awaitMarkers($directory, 'started', $processes);

            // Workers cannot complete booking while the doctor row is locked.
            usleep(250_000);
            foreach ($processes as $workerId => $process) {
                $this->assertTrue($process->isRunning(), "Worker {$workerId} finished while doctor lock was held: ".$process->getOutput().' '.$process->getErrorOutput());
            }

            DB::commit();
            $lockHeld = false;

            $responses = [];
            foreach ($processes as $workerId => $process) {
                $process->wait();
                $this->assertSame(0, $process->getExitCode(), "Worker {$workerId} failed: ".$process->getErrorOutput());
                $response = json_decode($process->getOutput(), true, 512, JSON_THROW_ON_ERROR);
                $responses[] = $response['status'];
                if ($response['status'] === 422) {
                    $this->assertContains('starts_at', $response['validation']);
                }
            }

            sort($responses);
            $this->assertSame([201, 422], $responses);
            $this->assertSame(1, Appointment::query()
                ->where('clinic_id', $clinic->id)
                ->where('doctor_id', $doctor->id)
                ->where('starts_at', '2030-06-03 09:00:00')
                ->where('status', AppointmentStatus::Scheduled->value)
                ->count());
            $this->assertSame(1, AuditLog::query()
                ->where('clinic_id', $clinic->id)
                ->where('action', AuditAction::AppointmentCreated->value)
                ->count());
        } finally {
            if ($lockHeld) {
                DB::rollBack();
            }

            foreach ($processes as $process) {
                if ($process->isRunning()) {
                    $process->stop(1);
                }
            }

            foreach (glob($directory.'/*') ?: [] as $file) {
                @unlink($file);
            }
            @rmdir($directory);

            // The dedicated database is disposable, but do not leave fixture data.
            $owner->tokens()->delete();
            $clinic->delete();
        }
    }

    private function assertIsolatedMysqlDatabase(): void
    {
        $this->assertSame('testing', app()->environment(), 'APP_ENV must be testing.');
        $this->assertSame('mysql', DB::connection()->getDriverName());
        $this->assertSame('klinikledger_concurrency_test', DB::connection()->getDatabaseName());
        $this->assertSame('1', getenv('KLINIK_CONCURRENCY_TEST'));
        $this->assertSame(0, DB::transactionLevel(), 'Test must not run inside a PHPUnit database transaction.');
    }

    /** @return array<string, string> */
    private function workerEnvironment(string $token): array
    {
        $database = config('database.connections.mysql');

        return [
            'APP_ENV' => 'testing',
            'APP_DEBUG' => 'false',
            'APP_KEY' => (string) config('app.key'),
            'DB_CONNECTION' => 'mysql',
            'DB_URL' => '',
            'DB_HOST' => (string) $database['host'],
            'DB_PORT' => (string) $database['port'],
            'DB_DATABASE' => (string) $database['database'],
            'DB_USERNAME' => (string) $database['username'],
            'DB_PASSWORD' => (string) $database['password'],
            'CACHE_STORE' => 'array',
            'SESSION_DRIVER' => 'array',
            'QUEUE_CONNECTION' => 'sync',
            'MAIL_MAILER' => 'array',
            'KLINIK_CONCURRENCY_TEST' => '1',
            'KLINIK_TEST_BEARER_TOKEN' => $token,
        ];
    }

    /** @param array<string, Process> $processes */
    private function awaitMarkers(string $directory, string $stage, array $processes): void
    {
        $deadline = microtime(true) + 20;

        while (microtime(true) < $deadline) {
            $ready = true;
            foreach ($processes as $workerId => $process) {
                if (! is_file($directory.'/'.$stage.'-'.$workerId)) {
                    $ready = false;
                }
                if (! $process->isRunning()) {
                    $this->fail("Worker {$workerId} exited before {$stage}: ".$process->getOutput().' '.$process->getErrorOutput());
                }
            }
            if ($ready) {
                return;
            }

            usleep(10_000);
        }

        $this->fail("Timed out waiting for worker stage: {$stage}.");
    }
}
