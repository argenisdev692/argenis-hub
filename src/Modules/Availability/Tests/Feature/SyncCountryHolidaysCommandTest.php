<?php

declare(strict_types=1);

namespace Modules\Availability\Tests\Feature;

use App\Models\CompanyData;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Modules\Availability\Infrastructure\Persistence\Eloquent\Models\AvailabilityExceptionEloquentModel;
use Tests\TestCase;

final class SyncCountryHolidaysCommandTest extends TestCase
{
    use RefreshDatabase;

    public function test_it_materialises_portugal_holidays_by_default(): void
    {
        $this->artisan('availability:sync-holidays', ['year' => 2026])->assertSuccessful();

        $this->assertSame(13, AvailabilityExceptionEloquentModel::count());
        $this->assertDatabaseHas('availability_exceptions', [
            'date' => '2026-12-25',
            'is_available' => false,
            'reason' => 'Natal',
        ]);
    }

    public function test_it_reads_the_company_country_from_the_database(): void
    {
        $user = User::factory()->create();
        CompanyData::query()->create([
            'company_name' => 'Vidula',
            'country_code' => 'PT',
            'user_id' => $user->id,
        ]);

        $this->artisan('availability:sync-holidays', ['year' => 2026])->assertSuccessful();

        $this->assertSame(13, AvailabilityExceptionEloquentModel::count());
    }

    public function test_it_is_idempotent(): void
    {
        $this->artisan('availability:sync-holidays', ['year' => 2026])->assertSuccessful();
        $this->artisan('availability:sync-holidays', ['year' => 2026])->assertSuccessful();

        $this->assertSame(13, AvailabilityExceptionEloquentModel::count());
    }

    public function test_it_never_clobbers_an_existing_override(): void
    {
        AvailabilityExceptionEloquentModel::factory()->on('2026-12-25')->open('10:00', '14:00')->create();

        $this->artisan('availability:sync-holidays', ['year' => 2026])->assertSuccessful();

        $this->assertDatabaseHas('availability_exceptions', [
            'date' => '2026-12-25',
            'is_available' => true,
            'start_time' => '10:00:00',
        ]);
        $this->assertSame(13, AvailabilityExceptionEloquentModel::count());
    }

    public function test_no_year_argument_syncs_current_and_next_year(): void
    {
        $this->artisan('availability:sync-holidays')->assertSuccessful();

        $currentYear = now()->year;
        $this->assertDatabaseHas('availability_exceptions', ['date' => "{$currentYear}-12-25"]);
        $this->assertDatabaseHas('availability_exceptions', ['date' => ($currentYear + 1).'-12-25']);
    }
}
