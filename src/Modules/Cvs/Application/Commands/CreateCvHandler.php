<?php

declare(strict_types=1);

namespace Modules\Cvs\Application\Commands;

use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Modules\Cvs\Application\DTOs\UploadCvData;
use Modules\Cvs\Domain\Enums\CvFileType;
use Modules\Cvs\Domain\Ports\CvRepositoryPort;
use Modules\Cvs\Domain\Ports\CvTextExtractorPort;
use Modules\Cvs\Infrastructure\Persistence\Eloquent\Models\CvEloquentModel;
use Shared\Domain\Ports\StoragePort;

/**
 * Persists a new CV. File is uploaded to private R2 before the DB transaction.
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
        if ($data->file === null) {
            throw ValidationException::withMessages([
                'file' => __('A CV file (PDF or Markdown) is required.'),
            ]);
        }

        $file = $data->file;
        $fileType = CvFileType::fromExtension($file->getClientOriginalExtension());
        $originalFilename = $file->getClientOriginalName();
        $rawText = $this->extractor->extract($fileType, $file);
        $filePath = $this->storage->putFile('cvs', $file, 'private');

        return DB::transaction(function () use ($data, $userId, $filePath, $fileType, $rawText, $originalFilename): CvEloquentModel {
            if ($data->isPrimary) {
                $this->cvs->clearPrimaryForUser($userId);
            }

            return $this->cvs->create([
                'title' => $data->normalizedTitle(),
                'niche' => $data->niche,
                'is_primary' => $data->isPrimary,
                'file_path' => $filePath,
                'file_type' => $fileType,
                'original_filename' => $originalFilename,
                'raw_text' => $rawText,
                'user_id' => $userId,
            ]);
        });
    }
}
