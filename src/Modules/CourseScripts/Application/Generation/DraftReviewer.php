<?php

declare(strict_types=1);

namespace Modules\CourseScripts\Application\Generation;

use Illuminate\Contracts\Config\Repository as Config;
use Modules\CourseScripts\Domain\Exceptions\GenerationProviderException;
use Modules\CourseScripts\Domain\Exceptions\ScriptValidationException;
use Modules\CourseScripts\Domain\Ports\ScriptReviewerPort;
use Modules\CourseScripts\Domain\ValueObjects\CallUsage;
use Modules\CourseScripts\Domain\ValueObjects\ReviewVerdict;
use Modules\CourseScripts\Domain\ValueObjects\ScriptDraft;
use Modules\CourseScripts\Domain\ValueObjects\VideoWritingContext;

/**
 * The second review loop (US-13 · FR-40…FR-43), run only when the run asked
 * for it (`with_review`).
 *
 * Review → if below threshold, rewrite only the targeted sections, artifacts
 * or closing → gate B → review again, up to the configured iterations. The
 * best-scoring draft is kept; when none passes it is delivered marked
 * not-passed with its scores.
 */
final readonly class DraftReviewer
{
    public function __construct(
        private ScriptReviewerPort $reviewer,
        private ScriptDraftWriter $writer,
        private Config $config,
    ) {}

    /**
     * @throws GenerationProviderException
     */
    public function review(VideoWritingContext $context, ScriptDraft $draft, string $writerProvider, string $reviewerProvider): ReviewOutcome
    {
        $maxIterations = max(1, (int) $this->config->get('course-scripts.runs.max_review_iterations', 3));
        $minDimension = (int) $this->config->get('course-scripts.review.min_dimension_score', 6);
        $minOverall = (int) $this->config->get('course-scripts.review.min_overall_score', 7);

        $usage = CallUsage::none();
        $best = null;

        for ($iteration = 1; $iteration <= $maxIterations; $iteration++) {
            $scriptVerdict = $this->reviewer->reviewScript($context, $draft, $reviewerProvider);
            $usage = $usage->add(new CallUsage(aiReview: 1));
            $practiceVerdict = null;

            if ($draft->practiceWarranted()) {
                $practiceVerdict = $this->reviewer->reviewPractice($context, $draft, $reviewerProvider);
                $usage = $usage->add(new CallUsage(aiReview: 1));
            }

            $passed = $scriptVerdict->passes($minDimension, $minOverall)
                && ($practiceVerdict === null || $practiceVerdict->passes($minDimension, $minOverall));

            $candidate = new ReviewOutcome($draft, $scriptVerdict, $practiceVerdict, $passed, $iteration, $usage);

            if ($best === null || $this->score($candidate) > $this->score($best)) {
                $best = $candidate;
            }

            if ($passed || $iteration === $maxIterations) {
                break;
            }

            try {
                $draft = $this->writer->rewrite($context, $draft, [
                    ...$scriptVerdict->objections,
                    ...($practiceVerdict === null ? [] : $practiceVerdict->objections),
                ], $writerProvider);
            } catch (ScriptValidationException) {
                // A rewrite that breaks the deterministic gate is discarded; the
                // best reviewed draft so far stands.
                $usage = $usage->add(new CallUsage(aiWrite: $this->writer->callsMade()));

                break;
            }

            $usage = $usage->add(new CallUsage(aiWrite: $this->writer->callsMade()));
        }

        /** @var ReviewOutcome $best */
        return new ReviewOutcome(
            draft: $best->draft,
            scriptVerdict: $best->scriptVerdict,
            practiceVerdict: $best->practiceVerdict,
            passed: $best->passed,
            iterations: $iteration > $maxIterations ? $maxIterations : $iteration,
            usage: $usage,
        );
    }

    private function score(ReviewOutcome $outcome): float
    {
        $verdicts = array_filter([$outcome->scriptVerdict, $outcome->practiceVerdict], static fn (?ReviewVerdict $verdict): bool => $verdict !== null);

        return ($outcome->passed ? 100 : 0) + array_sum(array_map(static fn (ReviewVerdict $verdict): float => $verdict->average(), $verdicts)) / count($verdicts);
    }
}
