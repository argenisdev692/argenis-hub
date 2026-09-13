<?php

declare(strict_types=1);

namespace Modules\VideoEdits\Application\DTOs;

use Spatie\LaravelData\Attributes\MapInputName;
use Spatie\LaravelData\Data;
use Spatie\LaravelData\Mappers\SnakeCaseMapper;

/**
 * The row selection the history table sends to `POST /bulk-delete`.
 */
#[MapInputName(SnakeCaseMapper::class)]
final class BulkDeleteVideoEditsData extends Data
{
    /**
     * @param  list<string>  $uuids
     */
    public function __construct(
        public array $uuids,
    ) {}

    /**
     * @return array<string, list<mixed>>
     */
    public static function rules(): array
    {
        return [
            'uuids' => ['required', 'array', 'min:1', 'max:100'],
            'uuids.*' => ['required', 'string', 'uuid', 'distinct'],
        ];
    }
}
