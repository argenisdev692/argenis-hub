<?php

declare(strict_types=1);

namespace Modules\CourseScripts\Infrastructure\Parsing;

use Modules\CourseScripts\Domain\Exceptions\UnrecognisableIndexException;
use Modules\CourseScripts\Domain\Ports\DocumentTextExtractorPort;

/**
 * Markdown is read as-is; a PDF goes through {@see PdfIndexParser::extractText()}
 * so `smalot/pdfparser` stays imported by exactly one class (research R1.2)
 * and content files get the same encrypted / no-text-layer diagnostics as an
 * index.
 */
final readonly class DocumentTextExtractor implements DocumentTextExtractorPort
{
    private const array TEXT_MIME_TYPES = ['text/markdown', 'text/x-markdown', 'text/plain'];

    public function __construct(
        private PdfIndexParser $pdf,
    ) {}

    public function extract(string $absolutePath, string $mimeType): string
    {
        $mimeType = strtolower($mimeType);

        if ($this->pdf->supports($mimeType)) {
            return $this->pdf->extractText($absolutePath);
        }

        if (! in_array($mimeType, self::TEXT_MIME_TYPES, true)) {
            throw new UnrecognisableIndexException([
                sprintf('Unsupported file type "%s". Upload a Markdown or PDF file.', $mimeType),
            ]);
        }

        $contents = (string) @file_get_contents($absolutePath);

        // Notes exported from older editors arrive as Windows-1252; the writer
        // and the PDF renderer both need valid UTF-8.
        return mb_check_encoding($contents, 'UTF-8')
            ? $contents
            : mb_convert_encoding($contents, 'UTF-8', 'Windows-1252');
    }
}
