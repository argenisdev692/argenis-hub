<?php

declare(strict_types=1);

namespace Modules\LeadScout\Infrastructure\Http\Controllers;

use Illuminate\Http\JsonResponse;
use Modules\LeadScout\Application\Commands\UpdateChannelStatusHandler;
use Modules\LeadScout\Application\DTOs\UpdateChannelData;

/**
 * Channel re-verification (spec US-12, plan §5): a channel that no longer
 * exists is marked `broken` and stops being recommended.
 */
final readonly class ChannelController
{
    public function update(string $uuid, UpdateChannelData $data, UpdateChannelStatusHandler $update): JsonResponse
    {
        $channel = $update->handle($uuid, $data);

        return response()->json(['data' => [
            'uuid' => $channel->uuid,
            'type' => $channel->channelType->value,
            'status' => $channel->status->value,
        ]]);
    }
}
