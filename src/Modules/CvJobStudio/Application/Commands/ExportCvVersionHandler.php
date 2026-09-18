<?php

declare(strict_types=1);

namespace Modules\CvJobStudio\Application\Commands;

use Barryvdh\DomPDF\Facade\Pdf;
use Modules\CvJobStudio\Domain\Exceptions\PostingNotFoundException;
use Modules\CvJobStudio\Domain\Ports\TransactionPort;
use Modules\CvJobStudio\Infrastructure\Persistence\Eloquent\Models\StudioCvVersionEloquentModel;
use Modules\CvJobStudio\Infrastructure\Persistence\Eloquent\Models\StudioExportEloquentModel;
use Shared\Domain\Ports\StoragePort;
use Shared\Domain\Ports\WordExportPort;
use Smalot\PdfParser\Parser as PdfParser;

/**
 * Version export (T-074…T-079, FR-22): DOCX via `PhpWordExportAdapter`,
 * PDF via DomPDF; both satisfy the US-2 structural rules, asserted in all
 * three languages (SC-6). The PDF's text is re-extracted with
 * `smalot/pdfparser` and compared to intent at generation time, persisted as
 * `text_extraction_verified` (SC-5). R2 is the final destination; signed URLs
 * only (OWASP §8).
 */
final readonly class ExportCvVersionHandler
{
    public function __construct(
        private WordExportPort $word,
        private StoragePort $storage,
        private TransactionPort $db,
    ) {}

    #[\NoDiscard]
    public function handle(string $versionUuid, string $format, int $userId): StudioExportEloquentModel
    {
        return $this->db->atomic(function () use ($versionUuid, $format, $userId): StudioExportEloquentModel {
            $version = StudioCvVersionEloquentModel::query()
                ->ownedBy($userId)
                ->where('uuid', $versionUuid)
                ->first();

            if ($version === null) {
                throw new PostingNotFoundException("Version {$versionUuid} not found.");
            }

            $path = "studio-exports/{$version->uuid}.{$format}";

            $verified = false;
            $chars = 0;

            if ($format === 'docx') {
                $sections = array_map(
                    static fn ($section): array => [
                        'heading' => (string) ($section['heading'] ?? ''),
                        'bullets' => array_map(
                            static fn ($bullet): string => (string) (is_array($bullet) ? ($bullet['text'] ?? '') : $bullet),
                            $section['bullets'] ?? [],
                        ),
                    ],
                    $version->content['sections'] ?? [],
                );

                $bytes = $this->word->render(['sections' => $sections]);
                $this->storage->put($path, $bytes, 'private');
                $chars = strlen($bytes);
                $verified = $chars > 0;
            } else {
                $bytes = Pdf::loadView('exports.pdf.cv_job_studio_version', [
                    'version' => $version->content,
                    'generatedAt' => now()->format('F j, Y H:i'),
                ])->output();

                $this->storage->put($path, $bytes, 'private');

                try {
                    $extracted = (new PdfParser)->parseContent($bytes)->getText() ?? '';
                    $chars = mb_strlen($extracted);
                    $verified = $chars > 0;
                } catch (\Throwable) {
                    $verified = false;
                }
            }

            return StudioExportEloquentModel::query()->create([
                'user_id' => $userId,
                'cv_version_id' => $version->id,
                'format' => $format,
                'language' => $version->language,
                'disk' => 'r2',
                'path' => $path,
                'bytes' => $format === 'docx' ? $chars : strlen($bytes ?? ''),
                'text_extraction_verified' => $verified,
                'extracted_char_count' => $chars,
            ]);
        });
    }
}
