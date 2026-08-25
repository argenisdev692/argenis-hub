<?php

declare(strict_types=1);

namespace App\Actions\Fortify;

use App\Concerns\PasswordValidationRules;
use App\Models\User;
use Illuminate\Contracts\Events\Dispatcher;
use Illuminate\Support\Facades\Validator;
use Laravel\Fortify\Contracts\ResetsUserPasswords;
use Modules\Auth\Domain\Events\UserPasswordChanged;

/**
 * The single place a forgotten password is applied — reached both by Fortify's
 * own reset endpoint and by the module's one-time-code flow.
 *
 * Everything that must follow a credential change (reuse history, signing other
 * sessions out, the notification email, the audit entry) is triggered by the
 * {@see UserPasswordChanged} event rather than inlined here, so no future entry
 * point can forget half of it.
 */
final readonly class ResetUserPassword implements ResetsUserPasswords
{
    use PasswordValidationRules;

    public function __construct(private Dispatcher $events) {}

    /**
     * Validate and reset the user's forgotten password.
     *
     * @param  array<string, string>  $input
     */
    public function reset(User $user, array $input): void
    {
        Validator::make($input, [
            'password' => $this->passwordRules((string) $user->uuid),
        ])->validate();

        $user->forceFill([
            'password' => $input['password'],
            'password_changed_at' => now(),
            'must_change_password' => false,
        ])->save();

        $this->events->dispatch(new UserPasswordChanged(
            userUuid: (string) $user->uuid,
            hashedPassword: (string) $user->password,
        ));
    }
}
