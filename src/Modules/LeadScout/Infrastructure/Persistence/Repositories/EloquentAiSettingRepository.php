<?php

declare(strict_types=1);

namespace Modules\LeadScout\Infrastructure\Persistence\Repositories;

use Modules\LeadScout\Domain\Entities\AiSetting;
use Modules\LeadScout\Domain\Enums\AiPurpose;
use Modules\LeadScout\Domain\Ports\AiSettingRepositoryPort;
use Modules\LeadScout\Infrastructure\Persistence\Eloquent\Models\ScoutAiSettingEloquentModel;

final readonly class EloquentAiSettingRepository implements AiSettingRepositoryPort
{
    public function forPurpose(AiPurpose $purpose): ?AiSetting
    {
        $model = ScoutAiSettingEloquentModel::query()->where('purpose', $purpose->value)->first();

        return $model === null ? null : new AiSetting(
            purpose: $purpose,
            provider: $model->provider,
            model: $model->model,
            fallbackProvider: $model->fallback_provider,
            fallbackModel: $model->fallback_model,
        );
    }

    public function save(AiSetting $setting): void
    {
        ScoutAiSettingEloquentModel::query()->updateOrCreate(
            ['purpose' => $setting->purpose->value],
            [
                'provider' => $setting->provider,
                'model' => $setting->model,
                'fallback_provider' => $setting->fallbackProvider,
                'fallback_model' => $setting->fallbackModel,
            ],
        );
    }
}
