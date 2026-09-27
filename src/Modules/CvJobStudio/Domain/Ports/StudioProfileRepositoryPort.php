<?php

declare(strict_types=1);

namespace Modules\CvJobStudio\Domain\Ports;

use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Modules\CvJobStudio\Application\DTOs\StudioProfileData;
use Modules\CvJobStudio\Application\DTOs\StudioRunData;
use Modules\CvJobStudio\Domain\Exceptions\ProfileGateIncompleteException;
use Modules\CvJobStudio\Domain\ValueObjects\StudioProfileSnapshot;

/**
 * Profile aggregate: upsert, rules versioning, gate-input validation, the
 * profile's seed vocabulary and run bootstrapping. Profiles own their
 * `rules` snapshot — a run may never silently inherit another profile's
 * gates (FR-31).
 */
interface StudioProfileRepositoryPort
{
    /** @return LengthAwarePaginator<int, StudioProfileData> */
    public function paginate(int $userId, int $perPage): LengthAwarePaginator;

    public function snapshotForUser(string $uuid, int $userId): ?StudioProfileSnapshot;

    /**
     * @param  array<string, mixed>  $defaultRules  seeded only on create — an update never overwrites rules
     * @return string the profile uuid
     */
    public function upsert(StudioProfileData $data, int $userId, array $defaultRules): string;

    /**
     * Update an existing profile BY UUID, addressed by the route rather than
     * by the slug the form happens to send.
     *
     * `upsert()` matches on slug, which made editing an accident: the operator
     * had to retype the exact slug or a second profile was created instead. The
     * slug is now editable like any other field, and this method is what
     * resolves the collision.
     *
     * @param  array<string, mixed>  $defaultRules  not touched on update
     * @return bool false when the profile is not the owner's
     */
    public function updateByUuid(StudioProfileData $data, string $uuid, int $userId, array $defaultRules): bool;

    /**
     * @param  array<string, mixed>  $opportunity
     * @return bool false when the profile is not the owner's
     */
    public function updateOpportunityRules(string $uuid, int $userId, array $opportunity): bool;

    /**
     * @return array{id: int, run: StudioRunData}|null null when the profile is not the owner's
     *
     * @throws ProfileGateIncompleteException
     */
    public function beginRun(string $uuid, int $userId): ?array;

    /**
     * Idempotent: `$strong` terms seed as strong vocabulary, `$neverSeed` as never_seed.
     *
     * @param  list<string>  $strong
     * @param  list<string>  $neverSeed
     * @return int terms written
     */
    public function seedVocabulary(int $profileId, int $userId, array $strong, array $neverSeed): int;
}
