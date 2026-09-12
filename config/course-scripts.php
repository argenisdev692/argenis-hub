<?php

declare(strict_types=1);

/*
|--------------------------------------------------------------------------
| Course Scripts module (spec 002-course-scripts)
|--------------------------------------------------------------------------
|
| Every limit, default and heuristic the module enforces. Decision references
| point at specs/002-course-scripts/clarify.md (DEC = user answer, D = resolved
| by default) and research.md (R = verified finding).
|
| No magic number lives inside the module: a limit that is not here is a bug.
|
*/

return [

    /*
    |----------------------------------------------------------------------
    | Index upload (FR-1, FR-58)
    |----------------------------------------------------------------------
    |
    | The index is parsed locally and in-process (DEC-1). MIME is verified with
    | ext-fileinfo against the real bytes, never the client-supplied header.
    |
    */

    'uploads' => [
        'max_kb' => 10 * 1024,                                  // 10 MB — a 48-video index PDF is ~1 MB
        'allowed_extensions' => ['md', 'markdown', 'pdf'],
        'allowed_mime_types' => [
            'text/markdown',
            'text/x-markdown',
            'text/plain',                                       // .md frequently sniffs as text/plain
            'application/pdf',
        ],
        // Below this many extracted characters a PDF is treated as having no
        // text layer (scanned/image-only) and is REJECTED under FR-7 rather
        // than parsed into an empty course. There is no OCR (R1.3).
        'pdf_min_text_length' => 200,
        'max_style_references' => 10,                           // FR-10
    ],

    /*
    |----------------------------------------------------------------------
    | Course structure sanity (FR-5, FR-7)
    |----------------------------------------------------------------------
    */

    'structure' => [
        'max_blocks' => 20,
        'max_videos' => 200,                                    // NFR scalability: ~100 videos expected
        'min_videos' => 1,                                      // zero videos is not a course (FR-25)
        'max_title_length' => 255,
        'max_text_field_length' => 4000,                        // objective / expected result
        'max_list_items' => 30,                                 // learning areas, mandatory content, …
    ],

    /*
    |----------------------------------------------------------------------
    | Generation runs (FR-15, FR-23, FR-24)
    |----------------------------------------------------------------------
    |
    | The ceiling is counted in PROVIDER CALLS, not videos (DEC-2), because
    | two-stage generation plus the review loop means one video is 8-12 calls,
    | not one. A video-count ceiling would never notice a script that iterated
    | five times.
    |
    | `max_calls_per_run` default sizing, from plan.md §3.4: the 48-video
    | reference course estimates ~384 calls and up to ~580 with rewrites. 700
    | leaves that whole course room to finish while still stopping a runaway.
    | Set it LOWER at your own cost: a ceiling below the estimate stops every
    | full-course run halfway, every time.
    |
    */

    'runs' => [
        'max_calls_per_run' => (int) env('COURSE_SCRIPTS_MAX_CALLS_PER_RUN', 700),
        'max_review_iterations' => 3,                           // FR-42, bounded rewrite loop
        'max_outline_attempts' => 2,                            // structural retry before failing the video
        'job_timeout_seconds' => 900,
        'queue' => env('COURSE_SCRIPTS_QUEUE', 'default'),
    ],

    /*
    |----------------------------------------------------------------------
    | Script shape (FR-29, FR-31)
    |----------------------------------------------------------------------
    |
    | `minutes_per_section` is not a guess: reference scripts Guion_Video_43
    | and Guion_Video_46 are both 9 minutes across 6 numbered sections. It
    | drives the CallEstimator (US-12) and the outline agent's guidance.
    |
    */

    'script' => [
        'minutes_per_section' => 1.5,
        'time_budget_tolerance_pct' => 10,                      // SC-4
        'min_sections' => 3,
        'max_sections' => 12,
    ],

    /*
    |----------------------------------------------------------------------
    | Providers (FR-14, A2/D2)
    |----------------------------------------------------------------------
    |
    | The author picks the WRITER per run from this allowlist. The MODEL is
    | never a request parameter — a user-supplied model string is both a
    | prompt-injection surface and a billing surface. Models come from
    | config/ai.php and the provider's own default.
    |
    | The reviewer defaults to config('ai.default_for_evaluation'), which this
    | application already keeps distinct from the writing default so the gate
    | is independent out of the box (D3). When an author's explicit writer
    | choice collides with it, the run is still allowed — it just reports
    | `reviewer_not_independent` (FR-41).
    |
    */

    'providers' => [
        'selectable_writers' => ['openai', 'anthropic', 'gemini'],
    ],

    /*
    |----------------------------------------------------------------------
    | Continuity (FR-33, FR-33a, A7/D7)
    |----------------------------------------------------------------------
    |
    | A late-course video cannot carry 47 full scripts into a prompt. Context
    | is the stored `taught_summary` of the immediate predecessors plus the
    | video's block position — which is exactly what the reference material
    | does: video 46 names videos 43, 44 and 45 in two sentences.
    |
    */

    'continuity' => [
        'predecessor_window' => 3,
        'max_summary_length' => 400,
    ],

    /*
    |----------------------------------------------------------------------
    | Deliverables (FR-45, FR-48, A6/D6)
    |----------------------------------------------------------------------
    */

    'deliverables' => [
        // null = unlimited. All script versions are retained for the life of
        // the course; the value of "what did version 2 say" stays high while
        // a course is in production (D6).
        'keep_versions' => null,
        'bundle_path_template' => 'block-%02d/video-%02d',      // D10
    ],

    /*
    |----------------------------------------------------------------------
    | Rate limits (FR-57, OWASP §14 / LLM10)
    |----------------------------------------------------------------------
    |
    | Per user, per minute. These sit ALONGSIDE the per-run call ceiling, not
    | instead of it: the throttle guards the endpoint, the ceiling guards the
    | wallet.
    |
    */

    'rate_limits' => [
        'upload' => 10,
        'generate' => 20,
        'export' => 30,
        'download' => 60,
    ],

];
