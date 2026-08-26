<?php

declare(strict_types=1);

namespace Modules\Services\Application\Commands;

use Modules\Services\Application\DTOs\ServiceData;
use Modules\Services\Infrastructure\Persistence\Eloquent\Models\ServiceEloquentModel;

final readonly class CreateServiceHandler
{
    /**
     * @param  array{name: string, slug: string, description: ?string, is_active?: bool, sort_order?: int}  $attributes
     */
    #[\NoDiscard('handle() returns the created service.')]
    public function handle(array $attributes, int $userId): ServiceData
    {
        $service = ServiceEloquentModel::query()->create([
            'name' => $attributes['name'],
            'slug' => $attributes['slug'],
            'description' => $attributes['description'] ?? null,
            'is_active' => $attributes['is_active'] ?? true,
            'sort_order' => $attributes['sort_order'] ?? 0,
            'user_id' => $userId,
        ]);

        return ServiceData::fromModel($service);
    }
}
