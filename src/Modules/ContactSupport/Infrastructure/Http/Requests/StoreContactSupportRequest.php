<?php

declare(strict_types=1);

namespace Modules\ContactSupport\Infrastructure\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

final class StoreContactSupportRequest extends FormRequest
{
    /**
     * Authorization is enforced by the route middleware (permission:CREATE_CONTACT_SUPPORTS).
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
            'first_name' => ['required', 'string', 'max:255'],
            'last_name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'email:rfc', 'max:255'],
            'phone' => ['required', 'string', 'max:20', 'regex:/^\+?[0-9]{7,15}$/'],
            'subject' => ['required', 'string', 'max:150'],
            'message' => ['required', 'string', 'max:5000'],
            'sms_consent' => ['sometimes', 'boolean'],
            'readed' => ['sometimes', 'boolean'],
            'is_spam' => ['sometimes', 'boolean'],
        ];
    }
}
