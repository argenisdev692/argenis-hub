<?php

declare(strict_types=1);

namespace Modules\CourseScripts\Application\DTOs;

use Modules\CourseScripts\Domain\Enums\SourceDocumentKind;
use Modules\CourseScripts\Infrastructure\Persistence\Eloquent\Models\CourseSourceDocumentEloquentModel;
use Spatie\LaravelData\Attributes\MapOutputName;
use Spatie\LaravelData\Data;
use Spatie\LaravelData\Mappers\SnakeCaseMapper;

/**
 * Metadata of an uploaded file. Storage path, checksum and extracted text
 * never leave the backend (OWASP §12).
 */
#[MapOutputName(SnakeCaseMapper::class)]
final class SourceDocumentData extends Data
{
    public function __construct(
        public string $uuid,
        public SourceDocumentKind $kind,
        public string $originalName,
        public string $mime,
        public int $sizeBytes,
        public ?string $videoUuid,
    ) {}

    /**
     * @param  array<int, string>  $videoUuidsById
     */
    public static function fromModel(CourseSourceDocumentEloquentModel $document, array $videoUuidsById = []): self
    {
        return new self(
            uuid: $document->uuid,
            kind: $document->kind,
            originalName: $document->original_name,
            mime: $document->mime,
            sizeBytes: $document->size_bytes,
            videoUuid: $document->course_video_id !== null ? ($videoUuidsById[$document->course_video_id] ?? null) : null,
        );
    }
}
