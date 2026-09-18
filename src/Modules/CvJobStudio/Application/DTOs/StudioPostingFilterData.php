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
 * chain. `status` is the soft-delete axis; `stages` is the pipeline axis
 * (`new` → `saved` → `applied`, or `dismissed`/`skipped`).
 */
#[MapInputName(SnakeCaseMapper::class)]
#[MapOutputName(SnakeCaseMapper::class)]
final class StudioPostingFilterData extends SoftDeleteFilterData
{
    /** Pipeline stages a posting can sit in; `reference` rows live in their own tab. */
    public const array STAGES = ['new', 'saved', 'applied', 'dismissed', 'skipped'];

    /** `fit` sorts by the latest score's total; the rest are plain columns. */
    public const array SORTABLE = ['created_at', 'title', 'employer_name', 'fit'];

    /**
     * @param  list<string>|null  $stages
     */
    public function __construct(
        ?string $search = null,
        ?string $status = null,
        ?string $dateFrom = null,
        ?string $dateTo = null,
        public ?string $remoteScope = null,
        public ?array $stages = null,
        public string $sortField = 'created_at',
        public int $sortOrder = -1,
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
            'stages' => ['nullable', 'array', 'max:'.count(self::STAGES)],
            'stages.*' => ['string', 'in:'.implode(',', self::STAGES)],
            'sort_field' => ['sometimes', 'string', 'in:'.implode(',', self::SORTABLE)],
            'sort_order' => ['sometimes', 'integer', 'in:1,-1'],
        ];
    }
}
