<?php

declare(strict_types=1);

namespace Modules\Availability\Tests\Feature;

use App\Models\User;
use Carbon\CarbonImmutable;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Modules\Availability\Domain\Services\AvailabilityResolver;
use Modules\Availability\Infrastructure\Persistence\Eloquent\Models\AvailabilityExceptionEloquentModel;
use Modules\Availability\Infrastructure\Persistence\Eloquent\Models\AvailabilityRuleEloquentModel;
use Tests\TestCase;

final class AvailabilityResolverTest extends TestCase
{
    use RefreshDatabase;

    private AvailabilityResolver $resolver;

    protected function setUp(): void
    {
        parent::setUp();
        $this->resolver = app(AvailabilityResolver::class);
    }

    public function test_a_weekday_with_a_rule_is_open_with_its_slots(): void
    {
        AvailabilityRuleEloquentModel::factory()->forDay(5)->slot('09:00', '13:00')->create();
        AvailabilityRuleEloquentModel::factory()->forDay(5)->slot('14:00', '18:00')->create();

        $day = $this->resolver->resolve(CarbonImmutable::parse('2026-12-11'));

        $this->assertTrue($day->isOpen);
        $this->assertSame('rule', $day->source);
        $this->assertSame(
            [['start' => '09:00', 'end' => '13:00'], ['start' => '14:00', 'end' => '18:00']],
            array_map(static fn ($slot): array => $slot->toArray(), $day->slots),
        );
    }

    public function test_a_weekday_without_a_rule_is_closed_by_default(): void
    {
        $day = $this->resolver->resolve(CarbonImmutable::parse('2026-12-13'));

        $this->assertFalse($day->isOpen);
        $this->assertSame('default', $day->source);
    }

    public function test_a_forced_open_exception_opens_the_day(): void
    {
        AvailabilityExceptionEloquentModel::factory()
            ->on('2026-12-25')
            ->open('10:00', '14:00')
            ->create(['reason' => 'Special Christmas shift']);

        $day = $this->resolver->resolve(CarbonImmutable::parse('2026-12-25'));

        $this->assertTrue($day->isOpen);
        $this->assertSame('exception', $day->source);
        $this->assertSame([['start' => '10:00', 'end' => '14:00']], array_map(static fn ($s): array => $s->toArray(), $day->slots));
    }

    public function test_a_closure_exception_closes_an_otherwise_open_weekday(): void
    {
        AvailabilityRuleEloquentModel::factory()->forDay(5)->slot('09:00', '13:00')->create();
        AvailabilityExceptionEloquentModel::factory()->on('2026-12-11')->create(['reason' => 'Team offsite']);

        $day = $this->resolver->resolve(CarbonImmutable::parse('2026-12-11'));

        $this->assertFalse($day->isOpen);
        $this->assertSame('exception', $day->source);
        $this->assertSame('Team offsite', $day->reason);
    }

    public function test_a_materialised_holiday_closes_the_day(): void
    {
        AvailabilityRuleEloquentModel::factory()->forDay(5)->slot('09:00', '13:00')->create();
        AvailabilityExceptionEloquentModel::factory()->on('2026-12-25')->create(['reason' => 'Natal']);

        $day = $this->resolver->resolve(CarbonImmutable::parse('2026-12-25'));

        $this->assertFalse($day->isOpen);
        $this->assertSame('exception', $day->source);
        $this->assertSame('Natal', $day->reason);
    }

    public function test_calendar_endpoint_returns_resolved_days(): void
    {
        $this->seed(RolePermissionSeeder::class);
        $admin = User::factory()->create();
        $admin->assignRole('SUPER_ADMIN');

        AvailabilityRuleEloquentModel::factory()->forDay(5)->slot('09:00', '13:00')->create();

        $this->actingAs($admin)
            ->getJson('/availability-rules/calendar?from=2026-12-11&to=2026-12-11')
            ->assertOk()
            ->assertJsonPath('data.0.date', '2026-12-11')
            ->assertJsonPath('data.0.is_open', true)
            ->assertJsonPath('data.0.source', 'rule');
    }
}
