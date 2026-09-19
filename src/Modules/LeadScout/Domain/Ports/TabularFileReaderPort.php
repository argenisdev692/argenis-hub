<?php

declare(strict_types=1);

namespace Modules\LeadScout\Domain\Ports;

use InvalidArgumentException;

/**
 * Row source for operator-supplied lists (CSV / XLSX).
 */
interface TabularFileReaderPort
{
    /**
     * Rows keyed by snake_case header.
     *
     * @return iterable<int, array<string, mixed>>
     *
     * @throws InvalidArgumentException when the file is missing, too large or of an unsupported type
     */
    public function rows(string $file, int $maxBytes): iterable;
}
