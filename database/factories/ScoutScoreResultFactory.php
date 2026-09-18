<?php

declare(strict_types=1);

namespace Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;
use Modules\LeadScout\Domain\Enums\Tier;
use Modules\LeadScout\Infrastructure\Persistence\Eloquent\Models\ScoutCompanyEloquentModel;
use Modules\LeadScout\Infrastructure\Persistence\Eloquent\Models\ScoutScoreResultEloquentModel;

/**
 * @extends Factory<ScoutScoreResultEloquentModel>
 */
final class ScoutScoreResultFactory extends Factory
{
    protected $model = ScoutScoreResultEloquentModel::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'uuid' => (string) Str::uuid7(),
            'company_id' => ScoutCompanyFactory::new(),
            'rules_version' => '2026.09.3',
            'subscores' => ['technical' => 80, 'commercial' => 96, 'recurrent' => 80, 'vitality' => 100, 'communication' => 100, 'geo_contract' => 60, 'remote' => 100],
            'lead_score' => 87,
            'confidence' => 78,
            'tier' => Tier::A,
            'is_current' => true,
        ];
    }

    public function forCompany(ScoutCompanyEloquentModel $company): self
    {
        return $this->state(fn (): array => ['company_id' => $company->id]);
    }

    public function a(): self
    {
        return $this->state(fn (): array => ['tier' => Tier::A, 'lead_score' => 87, 'confidence' => 78]);
    }

    public function needsResearch(): self
    {
        return $this->state(fn (): array => ['tier' => Tier::B, 'lead_score' => 82, 'confidence' => 55]);
    }
}
