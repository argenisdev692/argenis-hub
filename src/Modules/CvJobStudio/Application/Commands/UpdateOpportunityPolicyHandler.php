<?php

declare(strict_types=1);

namespace Modules\CvJobStudio\Application\Commands;

use Modules\CvJobStudio\Application\DTOs\UpdateOpportunityPolicyData;
use Modules\CvJobStudio\Domain\Exceptions\RecordNotFoundException;
use Modules\CvJobStudio\Domain\Ports\StudioProfileRepositoryPort;
use Modules\CvJobStudio\Domain\Services\OpportunityPolicy;

/**
 * Opportunity policy edit (T-135, FR-51): the policy object validates every
 * factor before it touches the profile. Neutral toggle included (SC-13).
 */
final readonly class UpdateOpportunityPolicyHandler
{
    public function __construct(private StudioProfileRepositoryPort $profiles) {}

    public function handle(string $uuid, UpdateOpportunityPolicyData $data, int $userId): void
    {
        $profile = $this->profiles->snapshotForUser($uuid, $userId)
            ?? throw RecordNotFoundException::of('profile', $uuid);

        $opportunity = [
            ...($profile->rules['opportunity'] ?? []),
            'channel' => $data->channels,
            'neutral' => $data->neutral,
        ];

        // Throws on any factor outside [0.1, 1] or missing grade/source.
        (void) OpportunityPolicy::fromConfig($opportunity, $data->neutral);

        if (! $this->profiles->updateOpportunityRules($uuid, $userId, $opportunity)) {
            throw RecordNotFoundException::of('profile', $uuid);
        }
    }
}
