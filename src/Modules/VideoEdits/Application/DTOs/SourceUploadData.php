<?php

declare(strict_types=1);

namespace Modules\VideoEdits\Application\DTOs;

use Spatie\LaravelData\Attributes\MapInputName;
use Spatie\LaravelData\Attributes\MapOutputName;
use Spatie\LaravelData\Data;
use Spatie\LaravelData\Mappers\SnakeCaseMapper;

/**
 * One clip the browser is about to upload directly to storage. Declared values
 * are untrusted: size is re-checked against the stored object at submit and the
 * container is verified by FFprobe before processing (OWASP §8).
 */
#[MapInputName(SnakeCaseMapper::class)]
#[MapOutputName(SnakeCaseMapper::class)]
final class SourceUploadData extends Data
{
    public function __construct(
        public int $position,
        public string $fileName,
        public string $mimeType,
        public int $sizeBytes,
    ) {}

    public function extension(): string
    {
        return $this->fileName
            |> (fn (string $name): string => pathinfo($name, PATHINFO_EXTENSION))
            |> strtolower(...);
    }
}
