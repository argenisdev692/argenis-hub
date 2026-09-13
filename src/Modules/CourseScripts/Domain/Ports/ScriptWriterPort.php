<?php

declare(strict_types=1);

namespace Modules\CourseScripts\Domain\Ports;

use Modules\CourseScripts\Domain\Exceptions\GenerationProviderException;
use Modules\CourseScripts\Domain\ValueObjects\ScriptDraft;
use Modules\CourseScripts\Domain\ValueObjects\VideoWritingContext;

/**
 * Writes a script in steps (plan §3.5). Every method is exactly one provider
 * call and returns normalised structure (shapes in {@see ScriptDraft}).
 *
 * `$corrections` are validator violations or reviewer objections from a
 * previous attempt, handed back so the rewrite fixes them.
 */
interface ScriptWriterPort
{
    /**
     * @param  list<string>  $corrections
     *
     * @throws GenerationProviderException
     */
    public function outline(VideoWritingContext $context, string $provider, array $corrections = []): ScriptDraft;

    /**
     * Segments for one top-level section and its sub-sections.
     *
     * @param  list<string>  $corrections
     * @return array<string, list<array<string, mixed>>> section number => segments
     *
     * @throws GenerationProviderException
     */
    public function sectionSegments(VideoWritingContext $context, ScriptDraft $draft, string $sectionNumber, string $provider, array $corrections = []): array;

    /**
     * @param  list<string>  $corrections
     * @return array<string, mixed> closing shape
     *
     * @throws GenerationProviderException
     */
    public function closing(VideoWritingContext $context, ScriptDraft $draft, string $provider, array $corrections = []): array;

    /**
     * @param  list<string>  $corrections
     * @return array<string, mixed> artifact shape
     *
     * @throws GenerationProviderException
     */
    public function artifact(VideoWritingContext $context, ScriptDraft $draft, string $fileName, string $provider, array $corrections = []): array;
}
