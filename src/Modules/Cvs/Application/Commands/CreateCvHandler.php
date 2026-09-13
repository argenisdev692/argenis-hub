<?php

declare(strict_types=1);

namespace Modules\Cvs\Application\Commands;

use Illuminate\Validation\ValidationException;
use Modules\Cvs\Application\DTOs\UploadCvData;
use Modules\Cvs\Domain\Enums\CvFileType;
use Modules\Cvs\Domain\Ports\CvRepositoryPort;
use Modules\Cvs\Domain\Ports\CvTextExtractorPort;
use Modules\Cvs\Infrastructure\Persistence\Eloquent\Models\CvEloquentModel;
use Shared\Domain\Ports\StoragePort;
use Throwable;

/**
 * Persists a new CV. The file is uploaded to private R2 first; if the database
 * write then fails, the object is deleted again so no orphaned résumé (PII)
 * lingers in the bucket (OWASP §10).
 */
final readonly class CreateCvHandler
{
    public function __construct(
        private CvRepositoryPort $cvs,
        private StoragePort $storage,
        private CvTextExtractorPort $extractor,
    ) {}

    #[\NoDiscard]
    public function handle(UploadCvData $data, int $userId): CvEloquentModel
    {
        $file = $data->file ?? throw ValidationException::withMessages([
            'file' => __('A CV file (PDF or Markdown) is required.'),
        ]);

        $fileType = CvFileType::fromExtension($file->getClientOriginalExtension());
        $rawText = $this->extractor->extract($fileType, $file);
        $filePath = $this->storage->putFile('cvs', $file, 'private');

        try {
            return $this->cvs->create([
                'title' => $data->normalizedTitle(),
                'niche' => $data->niche,
                'is_primary' => $data->isPrimary,
                'file_path' => $filePath,
                'file_type' => $fileType,
                'original_filename' => $file->getClientOriginalName(),
                'raw_text' => $rawText,
                'user_id' => $userId,
            ]);
        } catch (Throwable $exception) {
            $this->storage->delete($filePath);

            throw $exception;
        }
    }
}
