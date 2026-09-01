<?php

declare(strict_types=1);

namespace Modules\Campaigns\Application\DTOs;

use Spatie\LaravelData\Attributes\MapOutputName;
use Spatie\LaravelData\Data;
use Spatie\LaravelData\Mappers\SnakeCaseMapper;

/**
 * The writer's short visual brief for one asset — a 2-5 word title and one
 * sentence of subject matter. Deliberately colour-free: the brand palette is
 * applied deterministically at render time, so the model never picks the
 * look and every campaign image stays on-brand.
 *
 * A concept is CHEAP (it is just text on the draft). The billed artwork is
 * produced from it once, by the asset renderer, for the attempt that wins the
 * quality loop.
 */
#[MapOutputName(SnakeCaseMapper::class)]
final class CampaignImageConceptData extends Data
{
    public function __construct(
        public string $title,
        public string $visual,
    ) {}
}
