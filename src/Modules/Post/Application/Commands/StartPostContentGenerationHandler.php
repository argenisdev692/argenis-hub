<?php

declare(strict_types=1);

namespace Modules\Post\Application\Commands;

use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Support\Facades\DB;
use Modules\Post\Application\DTOs\GeneratePostContentData;
use Modules\Post\Application\DTOs\PostAiGenerationData;
use Modules\Post\Application\Queries\GetPostAiGenerationHandler;
use Modules\Post\Domain\Enums\PostAiGenerationStatus;
use Modules\Post\Domain\Ports\PostAiGenerationRepositoryPort;
use Modules\Post\Domain\Ports\PostGenerationDispatcherPort;
use Shared\Domain\Ports\AuditPort;

/**
 * Accepts a generation and hands it to the queue.
 *
 * Returns immediately with a `queued` row rather than blocking on the
 * up-to-5-iteration loop — the wizard polls
 * {@see GetPostAiGenerationHandler} (or, once
 * an Echo client exists, subscribes to `post.ai.progress`) for the phases and
 * the finished draft.
 *
 * The row is committed as `draft` BEFORE the dispatch, never inside the same
 * transaction as it: a worker can pick a job up faster than a transaction
 * commits, and a job whose row does not exist yet is a run lost for no reason.
 * That ordering is also what makes `draft` a real state rather than a
 * formality — a dispatch that throws leaves the row honestly parked there
 * instead of claiming to be on a queue it never reached.
 */
final readonly class StartPostContentGenerationHandler
{
    public function __construct(
        private PostAiGenerationRepositoryPort $generations,
        private PostGenerationDispatcherPort $dispatcher,
        private AuditPort $audit,
    ) {}

    #[\NoDiscard]
    public function handle(GeneratePostContentData $data, Authenticatable $causer): PostAiGenerationData
    {
        $causerId = (int) $causer->getAuthIdentifier();

        $generation = DB::transaction(fn () => $this->generations->create([
            'topic' => $data->topic,
            'angle' => $data->angle,
            'key_trend' => $data->keyTrend,
            'provider' => $data->provider,
            'image_mode' => $data->imageMode->value,
            'status' => PostAiGenerationStatus::Draft->value,
            'progress' => 0,
            'iteration' => 0,
            'created_by' => $causerId,
        ]));

        $this->dispatcher->dispatch($generation->uuid, $data, $causerId);

        // Under a real queue driver the row is untouched here and this is the
        // hand-off marker. Under the `sync` driver the job has ALREADY run to
        // completion inside dispatch(), so the guard is what stops this write
        // from stamping `queued` over a run that is finished — re-read rather
        // than trusting the instance we created.
        $generation = $this->generations->findByUuid($generation->uuid) ?? $generation;

        if ($generation->status === PostAiGenerationStatus::Draft) {
            $generation = $this->generations->update($generation, [
                'status' => PostAiGenerationStatus::Queued->value,
                'stage_message' => 'Queued — waiting for a worker.',
            ]);
        }

        $this->audit->log(
            event: 'post.ai.generation_started',
            subject: $generation,
            properties: [
                'provider' => $data->provider,
                'topic' => $data->topic,
                'image_mode' => $data->imageMode->value,
            ],
            causer: $causer,
            logName: 'post',
        );

        return PostAiGenerationData::fromModel($generation);
    }
}
