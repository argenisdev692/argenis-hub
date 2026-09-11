<?php

declare(strict_types=1);

namespace Modules\VideoEdits\Tests\Support;

use App\Models\User;

/**
 * Users for Video Edits feature tests. Requires RolePermissionSeeder to have run.
 */
final class VideoEditTestUsers
{
    public static function editor(): User
    {
        $user = User::factory()->create();
        $user->assignRole('SUPER_ADMIN');

        return $user;
    }

    public static function withoutVideoEditPermissions(): User
    {
        $user = User::factory()->create();
        $user->assignRole('USER');

        return $user;
    }
}
