<?php

declare(strict_types=1);

namespace Modules\LeadScout\Domain\Enums;

/**
 * A `blocked` answer is a response, not an obstacle: it is recorded and the
 * ladder never escalates it to another provider (spec FR-13).
 */
enum FetchStatus: string
{
    case Ok = 'ok';
    case Blocked = 'blocked';
    case Failed = 'failed';
    case SkippedRobots = 'skipped_robots';
    case ProxyMismatch = 'proxy_mismatch';

    /**
     * @return list<string>
     */
    public static function values(): array
    {
        return array_map(static fn (self $case): string => $case->value, self::cases());
    }
}
