<?php

declare(strict_types=1);

namespace Modules\Cvs\Application\DTOs;

use Modules\Cvs\Domain\Enums\CvNiche;
use Shared\Application\DTOs\SoftDeleteFilterData;
use Spatie\LaravelData\Attributes\MapInputName;
use Spatie\LaravelData\Attributes\MapOutputName;
use Spatie\LaravelData\Mappers\SnakeCaseMapper;

/**
 * List/export filter. Soft-delete `status` is active|suspended; optional
 * `niche` filters the domain niche column.
 */
#[MapInputName(SnakeCaseMapper::class)]
#[MapOutputName(SnakeCaseMapper::class)]
final class CvFilterData extends SoftDeleteFilterData
{
    public function __construct(
        ?string $search = null,
        ?string $status = null,
        ?string $dateFrom = null,
        ?string $dateTo = null,
        public ?CvNiche $niche = null,
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
            'niche' => ['nullable', 'string', 'in:'.implode(',', CvNiche::values())],
        ];
    }
}
