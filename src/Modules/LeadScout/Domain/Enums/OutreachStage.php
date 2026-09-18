<?php

declare(strict_types=1);

namespace Modules\LeadScout\Domain\Enums;

/**
 * Full outreach lifecycle (spec US-6 CA-1, FR-42): every transition appends
 * a row to `scout_outreach_stage_events`, including round trips.
 */
enum OutreachStage: string
{
    case Draft = 'draft';
    case Ready = 'ready';
    case Sent = 'sent';
    case Replied = 'replied';
    case Positive = 'positive';
    case Call = 'call';
    case Trial = 'trial';
    case Won = 'won';
    case Recurrent = 'recurrent';
    case Lost = 'lost';
    case DoNotContact = 'do_not_contact';

    /**
     * @return list<string>
     */
    public static function values(): array
    {
        return array_map(static fn (self $case): string => $case->value, self::cases());
    }
}
