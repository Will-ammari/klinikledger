# KlinikLedger: real MySQL/InnoDB concurrency verification

This is a **test-only extension** to the existing project. It does not change production routes, services, models, migrations or API contracts.

## What is tested

- Two real, independent PHP processes dispatch `POST /api/appointments` via Laravel's HTTP kernel, each with its own MySQL connection and test-only Sanctum token.
- The test temporarily holds an InnoDB row lock on the doctor until both requests have started. This makes the requests contend for the same serialization point.
- Expected outcomes: exactly one `201 Created`, exactly one `422` with a `starts_at` validation error, exactly one appointment row and one `AppointmentCreated` audit event.
- Failing to acquire a lock or losing transaction integrity is reported as a failed integration test.

This is **not** a load/throughput benchmark and cannot prove safe behavior for every possible deployment topology or scheduling feature. The original SQLite tests remain unchanged.

## Guardrails

The test and its worker refuse to run unless all of the following hold:

- `APP_ENV=testing`
- `DB_CONNECTION=mysql`
- `DB_DATABASE=klinikledger_concurrency_test` (exact name)
- `KLINIK_CONCURRENCY_TEST=1`

**Never point this test at production or a database containing real patients.** The script runs migrations and creates/deletes synthetic records. Use a temporary, isolated MySQL database and credentials restricted to that database.

## Windows + existing Docker Compose (local developer environment)

Your `docker-compose.yml` already exposes the MySQL service on `127.0.0.1:3307`. In a **local development-only** Compose environment, start MySQL:

```powershell
docker compose up -d mysql
```

Create a separate database and grant the local development MySQL user access. These example credentials match the **development-only** `docker-compose.yml`; change them if yours differ:

```powershell
docker compose exec mysql mysql -uroot -proot -e "CREATE DATABASE IF NOT EXISTS klinikledger_concurrency_test CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci; GRANT ALL PRIVILEGES ON klinikledger_concurrency_test.* TO 'klinikledger'@'%';"
```

Run the local helper from the project directory (it sets the test variables **only for its own PowerShell process**):

```powershell
powershell.exe -NoProfile -ExecutionPolicy Bypass -File .\scripts\run-concurrency-mysql.ps1 -DbHost 127.0.0.1 -DbPort 3307 -DbUser klinikledger -DbPassword 'secret'
```

It runs migrations on **only** `klinikledger_concurrency_test` and then runs the separate `phpunit.mysql.xml` suite. Do not alter the safety-check database name.

If running inside an application container, use the Docker network host `mysql` and port `3306`, not `127.0.0.1:3307`.

## GitHub Actions

The repository **already has** `.github/workflows/tests.yml` for SQLite tests, Pint and PHPStan. It is preserved. This patch adds `.github/workflows/mysql-concurrency.yml` as a separate MySQL 8.4 job for pushes to `main`/`master` and pull requests (also manual dispatch).

The job creates an ephemeral MySQL service, migrates only the temporary `klinikledger_concurrency_test` database, and executes:

```bash
php vendor/bin/phpunit -c phpunit.mysql.xml
```

CI credentials are disposable test-service credentials, not real system secrets. Enable branch protections/required checks after the job passes on your GitHub repository.

## Verification and delivery expectations

Run, in order:

```powershell
php artisan test
php vendor/bin/pint --test
php vendor/bin/phpstan analyse
```

Then run the **separate** MySQL test with the helper. Never treat a green SQLite suite as proof of row-level MySQL locking. During this patch build, the PHP files passed `php -l`; the MySQL job has not been executed in the packaging environment because Laravel dependencies and a MySQL server were unavailable. It must pass in your environment/CI before claiming concurrency safety.

If the MySQL test fails, preserve the complete error output and investigate; do not weaken its assertions just to make it green.
