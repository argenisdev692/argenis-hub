<?php

declare(strict_types=1);

namespace Modules\Authorization\Domain;

/**
 * Foundation roles seeded by `Database\Seeders\RolePermissionSeeder`. They are
 * referenced across the codebase and tests by name, so the module treats them as
 * an invariant: a system role can never be renamed nor deleted through the CRUD
 * surface. This is the domain rule that lifts the module above a plain CRUD.
 *
 * The seeder is referenced in prose, not imported — Domain depends on nothing
 * outside itself (BACKEND-PHP §5, Layer Imports).
 */
final readonly class SystemRoles
{
    public const string SUPER_ADMIN = 'SUPER_ADMIN';

    /** @var list<string> */
    public const array PROTECTED = ['SUPER_ADMIN', 'ADMIN', 'MODERATOR', 'USER', 'GUEST'];

    public static function isProtected(string $roleName): bool
    {
        return in_array($roleName, self::PROTECTED, true);
    }
}
