<?php

declare(strict_types=1);

namespace Modules\Products\Application\DTOs;

use Modules\Products\Domain\Enums\ProductStatus;
use Modules\Products\Domain\Enums\ProductType;
use Shared\Domain\Enums\BillingUnit;
use Spatie\LaravelData\Attributes\MapInputName;
use Spatie\LaravelData\Attributes\MapOutputName;
use Spatie\LaravelData\Data;
use Spatie\LaravelData\Mappers\SnakeCaseMapper;
use Spatie\LaravelData\Support\Validation\ValidationContext;

/**
 * Fused create/update DTO (DTO Fusion Rule — store and update share 100% of
 * fields). The slug is derived server-side from the title, never accepted from
 * the client.
 */
#[MapInputName(SnakeCaseMapper::class)]
#[MapOutputName(SnakeCaseMapper::class)]
final class StoreProductData extends Data
{
    public function __construct(
        public ProductType $type,
        public string $title,
        public float $price,
        public ?string $description = null,
        public string $currency = 'EUR',
        public BillingUnit $defaultUnit = BillingUnit::Hour,
        public ProductStatus $status = ProductStatus::Draft,
        public string $level = 'beginner',
        public string $language = 'es',
        public ?string $clientUuid = null,
        public ?string $startDate = null,
        public ?string $endDate = null,
        public ?float $totalHours = null,
        public ?int $totalSessions = null,
        public ?string $modality = null,
        public ?string $notes = null,
    ) {}

    /**
     * @return array<string, mixed>
     */
    public static function rules(?ValidationContext $context = null): array
    {
        return [
            'type' => ['required', 'string', 'in:'.implode(',', ProductType::values())],
            'title' => ['required', 'string', 'max:255'],
            'description' => ['nullable', 'string', 'max:5000'],
            'price' => ['required', 'numeric', 'min:0', 'max:99999999.99'],
            'currency' => ['required', 'string', 'size:3', 'alpha', 'uppercase'],
            'default_unit' => ['required', 'string', 'in:'.implode(',', BillingUnit::values())],
            'status' => ['required', 'string', 'in:'.implode(',', ProductStatus::values())],
            'level' => ['required', 'string', 'max:32'],
            'language' => ['required', 'string', 'max:8'],
            'client_uuid' => ['nullable', 'uuid', 'exists:clients,uuid'],
            'start_date' => ['nullable', 'date'],
            'end_date' => ['nullable', 'date', 'after_or_equal:start_date'],
            'total_hours' => ['nullable', 'numeric', 'min:0', 'max:999999.99'],
            'total_sessions' => ['nullable', 'integer', 'min:0', 'max:65535'],
            'modality' => ['nullable', 'string', 'max:16'],
            'notes' => ['nullable', 'string', 'max:5000'],
        ];
    }
}
