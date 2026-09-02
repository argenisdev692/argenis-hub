<?php

declare(strict_types=1);

namespace Modules\Cvs\Domain\Enums;

/**
 * CV niche for Module 1. Fullstack is the primary developer track;
 * Other covers non-niche CVs that Module 2 will process via RAG.
 */
enum CvNiche: string
{
    case Fullstack = 'fullstack';
    case Other = 'other';

    /**
     * @return list<string>
     */
    public static function values(): array
    {
        return array_column(self::cases(), 'value');
    }
}
