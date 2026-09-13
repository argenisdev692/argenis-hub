<?php

declare(strict_types=1);

namespace Modules\CourseScripts\Tests\Support;

use Modules\CourseScripts\Infrastructure\Ai\GeneratePracticeArtifactAgent;
use Modules\CourseScripts\Infrastructure\Ai\GenerateScriptClosingAgent;
use Modules\CourseScripts\Infrastructure\Ai\GenerateScriptOutlineAgent;
use Modules\CourseScripts\Infrastructure\Ai\GenerateScriptSectionAgent;

/**
 * Recorded provider payloads for runs over the factory course
 * (`CourseFactory::withVideos()`: videos 1..N, mandatory content "Contenido obligatorio N").
 */
final class GenerationScenario
{
    /**
     * @param  list<int>  $videoNumbers  in the order they will be written
     * @param  list<int>  $brokenOutlines  video numbers whose outline never passes
     * @return array<class-string, list<array<string, mixed>>>
     */
    public static function payloads(array $videoNumbers, array $brokenOutlines = [], bool $lastVideoIncluded = false): array
    {
        $outlines = [];
        $sections = [];
        $closings = [];
        $artifacts = [];
        $maxAttempts = (int) config('course-scripts.runs.max_outline_attempts', 2);

        foreach ($videoNumbers as $number) {
            $mandatory = ['Contenido obligatorio '.$number];

            if (in_array($number, $brokenOutlines, true)) {
                for ($attempt = 0; $attempt < $maxAttempts; $attempt++) {
                    $outlines[] = CanonicalScriptFixture::outlinePayload(['Otro'], durationMinutes: 14);
                }

                continue;
            }

            $outlines[] = CanonicalScriptFixture::outlinePayload($mandatory);

            foreach (['1', '2', '3', '4', '5'] as $section) {
                $sections[] = CanonicalScriptFixture::sectionPayload($section);
            }

            $isLast = $lastVideoIncluded && $number === max($videoNumbers);
            $closings[] = CanonicalScriptFixture::closingPayload(hasNext: ! $isLast);
            $artifacts[] = CanonicalScriptFixture::artifactPayload(CanonicalPracticePackFixture::FILE_A);
            $artifacts[] = CanonicalScriptFixture::artifactPayload(CanonicalPracticePackFixture::FILE_B);
        }

        // The client consumes lists in order and repeats the last entry, so a
        // sentinel keeps the queues from running dry.
        return array_filter([
            GenerateScriptOutlineAgent::class => [...$outlines, end($outlines)],
            GenerateScriptSectionAgent::class => $sections === [] ? [] : [...$sections, end($sections)],
            GenerateScriptClosingAgent::class => $closings === [] ? [] : [...$closings, end($closings)],
            GeneratePracticeArtifactAgent::class => $artifacts === [] ? [] : [...$artifacts, end($artifacts)],
        ], static fn (array $list): bool => $list !== []);
    }
}
