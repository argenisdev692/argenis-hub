<?php

declare(strict_types=1);

namespace Modules\Auth\Infrastructure\Http\Requests;

use App\Concerns\PasswordValidationRules;
use Illuminate\Foundation\Http\FormRequest;

/**
 * Reset a forgotten password with the 6-digit code (FR-11, FR-12).
 *
 * Reuses {@see PasswordValidationRules} so the strength policy is defined once
 * and stays identical across registration, reset and change. Reuse of a recent
 * password is rejected by the `NotAPreviousPassword` rule the trait attaches.
 */
final class ResetPasswordWithOtpRequest extends FormRequest
{
    use PasswordValidationRules;

    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'email' => ['required', 'string', 'email', 'max:255'],
            'code' => ['required', 'string', 'digits:6'],
            'password' => $this->passwordRules(),
        ];
    }

    public function email(): string
    {
        return mb_strtolower(trim((string) $this->validated('email')));
    }

    public function code(): string
    {
        return (string) $this->validated('code');
    }

    public function password(): string
    {
        return (string) $this->validated('password');
    }
}
