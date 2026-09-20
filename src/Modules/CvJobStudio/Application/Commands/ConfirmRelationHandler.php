<?php

declare(strict_types=1);

namespace Modules\CvJobStudio\Application\Commands;

use Modules\CvJobStudio\Domain\Exceptions\RecordNotFoundException;
use Modules\CvJobStudio\Domain\Ports\StudioSkillRelationRepositoryPort;

/**
 * Relations inbox (T-124, FR-44): a model may PROPOSE a relation, but only
 * the candidate's confirmation grants credit. Confirmation is the audited
 * business action (T-091) — recorded through the model's activity log.
 */
final readonly class ConfirmRelationHandler
{
    public function __construct(private StudioSkillRelationRepositoryPort $relations) {}

    public function handle(string $uuid, int $userId): void
    {
        if (! $this->relations->setStatus($uuid, $userId, 'confirmed')) {
            throw RecordNotFoundException::of('relation', $uuid);
        }
    }
}
