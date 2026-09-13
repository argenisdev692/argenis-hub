<?php

declare(strict_types=1);

namespace Modules\CourseScripts\Tests\Support;

use App\Models\User;

/**
 * Users for Course Scripts feature tests. Requires RolePermissionSeeder to
 * have run.
 */
final class CourseScriptTestUsers
{
    public static function author(): User
    {
        $user = User::factory()->create();
        $user->assignRole('SUPER_ADMIN');

        return $user;
    }

    public static function withoutPermissions(): User
    {
        $user = User::factory()->create();
        $user->assignRole('USER');

        return $user;
    }
}
