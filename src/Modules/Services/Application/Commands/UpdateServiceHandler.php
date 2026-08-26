<?php

declare(strict_types=1);

namespace Modules\Services\Application\Commands;

use Illuminate\Database\Eloquent\ModelNotFoundException;
use Modules\Services\Application\DTOs\ServiceData;
use Modules\Services\Infrastructure\Persistence\Eloquent\Models\ServiceEloquentModel;

final readonly class UpdateServiceHandler
{
    /**
     * @param  array{name: string, slug: string, description: ?string, is_active?: bool, sort_order?: int}  $attributes
     *
     * @throws ModelNotFoundException<ServiceEloquentModel>
     */
    #[\NoDiscard('handle() returns the updated service.')]
    public function handle(string $uuid, array $attributes): ServiceData
    {
        $service = ServiceEloquentModel::query()->where('uuid', $uuid)->firstOrFail();

        $service->update([
            'name' => $attributes['name'],
            'slug' => $attributes['slug'],
            'description' => $attributes['description'] ?? null,
            'is_active' => $attributes['is_active'] ?? $service->is_active,
            'sort_order' => $attributes['sort_order'] ?? $service->sort_order,
        ]);

        return ServiceData::fromModel($service);
    }
}
