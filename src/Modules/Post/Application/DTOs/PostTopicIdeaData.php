<?php

declare(strict_types=1);

namespace Modules\Post\Application\DTOs;

use Modules\Post\Domain\Ports\PostTopicIdeatorPort;
use Spatie\LaravelData\Attributes\MapOutputName;
use Spatie\LaravelData\Data;
use Spatie\LaravelData\Mappers\SnakeCaseMapper;

/**
 * One candidate topic returned by {@see PostTopicIdeatorPort}.
 */
#[MapOutputName(SnakeCaseMapper::class)]
final class PostTopicIdeaData extends Data
{
    public function __construct(
        public string $title,
        public string $angle,
        public string $hook,
        public int $estimatedVirality,
        public int $estimatedRoi,
        public int $eeatPotential,
        public string $whyItWorks,
        public string $keyTrend,
    ) {}
}
