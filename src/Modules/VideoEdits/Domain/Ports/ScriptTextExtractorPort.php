<?php

declare(strict_types=1);

namespace Modules\VideoEdits\Domain\Ports;

use Modules\VideoEdits\Domain\Exceptions\ScriptUnreadableException;
use Modules\VideoEdits\Domain\ValueObjects\ScriptDocument;

/**
 * Turns an uploaded `.md` / `.pdf` script into plain text (US-12, EX-9).
 *
 * Only the words reach the AI: a PDF is a container that can carry scripts,
 * embedded files and metadata, none of which the model needs.
 */
interface ScriptTextExtractorPort
{
    /**
     * @throws ScriptUnreadableException
     */
    public function extract(string $localPath, string $originalName): ScriptDocument;
}
