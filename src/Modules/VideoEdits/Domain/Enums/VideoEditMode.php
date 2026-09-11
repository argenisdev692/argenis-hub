<?php

declare(strict_types=1);

namespace Modules\VideoEdits\Domain\Enums;

/**
 * The three product modes (spec 001-video-edit §1, EX-4).
 *
 * All three exist from V1 so the roadmap is first-class in the data model;
 * `AiEdit` stays unavailable until V3 ships its decision producer.
 */
enum VideoEditMode: string
{
    case Merge = 'merge';
    case AutoEdit = 'auto_edit';
    case AiEdit = 'ai_edit';

    public function isAvailable(): bool
    {
        return $this !== self::AiEdit;
    }

    public function minimumSources(): int
    {
        return match ($this) {
            self::Merge => 2,
            self::AutoEdit, self::AiEdit => 1,
        };
    }

    /**
     * Merge joins clips without content analysis, so it takes no cut decisions.
     */
    public function acceptsCutDecisions(): bool
    {
        return $this !== self::Merge;
    }

    /**
     * @return list<string>
     */
    #[\NoDiscard]
    public static function availableValues(): array
    {
        return array_values(array_map(
            static fn (self $mode): string => $mode->value,
            array_filter(self::cases(), static fn (self $mode): bool => $mode->isAvailable()),
        ));
    }
}
