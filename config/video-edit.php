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
        // Bounds ffprobe on an uploaded file; a crafted one must not stall a worker.
        'probe_timeout_seconds' => (int) env('VIDEO_EDIT_PROBE_TIMEOUT', 120),
    ],

    'silence' => [
        'default_threshold_seconds' => 1.0,                    // US-2
        'min_threshold_seconds' => 0.3,                        // D4
        'max_threshold_seconds' => 10.0,                       // D4
        // D4 — system setting. -40 rather than -30: a Spanish word decays into
        // an unstressed last syllable that -30 already reads as silence.
        'noise_floor_db' => -40,
        // D5 — asymmetric: word endings fade out slowly, attacks are sharp.
        'padding_after_speech_ms' => 250,
        'padding_before_speech_ms' => 100,
        // Only when a transcript exists (speech cleanup / AI edit): no silence
        // cut may reach this close to a word Whisper transcribed.
        'word_guard_after_ms' => 150,
        'word_guard_before_ms' => 60,
        // A pause cut inside a sentence keeps this much air, so the two halves
        // of one intonation are not glued together. Sentence ends (Whisper
        // segments closing on . ? !) are cut tight. Needs a transcript.
        'mid_sentence_pause_ms' => 300,
    ],

    /*
    |----------------------------------------------------------------------
    | Tool waits (screen busy while nobody speaks)
    |----------------------------------------------------------------------
    |
    | A silence during which the screen keeps moving is a wait on a tool —
    | Claude or ChatGPT streaming an answer, a Cursor agent editing files,
    | Copilot filling a Word document. It is sped up instead of cut, so the
    | viewer sees the request go out and the result land. Detection is by
    | picture change (FFmpeg `freezedetect`), so it works for any tool.
    |
    */
    'screen_activity' => [
        'enabled' => (bool) env('VIDEO_EDIT_SCREEN_ACTIVITY', true),
        // Pixel change below this counts as still (a blinking caret stays below).
        'freeze_noise_db' => -50,
        'freeze_min_seconds' => 0.5,
        // Share of the silence the screen must spend moving to count as a wait.
        'min_activity_ratio' => 0.5,
        'keep_head_ms' => 1500,
        'keep_tail_ms' => 1000,
        'speed' => 3.0,
        // However long the wait, the sped-up middle never lasts longer on screen.
        'max_compressed_output_ms' => 4000,
    ],

    /*
    | Breaths and mouth noise between words are turned down, never cut: cutting
    | every breath is what makes an edit sound robotic. Needs a transcript.
    */
    'breaths' => [
        'enabled' => (bool) env('VIDEO_EDIT_BREATHS', true),
        'min_gap_ms' => 250,
        'attenuation_db' => -15.0,
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
            // Whisper tidies its transcript and drops hesitations unless the
            // prompt shows it verbatim speech; a filler that is never
            // transcribed can never be cut. Keyed by request language, with
            // `default` for anything else. Under Whisper's 224-token prompt cap.
            'prompts' => [
                'es' => 'Eh, mmm... bueno, este, o sea, vamos a ver. Ehh, ah, pues, digamos que, mmm, sí.',
                'en' => 'Um, uh... so, like, you know, I mean, uh, let\'s see. Hmm, well, basically.',
                'default' => 'Um, uh, eh, mmm... hmm, ah.',
            ],
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

        // Every AI cut waits for the owner's review (OWASP LLM06). Proposals at or
        // above this confidence start ticked in the review; the rest start
        // unticked but are still shown, so nothing is discarded unseen.
        'preselect_above_confidence' => (float) env('VIDEO_EDIT_AI_MIN_CONFIDENCE', 0.8),

        // Transcript words shown either side of a proposed cut in the review.
        'review_context_words' => 8,

        // One runaway appendix must not blow the context window or the bill.
        'max_script_characters' => 120_000,

        'script' => [
            'allowed_extensions' => ['md', 'pdf'],
            'allowed_mime_types' => ['text/markdown', 'text/plain', 'application/pdf'],
            'max_file_bytes' => 10 * 1024 * 1024,
        ],
    ],

    /*
    |----------------------------------------------------------------------
    | Speaking pace (AI edit report)
    |----------------------------------------------------------------------
    |
    | Measured from the Whisper word timings, never by the model. A mini-pause
    | is a gap long enough to hear as hesitation that the silence threshold
    | leaves in; the report flags a recording with too many of them, or with a
    | delivery slower than `slow_words_per_minute`.
    |
    */
    'pace' => [
        'mini_pause_ms' => 400,
        'min_mini_pauses' => 5,
        'slow_words_per_minute' => 130,
        // Below this, words per minute is noise rather than a measurement.
        'min_speaking_ms' => 60_000,
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
        // An unanswered cut review resolves as "keep everything" and renders.
        'review_hours' => 24,
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
