<?php

declare(strict_types=1);

namespace Modules\SocialMedia\Infrastructure\Broadcasting;

use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * Single owner of "tell the wizard where the generation is".
 *
 * The generator, the evaluator, the asset renderer and the loop job all report
 * progress; each used to carry its own copy of the same null-causer guard and
 * try/catch. Broadcasting is an optional nicety — a dead Reverb connection
 * must never fail a paid generation — so every failure is swallowed with a
 * warning here, once.
 */
final readonly class SocialMediaProgressNotifier
{
    public function notify(
        ?object $causer,
        string $contentUuid,
        string $stage,
        string $message,
        int $progress,
        int $iteration = 1,
    ): void {
        if (! $causer instanceof Authenticatable) {
            return;
        }

        try {
            broadcast(new SocialMediaAiGenerationProgress(
                userId: (int) $causer->getAuthIdentifier(),
                contentUuid: $contentUuid,
                stage: $stage,
                message: $message,
                progress: $progress,
                iteration: $iteration,
            ));
        } catch (Throwable $exception) {
            Log::warning('social_media.ai.broadcast_failed', ['message' => $exception->getMessage()]);
        }
    }
}
