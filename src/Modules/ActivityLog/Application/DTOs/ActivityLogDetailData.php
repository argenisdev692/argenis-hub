<?php

declare(strict_types=1);

namespace Modules\ActivityLog\Application\DTOs;

use Spatie\Activitylog\Models\Activity;
use Spatie\LaravelData\Attributes\MapOutputName;
use Spatie\LaravelData\Data;
use Spatie\LaravelData\Mappers\SnakeCaseMapper;

/**
 * Full read-only projection for the activity-log detail screen — the list shape
 * plus the actor type and the `properties` / `attribute_changes` JSON payloads.
 * Admin-gated (`permission:VIEW_ACTIVITY_LOGS`): these blobs hold whatever the
 * logging models declared in `logOnly([...])`, never raw secrets.
 */
#[MapOutputName(SnakeCaseMapper::class)]
final class ActivityLogDetailData extends Data
{
    /**
     * @param  array<string, mixed>|null  $properties
     * @param  array<string, mixed>|null  $attributeChanges
     */
    public function __construct(
        public readonly int $id,
        public readonly ?string $logName,
        public readonly string $description,
        public readonly ?string $event,
        public readonly ?string $subjectType,
        public readonly ?string $subjectId,
        public readonly ?string $causerId,
        public readonly ?string $causerType,
        public readonly ?string $causerLabel,
        public readonly ?array $properties,
        public readonly ?array $attributeChanges,
        public readonly ?string $createdAt,
        public readonly ?string $updatedAt,
    ) {}

    public static function fromActivity(Activity $activity): self
    {
        $base = ActivityLogData::fromActivity($activity);

        return new self(
            id: $base->id,
            logName: $base->logName,
            description: $base->description,
            event: $base->event,
            subjectType: $base->subjectType,
            subjectId: $base->subjectId,
            causerId: $base->causerId,
            causerType: ActivityLogData::shortType($activity->causer_type),
            causerLabel: $base->causerLabel,
            properties: $activity->properties?->toArray(),
            attributeChanges: self::rawJson($activity, 'attribute_changes'),
            createdAt: $base->createdAt,
            updatedAt: $activity->updated_at?->toIso8601String(),
        );
    }

    /**
     * Reads a JSON column the default Spatie model does not cast (this project's
     * migration adds `attribute_changes`).
     *
     * @return array<string, mixed>|null
     */
    private static function rawJson(Activity $activity, string $column): ?array
    {
        $value = $activity->getAttribute($column);

        return match (true) {
            is_array($value) => $value,
            is_string($value) => (is_array($decoded = json_decode($value, true)) ? $decoded : null),
            default => null,
        };
    }
}
