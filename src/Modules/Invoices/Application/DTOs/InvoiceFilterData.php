<?php

declare(strict_types=1);

namespace Modules\Invoices\Application\DTOs;

use Shared\Application\DTOs\SoftDeleteFilterData;
use Spatie\LaravelData\Attributes\MapInputName;
use Spatie\LaravelData\Attributes\MapOutputName;
use Spatie\LaravelData\Mappers\SnakeCaseMapper;

#[MapInputName(SnakeCaseMapper::class)]
#[MapOutputName(SnakeCaseMapper::class)]
final class InvoiceFilterData extends SoftDeleteFilterData
{
    public function __construct(
        ?string $search = null,
        ?string $status = null,
        ?string $dateFrom = null,
        ?string $dateTo = null,
        public ?int $year = null,
        public ?string $clientUuid = null,
    ) {
        parent::__construct($search, $status, $dateFrom, $dateTo);
    }

    /**
     * @return array<string, mixed>
     */
    public static function rules(): array
    {
        return [
            ...self::baseRules(),
            'status' => ['nullable', 'string', 'in:active,suspended'],
            'year' => ['nullable', 'integer', 'min:2000', 'max:2100'],
            'client_uuid' => ['nullable', 'uuid'],
        ];
    }
}
