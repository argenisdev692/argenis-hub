<?php

declare(strict_types=1);

namespace Modules\CourseScripts\Tests\Support;

use Barryvdh\DomPDF\Facade\Pdf;
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

    public static function parsed(string $fixture): ParsedIndex
    {
        return (new MarkdownIndexParser)->parse(self::path($fixture), 'text/markdown');
    }

    /**
     * A text-layer PDF twin of a Markdown fixture, rendered on the fly so the
     * notes parity test does not depend on another committed binary. The
     * caller deletes the file.
     */
    public static function pdfTwinOf(string $fixture): string
    {
        $markdown = (string) file_get_contents(self::path($fixture));
        $path = tempnam(sys_get_temp_dir(), 'cs-twin-').'.pdf';

        $html = '<html><head><meta charset="utf-8"></head><body>'
            .'<pre style="font-family: DejaVu Sans, sans-serif; font-size: 9px; white-space: pre-wrap">'
            .htmlspecialchars($markdown, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8')
            .'</pre></body></html>';

        file_put_contents($path, Pdf::loadHTML($html)->output());

        return $path;
    }
}
