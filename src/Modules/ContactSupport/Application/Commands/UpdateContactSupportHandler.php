<?php

declare(strict_types=1);

namespace Modules\ContactSupport\Application\Commands;

use Illuminate\Database\Eloquent\ModelNotFoundException;
use Modules\ContactSupport\Application\DTOs\ContactSupportData;
use Modules\ContactSupport\Infrastructure\Persistence\Eloquent\Models\ContactSupportEloquentModel;

/**
 * Admin edit / triage. Only the keys present in `$attributes` are written, so
 * the common case — flipping `readed` or `is_spam` from the inbox — never has
 * to resend the whole record.
 */
final readonly class UpdateContactSupportHandler
{
    /**
     * @param  array{
     *     first_name?: string,
     *     last_name?: string,
     *     email?: string,
     *     phone?: string,
     *     subject?: string,
     *     message?: string,
     *     sms_consent?: bool,
     *     readed?: bool,
     *     is_spam?: bool
     * }  $attributes
     *
     * @throws ModelNotFoundException<ContactSupportEloquentModel>
     */
    #[\NoDiscard('handle() returns the updated request.')]
    public function handle(string $uuid, array $attributes): ContactSupportData
    {
        $support = ContactSupportEloquentModel::query()->where('uuid', $uuid)->firstOrFail();

        $editable = array_intersect_key($attributes, array_flip([
            'first_name', 'last_name', 'email', 'phone', 'subject', 'message',
            'sms_consent', 'readed', 'is_spam',
        ]));

        if ($editable !== []) {
            $support->update($editable);
        }

        return ContactSupportData::fromModel($support);
    }
}
