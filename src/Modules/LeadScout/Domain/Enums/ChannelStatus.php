<?php

declare(strict_types=1);

namespace Modules\LeadScout\Domain\Enums;

enum ChannelStatus: string
{
    case Active = 'active';
    case Broken = 'broken';
    case Used = 'used';

    /**
     * @return list<string>
     */
    public static function values(): array
    {
        return array_map(static fn (self $case): string => $case->value, self::cases());
    }
}
