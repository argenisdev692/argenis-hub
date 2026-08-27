<?php

declare(strict_types=1);

namespace Modules\Clients\Infrastructure\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rules\Enum;
use Modules\Clients\Domain\Enums\ClientStatus;

final class UpdateClientRequest extends FormRequest
{
    /**
     * Authorization is enforced by the route middleware (permission:UPDATE_CLIENTS).
     */
    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, list<mixed>>
     */
    public function rules(): array
    {
        return [
            'client_name' => ['required', 'string', 'max:255'],
            'email' => ['nullable', 'email:rfc', 'max:255'],
            'status' => ['sometimes', 'nullable', new Enum(ClientStatus::class)],
            'phone' => ['required', 'string', 'max:20', 'regex:/^\+?[0-9]{7,15}$/'],
            'address' => ['nullable', 'string', 'max:255'],
            'country' => ['nullable', 'string', 'max:100'],
            'country_code' => ['nullable', 'string', 'size:2', 'alpha'],
            'tax_id' => ['nullable', 'string', 'max:50'],
            'nif' => ['nullable', 'string', 'max:50'],
            'website' => ['nullable', 'url:http,https', 'max:255'],
            'facebook_link' => ['nullable', 'url:http,https', 'max:255'],
            'instagram_link' => ['nullable', 'url:http,https', 'max:255'],
            'linkedin_link' => ['nullable', 'url:http,https', 'max:255'],
            'twitter_link' => ['nullable', 'url:http,https', 'max:255'],
            'notes' => ['nullable', 'string', 'max:5000'],
        ];
    }

    protected function prepareForValidation(): void
    {
        $countryCode = $this->input('country_code');

        if (is_string($countryCode)) {
            $this->merge(['country_code' => mb_strtoupper($countryCode)]);
        }
    }
}
