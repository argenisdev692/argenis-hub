<?php

declare(strict_types=1);

namespace Modules\Products\Application\Commands;

use Modules\Products\Application\DTOs\StoreProductData;
use Modules\Products\Application\Support\ProductClientResolver;
use Modules\Products\Application\Support\ProductSlug;
use Modules\Products\Infrastructure\Persistence\Eloquent\Models\ProductEloquentModel;

final readonly class UpdateProductHandler
{
    #[\NoDiscard]
    public function handle(ProductEloquentModel $product, StoreProductData $data): ProductEloquentModel
    {
        $product->update([
            'client_id' => ProductClientResolver::idFor($data->clientUuid),
            'type' => $data->type,
            'title' => $data->title,
            'slug' => ProductSlug::uniqueFor($data->title, $product->id),
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

        return $product->refresh();
    }
}
