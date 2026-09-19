<?php

declare(strict_types=1);

namespace Modules\LeadScout\Domain\Entities;

use Modules\LeadScout\Domain\Enums\ChannelAudience;
use Modules\LeadScout\Domain\Enums\ChannelStatus;
use Modules\LeadScout\Domain\Enums\ChannelType;

/**
 * A way to reach a company, detected on its public pages (spec US-12,
 * FR-31). The module never fills or submits forms (FR-32).
 */
final readonly class ContactChannel
{
    /**
     * @param  list<string>|null  $formFields
     */
    public function __construct(
        public int $id,
        public string $uuid,
        public int $companyId,
        public ChannelType $channelType,
        public ?string $url,
        public ?string $genericEmail,
        public ?array $formFields,
        public bool $hasCaptcha,
        public ?ChannelAudience $audience,
        public ?string $evidenceUrl,
        public ?string $evidenceExcerpt,
        public ChannelStatus $status,
    ) {}
}
