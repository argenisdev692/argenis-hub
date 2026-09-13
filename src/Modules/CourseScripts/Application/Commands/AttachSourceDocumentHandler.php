<?php

declare(strict_types=1);

namespace Modules\CourseScripts\Application\Commands;

use Illuminate\Contracts\Config\Repository as Config;
use Illuminate\Support\Str;
use Modules\CourseScripts\Application\DTOs\SourceDocumentData;
use Modules\CourseScripts\Domain\Enums\SourceDocumentKind;
use Modules\CourseScripts\Domain\Exceptions\CourseNotFoundException;
use Modules\CourseScripts\Domain\Exceptions\EncryptedPdfException;
use Modules\CourseScripts\Domain\Exceptions\NoTextLayerException;
use Modules\CourseScripts\Domain\Exceptions\SourceDocumentLimitException;
use Modules\CourseScripts\Domain\Ports\CourseRepositoryPort;
use Modules\CourseScripts\Domain\Ports\DocumentTextExtractorPort;
use Modules\CourseScripts\Domain\ValueObjects\IncomingDocument;
use Shared\Domain\Ports\StoragePort;
use Throwable;

/**
 * Adds a content file (FR-1b) or a style reference (FR-10) to an existing
 * course. The text is extracted before anything is stored.
 */
final readonly class AttachSourceDocumentHandler
{
    public function __construct(
        private CourseRepositoryPort $courses,
        private DocumentTextExtractorPort $extractor,
        private StoragePort $storage,
        private Config $config,
    ) {}

    /**
     * @throws CourseNotFoundException
     * @throws SourceDocumentLimitException
     * @throws EncryptedPdfException
     * @throws NoTextLayerException
     */
    public function handle(string $courseUuid, IncomingDocument $document, SourceDocumentKind $kind, int $userId): SourceDocumentData
    {
        $course = $this->courses->findOwned($courseUuid, $userId) ?? throw new CourseNotFoundException;

        $limit = (int) $this->config->get($kind === SourceDocumentKind::StyleReference
            ? 'course-scripts.uploads.max_style_references'
            : 'course-scripts.uploads.max_content_files', 10);

        if ($this->courses->countDocuments($course, $kind) >= $limit) {
            throw new SourceDocumentLimitException($limit);
        }

        $text = $this->extractor->extract($document->localPath, $document->mimeType);
        $documentUuid = (string) Str::uuid7();

        $path = $this->storage->put(
            sprintf('%s/%s/sources/%s/%s', (string) $this->config->get('course-scripts.uploads.disk_directory', 'course-scripts'), $course->uuid, $documentUuid, $document->safeFileName()),
            (string) file_get_contents($document->localPath),
        );

        try {
            $stored = $this->courses->addDocument($course, [
                'uuid' => $documentUuid,
                'kind' => $kind,
                'original_name' => mb_substr($document->originalName, 0, 255),
                'path' => $path,
                'mime' => $document->mimeType,
                'size_bytes' => $document->sizeBytes,
                'checksum' => $document->checksum(),
                'extracted_text' => $text,
                'video_number' => $kind === SourceDocumentKind::Content ? $document->videoNumber : null,
            ]);
        } catch (Throwable $exception) {
            $this->storage->delete($path);

            throw $exception;
        }

        $videoUuid = $stored->course_video_id === null ? [] : [$stored->course_video_id => (string) $stored->video()->value('uuid')];

        return SourceDocumentData::fromModel($stored, $videoUuid);
    }
}
