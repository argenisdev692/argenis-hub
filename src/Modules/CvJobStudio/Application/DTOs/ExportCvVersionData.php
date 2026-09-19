<?php

declare(strict_types=1);

namespace Modules\CvJobStudio\Application\DTOs;

use Spatie\LaravelData\Attributes\MapInputName;
use Spatie\LaravelData\Data;
use Spatie\LaravelData\Mappers\SnakeCaseMapper;

/** CV version export input: document format. */
#[MapInputName(SnakeCaseMapper::class)]
final class ExportCvVersionData extends Data
{
    public function __construct(public readonly string $format = 'pdf') {}

    /** @return array<string, mixed> */
    public static function rules(): array
    {
        return ['format' => ['sometimes', 'string', 'in:docx,pdf']];
    }
}
