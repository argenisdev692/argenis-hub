<?php

declare(strict_types=1);

namespace Modules\CvJobStudio\Application\Commands;

use Modules\CvJobStudio\Application\DTOs\ScorePostingInputData;
use Modules\CvJobStudio\Domain\Exceptions\PostingNotFoundException;
use Modules\CvJobStudio\Domain\Ports\StudioPostingRepositoryPort;
use Modules\CvJobStudio\Domain\Ports\StudioScoreRepositoryPort;
use Modules\CvJobStudio\Domain\Services\MatchScoreCalculator;
use Modules\CvJobStudio\Domain\Services\RequirementWeigher;
use Modules\CvJobStudio\Domain\Services\SkillRelationSet;
use Modules\CvJobStudio\Infrastructure\Persistence\Eloquent\Models\StudioPostingEloquentModel;
use Modules\CvJobStudio\Infrastructure\Persistence\Eloquent\Models\StudioScoreEloquentModel;

/**
 * Scores a gate-passed posting from stored rows only — no provider call, so
 * this is both the first score and every rescore (FR-37, SC-2). Gate-failed
 * postings are refused here; they are counted, never scored (FR-12).
 */
final readonly class ScorePostingHandler
{
    public function __construct(
        private MatchScoreCalculator $calculator,
        private RequirementWeigher $weigher,
        private StudioPostingRepositoryPort $postings,
        private StudioScoreRepositoryPort $scores,
    ) {}

    #[\NoDiscard]
    public function handle(string $postingUuid, ScorePostingInputData $input, int $userId): StudioScoreEloquentModel
    {
        $posting = $this->postings->scoringContext($postingUuid, $userId);

        if ($posting === null) {
            throw new PostingNotFoundException("Posting {$postingUuid} not found.");
        }

        $this->guardGatesPassed($posting);

        $rules = $posting->profile->rules ?? config('cv-job-studio');
        $weighed = $this->weigher->weigh(
            $posting->requirements->map(
                static fn ($requirement): array => ['tag' => $requirement->tag->value, 'nature' => $requirement->nature->value],
            )->all(),
            $rules['tag_weights'],
            (float) $rules['soft_cap_ratio'],
        );

        $relations = new SkillRelationSet($this->inputSkillMap($input), $this->postings->confirmedRelations($userId));
        $skillRows = $this->buildSkillRows($posting, $input, $weighed['weights']);

        $breakdown = $this->calculator->calculate(
            $skillRows,
            $relations,
            $input->similarities,
            $input->signals,
            $input->capContext,
            [...$rules, 'rules_version' => $posting->profile->rules_version],
        );

        $textHash = hash('sha256', (string) $posting->texts->sortByDesc('id')->first()?->text);

        return $this->scores->store($postingUuid, $userId, $breakdown, $textHash);
    }

    private function guardGatesPassed(StudioPostingEloquentModel $posting): void
    {
        foreach ($posting->gateResults as $result) {
            if (! $result->passed) {
                throw new PostingNotFoundException("Posting {$posting->uuid} failed gate {$result->gate_code} and cannot be scored.");
            }
        }
    }

    /** @return array<string, true> */
    private function inputSkillMap(ScorePostingInputData $input): array
    {
        $map = [];

        foreach ($input->cvSkills as $skill) {
            $map[SkillRelationSet::normalize($skill['name'])] = true;
        }

        return $map;
    }

    /**
     * @param  array{weights: array<int, float>}  $weighed
     * @return array<int, array{requirement: string, weight: float, evidence: string, position_ratio: float}>
     */
    private function buildSkillRows(StudioPostingEloquentModel $posting, ScorePostingInputData $input, array $weights): array
    {
        $byName = [];

        foreach ($input->cvSkills as $skill) {
            $byName[SkillRelationSet::normalize($skill['name'])] = $skill;
        }

        $rows = [];
        $index = 0;

        foreach ($posting->requirements as $requirement) {
            $known = $byName[SkillRelationSet::normalize($requirement->canonical_name)] ?? null;

            $rows[] = [
                'requirement' => $requirement->canonical_name,
                'weight' => $weights[$index] ?? 0.0,
                'evidence' => $known['evidence'] ?? 'list_only',
                'position_ratio' => (float) ($known['position_ratio'] ?? 1.0),
            ];

            $index++;
        }

        return $rows;
    }
}
