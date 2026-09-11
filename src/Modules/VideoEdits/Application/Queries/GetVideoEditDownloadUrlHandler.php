<?php

declare(strict_types=1);

namespace Modules\VideoEdits\Application\Queries;

use Carbon\CarbonImmutable;
use Illuminate\Contracts\Config\Repository as Config;
use Modules\VideoEdits\Application\DTOs\DownloadUrlData;
use Modules\VideoEdits\Domain\Enums\VideoEditStatus;
use Modules\VideoEdits\Domain\Exceptions\VideoEditNotFoundException;
use Modules\VideoEdits\Domain\Exceptions\VideoEditStateConflictException;
use Modules\VideoEdits\Domain\Ports\VideoEditRepositoryPort;
use Shared\Domain\Ports\StoragePort;

/**
 * A short-lived signed link to the finished video (E5 · US-5 · D12). A fresh
 * link can be requested at any time; the URL is never stored or logged.
 */
final readonly class GetVideoEditDownloadUrlHandler
{
    public function __construct(
        private VideoEditRepositoryPort $edits,
        private StoragePort $storage,
        private Config $config,
    ) {}

    /**
     * @throws VideoEditNotFoundException
     * @throws VideoEditStateConflictException
     */
    #[\NoDiscard]
    public function handle(string $uuid, int $userId): DownloadUrlData
    {
        $edit = $this->edits->findOwnedByUuid($uuid, $userId)
            ?? throw VideoEditNotFoundException::forUuid($uuid);

        if ($edit->status !== VideoEditStatus::Completed || $edit->result_path === null) {
            throw VideoEditStateConflictException::notCompleted();
        }

        $expiresAt = CarbonImmutable::now()->addMinutes((int) $this->config->get('video-edit.urls.download_ttl_minutes'));

        return new DownloadUrlData(
            url: $this->storage->temporaryUrl($edit->result_path, $expiresAt),
            expiresAt: $expiresAt->toIso8601String(),
        );
    }
}
