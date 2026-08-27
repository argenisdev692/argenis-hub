<?php

declare(strict_types=1);

namespace Modules\ContactSupport\Infrastructure\Http\Requests;

use Illuminate\Contracts\Validation\Validator;
use Illuminate\Foundation\Http\FormRequest;

/**
 * Every field is optional — the inbox flips `readed` / `is_spam` on their own,
 * and a correction touches only the fields that changed. At least one editable
 * field must be present.
 */
final class UpdateContactSupportRequest extends FormRequest
{
    /**
     * Authorization is enforced by the route middleware (permission:UPDATE_CONTACT_SUPPORTS).
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
            'first_name' => ['sometimes', 'required', 'string', 'max:255'],
            'last_name' => ['sometimes', 'required', 'string', 'max:255'],
            'email' => ['sometimes', 'required', 'email:rfc', 'max:255'],
            'phone' => ['sometimes', 'required', 'string', 'max:20', 'regex:/^\+?[0-9]{7,15}$/'],
            'subject' => ['sometimes', 'required', 'string', 'max:150'],
            'message' => ['sometimes', 'required', 'string', 'max:5000'],
            'sms_consent' => ['sometimes', 'boolean'],
            'readed' => ['sometimes', 'boolean'],
            'is_spam' => ['sometimes', 'boolean'],
        ];
    }

    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator): void {
            $provided = array_intersect(array_keys($this->all()), [
                'first_name', 'last_name', 'email', 'phone', 'subject', 'message',
                'sms_consent', 'readed', 'is_spam',
            ]);

            if ($provided === []) {
                $validator->errors()->add('subject', 'At least one field must be provided.');
            }
        });
    }
}
