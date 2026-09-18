<?php

declare(strict_types=1);

namespace Modules\LeadScout\Infrastructure\Http\Controllers;

use Illuminate\Http\JsonResponse;
use Modules\LeadScout\Application\DTOs\UpdateChannelData;
use Modules\LeadScout\Domain\Enums\ChannelStatus;
use Modules\LeadScout\Infrastructure\Persistence\Eloquent\Models\ScoutContactChannelEloquentModel;

/**
 * Channel re-verification (spec US-12, plan §5): a channel that no longer
 * exists is marked `broken` and stops being recommended.
 */
final readonly class ChannelController
{
    public function update(string $uuid, UpdateChannelData $data): JsonResponse
    {
        $channel = ScoutContactChannelEloquentModel::query()->where('uuid', $uuid)->firstOrFail();
        $channel->update(['status' => ChannelStatus::from($data->status)->value]);

        return response()->json(['data' => [
            'uuid' => $channel->uuid,
            'type' => $channel->channel_type->value,
            'status' => $channel->status->value,
        ]]);
    }
}
