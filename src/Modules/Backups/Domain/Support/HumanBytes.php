<?php

declare(strict_types=1);

namespace Modules\Backups\Domain\Support;

/**
 * Formats a byte count as a human-readable size (`1.5 MB`). Shared by the admin
 * DTO and the export transformer so the panel and the reports never disagree on
 * how an archive size is rendered.
 */
final readonly class HumanBytes
{
    /** @var list<string> */
    private const array UNITS = ['B', 'KB', 'MB', 'GB', 'TB', 'PB'];

    #[\NoDiscard]
    public static function format(?int $bytes): string
    {
        if ($bytes === null) {
            return '—';
        }

        if ($bytes <= 0) {
            return '0 B';
        }

        $power = min((int) floor(log($bytes, 1024)), count(self::UNITS) - 1);

        return sprintf('%.1f %s', $bytes / (1024 ** $power), self::UNITS[$power]);
    }
}
