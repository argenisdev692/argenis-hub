<?php

declare(strict_types=1);

use Modules\Auth\Domain\ValueObjects\DeviceFingerprint;

test('the same browser on the same network yields the same fingerprint', function (): void {
    $first = DeviceFingerprint::fromRequestSignals('Mozilla/5.0 Firefox/140.0', '203.0.113.10');
    $second = DeviceFingerprint::fromRequestSignals('Mozilla/5.0 Firefox/140.0', '203.0.113.10');

    expect($first->equals($second))->toBeTrue();
});

test('a roaming address inside the same network is still the same device', function (): void {
    $home = DeviceFingerprint::fromRequestSignals('Mozilla/5.0 Firefox/140.0', '203.0.113.10');
    $roamed = DeviceFingerprint::fromRequestSignals('Mozilla/5.0 Firefox/140.0', '203.0.113.99');

    expect($home->equals($roamed))->toBeTrue();
});

test('a different browser is a different device', function (): void {
    $firefox = DeviceFingerprint::fromRequestSignals('Mozilla/5.0 Firefox/140.0', '203.0.113.10');
    $chrome = DeviceFingerprint::fromRequestSignals('Mozilla/5.0 Chrome/140.0', '203.0.113.10');

    expect($firefox->equals($chrome))->toBeFalse();
});

test('a different network is a different device', function (): void {
    $office = DeviceFingerprint::fromRequestSignals('Mozilla/5.0 Firefox/140.0', '203.0.113.10');
    $cafe = DeviceFingerprint::fromRequestSignals('Mozilla/5.0 Firefox/140.0', '198.51.100.10');

    expect($office->equals($cafe))->toBeFalse();
});

test('missing request signals still produce a valid fingerprint', function (): void {
    $fingerprint = DeviceFingerprint::fromRequestSignals(null, null);

    expect($fingerprint->hash)->toMatch('/^[0-9a-f]{64}$/');
});

test('a malformed address does not leak into the digest as-is', function (): void {
    $garbage = DeviceFingerprint::fromRequestSignals('Mozilla/5.0', 'not-an-ip');
    $missing = DeviceFingerprint::fromRequestSignals('Mozilla/5.0', null);

    expect($garbage->equals($missing))->toBeTrue();
});

test('the raw user agent never appears in the fingerprint', function (): void {
    $fingerprint = DeviceFingerprint::fromRequestSignals('Mozilla/5.0 Firefox/140.0', '203.0.113.10');

    expect($fingerprint->hash)->not->toContain('Firefox');
});

test('a fingerprint rejects anything that is not a sha-256 digest', function (): void {
    expect(fn () => new DeviceFingerprint('nope'))->toThrow(InvalidArgumentException::class);
});
