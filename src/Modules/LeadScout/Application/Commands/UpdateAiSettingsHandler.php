<?php

declare(strict_types=1);

namespace Modules\LeadScout\Application\Commands;

use Modules\LeadScout\Application\DTOs\AiSettingsData;
use Modules\LeadScout\Application\DTOs\UpdateAiSettingsData;
use Modules\LeadScout\Application\Queries\GetAiSettingsHandler;
use Modules\LeadScout\Domain\Entities\AiSetting;
use Modules\LeadScout\Domain\Enums\AiPurpose;
use Modules\LeadScout\Domain\Exceptions\InvalidInputException;
use Modules\LeadScout\Domain\Ports\AiModelCatalogPort;
use Modules\LeadScout\Domain\Ports\AiSettingRepositoryPort;

/**
 * AI default change (spec US-10, T054): catalog-closed, credentials-checked.
 * Changing the default never rewrites already-generated drafts (their
 * provider/model is frozen on the outreach row).
 */
final readonly class UpdateAiSettingsHandler
{
    public function __construct(
        private AiModelCatalogPort $catalog,
        private AiSettingRepositoryPort $settings,
        private GetAiSettingsHandler $read,
    ) {}

    public function handle(UpdateAiSettingsData $data): AiSettingsData
    {
        $resolved = $this->catalog->resolve($data->purpose, $data->provider, $data->model);

        if (($data->fallbackProvider ?? null) !== null || ($data->fallbackModel ?? null) !== null) {
            if ($data->fallbackProvider === null || $data->fallbackModel === null) {
                throw InvalidInputException::withMessages(['fallback' => 'Fallback needs both provider and model.']);
            }

            $this->catalog->resolve($data->purpose, $data->fallbackProvider, $data->fallbackModel);
        }

        $this->settings->save(new AiSetting(
            purpose: AiPurpose::from($data->purpose),
            provider: $resolved['provider'],
            model: $resolved['model'],
            fallbackProvider: $data->fallbackProvider ?? $resolved['fallback_provider'],
            fallbackModel: $data->fallbackModel ?? $resolved['fallback_model'],
        ));

        return $this->read->handle();
    }
}
