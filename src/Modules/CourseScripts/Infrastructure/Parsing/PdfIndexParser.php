<?php

declare(strict_types=1);

namespace Modules\CourseScripts\Infrastructure\Parsing;

use Modules\CourseScripts\Domain\Exceptions\EncryptedPdfException;
use Modules\CourseScripts\Domain\Exceptions\NoTextLayerException;
use Modules\CourseScripts\Domain\Ports\IndexDocumentParserPort;
use Modules\CourseScripts\Domain\ValueObjects\ParsedIndex;
use Smalot\PdfParser\Parser as SmalotParser;
use Throwable;

/**
 * Extracts text from an uploaded PDF index and hands it to the Markdown
 * grammar (spec FR-1; clarify DEC-1).
 *
 * **This is the only class in the application that imports `smalot/pdfparser`**
 * (research R1.2). The package is under self-declared limited maintenance, so
 * confining it to one file behind {@see IndexDocumentParserPort} keeps the
 * swap cost at one class if it is ever abandoned.
 *
 * It owns no grammar of its own. Recovering structure lives entirely in
 * {@see MarkdownIndexParser}, which parses semantic anchors rather than Markdown
 * syntax precisely so that extracted PDF text is a valid input to it. Two
 * grammars would have meant two behaviours and no way to honour the spec's
 * Markdown/PDF parity criterion.
 *
 * Extraction is local and in-process: the index never leaves this
 * infrastructure, which is the privacy half of the decision that chose a PHP
 * parser over an external document service.
 */
final readonly class PdfIndexParser implements IndexDocumentParserPort
{
    public function __construct(
        private MarkdownIndexParser $grammar,
        private SmalotParser $parser,
        private int $minimumTextLength = 200,
    ) {}

    public function supports(string $mimeType): bool
    {
        return strtolower($mimeType) === 'application/pdf';
    }

    public function parse(string $absolutePath, string $mimeType): ParsedIndex
    {
        return $this->grammar->parseText($this->extractText($absolutePath));
    }

    /**
     * @throws EncryptedPdfException
     * @throws NoTextLayerException
     */
    private function extractText(string $absolutePath): string
    {
        try {
            $text = $this->parser->parseFile($absolutePath)->getText();
        } catch (Throwable $exception) {
            // Upstream states secured documents are unsupported and signals it
            // by throwing. Translating it here gives the author an actionable
            // message ("remove the password") instead of a generic parse error.
            if ($this->looksEncrypted($exception->getMessage())) {
                throw new EncryptedPdfException;
            }

            throw $exception;
        }

        $length = mb_strlen(trim($text));

        if ($length < $this->minimumTextLength) {
            // A scan or a raster print-to-PDF. Not hypothetical: the project's
            // own `CLAUDE-PARA-USUARIOS-RESUMEN.pdf` is 43 such pages and
            // yields one character each. There is no OCR in scope, so refusing
            // with the real cause beats persisting an empty course (FR-7).
            throw new NoTextLayerException($length);
        }

        return $text;
    }

    private function looksEncrypted(string $message): bool
    {
        $needles = ['secured', 'encrypt', 'password', 'permission'];
        $message = strtolower($message);

        foreach ($needles as $needle) {
            if (str_contains($message, $needle)) {
                return true;
            }
        }

        return false;
    }
}
