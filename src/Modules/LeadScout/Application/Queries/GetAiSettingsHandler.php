<?php

declare(strict_types=1);

namespace Modules\LeadScout\Application\Queries;

use Modules\LeadScout\Application\DTOs\AiSettingsData;
use Modules\LeadScout\Domain\Enums\AiPurpose;
use Modules\LeadScout\Domain\Ports\AiModelCatalogPort;
use Modules\LeadScout\Domain\Ports\AiSettingRepositoryPort;

/**
 * AI settings read (spec US-10, T054).
 */
final readonly class GetAiSettingsHandler
{
    public function __construct(
        private AiModelCatalogPort $catalog,
        private AiSettingRepositoryPort $settings,
    ) {}

    public function handle(): AiSettingsData
    {
        $purposes = [];

        foreach (AiPurpose::cases() as $purpose) {
            $stored = $this->settings->forPurpose($purpose);

            $purposes[$purpose->value] = [
                'provider' => $stored?->provider,
                'model' => $stored?->model,
                'fallback_provider' => $stored?->fallbackProvider,
                'fallback_model' => $stored?->fallbackModel,
                'options' => $this->catalog->options($purpose->value),
            ];
        }

        return new AiSettingsData($purposes);
    }
}
