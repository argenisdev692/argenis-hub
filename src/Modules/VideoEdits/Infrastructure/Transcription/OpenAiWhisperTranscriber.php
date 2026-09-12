<?php

declare(strict_types=1);

namespace Modules\VideoEdits\Infrastructure\Transcription;

use Illuminate\Contracts\Config\Repository as ConfigRepository;
use Illuminate\Http\Client\Factory as HttpFactory;
use Modules\VideoEdits\Domain\Exceptions\TranscriptionFailedException;
use Modules\VideoEdits\Domain\Ports\TranscriptionPort;
use Modules\VideoEdits\Domain\ValueObjects\Transcript;
use Modules\VideoEdits\Domain\ValueObjects\TranscriptSegment;
use Modules\VideoEdits\Domain\ValueObjects\TranscriptWord;
use Psr\Log\LoggerInterface;

/**
 * OpenAI Whisper behind {@see TranscriptionPort} (V2 · US-10).
 *
 * **Why this talks HTTP instead of using the installed `laravel/ai` SDK.**
 * That SDK's `TranscriptionResponse` exposes `text`, `segments`, `language`,
 * `usage` and `meta` — and its `TranscriptionSegment` is `{id, start, end,
 * text}`. There is no word-level timing anywhere in the typed response, and
 * `Meta` carries only model/finish_reason/warnings, so the raw payload cannot
 * be recovered either (verified against the SDK docs, 0.x).
 *
 * Segment timings cannot drive this feature: a segment is 5–30 seconds and the
 * "eh" we remove is ~300 ms, so cutting on segments would delete whole
 * sentences. Whisper only returns word timings when asked for
 * `timestamp_granularities[]=word`, which needs direct control of the
 * multipart request. The SDK stays the right tool for chat and embeddings; for
 * this one endpoint it discards the field the feature is built on.
 *
 * Swapping in Groq, Deepgram or a self-hosted whisper.cpp means writing another
 * {@see TranscriptionPort} — nothing else in the module changes (EX-5).
 */
final readonly class OpenAiWhisperTranscriber implements TranscriptionPort
{
    private const string ENDPOINT = 'https://api.openai.com/v1/audio/transcriptions';

    public function __construct(
        private HttpFactory $http,
        private ConfigRepository $config,
        private LoggerInterface $logger,
    ) {}

    public function transcribe(string $audioPath, ?string $language = null): Transcript
    {
        $apiKey = (string) $this->config->get('video-edit.speech.openai.api_key');

        if ($apiKey === '') {
            throw TranscriptionFailedException::unusableResponse('no OpenAI API key is configured');
        }

        $stream = @fopen($audioPath, 'rb');

        if ($stream === false) {
            throw TranscriptionFailedException::unusableResponse('the extracted audio could not be read');
        }

        $response = $this->http
            ->withToken($apiKey)
            ->timeout((int) $this->config->get('video-edit.speech.openai.timeout_seconds', 600))
            // Whisper is rate-limited and occasionally 5xxs on long uploads;
            // three quick attempts here save a full re-run of the whole job.
            ->retry(3, 2_000, throw: false)
            ->attach('file', $stream, basename($audioPath))
            ->asMultipart()
            ->post(self::ENDPOINT, array_values(array_filter([
                ['name' => 'model', 'contents' => (string) $this->config->get('video-edit.speech.openai.model', 'whisper-1')],
                ['name' => 'response_format', 'contents' => 'verbose_json'],
                // The whole reason this adapter exists.
                ['name' => 'timestamp_granularities[]', 'contents' => 'word'],
                ['name' => 'timestamp_granularities[]', 'contents' => 'segment'],
                $language === null ? null : ['name' => 'language', 'contents' => $language],
            ])));

        if ($response->failed()) {
            // The provider echoes the request in its error body; only the status
            // is safe to surface, and even the body stays in the log (FR-20).
            $this->logger->error('video-edit.transcription_failed', [
                'status' => $response->status(),
                'body_tail' => mb_substr($response->body(), -1_000),
            ]);

            throw TranscriptionFailedException::providerRejected($response->status());
        }

        return self::toTranscript($response->json() ?? []);
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    private static function toTranscript(array $payload): Transcript
    {
        /** @var list<array<string, mixed>> $words */
        $words = is_array($payload['words'] ?? null) ? array_values($payload['words']) : [];
        /** @var list<array<string, mixed>> $segments */
        $segments = is_array($payload['segments'] ?? null) ? array_values($payload['segments']) : [];

        if ($words === []) {
            // Better to fail loudly than to silently return zero detections and
            // hand the user an "edited" video that was never edited.
            throw TranscriptionFailedException::unusableResponse('the provider returned no word timings');
        }

        return new Transcript(
            words: array_map(
                static fn (array $word): TranscriptWord => new TranscriptWord(
                    text: (string) ($word['word'] ?? ''),
                    startMs: self::toMilliseconds($word['start'] ?? 0),
                    endMs: self::toMilliseconds($word['end'] ?? 0),
                    // Whisper reports per-segment logprobs, never per-word
                    // confidence; inventing one would make the detector's
                    // confidence gate meaningless.
                    confidence: null,
                ),
                $words,
            ),
            segments: array_map(
                static fn (array $segment): TranscriptSegment => new TranscriptSegment(
                    text: trim((string) ($segment['text'] ?? '')),
                    startMs: self::toMilliseconds($segment['start'] ?? 0),
                    endMs: self::toMilliseconds($segment['end'] ?? 0),
                ),
                $segments,
            ),
            language: isset($payload['language']) ? (string) $payload['language'] : null,
        );
    }

    private static function toMilliseconds(mixed $seconds): int
    {
        return max(0, (int) round(((float) $seconds) * 1_000));
    }
}
