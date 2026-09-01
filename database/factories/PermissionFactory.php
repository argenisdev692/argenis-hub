<?php

declare(strict_types=1);

namespace Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;
use Modules\Authorization\Infrastructure\Persistence\Eloquent\Models\Permission;

/**
 * @extends Factory<Permission>
 */
final class PermissionFactory extends Factory
{
    protected $model = Permission::class;

    /**
     * Names follow the project's `{ACTION}_{MODULE}` convention enforced by
     * `PermissionData::rules()`, and are unique so the `(name, guard_name)`
     * unique index never collides across a batch.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'uuid' => (string) Str::uuid7(),
            'name' => Str::upper(Str::snake(fake()->unique()->words(2, true))),
            'guard_name' => 'web',
        ];
    }

    /**
     * Soft-deleted after insert — see {@see RoleFactory::suspended()}.
     */
    public function suspended(): self
    {
        return $this->afterCreating(static fn (Permission $permission) => $permission->delete());
    }
}
