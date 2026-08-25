<?php

declare(strict_types=1);

namespace Modules\Auth\Infrastructure\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

/**
 * The 6-digit code submitted to clear the two-factor challenge by email.
 *
 * Authorisation is the presence of a challenged user in the session: that key
 * is only written by Fortify after a password has already been verified, so
 * nobody reaches this endpoint without having passed the first factor.
 */
final class VerifyTwoFactorEmailCodeRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->session()->has('login.id');
    }

    /**
     * @return array<string, list<string>>
     */
    public function rules(): array
    {
        return [
            'code' => ['required', 'string', 'digits:6'],
        ];
    }

    public function code(): string
    {
        return (string) $this->validated('code');
    }
}
