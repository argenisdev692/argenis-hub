<?php

declare(strict_types=1);

namespace Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;
use Modules\LeadScout\Domain\Enums\ActivityStatus;
use Modules\LeadScout\Domain\Enums\CompanyOrigin;
use Modules\LeadScout\Domain\Enums\CompanyType;
use Modules\LeadScout\Domain\Enums\EmployeeRange;
use Modules\LeadScout\Infrastructure\Persistence\Eloquent\Models\ScoutCompanyEloquentModel;

/**
 * @extends Factory<ScoutCompanyEloquentModel>
 */
final class ScoutCompanyFactory extends Factory
{
    protected $model = ScoutCompanyEloquentModel::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $name = ucfirst((string) fake()->unique()->words(2, true)).' Labs';

        return [
            'uuid' => (string) Str::uuid7(),
            'canonical_domain' => fake()->unique()->domainName(),
            'name' => $name,
            'country' => 'ES',
            'origin' => CompanyOrigin::Discovery,
            'company_type' => CompanyType::SoftwareAgency,
            'employee_range' => EmployeeRange::From11To50,
            'activity_status' => ActivityStatus::Active,
        ];
    }

    public function spanishAgency(): self
    {
        return $this->state(fn (): array => [
            'country' => 'ES',
            'company_type' => CompanyType::SoftwareAgency,
        ]);
    }

    public function portugueseAgency(): self
    {
        return $this->state(fn (): array => [
            'country' => 'PT',
            'company_type' => CompanyType::SoftwareAgency,
        ]);
    }

    public function discovered(): self
    {
        return $this->state(fn (): array => [
            'origin' => CompanyOrigin::Discovery,
            'origin_ref' => 'agencia desarrollo Laravel Madrid',
            'discovery_wave' => 'wave1',
        ]);
    }

    public function needsResearch(): self
    {
        return $this->state(fn (): array => ['needs_research' => true]);
    }

    public function tierA(): self
    {
        return $this->afterCreating(function (ScoutCompanyEloquentModel $company): void {
            ScoutScoreResultFactory::new()->forCompany($company)->a()->create();
        });
    }

    public function suppressed(): self
    {
        return $this->afterCreating(function (ScoutCompanyEloquentModel $company): void {
            ScoutSuppressionFactory::new()->forDomain($company->canonical_domain)->create();
        });
    }
}
