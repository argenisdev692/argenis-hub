<?php

declare(strict_types=1);

namespace Modules\Post\Infrastructure\Broadcasting;

use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Support\Facades\Log;
use Modules\SocialMedia\Infrastructure\Broadcasting\SocialMediaProgressNotifier;
use Throwable;

/**
 * Single owner of "tell the AI-assist panel where the generation is".
 *
 * The writer adapter, the judge, the cover renderer and the loop handler all
 * report progress; each would otherwise carry its own copy of the same
 * null-causer guard and try/catch. Broadcasting is an optional nicety — a dead
 * Reverb connection must never fail a paid generation — so every failure is
 * swallowed with a warning here, once.
 *
 * Mirrors {@see SocialMediaProgressNotifier}.
 */
final readonly class PostProgressNotifier
{
    public function notify(
        ?object $causer,
        string $flow,
        string $stage,
        string $message,
        int $progress,
    ): void {
        if (! $causer instanceof Authenticatable) {
            return;
        }

        try {
            broadcast(new PostAiGenerationProgress(
                userId: (int) $causer->getAuthIdentifier(),
                flow: $flow,
                stage: $stage,
                message: $message,
                progress: $progress,
            ));
        } catch (Throwable $exception) {
            Log::warning('post.ai.broadcast_failed', ['message' => $exception->getMessage()]);
        }
    }
}
