<?php

declare(strict_types=1);

namespace Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;
use Modules\LeadScout\Domain\Enums\ExtractionMethod;
use Modules\LeadScout\Domain\Enums\SignalDimension;
use Modules\LeadScout\Domain\Enums\SignalNature;
use Modules\LeadScout\Infrastructure\Persistence\Eloquent\Models\ScoutSignalEloquentModel;

/**
 * @extends Factory<ScoutSignalEloquentModel>
 */
final class ScoutSignalFactory extends Factory
{
    protected $model = ScoutSignalEloquentModel::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'uuid' => (string) Str::uuid7(),
            'company_id' => ScoutCompanyFactory::new(),
            'dimension' => SignalDimension::Technical,
            'signal_key' => 'laravel_detected',
            'value_text' => 'Laravel',
            'nature' => SignalNature::Fact,
            'confidence' => 90,
            'evidence_url' => 'https://example.com/services',
            'evidence_excerpt' => 'Desarrollamos con Laravel y Vue.',
            'captured_at' => now(),
            'extraction_method' => ExtractionMethod::Rule,
        ];
    }
}
