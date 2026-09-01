<?php

declare(strict_types=1);

namespace Modules\Authorization\Application\DTOs;

use Illuminate\Validation\Rule;
use Spatie\LaravelData\Data;

/**
 * Create/update payload for a role (DTO Fusion Rule — store and update share the
 * same shape). `permissions` is the full set of permission NAMES the role should
 * hold after the operation; it is synced (not merged), so an empty array revokes
 * every grant. Names are validated against the live `permissions` catalogue so a
 * client can never attach an unknown or suspended permission.
 *
 * The guard is fixed to `web` — this application authorizes the Inertia/session
 * surface, and mixing guards silently breaks Spatie's permission checks.
 */
final class RoleData extends Data
{
    /**
     * @param  array<int, string>  $permissions
     */
    public function __construct(
        public string $name,
        public array $permissions = [],
    ) {}

    /**
     * @return array<string, mixed>
     */
    public static function rules(): array
    {
        $uuid = request()->route('uuid');

        return [
            'name' => [
                'required',
                'string',
                'max:125',
                'regex:/^[A-Za-z0-9 _-]+$/',
                Rule::unique('roles', 'name')
                    ->where('guard_name', 'web')
                    ->ignore($uuid, 'uuid'),
            ],
            'permissions' => ['array'],
            'permissions.*' => [
                'string',
                Rule::exists('permissions', 'name')->where('guard_name', 'web'),
            ],
        ];
    }
}
