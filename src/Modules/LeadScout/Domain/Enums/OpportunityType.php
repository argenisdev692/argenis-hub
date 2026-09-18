<?php

declare(strict_types=1);

namespace Modules\LeadScout\Domain\Enums;

/**
 * Several opportunities can hang off one contact (trial first, then retainer).
 */
enum OpportunityType: string
{
    case Trial = 'trial';
    case Project = 'project';
    case Retainer = 'retainer';
    case StaffAugmentation = 'staff_augmentation';

    /**
     * @return list<string>
     */
    public static function values(): array
    {
        return array_map(static fn (self $case): string => $case->value, self::cases());
    }
}
