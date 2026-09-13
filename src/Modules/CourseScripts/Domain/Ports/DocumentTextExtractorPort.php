<?php

declare(strict_types=1);

namespace Modules\CourseScripts\Domain\Ports;

use Modules\CourseScripts\Domain\Exceptions\EncryptedPdfException;
use Modules\CourseScripts\Domain\Exceptions\NoTextLayerException;
use Modules\CourseScripts\Domain\Exceptions\UnrecognisableIndexException;

/**
 * Plain text of an uploaded content file or style reference (FR-1b, FR-10).
 *
 * Unlike {@see IndexDocumentParserPort} it recovers no structure: a content
 * file is the author's notes, consumed as excerpts by the notes selector.
 */
interface DocumentTextExtractorPort
{
    /**
     * @throws EncryptedPdfException
     * @throws NoTextLayerException
     * @throws UnrecognisableIndexException when the type is unsupported
     */
    public function extract(string $absolutePath, string $mimeType): string;
}
