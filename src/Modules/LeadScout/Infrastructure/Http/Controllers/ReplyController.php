<?php

declare(strict_types=1);

namespace Modules\LeadScout\Infrastructure\Http\Controllers;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Modules\LeadScout\Application\Commands\RecordReplyHandler;
use Modules\LeadScout\Application\DTOs\OutreachData;
use Modules\LeadScout\Application\DTOs\RecordReplyData;

/**
 * Reply intake (spec FR-42/43, T084).
 */
final readonly class ReplyController
{
    public function store(Request $request, string $uuid, RecordReplyData $data, RecordReplyHandler $record): JsonResponse
    {
        $outreach = $record->handle($uuid, $data, (int) $request->user()->id);

        return response()->json(['data' => OutreachData::fromEntity($outreach)]);
    }
}
