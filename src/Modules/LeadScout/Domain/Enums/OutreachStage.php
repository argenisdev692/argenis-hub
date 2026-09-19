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
     * Manual funnel transitions (spec US-6). Replies arrive through
     * `sent → positive|lost|do_not_contact` in the reply flow instead.
     */
    public function canMoveTo(self $to): bool
    {
        return in_array($to, match ($this) {
            self::Draft => [self::Draft, self::Ready],
            self::Ready => [self::Draft, self::Ready, self::Sent],
            self::Sent => [self::Sent, self::Call, self::Lost],
            self::Replied => [self::Positive, self::Lost],
            self::Positive => [self::Call, self::Lost],
            self::Call => [self::Trial, self::Lost],
            self::Trial => [self::Won, self::Lost],
            self::Won => [self::Recurrent, self::Lost],
            self::Recurrent => [self::Recurrent],
            self::Lost, self::DoNotContact => [],
        }, true);
    }

    /**
     * Stages that mean the company answered (spec US-6).
     *
     * @return list<self>
     */
    public static function respondedOutcomes(): array
    {
        return [self::Replied, ...self::positiveOutcomes()];
    }

    /**
     * Stages that count as a positive answer in the funnel (spec US-6).
     *
     * @return list<self>
     */
    public static function positiveOutcomes(): array
    {
        return [self::Positive, self::Call, self::Trial, self::Won, self::Recurrent];
    }

    /**
     * @return list<string>
     */
    public static function values(): array
    {
        return array_map(static fn (self $case): string => $case->value, self::cases());
    }
}
