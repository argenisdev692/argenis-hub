<?php

declare(strict_types=1);

namespace Modules\VideoEdits\Application\DTOs;

use Spatie\LaravelData\Attributes\DataCollectionOf;
use Spatie\LaravelData\Attributes\MapOutputName;
use Spatie\LaravelData\Data;
use Spatie\LaravelData\Mappers\SnakeCaseMapper;

/**
 * Create-draft response (E2): the draft plus one upload target per source.
 */
#[MapOutputName(SnakeCaseMapper::class)]
final class CreatedVideoEditData extends Data
{
    /**
     * @param  list<UploadTargetData>  $uploads
     */
    public function __construct(
        public VideoEditDetailData $edit,
        #[DataCollectionOf(UploadTargetData::class)]
        public array $uploads,
    ) {}
}
