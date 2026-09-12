<?php

declare(strict_types=1);

/*
|--------------------------------------------------------------------------
| Video Edits module (spec 001-video-edit)
|--------------------------------------------------------------------------
|
| Every limit and default the module enforces. Decision references point at
| specs/001-video-edit/clarify.md (D = resolved by default, Q/P = user).
|
*/

return [

    'limits' => [
        // Minimum clips per mode lives in VideoEditMode::minimumSources().
        'max_sources' => 10,                                   // D2
        'max_file_bytes' => 2 * 1024 * 1024 * 1024,            // D2 — 2 GB
        'size_tolerance_ratio' => 0.01,                        // declared vs stored size
        'max_total_duration_seconds' => 90 * 60,               // D2
        'max_manual_ranges' => 500,                            // D9
        'max_range_note_length' => 120,
        'allowed_extensions' => ['mp4', 'mov', 'webm', 'mkv'],
        'allowed_mime_types' => ['video/mp4', 'video/quicktime', 'video/webm', 'video/x-matroska'],
        // FFprobe `format_name` tokens accepted after upload (OWASP §8 content check).
        'allowed_containers' => ['mov', 'mp4', 'webm', 'matroska'],
    ],

    'silence' => [
        'default_threshold_seconds' => 1.0,                    // US-2
        'min_threshold_seconds' => 0.3,                        // D4
        'max_threshold_seconds' => 10.0,                       // D4
        'noise_floor_db' => -30,                               // D4 — system setting
        'padding_ms' => 150,                                   // D5
    ],

    /*
    |----------------------------------------------------------------------
    | Speech cleanup (V2 · US-10)
    |----------------------------------------------------------------------
    |
    | Dictionaries live here rather than in the detector so a new filler can be
    | added without a deploy of new code, and so Spanish and English can be
    | tuned independently (resolves R3 — the recordings are Spanish-first, but
    | the English padding words show up constantly in tech tutorials).
    |
    */
    'speech' => [
        'default_language' => env('VIDEO_EDIT_SPEECH_LANGUAGE', 'es'),

        // 16 kHz mono is what speech models resample to anyway; 32 kbps keeps
        // the 90-minute maximum (D2) near 21 MB, under OpenAI's 25 MB cap.
        'audio_sample_rate' => 16_000,
        'audio_bitrate_kbps' => 32,
        'max_audio_bytes' => 25 * 1024 * 1024,

        // A word the provider is unsure about is a word we must not cut on.
        'min_confidence' => 0.5,
        // Longer than this and a prefix match is a real word, not a false start.
        'max_stutter_fragment_ms' => 400,

        'openai' => [
            'api_key' => env('OPENAI_API_KEY'),
            'model' => env('OPENAI_WHISPER_MODEL', 'whisper-1'),
            'timeout_seconds' => (int) env('OPENAI_WHISPER_TIMEOUT', 600),
        ],

        'dictionaries' => [
            // Non-lexical hesitations — transcribed as words, never meaningful.
            'filler_sounds' => [
                'eh', 'ehh', 'em', 'emm', 'mm', 'mmm', 'ah', 'ahh', 'uh', 'uhh',
                'um', 'umm', 'er', 'err', 'este', 'esteee', 'hmm', 'hm',
            ],
            // Real words used as padding. Deliberately short: every entry here
            // is a word that will be deleted from the user's speech, so it must
            // be one that is padding essentially every time it appears.
            'filler_words' => [
                'bueno', 'entonces', 'digamos', 'basicamente', 'literalmente',
                'like', 'basically', 'literally', 'actually', 'so',
            ],
            // Multi-word padding: "sea" alone is ordinary Spanish, "o sea" is not.
            'filler_phrases' => [
                'o sea', 'es decir', 'you know', 'i mean', 'kind of', 'sort of',
            ],
            // Doubling these is emphasis, not a stumble.
            'repetition_allow_list' => [
                'no', 'si', 'muy', 'ya', 'very', 'no-no',
            ],
        ],
    ],

    /*
    |----------------------------------------------------------------------
    | AI edit (V3 · US-12/13/14)
    |----------------------------------------------------------------------
    |
    | Provider selection goes through config/ai.php via Shared AIClientInterface,
    | so switching Gemini for OpenAI or Anthropic is a config change.
    |
    */
    'ai' => [
        'provider' => env('VIDEO_EDIT_AI_PROVIDER', 'gemini'),

        // Flash, not Pro: the job is reading a transcript and returning
        // structured decisions, which is squarely the Flash tier's workhorse
        // case, at a fraction of the cost over a 20-minute recording.
        'model' => env('VIDEO_EDIT_AI_MODEL', 'gemini-3.7-flash'),
        'timeout_seconds' => (int) env('VIDEO_EDIT_AI_TIMEOUT', 300),

        // Decision R6: pause markers and retakes apply automatically at or above
        // this confidence. Anything less is discarded rather than guessed at.
        'auto_apply_above_confidence' => (float) env('VIDEO_EDIT_AI_MIN_CONFIDENCE', 0.8),

        // One runaway appendix must not blow the context window or the bill.
        'max_script_characters' => 120_000,

        'script' => [
            'allowed_extensions' => ['md', 'pdf'],
            'allowed_mime_types' => ['text/markdown', 'text/plain', 'application/pdf'],
            'max_file_bytes' => 10 * 1024 * 1024,
        ],
    ],

    'cuts' => [
        'min_kept_fragment_ms' => 250,                         // D6
        'min_output_ms' => 1000,                               // D6
    ],

    'output' => [
        'max_width' => 1920,                                   // D3
        'max_height' => 1080,                                  // D3
        'max_frame_rate' => 60,                                // D3
        'video_codec' => 'libx264',
        'pixel_format' => 'yuv420p',
        'crf' => 20,
        'preset' => 'medium',
        'intermediate_crf' => 14,                              // AD-4
        'intermediate_preset' => 'veryfast',                   // AD-4
        'audio_codec' => 'aac',
        'audio_bitrate_kbps' => 192,
        'audio_sample_rate' => 48000,                          // D3
        'audio_channels' => 2,                                 // D3
    ],

    'queue' => [
        // Queue CONNECTION name from config/queue.php; phpunit.xml overrides it with `sync`.
        'connection' => env('VIDEO_EDIT_QUEUE_CONNECTION', 'video-edits'),  // P3 / AD-15
        'name' => env('VIDEO_EDIT_QUEUE', 'video-edits'),
    ],

    'retention' => [
        'failed_sources_hours' => 24,                          // Q3c / FR-10
        'draft_hours' => 24,                                   // D17
        'stale_processing_minutes' => 65,                      // AD-14
    ],

    'urls' => [
        'upload_ttl_minutes' => 60,                            // AD-1
        'download_ttl_minutes' => 15,                          // D12
    ],

    'storage' => [
        // Object keys live under this prefix on the R2 disk behind StoragePort.
        'path_prefix' => 'video-edits',
    ],

    'workspace' => [
        'disk' => 'video-edit-workspace',                      // AD-12
        // Free space required before download, as a multiple of the total source size (R5).
        'free_space_multiplier' => 3,
    ],

    'progress' => [
        'min_percent_step' => 2,
        'min_interval_seconds' => 5,
    ],

];
