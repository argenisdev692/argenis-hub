<?php

declare(strict_types=1);

namespace Modules\LeadScout\Infrastructure\Persistence\Repositories;

use Illuminate\Support\Facades\DB;
use Modules\LeadScout\Domain\Enums\OutreachStage;
use Modules\LeadScout\Domain\Enums\ReplyOutcome;
use Modules\LeadScout\Domain\Ports\OutreachRepositoryPort;
use Modules\LeadScout\Infrastructure\Persistence\Eloquent\Models\ScoutOutreachEloquentModel;

/**
 * Stage moves always append history — including round trips (US-6 CA-1).
 */
final readonly class EloquentOutreachRepository implements OutreachRepositoryPort
{
    public function findByUuid(string $uuid): ?ScoutOutreachEloquentModel
    {
        return ScoutOutreachEloquentModel::query()->where('uuid', $uuid)->first();
    }

    public function create(array $attributes): ScoutOutreachEloquentModel
    {
        return DB::transaction(
            static fn (): ScoutOutreachEloquentModel => ScoutOutreachEloquentModel::query()->create($attributes),
        );
    }

    public function moveToStage(
        ScoutOutreachEloquentModel $outreach,
        OutreachStage $to,
        int $operatorId,
        ?ReplyOutcome $replyOutcome = null,
    ): ScoutOutreachEloquentModel {
        return DB::transaction(function () use ($outreach, $to, $operatorId, $replyOutcome): ScoutOutreachEloquentModel {
            $from = $outreach->stage;

            $outreach->update([
                'stage' => $to->value,
                'stage_changed_at' => now(),
                'reply_outcome' => $replyOutcome?->value ?? $outreach->reply_outcome?->value,
                'replied_at' => $replyOutcome === null ? $outreach->replied_at : now(),
            ]);

            $outreach->stageEvents()->create([
                'from_stage' => $from->value,
                'to_stage' => $to->value,
                'reply_outcome' => $replyOutcome?->value,
                'operator_id' => $operatorId,
            ]);

            return $outreach->refresh();
        });
    }
}
