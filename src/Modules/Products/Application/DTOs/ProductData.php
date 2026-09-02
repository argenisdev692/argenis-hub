<?php

declare(strict_types=1);

namespace Modules\Products\Application\DTOs;

use Modules\Products\Domain\Enums\ProductStatus;
use Modules\Products\Domain\Enums\ProductType;
use Modules\Products\Infrastructure\Persistence\Eloquent\Models\ProductEloquentModel;
use Shared\Domain\Enums\BillingUnit;
use Spatie\LaravelData\Attributes\MapOutputName;
use Spatie\LaravelData\Data;
use Spatie\LaravelData\Mappers\SnakeCaseMapper;

/**
 * Admin-facing representation of a catalog product.
 *
 * The allowlist behind every products response: the auto-increment `id` and
 * `user_id` never cross this boundary (OWASP §12).
 */
#[MapOutputName(SnakeCaseMapper::class)]
final class ProductData extends Data
{
    public function __construct(
        public readonly string $uuid,
        public readonly ProductType $type,
        public readonly string $title,
        public readonly string $slug,
        public readonly ?string $description,
        public readonly float $price,
        public readonly string $currency,
        public readonly BillingUnit $defaultUnit,
        public readonly ProductStatus $status,
        public readonly string $level,
        public readonly string $language,
        public readonly ?string $clientUuid,
        public readonly ?string $clientName,
        public readonly ?string $startDate,
        public readonly ?string $endDate,
        public readonly ?float $totalHours,
        public readonly ?int $totalSessions,
        public readonly ?string $modality,
        public readonly ?string $notes,
        public readonly ?string $createdAt,
        public readonly ?string $updatedAt,
        public readonly ?string $deletedAt,
    ) {}

    public static function fromModel(ProductEloquentModel $product): self
    {
        return new self(
            uuid: $product->uuid,
            type: $product->type,
            title: $product->title,
            slug: $product->slug,
            description: $product->description,
            price: (float) $product->price,
            currency: $product->currency,
            defaultUnit: $product->default_unit,
            status: $product->status,
            level: $product->level,
            language: $product->language,
            clientUuid: $product->relationLoaded('client') ? $product->client?->uuid : null,
            clientName: $product->relationLoaded('client') ? $product->client?->client_name : null,
            startDate: $product->start_date?->toDateString(),
            endDate: $product->end_date?->toDateString(),
            totalHours: $product->total_hours !== null ? (float) $product->total_hours : null,
            totalSessions: $product->total_sessions,
            modality: $product->modality,
            notes: $product->notes,
            createdAt: $product->created_at?->toIso8601String(),
            updatedAt: $product->updated_at?->toIso8601String(),
            deletedAt: $product->deleted_at?->toIso8601String(),
        );
    }
}
