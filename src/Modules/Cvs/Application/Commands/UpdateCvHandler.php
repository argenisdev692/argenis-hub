<?php

declare(strict_types=1);

namespace Modules\Cvs\Application\Commands;

use Modules\Cvs\Application\DTOs\UploadCvData;
use Modules\Cvs\Domain\Enums\CvFileType;
use Modules\Cvs\Domain\Ports\CvRepositoryPort;
use Modules\Cvs\Domain\Ports\CvTextExtractorPort;
use Modules\Cvs\Infrastructure\Persistence\Eloquent\Models\CvEloquentModel;
use Shared\Domain\Ports\StoragePort;
use Throwable;

/**
 * Updates CV metadata and optionally replaces the stored file on R2.
 *
 * The previous object is deleted only after the row points at the new one; if
 * the write fails, the freshly uploaded object is deleted instead — either way
 * exactly one file per CV remains in the bucket.
 */
final readonly class UpdateCvHandler
{
    public function __construct(
        private CvRepositoryPort $cvs,
        private StoragePort $storage,
        private CvTextExtractorPort $extractor,
    ) {}

    #[\NoDiscard]
    public function handle(CvEloquentModel $cv, UploadCvData $data): CvEloquentModel
    {
        $attributes = [
            'title' => $data->normalizedTitle(),
            'niche' => $data->niche,
            'is_primary' => $data->isPrimary,
        ];

        $previousPath = $cv->file_path;
        $newPath = null;

        if ($data->file !== null) {
            $fileType = CvFileType::fromExtension($data->file->getClientOriginalExtension());
            $attributes['raw_text'] = $this->extractor->extract($fileType, $data->file);
            $attributes['file_type'] = $fileType;
            $attributes['original_filename'] = $data->file->getClientOriginalName();
            $newPath = $this->storage->putFile('cvs', $data->file, 'private');
            $attributes['file_path'] = $newPath;
        }

        try {
            $updated = $this->cvs->update($cv, $attributes);
        } catch (Throwable $exception) {
            if ($newPath !== null) {
                $this->storage->delete($newPath);
            }

            throw $exception;
        }

        if ($newPath !== null && $previousPath !== '' && $previousPath !== $newPath) {
            $this->storage->delete($previousPath);
        }

        return $updated;
    }
}
