<?php

declare(strict_types=1);

namespace Modules\Services\Application\DTOs;

use Modules\Services\Infrastructure\Persistence\Eloquent\Models\ServiceEloquentModel;
use Spatie\LaravelData\Attributes\MapOutputName;
use Spatie\LaravelData\Data;
use Spatie\LaravelData\Mappers\SnakeCaseMapper;

/**
 * Public catalog entry for the landing page `<select>` (spec: {@see
 * \Database\Seeders\ServiceSeeder}). Deliberately narrower than {@see
 * ServiceData}: no timestamps, no lifecycle state, no owner — nothing an
 * unauthenticated caller has any use for (OWASP §12 allowlist).
 */
#[MapOutputName(SnakeCaseMapper::class)]
final class PublicServiceData extends Data
{
    public function __construct(
        public readonly string $uuid,
        public readonly string $name,
        public readonly string $slug,
        public readonly ?string $description,
        public readonly int $sortOrder,
    ) {}

    public static function fromModel(ServiceEloquentModel $service): self
    {
        return new self(
            uuid: $service->uuid,
            name: $service->name,
            slug: $service->slug,
            description: $service->description,
            sortOrder: $service->sort_order,
        );
    }
}
