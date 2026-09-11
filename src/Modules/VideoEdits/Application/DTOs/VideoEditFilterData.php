<?php

declare(strict_types=1);

namespace Modules\VideoEdits\Application\DTOs;

use Illuminate\Validation\Rule;
use Modules\VideoEdits\Domain\Enums\VideoEditMode;
use Modules\VideoEdits\Domain\Enums\VideoEditStatus;
use Spatie\LaravelData\Attributes\MapInputName;
use Spatie\LaravelData\Data;
use Spatie\LaravelData\Mappers\SnakeCaseMapper;

/**
 * History filters (E1 · US-6). Drafts are never listed, so `draft` is not a
 * selectable status.
 */
#[MapInputName(SnakeCaseMapper::class)]
final class VideoEditFilterData extends Data
{
    public function __construct(
        public ?VideoEditStatus $status = null,
        public ?VideoEditMode $mode = null,
        public int $page = 1,
        public int $perPage = 15,
    ) {}

    /**
     * @return array<string, list<mixed>>
     */
    public static function rules(): array
    {
        $listedStatuses = array_values(array_map(
            static fn (VideoEditStatus $status): string => $status->value,
            array_filter(VideoEditStatus::cases(), static fn (VideoEditStatus $status): bool => $status->isListed()),
        ));

        return [
            'status' => ['nullable', 'string', Rule::in($listedStatuses)],
            'mode' => ['nullable', 'string', Rule::in(array_column(VideoEditMode::cases(), 'value'))],
            'page' => ['integer', 'min:1'],
            'per_page' => ['integer', 'min:1', 'max:100'],
        ];
    }
}
