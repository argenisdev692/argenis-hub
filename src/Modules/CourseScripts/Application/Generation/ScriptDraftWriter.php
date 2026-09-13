<?php

declare(strict_types=1);

namespace Modules\CourseScripts\Application\Generation;

use Illuminate\Contracts\Config\Repository as Config;
use Modules\CourseScripts\Domain\Exceptions\GenerationProviderException;
use Modules\CourseScripts\Domain\Exceptions\ScriptValidationException;
use Modules\CourseScripts\Domain\Ports\ScriptWriterPort;
use Modules\CourseScripts\Domain\Services\ScriptCompletenessValidator;
use Modules\CourseScripts\Domain\Services\ScriptOutlineValidator;
use Modules\CourseScripts\Domain\ValueObjects\ScriptDraft;
use Modules\CourseScripts\Domain\ValueObjects\VideoWritingContext;

/**
 * Writes a complete draft through the deterministic gates (plan §3.5 steps
 * 4–9, FR-44a). Counts every provider call it makes.
 *
 * Gate A retries the outline with its violations; gate B re-runs only the
 * steps that produced a violation, once. A draft that still fails is not
 * delivered: the video fails with the violations recorded.
 */
final class ScriptDraftWriter
{
    private int $calls = 0;

    public function __construct(
        private readonly ScriptWriterPort $writer,
        private readonly ScriptOutlineValidator $outlineGate,
        private readonly ScriptCompletenessValidator $completenessGate,
        private readonly Config $config,
    ) {}

    /**
     * @throws GenerationProviderException
     * @throws ScriptValidationException
     */
    public function write(VideoWritingContext $context, string $provider): ScriptDraft
    {
        $this->calls = 0;

        $draft = $this->outline($context, $provider);

        foreach ($draft->topLevelSections() as $section) {
            $draft = $this->writeSection($context, $draft, (string) $section['number'], $provider);
        }

        $draft = $draft->withClosing($this->call(fn (): array => $this->writer->closing($context, $draft, $provider)));

        foreach ($draft->plannedFileNames() as $fileName) {
            $draft = $draft->withArtifact($this->call(fn (): array => $this->writer->artifact($context, $draft, $fileName, $provider)));
        }

        return $this->passGateB($context, $draft, $provider);
    }

    /**
     * Rewrites the parts named in reviewer objections (second review, FR-42):
     * `section N` targets a top-level section, `artifact FILE` a practice file,
     * anything else the closing. Returns the draft after gate B.
     *
     * @param  list<array{target: string, text: string}>  $objections
     */
    public function rewrite(VideoWritingContext $context, ScriptDraft $draft, array $objections, string $provider): ScriptDraft
    {
        $this->calls = 0;
        $bySection = [];
        $byArtifact = [];
        $general = [];

        foreach ($objections as $objection) {
            $target = trim($objection['target']);

            if (preg_match('/^section\s+(\d+)/i', $target, $match) === 1) {
                $bySection[$match[1]][] = $objection['text'];
            } elseif (preg_match('/^artifact\s+(.+)$/i', $target, $match) === 1 && in_array(trim($match[1]), $draft->plannedFileNames(), true)) {
                $byArtifact[trim($match[1])][] = $objection['text'];
            } else {
                $general[] = $objection['text'];
            }
        }

        foreach ($bySection as $number => $corrections) {
            $draft = $this->writeSection($context, $draft, (string) $number, $provider, $corrections);
        }

        foreach ($byArtifact as $fileName => $corrections) {
            $draft = $draft->withArtifact($this->call(fn (): array => $this->writer->artifact($context, $draft, $fileName, $provider, $corrections)));
        }

        if ($general !== []) {
            $draft = $draft->withClosing($this->call(fn (): array => $this->writer->closing($context, $draft, $provider, $general)));
        }

        return $this->passGateB($context, $draft, $provider);
    }

    public function callsMade(): int
    {
        return $this->calls;
    }

    private function outline(VideoWritingContext $context, string $provider): ScriptDraft
    {
        $attempts = max(1, (int) $this->config->get('course-scripts.runs.max_outline_attempts', 2));
        $violations = [];

        for ($attempt = 1; $attempt <= $attempts; $attempt++) {
            $draft = $this->call(fn (): ScriptDraft => $this->writer->outline($context, $provider, $violations));
            $violations = $this->outlineGate->violations($draft, $context->durationMinutes, $context->mandatoryContent());

            if ($context->forcePractice && ! $draft->practiceWarranted()) {
                $violations[] = 'The author requires a practice pack for this video: plan one.';
            }

            if ($violations === []) {
                return $draft;
            }
        }

        throw new ScriptValidationException('outline', $violations);
    }

    /**
     * @param  list<string>  $corrections
     */
    private function writeSection(VideoWritingContext $context, ScriptDraft $draft, string $number, string $provider, array $corrections = []): ScriptDraft
    {
        $parts = $this->call(fn (): array => $this->writer->sectionSegments($context, $draft, $number, $provider, $corrections));
        $sections = $draft->sections;

        foreach ($sections as $index => $section) {
            $belongs = $section['number'] === $number || ($section['parent_number'] ?? null) === $number;

            if ($belongs && array_key_exists((string) $section['number'], $parts)) {
                $sections[$index]['segments'] = $parts[(string) $section['number']];
            }
        }

        return $draft->withSections($sections);
    }

    private function passGateB(VideoWritingContext $context, ScriptDraft $draft, string $provider): ScriptDraft
    {
        $hasNext = $context->continuity->hasNextVideo();
        $violations = $this->completenessGate->violations($draft, $hasNext, $context->bible);
        $retries = max(0, (int) $this->config->get('course-scripts.runs.max_step_retries', 1));

        for ($retry = 0; $retry < $retries && $violations !== []; $retry++) {
            $draft = $this->repair($context, $draft, $provider, $violations);
            $violations = $this->completenessGate->violations($draft, $hasNext, $context->bible);
        }

        if ($violations !== []) {
            throw new ScriptValidationException('script', $violations);
        }

        return $draft;
    }

    /**
     * @param  list<string>  $violations
     */
    private function repair(VideoWritingContext $context, ScriptDraft $draft, string $provider, array $violations): ScriptDraft
    {
        $sectionNumbers = [];
        $closing = [];
        $artifacts = [];

        foreach ($violations as $violation) {
            if (preg_match('/Section (\d+)(?:\.\d+)?/', $violation, $match) === 1) {
                $sectionNumbers[$match[1]][] = $violation;
            } elseif (preg_match('/"([^"]+)"/', $violation, $match) === 1 && in_array($match[1], $draft->plannedFileNames(), true) && ! str_contains($violation, 'never used') && ! str_contains($violation, 'preparation')) {
                $artifacts[$match[1]][] = $violation;
            } else {
                $closing[] = $violation;
            }
        }

        foreach ($sectionNumbers as $number => $corrections) {
            $draft = $this->writeSection($context, $draft, (string) $number, $provider, $corrections);
        }

        foreach ($artifacts as $fileName => $corrections) {
            $draft = $draft->withArtifact($this->call(fn (): array => $this->writer->artifact($context, $draft, $fileName, $provider, $corrections)));
        }

        if ($closing !== []) {
            $draft = $draft->withClosing($this->call(fn (): array => $this->writer->closing($context, $draft, $provider, $closing)));
        }

        return $draft;
    }

    /**
     * @template T
     *
     * @param  callable(): T  $step
     * @return T
     */
    private function call(callable $step): mixed
    {
        $this->calls++;

        return $step();
    }
}
