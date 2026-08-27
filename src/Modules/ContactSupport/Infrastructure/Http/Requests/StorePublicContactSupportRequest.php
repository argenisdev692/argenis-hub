<?php

declare(strict_types=1);

namespace Modules\ContactSupport\Infrastructure\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Spatie\Honeypot\ProtectAgainstSpam;

/**
 * The public landing-page contact form. Unauthenticated: the route is throttled
 * and honeypot-protected ({@see ProtectAgainstSpam}), and this
 * request is the authoritative validation gate — the client-side Zod schema is
 * UX only (OWASP §3 / §15.5). Triage columns (`readed`, `is_spam`, `spam_*`)
 * are NOT accepted here; the submission pipeline owns them.
 */
final class StorePublicContactSupportRequest extends FormRequest
{
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
            'message' => ['required', 'string', 'min:10', 'max:5000'],
            'sms_consent' => ['sometimes', 'boolean'],
        ];
    }
}
