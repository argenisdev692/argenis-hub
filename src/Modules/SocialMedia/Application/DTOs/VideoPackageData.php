<?php

declare(strict_types=1);

namespace Modules\SocialMedia\Application\DTOs;

use Spatie\LaravelData\Attributes\MapOutputName;
use Spatie\LaravelData\Data;
use Spatie\LaravelData\Mappers\SnakeCaseMapper;

/**
 * CapCut-ready short-form video package (9:16, stage-aware 15–30s): scene
 * timeline, clean VO script, target duration, UGC creative style, and a
 * trending-sound *type* + search term — never an invented track name.
 * Mirrors Post's ReelPackage shape without coupling the modules.
 */
#[MapOutputName(SnakeCaseMapper::class)]
final class VideoPackageData extends Data
{
    /**
     * @param  list<VideoSceneData>  $scenes
     */
    public function __construct(
        public array $scenes,
        public string $cleanScript,
        public string $soundSuggestion,
        public int $targetDurationSeconds = 15,
        public string $creativeStyle = 'ugc_native',
    ) {}
}
