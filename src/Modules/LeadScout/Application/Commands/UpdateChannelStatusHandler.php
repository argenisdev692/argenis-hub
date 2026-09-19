<?php

declare(strict_types=1);

namespace Modules\LeadScout\Application\Commands;

use Modules\LeadScout\Application\DTOs\UpdateChannelData;
use Modules\LeadScout\Domain\Entities\ContactChannel;
use Modules\LeadScout\Domain\Enums\ChannelStatus;
use Modules\LeadScout\Domain\Exceptions\ChannelNotFoundException;
use Modules\LeadScout\Domain\Ports\ContactChannelRepositoryPort;

/**
 * Operator marks a detected channel active, broken or used (spec US-12).
 */
final readonly class UpdateChannelStatusHandler
{
    public function __construct(private ContactChannelRepositoryPort $channels) {}

    public function handle(string $channelUuid, UpdateChannelData $data): ContactChannel
    {
        $channel = $this->channels->byUuid($channelUuid) ?? throw new ChannelNotFoundException($channelUuid);

        return $this->channels->setStatus($channel, ChannelStatus::from($data->status));
    }
}
