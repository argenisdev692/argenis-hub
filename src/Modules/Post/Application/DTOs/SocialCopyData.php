<?php

declare(strict_types=1);

namespace Modules\Post\Application\DTOs;

use Spatie\LaravelData\Attributes\MapOutputName;
use Spatie\LaravelData\Data;
use Spatie\LaravelData\Mappers\SnakeCaseMapper;

/**
 * LinkedIn + Instagram/Facebook copy for one chosen topic/angle. IG and
 * Facebook share a single short adaptation (same caption), matching the
 * guide's "Adaptación corta para Instagram / Facebook" deliverable.
 */
#[MapOutputName(SnakeCaseMapper::class)]
final class SocialCopyData extends Data
{
    /**
     * @param  list<string>  $hashtags
     */
    public function __construct(
        public string $linkedinPost,
        public string $socialCaption,
        public array $hashtags,
    ) {}
}
