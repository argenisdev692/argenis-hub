<?php

declare(strict_types=1);

namespace Modules\Cvs\Domain\Enums;

/**
 * Which pipeline produced the CV row: a direct `upload`, a Studio version
 * promoted back (`studio`), or text supplied through the agent chat (`chat`).
 */
enum CvSource: string
{
    case Upload = 'upload';
    case Studio = 'studio';
    case Chat = 'chat';

    /**
     * @return list<string>
     */
    public static function values(): array
    {
        return array_column(self::cases(), 'value');
    }
}
