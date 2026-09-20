<?php

declare(strict_types=1);

namespace Modules\CourseScripts\Infrastructure\Ai;

use Illuminate\Contracts\Config\Repository as Config;
use Laravel\Ai\Responses\StreamableAgentResponse;
use Modules\CourseScripts\Application\Generation\VideoWritingContextFactory;
use Modules\CourseScripts\Domain\Exceptions\CourseNotFoundException;
use Modules\CourseScripts\Domain\Exceptions\RunRequestRejectedException;
use Modules\CourseScripts\Domain\Ports\CourseRepositoryPort;
use Shared\Infrastructure\AI\PromptCache\PromptCachingAIClient;

/**
 * Live SSE preview of a video's outline (instructor draft screen).
 *
 * Builds the same cacheable context as the queued run, then streams the
 * outline agent directly — no version is stored. Long research is reused from
 * stored findings when present, so repeat previews start instantly. Lives in
 * Infrastructure because its product is a Laravel AI stream, not a domain value.
 */
final readonly class OutlinePreviewStreamer
{
    public function __construct(
        private CourseRepositoryPort $courses,
        private VideoWritingContextFactory $contexts,
        private WritingContextRenderer $renderer,
        private PromptCachingAIClient $streaming,
        private GenerationRequestPolicy $policy,
        private Config $config,
    ) {}

    /**
     * @throws RunRequestRejectedException the provider is not an allowed writer
     * @throws CourseNotFoundException
     */
    public function stream(string $courseUuid, string $videoUuid, string $provider, int $userId): StreamableAgentResponse
    {
        $provider = strtolower(trim($provider));

        if (! in_array($provider, (array) $this->config->get('course-scripts.providers.selectable_writers', ['openai', 'anthropic', 'gemini']), true)) {
            throw RunRequestRejectedException::unknownProvider();
        }

        $course = $this->courses->findOwnedWithStructure($courseUuid, $userId) ?? throw new CourseNotFoundException;
        $video = $course->videos->firstWhere('uuid', $videoUuid) ?? throw new CourseNotFoundException;

        ['context' => $context] = $this->contexts->build($course, $video);

        return $this->streaming->streamStructured(
            GenerateScriptOutlineAgent::class,
            $this->renderer->prompt($context, 'REQUEST: Write the OUTLINE of this video.'),
            $provider,
            $this->policy->modelFor('outline'),
            $this->policy->timeoutFor('outline'),
            'outline-preview',
        );
    }
}
