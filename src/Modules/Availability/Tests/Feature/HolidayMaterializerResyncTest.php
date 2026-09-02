<?php

declare(strict_types=1);

namespace Modules\Availability\Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Modules\Availability\Domain\Services\HolidayMaterializer;
use Modules\Availability\Domain\ValueObjects\ExceptionSource;
use Modules\Availability\Infrastructure\Persistence\Eloquent\Models\AvailabilityExceptionEloquentModel;
use Tests\TestCase;

final class HolidayMaterializerResyncTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->travelTo(Carbon::parse('2026-03-01'));
    }

    private function materializer(): HolidayMaterializer
    {
        return app(HolidayMaterializer::class);
    }

    public function test_resync_rebuilds_future_holidays_and_preserves_manual_overrides(): void
    {
        // A stale future holiday from the previous country (a date that is NOT a
        // real PT holiday) — must be purged.
        AvailabilityExceptionEloquentModel::factory()->on('2026-03-15')->holiday()->create();

        // A manual override on a future date — must survive untouched.
        $manual = AvailabilityExceptionEloquentModel::factory()
            ->on('2026-03-10')->open('10:00', '14:00')->create();

        $result = $this->materializer()->resync();

        // Stale holiday gone, manual override intact, PT holidays rebuilt.
        $this->assertDatabaseMissing('availability_exceptions', ['date' => '2026-03-15']);
        $this->assertDatabaseHas('availability_exceptions', [
            'uuid' => $manual->uuid,
            'is_available' => true,
            'source' => ExceptionSource::Manual->value,
        ]);
        $this->assertDatabaseHas('availability_exceptions', [
            'date' => '2026-12-25',
            'reason' => 'Natal',
            'source' => ExceptionSource::Holiday->value,
        ]);
        $this->assertDatabaseHas('availability_exceptions', ['date' => '2027-12-25']);
        $this->assertSame(1, $result['purged']);
    }

    public function test_resync_never_clobbers_a_manual_override_on_a_holiday_date(): void
    {
        // Manual forced-open on Dia da Liberdade (a real PT holiday).
        AvailabilityExceptionEloquentModel::factory()
            ->on('2026-04-25')->open('09:00', '13:00')->create();

        $this->materializer()->resync();

        $row = AvailabilityExceptionEloquentModel::query()->whereDate('date', '2026-04-25')->firstOrFail();
        $this->assertTrue($row->is_available);
        $this->assertSame(ExceptionSource::Manual, $row->source);
    }

    public function test_resync_keeps_past_holidays_as_history(): void
    {
        AvailabilityExceptionEloquentModel::factory()->on('2026-02-20')->holiday()->create();

        $this->materializer()->resync();

        $this->assertDatabaseHas('availability_exceptions', [
            'date' => '2026-02-20',
            'source' => ExceptionSource::Holiday->value,
        ]);
    }
}
