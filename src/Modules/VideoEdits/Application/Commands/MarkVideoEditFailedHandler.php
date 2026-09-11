<?php

declare(strict_types=1);

namespace Modules\VideoEdits\Application\Commands;

use Carbon\CarbonImmutable;
use Illuminate\Contracts\Config\Repository as Config;
use Modules\VideoEdits\Domain\Enums\VideoEditStatus;
use Modules\VideoEdits\Domain\Exceptions\PermanentVideoEditFailure;
use Modules\VideoEdits\Domain\Ports\VideoEditRepositoryPort;
use Throwable;

/**
 * Records a final failure (FR-10, FR-17, FR-20).
 *
 * Only permanent failures carry their own message and details — they are written
 * for users. Everything else becomes a generic message: raw exception text may
 * contain commands or paths and never leaves the logs. Sources stay available
 * for the retry window.
 */
final readonly class MarkVideoEditFailedHandler
{
    public const string PROCESSING_ERROR = 'processing_error';

    public const string PROCESSING_TIMEOUT = 'processing_timeout';

    public function __construct(
        private VideoEditRepositoryPort $edits,
        private Config $config,
    ) {}

    public function handle(string $uuid, ?Throwable $failure): void
    {
        [$code, $message, $details] = $failure instanceof PermanentVideoEditFailure
            ? [$failure->failureCode(), $failure->getMessage(), $failure->failureDetails()]
            : [self::PROCESSING_ERROR, 'The video could not be processed. You can retry it.', null];

        $this->markFailed($uuid, $code, $message, $details);
    }

    public function handleTimeout(string $uuid): void
    {
        $this->markFailed($uuid, self::PROCESSING_TIMEOUT, 'Processing took too long and was stopped. You can retry it.', null);
    }

    /**
     * @param  array<string, mixed>|null  $details
     */
    private function markFailed(string $uuid, string $code, string $message, ?array $details): void
    {
        $now = CarbonImmutable::now();

        $this->edits->transitionStatus($uuid, [VideoEditStatus::Queued, VideoEditStatus::Processing], VideoEditStatus::Failed, [
            'failure_code' => $code,
            'failure_message' => mb_substr($message, 0, 255),
            'failure_details' => $details,
            'failed_at' => $now,
            'sources_expire_at' => $now->addHours((int) $this->config->get('video-edit.retention.failed_sources_hours')),
        ]);
    }
}
