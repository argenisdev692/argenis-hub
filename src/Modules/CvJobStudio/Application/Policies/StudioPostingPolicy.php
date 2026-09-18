<?php

declare(strict_types=1);

namespace Modules\CvJobStudio\Application\Policies;

use App\Models\User;
use Modules\CvJobStudio\Infrastructure\Persistence\Eloquent\Models\StudioPostingEloquentModel;

/** Object-level authorization: tenant/owner verified before mutation (OWASP §11). */
final readonly class StudioPostingPolicy
{
    public function view(User $actor, StudioPostingEloquentModel $posting): bool
    {
        return $actor->id === $posting->user_id;
    }

    public function update(User $actor, StudioPostingEloquentModel $posting): bool
    {
        return $actor->id === $posting->user_id;
    }

    public function delete(User $actor, StudioPostingEloquentModel $posting): bool
    {
        return $actor->id === $posting->user_id;
    }

    public function restore(User $actor, StudioPostingEloquentModel $posting): bool
    {
        return $actor->id === $posting->user_id;
    }
}
