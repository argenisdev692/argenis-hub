<?php

declare(strict_types=1);

namespace Modules\Availability\Infrastructure\Http\Controllers;

use Carbon\CarbonImmutable;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Modules\Availability\Domain\Services\AvailabilityResolver;
use Modules\Availability\Domain\ValueObjects\ResolvedDay;

/**
 * Read-only view of the *effective* availability over a date range — the weekly
 * template with national holidays and date exceptions already applied. The
 * appointment module (and the calendar UI) consumes this before offering slots.
 *
 * The range is capped at 92 days so it can never be turned into an unbounded
 * computation (OWASP — unrestricted resource consumption).
 */
final readonly class AvailabilityCalendarController
{
    private const int MAX_DAYS = 92;

    public function __invoke(Request $request, AvailabilityResolver $resolver): JsonResponse
    {
        $validated = $request->validate([
            'from' => ['nullable', 'date_format:Y-m-d'],
            'to' => ['nullable', 'date_format:Y-m-d', 'after_or_equal:from'],
        ]);

        $from = CarbonImmutable::parse($validated['from'] ?? CarbonImmutable::now()->startOfMonth()->format('Y-m-d'));
        $to = CarbonImmutable::parse($validated['to'] ?? $from->endOfMonth()->format('Y-m-d'));

        if ($from->diffInDays($to) > self::MAX_DAYS) {
            $to = $from->addDays(self::MAX_DAYS);
        }

        $days = array_map(
            static fn (ResolvedDay $day): array => $day->toArray(),
            array_values($resolver->resolveRange($from, $to)),
        );

        return response()->json(['data' => $days]);
    }
}
