<?php

declare(strict_types=1);

namespace Modules\CourseScripts\Application\DTOs;

use Illuminate\Validation\Rule;
use Spatie\LaravelData\Attributes\MapInputName;
use Spatie\LaravelData\Data;
use Spatie\LaravelData\Mappers\SnakeCaseMapper;

/**
 * Regenerate one video with the author's feedback, or force its practice pack
 * (US-14, US-7 · FR-38). `with_review` applies to this single-video run too.
 */
#[MapInputName(SnakeCaseMapper::class)]
final class RegenerateScriptData extends Data
{
    public function __construct(
        public string $writerProvider,
        public ?string $feedbackNote = null,
        public ?bool $withReview = null,
    ) {}

    /**
     * @return array<string, list<mixed>>
     */
    public static function rules(): array
    {
        return [
            'writer_provider' => ['required', 'string', Rule::in((array) config('course-scripts.providers.selectable_writers'))],
            'feedback_note' => ['nullable', 'string', 'max:4000'],
            'with_review' => ['nullable', 'boolean'],
        ];
    }

    public function reviewRequested(): bool
    {
        return $this->withReview ?? (bool) config('course-scripts.review.default_with_review', false);
    }
}
