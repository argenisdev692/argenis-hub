<?php

declare(strict_types=1);

namespace Modules\CvJobStudio\Application\DTOs;

use Shared\Application\DTOs\SoftDeleteFilterData;
use Spatie\LaravelData\Attributes\MapInputName;
use Spatie\LaravelData\Attributes\MapOutputName;
use Spatie\LaravelData\Mappers\SnakeCaseMapper;

/**
 * List/export filter shared by ListPostingsHandler and the Excel/PDF exports
 * (BACKEND-PHP §5.2) — one validated contract, never a duplicated `when()`
 * chain.
 */
#[MapInputName(SnakeCaseMapper::class)]
#[MapOutputName(SnakeCaseMapper::class)]
final class StudioPostingFilterData extends SoftDeleteFilterData
{
    public function __construct(
        ?string $search = null,
        ?string $status = null,
        ?string $dateFrom = null,
        ?string $dateTo = null,
        public ?string $remoteScope = null,
    ) {
        parent::__construct($search, $status, $dateFrom, $dateTo);
    }

    /** @return array<string, mixed> */
    public static function rules(): array
    {
        return [
            ...self::baseRules(),
            'status' => ['nullable', 'string', 'in:all,active,suspended'],
            'remote_scope' => ['nullable', 'string', 'in:remote_global,remote_eu,remote_pt_es,remote_unclear,hybrid_local'],
        ];
    }
}
