<?php

declare(strict_types=1);

namespace Modules\ContactSupport\Application\Commands;

use Modules\ContactSupport\Application\DTOs\ContactSupportData;
use Modules\ContactSupport\Infrastructure\Persistence\Eloquent\Models\ContactSupportEloquentModel;

/**
 * Admin-side creation — an operator logging a request that arrived off-channel
 * (a phone call, a forwarded email). `$userId` is the acting operator.
 */
final readonly class CreateContactSupportHandler
{
    /**
     * @param  array{
     *     first_name: string,
     *     last_name: string,
     *     email: string,
     *     phone: string,
     *     subject: string,
     *     message: string,
     *     sms_consent?: bool,
     *     readed?: bool,
     *     is_spam?: bool
     * }  $attributes
     */
    #[\NoDiscard('handle() returns the created request.')]
    public function handle(array $attributes, int $userId): ContactSupportData
    {
        $support = ContactSupportEloquentModel::query()->create([
            'user_id' => $userId,
            'first_name' => $attributes['first_name'],
            'last_name' => $attributes['last_name'],
            'email' => $attributes['email'],
            'phone' => $attributes['phone'],
            'subject' => $attributes['subject'],
            'message' => $attributes['message'],
            'sms_consent' => $attributes['sms_consent'] ?? false,
            'readed' => $attributes['readed'] ?? false,
            'is_spam' => $attributes['is_spam'] ?? false,
            'spam_score' => 0,
            'spam_reasons' => null,
        ]);

        return ContactSupportData::fromModel($support);
    }
}
