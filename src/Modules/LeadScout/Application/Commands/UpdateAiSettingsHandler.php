<?php

declare(strict_types=1);

namespace Modules\LeadScout\Application\Commands;

use Illuminate\Validation\ValidationException;
use Modules\LeadScout\Application\DTOs\AiSettingsData;
use Modules\LeadScout\Application\DTOs\UpdateAiSettingsData;
use Modules\LeadScout\Domain\Ports\AiModelCatalogPort;
use Modules\LeadScout\Infrastructure\Persistence\Eloquent\Models\ScoutAiSettingEloquentModel;

/**
 * AI default change (spec US-10, T054): catalog-closed, credentials-checked.
 * Changing the default never rewrites already-generated drafts (their
 * provider/model is frozen on the outreach row).
 */
final readonly class UpdateAiSettingsHandler
{
    public function __construct(
        private AiModelCatalogPort $catalog,
        private GetAiSettingsHandler $read,
    ) {}

    public function handle(UpdateAiSettingsData $data): AiSettingsData
    {
        $resolved = $this->catalog->resolve($data->purpose, $data->provider, $data->model);

        if (($data->fallbackProvider ?? null) !== null || ($data->fallbackModel ?? null) !== null) {
            if ($data->fallbackProvider === null || $data->fallbackModel === null) {
                throw ValidationException::withMessages(['fallback' => 'Fallback needs both provider and model.']);
            }

            $this->catalog->resolve($data->purpose, $data->fallbackProvider, $data->fallbackModel);
        }

        ScoutAiSettingEloquentModel::query()->updateOrCreate(
            ['purpose' => $data->purpose],
            [
                'provider' => $resolved['provider'],
                'model' => $resolved['model'],
                'fallback_provider' => $data->fallbackProvider ?? $resolved['fallback_provider'],
                'fallback_model' => $data->fallbackModel ?? $resolved['fallback_model'],
            ],
        );

        return $this->read->handle();
    }
}
