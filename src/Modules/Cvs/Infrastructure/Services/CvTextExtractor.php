<?php

declare(strict_types=1);

namespace Modules\Cvs\Infrastructure\Services;

use Modules\Cvs\Domain\Enums\CvFileType;
use Modules\Cvs\Domain\Ports\CvTextExtractorPort;

/**
 * Extracts plain text from uploaded CV files. MD is read immediately;
 * PDF text extraction is deferred to Module 2 (no PDF parser dependency yet).
 */
final readonly class CvTextExtractor implements CvTextExtractorPort
{
    private const int MAX_LENGTH = 500_000;

    public function extract(CvFileType $type, \SplFileInfo $file): ?string
    {
        return match ($type) {
            CvFileType::Md => $this->readMarkdown($file),
            CvFileType::Pdf => null, // no PDF parser until Module 2
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
}
