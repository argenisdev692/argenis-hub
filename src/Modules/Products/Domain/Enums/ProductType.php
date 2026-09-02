<?php

declare(strict_types=1);

namespace Modules\Products\Domain\Enums;

/**
 * Kind of billable catalog entry. `Course` is live/remote instructor-led
 * training billed by the hour; `VideoCourse` is recorded material.
 */
enum ProductType: string
{
    case Course = 'COURSE';
    case VideoCourse = 'VIDEO_COURSE';
    case Workshop = 'WORKSHOP';
    case Mentoring = 'MENTORING';

    /**
     * @return list<string>
     */
    public static function values(): array
    {
        return array_column(self::cases(), 'value');
    }
}
