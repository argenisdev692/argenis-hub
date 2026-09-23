<?php

declare(strict_types=1);

use Modules\VideoEdits\Domain\Services\FrozenFrameParser;
use Modules\VideoEdits\Domain\ValueObjects\TimeRange;

it('turns freezedetect output into still intervals', function (): void {
    $stderr = <<<'LOG'
        [freezedetect @ 0x55] lavfi.freezedetect.freeze_start: 2.5
        [freezedetect @ 0x55] lavfi.freezedetect.freeze_duration: 4
        [freezedetect @ 0x55] lavfi.freezedetect.freeze_end: 6.5
        [freezedetect @ 0x55] lavfi.freezedetect.freeze_start: 30.12
        LOG;

    $frozen = (new FrozenFrameParser)->parse($stderr, 40_000);

    // A recording that ends frozen never prints the last freeze_end.
    expect(array_map(static fn (TimeRange $range): array => [$range->startMs, $range->endMs], $frozen))
        ->toBe([[2_500, 6_500], [30_120, 40_000]]);
});

it('measures the share of a range during which the screen moved', function (): void {
    $frozen = [new TimeRange(0, 4_000), new TimeRange(9_000, 12_000)];

    expect(FrozenFrameParser::activityRatio(new TimeRange(2_000, 12_000), $frozen))->toBe(0.5)
        ->and(FrozenFrameParser::activityRatio(new TimeRange(4_000, 9_000), $frozen))->toBe(1.0)
        ->and(FrozenFrameParser::activityRatio(new TimeRange(0, 4_000), $frozen))->toBe(0.0);
});
