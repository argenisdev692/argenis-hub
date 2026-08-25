<?php

declare(strict_types=1);

namespace App\Concerns;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Validation\Rules\Password;
use Modules\Auth\Application\Validation\NotAPreviousPassword;

trait PasswordValidationRules
{
    /**
     * Get the validation rules used to validate passwords.
     *
     * Reuse of a recent password is rejected only when the subject is known
     * (spec 001 FR-12). Registration has no history yet, so the rule is a no-op
     * there; every path that already has a user — reset, self-service change —
     * passes the uuid and gets the check.
     *
     * @return array<int, Password|ValidationRule|array<mixed>|string>
     */
    protected function passwordRules(?string $userUuid = null): array
    {
        return [
            'required',
            'string',
            Password::default(),
            'confirmed',
            app(NotAPreviousPassword::class)->for($userUuid),
        ];
    }

    /**
     * Get the validation rules used to validate the current password.
     *
     * @return array<int, Password|ValidationRule|array<mixed>|string>
     */
    protected function currentPasswordRules(): array
    {
        return ['required', 'string', 'current_password'];
    }
}
