<?php

declare(strict_types=1);

use Modules\VideoEdits\Domain\ValueObjects\SilenceThreshold;

it('accepts thresholds inside the allowed range, inclusive', function (float $seconds, int $milliseconds): void {
    $threshold = SilenceThreshold::fromSeconds($seconds, 0.3, 10.0);

    expect($threshold->milliseconds)->toBe($milliseconds)
        ->and($threshold->seconds())->toEqual($seconds);
})->with([
    'minimum' => [0.3, 300],
    'default' => [1.0, 1_000],
    'maximum' => [10.0, 10_000],
]);

it('rejects thresholds outside the allowed range', function (float $seconds): void {
    SilenceThreshold::fromSeconds($seconds, 0.3, 10.0);
})->with([
    'too short' => [0.29],
    'too long' => [10.01],
])->throws(InvalidArgumentException::class);
