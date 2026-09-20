<?php

declare(strict_types=1);

namespace Modules\CvJobStudio\Infrastructure\Persistence\Repositories;

use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\DB;
use Modules\CvJobStudio\Application\DTOs\StudioProfileData;
use Modules\CvJobStudio\Domain\Exceptions\ProfileGateIncompleteException;
use Modules\CvJobStudio\Domain\Ports\StudioProfileRepositoryPort;
use Modules\CvJobStudio\Domain\ValueObjects\StudioProfileSnapshot;
use Modules\CvJobStudio\Infrastructure\Persistence\Eloquent\Models\StudioProfileEloquentModel;
use Modules\CvJobStudio\Infrastructure\Persistence\Eloquent\Models\StudioRunEloquentModel;
use Modules\CvJobStudio\Infrastructure\Persistence\Eloquent\Models\StudioVocabularyEloquentModel;
use Modules\CvJobStudio\Infrastructure\Persistence\Mappers\StudioReadModelMapper;

final readonly class EloquentStudioProfileRepository implements StudioProfileRepositoryPort
{
    /**
     * Every column `StudioProfileData` exposes — a narrower select would
     * silently null the gate inputs on the Settings page.
     *
     * @var list<string>
     */
    private const array LIST_COLUMNS = [
        'id', 'uuid', 'user_id', 'name', 'slug', 'flow', 'is_active',
        'base_city', 'base_country', 'accepted_remote_scopes', 'stack_must',
        'stack_reject', 'geography_deny', 'years_baseline',
        'rules_version', 'created_at', 'updated_at', 'deleted_at',
    ];

    public function paginate(int $userId, int $perPage): LengthAwarePaginator
    {
        return StudioProfileEloquentModel::query()
            ->ownedBy($userId)
            ->select(self::LIST_COLUMNS)
            ->orderByDesc('created_at')
            ->orderByDesc('id')
            ->paginate($perPage)
            ->withQueryString()
            ->through(StudioReadModelMapper::profile(...));
    }

    public function snapshotForUser(string $uuid, int $userId): ?StudioProfileSnapshot
    {
        $profile = StudioProfileEloquentModel::query()
            ->ownedBy($userId)
            ->where('uuid', $uuid)
            ->first(['id', 'uuid', 'accepted_remote_scopes', 'stack_must', 'stack_reject', 'rules']);

        return $profile !== null ? StudioReadModelMapper::profileSnapshot($profile) : null;
    }

    public function upsert(StudioProfileData $data, int $userId, array $defaultRules): string
    {
        return DB::transaction(static function () use ($data, $userId, $defaultRules): string {
            $existing = StudioProfileEloquentModel::query()
                ->ownedBy($userId)
                ->where('slug', $data->slug)
                ->lockForUpdate()
                ->first();

            $attributes = [
                'name' => $data->name,
                'base_city' => $data->baseCity,
                'base_country' => $data->baseCountry,
                'accepted_remote_scopes' => $data->acceptedRemoteScopes,
                'stack_must' => $data->stackMust,
                'stack_reject' => $data->stackReject,
                'geography_deny' => $data->geographyDeny,
                'years_baseline' => $data->yearsBaseline,
            ];

            if ($existing !== null) {
                $existing->update($attributes);

                return $existing->uuid;
            }

            return StudioProfileEloquentModel::query()->create([
                'user_id' => $userId,
                'slug' => $data->slug,
                'flow' => 'fullstack',
                'is_active' => true,
                'rules' => $defaultRules,
                'rules_version' => (int) ($defaultRules['rules_version'] ?? 2),
                ...$attributes,
            ])->uuid;
        });
    }

    public function updateOpportunityRules(string $uuid, int $userId, array $opportunity): bool
    {
        return DB::transaction(static function () use ($uuid, $userId, $opportunity): bool {
            $profile = StudioProfileEloquentModel::query()
                ->ownedBy($userId)
                ->where('uuid', $uuid)
                ->lockForUpdate()
                ->first();

            if ($profile === null) {
                return false;
            }

            $rules = $profile->rules ?? [];
            $rules['opportunity'] = $opportunity;

            return $profile->update(['rules' => $rules]);
        });
    }

    public function beginRun(string $uuid, int $userId): ?array
    {
        return DB::transaction(static function () use ($uuid, $userId): ?array {
            $profile = StudioProfileEloquentModel::query()
                ->ownedBy($userId)
                ->where('uuid', $uuid)
                ->lockForUpdate()
                ->first();

            if ($profile === null) {
                return null;
            }

            $missing = [];

            if (($profile->accepted_remote_scopes ?? []) === []) {
                $missing[] = 'accepted_remote_scopes';
            }

            if (($profile->stack_must ?? []) === [] && ($profile->stack_reject ?? []) === []) {
                $missing[] = 'stack_must or stack_reject';
            }

            if ($missing !== []) {
                throw new ProfileGateIncompleteException($missing);
            }

            $run = StudioRunEloquentModel::query()->create([
                'user_id' => $userId,
                'profile_id' => $profile->id,
                'status' => 'queued',
                'rules_version' => $profile->rules_version,
            ]);

            return ['id' => $run->id, 'run' => StudioReadModelMapper::run($run->refresh())];
        });
    }

    public function seedVocabulary(int $profileId, int $userId, array $strong, array $neverSeed): int
    {
        return DB::transaction(static function () use ($profileId, $userId, $strong, $neverSeed): int {
            $terms = [
                ...array_map(static fn (string $term): array => [$term, 'strong'], $strong),
                ...array_map(static fn (string $term): array => [$term, 'never_seed'], $neverSeed),
            ];

            foreach ($terms as [$term, $kind]) {
                StudioVocabularyEloquentModel::query()->updateOrCreate(
                    ['profile_id' => $profileId, 'term' => $term, 'language' => 'en'],
                    ['user_id' => $userId, 'kind' => $kind, 'refreshed_at' => now()],
                );
            }

            return count($terms);
        });
    }
}
