<?php

declare(strict_types=1);

namespace App\Actions\Fortify;

use App\Concerns\PasswordValidationRules;
use App\Concerns\ProfileValidationRules;
use App\Models\User;
use Illuminate\Support\Facades\Validator;
use Laravel\Fortify\Contracts\CreatesNewUsers;
use Modules\Auth\Application\Commands\RecordPasswordChangeHandler;

/**
 * Registers a new account (spec 001 FR-01).
 *
 * The first credential is seeded into the reuse history immediately, so FR-12
 * covers a user's very first password change rather than starting one change
 * later.
 */
final readonly class CreateNewUser implements CreatesNewUsers
{
    use PasswordValidationRules;
    use ProfileValidationRules;

    public function __construct(private RecordPasswordChangeHandler $recordPassword) {}

    /**
     * Validate and create a newly registered user.
     *
     * @param  array<string, string>  $input
     */
    public function create(array $input): User
    {
        Validator::make($input, [
            ...$this->profileRules(),
            'password' => $this->passwordRules(),
        ])->validate();

        $user = User::create([
            'first_name' => $input['first_name'],
            'last_name' => $input['last_name'] ?? null,
            'email' => $input['email'],
            'password' => $input['password'],
            'password_changed_at' => now(),
        ]);

        $this->recordPassword->handle((string) $user->uuid, (string) $user->password);

        return $user;
    }
}
