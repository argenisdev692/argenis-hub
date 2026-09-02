<?php

declare(strict_types=1);

namespace Modules\Cvs\Domain\Enums;

enum CvFileType: string
{
    case Pdf = 'pdf';
    case Md = 'md';

    /**
     * @return list<string>
     */
    public static function values(): array
    {
        return array_column(self::cases(), 'value');
    }

    public static function fromExtension(string $extension): self
    {
        $normalized = strtolower($extension);

        return match ($normalized) {
            'pdf' => self::Pdf,
            'md', 'markdown' => self::Md,
            default => throw new \InvalidArgumentException("Unsupported CV extension: {$extension}"),
        };
    }
}
