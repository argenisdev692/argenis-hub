<?php

declare(strict_types=1);

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Str;
use Modules\Availability\Infrastructure\Persistence\Eloquent\Models\AvailabilityRuleEloquentModel;

/**
 * Seeds the default weekly availability template — the standard Iberian office
 * week: Monday–Friday, split shift 09:00–13:00 + 14:00–18:00 (one-hour lunch).
 * Saturday/Sunday are left with no rule, so the resolver reports them closed by
 * default. National holidays are NOT seeded — they are computed at runtime by
 * PortugalHolidayProvider (an exception row overrides one if ever needed).
 *
 * Idempotent: keyed on (day_of_week, start_time), so re-running never duplicates.
 */
final class AvailabilitySeeder extends Seeder
{
    /**
     * @var list<array{start: string, end: string}>
     */
    private const array SLOTS = [
        ['start' => '09:00:00', 'end' => '13:00:00'],
        ['start' => '14:00:00', 'end' => '18:00:00'],
    ];

    public function run(): void
    {
        foreach (range(1, 5) as $dayOfWeek) { // Monday … Friday
            foreach (self::SLOTS as $slot) {
                $rule = AvailabilityRuleEloquentModel::query()
                    ->firstOrNew(['day_of_week' => $dayOfWeek, 'start_time' => $slot['start']]);

                // The seeder runs under `WithoutModelEvents` (DatabaseSeeder), so the
                // model's `creating` hook is muted — set the UUID here, but only on
                // insert so a re-seed preserves the existing row's UUID.
                if (! $rule->exists) {
                    $rule->uuid = (string) Str::uuid7();
                }

                $rule->end_time = $slot['end'];
                $rule->is_available = true;
                $rule->save();
            }
        }
    }
}
