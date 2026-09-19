<?php

declare(strict_types=1);

namespace Modules\LeadScout\Domain\Ports;

use Modules\LeadScout\Domain\Entities\ContactChannel;
use Modules\LeadScout\Domain\Enums\ChannelAudience;
use Modules\LeadScout\Domain\Enums\ChannelStatus;
use Modules\LeadScout\Domain\Enums\ChannelType;

interface ContactChannelRepositoryPort
{
    public function byUuid(string $uuid): ?ContactChannel;

    public function setStatus(ContactChannel $channel, ChannelStatus $status): ContactChannel;

    /**
     * @return list<ContactChannel> every status, oldest first
     */
    public function forCompany(int $companyId): array;

    /**
     * @return list<ContactChannel>
     */
    public function activeForCompany(int $companyId): array;

    /**
     * Stores a channel found on the company pages, or refreshes the
     * evidence of the same type + URL.
     *
     * @param  list<string>|null  $formFields
     * @return bool true when the channel is new
     */
    public function upsertDetected(
        int $companyId,
        ChannelType $type,
        ?string $url,
        ?string $genericEmail,
        ?array $formFields,
        bool $hasCaptcha,
        ?ChannelAudience $audience,
        ?string $evidenceUrl,
        ?string $evidenceExcerpt,
    ): bool;
}
