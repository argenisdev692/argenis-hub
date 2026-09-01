<?php

declare(strict_types=1);

namespace Modules\Campaigns\Infrastructure\Broadcasting;

use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * Single owner of "tell the wizard where the generation is".
 *
 * The generator, the evaluator, the asset renderer and the loop handler all
 * report progress; each used to carry its own copy of the same null-causer
 * guard and try/catch. Broadcasting is an optional nicety — a dead Reverb
 * connection must never fail a paid generation — so every failure is swallowed
 * with a warning here, once.
 */
final readonly class CampaignProgressNotifier
{
    public function notify(
        ?object $causer,
        string $campaignUuid,
        string $stage,
        string $message,
        int $progress,
        int $iteration = 1,
    ): void {
        if (! $causer instanceof Authenticatable) {
            return;
        }

        try {
            broadcast(new CampaignAiGenerationProgress(
                userId: (int) $causer->getAuthIdentifier(),
                campaignUuid: $campaignUuid,
                stage: $stage,
                message: $message,
                progress: $progress,
                iteration: $iteration,
            ));
        } catch (Throwable $exception) {
            Log::warning('campaigns.ai.broadcast_failed', ['message' => $exception->getMessage()]);
        }
    }
}
