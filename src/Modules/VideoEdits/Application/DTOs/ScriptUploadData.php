<?php

declare(strict_types=1);

namespace Modules\VideoEdits\Application\DTOs;

use Spatie\LaravelData\Attributes\MapInputName;
use Spatie\LaravelData\Attributes\MapOutputName;
use Spatie\LaravelData\Data;
use Spatie\LaravelData\Mappers\SnakeCaseMapper;

/**
 * The `.md` / `.pdf` script the user attaches to an AI edit (EX-9).
 *
 * Declared like a video source and uploaded the same way — straight to storage
 * with a system-issued key — so a script never travels through PHP and the
 * client can never choose where it lands (FR-19).
 */
#[MapInputName(SnakeCaseMapper::class)]
#[MapOutputName(SnakeCaseMapper::class)]
final class ScriptUploadData extends Data
{
    public function __construct(
        public string $fileName,
        public string $mimeType,
        public int $sizeBytes,
    ) {}

    public function extension(): string
    {
        return mb_strtolower(pathinfo($this->fileName, PATHINFO_EXTENSION));
    }
}
