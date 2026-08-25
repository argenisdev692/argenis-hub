<?php

declare(strict_types=1);

namespace Modules\Auth\Infrastructure\Http\Requests\Api;

use Illuminate\Foundation\Http\FormRequest;

/**
 * Credentials for `POST /api/auth/login`.
 *
 * `device_name` names the token so it can be rotated and revoked per device
 * ("Pixel 8", "iPad"). It is attacker-controlled text that ends up in a token
 * label, so it is length-capped and never interpolated anywhere but the label.
 *
 * `code` carries the TOTP or recovery code and is required only when the
 * account has confirmed two-factor authentication — enforced in the controller,
 * because demanding it up front would leak which accounts have 2FA enabled.
 */
final class ApiLoginRequest extends FormRequest
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
            'password' => ['required', 'string'],
            'device_name' => ['required', 'string', 'max:120'],
            'code' => ['nullable', 'string', 'max:64'],
        ];
    }

    public function email(): string
    {
        return mb_strtolower(trim((string) $this->validated('email')));
    }

    public function password(): string
    {
        return (string) $this->validated('password');
    }

    public function deviceName(): string
    {
        return trim((string) $this->validated('device_name'));
    }

    public function code(): ?string
    {
        $code = $this->validated('code');

        return is_string($code) && trim($code) !== '' ? trim($code) : null;
    }
}
