<?php

declare(strict_types=1);

namespace Modules\Post\Application\DTOs;

use Modules\Post\Domain\Ports\PostCoverImageRendererPort;
use Spatie\LaravelData\Attributes\MapOutputName;
use Spatie\LaravelData\Data;
use Spatie\LaravelData\Mappers\SnakeCaseMapper;

/**
 * The outcome of the single billed render pass — see
 * {@see PostCoverImageRendererPort}.
 *
 * `prompts` is populated in every image mode (the user can always render the
 * cover elsewhere with the exact palette-locked text the pipeline would have
 * used); `path` / `url` are null under `none`, and null again when the image
 * provider failed on a draft that is otherwise complete.
 */
#[MapOutputName(SnakeCaseMapper::class)]
final class RenderedCoverImageData extends Data
{
    /**
     * @param  array{background: string, content: string}  $prompts
     */
    public function __construct(
        public array $prompts,
        public ?string $path = null,
        public ?string $url = null,
    ) {}
}
