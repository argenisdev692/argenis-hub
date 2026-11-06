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
        'max_sources' => 20,                                   // D2 — raised from 10
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
        // The loudness measurement decodes audio only, so it is far cheaper than
        // a render and gets its own, shorter bound than the hour a render may use.
        'measure_timeout_seconds' => (int) env('VIDEO_EDIT_MEASURE_TIMEOUT', 600),
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
        'min_activity_ratio' => 0.9,
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
        // Off by default after the content review: this ramp is applied to every
        // gap ≥ `min_gap_ms` between TRANSCRIBED words, so when the provider drops
        // a short transition ("vamos", "observemos") the gap covers that word and
        // the whole phrase comes out 15 dB down. The fix is either accurate word
        // timings or no ramp; this module cannot verify the former, so the default
        // is the latter. Enable it per installation when the transcript is known
        // to be complete.
        'enabled' => (bool) env('VIDEO_EDIT_BREATHS', false),
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
    | **Two-pass, always.** The first pass runs the same cut graph audio-only
    | with `loudnorm=print_format=json`; the second pass applies one constant
    | gain from those measurements. Single-pass dynamic mode was the source of
    | the review's "la frase entra baja y recupera el nivel": the filter decides
    | its gain while it plays, and after a pause or a cut it ramps back up. A
    | measurement pass costs one audio decode, never a second video encode.
    |
    | If a file cannot be measured (silence, unreadable audio), the loudness
    | stage is skipped. Falling back to dynamic mode would silently reintroduce
    | the pumping, which is worse than leaving the level where it was.
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
    /*
    |----------------------------------------------------------------------
    | Transcription provider
    |----------------------------------------------------------------------
    |
    | `openai` (whisper-1) or `gemini` (gemini-3.5-transcribe). Whisper stays the
    | default: it still serves, and switching a recording's transcriber changes
    | every timestamp in it, which invalidates the stored transcript cache for
    | those sources.
    |
    | **The migration is not optional.** OpenAI has deprecated whisper-1,
    | gpt-4o-transcribe and gpt-4o-transcribe-diarize for 2027-02-26, and their
    | guide states that `timestamp_granularities[]` "is only supported for
    | whisper-1" — every other transcription model they offer returns plain text.
    | This module cannot run on plain text: cuts are addressed by word index and
    | resolved to milliseconds.
    |
    | Gemini is the successor that returns word-level offsets AND keeps the
    | fillers. Its `verbatim` mode is documented as preserving "raw filler words,
    | repetitions, pauses, and false starts", which is exactly what Whisper
    | normalises away — and the reason this module's own recordings kept a spoken
    | "pausa" in the delivered audio is that Whisper dropped the word from the
    | transcript in some takes. `smart` mode does the opposite of what is needed
    | (it strips disfluencies) and cannot be combined with timestamps anyway.
    |
    | The key is GEMINI_API_KEY, already used by the AI analyzer — no new secret.
    */
    'provider' => env('VIDEO_EDIT_TRANSCRIPTION_PROVIDER', 'openai'),

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

        /*
        |----------------------------------------------------------------------
        | Gemini transcription
        |----------------------------------------------------------------------
        |
        | Sits beside `openai` rather than at the top level on purpose: both are
        | the same concern — credentials, model and timeout for one transcriber —
        | and every reader already addressed them as `video-edit.speech.<provider>`.
        | The block used to live at `video-edit.gemini.*` while GeminiTranscriber
        | and its test both read `video-edit.speech.gemini.*`, so the key came
        | back null, the adapter threw "no Gemini API key is configured" on every
        | call, and the test passed anyway because it set the same wrong key. The
        | only way that provider ever ran was if something else supplied it.
        */
        'gemini' => [
            'api_key' => env('GEMINI_API_KEY'),
            'model' => env('VIDEO_EDIT_GEMINI_TRANSCRIBE_MODEL', 'gemini-3.5-transcribe'),
            'timeout_seconds' => (int) env('VIDEO_EDIT_GEMINI_TRANSCRIBE_TIMEOUT', 1_200),
            // Gemini caps audio at 30 minutes once word timestamps are enabled,
            // while this module accepts 90. Longer recordings are sliced into
            // chunks and the word offsets rebased onto the full timeline. Keep
            // this under 30.
            'chunk_seconds' => (int) env('VIDEO_EDIT_GEMINI_TRANSCRIBE_CHUNK_SECONDS', 1_500),
        ],

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
            //
            // "pausa" is here for the same reason as "eh". A speaker who flags a
            // mistake out loud is the convention this module's `pause_marker`
            // reason exists to serve, and Whisper treats an unprompted
            // interjection as noise to be normalised away — so without the cue
            // here the markers can be dropped or rewritten in the transcript,
            // and once they are gone neither the deterministic detector nor the
            // AI analyzer has anything left to find. Style conditioning is the
            // only lever available: it is what makes Whisper imitate the prompt's
            // register rather than tidy it.
            'prompts' => [
                'es' => 'Eh, mmm... bueno, este, o sea, vamos a ver. Ehh, ah, pues, digamos que, mmm, sí. Okay. Okey. Muy bien. Pausa. Pausa aquí. Pausa acá. Otra vez.',
                'en' => 'Um, uh... so, like, you know, I mean, uh, let\'s see. Hmm, well, basically. Okay. Very good. Pause. Pause there. Take that again.',
                'default' => 'Um, uh, eh, mmm... hmm, ah. Okay. Pause. Pause there.',
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
            //
            // "okay" and "okey" were the gap that made AI edit look broken: the
            // speaker heard them removed from every sentence and none of them
            // went anywhere. They could not be caught by the model either — the
            // agent is explicitly forbidden from proposing filler cuts, because
            // the word timings here are exact and its are not. So the only layer
            // that can remove them is this one, and they were simply absent.
            //
            // The residual risk is real and worth stating: "okay" is a word
            // someone can be SAYING ("escribí okay en el campo"), and this
            // dictionary cannot tell the two apart. It removes every occurrence.
            // That is the same trade every entry here makes, and it is why the
            // list stays short.
            'filler_words' => [
                'bueno', 'entonces', 'digamos', 'basicamente', 'literalmente',
                'like', 'basically', 'literally', 'actually', 'so',
                'okay', 'okey',
            ],
            // Multi-word padding: "sea" alone is ordinary Spanish, "o sea" is not.
            //
            // "muy bien" and "muy claro" were here and were removed after the
            // content review: in this register they are transitions the speaker
            // uses to move the lesson forward ("muy bien, ahora..."), not padding.
            // A dictionary cannot tell a transition from a tic, and this module
            // prefers a missed filler over a deleted sentence — the same trade the
            // rest of this list makes.
            'filler_phrases' => [
                'o sea', 'es decir', 'you know', 'i mean', 'kind of', 'sort of',
            ],
            /*
            |----------------------------------------------------------------------
            | Spoken pause markers
            |----------------------------------------------------------------------
            |
            | A speaker who says "PAUSA" out loud is flagging a mistake, and that
            | marker is the one editorial error in a recording that is bounded
            | exactly — unlike rambling, which has no boundary and stays advice.
            |
            | It used to be reachable only through the AI analyzer, which is the
            | wrong layer: it is a literal token match, and asking a Flash-tier
            | model to spot a word inside a 2,000-word indexed list is asking it
            | to do a regex. A `CutReason` existed with no `SpeechCategory` behind
            | it, so nothing deterministic could ever produce one.
            |
            | Matched as whole normalised words only, and kept to markers that are
            | interjections in EVERY position. "para", "otra", "repetir" and
            | "repite" were all candidates and all four are out: "para" opens real
            | sentences, "otra" is an ordinary adjective, and "repetir el comando"
            | is content the viewer needs. A retake flagged without the literal
            | word "pausa" is the AI's `retake` judgement to make — which is exactly
            | the division of labour this whole change restores.
            */
            'pause_phrases' => [
                'pausa', 'pausa aca', 'pausa aqui', 'pausa ahi', 'otra vez',
            ],
            /*
            |----------------------------------------------------------------------
            | Vocal elongation ("eeee", "y deee", "para queee")
            |----------------------------------------------------------------------
            |
            | A literal dictionary cannot cover these: Whisper spells the same
            | hesitation differently every time — "eee", "eeee", "eeeee" — so any
            | hand-written entry ("esteee" sat in `filler_sounds` until this
            | landed) is right for one recording and wrong for the next.
            |
            | These are patterns, matched against the normalised word, so the count
            | of repeats does not have to be known in advance. Every one is
            | deliberately narrow enough to exclude a real word:
            |
            |  - a bare run of one letter, 3+ times, which no Spanish or English
            |    word is;
            |  - a suffix where one vowel repeats 3+ times, which excludes "see",
            |    "bee", "coffee" and "three" at two repeats while catching
            |    "deee" and "queee" at three.
            |
            | The 3-repeat floor is the whole safety rule. Lowering it to 2 would
            | start deleting English diphthong-final words on a recording that
            | switches language, and the module's stated preference is a missed
            | filler over a wrongly removed real word.
            |
            | Both patterns run against `TranscriptWord::normalized()`, which has
            | already lower-cased and accent-folded the token, so the vowel class is
            | written bare. The first uses a backreference on purpose: a plain
            | character class would also match "ana" (a, n, a), which is a real
            | Spanish word.
            */
            'elongation_patterns' => [
                // A bare hesitation run: eee, aaa, mmm. 3+ of the SAME letter.
                '/^([aeiou]|m|n|r|j|s|l|h)\1{2,}$/',
                // A real word whose last vowel is held: deee, queee, veeee.
                '/([aeiou])\1{2,}$/',
            ],
            // Doubling these is emphasis, not a stumble.
            'repetition_allow_list' => [
                'no', 'si', 'muy', 'ya', 'very', 'no-no',
            ],
        ],
    ],

    /*
    |----------------------------------------------------------------------
    | Failed takes before a spoken pause marker
    |----------------------------------------------------------------------
    |
    | OBSERVACIONES round 2, item 2: cutting "pausa acá" and nothing else
    | leaves the flubbed sentence in place, so the join reads as
    | [wrong phrase] → [jump] → [right phrase] — the "oración que deja hueco"
    | the speaker kept hearing in the delivered file.
    |
    | {see \Modules\VideoEdits\Domain\Services\FailedTakeDetector} works out
    | where the failed attempt ended by walking BACK from the marker and
    | stopping at real evidence rather than at a guess: a word Whisper wrote
    | with sentence punctuation, a pause long enough to hear, or the look-back
    | ceiling. Every one of those stops makes the proposal SHORTER, which is the
    | safe direction to be wrong in.
    |
    | Nothing here is applied automatically. The proposals are stored on the
    | existing cut review and the edit parks in `awaiting_review`, because
    | deleting a take the owner still wanted is not recoverable from the
    | rendered file.
    */
    'failed_take' => [
        'enabled' => (bool) env('VIDEO_EDIT_FAILED_TAKE', true),
        // A silence this long before the marker means the speaker had finished
        // a thought — the take cannot reach back across it.
        'boundary_gap_ms' => (int) env('VIDEO_EDIT_FAILED_TAKE_GAP_MS', 400),
        // Hard ceiling on the look-back. A failed take is 2-5 seconds of
        // speech; anything longer than this is the previous idea, not this one.
        'max_lookback_ms' => (int) env('VIDEO_EDIT_FAILED_TAKE_LOOKBACK_MS', 8_000),
        // Below this there is no take worth proposing — a stray "y" is not a
        // sentence the speaker restarted.
        'min_take_ms' => (int) env('VIDEO_EDIT_FAILED_TAKE_MIN_MS', 400),
        // Transcript words shown either side of a proposal in the review.
        'context_words' => (int) env('VIDEO_EDIT_FAILED_TAKE_CONTEXT_WORDS', 8),
        // Only a take closed by sentence punctuation starts ticked. The others
        // are still shown, so nothing is discarded unseen.
        'preselect_above_confidence' => (float) env('VIDEO_EDIT_FAILED_TAKE_PRESELECT', 0.85),
    ],

    /*
    |----------------------------------------------------------------------
    | Restarts (a repeated clause) — the marker-proof signal
    |----------------------------------------------------------------------
    |
    | The `failed_take` block above works from a spoken "pausa". That signal is
    | not dependable, and this application's own recordings are the proof: in some
    | takes Whisper transcribed the marker as a word, in others it dropped it
    | entirely and the moment came back as a bare "acá". A dictionary can only
    | match what was transcribed, so on those recordings every deterministic rule
    | was blind and "pausa acá" stayed in the delivered audio.
    |
    | Sending a different prompt cannot fix it: OpenAI documents `prompt` as a
    | spelling dictionary, not a style control, so it cannot stop the
    | normalisation that drops the word.
    |
    | What does survive is the RESTART. A speaker who abandons an attempt says the
    | next thing again from the beginning, and that repetition is in the transcript
    | either way. It is also the weaker signal — speakers repeat for emphasis — so
    | `MIN_GAP_MS` is what separates the two, and everything it produces still
    | goes through the owner's review.
    |
    | A phrase said three or more times is treated as a refrain and skipped: a
    | missed restart costs one unticked box, a deleted refrain costs real speech.
    */
    'restart' => [
        'enabled' => (bool) env('VIDEO_EDIT_RESTART', true),
        // How many words must match for the repeat to be a clause and not a
        // coincidence. Four is where this application's own restarts first appear.
        'min_phrase_words' => (int) env('VIDEO_EDIT_RESTART_MIN_WORDS', 5),
        'max_phrase_words' => (int) env('VIDEO_EDIT_RESTART_MAX_WORDS', 8),
        // THE discriminator. An emphasis repeat follows immediately; a restart is
        // preceded by a hesitation a listener can hear. Everything at or above
        // this counts as a restart, so raising it trades recall for precision.
        'min_gap_ms' => (int) env('VIDEO_EDIT_RESTART_MIN_GAP_MS', 700),
        // Two copies further apart than this are a refrain or a callback, not an
        // abandoned attempt.
        'max_repeat_distance_ms' => (int) env('VIDEO_EDIT_RESTART_MAX_DISTANCE_MS', 60_000),
        // One restart is reported by every phrase length that fits inside it, at
        // several different offsets. Candidates whose second copy starts within
        // this window are the same restart and collapse into one proposal.
        'cluster_ms' => (int) env('VIDEO_EDIT_RESTART_CLUSTER_MS', 2_000),
        // How close a spoken marker has to sit to a restart to be read as the same
        // event rather than two of them. A speaker flags the mistake and restarts
        // within a beat of each other, and inside this window the two signals
        // corroborate each other instead of proposing overlapping cuts.
        'marker_proximity_ms' => (int) env('VIDEO_EDIT_RESTART_MARKER_PROXIMITY_MS', 2_000),
        // How many words may sit between the two copies of a repeated clause.
        // Measured, not guessed: a real restart in this module's own transcript has
        // fourteen, because the speaker kept talking after the repeated clause
        // before giving up and restarting. Far below that would exclude genuine
        // restarts; far above it would admit callbacks.
        'max_interlude_words' => (int) env('VIDEO_EDIT_RESTART_MAX_INTERLUDE_WORDS', 20),
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
        // Gemini 3 reasons at `high` unless told otherwise. Pinned explicitly so
        // a package default cannot silently lower the one model judgement this
        // pipeline makes — where a retake ended. `low` is a cost trade, not a
        // quality-equivalent one.
        'thinking_level' => env('VIDEO_EDIT_AI_THINKING_LEVEL', 'high'),

        // Every AI cut waits for the owner's review (OWASP LLM06). Proposals at or
        // above this confidence start ticked in the review; the rest start
        // unticked but are still shown, so nothing is discarded unseen.
        'preselect_above_confidence' => (float) env('VIDEO_EDIT_AI_MIN_CONFIDENCE', 0.8),

        // Transcript words shown either side of a proposed cut in the review.
        'review_context_words' => 8,

        /*
        |----------------------------------------------------------------------
        | Timing annotations in the transcript handed to the model
        |----------------------------------------------------------------------
        |
        | The model reads a numbered word list with no milliseconds in it, so a
        | 0.9 s hesitation and a 0.05 s one render as the same characters. That
        | is invisible to it, and it is the whole basis of three findings it is
        | asked for: a mini-pause, a speaker who abandoned a sentence and started
        | again, and where a flagged mistake began. Whisper already measured
        | every gap exactly, so it is annotated rather than estimated.
        |
        | Sparse on purpose. Only gaps at or above this threshold are written, so
        | a normal sentence stays a compact one-line word list and the cost is a
        | handful of extra tokens per recording. Reuses the `pace` threshold
        | rather than inventing a second one: below it, silence removal is about
        | to take the gap out anyway, which makes it the right cut-off for
        | "would a person hear this pause?".
        */
        'gap_annotation_ms' => (int) env('VIDEO_EDIT_AI_GAP_ANNOTATION_MS', 400),

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
        | `fade_ms` is an ANTI-CLICK ramp, not the breath. It was 40 ms and sized
        | from the silence measured at the seam, which is audible on speech: the
        | rising gain multiplies the first syllable, so the phrase enters under
        | level and recovers — exactly what the review reported. 5 ms removes the
        | waveform step and is far below the ~20 ms where a gain ramp is perceived
        | as a level change. The builder clamps it to 10 ms so a stale `.env`
        | cannot bring the ducking back.
        |
        | The breath is `air_ms`: 80 ms of room tone after the fade-out and before
        | the next fragment. `air_ms=0` disables it and keeps only the micro-fade.
        */
        'join_fade_ms' => (int) env('VIDEO_EDIT_JOIN_FADE_MS', 5),
        'join_air_ms' => (int) env('VIDEO_EDIT_JOIN_AIR_MS', 80),

        /*
        |----------------------------------------------------------------------
        | Seam air (OBSERVACIONES round 2 · "oración que deja hueco")
        |----------------------------------------------------------------------
        |
        | "Luego de remover frases malas sigue dejando hueco silencio entre
        | palabras y suenan robotizadas: 'vamos... a .... ingresar este prompt'."
        |
        | The cause is structural, not a tuning problem. A cut proposed from word
        | timings spans exactly the removed WORDS, so the silence that surrounded
        | them survives on both sides and the two kept fragments are welded
        | silence-to-silence. Only silence cuts were ever inset by a padding; a
        | filler, a stutter or any of the four AI reasons got none of it.
        |
        | So every other cut grows into the silence at its own edges and then
        | hands a fixed breath back, the way auto-editor's `--margin` does. The
        | target is CONSISTENCY, not zero: a uniform breath reads as an
        | intentional cut, whereas whatever silence happened to be lying there
        | is what reads as a glitch. `seam_breath_ms` is that constant, and it is
        | the TOTAL air at a seam split across both sides in proportion to the
        | silence each side had — so a seam with one long pause and one short one
        | does not come out lopsided.
        |
        | `seam_absorb_ms` bounds the total one seam may consume. The other bound
        | is structural: a share is always a fraction of the silence that side
        | actually had, so the expansion can never reach a neighbouring word. This
        | rule tidies air; taking words is CutEdgeTrimmer's job, and the two never
        | overlap.
        |
        | 250 ms of breath is the conservative end of the range auto-editor ships
        | (0.2 s). It is deliberately unhurried: a missed tidy costs nothing, and
        | this module would rather leave a small breath than glue two halves of
        | one intonation together.
        */
        'seam_breath_ms' => (int) env('VIDEO_EDIT_SEAM_BREATH_MS', 250),
        'seam_absorb_ms' => (int) env('VIDEO_EDIT_SEAM_ABSORB_MS', 600),

        /*
        |----------------------------------------------------------------------
        | De-clicking the seams
        |----------------------------------------------------------------------
        |
        | "Algunas palabras no sé por qué al acomodarlas se les baja el volumen."
        |
        | The 5 ms join fade already removes the waveform step, deterministically
        | and without touching the word. `adeclick` is a different operation: it
        | classifies samples as impulsive and REPLACES them with an autoregressive
        | interpolation. At FFmpeg's default threshold of 2 it also classifies
        | plosives and word attacks, and the reconstruction is heard as a short
        | static burst at the start of a phrase — the review's "chisporroteo".
        |
        | So it is OFF by default. Enable it only for a recording with genuine
        | impulse noise (a chair knock, a cable pop), with `threshold` at 10 or
        | higher and after listening to a word onset. `window_ms` is MILLISECONDS
        | (FFmpeg range 10–100); passing seconds aborts the render.
        |
        | Requires an FFmpeg build with `adeclick` (checked against FFmpeg 9.0.2).
        */
        'declick' => [
            'enabled' => (bool) env('VIDEO_EDIT_DECLICK', false),
            'window_ms' => (int) env('VIDEO_EDIT_DECLICK_WINDOW_MS', 20),
            'overlap_percent' => (int) env('VIDEO_EDIT_DECLICK_OVERLAP', 75),
            'threshold' => (int) env('VIDEO_EDIT_DECLICK_THRESHOLD', 10),
            // 'save' (overlap-save) leaves unclassified samples untouched; 'add'
            // is faster but also touches what it did not classify.
            'method' => env('VIDEO_EDIT_DECLICK_METHOD', 'save'),
        ],

        /*
        | Whisper places each word within roughly 20–60 ms, and a cut that ends
        | exactly on the transcribed word boundary can take the attack of the next
        | word with it — the "first syllable is hard to understand" half of the
        | review. This pulls every non-silence cut end back by this much, leaving a
        | short tail of the removed word in exchange for a clean onset. Manual cuts
        | are never widened or shortened.
        */
        'onset_guard_ms' => (int) env('VIDEO_EDIT_ONSET_GUARD_MS', 40),

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
