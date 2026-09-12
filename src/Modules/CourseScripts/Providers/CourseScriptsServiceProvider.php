<?php

declare(strict_types=1);

namespace Modules\CourseScripts\Providers;

use Illuminate\Support\ServiceProvider;
use Modules\CourseScripts\Domain\Ports\IndexDocumentParserPort;
use Modules\CourseScripts\Domain\Services\CourseIndexValidator;
use Modules\CourseScripts\Infrastructure\Parsing\IndexDocumentParser;
use Modules\CourseScripts\Infrastructure\Parsing\MarkdownIndexParser;
use Modules\CourseScripts\Infrastructure\Parsing\PdfIndexParser;
use Smalot\PdfParser\Parser as SmalotParser;

/**
 * Composition root of the Course Scripts module (spec 002-course-scripts).
 *
 * Route registration, rate limiters, AI port bindings and domain-exception
 * rendering are added as their adapters land (tasks.md Phases D-K). The module
 * ships session-authenticated JSON endpoints only — no Sanctum API file and no
 * Inertia page until a frontend spec exists, matching the VideoEdits precedent
 * (plan 001 decision P4).
 */
final class CourseScriptsServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->registerIndexParsing();
    }

    public function boot(): void
    {
        //
    }

    /**
     * Markdown first: it is the cheaper branch and the one the PDF adapter
     * delegates into, so registering it ahead of the PDF parser keeps the
     * dispatcher's resolution order matching the cost order.
     *
     * Limits come from config rather than constructor defaults so a course with
     * unusual size, or a PDF whose text threshold needs tuning, is an ops change
     * rather than a deploy.
     */
    private function registerIndexParsing(): void
    {
        $this->app->singleton(MarkdownIndexParser::class);

        $this->app->bind(PdfIndexParser::class, static fn (): PdfIndexParser => new PdfIndexParser(
            grammar: app(MarkdownIndexParser::class),
            parser: new SmalotParser,
            minimumTextLength: (int) config('course-scripts.uploads.pdf_min_text_length', 200),
        ));

        $this->app->bind(IndexDocumentParserPort::class, static fn (): IndexDocumentParser => new IndexDocumentParser([
            app(MarkdownIndexParser::class),
            app(PdfIndexParser::class),
        ]));

        $this->app->bind(CourseIndexValidator::class, static fn (): CourseIndexValidator => new CourseIndexValidator(
            minVideos: (int) config('course-scripts.structure.min_videos', 1),
            maxVideos: (int) config('course-scripts.structure.max_videos', 200),
            maxBlocks: (int) config('course-scripts.structure.max_blocks', 20),
        ));
    }
}
