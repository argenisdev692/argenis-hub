<?php

declare(strict_types=1);

namespace Modules\VideoEdits\Infrastructure\Transcription;

use Illuminate\Contracts\Config\Repository as ConfigRepository;
use Illuminate\Http\Client\Factory as HttpFactory;
use Modules\VideoEdits\Domain\Exceptions\TranscriptionFailedException;
use Modules\VideoEdits\Domain\Ports\TranscriptionPort;
use Modules\VideoEdits\Domain\Ports\VideoEditorPort;
use Modules\VideoEdits\Domain\ValueObjects\Transcript;
use Modules\VideoEdits\Domain\ValueObjects\TranscriptSegment;
use Modules\VideoEdits\Domain\ValueObjects\TranscriptWord;
use Psr\Log\LoggerInterface;
use Throwable;

/**
 * {@see TranscriptionPort} on `gemini-3.5-transcribe`, and the migration
 * `whisper-1` forces.
 *
 * **Why this exists, which is not a preference.** OpenAI's deprecation notice puts
 * `whisper-1` — along with `gpt-4o-transcribe` and its diarized variant — out of
 * service on 2027-02-26, and their own guide states that `timestamp_granularities[]`
 * "is only supported for whisper-1". Every other transcription model they offer
 * returns plain text. This module cannot run on plain text: cuts are addressed by
 * word index and resolved to milliseconds, so without word timings there is nothing
 * to cut on.
 *
 * **Why Gemini specifically.** It is the one successor that returns word-level
 * offsets AND keeps the fillers. Its `verbatim` mode is documented as preserving
 * "raw filler words, repetitions, pauses, and false starts", which is precisely
 * what Whisper normalises away — and this module's own recordings show the cost of
 * that: Whisper wrote the spoken marker "pausa" in some takes and dropped it
 * entirely in others, leaving every deterministic rule blind and the word in the
 * delivered audio. `smart` mode does the opposite of what is needed here (it
 * strips disfluencies) and cannot be combined with timestamps anyway.
 *
 * **The 30-minute cap is real and is handled here, not ignored.** Gemini limits
 * audio processing to thirty minutes once word timestamps are enabled, while this
 * module accepts ninety. A longer recording is therefore sliced with FFmpeg and
 * sent in chunks, and every chunk's word offsets are rebased onto the full
 * timeline — without that, every timestamp after the first chunk would be wrong by
 * however long the earlier chunks were.
 *
 * Off by default: `whisper-1` still serves until February 2027, and switching a
 * recording's transcriber changes every timestamp in it, which invalidates the
 * stored transcript cache for those sources.
 */
final readonly class GeminiTranscriber implements TranscriptionPort
{
    private const string FILES_ENDPOINT = 'https://generativelanguage.googleapis.com/upload/v1beta/files';

    private const string INTERACTIONS_ENDPOINT = 'https://generativelanguage.googleapis.com/v1beta/interactions';

    /** Gemini documents `audio/mp3` for MP3; the extraction stage writes exactly that. */
    private const string AUDIO_MIME_TYPE = 'audio/mp3';

    /**
     * The ISO 639-1 codes this module's `speech_cleanup.language` carries, mapped to
     * the BCP-47 tags Gemini expects. An unmapped code is passed through as-is:
     * Gemini detects the language when it is omitted, and a wrong tag is worse than
     * none.
     *
     * @var array<string, string>
     */
    private const array LANGUAGE_TAGS = [
        'es' => 'es-ES',
        'en' => 'en-US',
        'pt' => 'pt-BR',
        'fr' => 'fr-FR',
        'de' => 'de-DE',
        'it' => 'it-IT',
    ];

    public function __construct(
        private HttpFactory $http,
        private ConfigRepository $config,
        private VideoEditorPort $editor,
        private LoggerInterface $logger,
    ) {}

    public function transcribe(string $audioPath, ?string $language = null): Transcript
    {
        $apiKey = (string) $this->config->get('video-edit.speech.gemini.api_key');

        if ($apiKey === '') {
            throw TranscriptionFailedException::unusableResponse('no Gemini API key is configured');
        }

        $chunkMs = max(60_000, (int) $this->config->get('video-edit.speech.gemini.chunk_seconds', 1_500) * 1000);
        $maxBytes = (int) $this->config->get('video-edit.speech.max_audio_bytes');

        $words = [];
        $texts = [];

        foreach ($this->chunkBoundaries($audioPath, $chunkMs) as $index => [$startMs, $durationMs]) {
            $chunkPath = $this->slice($audioPath, $startMs, $durationMs, $maxBytes, $index);
            $result = $this->transcribeChunk($chunkPath, $apiKey, $language);

            foreach ($result['words'] as $word) {
                $words[] = new TranscriptWord(
                    text: $word['text'],
                    // The rebasing is the whole reason a chunked transcription is
                    // not just a loop: without it every word after the first chunk
                    // is offset by the length of the ones before it.
                    startMs: (int) $word['start_ms'] + $startMs,
                    endMs: (int) $word['end_ms'] + $startMs,
                    // Gemini returns no per-word confidence. Left null rather than
                    // invented: the confidence gate in SpeechDisfluencyDetector is
                    // inert with a null score, which is correct — it has nothing to
                    // be unsure about, and a fabricated number would make the gate
                    // look exercised when it never was.
                    confidence: null,
                );
            }

            foreach ($result['texts'] as $text) {
                $texts[] = new TranscriptSegment(
                    text: $text['text'],
                    startMs: (int) $text['start_ms'] + $startMs,
                    endMs: (int) $text['end_ms'] + $startMs,
                );
            }
        }

        if ($words === []) {
            throw TranscriptionFailedException::unusableResponse('the provider returned no word timings');
        }

        return new Transcript(words: $words, segments: $texts, language: $language);
    }

    /**
     * @return list<array{0: int, 1: int}> `[startMs, durationMs]` per chunk
     */
    private function chunkBoundaries(string $audioPath, int $chunkMs): array
    {
        $probe = $this->editor->probe($audioPath);

        if ($probe->durationMs <= $chunkMs) {
            return [[0, max(1, $probe->durationMs)]];
        }

        $chunks = [];
        $total = $probe->durationMs;

        for ($start = 0; $start < $total; $start += $chunkMs) {
            $chunks[] = [$start, min($chunkMs, $total - $start)];
        }

        return $chunks;
    }

    private function slice(string $audioPath, int $startMs, int $durationMs, int $maxBytes, int $index): string
    {
        if ($startMs === 0 && $durationMs >= $this->editor->probe($audioPath)->durationMs) {
            return $audioPath;
        }

        $path = dirname($audioPath).DIRECTORY_SEPARATOR.sprintf('speech-chunk-%02d.mp3', $index);

        $this->editor->sliceAudio($audioPath, $path, $startMs, $durationMs, $maxBytes);

        return $path;
    }

    /**
     * @return array{words: list<array{text: string, start_ms: int, end_ms: int}>, texts: list<array{text: string, start_ms: int, end_ms: int}>}
     */
    private function transcribeChunk(string $chunkPath, string $apiKey, ?string $language): array
    {
        try {
            $stream = @fopen($chunkPath, 'rb');

            if ($stream === false) {
                throw TranscriptionFailedException::unusableResponse('the extracted audio could not be read');
            }

            $uri = $this->upload($apiKey, $stream, basename($chunkPath));
            $response = $this->interact($apiKey, $uri, $language);
        } catch (TranscriptionFailedException $exception) {
            throw $exception;
        } catch (Throwable $exception) {
            $this->logger->error('video-edit.gemini_transcription_failed', [
                'exception' => $exception::class,
                'message' => mb_substr($exception->getMessage(), 0, 500),
            ]);

            throw TranscriptionFailedException::providerRejected(0);
        }

        return self::toTranscript($response);
    }

    /**
     * The Files API is resumable: a `start` call that returns the upload URL, then
     * a `PUT` of the bytes to it. A single POST with the body would be simpler and
     * is not offered for this size class.
     *
     * @param  resource  $stream
     */
    private function upload(string $apiKey, $stream, string $displayName): string
    {
        $bytes = (int) (fstat($stream)['size'] ?? 0);

        $start = $this->http
            ->withHeaders(['x-goog-api-key' => $apiKey])
            ->timeout((int) $this->config->get('video-edit.speech.gemini.timeout_seconds', 1_200))
            ->post(self::FILES_ENDPOINT, ['file' => ['display_name' => $displayName]], [
                'X-Goog-Upload-Protocol' => 'resumable',
                'X-Goog-Upload-Command' => 'start',
                'X-Goog-Upload-Header-Content-Length' => (string) $bytes,
                'X-Goog-Upload-Header-Content-Type' => self::AUDIO_MIME_TYPE,
            ]);

        $location = $start->header('Location');

        if ($start->failed() || $location === '') {
            throw TranscriptionFailedException::providerRejected($start->status());
        }

        $finished = $this->http
            ->withHeaders(['x-goog-api-key' => $apiKey])
            ->timeout((int) $this->config->get('video-edit.speech.gemini.timeout_seconds', 1_200))
            ->withBody($stream, self::AUDIO_MIME_TYPE)
            ->put($location, [
                'X-Goog-Upload-Offset' => '0',
                'X-Goog-Upload-Command' => 'upload, finalize',
                'Content-Length' => (string) $bytes,
            ]);

        if ($finished->failed()) {
            throw TranscriptionFailedException::providerRejected($finished->status());
        }

        $uri = $finished->json('file.uri');

        if (! is_string($uri) || $uri === '') {
            throw TranscriptionFailedException::unusableResponse('the file upload returned no uri');
        }

        return $uri;
    }

    /**
     * @return array<string, mixed>
     */
    private function interact(string $apiKey, string $uri, ?string $language): array
    {
        $mode = ['type' => 'verbatim', 'timestamp_granularities' => ['word']];
        $transcriptionConfig = ['mode' => $mode];

        if ($language !== null && $language !== '') {
            $transcriptionConfig['language_codes'] = [self::LANGUAGE_TAGS[$language] ?? $language];
        }

        $response = $this->http
            ->withHeaders(['x-goog-api-key' => $apiKey])
            ->timeout((int) $this->config->get('video-edit.speech.gemini.timeout_seconds', 1_200))
            ->post(self::INTERACTIONS_ENDPOINT, [
                'model' => (string) $this->config->get('video-edit.speech.gemini.model', 'gemini-3.5-transcribe'),
                'input' => [
                    ['type' => 'audio', 'uri' => $uri, 'mime_type' => self::AUDIO_MIME_TYPE],
                ],
                'generation_config' => ['transcription_config' => $transcriptionConfig],
            ]);

        if ($response->failed()) {
            $this->logger->error('video-edit.gemini_interaction_failed', [
                'status' => $response->status(),
                'body_tail' => mb_substr($response->body(), -1_000),
            ]);

            throw TranscriptionFailedException::providerRejected($response->status());
        }

        $payload = $response->json();

        if (! is_array($payload)) {
            throw TranscriptionFailedException::unusableResponse('the interaction returned no json');
        }

        return $payload;
    }

    /**
     * `steps[].content[].annotations[]`, filtered to `word_info`.
     *
     * Offsets arrive as strings ("0.100s"), so they are parsed rather than cast —
     * casting "0.100s" to an int yields zero, which would collapse the whole
     * transcript onto its first instant and silently disable every cut.
     *
     * @param  array<string, mixed>  $payload
     * @return array{words: list<array{text: string, start_ms: int, end_ms: int}>, texts: list<array{text: string, start_ms: int, end_ms: int}>}
     */
    #[\NoDiscard]
    public static function toTranscript(array $payload): array
    {
        $words = [];
        $texts = [];

        foreach (self::contentBlocks($payload) as $content) {
            $text = $content['text'] ?? null;

            if (is_string($text) && trim($text) !== '') {
                $texts[] = ['text' => $text, 'start_ms' => 0, 'end_ms' => 0];
            }

            foreach (self::annotations($content) as $annotation) {
                if (($annotation['type'] ?? null) !== 'word_info') {
                    continue;
                }

                $word = (string) ($annotation['text'] ?? '');
                $startMs = self::offsetMs($annotation['start_offset'] ?? null);
                $endMs = self::offsetMs($annotation['end_offset'] ?? null);

                // An annotation with no usable timing cannot be cut on, and a word
                // whose end precedes its start would throw on construction. Dropped
                // rather than guessed.
                if ($word === '' || $startMs === null || $endMs === null || $endMs < $startMs) {
                    continue;
                }

                $words[] = ['text' => $word, 'start_ms' => $startMs, 'end_ms' => $endMs];
            }
        }

        return ['words' => $words, 'texts' => $texts];
    }

    /**
     * @param  array<string, mixed>  $payload
     * @return list<array<string, mixed>>
     */
    private static function contentBlocks(array $payload): array
    {
        $blocks = [];

        foreach ((array) ($payload['steps'] ?? []) as $step) {
            if (! is_array($step)) {
                continue;
            }

            foreach ((array) ($step['content'] ?? []) as $content) {
                if (is_array($content)) {
                    $blocks[] = $content;
                }
            }
        }

        return $blocks;
    }

    /**
     * @param  array<string, mixed>  $content
     * @return list<array<string, mixed>>
     */
    private static function annotations(array $content): array
    {
        $annotations = [];

        foreach ((array) ($content['annotations'] ?? []) as $annotation) {
            if (is_array($annotation)) {
                $annotations[] = $annotation;
            }
        }

        return $annotations;
    }

    /**
     * "0.100s", "1500ms", "1m30s" or a bare number → milliseconds.
     *
     * Parsed rather than cast, and parsed as a whole rather than by stripping one
     * suffix: an offset carries at most one unit but always several fields at once
     * ("1m30s"), so a `str_ends_with()` chain silently returns null for every
     * compound offset and the word is dropped.
     *
     * A bare number is read as SECONDS, which is what the documented "0.100s" means
     * with the `s` removed — reading it as milliseconds would shift a whole
     * transcript by a factor of a thousand.
     *
     * Anything unparseable is null, which the caller drops.
     */
    private static function offsetMs(mixed $offset): ?int
    {
        if (is_int($offset) || is_float($offset)) {
            return (int) round($offset * 1000);
        }

        if (! is_string($offset) || trim($offset) === '') {
            return null;
        }

        $value = trim($offset);

        if (str_ends_with(strtolower($value), 'ms')) {
            $milliseconds = substr($value, 0, -2);

            return preg_match('/^\d+(\.\d+)?$/', $milliseconds) === 1
                ? (int) round((float) $milliseconds)
                : null;
        }

        if (preg_match('/^(?:(\d+)h)?(?:(\d+)m)?(?:(\d+(?:\.\d+)?)s?)?$/i', $value, $matches) !== 1) {
            return null;
        }

        if (($matches[1] ?? '') === '' && ($matches[2] ?? '') === '' && ($matches[3] ?? '') === '') {
            return null;
        }

        return ((int) ($matches[1] ?? 0) * 3_600_000)
            + ((int) ($matches[2] ?? 0) * 60_000)
            + (int) round((float) ($matches[3] ?? 0) * 1000);
    }
}
