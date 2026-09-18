<?php

declare(strict_types=1);

namespace Modules\CvJobStudio\Application\Commands;

use Modules\CvJobStudio\Domain\Enums\AiPurpose;
use Modules\CvJobStudio\Domain\Exceptions\AllProvidersFailedException;
use Modules\CvJobStudio\Infrastructure\Ai\AiCallExecutor;
use Modules\CvJobStudio\Infrastructure\Ai\NarrateMatchAgent;
use Modules\CvJobStudio\Infrastructure\Persistence\Eloquent\Models\StudioScoreEloquentModel;

/**
 * Match narrative (T-156): 2–4 sentences from the stored breakdown only. If
 * every provider fails, the posting shows without narrative and its numbers
 * are unchanged (degrade path, T-152 test 3).
 *
 * @return array{why: string|null, gaps: string|null}
 */
final readonly class NarrateMatchHandler
{
    public function __construct(private AiCallExecutor $calls) {}

    #[\NoDiscard]
    public function handle(string $scoreUuid, int $userId): array
    {
        $score = StudioScoreEloquentModel::query()
            ->where('user_id', $userId)
            ->where('uuid', $scoreUuid)
            ->with(['skillMatches.requirement', 'posting'])
            ->firstOrFail();

        $prompt = json_encode([
            'total' => $score->total_score,
            'band' => $score->band,
            'matches' => $score->skillMatches->map(
                static fn ($match): array => [
                    'requirement' => $match->requirement->canonical_name,
                    'credit' => $match->credit_final,
                ],
            )->all(),
        ], JSON_THROW_ON_ERROR);

        try {
            $result = $this->calls->call(AiPurpose::MatchNarrative, NarrateMatchAgent::class, $prompt, $userId);
        } catch (AllProvidersFailedException) {
            return ['why' => null, 'gaps' => null];
        }

        /** @var array{why: string, gaps: string} $data */
        $data = (array) $result['response'];

        return ['why' => $data['why'], 'gaps' => $data['gaps']];
    }
}
