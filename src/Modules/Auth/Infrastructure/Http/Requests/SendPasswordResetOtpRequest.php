<?php

declare(strict_types=1);

namespace Modules\Auth\Infrastructure\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

/**
 * "Email me a reset code" (FR-11).
 *
 * Validates the address shape only — never its existence. An `exists:users`
 * rule here would turn the forgot-password form into a free account-enumeration
 * oracle; the controller answers identically either way.
 */
final class SendPasswordResetOtpRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, list<string>>
     */
    public function rules(): array
    {
        return [
            'email' => ['required', 'string', 'email', 'max:255'],
        ];
    }

    public function email(): string
    {
        return mb_strtolower(trim((string) $this->validated('email')));
    }
}
