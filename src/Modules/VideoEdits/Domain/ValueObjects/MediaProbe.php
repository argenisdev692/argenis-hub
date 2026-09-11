<?php

declare(strict_types=1);

namespace Modules\VideoEdits\Domain\ValueObjects;

use InvalidArgumentException;

/**
 * What FFprobe reported about one media file, in framework-free terms.
 */
final readonly class MediaProbe
{
    public function __construct(
        public int $durationMs,
        public string $container,
        public bool $hasVideo,
        public bool $hasAudio,
        public ?int $width = null,
        public ?int $height = null,
        public ?float $frameRate = null,
        public ?string $videoCodec = null,
        public ?string $audioCodec = null,
    ) {
        if ($durationMs < 0) {
            throw new InvalidArgumentException('A media duration cannot be negative.');
        }
    }

    /**
     * FFprobe reports families such as `mov,mp4,m4a,3gp,3g2,mj2` or `matroska,webm`;
     * the file is accepted when any token is on the allow-list (OWASP §8).
     *
     * @param  list<string>  $allowedContainers
     */
    public function isAllowedContainer(array $allowedContainers): bool
    {
        $tokens = array_map(trim(...), explode(',', strtolower($this->container)));

        return array_intersect($tokens, $allowedContainers) !== [];
    }
}
