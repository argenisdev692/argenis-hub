<?php

declare(strict_types=1);

namespace Modules\CourseScripts\Application\DTOs;

use Spatie\LaravelData\Attributes\MapInputName;
use Spatie\LaravelData\Attributes\Validation\Max;
use Spatie\LaravelData\Attributes\Validation\Min;
use Spatie\LaravelData\Attributes\Validation\Required;
use Spatie\LaravelData\Data;
use Spatie\LaravelData\Mappers\SnakeCaseMapper;

/**
 * An author's edit of a video's brief and notes (US-2 · FR-6). Every field is
 * sent; the stored brief becomes exactly this.
 */
#[MapInputName(SnakeCaseMapper::class)]
final class UpdateVideoBriefData extends Data
{
    /**
     * @param  list<string|null>|null  $learningAreas
     * @param  list<string|null>|null  $audienceObjectives
     * @param  list<string|null>|null  $mandatoryContent
     * @param  list<string|null>|null  $errorsToAvoid
     */
    public function __construct(
        #[Required, Max(255)]
        public string $title,
        #[Max(255)]
        public ?string $topic = null,
        #[Min(1), Max(180)]
        public ?int $declaredDurationMinutes = null,
        #[Max(4000)]
        public ?string $objective = null,
        #[Max(30)]
        public ?array $learningAreas = null,
        #[Max(30)]
        public ?array $audienceObjectives = null,
        #[Max(30)]
        public ?array $mandatoryContent = null,
        #[Max(30)]
        public ?array $errorsToAvoid = null,
        #[Max(4000)]
        public ?string $expectedResult = null,
        #[Max(60000)]
        public ?string $notes = null,
    ) {}

    /**
     * @return array<string, list<string>>
     */
    public static function rules(): array
    {
        return [
            'learning_areas.*' => ['nullable', 'string', 'max:500'],
            'audience_objectives.*' => ['nullable', 'string', 'max:500'],
            'mandatory_content.*' => ['nullable', 'string', 'max:500'],
            'errors_to_avoid.*' => ['nullable', 'string', 'max:500'],
        ];
    }

    /**
     * @return array<string, mixed>
     */
    #[\NoDiscard]
    public function toAttributes(): array
    {
        $clean = static fn (?array $items): array => array_values(array_filter(
            array_map(static fn (mixed $item): string => trim((string) $item), $items ?? []),
            static fn (string $item): bool => $item !== '',
        ));
        $nullable = static fn (?string $value): ?string => $value === null || trim($value) === '' ? null : trim($value);

        return [
            'title' => trim($this->title),
            'topic' => $nullable($this->topic),
            'declared_duration_minutes' => $this->declaredDurationMinutes,
            'objective' => $nullable($this->objective),
            'learning_areas' => $clean($this->learningAreas),
            'audience_objectives' => $clean($this->audienceObjectives),
            'mandatory_content' => $clean($this->mandatoryContent),
            'errors_to_avoid' => $clean($this->errorsToAvoid),
            'expected_result' => $nullable($this->expectedResult),
            'notes' => $nullable($this->notes),
        ];
    }
}
