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
    | Room tone (OBSERVACIONES round 2)
    |----------------------------------------------------------------------
    |
    | Cuts cannot fix a reverberant recording. What this can do is subtract the
    | steady part of it: `highpass` drops rumble from a desk being knocked, and
    | `afftdn` learns the noise floor and takes the hiss out from under the
    | voice. Expect the tail of a heavy reverb to survive — that is a room, not a
    | noise floor, and it belongs at the desk.
    |
    | Off by default: the reduction is audible on clean speech, so it is a
    | deliberate per-installation choice rather than a sensible default. Set
    | NR_DB to 0 to leave the audio untouched.
    |
    */
    'room_tone' => [
        'enabled' => (bool) env('VIDEO_EDIT_ROOM_TONE', false),
        // How much to pull down, in dB. FFmpeg accepts 0.01 – 97; 12 is mild.
        'reduction_db' => (float) env('VIDEO_EDIT_ROOM_TONE_NR_DB', 12),
        // Where the floor is assumed to sit. −25 suits a quiet room.
        'floor_db' => (float) env('VIDEO_EDIT_ROOM_TONE_NF_DB', -25),
        // 0 disables the highpass stage on its own.
        'highpass_hz' => (int) env('VIDEO_EDIT_ROOM_TONE_HIGHPASS_HZ', 80),
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
            // `whisper-1` on purpose. OpenAI's newer models (`gpt-transcribe`,
            // `gpt-4o-transcribe`) answer with plain text and no word timings,
            // which is the one thing this module cannot work without — see the
            // docblock on OpenAiWhisperTranscriber. OpenAI has announced
            // `whisper-1` for removal on 2027-02-26; that migration goes to a
            // different provider, not to a different OpenAI model.
            'model' => env('OPENAI_WHISPER_MODEL', 'whisper-1'),
            'timeout_seconds' => (int) env('OPENAI_WHISPER_TIMEOUT', 1_200),
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
        'model' => env('VIDEO_EDIT_AI_MODEL', 'gemini-3.8-flash'),
        'timeout_seconds' => (int) env('VIDEO_EDIT_AI_TIMEOUT', 300),

        // Every AI cut waits for the owner's review (OWASP LLM06). Proposals at or
        // above this confidence start ticked in the review; the rest start
        // unticked but are still shown, so nothing is discarded unseen.
        'preselect_above_confidence' => (float) env('VIDEO_EDIT_AI_MIN_CONFIDENCE', 0.8),

        // Transcript words shown either side of a proposed cut in the review.
        'review_context_words' => 8,

        // One runaway appendix must not blow the context window or the bill.
        'max_script_characters' => 120_000,

        // Mirrors the `max:20000` on `ai_edit.instructions`, so a profile can
        // never prefill a field the validator would then reject.
        'max_instructions_characters' => 20_000,

        'script' => [
            'allowed_extensions' => ['md', 'pdf'],
            'allowed_mime_types' => ['text/markdown', 'text/plain', 'application/pdf'],
            'max_file_bytes' => 10 * 1024 * 1024,
        ],

        /*
        |----------------------------------------------------------------------
        | Instruction profiles
        |----------------------------------------------------------------------
        |
        | `ai_edit.instructions` is free text the owner types per video, and
        | typing the same standing notes into every single one is how they end up
        | not applied at all. A profile is a saved, named block of those notes:
        | picking one in the form fills the field, the text stays editable, and
        | whatever is finally sent is stored on the edit as before — so a
        | profile is a starting point, never an override.
        |
        | These live in config rather than in a table on purpose: they are
        | editorial policy, they want code review and history like any other
        | change, and they are identical for every edit. `supervisor_lms` is the
        | distillation of the standing notes in GUIDE/MODULE-VIDEOS/
        | OBSERVACIONES.txt — edit that report freely, but edit the rules here.
        |
        | Text reaches the model as data, never as system rules: the agent in
        | Infrastructure/Ai/AnalyzeVideoEditAgent refuses to obey anything in here
        | that reads like an instruction to override its own rules.
        |
        */
        'instruction_profiles' => [
            'supervisor_lms' => [
                'name' => 'Supervisor LMS',
                'instructions' => <<<'PROFILE'
                    Criterio de edicion para formacion LMS:

                    - Ritmo dinamico. Prefiere un tono fluido y frases encadenadas: senala
                      los puntos donde el ponente se detiene entre frases que debian
                      seguir, porque eso solo se arregla en una regrabacion, no en el
                      corte.
                    - Cortes limpios. Ningun corte debe interrumpir una palabra ni dejar
                      una conjucion o silaba suelta en el aire. Si detectas un empalme
                      tosco, senalalo.
                    - No leer los prompts en voz alta. Si el ponente lee literalmente un
                      prompt del guion, recordale que deberia pegarse en pantalla y
                      resumirse en una frase.
                    - Ejercicios practicos. Plantea ejemplos que apliquen de verdad al
                      mundo laboral del alumno, no ejercicios academicos.
                    - Enseña el paso completo. Donde haya cambio de escena o se abra un
                      menu, no insertes cortes: la accion se ve entera.
                    - Nunca cortes mientras una herramienta esta trabajando. Si Claude,
                      ChatGPT, Gemini o cualquier agente esta generando en pantalla,
                      ese tramo se deja intacto.
                    PROFILE,
            ],
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

        /*
        | Cut edges (OBSERVACIONES round 2).
        |
        | Removing the words around a mistake leaves the seam visible: the
        | conjunction the speaker had already committed to ("...de Veralux Group
        | y…") and any word the transcriber heard cut off mid-syllable ("o co-").
        | A cut grows this far to take those with it, and only ever past a word
        | that cannot end a sentence — so no clause is ever swallowed.
        |
        | 0 disables it entirely.
        */
        'edge_absorb_ms' => (int) env('VIDEO_EDIT_EDGE_ABSORB_MS', 400),
        'dangling_edge_words' => [
            // Spanish. Every one of these is a word that can never end a
            // sentence, which is the whole safety rule — adding a content word
            // here would let a cut eat the end of the neighbouring clause.
            'y', 'e', 'o', 'u', 'ni', 'que', 'a', 'al', 'de', 'del',
            'la', 'el', 'lo', 'los', 'las', 'un', 'una', 'unos', 'unas',
            'en', 'con', 'por', 'para',
        ],
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
