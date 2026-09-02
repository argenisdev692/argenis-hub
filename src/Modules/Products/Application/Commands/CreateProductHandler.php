<?php

declare(strict_types=1);

namespace Modules\Products\Application\Commands;

use Modules\Products\Application\DTOs\StoreProductData;
use Modules\Products\Application\Support\ProductClientResolver;
use Modules\Products\Application\Support\ProductSlug;
use Modules\Products\Infrastructure\Persistence\Eloquent\Models\ProductEloquentModel;

final readonly class CreateProductHandler
{
    #[\NoDiscard]
    public function handle(StoreProductData $data, int $userId): ProductEloquentModel
    {
        return ProductEloquentModel::query()->create([
            'user_id' => $userId,
            'client_id' => ProductClientResolver::idFor($data->clientUuid),
            'type' => $data->type,
            'title' => $data->title,
            'slug' => ProductSlug::uniqueFor($data->title),
            'description' => $data->description,
            'price' => $data->price,
            'currency' => $data->currency,
            'default_unit' => $data->defaultUnit,
            'status' => $data->status,
            'level' => $data->level,
            'language' => $data->language,
            'start_date' => $data->startDate,
            'end_date' => $data->endDate,
            'total_hours' => $data->totalHours,
            'total_sessions' => $data->totalSessions,
            'modality' => $data->modality,
            'notes' => $data->notes,
        ]);
    }
}
