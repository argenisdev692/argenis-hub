<?php

declare(strict_types=1);

namespace Modules\LeadScout\Domain\Ports;

use Modules\LeadScout\Domain\Enums\OutreachStage;
use Modules\LeadScout\Domain\Enums\ReplyOutcome;
use Modules\LeadScout\Infrastructure\Persistence\Eloquent\Models\ScoutOutreachEloquentModel;

/**
 * Outreach persistence (mandatory port in the intermediate baseline).
 * Stage moves always append history — including round trips (US-6 CA-1).
 */
interface OutreachRepositoryPort
{
    public function findByUuid(string $uuid): ?ScoutOutreachEloquentModel;

    /**
     * @param  array<string, mixed>  $attributes
     */
    public function create(array $attributes): ScoutOutreachEloquentModel;

    public function moveToStage(
        ScoutOutreachEloquentModel $outreach,
        OutreachStage $to,
        int $operatorId,
        ?ReplyOutcome $replyOutcome = null,
    ): ScoutOutreachEloquentModel;
}
