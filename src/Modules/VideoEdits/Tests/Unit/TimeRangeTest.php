<?php

declare(strict_types=1);

use Modules\VideoEdits\Domain\ValueObjects\TimeRange;

it('measures its duration in milliseconds', function (): void {
    expect((new TimeRange(1_250, 4_000))->durationMs())->toBe(2_750);
});

it('rejects impossible intervals', function (int $start, int $end): void {
    new TimeRange($start, $end);
})->with([
    'negative start' => [-1, 500],
    'empty' => [1_000, 1_000],
    'reversed' => [2_000, 1_000],
])->throws(InvalidArgumentException::class);
