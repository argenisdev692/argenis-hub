<?php

declare(strict_types=1);

/*
|--------------------------------------------------------------------------
| Course Scripts module (spec 002-course-scripts, revision 3)
|--------------------------------------------------------------------------
|
| Every limit, default and heuristic the module enforces. Decision references
| point at specs/002-course-scripts/clarify.md (DEC/Q = author answer, D =
| resolved by default) and research.md (R = verified finding).
|
| No magic number lives inside the module: a limit that is not here is a bug.
|
*/

return [

    /*
    |----------------------------------------------------------------------
    | Uploads (FR-1, FR-1b, FR-10, FR-58)
    |----------------------------------------------------------------------
    |
    | Indexes and content files are parsed locally and in-process (DEC-1).
    | MIME is verified against the real bytes, never the client header.
    |
    */

    'uploads' => [
        'disk_directory' => 'course-scripts',
        'max_kb' => 10 * 1024,                                  // per file; a 48-video index PDF is ~1 MB
        'allowed_extensions' => ['md', 'markdown', 'pdf'],
        'allowed_mime_types' => [
            'text/markdown',
            'text/x-markdown',
            'text/plain',                                       // .md frequently sniffs as text/plain
            'application/pdf',
        ],
        // Below this many extracted characters a PDF has no text layer
        // (scanned/image-only) and is rejected under FR-7. No OCR (R1.3).
        'pdf_min_text_length' => 200,
        'max_content_files' => 10,                              // FR-1b
        'max_content_total_kb' => 30 * 1024,
        'max_style_references' => 10,                           // FR-10
    ],

    /*
    |----------------------------------------------------------------------
    | Course structure sanity (FR-5, FR-7)
    |----------------------------------------------------------------------
    */

    'structure' => [
        'max_blocks' => 20,
        'max_videos' => 200,
        'min_videos' => 1,
        'max_title_length' => 255,
        'max_text_field_length' => 4000,
        'max_list_items' => 30,
        'max_notes_length' => 60_000,                           // per video / course notes (FR-4b/4c)
    ],

    /*
    |----------------------------------------------------------------------
    | Generation runs (FR-14a, FR-15, FR-23, FR-24 · DEC-2, D21)
    |----------------------------------------------------------------------
    |
    | Ceilings are counted in CALLS, not videos. AI writing and AI review calls
    | share one ceiling so the review loop cannot escape it; research calls
    | have their own because they are a different provider and price curve.
    |
    | 900 AI calls fits the 48-video reference course with the second review
    | on (~490 writing + ~175 review, plan §3.6). Lower it at your own cost.
    |
    */

    'runs' => [
        'max_ai_calls_per_run' => (int) env('COURSE_SCRIPTS_MAX_AI_CALLS_PER_RUN', 900),
        'max_research_calls_per_run' => (int) env('COURSE_SCRIPTS_MAX_RESEARCH_CALLS_PER_RUN', 250),
        'max_outline_attempts' => 2,                            // gate A retries
        'max_step_retries' => 1,                                // gate B retries per offending step
        'max_review_iterations' => 3,                           // FR-42
        'expected_rewrite_rounds' => 0.5,                       // estimate only
        'job_timeout_seconds' => 1800,
        'queue' => env('COURSE_SCRIPTS_QUEUE', 'default'),
        'connection' => env('COURSE_SCRIPTS_QUEUE_CONNECTION'),
    ],

    /*
    |----------------------------------------------------------------------
    | Second review (US-13 · DEC-10)
    |----------------------------------------------------------------------
    |
    | Opt-in per run with `with_review`. Scores are 0–10 per dimension; a
    | draft passes when every dimension reaches `min_dimension_score` and the
    | average reaches `min_overall_score`.
    |
    */

    'review' => [
        'default_with_review' => false,
        'min_dimension_score' => 6,
        'min_overall_score' => 7,
    ],

    /*
    |----------------------------------------------------------------------
    | Script shape (FR-8a, FR-29, FR-31)
    |----------------------------------------------------------------------
    |
    | `minutes_per_section` comes from Guion_Video_43/46: 9 minutes, 6 sections.
    |
    */

    'script' => [
        'default_video_minutes' => 8,                           // D16
        'minutes_per_section' => 1.5,
        'time_budget_tolerance_pct' => 10,                      // SC-4
        'min_sections' => 3,
        'max_sections' => 12,
        'practice_ratio' => 0.6,                                // estimate: share of videos with a pack
    ],

    /*
    |----------------------------------------------------------------------
    | Practice packs (FR-36…FR-39b · DEC-9, D19)
    |----------------------------------------------------------------------
    */

    'practice' => [
        'avg_artifacts_per_pack' => 2,                          // estimate (sample: 2 proposals)
        'max_artifacts_per_pack' => 4,
        'table_total_tolerance' => 0.01,                        // FR-36d
        // FR-39b: invented contact data must never use these real domains.
        'contact_domain_denylist' => [
            'gmail.com', 'googlemail.com', 'outlook.com', 'hotmail.com', 'live.com',
            'yahoo.com', 'yahoo.es', 'icloud.com', 'me.com', 'aol.com', 'proton.me',
            'protonmail.com', 'gmx.com', 'gmx.es', 'telefonica.es', 'movistar.es',
            'google.com', 'microsoft.com', 'apple.com', 'amazon.com', 'amazon.es',
            'meta.com', 'facebook.com', 'openai.com', 'anthropic.com',
        ],
    ],

    /*
    |----------------------------------------------------------------------
    | Providers (FR-14, A2/D2)
    |----------------------------------------------------------------------
    |
    | The author picks the WRITER per run from this allowlist. The MODEL is
    | never a request parameter. The reviewer is config('ai.default_for_evaluation').
    |
    */

    'providers' => [
        'selectable_writers' => ['openai', 'anthropic', 'gemini'],
    ],

    /*
    |----------------------------------------------------------------------
    | Continuity (FR-33, FR-33a, A7/D7)
    |----------------------------------------------------------------------
    */

    'continuity' => [
        'predecessor_window' => 3,
        'max_summary_length' => 400,
    ],

    /*
    |----------------------------------------------------------------------
    | Author notes and style exemplars (FR-4d, FR-10 · D15)
    |----------------------------------------------------------------------
    */

    'notes' => [
        'excerpt_budget_chars' => 6000,
        'max_excerpts' => 8,
        'rich_notes_threshold_chars' => 1500,                   // fewer research queries above this
    ],

    'style' => [
        'exemplar_budget_chars' => 6000,
    ],

    /*
    |----------------------------------------------------------------------
    | Research (FR-13a…FR-13j · R10)
    |----------------------------------------------------------------------
    */

    'research' => [
        'subject_queries' => 4,
        'point_queries_max' => 2,
        'recency' => 'year',                                    // Tavily time_range: day|week|month|year
        'finding_content_max_chars' => 4000,
        'thin_snippet_chars' => 300,                            // below this a result may escalate
        'max_firecrawl_per_video' => 2,
        'firecrawl_enabled' => (bool) env('COURSE_SCRIPTS_FIRECRAWL_ENABLED', true),
    ],

    /*
    |----------------------------------------------------------------------
    | Versions and deliverables (FR-45…FR-50 · A6/D6, D17)
    |----------------------------------------------------------------------
    */

    'versions' => [
        'auto_accept_regenerations' => false,
    ],

    'deliverables' => [
        'keep_versions' => null,                                // null = unlimited (D6)
        'signed_url_minutes' => 15,
    ],

    // A deleted course stays recoverable this long; then `model:prune` removes
    // its rows and every stored file (A6/D6).
    'deletion' => [
        'purge_after_days' => 30,
    ],

    /*
    |----------------------------------------------------------------------
    | Rate limits (FR-57, OWASP §14 / LLM10) — per user, per minute
    |----------------------------------------------------------------------
    */

    'rate_limits' => [
        'upload' => 10,
        'generate' => 20,
        'status' => 120,
        'download' => 60,
        'export' => 10,
    ],

];
