<?php

declare(strict_types=1);

namespace Modules\CourseScripts\Domain\Services;

use Modules\CourseScripts\Domain\ValueObjects\CallEstimate;

/**
 * Estimates a run's provider calls before it starts (US-12 · plan §3.6, D21).
 *
 * Per video: 1 outline + S sections + 1 closing + practice artifacts, where
 * S = minutes / minutes-per-section and practice is weighted by how often a
 * video warrants a pack. Review calls are counted only when the run asks for
 * the second review. Retries are not estimated; the ceiling covers them.
 */
final readonly class CallEstimator
{
    public function __construct(
        private float $minutesPerSection = 1.5,
        private int $minSections = 3,
        private int $maxSections = 12,
        private float $practiceRatio = 0.6,
        private float $avgArtifactsPerPack = 2.0,
        private float $expectedRewriteRounds = 0.5,
        private int $pointQueriesMax = 2,
        private int $subjectQueries = 4,
        private int $maxFirecrawlPerVideo = 2,
        private bool $firecrawlEnabled = true,
    ) {}

    /**
     * @param  list<int>  $videoMinutes  effective duration of each video in scope
     */
    #[\NoDiscard]
    public function estimate(
        array $videoMinutes,
        bool $withReview,
        bool $needsBible,
        bool $needsSubjectResearch,
        int $aiCeiling,
        int $researchCeiling,
    ): CallEstimate {
        $write = $needsBible ? 1 : 0;
        $review = 0.0;
        $research = $needsSubjectResearch ? $this->subjectQueries : 0;

        foreach ($videoMinutes as $minutes) {
            $sections = $this->sections($minutes);
            $write += 1 + $sections + 1;
            $artifacts = $this->practiceRatio * $this->avgArtifactsPerPack;
            $write += $artifacts;

            if ($withReview) {
                // Script review (+ practice review when a pack exists), and the
                // expected share of rewrites with their re-review.
                $reviews = 1 + $this->practiceRatio;
                $review += $reviews * (1 + $this->expectedRewriteRounds);
                $write += $this->expectedRewriteRounds * (($sections / 2) + $artifacts / 2);
            }

            $research += $this->pointQueriesMax + ($this->firecrawlEnabled ? $this->maxFirecrawlPerVideo / 2 : 0);
        }

        return new CallEstimate(
            aiWriteCalls: (int) ceil($write),
            aiReviewCalls: (int) ceil($review),
            researchCalls: (int) ceil($research),
            aiCeiling: $aiCeiling,
            researchCeiling: $researchCeiling,
        );
    }

    public function sections(int $minutes): int
    {
        return max($this->minSections, min($this->maxSections, (int) round(max(1, $minutes) / $this->minutesPerSection)));
    }
}
