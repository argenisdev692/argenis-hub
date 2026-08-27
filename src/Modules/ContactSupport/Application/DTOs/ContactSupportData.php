<?php

declare(strict_types=1);

namespace Modules\ContactSupport\Application\DTOs;

use Modules\ContactSupport\Infrastructure\Persistence\Eloquent\Models\ContactSupportEloquentModel;
use Spatie\LaravelData\Attributes\MapOutputName;
use Spatie\LaravelData\Data;
use Spatie\LaravelData\Mappers\SnakeCaseMapper;

/**
 * Admin-facing representation of an inbound support request — the allowlist
 * behind every `/data/admin/contact-supports` response. The auto-increment `id`
 * and the owning `user_id` never cross this boundary (OWASP §12).
 */
#[MapOutputName(SnakeCaseMapper::class)]
final class ContactSupportData extends Data
{
    /**
     * @param  array<int, string>|null  $spamReasons
     */
    public function __construct(
        public readonly string $uuid,
        public readonly string $firstName,
        public readonly string $lastName,
        public readonly string $email,
        public readonly string $phone,
        public readonly string $subject,
        public readonly string $message,
        public readonly bool $smsConsent,
        public readonly bool $readed,
        public readonly bool $isSpam,
        public readonly int $spamScore,
        public readonly ?array $spamReasons,
        public readonly ?string $createdAt,
        public readonly ?string $updatedAt,
        public readonly ?string $deletedAt,
    ) {}

    public static function fromModel(ContactSupportEloquentModel $support): self
    {
        return new self(
            uuid: $support->uuid,
            firstName: $support->first_name,
            lastName: $support->last_name,
            email: $support->email,
            phone: $support->phone,
            subject: $support->subject,
            message: $support->message,
            smsConsent: $support->sms_consent,
            readed: $support->readed,
            isSpam: $support->is_spam,
            spamScore: $support->spam_score,
            spamReasons: $support->spam_reasons,
            createdAt: $support->created_at?->toIso8601String(),
            updatedAt: $support->updated_at?->toIso8601String(),
            deletedAt: $support->deleted_at?->toIso8601String(),
        );
    }
}
