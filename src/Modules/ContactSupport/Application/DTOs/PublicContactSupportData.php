<?php

declare(strict_types=1);

namespace Modules\ContactSupport\Application\DTOs;

use Modules\ContactSupport\Infrastructure\Persistence\Eloquent\Models\ContactSupportEloquentModel;
use Spatie\LaravelData\Attributes\MapOutputName;
use Spatie\LaravelData\Data;
use Spatie\LaravelData\Mappers\SnakeCaseMapper;

/**
 * The acknowledgement the public landing-page form receives after a successful
 * submission. Deliberately minimal: the reference `uuid` and the received
 * `subject`, nothing about triage state, spam scoring or the owner (OWASP §12
 * allowlist) — an unauthenticated caller has no use for any of it.
 */
#[MapOutputName(SnakeCaseMapper::class)]
final class PublicContactSupportData extends Data
{
    public function __construct(
        public readonly string $uuid,
        public readonly string $subject,
    ) {}

    public static function fromModel(ContactSupportEloquentModel $support): self
    {
        return new self(
            uuid: $support->uuid,
            subject: $support->subject,
        );
    }
}
