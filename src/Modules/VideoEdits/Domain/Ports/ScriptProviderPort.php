<?php

declare(strict_types=1);

namespace Modules\VideoEdits\Domain\Ports;

use Modules\VideoEdits\Domain\ValueObjects\ScriptDocument;

/**
 * The script attached to an edit, already reduced to text (EX-9).
 *
 * Separate from {@see ScriptTextExtractorPort}: that one parses a file, this
 * one answers "what script does this edit have, if any". Returns null when the
 * user attached none — an AI edit without a script is valid and still finds
 * pause markers and retakes.
 */
interface ScriptProviderPort
{
    public function forEdit(int $videoEditId): ?ScriptDocument;
}
