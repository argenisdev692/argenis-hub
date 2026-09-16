<?php

declare(strict_types=1);

namespace Modules\VideoEdits\Application\DTOs;

use Spatie\LaravelData\Attributes\MapInputName;
use Spatie\LaravelData\Attributes\MapOutputName;
use Spatie\LaravelData\Data;
use Spatie\LaravelData\Mappers\SnakeCaseMapper;

/**
 * The owner's answer to the cut review (`POST /{uuid}/review`).
 *
 * `approved_cut_ids` must be present even when empty: an empty list is the
 * explicit "keep everything" answer, and a request that forgot the field must
 * not be read as one. Whether the ids belong to this edit is checked by the
 * handler against the stored review.
 */
#[MapInputName(SnakeCaseMapper::class)]
#[MapOutputName(SnakeCaseMapper::class)]
final class ReviewVideoEditCutsData extends Data
{
    /**
     * @param  list<string>  $approvedCutIds
     */
    public function __construct(
        public array $approvedCutIds = [],
    ) {}

    /**
     * @return array<string, list<mixed>>
     */
    public static function rules(): array
    {
        return [
            'approved_cut_ids' => ['present', 'array', 'max:500'],
            'approved_cut_ids.*' => ['required', 'string', 'uuid', 'distinct'],
        ];
    }
}
