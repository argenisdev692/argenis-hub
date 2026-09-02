<?php

declare(strict_types=1);

namespace Modules\Availability\Application\DTOs;

use Closure;
use Modules\Availability\Domain\Ports\AvailabilityRuleRepositoryPort;
use Spatie\LaravelData\Attributes\MapInputName;
use Spatie\LaravelData\Data;
use Spatie\LaravelData\Mappers\SnakeCaseMapper;

/**
 * Create/update payload for a weekly availability rule (one slot on one weekday).
 * Store and Update share the full field set, so a single fused DTO is used.
 *
 * Times are `H:i` (24h) — the DB `time` column normalises them to `HH:MM:SS`.
 * Two guarantees beyond field validation: `end_time` must be strictly after
 * `start_time`, and an AVAILABLE slot may not overlap another available slot on
 * the same weekday (so the resolver never emits ambiguous windows).
 */
#[MapInputName(SnakeCaseMapper::class)]
final class AvailabilityRuleData extends Data
{
    public function __construct(
        public int $dayOfWeek,
        public string $startTime,
        public string $endTime,
        public bool $isAvailable = true,
    ) {}

    /**
     * @return array<string, mixed>
     */
    public static function rules(): array
    {
        return [
            'day_of_week' => ['required', 'integer', 'between:0,6'],
            'start_time' => ['required', 'date_format:H:i', self::noOverlapRule()],
            'end_time' => ['required', 'date_format:H:i', 'after:start_time'],
            'is_available' => ['required', 'boolean'],
        ];
    }

    /**
     * Rejects an available slot that overlaps another available slot on the same
     * weekday. The overlap predicate itself lives in
     * {@see AvailabilityRuleRepositoryPort::hasOverlappingAvailableSlot()} — the
     * Application layer asks the port a question and never names a table or
     * builds a query of its own.
     */
    private static function noOverlapRule(): Closure
    {
        return function (string $attribute, mixed $value, Closure $fail): void {
            $request = request();

            if (! $request->boolean('is_available')) {
                return; // unavailable rows never conflict
            }

            $start = (string) $value;
            $end = (string) $request->input('end_time');

            if ($end === '' || $end <= $start) {
                return; // let date_format / after rules report malformed input
            }

            $overlaps = app(AvailabilityRuleRepositoryPort::class)->hasOverlappingAvailableSlot(
                $request->integer('day_of_week'),
                $start,
                $end,
                $request->route('uuid') !== null ? (string) $request->route('uuid') : null,
            );

            if ($overlaps) {
                $fail(__('This slot overlaps an existing availability window on the same day.'));
            }
        };
    }
}
