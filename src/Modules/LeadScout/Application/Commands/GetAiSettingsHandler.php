<?php

declare(strict_types=1);

namespace Modules\LeadScout\Application\Commands;

use Modules\LeadScout\Application\DTOs\AiSettingsData;
use Modules\LeadScout\Domain\Ports\AiModelCatalogPort;
use Modules\LeadScout\Infrastructure\Persistence\Eloquent\Models\ScoutAiSettingEloquentModel;

/**
 * AI settings read (spec US-10, T054).
 */
final readonly class GetAiSettingsHandler
{
    public function __construct(private AiModelCatalogPort $catalog) {}

    public function handle(): AiSettingsData
    {
        $purposes = [];

        foreach (['extraction', 'drafting'] as $purpose) {
            $stored = ScoutAiSettingEloquentModel::query()->where('purpose', $purpose)->first();

            $purposes[$purpose] = [
                'provider' => $stored?->provider,
                'model' => $stored?->model,
                'fallback_provider' => $stored?->fallback_provider,
                'fallback_model' => $stored?->fallback_model,
                'options' => $this->catalog->options($purpose),
            ];
        }

        return new AiSettingsData($purposes);
    }
}
