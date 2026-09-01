<?php

declare(strict_types=1);

namespace Modules\Authorization\Application\DTOs;

use Illuminate\Validation\Rule;
use Spatie\LaravelData\Data;

/**
 * Create/update payload for a permission (DTO Fusion Rule). The name follows the
 * project convention `{ACTION}_{MODULE}` (upper snake case); the regex enforces
 * that shape so the catalogue stays consistent and code-referenceable. Guard is
 * fixed to `web` — see {@see RoleData}.
 */
final class PermissionData extends Data
{
    public function __construct(
        public string $name,
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
                'regex:/^[A-Z][A-Z0-9_]*$/',
                Rule::unique('permissions', 'name')
                    ->where('guard_name', 'web')
                    ->ignore($uuid, 'uuid'),
            ],
        ];
    }
}
