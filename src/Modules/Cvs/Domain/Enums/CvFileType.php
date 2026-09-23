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
        return self::tryFromExtension($extension)
            ?? throw new \InvalidArgumentException("Unsupported CV extension: {$extension}");
    }

    public static function tryFromExtension(string $extension): ?self
    {
        return match (strtolower($extension)) {
            'pdf' => self::Pdf,
            'md', 'markdown' => self::Md,
            default => null,
        };
    }

    /**
     * Whether a content-sniffed MIME type is consistent with this type. The
     * extension alone is client-controlled, so a `.pdf` carrying plain text (or
     * a `.md` carrying a PDF) must be rejected rather than parsed as the wrong
     * format (OWASP §8).
     */
    public function acceptsMimeType(string $mimeType): bool
    {
        return match ($this) {
            self::Pdf => $mimeType === 'application/pdf',
            self::Md => in_array($mimeType, ['text/plain', 'text/markdown', 'text/x-markdown'], true),
        };
    }
}
