<?php

declare(strict_types=1);

namespace Modules\CourseScripts\Application\DTOs;

use Illuminate\Validation\Rule;
use Spatie\LaravelData\Attributes\MapInputName;
use Spatie\LaravelData\Data;
use Spatie\LaravelData\Mappers\SnakeCaseMapper;

/**
 * The writer picked for the bible proposal. Model names are never accepted
 * from the request (A2/D2).
 */
#[MapInputName(SnakeCaseMapper::class)]
final class PrepareCourseData extends Data
{
    public function __construct(
        public string $writerProvider,
    ) {}

    /**
     * @return array<string, list<mixed>>
     */
    public static function rules(): array
    {
        return [
            'writer_provider' => ['required', 'string', Rule::in((array) config('course-scripts.providers.selectable_writers'))],
        ];
    }
}
