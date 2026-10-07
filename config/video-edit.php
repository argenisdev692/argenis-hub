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
    | Loudness (OBSERVACIONES round 2 · "umbral de captación muy bajo")
    |----------------------------------------------------------------------
    |
    | "A veces las palabras no se aprecian bien y suenan cortadas." Denoising
    | makes that worse, not better: `afftdn` subtracts energy from the whole
    | signal, so the words that were already quiet come out quieter still. What
    | actually rescues them is bringing the programme back up to a known level.
    |
    | `loudnorm` is the EBU R128 / ITU BS.1770 normaliser, and these are the
    | podcast values every student platform targets: −16 LUFS integrated, −1.5
    | dBTP true peak (leaving headroom so the platform's own encoder does not
    | clip), LRA 11 to keep the natural dynamic range of speech rather than
    | flattening it into a broadcast compressor.
    |
    | It runs AFTER the denoiser, which is the only correct order: normalising
    | first would just hand `afftdn` a louder noise floor to learn.
    |
    | Single-pass mode, not two-pass. Two-pass needs a measurement pass over the
    | whole file and a linear filter baked from the result, which a worker that
    | renders one edit at a time through a filtergraph cannot express; dynamic
    | mode is the right trade for a talking-head tutorial.
    */
    /*
    |----------------------------------------------------------------------
    | Capture chain — what OBS already did to the file (guia_audio_obs.md)
    |----------------------------------------------------------------------
    |
    | The source arrives already processed. OBS runs the Fifine USB mic through
    | its own filter chain — RNNoise, a 3:1 compressor, a 4:1 expander and a
    | limiter — and Windows' own enhancements are switched off precisely so
    | nothing is applied twice on the way in.
    |
    | That fact has to be known here, because the obvious default is wrong: a
    | spectral denoiser on top of RNNoise does not clean anything, it subtracts
    | energy from speech that is already clean and lands on the metallic
    | "underwater" artefact people blame on the recorder. Same for a second
    | compressor on top of the first: the levels are already level.
    |
    | So the chain below is OFF by default, and `room_tone` is deliberately
    | gentle. Set `VIDEO_EDIT_VOICE_COMPRESSOR` / `_EXPANDER` / `_LIMITER` /
    | `_RNNOISE` to true only for a recording that did NOT come through OBS.
    |
    | `loudness` is the exception and stays on: OBS has no loudness
    | normalisation filter, and the guide switches Windows' enhancements off, so
    | nothing in the capture path targets a delivery level. -16 LUFS is a
    | destination, not a duplicate of a stage already applied.
    */
    'voice_chain' => [
        // OBS's Noise Suppression is RNNoise. Needs a model file; see below.
        'rnnoise' => [
            'enabled' => (bool) env('VIDEO_EDIT_VOICE_RNNOISE', false),
            // arnndn needs a real .rnnn model on disk. RNNoise's is sh.rnnn.
            // Absent or unreadable, the stage is skipped rather than failing the
            // render: a missing model is a config problem, not a bad recording.
            'model' => env('VIDEO_EDIT_VOICE_RNNOISE_MODEL', ''),
            // How much of the denoised signal to keep. 1 is fully denoised; OBS
            // itself sits around 1.
            'mix' => (float) env('VIDEO_EDIT_VOICE_RNNOISE_MIX', 1.0),
        ],

        // Mirrors OBS's Compressor: 3:1 above -18 dB, 6 ms attack, 100 ms
        // release, +3 dB make-up. FFmpeg takes linear ratios, so the dB figures
        // from the guide are converted here rather than pasted.
        'compressor' => [
            'enabled' => (bool) env('VIDEO_EDIT_VOICE_COMPRESSOR', false),
            'threshold_db' => (float) env('VIDEO_EDIT_VOICE_COMP_THRESHOLD', -18.0),
            'ratio' => (float) env('VIDEO_EDIT_VOICE_COMP_RATIO', 3.0),
            'attack_ms' => (float) env('VIDEO_EDIT_VOICE_COMP_ATTACK', 6.0),
            'release_ms' => (float) env('VIDEO_EDIT_VOICE_COMP_RELEASE', 100.0),
            'makeup_db' => (float) env('VIDEO_EDIT_VOICE_COMP_MAKEUP', 3.0),
            // 2.82843 is FFmpeg's default and OBS has no equivalent knob.
            'knee' => (float) env('VIDEO_EDIT_VOICE_COMP_KNEE', 2.82843),
        ],

        // Mirrors OBS's Expander: 4:1 below -40 dB, RMS detection. FFmpeg's
        // `agate` is a downward gate rather than a true expander, so the expander
        // is approximated by a high ratio plus a deep `range`.
        'expander' => [
            'enabled' => (bool) env('VIDEO_EDIT_VOICE_EXPANDER', false),
            'threshold_db' => (float) env('VIDEO_EDIT_VOICE_EXP_THRESHOLD', -40.0),
            'ratio' => (float) env('VIDEO_EDIT_VOICE_EXP_RATIO', 4.0),
            'attack_ms' => (float) env('VIDEO_EDIT_VOICE_EXP_ATTACK', 10.0),
            'release_ms' => (float) env('VIDEO_EDIT_VOICE_EXP_RELEASE', 100.0),
            // Deepest attenuation applied below the threshold. -40 dB matches
            // where the guide puts the expander, so keyboard and fan noise
            // between phrases go as quiet as OBS takes them.
            'range_db' => (float) env('VIDEO_EDIT_VOICE_EXP_RANGE', -40.0),
        ],

        // Mirrors OBS's Limiter at -3 dB. FFmpeg's alimiter auto-levels by
        // default (`level=true`), which would silently fight `loudnorm`
        // downstream, so it is switched off explicitly and only the ceiling kept.
        'limiter' => [
            'enabled' => (bool) env('VIDEO_EDIT_VOICE_LIMITER', false),
            'ceiling_db' => (float) env('VIDEO_EDIT_VOICE_LIMIT_CEILING', -3.0),
            'attack_ms' => (float) env('VIDEO_EDIT_VOICE_LIMIT_ATTACK', 5.0),
            'release_ms' => (float) env('VIDEO_EDIT_VOICE_LIMIT_RELEASE', 60.0),
        ],
    ],

    'loudness' => [
        'enabled' => (bool) env('VIDEO_EDIT_LOUDNORM', true),
        'target_lufs' => (float) env('VIDEO_EDIT_LOUDNORM_I', -16.0),
        'true_peak_dbtp' => (float) env('VIDEO_EDIT_LOUDNORM_TP', -1.5),
        'loudness_range' => (float) env('VIDEO_EDIT_LOUDNORM_LRA', 11.0),
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
                    - No atropelles los pasos del ejemplo. Si el ponente recorre los
                      pasos de golpe, senalalo con su timecode exacto: el alumno se
                      pierde en el paso que no se explica, no en el que se explica
                      dos veces.
                    - Microfono. Si detectas palabras cortadas o apenas inteligibles,
                      conviene subir la ganancia del micro o acercarlo; en el corte no
                      se arregla.
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

        /*
        |----------------------------------------------------------------------
        | Rushed stretches (OBSERVACIONES round 2)
        |----------------------------------------------------------------------
        |
        | "Ciertos pasos de los ejemplos se muestran muy rápido y el alumno puede
        | perderse fácilmente." The global pace number cannot see this — a
        | recording can average 145 words/min and still sprint through the part
        | that matters. So the test is RELATIVE: a run of consecutive words
        | spoken markedly faster than the speaker's own average for this
        | recording, for long enough to lose a viewer.
        |
        | Measured PER WORD — the gap in front of a word plus the word itself —
        | rather than over a sliding window. A window is a stab at estimating a
        | rate from a sample, and it bleeds: the window straddling the boundary
        | of a fast stretch is already fast enough to qualify, so the reported
        | range starts a second or two before the rush actually begins, and
        | advice with the wrong timecode is worse than no advice.
        |
        | Per-word rate has no such slack. It includes the gaps, so a rushed
        | stretch has to beat its own pauses to register — which is the
        | deliberate part: an ordinary paragraph never trips it. It also
        | survives silence removal, since the pauses it is measured against are
        | exactly what the edit removes, making the delivered video faster still.
        |
        | Both floors have to be cleared. The ratio is relative, so a slow
        | speaker is never told they sped up; the absolute floor is the
        | converse, so a uniformly slow recording with one fast burst still gets
        | the burst reported without the ratio being the only judge.
        */
        'rush_ratio' => 1.35,
        'rush_floor_words_per_minute' => 190,
        // A run shorter than this is one hurried sentence, not a streak.
        'rush_min_total_ms' => 4_000,
        'rush_min_words' => 8,
    ],

    /*
    |----------------------------------------------------------------------
    | Unclear words (OBSERVACIONES round 2)
    |----------------------------------------------------------------------
    |
    | "El micrófono parece tener un umbral de captación muy bajo, por lo que a
    | veces las palabras no se aprecian bien y suenan cortadas."
    |
    | Half of that is the desk, and no edit can fix it. The other half is
    | measurable here: when the input never reached the preamp, Whisper hears the
    | word stop mid-syllable and writes the fragment with a trailing hyphen
    | ("co-"). That is the transcriber's own evidence that the word was clipped,
    | and it is the only signal in the pipeline that distinguishes "the speaker
    | said this badly" from "the microphone never got this".
    |
    | `loudness` above is what rescues the words that survived. This is what says
    | how many did not, and where.
    */
    'clarity' => [
        // Reporting four clipped words in a recording is useful; reporting one
        // is noise, and one is what a single false start produces.
        'min_clipped_words' => 3,
        'max_reported_words' => 20,
    ],

    'cuts' => [
        'min_kept_fragment_ms' => 250,                         // D6
        'min_output_ms' => 1000,                               // D6

        /*
        |----------------------------------------------------------------------
        | Join seams (OBSERVACIONES round 2 · 05:22)
        |----------------------------------------------------------------------
        |
        | "Salto de audio/respiración muy brusco ... falta suavizar el crossfade
        | o dejar un aire natural." The reviewer's own wording offers the two
        | ways out, and only one of them is safe here.
        |
        | A real crossfade (`acrossfade`) OVERLAPS the two clips, which shortens
        | the output by the overlap and drifts the lip sync away from the
        | picture — the video side is `trim`+`concat`, never `xfade`, so nothing
        | absorbs the difference. On speech it also smears two syllables into one
        | phaser artifact. So this takes the reviewer's second option: a fade
        | long enough to read as a breath, followed by a short hold of room tone.
        | The picture holds its last frame for exactly as long as the audio holds
        | the air, so the two never drift.
        |
        | `fade_ms` was 15 ms, which only removed the click; the seam itself was
        | still audible as a splice. 40 ms is long enough to be heard as a breath
        | and short enough to cost no intelligibility.
        |
        | Set `air_ms` to 0 to get the old 15 ms behaviour back (well: the new
        | fade length still applies).
        */
        'join_fade_ms' => (int) env('VIDEO_EDIT_JOIN_FADE_MS', 40),
        'join_air_ms' => (int) env('VIDEO_EDIT_JOIN_AIR_MS', 80),

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
        // The client spec (1080p, >=30 fps) is only a CEILING here. The profile
        // never upscales: `OutputProfile::fromFirstSource` scales by
        // min(1, max/source), so a 720p capture stays 720p and a 25 fps capture
        // stays 25 fps. Both would ship and fail the spec silently — nothing in
        // the pipeline asserts the delivered file against it.
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

        /*
        |----------------------------------------------------------------------
        | Delivery format (client spec: 1080p, >=30 fps, H.264 .mp4, 48 kHz)
        |----------------------------------------------------------------------
        |
        | The rate and the channel count were the last two output settings that
        | could not be changed without editing this file, because every other
        | one is env-driven. That mattered: the capture guide (guia_audio_obs.md
        | 2.1) records OBS in MONO, so a hardcoded 2 upmixed every export and the
        | file no longer matched what was recorded. Setting the count to 1 keeps
        | the render in the source's own layout (`FilterGraphBuilder::channelLayout`
        | and the `-ac` on the encode both read this value), so the audio reaches
        | the encoder bit-for-bit unmodified.
        |
        | The rate stays pinned at 48 kHz on purpose: it is half of the 96 kHz
        | family and is what the client asks for. Do not "save" it by dropping to
        | the 16 kHz Whisper uses — that is `transcription.*` above, a different
        | stage, and it is mono 32 kbps because the model only ever needs to
        | recognise words, not to sound good.
        */
        'audio_sample_rate' => (int) env('VIDEO_EDIT_AUDIO_SAMPLE_RATE', 48000),   // D3
        'audio_channels' => (int) env('VIDEO_EDIT_AUDIO_CHANNELS', 2),             // D3
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
