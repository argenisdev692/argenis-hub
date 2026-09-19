<?php

declare(strict_types=1);

namespace Modules\CvJobStudio\Infrastructure\Console\Commands;

use Illuminate\Console\Command;
use Modules\CvJobStudio\Application\Commands\StoreEmbeddingHandler;
use Modules\CvJobStudio\Infrastructure\Persistence\Eloquent\Models\StudioCvBulletEloquentModel;
use Modules\CvJobStudio\Infrastructure\Persistence\Eloquent\Models\StudioEmbeddingEloquentModel;
use Modules\CvJobStudio\Infrastructure\Persistence\Eloquent\Models\StudioRequirementEloquentModel;
use Throwable;

/**
 * Backfill the vector corpus (T-054, OD-1, NFR-9): embeds CV bullets and
 * posting requirements missing a stored vector. Write-once per content hash
 * is preserved — rows already embedded are skipped without a provider call.
 * `--model=` drops vectors stored under a different model first, so a model
 * change is a re-embed, never silent corruption. Stops at the first provider
 * failure: an embedding outage delays work instead of failing it (NFR-17).
 */
final class StudioReembedCommand extends Command
{
    protected $signature = 'studio:reembed {--model= : Embedding model (drop vectors stored under a different model first)} {--limit=500 : Max rows embedded per owner type}';

    protected $description = 'Embed CV bullets and posting requirements missing a stored vector.';

    public function handle(StoreEmbeddingHandler $embeddings): int
    {
        $model = (string) ($this->option('model') ?? '');
        $limit = max(1, (int) $this->option('limit'));

        if ($model !== '') {
            $dropped = StudioEmbeddingEloquentModel::query()
                ->whereIn('owner_type', ['cv_bullet', 'posting_requirement'])
                ->where('model', '!=', $model)
                ->delete();

            $this->info("Dropped {$dropped} vector(s) stored under a different model.");
        }

        $bullets = $this->backfill(
            $embeddings,
            StudioCvBulletEloquentModel::class,
            'cv_bullet',
            $limit,
            static fn ($row): string => (string) $row->text,
        );

        if ($bullets['halted']) {
            $this->warn("Embedding provider failed after {$bullets['embedded']} bullet(s) — requirements skipped, re-run later.");

            return self::SUCCESS;
        }

        $requirements = $this->backfill(
            $embeddings,
            StudioRequirementEloquentModel::class,
            'posting_requirement',
            $limit,
            static fn ($row): string => trim((string) ($row->raw_text !== '' ? $row->raw_text : $row->canonical_name)),
        );

        if ($requirements['halted']) {
            $this->warn("Embedding provider failed after {$requirements['embedded']} requirement(s) — re-run later.");

            return self::SUCCESS;
        }

        $this->info("Re-embed done: {$bullets['embedded']} bullet(s), {$requirements['embedded']} requirement(s).");

        return self::SUCCESS;
    }

    /**
     * @param  class-string  $model
     * @param  callable(object): string  $text
     * @return array{embedded: int, halted: bool}
     */
    private function backfill(StoreEmbeddingHandler $embeddings, string $model, string $ownerType, int $limit, callable $text): array
    {
        $embedded = 0;

        try {
            $query = $model::query()->orderBy('id');

            if ($ownerType === 'posting_requirement') {
                $query->where(static function ($nested): void {
                    $nested->where('raw_text', '!=', '')->orWhere('canonical_name', '!=', '');
                });
            } else {
                $query->where('text', '!=', '');
            }

            $query->chunkById(200, function ($rows) use ($embeddings, $ownerType, $text, $limit, &$embedded): bool {
                foreach ($rows as $row) {
                    if ($embedded >= $limit) {
                        return false;
                    }

                    $content = trim($text($row));

                    if ($content === '') {
                        continue;
                    }

                    $hash = hash('sha256', $content);

                    $exists = StudioEmbeddingEloquentModel::query()
                        ->where('user_id', $row->user_id)
                        ->where('owner_type', $ownerType)
                        ->where('owner_id', $row->id)
                        ->where('content_hash', $hash)
                        ->exists();

                    if ($exists) {
                        continue;
                    }

                    try {
                        (void) $embeddings->handle($ownerType, (int) $row->id, $content, (int) $row->user_id);
                    } catch (Throwable) {
                        return false;
                    }

                    $embedded++;
                }

                return true;
            });
        } catch (Throwable) {
            return ['embedded' => $embedded, 'halted' => true];
        }

        return ['embedded' => $embedded, 'halted' => false];
    }
}
