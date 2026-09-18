<?php

declare(strict_types=1);

namespace Modules\LeadScout\Infrastructure\Http\Controllers;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Modules\LeadScout\Application\Commands\ImportCvProfileHandler;
use Modules\LeadScout\Application\Commands\UpdateProfileHandler;
use Modules\LeadScout\Application\DTOs\CvOptionData;
use Modules\LeadScout\Application\DTOs\ImportCvData;
use Modules\LeadScout\Application\DTOs\ProfileData;
use Modules\LeadScout\Application\DTOs\UpdateProfileData;
use Modules\LeadScout\Domain\Exceptions\ProfileNotFoundException;
use Modules\LeadScout\Domain\Ports\CvSourcePort;
use Modules\LeadScout\Infrastructure\Persistence\Eloquent\Models\ScoutProfileEloquentModel;

/**
 * Matching-profile endpoints (spec US-1, plan §5). The CV text never
 * crosses this boundary — options and profiles carry derived data only.
 */
final readonly class ProfileController
{
    public function show(Request $request, CvSourcePort $cvs): JsonResponse
    {
        $profile = $this->current((int) $request->user()->id);

        return response()->json(['data' => ProfileData::fromModel($profile, $cvs, (int) $request->user()->id)]);
    }

    public function update(Request $request, UpdateProfileData $data, UpdateProfileHandler $update, CvSourcePort $cvs): JsonResponse
    {
        $profile = $update->handle($data, (int) $request->user()->id);

        return response()->json(['data' => ProfileData::fromModel($profile, $cvs, (int) $request->user()->id)]);
    }

    public function cvs(Request $request, CvSourcePort $cvs): JsonResponse
    {
        $options = $cvs->cvsForUser((int) $request->user()->id);

        return response()->json(['data' => CvOptionData::collect($options)]);
    }

    public function importCv(Request $request, ImportCvData $data, ImportCvProfileHandler $import, CvSourcePort $cvs): JsonResponse
    {
        $profile = $import->handle($data->cvUuid, (int) $request->user()->id);

        return response()->json(
            ['data' => ProfileData::fromModel($profile, $cvs, (int) $request->user()->id)],
            201,
        );
    }

    private function current(int $userId): ScoutProfileEloquentModel
    {
        return ScoutProfileEloquentModel::query()
            ->where('user_id', $userId)
            ->where('is_current', true)
            ->first() ?? throw new ProfileNotFoundException;
    }
}
