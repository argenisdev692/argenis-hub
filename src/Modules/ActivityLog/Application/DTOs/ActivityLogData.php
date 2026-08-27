<?php

declare(strict_types=1);

namespace Modules\ActivityLog\Application\DTOs;

use Spatie\Activitylog\Models\Activity;
use Spatie\LaravelData\Attributes\MapOutputName;
use Spatie\LaravelData\Data;
use Spatie\LaravelData\Mappers\SnakeCaseMapper;

/**
 * Read-only list projection of one activity-log entry.
 *
 * The allowlist behind every `/activity-logs` response: the polymorphic
 * `causer` / `subject` relations are reduced to a short type + a human label so
 * no related model ever crosses the boundary (OWASP §12). Mapping lives in
 * `fromActivity()`, mirroring the sibling `ServiceData::fromModel()` convention,
 * and is reused by the list paginator, the detail projection and the export
 * transformer (DRY).
 */
#[MapOutputName(SnakeCaseMapper::class)]
final class ActivityLogData extends Data
{
    public function __construct(
        public readonly int $id,
        public readonly ?string $logName,
        public readonly string $description,
        public readonly ?string $event,
        public readonly ?string $subjectType,
        public readonly ?string $subjectId,
        public readonly ?string $causerId,
        public readonly ?string $causerLabel,
        public readonly ?string $createdAt,
    ) {}

    public static function fromActivity(Activity $activity): self
    {
        return new self(
            id: (int) $activity->id,
            logName: $activity->log_name,
            description: $activity->description,
            event: $activity->event,
            subjectType: self::shortType($activity->subject_type),
            subjectId: $activity->subject_id === null ? null : (string) $activity->subject_id,
            causerId: $activity->causer_id === null ? null : (string) $activity->causer_id,
            causerLabel: self::causerLabel($activity),
            createdAt: $activity->created_at?->toIso8601String(),
        );
    }

    public static function shortType(?string $type): ?string
    {
        return $type === null ? null : class_basename($type);
    }

    /**
     * Human label for the actor. `causer` is polymorphic: Users expose
     * first/last name, other models may expose `name` or `email`.
     */
    public static function causerLabel(Activity $activity): ?string
    {
        $causer = $activity->causer;

        return match (true) {
            $causer === null => null,
            isset($causer->first_name) => trim("{$causer->first_name} {$causer->last_name}") ?: ($causer->email ?? "#{$activity->causer_id}"),
            isset($causer->name) => (string) $causer->name,
            isset($causer->email) => (string) $causer->email,
            default => "#{$activity->causer_id}",
        };
    }
}
