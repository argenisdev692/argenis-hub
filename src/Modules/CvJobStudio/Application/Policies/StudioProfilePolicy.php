<?php

declare(strict_types=1);

namespace Modules\CvJobStudio\Application\Policies;

use App\Models\User;
use Modules\CvJobStudio\Infrastructure\Persistence\Eloquent\Models\StudioProfileEloquentModel;

final readonly class StudioProfilePolicy
{
    public function view(User $actor, StudioProfileEloquentModel $profile): bool
    {
        return $actor->id === $profile->user_id;
    }

    public function update(User $actor, StudioProfileEloquentModel $profile): bool
    {
        return $actor->id === $profile->user_id;
    }
}
