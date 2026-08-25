<?php

declare(strict_types=1);

/**
 * Upstash is a serverless Redis with two constraints that Laravel's stock
 * config violates. Neither fails at boot — they fail on the first real command,
 * which is a slow and confusing way to find out.
 */
test('every redis connection targets database 0', function (): void {
    // Upstash answers anything else with
    // "ERR Only 0th database is supported! Selected DB: 1",
    // and Laravel ships REDIS_CACHE_DB defaulting to 1.
    foreach (['default', 'cache'] as $connection) {
        expect((string) config("database.redis.{$connection}.database"))
            ->toBe('0', "redis.{$connection} must use database 0 on Upstash");
    }
});

test('no scheme option is set, because the host carries the prefix', function (): void {
    // Laravel's PhpRedisConnector throws when a `scheme` option and a scheme on
    // the host disagree, and Upstash hands out hosts already prefixed `tls://`.
    foreach (['default', 'cache'] as $connection) {
        expect(config("database.redis.{$connection}.scheme"))->toBeNull();
    }
});

test('the redis diagnostic command is registered', function (): void {
    expect(array_key_exists('redis:ping', Artisan::all()))->toBeTrue();
});
