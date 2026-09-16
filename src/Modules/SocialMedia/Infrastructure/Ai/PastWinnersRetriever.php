<?php

declare(strict_types=1);

namespace Modules\SocialMedia\Infrastructure\Ai;

use Illuminate\Database\Eloquent\Builder;
use Modules\SocialMedia\Infrastructure\Persistence\Eloquent\Models\SocialMediaContentEloquentModel;

/**
 * Deterministic retrieval for the writer: the author's own top-scoring
 * passed pieces, newest proof first. No vectors, no external store — the
 * scores the judge already persisted ARE the ranking signal.
 *
 * Fail-closed on ownership: without a user id it returns nothing, so one
 * author's winners can never leak into another author's prompt.
 */
final readonly class PastWinnersRetriever
{
    private const int DEFAULT_LIMIT = 3;

    private const int MAX_LIMIT = 5;

    /**
     * @return list<array{topic: string, hook: ?string, human_writing_index: ?int, virality_score: ?int}>
     */
    public function forBrief(?int $userId, ?string $niche, ?string $topic, int $limit = self::DEFAULT_LIMIT): array
    {
        if ($userId === null) {
            return [];
        }

        $terms = array_values(array_unique(array_filter([
            trim((string) $niche),
            trim((string) $topic),
        ])));

        return SocialMediaContentEloquentModel::query()
            ->where('created_by', $userId)
            ->where('all_scores_pass', true)
            ->when(
                $terms !== [],
                static fn (Builder $query): Builder => $query->where(
                    static function (Builder $query) use ($terms): Builder {
                        foreach ($terms as $term) {
                            $query->orWhere('niche', 'like', "%{$term}%")
                                ->orWhere('topic', 'like', "%{$term}%");
                        }

                        return $query;
                    },
                ),
            )
            ->orderByDesc('overall_score_avg')
            ->limit(max(1, min($limit, self::MAX_LIMIT)))
            ->get(['topic', 'hook', 'human_writing_index', 'virality_score'])
            ->map(static fn (SocialMediaContentEloquentModel $content): array => [
                'topic' => (string) $content->topic,
                'hook' => $content->hook !== null && trim((string) $content->hook) !== '' ? (string) $content->hook : null,
                'human_writing_index' => $content->human_writing_index !== null ? (int) $content->human_writing_index : null,
                'virality_score' => $content->virality_score !== null ? (int) $content->virality_score : null,
            ])
            ->all();
    }

    /**
     * One compact block for the prompt tail. Empty string when there is
     * nothing to show — the caller appends it only when non-empty so the
     * cacheable tail stays stable for authors with no history yet.
     *
     * @param  list<array{topic: string, hook: ?string, human_writing_index: ?int, virality_score: ?int}>  $winners
     */
    #[\NoDiscard]
    public function toPromptBlock(array $winners): string
    {
        if ($winners === []) {
            return '';
        }

        $lines = array_map(
            static fn (array $winner): string => '- '.(string) $winner['topic']
                .($winner['hook'] !== null ? " (hook: {$winner['hook']})" : '')
                .' [writing: '.($winner['human_writing_index'] ?? '?').', virality: '.($winner['virality_score'] ?? '?').']',
            $winners,
        );

        return "Past winners (your own top-scoring pieces — match their bar, do not copy them):\n".implode("\n", $lines);
    }
}
