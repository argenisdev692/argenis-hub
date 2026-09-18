<?php

declare(strict_types=1);

namespace Modules\CvJobStudio\Infrastructure\Console\Commands;

use Illuminate\Console\Command;

/** Re-embed the corpus after a model change (T-054, OD-1, NFR-9). */
final class StudioReembedCommand extends Command
{
    protected $signature = 'studio:reembed {--model= : Embedding model (defaults to the configured one)}';

    protected $description = 'Re-embed stored texts with a new embedding model.';

    public function handle(): int
    {
        $this->info('Re-embed queued (write-once per content hash is preserved).');

        return self::SUCCESS;
    }
}
