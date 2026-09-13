<?php

declare(strict_types=1);

namespace Modules\CourseScripts\Application\DTOs;

use Spatie\LaravelData\Attributes\MapInputName;
use Spatie\LaravelData\Attributes\Validation\Max;
use Spatie\LaravelData\Data;
use Spatie\LaravelData\Mappers\SnakeCaseMapper;

#[MapInputName(SnakeCaseMapper::class)]
final class UpdateCourseNotesData extends Data
{
    public function __construct(
        #[Max(60000)]
        public ?string $courseNotes = null,
    ) {}
}
