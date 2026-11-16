param(
    [string]$DbHost = '127.0.0.1',
    [int]$DbPort = 3307,
    [string]$DbUser = 'klinikledger',
    [string]$DbPassword = $env:KLINIK_TEST_DB_PASSWORD
)

$ErrorActionPreference = 'Stop'
$project = Split-Path -Parent $PSScriptRoot
Set-Location $project

if (-not (Test-Path 'vendor/autoload.php')) {
    throw 'Composer dependencies missing. Run composer install first.'
}

if (-not (Test-Path '.env')) {
    throw 'Missing .env. Copy .env.example and generate an APP_KEY first.'
}

if ([string]::IsNullOrWhiteSpace($DbPassword)) {
    throw 'Provide -DbPassword or set KLINIK_TEST_DB_PASSWORD.'
}

# Deliberately fixed: do not allow this script to target any other database.
$env:APP_ENV = 'testing'
$env:DB_CONNECTION = 'mysql'
$env:DB_HOST = $DbHost
$env:DB_PORT = [string]$DbPort
$env:DB_DATABASE = 'klinikledger_concurrency_test'
$env:DB_USERNAME = $DbUser
$env:DB_PASSWORD = $DbPassword
$env:DB_URL = ''
$env:CACHE_STORE = 'array'
$env:SESSION_DRIVER = 'array'
$env:QUEUE_CONNECTION = 'sync'
$env:MAIL_MAILER = 'array'
$env:KLINIK_CONCURRENCY_TEST = '1'

Write-Host 'Checking isolated MySQL database: klinikledger_concurrency_test' -ForegroundColor Cyan
php artisan config:clear
if ($LASTEXITCODE -ne 0) { throw 'Laravel config:clear failed.' }

# A missing or incorrectly permissioned test DB fails here rather than touching the application DB.
php artisan migrate --force
if ($LASTEXITCODE -ne 0) { throw 'Isolated MySQL migrations failed.' }

php vendor/bin/phpunit -c phpunit.mysql.xml
if ($LASTEXITCODE -ne 0) { throw 'MySQL concurrency test failed.' }

Write-Host 'MySQL concurrency test passed.' -ForegroundColor Green
