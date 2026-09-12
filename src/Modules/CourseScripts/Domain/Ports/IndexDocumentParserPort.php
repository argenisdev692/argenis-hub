<?php

declare(strict_types=1);

namespace Modules\CourseScripts\Domain\Ports;

use Modules\CourseScripts\Domain\Exceptions\EncryptedPdfException;
use Modules\CourseScripts\Domain\Exceptions\NoTextLayerException;
use Modules\CourseScripts\Domain\ValueObjects\ParsedIndex;

/**
 * Turns an uploaded course index into structure (spec FR-1 … FR-4).
 *
 * Two adapters sit behind this, selected by MIME: one reads Markdown directly,
 * the other extracts text from a PDF and hands it to the first. That reduction
 * is deliberate — one implementation owns the index grammar, so the PDF path
 * cannot drift from the Markdown path (plan §3.2).
 *
 * This port exists despite the project's warning against single-adapter ports
 * because it genuinely has two implementations plus a dispatcher, and because
 * it quarantines the module's only third-party dependency
 * (`smalot/pdfparser`, under self-declared limited maintenance — research RK-2)
 * behind one swappable interface.
 */
interface IndexDocumentParserPort
{
    /**
     * @param  string  $absolutePath  A readable local path; the caller is
     *                                responsible for fetching remote objects first.
     * @param  string  $mimeType  The REAL type, detected from the bytes with
     *                            ext-fileinfo — never the client-supplied header (FR-58).
     *
     * @throws EncryptedPdfException a secured PDF, which upstream does not support
     * @throws NoTextLayerException a scanned/image-only PDF; there is no OCR (R1.3)
     */
    public function parse(string $absolutePath, string $mimeType): ParsedIndex;

    public function supports(string $mimeType): bool;
}
