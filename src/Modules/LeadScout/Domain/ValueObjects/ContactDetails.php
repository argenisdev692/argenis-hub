<?php

declare(strict_types=1);

namespace Modules\LeadScout\Domain\ValueObjects;

use Modules\LeadScout\Domain\Enums\EmailKind;
use Modules\LeadScout\Domain\Enums\RoleCategory;

/**
 * Operator-entered decisor fields (spec US-11 CA-7). A null `$isPrimary`
 * keeps the current flag on update.
 */
final readonly class ContactDetails
{
    public function __construct(
        public string $fullName,
        public string $roleTitle,
        public RoleCategory $roleCategory,
        public ?bool $isPrimary,
        public ?string $publishedEmail,
        public ?string $publicProfileUrl,
    ) {}

    public function emailKind(): ?EmailKind
    {
        return $this->publishedEmail === null ? null : EmailKind::Nominative;
    }
}
