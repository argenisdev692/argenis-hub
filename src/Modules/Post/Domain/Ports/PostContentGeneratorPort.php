<?php

declare(strict_types=1);

namespace Modules\Post\Domain\Ports;

use Modules\Post\Application\Commands\GeneratePostContentHandler;
use Modules\Post\Application\DTOs\GeneratePostContentData;
use Modules\Post\Application\DTOs\PostContentDraftData;

/**
 * The writing half of the quality gate: produces ONE text-only blog draft per
 * call — no scores, no artwork.
 *
 * Scoring is {@see PostContentEvaluatorPort} (a different model, so the gate
 * is independent) and the cover is {@see PostCoverImageRendererPort} (invoked
 * once, after the loop, so rejected drafts cost no images). The loop that
 * calls all three is
 * {@see GeneratePostContentHandler}.
 *
 * Read-only — the caller decides whether/when to persist the result via
 * CreatePostHandler / UpdatePostHandler.
 */
interface PostContentGeneratorPort
{
    /**
     * @param  list<array{score: string, current: int, target: int, gap: int, explanation: string}>  $previousWeaknesses
     */
    public function generate(
        GeneratePostContentData $data,
        int $iteration = 1,
        array $previousWeaknesses = [],
        ?object $causer = null,
    ): PostContentDraftData;
}
