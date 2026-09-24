<?php

declare(strict_types=1);

namespace Modules\CvJobStudio\Application\Commands;

use Modules\CvJobStudio\Domain\Exceptions\VersionNotFoundException;
use Modules\CvJobStudio\Domain\Ports\CvVersionDocumentPort;
use Modules\CvJobStudio\Domain\Ports\StudioCvRepositoryPort;
use Shared\Domain\Ports\StoragePort;

/**
 * Version export (T-074…T-079, FR-22): the document port renders DOCX / PDF
 * and verifies the text is machine readable (SC-5, SC-6). R2 is the final
 * destination; signed URLs only (OWASP §8). The object key is stable per
 * version + format, so a re-export replaces the file in place.
 */
final readonly class ExportCvVersionHandler
{
    public function __construct(
        private CvVersionDocumentPort $documents,
        private StoragePort $storage,
        private StudioCvRepositoryPort $cvs,
    ) {}

    /**
     * @param  'docx'|'pdf'  $format
     * @param  array{name: string, lines: list<string>}|null  $header  ATS
     *                                                                 contact block; null renders the sections without a header.
     * @return array{uuid: string, path: string, verified: bool}
     */
    #[\NoDiscard]
    public function handle(string $versionUuid, string $format, int $userId, ?array $header = null): array
    {
        $version = $this->cvs->versionForUser($versionUuid, $userId)
            ?? throw new VersionNotFoundException("Version {$versionUuid} not found.");

        $document = $this->documents->render($format, $version->content, $header);
        $path = "studio-exports/{$version->uuid}.{$format}";

        $this->storage->put($path, $document['bytes'], 'private');

        return $this->cvs->recordExport([
            'user_id' => $userId,
            'cv_version_id' => $version->id,
            'format' => $format,
            'language' => $version->language,
            'disk' => 'r2',
            'path' => $path,
            'bytes' => strlen($document['bytes']),
            'text_extraction_verified' => $document['verified'],
            'extracted_char_count' => $document['extracted_chars'],
        ]);
    }
}
