<?php

declare(strict_types=1);

namespace Modules\CourseScripts\Application\Commands;

use Modules\CourseScripts\Domain\Enums\SourceDocumentKind;
use Modules\CourseScripts\Domain\Exceptions\CourseNotFoundException;
use Modules\CourseScripts\Domain\Ports\CourseRepositoryPort;
use Shared\Domain\Ports\StoragePort;

/**
 * Removes a content file or style reference. The index itself cannot be
 * detached: it is the course's origin (FR-8).
 */
final readonly class DetachSourceDocumentHandler
{
    public function __construct(
        private CourseRepositoryPort $courses,
        private StoragePort $storage,
    ) {}

    /**
     * @throws CourseNotFoundException
     */
    public function handle(string $courseUuid, string $documentUuid, int $userId): void
    {
        $document = $this->courses->findOwnedDocument($courseUuid, $documentUuid, $userId);

        if ($document === null || $document->kind === SourceDocumentKind::Index) {
            throw new CourseNotFoundException;
        }

        $this->courses->deleteDocument($document);
        $this->storage->delete($document->path);
    }
}
