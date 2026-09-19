<?php

declare(strict_types=1);

namespace Modules\Cvs\Infrastructure\Services;

use Modules\Cvs\Domain\Enums\CvFileType;
use Modules\Cvs\Domain\Ports\CvTextExtractorPort;
use PrinsFrank\PdfParser\PdfParser;
use Throwable;

/**
 * Extracts plain text from uploaded CV files. MD is read immediately;
 * PDF goes through `prinsfrank/pdfparser` (PHP-native, no Poppler).
 * Scanned/image-only PDFs yield no text and return null — reported
 * downstream as an extraction failure, never as empty content.
 */
final readonly class CvTextExtractor implements CvTextExtractorPort
{
    private const int MAX_LENGTH = 500_000;

    public function extract(CvFileType $type, \SplFileInfo $file): ?string
    {
        return match ($type) {
            CvFileType::Md => $this->readMarkdown($file),
            CvFileType::Pdf => $this->readPdf($file),
        };
    }

    private function readMarkdown(\SplFileInfo $file): string
    {
        if (! $file->isFile() || ! $file->isReadable()) {
            return '';
        }

        return (string) file_get_contents($file->getRealPath() ?: $file->getPathname())
            |> trim(...)
            |> (fn (string $text): string => mb_substr($text, 0, self::MAX_LENGTH));
    }

    private function readPdf(\SplFileInfo $file): ?string
    {
        $path = $file->getRealPath() ?: $file->getPathname();

        if (! $file->isFile() || ! $file->isReadable()) {
            return null;
        }

        try {
            $text = (new PdfParser)->parseFile($path)->getText();
        } catch (Throwable) {
            return null;
        }

        $normalized = $text
            |> trim(...)
            |> (fn (string $t): string => (string) preg_replace('/[ \t]+/', ' ', $t))
            |> trim(...)
            |> (fn (string $t): string => (string) preg_replace('/\n{3,}/', "\n\n", $t))
            |> trim(...)
            |> (fn (string $t): string => mb_substr($t, 0, self::MAX_LENGTH));

        return trim($normalized) === '' ? null : $normalized;
    }
}
