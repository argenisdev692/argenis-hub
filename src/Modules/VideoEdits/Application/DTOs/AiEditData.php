<?php

declare(strict_types=1);

namespace Modules\VideoEdits\Application\DTOs;

use Modules\VideoEdits\Infrastructure\Ai\AnalyzeVideoEditAgent;
use Spatie\LaravelData\Attributes\MapInputName;
use Spatie\LaravelData\Attributes\MapOutputName;
use Spatie\LaravelData\Data;
use Spatie\LaravelData\Mappers\SnakeCaseMapper;

/**
 * V3 AI edit request block (US-12), shaped like `silence_removal` and
 * `speech_cleanup` so all three switches read the same.
 *
 * `consented` is required to be true and is NOT a formality: it is the record
 * that the user agreed to send their transcript and script to an external
 * provider (decision R9). Nothing is transmitted without it.
 *
 * `instructions` carries the user's own editorial prompt. It reaches the model
 * as data, never as rules (US-12) — see {@see AnalyzeVideoEditAgent}.
 */
#[MapInputName(SnakeCaseMapper::class)]
#[MapOutputName(SnakeCaseMapper::class)]
final class AiEditData extends Data
{
    public function __construct(
        public bool $enabled,
        public bool $consented = false,
        public ?ScriptUploadData $script = null,
        public ?string $instructions = null,
        public ?int $targetDurationMinutes = null,
    ) {}
}
