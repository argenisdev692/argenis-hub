<?php

declare(strict_types=1);

namespace Modules\VideoEdits\Application\Commands;

use Carbon\CarbonImmutable;
use Illuminate\Contracts\Config\Repository as Config;
use Illuminate\Support\Str;
use Modules\VideoEdits\Application\DTOs\CreatedVideoEditData;
use Modules\VideoEdits\Application\DTOs\CreateVideoEditData;
use Modules\VideoEdits\Application\DTOs\ManualRangeData;
use Modules\VideoEdits\Application\DTOs\SourceUploadData;
use Modules\VideoEdits\Application\DTOs\UploadTargetData;
use Modules\VideoEdits\Application\DTOs\VideoEditDetailData;
use Modules\VideoEdits\Domain\Enums\SpeechCategory;
use Modules\VideoEdits\Domain\Exceptions\VideoEditNotFoundException;
use Modules\VideoEdits\Domain\Ports\VideoEditRepositoryPort;
use Shared\Domain\Ports\StoragePort;

/**
 * Creates a draft edit and issues one short-lived upload URL per source
 * (E2 · US-1/2/3/7 · AD-1). Object keys are generated here — never taken from
 * the client — so only system-issued locations can ever be submitted (FR-19).
 */
final readonly class CreateVideoEditHandler
{
    public function __construct(
        private VideoEditRepositoryPort $edits,
        private StoragePort $storage,
        private Config $config,
    ) {}

    /**
     * @throws VideoEditNotFoundException when `previous_edit_uuid` is not one of the caller's edits
     */
    #[\NoDiscard]
    public function handle(CreateVideoEditData $data, int $userId, string $userUuid): CreatedVideoEditData
    {
        $previousEditId = null;

        if ($data->previousEditUuid !== null) {
            $previousEditId = $this->edits->findOwnedByUuid($data->previousEditUuid, $userId)?->id
                ?? throw VideoEditNotFoundException::forUuid($data->previousEditUuid);
        }

        $editUuid = (string) Str::uuid7();
        $sources = $this->sourceRows($data->sources, $userUuid, $editUuid);
        $script = $this->scriptRow($data, $userUuid, $editUuid);

        $edit = $this->edits->createDraft(
            uuid: $editUuid,
            userId: $userId,
            previousEditId: $previousEditId,
            mode: $data->mode,
            parameters: $this->parameters($data),
            sources: $sources,
            script: $script,
            consentedAt: $data->aiEdit?->consented === true ? CarbonImmutable::now() : null,
        );

        $now = CarbonImmutable::now();
        $expiresAt = $now->addMinutes((int) $this->config->get('video-edit.urls.upload_ttl_minutes'));

        return new CreatedVideoEditData(
            edit: VideoEditDetailData::fromModel($edit, $now),
            uploads: array_map(
                function (array $source) use ($expiresAt): UploadTargetData {
                    $target = $this->storage->temporaryUploadUrl($source['storage_path'], $expiresAt);

                    return new UploadTargetData(
                        sourceUuid: $source['uuid'],
                        position: $source['position'],
                        uploadUrl: $target['upload_url'],
                        headers: $target['headers'],
                        expiresAt: $expiresAt->toIso8601String(),
                    );
                },
                $sources,
            ),
            // The script uploads the same way a clip does — straight to storage
            // with a system-issued key, never through PHP (AD-1, FR-19).
            scriptUpload: $script === null ? null : $this->scriptUploadTarget($script, $expiresAt),
        );
    }

    /**
     * @param  list<SourceUploadData>  $uploads
     * @return list<array{uuid: string, position: int, original_name: string, extension: string, declared_mime: string, declared_size_bytes: int, storage_path: string}>
     */
    private function sourceRows(array $uploads, string $userUuid, string $editUuid): array
    {
        $prefix = (string) $this->config->get('video-edit.storage.path_prefix');

        usort($uploads, static fn (SourceUploadData $a, SourceUploadData $b): int => $a->position <=> $b->position);

        return array_map(static function (SourceUploadData $upload) use ($prefix, $userUuid, $editUuid): array {
            $sourceUuid = (string) Str::uuid7();
            $extension = $upload->extension();

            return [
                'uuid' => $sourceUuid,
                'position' => $upload->position,
                'original_name' => $upload->fileName |> basename(...) |> (fn (string $name): string => mb_substr($name, 0, 255)),
                'extension' => $extension,
                'declared_mime' => $upload->mimeType,
                'declared_size_bytes' => $upload->sizeBytes,
                'storage_path' => "{$prefix}/{$userUuid}/{$editUuid}/sources/{$sourceUuid}.{$extension}",
            ];
        }, $uploads);
    }

    /**
     * The requested settings, stored as-is so a re-edit can be prefilled (US-7).
     *
     * @return array{silence_removal: array{enabled: bool, threshold_seconds: float|null}, speech_cleanup: array{enabled: bool, categories: list<string>, language: string|null}, manual_ranges: list<array{start_ms: int, end_ms: int, note: string|null}>}
     */
    private function parameters(CreateVideoEditData $data): array
    {
        $silenceEnabled = $data->silenceRemovalEnabled();
        $speechEnabled = $data->speechCleanup?->enabled === true;

        return [
            'silence_removal' => [
                'enabled' => $silenceEnabled,
                'threshold_seconds' => $silenceEnabled
                    ? ($data->silenceRemoval?->thresholdSeconds ?? (float) $this->config->get('video-edit.silence.default_threshold_seconds'))
                    : null,
            ],
            // Categories are resolved here rather than at detection time so the
            // stored parameters say exactly what was asked for — a re-edit a
            // month later must not silently pick up a new default (US-7).
            'speech_cleanup' => [
                'enabled' => $speechEnabled,
                'categories' => $speechEnabled
                    ? array_map(
                        static fn (SpeechCategory $category): string => $category->value,
                        $data->speechCleanup?->effectiveCategories() ?? [],
                    )
                    : [],
                'language' => $speechEnabled
                    ? ($data->speechCleanup?->language ?? (string) $this->config->get('video-edit.speech.default_language'))
                    : null,
            ],
            // Consent is recorded in the parameters the worker reads, not only
            // as a timestamp on the row: the producer gates on it (R9), and it
            // must travel with the settings a retry replays.
            'ai_edit' => [
                'enabled' => $data->aiEdit?->enabled === true,
                'consented' => $data->aiEdit?->consented === true,
                'instructions' => $data->aiEdit?->instructions,
                'target_duration_minutes' => $data->aiEdit?->targetDurationMinutes,
            ],
            'manual_ranges' => array_map(
                static fn (ManualRangeData $range): array => $range->toParameter(),
                $data->manualRanges,
            ),
        ];
    }

    /**
     * @param  array{uuid: string, storage_path: string, ...}  $script
     */
    private function scriptUploadTarget(array $script, CarbonImmutable $expiresAt): UploadTargetData
    {
        $target = $this->storage->temporaryUploadUrl($script['storage_path'], $expiresAt);

        return new UploadTargetData(
            sourceUuid: $script['uuid'],
            // Scripts are not part of the clip ordering; 0 keeps the shape of
            // the upload contract without pretending it has a position.
            position: 0,
            uploadUrl: $target['upload_url'],
            headers: $target['headers'],
            expiresAt: $expiresAt->toIso8601String(),
        );
    }

    /**
     * @return array{uuid: string, original_name: string, extension: string, declared_mime: string, declared_size_bytes: int, storage_path: string}|null
     */
    private function scriptRow(CreateVideoEditData $data, string $userUuid, string $editUuid): ?array
    {
        $script = $data->aiEdit?->script;

        if ($script === null) {
            return null;
        }

        $prefix = (string) $this->config->get('video-edit.storage.path_prefix');
        $scriptUuid = (string) Str::uuid7();
        $extension = $script->extension();

        return [
            'uuid' => $scriptUuid,
            'original_name' => $script->fileName |> basename(...) |> (fn (string $name): string => mb_substr($name, 0, 255)),
            'extension' => $extension,
            'declared_mime' => $script->mimeType,
            'declared_size_bytes' => $script->sizeBytes,
            // Key generated here, never taken from the client (FR-19).
            'storage_path' => "{$prefix}/{$userUuid}/{$editUuid}/script/{$scriptUuid}.{$extension}",
        ];
    }
}
