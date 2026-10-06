<?php

declare(strict_types=1);

namespace Modules\VideoEdits\Application\DTOs;

use Spatie\LaravelData\Attributes\MapOutputName;
use Spatie\LaravelData\Data;
use Spatie\LaravelData\Mappers\SnakeCaseMapper;

/**
 * A saved block of standing editorial notes the owner can start an edit from.
 *
 * Read-only and config-backed: there is no profile to create, edit or delete,
 * because these are editorial policy rather than user data. What the owner does
 * with one is theirs — picking `supervisor_lms` fills `ai_edit.instructions`,
 * which they then edit freely, and that edited text is what gets stored on the
 * edit. The profile itself never travels to the model.
 */
#[MapOutputName(SnakeCaseMapper::class)]
final class InstructionProfileData extends Data
{
    public function __construct(
        public string $key,
        public string $name,
        public string $instructions,
    ) {}
}
