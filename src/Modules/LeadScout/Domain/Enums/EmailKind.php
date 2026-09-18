<?php

declare(strict_types=1);

namespace Modules\LeadScout\Domain\Enums;

/**
 * Published-email kind (spec FR-24): nominative addresses stay person-bound
 * (opposition-capable), generic company mailboxes do not attach to a person.
 */
enum EmailKind: string
{
    case Nominative = 'nominative';
    case Generic = 'generic';

    /**
     * @return list<string>
     */
    public static function values(): array
    {
        return array_map(static fn (self $case): string => $case->value, self::cases());
    }
}
