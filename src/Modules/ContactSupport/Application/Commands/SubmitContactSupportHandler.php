<?php

declare(strict_types=1);

namespace Modules\ContactSupport\Application\Commands;

use Modules\ContactSupport\Application\DTOs\PublicContactSupportData;
use Modules\ContactSupport\Infrastructure\Persistence\Eloquent\Models\ContactSupportEloquentModel;

/**
 * Stores an inbound request from the public landing-page form. The message
 * always lands as unread ham (`readed = false`, `is_spam = false`,
 * `spam_score = 0`); the anti-spam verdict columns exist for a future scoring
 * pipeline and are not this handler's concern. `$userId` is the acting user
 * when a signed-in visitor submits, otherwise `null`.
 */
final readonly class SubmitContactSupportHandler
{
    /**
     * @param  array{
     *     first_name: string,
     *     last_name: string,
     *     email: string,
     *     phone: string,
     *     subject: string,
     *     message: string,
     *     sms_consent?: bool
     * }  $attributes
     */
    #[\NoDiscard('handle() returns the submission acknowledgement.')]
    public function handle(array $attributes, ?int $userId = null): PublicContactSupportData
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
            'readed' => false,
            'is_spam' => false,
            'spam_score' => 0,
            'spam_reasons' => null,
        ]);

        return PublicContactSupportData::fromModel($support);
    }
}
