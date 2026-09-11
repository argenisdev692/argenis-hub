<?php

declare(strict_types=1);

namespace Modules\VideoEdits\Domain\ValueObjects;

use InvalidArgumentException;

/**
 * Minimum silence length that gets removed (US-2). Bounds come from
 * `config/video-edit.php` (D4) and are passed in, keeping the domain framework-free.
 */
final readonly class SilenceThreshold
{
    private function __construct(
        public int $milliseconds,
    ) {}

    public static function fromSeconds(float $seconds, float $minimumSeconds, float $maximumSeconds): self
    {
        if ($seconds < $minimumSeconds || $seconds > $maximumSeconds) {
            throw new InvalidArgumentException(sprintf(
                'The silence threshold must be between %s and %s seconds.',
                $minimumSeconds,
                $maximumSeconds,
            ));
        }

        return new self((int) round($seconds * 1000));
    }

    public function seconds(): float
    {
        return $this->milliseconds / 1000;
    }
}
