<?php

declare(strict_types=1);

namespace Modules\VideoEdits\Domain\Ports;

use Modules\VideoEdits\Domain\Exceptions\TranscriptionFailedException;
use Modules\VideoEdits\Domain\ValueObjects\Transcript;

/**
 * Speech-to-text, provider-neutral (EX-5).
 *
 * Swapping OpenAI Whisper for Deepgram, Groq or a self-hosted whisper.cpp must
 * not change the detectors, validation, render or reporting — which is why this
 * contract speaks in {@see Transcript} and milliseconds rather than in any
 * provider's response shape.
 *
 * Implementations MUST return word-level timings: segment-level output cannot
 * drive a 300 ms filler cut.
 */
interface TranscriptionPort
{
    /**
     * @param  string  $audioPath  local, already-extracted audio track
     * @param  string|null  $language  ISO 639-1 hint; null lets the provider detect it
     *
     * @throws TranscriptionFailedException
     */
    public function transcribe(string $audioPath, ?string $language = null): Transcript;
}
