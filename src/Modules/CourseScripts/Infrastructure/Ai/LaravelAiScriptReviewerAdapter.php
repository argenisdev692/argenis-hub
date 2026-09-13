<?php

declare(strict_types=1);

namespace Modules\CourseScripts\Infrastructure\Ai;

use Modules\CourseScripts\Domain\Exceptions\GenerationProviderException;
use Modules\CourseScripts\Domain\Ports\ScriptReviewerPort;
use Modules\CourseScripts\Domain\ValueObjects\ReviewVerdict;
use Modules\CourseScripts\Domain\ValueObjects\ScriptDraft;
use Modules\CourseScripts\Domain\ValueObjects\VideoWritingContext;
use Psr\Log\LoggerInterface;
use Shared\Infrastructure\AI\PromptCache\PromptCachingAIClient;
use Throwable;

/**
 * {@see ScriptReviewerPort} over the shared AI bridge. Uses the same cacheable
 * course and video layers as the writer, so a reviewer on the same provider
 * reads the shared material from cache.
 */
final readonly class LaravelAiScriptReviewerAdapter implements ScriptReviewerPort
{
    public function __construct(
        private PromptCachingAIClient $generator,
        private WritingContextRenderer $renderer,
        private LoggerInterface $logger,
    ) {}

    public function reviewScript(VideoWritingContext $context, ScriptDraft $draft, string $provider): ReviewVerdict
    {
        return $this->review(ReviewScriptAgent::class, self::SCRIPT_DIMENSIONS, $context, $provider, 'Review this script draft.', [
            'recording_format' => $draft->recordingFormat,
            'learning_objectives' => $draft->learningObjectives,
            'continuity_note' => $draft->continuityNote,
            'uses_tool' => $draft->usesTool,
            'sections' => $draft->sections,
            'coverage_map' => $draft->coverageMap,
            'closing' => $draft->closing,
            'practice_files' => $draft->plannedFileNames(),
        ]);
    }

    public function reviewPractice(VideoWritingContext $context, ScriptDraft $draft, string $provider): ReviewVerdict
    {
        return $this->review(ReviewPracticeAgent::class, self::PRACTICE_DIMENSIONS, $context, $provider, 'Review this practice pack.', [
            'plan' => $draft->practicePlan,
            'artifacts' => array_values($draft->artifacts),
            'demo_sections' => array_values(array_filter($draft->sections, static fn (array $section): bool => (array) $section['practice_files'] !== [])),
        ]);
    }

    /**
     * @param  class-string  $agent
     * @param  list<string>  $dimensions
     * @param  array<string, mixed>  $material
     */
    private function review(string $agent, array $dimensions, VideoWritingContext $context, string $provider, string $request, array $material): ReviewVerdict
    {
        $tail = UntrustedContentBlock::wrap('draft_under_review', (string) json_encode($material, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES))
            ."\n\nREQUEST: ".$request;

        try {
            $response = $this->generator->generateStructured($agent, $this->renderer->prompt($context, $tail), $provider);
        } catch (Throwable $exception) {
            $this->logger->error('course_scripts.review_failed', ['exception' => $exception::class]);

            throw GenerationProviderException::providerFailed('review');
        }

        $scores = [];

        foreach ($dimensions as $dimension) {
            $scores[$dimension] = max(0, min(10, (int) ($response[$dimension] ?? 0)));
        }

        $objections = [];

        foreach ((array) ($response['objections'] ?? []) as $objection) {
            $text = is_array($objection) ? mb_substr(trim((string) ($objection['text'] ?? '')), 0, 1000) : '';

            if ($text !== '') {
                $objections[] = ['target' => mb_substr(trim((string) ($objection['target'] ?? 'closing')), 0, 120), 'text' => $text];
            }
        }

        return new ReviewVerdict($scores, array_slice($objections, 0, 20));
    }
}
