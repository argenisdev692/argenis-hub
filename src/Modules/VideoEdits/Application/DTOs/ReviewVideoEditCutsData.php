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
            // A unified review can carry every silence of a long recording on
            // top of the speech and AI cuts; 500 was sized for AI-only reviews
            // and would reject a legitimate answer to a 40-minute video.
            'approved_cut_ids' => ['present', 'array', 'max:2000'],
            'approved_cut_ids.*' => ['required', 'string', 'uuid', 'distinct'],
        ];
    }
}
