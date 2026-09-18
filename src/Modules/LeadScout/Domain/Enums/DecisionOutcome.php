<?php

declare(strict_types=1);

namespace Modules\LeadScout\Domain\Enums;

/**
 * Decision-rule branch shown once the sample is reached (spec US-6 CA-3,
 * FR-33). Below the sample the metrics read `inconclusive`, never «failure».
 */
enum DecisionOutcome: string
{
    case Scale = 'scale';
    case Iterate = 'iterate';
    case Stop = 'stop';
    case Inconclusive = 'inconclusive';

    /**
     * @return list<string>
     */
    public static function values(): array
    {
        return array_map(static fn (self $case): string => $case->value, self::cases());
    }
}
