<?php

declare(strict_types=1);

namespace Modules\Cvs\Application\Commands;

use Illuminate\Http\UploadedFile;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Modules\Cvs\Application\DTOs\UploadCvData;
use Modules\Cvs\Domain\Enums\CvFileType;
use Modules\Cvs\Domain\Enums\CvSource;
use Modules\Cvs\Domain\Ports\CvRepositoryPort;
use Modules\Cvs\Domain\Ports\CvTextExtractorPort;
use Modules\Cvs\Infrastructure\Persistence\Eloquent\Models\CvEloquentModel;
use Shared\Domain\Ports\StoragePort;
use Throwable;

/**
 * Persists a new CV from an uploaded file or pasted Markdown (agent chat).
 * The file is uploaded to private R2 first; if the database write then
 * fails, the object is deleted again so no orphaned résumé (PII) lingers in
 * the bucket (OWASP §10).
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
        if ($data->file !== null) {
            return $this->fromFile($data, $data->file, $userId);
        }

        if ($data->content !== null && trim($data->content) !== '') {
            return $this->fromContent($data, $data->content, $userId);
        }

        throw ValidationException::withMessages([
            'file' => __('A CV file (PDF or Markdown) or pasted content is required.'),
        ]);
    }

    private function fromFile(UploadCvData $data, UploadedFile $file, int $userId): CvEloquentModel
    {
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
                'source' => CvSource::Upload,
                'user_id' => $userId,
            ]);
        } catch (Throwable $exception) {
            $this->storage->delete($filePath);

            throw $exception;
        }
    }

    private function fromContent(UploadCvData $data, string $content, int $userId): CvEloquentModel
    {
        $rawText = $content
            |> trim(...)
            |> (fn (string $text): string => mb_substr($text, 0, 500_000));

        $filePath = 'cvs/'.((string) Str::uuid7()).'.md';
        $this->storage->put($filePath, $rawText, 'private');

        try {
            return $this->cvs->create([
                'title' => $data->normalizedTitle(),
                'niche' => $data->niche,
                'is_primary' => $data->isPrimary,
                'file_path' => $filePath,
                'file_type' => CvFileType::Md,
                'original_filename' => Str::slug($data->normalizedTitle()).'.md',
                'raw_text' => $rawText,
                'source' => CvSource::Chat,
                'user_id' => $userId,
            ]);
        } catch (Throwable $exception) {
            $this->storage->delete($filePath);

            throw $exception;
        }
    }
}
