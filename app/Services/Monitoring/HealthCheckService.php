<?php

namespace App\Services\Monitoring;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Redis;
use Throwable;

class HealthCheckService
{
    /**
     * @return array{status: string, checks: array<string, array<string, mixed>>}
     */
    public function run(): array
    {
        $checks = [
            'database' => $this->measure(fn () => $this->checkDatabase()),
            'cache' => $this->measure(fn () => $this->checkCache()),
            'queue' => $this->measure(fn () => $this->checkQueueConfiguration()),
            'redis' => $this->measure(fn () => $this->checkRedisIfUsed()),
        ];

        $overallStatus = collect($checks)
            ->contains(fn (array $check): bool => $check['status'] === 'unhealthy')
                ? 'unhealthy'
                : 'healthy';

        return [
            'status' => $overallStatus,
            'checks' => $checks,
        ];
    }

    /**
     * @param  callable(): array<string, mixed>  $callback
     * @return array<string, mixed>
     */
    private function measure(callable $callback): array
    {
        $startedAt = microtime(true);

        try {
            $result = $callback();
        } catch (Throwable $exception) {
            $result = [
                'status' => 'unhealthy',
                'message' => $exception->getMessage(),
            ];
        }

        $result['latency_ms'] = round((microtime(true) - $startedAt) * 1000, 2);

        return $result;
    }

    /** @return array<string, mixed> */
    private function checkDatabase(): array
    {
        DB::connection()->getPdo();
        DB::select('select 1');

        return [
            'status' => 'healthy',
            'connection' => config('database.default'),
        ];
    }

    /** @return array<string, mixed> */
    private function checkCache(): array
    {
        $key = 'health-check:'.uniqid('', true);

        Cache::put($key, 'ok', 10);
        $value = Cache::get($key);
        Cache::forget($key);

        if ($value !== 'ok') {
            return [
                'status' => 'unhealthy',
                'message' => 'Cache write/read check failed.',
            ];
        }

        return [
            'status' => 'healthy',
            'store' => config('cache.default'),
        ];
    }

    /** @return array<string, mixed> */
    private function checkQueueConfiguration(): array
    {
        $connection = (string) config('queue.default');
        $driver = config("queue.connections.{$connection}.driver");

        if ($connection === '' || $driver === null) {
            return [
                'status' => 'unhealthy',
                'message' => 'Queue connection is not configured.',
            ];
        }

        return [
            'status' => 'healthy',
            'connection' => $connection,
            'driver' => $driver,
        ];
    }

    /** @return array<string, mixed> */
    private function checkRedisIfUsed(): array
    {
        $usesRedis = config('cache.default') === 'redis' || config('queue.default') === 'redis';

        if (! $usesRedis) {
            return [
                'status' => 'skipped',
                'message' => 'Redis is not used by the active cache or queue configuration.',
            ];
        }

        Redis::connection()->ping();

        return [
            'status' => 'healthy',
            'connection' => config('database.redis.client'),
        ];
    }
}
