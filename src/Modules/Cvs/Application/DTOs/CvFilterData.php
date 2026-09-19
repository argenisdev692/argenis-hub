<?php

declare(strict_types=1);

namespace Modules\Cvs\Application\DTOs;

use Modules\Cvs\Domain\Enums\CvNiche;
use Modules\Cvs\Domain\Enums\CvSource;
use Shared\Application\DTOs\SoftDeleteFilterData;
use Spatie\LaravelData\Attributes\MapInputName;
use Spatie\LaravelData\Attributes\MapOutputName;
use Spatie\LaravelData\Mappers\SnakeCaseMapper;

/**
 * List/export filter. Soft-delete `status` is all|active|suspended; optional
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
        public ?CvSource $source = null,
        public ?string $language = null,
    ) {
        parent::__construct($search, $status, $dateFrom, $dateTo);
    }

    /**
     * The comments below are published as these query parameters' descriptions
     * in `api.json` — write them for an API consumer, not for the next
     * maintainer, whose notes belong in this docblock.
     *
     * `search` matches the CV title, its original filename or its niche; the
     * date window narrows on `created_at`. Every result is scoped to the
     * authenticated user's own CVs regardless of the filters.
     *
     * @return array<string, mixed>
     */
    public static function rules(): array
    {
        return [
            ...self::baseRules(),
            // Lifecycle. `active` (and omitting this) returns live CVs;
            // `suspended` returns only the soft-deleted ones; `all` returns both.
            'status' => ['nullable', 'string', 'in:all,active,suspended'],
            // The specialisation a CV is written for. Omit for any niche.
            'niche' => ['nullable', 'string', 'in:'.implode(',', CvNiche::values())],
            // Which pipeline produced the row. Omit for any source.
            'source' => ['nullable', 'string', 'in:'.implode(',', CvSource::values())],
            // Version language (en, es, pt-PT). Omit for any language.
            'language' => ['nullable', 'string', 'max:8'],
        ];
    }
}
