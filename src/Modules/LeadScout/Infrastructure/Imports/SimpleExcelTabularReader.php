<?php

declare(strict_types=1);

namespace Modules\LeadScout\Infrastructure\Imports;

use InvalidArgumentException;
use Modules\LeadScout\Domain\Ports\TabularFileReaderPort;
use Spatie\SimpleExcel\SimpleExcelReader;

final readonly class SimpleExcelTabularReader implements TabularFileReaderPort
{
    private const array EXTENSIONS = ['csv', 'xlsx'];

    public function rows(string $file, int $maxBytes): iterable
    {
        if (! is_file($file) || ! is_readable($file)) {
            throw new InvalidArgumentException("File not found or unreadable: {$file}.");
        }

        if ((filesize($file) ?: 0) > $maxBytes) {
            throw new InvalidArgumentException('File exceeds the '.intdiv($maxBytes, 1024 * 1024).' MB limit.');
        }

        $extension = mb_strtolower((string) pathinfo($file, PATHINFO_EXTENSION));

        if (! in_array($extension, self::EXTENSIONS, true)) {
            throw new InvalidArgumentException("Unsupported extension '{$extension}': use CSV or XLSX.");
        }

        foreach (SimpleExcelReader::create($file)->headersToSnakeCase()->getRows() as $row) {
            yield is_array($row) ? $row : [];
        }
    }
}
