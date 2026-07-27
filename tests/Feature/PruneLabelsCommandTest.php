<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Redis;
use Predis\Client;
use Prometheus\CollectorRegistry;
use Prometheus\Storage\Predis as PredisStorage;

/**
 * Exercises the command against a real Redis, with metrics written by promphp's
 * own adapter — hand-built keys would only prove the test agrees with itself.
 *
 * A client prefix is configured on purpose: the Lua scripts store already
 * prefixed keys as set members, so pruning has to strip that prefix before
 * reusing the key through the same client.
 */
const TEST_REDIS_HOST = '127.0.0.1';

const TEST_REDIS_PORT = 6399;

function redisReachable(): bool
{
    $socket = @fsockopen(TEST_REDIS_HOST, TEST_REDIS_PORT, $errno, $errstr, 1);

    if ($socket === false) {
        return false;
    }

    fclose($socket);

    return true;
}

function storage(): PredisStorage
{
    PredisStorage::setPrefix('PROMETHEUS_');

    /** @var Client $client */
    $client = Redis::connection('default')->client();

    return PredisStorage::fromExistingConnection($client);
}

/**
 * Seed one counter and one histogram, each with a junk series and a good one.
 */
function seedMetrics(): CollectorRegistry
{
    $registry = new CollectorRegistry(storage(), false);

    $counter = $registry->getOrRegisterCounter('app', 'http_requests', 'reqs', ['route', 'method', 'status']);
    $counter->incBy(3, ['generated::Lxb1UUxX1M6bG8yL', 'GET', '200']);
    $counter->incBy(5, ['generated::OtherRandomName', 'GET', '404']);
    $counter->incBy(7, ['app.dashboard', 'GET', '200']);

    $histogram = $registry->getOrRegisterHistogram('app', 'duration_seconds', 'dur', ['route'], [0.1, 1.0]);
    $histogram->observe(0.05, ['generated::Lxb1UUxX1M6bG8yL']);
    $histogram->observe(0.05, ['app.dashboard']);

    return $registry;
}

/**
 * @return list<string>
 */
function routeLabelsInStorage(): array
{
    $routes = [];

    foreach (storage()->collect() as $family) {
        foreach ($family->getSamples() as $sample) {
            $values = $sample->getLabelValues();

            if ($values !== []) {
                $routes[] = (string) $values[0];
            }
        }
    }

    return array_values(array_unique($routes));
}

beforeEach(function () {
    if (! redisReachable()) {
        // Skipping is a convenience for laptops without Redis. In CI the
        // service is guaranteed, so a skip there means the suite quietly
        // stopped covering the command — fail loudly instead.
        if (getenv('CI') !== false) {
            throw new RuntimeException(
                'Redis must be reachable on '.TEST_REDIS_HOST.':'.TEST_REDIS_PORT.' in CI'
            );
        }

        $this->markTestSkipped('No Redis on '.TEST_REDIS_HOST.':'.TEST_REDIS_PORT);
    }

    config()->set('database.redis.client', 'predis');
    config()->set('database.redis.default', [
        'host' => TEST_REDIS_HOST,
        'port' => TEST_REDIS_PORT,
        'database' => 0,
    ]);
    config()->set('database.redis.options.prefix', 'testapp_database_');
    config()->set('prometheus.storage.driver', 'redis');
    config()->set('prometheus.storage.redis.prefix', 'PROMETHEUS_');

    Redis::connection('default')->flushdb();
});

afterEach(function () {
    if (redisReachable()) {
        Redis::connection('default')->flushdb();
    }
});

it('removes only the matching series and leaves the rest intact', function () {
    seedMetrics();

    expect(routeLabelsInStorage())->toContain('generated::Lxb1UUxX1M6bG8yL', 'app.dashboard');

    $this->artisan('prometheus:prune-labels', ['--match' => ['generated::*']])
        ->assertSuccessful();

    $routes = routeLabelsInStorage();

    expect($routes)->toContain('app.dashboard')
        ->and($routes)->not->toContain('generated::Lxb1UUxX1M6bG8yL')
        ->and($routes)->not->toContain('generated::OtherRandomName');
});

it('preserves the surviving series values', function () {
    seedMetrics();

    $this->artisan('prometheus:prune-labels', ['--match' => ['generated::*']])->assertSuccessful();

    $counter = collect(storage()->collect())->first(fn ($f) => $f->getName() === 'app_http_requests');
    $samples = collect($counter->getSamples())->filter(fn ($s) => $s->getLabelValues()[0] === 'app.dashboard');

    expect($samples)->toHaveCount(1)
        ->and($samples->first()->getValue())->toEqual(7);
});

it('keeps the metric itself collectable after pruning', function () {
    seedMetrics();

    $this->artisan('prometheus:prune-labels', ['--match' => ['generated::*']])->assertSuccessful();

    // __meta must survive, otherwise the whole metric disappears from /metrics.
    $names = collect(storage()->collect())->map(fn ($f) => $f->getName())->all();

    expect($names)->toContain('app_http_requests', 'app_duration_seconds');
});

it('prunes histogram buckets that carry the matching label', function () {
    seedMetrics();

    $this->artisan('prometheus:prune-labels', ['--match' => ['generated::*']])->assertSuccessful();

    $histogram = collect(storage()->collect())->first(fn ($f) => $f->getName() === 'app_duration_seconds');
    $routes = collect($histogram->getSamples())->map(fn ($s) => $s->getLabelValues()[0] ?? null)->unique()->filter();

    expect($routes)->toContain('app.dashboard')
        ->and($routes)->not->toContain('generated::Lxb1UUxX1M6bG8yL');
});

it('changes nothing on a dry run', function () {
    seedMetrics();

    $this->artisan('prometheus:prune-labels', ['--match' => ['generated::*'], '--dry-run' => true])
        ->assertSuccessful();

    expect(routeLabelsInStorage())->toContain('generated::Lxb1UUxX1M6bG8yL', 'app.dashboard');
});

it('leaves everything alone when nothing matches', function () {
    seedMetrics();

    $before = routeLabelsInStorage();

    $this->artisan('prometheus:prune-labels', ['--match' => ['nothing-like-this*']])->assertSuccessful();

    expect(routeLabelsInStorage())->toEqualCanonicalizing($before);
});

it('fails when no pattern is given', function () {
    $this->artisan('prometheus:prune-labels')->assertFailed();
});

it('refuses to run against a non-redis storage driver', function () {
    config()->set('prometheus.storage.driver', 'in_memory');

    $this->artisan('prometheus:prune-labels', ['--match' => ['generated::*']])->assertFailed();
});
