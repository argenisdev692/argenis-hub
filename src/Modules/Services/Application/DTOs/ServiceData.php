<?php

declare(strict_types=1);

namespace Modules\Services\Application\DTOs;

use Modules\Services\Infrastructure\Persistence\Eloquent\Models\ServiceEloquentModel;
use Spatie\LaravelData\Attributes\MapOutputName;
use Spatie\LaravelData\Data;
use Spatie\LaravelData\Mappers\SnakeCaseMapper;

/**
 * Admin-facing representation of a catalog service.
 *
 * The allowlist behind every `/data/admin/services` response: the
 * auto-increment `id` and `user_id` never cross this boundary (OWASP §12).
 */
#[MapOutputName(SnakeCaseMapper::class)]
final class ServiceData extends Data
{
    public function __construct(
        public readonly string $uuid,
        public readonly string $name,
        public readonly string $slug,
        public readonly ?string $description,
        public readonly bool $isActive,
        public readonly int $sortOrder,
        public readonly ?string $createdAt,
        public readonly ?string $updatedAt,
        public readonly ?string $deletedAt,
    ) {}

    public static function fromModel(ServiceEloquentModel $service): self
    {
        return new self(
            uuid: $service->uuid,
            name: $service->name,
            slug: $service->slug,
            description: $service->description,
            isActive: $service->is_active,
            sortOrder: $service->sort_order,
            createdAt: $service->created_at?->toIso8601String(),
            updatedAt: $service->updated_at?->toIso8601String(),
            deletedAt: $service->deleted_at?->toIso8601String(),
        );
    }
}
