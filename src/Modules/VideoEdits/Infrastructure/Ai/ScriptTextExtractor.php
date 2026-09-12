<?php

declare(strict_types=1);

namespace Modules\VideoEdits\Infrastructure\Ai;

use Modules\VideoEdits\Domain\Exceptions\ScriptUnreadableException;
use Modules\VideoEdits\Domain\Ports\ScriptTextExtractorPort;
use Modules\VideoEdits\Domain\ValueObjects\ScriptDocument;
use Smalot\PdfParser\Parser as PdfParser;
use Throwable;

/**
 * Reads a `.md` or `.pdf` script into plain text (US-12, EX-9).
 *
 * Only the words leave this class. A PDF is a container that can carry embedded
 * files, JavaScript and metadata; the model needs none of it, so nothing but
 * extracted text is passed on or stored.
 */
final readonly class ScriptTextExtractor implements ScriptTextExtractorPort
{
    public function __construct(private PdfParser $pdf) {}

    public function extract(string $localPath, string $originalName): ScriptDocument
    {
        $text = str_ends_with(mb_strtolower($originalName), '.pdf')
            ? $this->fromPdf($localPath, $originalName)
            : $this->fromMarkdown($localPath, $originalName);

        $normalized = self::normalizeWhitespace($text);

        if (trim($normalized) === '') {
            throw ScriptUnreadableException::noExtractableText($originalName);
        }

        return new ScriptDocument($originalName, $normalized);
    }

    private function fromPdf(string $localPath, string $originalName): string
    {
        try {
            return $this->pdf->parseFile($localPath)->getText();
        } catch (Throwable) {
            // A scanned script parses without error but yields nothing, and a
            // corrupt one throws; both mean the same thing to the user.
            throw ScriptUnreadableException::noExtractableText($originalName);
        }
    }

    private function fromMarkdown(string $localPath, string $originalName): string
    {
        $contents = @file_get_contents($localPath);

        if ($contents === false) {
            throw ScriptUnreadableException::noExtractableText($originalName);
        }

        // Markdown reaches the model as-is: headings and bullets are exactly
        // how a script states its sections and their time budgets, which is
        // what the topic audit reads.
        return $contents;
    }

    /**
     * PDF extraction leaves ragged spacing that would inflate the token count
     * without adding meaning.
     */
    private static function normalizeWhitespace(string $text): string
    {
        return trim(
            preg_replace('/\n{3,}/', "\n\n", preg_replace('/[ \t]+/', ' ', $text) ?? $text) ?? $text,
        );
    }
}
