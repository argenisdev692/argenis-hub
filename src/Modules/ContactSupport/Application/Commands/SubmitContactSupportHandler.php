<?php

declare(strict_types=1);

namespace Modules\ContactSupport\Application\Commands;

use Modules\ContactSupport\Application\DTOs\PublicContactSupportData;
use Modules\ContactSupport\Domain\Events\ContactSupportSubmitted;
use Modules\ContactSupport\Domain\Spam\SpamGuard;
use Modules\ContactSupport\Infrastructure\Persistence\Eloquent\Models\ContactSupportEloquentModel;

/**
 * Stores an inbound request from the public landing-page form.
 *
 * The submission has already cleared the bot traps on the route (honeypot +
 * per-IP throttle). {@see SpamGuard} then scores the content — link floods,
 * keyword blasts, throw-away inboxes — and the verdict (`is_spam`,
 * `spam_score`, `spam_reasons`) is written with the row. A
 * {@see ContactSupportSubmitted} event fires afterwards so the operator inbox
 * notification is sent off the request path.
 *
 * `$userId` is the acting user when a signed-in visitor submits, otherwise
 * `null`.
 */
final readonly class SubmitContactSupportHandler
{
    public function __construct(private SpamGuard $spamGuard) {}

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
        $assessment = $this->spamGuard->assess(
            firstName: $attributes['first_name'],
            lastName: $attributes['last_name'],
            email: $attributes['email'],
            phone: $attributes['phone'],
            subject: $attributes['subject'],
            message: $attributes['message'],
        );

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
            'is_spam' => $assessment->isSpam,
            'spam_score' => $assessment->score,
            'spam_reasons' => $assessment->reasonsForStorage(),
        ]);

        event(new ContactSupportSubmitted($support->uuid, $assessment->isSpam));

        return PublicContactSupportData::fromModel($support);
    }
}
