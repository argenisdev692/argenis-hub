<?php

declare(strict_types=1);

namespace Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;
use Modules\Authorization\Domain\SystemRoles;
use Modules\Authorization\Infrastructure\Persistence\Eloquent\Models\Role;

/**
 * @extends Factory<Role>
 */
final class RoleFactory extends Factory
{
    protected $model = Role::class;

    /**
     * Names are upper snake case to match the seeded catalogue, and unique so the
     * `(name, guard_name)` unique index never collides across a batch.
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
     * A protected system role — the invariant guarded by {@see SystemRoles}.
     */
    public function systemRole(string $name = SystemRoles::SUPER_ADMIN): self
    {
        return $this->state(fn (): array => ['name' => $name]);
    }

    /**
     * Soft-deleted after insert: `deleted_at` is outside the `$fillable`
     * allowlist, so it is set through the model rather than through state.
     */
    public function suspended(): self
    {
        return $this->afterCreating(static fn (Role $role) => $role->delete());
    }
}
