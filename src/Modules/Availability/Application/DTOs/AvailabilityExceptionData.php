<?php

declare(strict_types=1);

namespace Modules\Availability\Application\DTOs;

use Illuminate\Validation\Rule;
use Spatie\LaravelData\Attributes\MapInputName;
use Spatie\LaravelData\Data;
use Spatie\LaravelData\Mappers\SnakeCaseMapper;

/**
 * Create/update payload for a date exception. A closure (`is_available = false`)
 * needs no hours; a forced-open day (`is_available = true`) MUST carry its own
 * `start_time`/`end_time`. Only one ACTIVE exception may exist per date — the
 * unique rule ignores soft-deleted rows and the row being edited.
 */
#[MapInputName(SnakeCaseMapper::class)]
final class AvailabilityExceptionData extends Data
{
    public function __construct(
        public string $date,
        public bool $isAvailable = false,
        public ?string $startTime = null,
        public ?string $endTime = null,
        public ?string $reason = null,
    ) {}

    /**
     * @return array<string, mixed>
     */
    public static function rules(): array
    {
        $uuid = request()->route('uuid');

        // A `nullable` field short-circuits its remaining rules when null, which
        // would silently skip a "required when open" check — so the hours' rules
        // are branched on the intent instead: mandatory for a forced-open day,
        // ignored (and cleared by the handler) for a closure.
        $open = request()->boolean('is_available');

        // A brand-new exception may only be scheduled from today onward; editing
        // stays open so an already-past exception can still be adjusted.
        $isCreate = $uuid === null;

        return [
            'date' => [
                'required',
                'date_format:Y-m-d',
                ...($isCreate ? ['after_or_equal:today'] : []),
                Rule::unique('availability_exceptions', 'date')
                    ->ignore($uuid, 'uuid')
                    ->whereNull('deleted_at'),
            ],
            'is_available' => ['required', 'boolean'],
            'start_time' => $open
                ? ['required', 'date_format:H:i']
                : ['nullable', 'date_format:H:i'],
            'end_time' => $open
                ? ['required', 'date_format:H:i', 'after:start_time']
                : ['nullable', 'date_format:H:i'],
            'reason' => ['nullable', 'string', 'max:255'],
        ];
    }
}
