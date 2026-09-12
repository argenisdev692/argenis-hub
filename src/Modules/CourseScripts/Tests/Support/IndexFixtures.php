<?php

declare(strict_types=1);

namespace Modules\CourseScripts\Tests\Support;

use Modules\CourseScripts\Domain\ValueObjects\ParsedIndex;
use Modules\CourseScripts\Infrastructure\Parsing\MarkdownIndexParser;

/**
 * Shared access to the committed index fixtures.
 *
 * `index-sample.md` is a verbatim copy of the project's real course index
 * (`GUIDE/MODULE-VIDEOS/pildoras_video_claude_usuarios.md`) and
 * `index-sample.pdf` is its text-layer twin, so the Markdown and PDF parser
 * tests assert field parity over identical content (spec SC-2).
 *
 * A class rather than Pest helper functions: both the Markdown and the PDF
 * suite need these paths, and duplicate global function names across test
 * files are a collision waiting to happen.
 */
final class IndexFixtures
{
    public static function path(string $name): string
    {
        return __DIR__.'/../Fixtures/'.$name;
    }

    public static function markdownPath(): string
    {
        return self::path('index-sample.md');
    }

    public static function pdfPath(): string
    {
        return self::path('index-sample.pdf');
    }

    public static function markdown(): string
    {
        return (string) file_get_contents(self::markdownPath());
    }

    /**
     * The reference index, parsed. Used by every test that asserts against the
     * real 48-video course rather than a hand-written miniature.
     */
    public static function parsedSample(): ParsedIndex
    {
        return (new MarkdownIndexParser)->parse(self::markdownPath(), 'text/markdown');
    }
}
