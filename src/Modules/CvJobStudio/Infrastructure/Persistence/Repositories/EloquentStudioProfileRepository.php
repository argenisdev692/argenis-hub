<?php

declare(strict_types=1);

namespace Modules\CvJobStudio\Infrastructure\Persistence\Repositories;

use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\DB;
use Modules\CvJobStudio\Application\DTOs\StudioProfileData;
use Modules\CvJobStudio\Domain\Exceptions\PostingNotFoundException;
use Modules\CvJobStudio\Domain\Exceptions\ProfileGateIncompleteException;
use Modules\CvJobStudio\Domain\Ports\StudioProfileRepositoryPort;
use Modules\CvJobStudio\Infrastructure\Persistence\Eloquent\Models\StudioProfileEloquentModel;
use Modules\CvJobStudio\Infrastructure\Persistence\Eloquent\Models\StudioRunEloquentModel;

final readonly class EloquentStudioProfileRepository implements StudioProfileRepositoryPort
{
    /** @var list<string> */
    private const array LIST_COLUMNS = [
        'id', 'uuid', 'user_id', 'name', 'slug', 'flow', 'is_active',
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
            ->withQueryString();
    }

    public function findByUuidForUser(string $uuid, int $userId): ?StudioProfileEloquentModel
    {
        return StudioProfileEloquentModel::query()
            ->ownedBy($userId)
            ->where('uuid', $uuid)
            ->first();
    }

    public function upsert(StudioProfileData $data, int $userId, array $defaultRules): StudioProfileEloquentModel
    {
        return DB::transaction(static function () use ($data, $userId, $defaultRules): StudioProfileEloquentModel {
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

                return $existing->refresh();
            }

            return StudioProfileEloquentModel::query()->create([
                'user_id' => $userId,
                'slug' => $data->slug,
                'flow' => 'fullstack',
                'is_active' => true,
                'rules' => $defaultRules,
                'rules_version' => (int) ($defaultRules['rules_version'] ?? 2),
                ...$attributes,
            ]);
        });
    }

    public function updateOpportunityRules(string $uuid, int $userId, array $opportunity): StudioProfileEloquentModel
    {
        return DB::transaction(static function () use ($uuid, $userId, $opportunity): StudioProfileEloquentModel {
            $profile = StudioProfileEloquentModel::query()
                ->ownedBy($userId)
                ->where('uuid', $uuid)
                ->lockForUpdate()
                ->first();

            if ($profile === null) {
                throw new PostingNotFoundException("Profile {$uuid} not found.");
            }

            $rules = $profile->rules ?? [];
            $rules['opportunity'] = $opportunity;
            $profile->update(['rules' => $rules]);

            return $profile->refresh();
        });
    }

    public function beginRun(string $uuid, int $userId): StudioRunEloquentModel
    {
        return DB::transaction(static function () use ($uuid, $userId): StudioRunEloquentModel {
            $profile = StudioProfileEloquentModel::query()
                ->ownedBy($userId)
                ->where('uuid', $uuid)
                ->lockForUpdate()
                ->first();

            if ($profile === null) {
                throw new PostingNotFoundException("Profile {$uuid} not found.");
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

            return StudioRunEloquentModel::query()->create([
                'user_id' => $userId,
                'profile_id' => $profile->id,
                'status' => 'queued',
                'rules_version' => $profile->rules_version,
            ]);
        });
    }
}
