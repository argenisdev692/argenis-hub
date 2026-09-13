<?php

declare(strict_types=1);

namespace Modules\CourseScripts\Application\Commands;

use Illuminate\Contracts\Config\Repository as Config;
use Illuminate\Support\Str;
use Modules\CourseScripts\Application\DTOs\StoreCourseInput;
use Modules\CourseScripts\Domain\Enums\SourceDocumentKind;
use Modules\CourseScripts\Domain\Exceptions\EncryptedPdfException;
use Modules\CourseScripts\Domain\Exceptions\MissingCourseTitleException;
use Modules\CourseScripts\Domain\Exceptions\NoTextLayerException;
use Modules\CourseScripts\Domain\Exceptions\UnrecognisableIndexException;
use Modules\CourseScripts\Domain\Ports\CourseRepositoryPort;
use Modules\CourseScripts\Domain\Ports\DocumentTextExtractorPort;
use Modules\CourseScripts\Domain\Ports\IndexDocumentParserPort;
use Modules\CourseScripts\Domain\Services\CourseIndexValidator;
use Modules\CourseScripts\Domain\ValueObjects\IncomingDocument;
use Modules\CourseScripts\Infrastructure\Persistence\Eloquent\Models\CourseEloquentModel;
use Shared\Domain\Ports\AuditPort;
use Shared\Domain\Ports\StoragePort;
use SplFileInfo;
use Throwable;

/**
 * Upload title + index + content files and persist the parsed course
 * (US-1 · FR-1…FR-8a).
 *
 * Everything that can reject the upload — parsing, validation, content
 * extraction, the title — runs BEFORE anything is stored, so a rejected upload
 * leaves no course and no file behind (FR-7). If persistence itself fails, the
 * files already stored are removed.
 */
final readonly class StoreCourseHandler
{
    public function __construct(
        private IndexDocumentParserPort $parser,
        private DocumentTextExtractorPort $extractor,
        private CourseIndexValidator $validator,
        private CourseRepositoryPort $courses,
        private StoragePort $storage,
        private AuditPort $audit,
        private Config $config,
    ) {}

    /**
     * @throws UnrecognisableIndexException
     * @throws EncryptedPdfException
     * @throws NoTextLayerException
     * @throws MissingCourseTitleException
     */
    #[\NoDiscard]
    public function handle(StoreCourseInput $input, int $userId, ?object $causer = null): CourseEloquentModel
    {
        $index = $this->parser->parse($input->index->localPath, $input->index->mimeType);
        $this->validator->validate($index);

        $title = $input->title
            |> (static fn (?string $title): string => trim((string) $title))
            |> (static fn (string $title): string => $title !== '' ? $title : trim((string) $index->title));

        if ($title === '') {
            throw new MissingCourseTitleException;
        }

        $contentTexts = array_map(
            fn (IncomingDocument $document): string => $this->extractor->extract($document->localPath, $document->mimeType),
            $input->contents,
        );

        $courseUuid = (string) Str::uuid7();
        $documents = [];

        try {
            $documents[] = $this->store($courseUuid, $input->index, SourceDocumentKind::Index, null);

            foreach ($input->contents as $position => $content) {
                $documents[] = $this->store($courseUuid, $content, SourceDocumentKind::Content, $contentTexts[$position]);
            }

            $course = $this->courses->createFromIndex(
                uuid: $courseUuid,
                userId: $userId,
                title: mb_substr($title, 0, (int) $this->config->get('course-scripts.structure.max_title_length', 255)),
                index: $index,
                defaultVideoMinutes: (int) $this->config->get('course-scripts.script.default_video_minutes', 8),
                documents: $documents,
            );
        } catch (Throwable $exception) {
            foreach ($documents as $document) {
                $this->storage->delete($document['path']);
            }

            throw $exception;
        }

        // Metadata only — never the index, the notes or file names (FR-55).
        $this->audit->log(
            'course_scripts.course_uploaded',
            $course,
            [
                'course_uuid' => $course->uuid,
                'video_count' => $index->pointCount(),
                'block_count' => count(array_filter($index->groups, static fn ($group): bool => ! $group->isImplicit)),
                'content_file_count' => count($input->contents),
                'index_mime' => $input->index->mimeType,
            ],
            $causer,
            'course_scripts.course',
        );

        return $course;
    }

    /**
     * @return array{uuid: string, kind: SourceDocumentKind, original_name: string, path: string, mime: string, size_bytes: int, checksum: string, extracted_text: ?string, video_number: ?int}
     */
    private function store(string $courseUuid, IncomingDocument $document, SourceDocumentKind $kind, ?string $extractedText): array
    {
        $documentUuid = (string) Str::uuid7();
        $directory = sprintf(
            '%s/%s/sources/%s',
            (string) $this->config->get('course-scripts.uploads.disk_directory', 'course-scripts'),
            $courseUuid,
            $documentUuid,
        );

        $stored = $this->storage->put(
            $directory.'/'.$document->safeFileName(),
            (string) file_get_contents((new SplFileInfo($document->localPath))->getPathname()),
        );

        return [
            'uuid' => $documentUuid,
            'kind' => $kind,
            'original_name' => mb_substr($document->originalName, 0, 255),
            'path' => $stored,
            'mime' => $document->mimeType,
            'size_bytes' => $document->sizeBytes,
            'checksum' => $document->checksum(),
            'extracted_text' => $extractedText,
            'video_number' => $kind === SourceDocumentKind::Content ? $document->videoNumber : null,
        ];
    }
}
