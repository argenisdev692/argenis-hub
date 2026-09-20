<?php

declare(strict_types=1);

namespace Modules\CvJobStudio\Application\Commands;

use Modules\CvJobStudio\Application\DTOs\ScorePostingInputData;
use Modules\CvJobStudio\Application\DTOs\StudioScoreData;
use Modules\CvJobStudio\Domain\Exceptions\PostingNotFoundException;
use Modules\CvJobStudio\Domain\Ports\StudioPostingRepositoryPort;
use Modules\CvJobStudio\Domain\Ports\StudioScoreRepositoryPort;
use Modules\CvJobStudio\Domain\Services\MatchScoreCalculator;
use Modules\CvJobStudio\Domain\Services\RequirementWeigher;
use Modules\CvJobStudio\Domain\Services\SkillRelationSet;
use Modules\CvJobStudio\Domain\ValueObjects\PostingScoringContext;

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
    public function handle(string $postingUuid, ScorePostingInputData $input, int $userId): StudioScoreData
    {
        $posting = $this->postings->scoringContext($postingUuid, $userId)
            ?? throw new PostingNotFoundException("Posting {$postingUuid} not found.");

        $failedGate = $posting->failedGate();

        if ($failedGate !== null) {
            throw new PostingNotFoundException("Posting {$posting->uuid} failed gate {$failedGate} and cannot be scored.");
        }

        $rules = $posting->rules ?? config('cv-job-studio');

        $weighed = $this->weigher->weigh(
            array_map(
                static fn (array $requirement): array => ['tag' => $requirement['tag']->value, 'nature' => $requirement['nature']->value],
                $posting->requirements,
            ),
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
            [...$rules, 'rules_version' => $posting->rulesVersion],
        );

        return $this->scores->store($postingUuid, $userId, $breakdown, hash('sha256', $posting->latestText));
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
     * @param  array<int, float>  $weights
     * @return list<array{requirement: string, weight: float, evidence: string, position_ratio: float}>
     */
    private function buildSkillRows(PostingScoringContext $posting, ScorePostingInputData $input, array $weights): array
    {
        $byName = [];

        foreach ($input->cvSkills as $skill) {
            $byName[SkillRelationSet::normalize($skill['name'])] = $skill;
        }

        $rows = [];

        foreach ($posting->requirements as $index => $requirement) {
            $known = $byName[SkillRelationSet::normalize($requirement['canonical_name'])] ?? null;

            $rows[] = [
                'requirement' => $requirement['canonical_name'],
                'weight' => $weights[$index] ?? 0.0,
                'evidence' => $known['evidence'] ?? 'list_only',
                'position_ratio' => (float) ($known['position_ratio'] ?? 1.0),
            ];
        }

        return $rows;
    }
}
