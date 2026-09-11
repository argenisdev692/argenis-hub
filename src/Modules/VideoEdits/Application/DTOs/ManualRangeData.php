<?php

declare(strict_types=1);

namespace Modules\VideoEdits\Application\DTOs;

use Spatie\LaravelData\Attributes\MapInputName;
use Spatie\LaravelData\Attributes\MapOutputName;
use Spatie\LaravelData\Data;
use Spatie\LaravelData\Mappers\SnakeCaseMapper;

/**
 * A time range the user wants removed, on the merged timeline (D7). Only
 * structure is checked on input; the real duration is checked at job start (P1).
 */
#[MapInputName(SnakeCaseMapper::class)]
#[MapOutputName(SnakeCaseMapper::class)]
final class ManualRangeData extends Data
{
    public function __construct(
        public int $startMs,
        public int $endMs,
        public ?string $note = null,
    ) {}

    /**
     * The stored `parameters.manual_ranges` entry — shared by create and retry.
     *
     * @return array{start_ms: int, end_ms: int, note: string|null}
     */
    public function toParameter(): array
    {
        return [
            'start_ms' => $this->startMs,
            'end_ms' => $this->endMs,
            'note' => $this->note === null ? null : trim($this->note),
        ];
    }

    /**
     * Structural rules shared by create (E2) and the correctable retry (E6).
     *
     * @return array<string, list<mixed>>
     */
    public static function listRules(string $prefix): array
    {
        return [
            $prefix => ['array', 'max:'.(int) config('video-edit.limits.max_manual_ranges')],
            "{$prefix}.*.start_ms" => ['required', 'integer', 'min:0'],
            "{$prefix}.*.end_ms" => ['required', 'integer', "gt:{$prefix}.*.start_ms"],
            "{$prefix}.*.note" => ['nullable', 'string', 'max:'.(int) config('video-edit.limits.max_range_note_length')],
        ];
    }
}
