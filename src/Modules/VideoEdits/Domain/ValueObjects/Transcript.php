<?php

declare(strict_types=1);

namespace Modules\VideoEdits\Domain\ValueObjects;

/**
 * A finished transcription, in framework- and provider-free terms (EX-5).
 *
 * Serialisable both ways on purpose: a transcript is expensive enough that
 * re-editing identical sources must reuse the stored one rather than pay for it
 * twice (US-11), so it round-trips through JSON without losing word timings.
 */
final readonly class Transcript
{
    /**
     * @param  list<TranscriptWord>  $words
     * @param  list<TranscriptSegment>  $segments
     */
    public function __construct(
        public array $words,
        public array $segments = [],
        public ?string $language = null,
    ) {}

    public function isEmpty(): bool
    {
        return $this->words === [];
    }

    public function wordCount(): int
    {
        return count($this->words);
    }

    /**
     * @return array{words: list<array{text: string, start_ms: int, end_ms: int, confidence: float|null}>, segments: list<array{text: string, start_ms: int, end_ms: int}>, language: string|null}
     */
    #[\NoDiscard]
    public function toArray(): array
    {
        return [
            'words' => array_map(
                static fn (TranscriptWord $word): array => [
                    'text' => $word->text,
                    'start_ms' => $word->startMs,
                    'end_ms' => $word->endMs,
                    'confidence' => $word->confidence,
                ],
                $this->words,
            ),
            'segments' => array_map(
                static fn (TranscriptSegment $segment): array => [
                    'text' => $segment->text,
                    'start_ms' => $segment->startMs,
                    'end_ms' => $segment->endMs,
                ],
                $this->segments,
            ),
            'language' => $this->language,
        ];
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    #[\NoDiscard]
    public static function fromArray(array $payload): self
    {
        /** @var list<array<string, mixed>> $words */
        $words = is_array($payload['words'] ?? null) ? array_values($payload['words']) : [];
        /** @var list<array<string, mixed>> $segments */
        $segments = is_array($payload['segments'] ?? null) ? array_values($payload['segments']) : [];

        return new self(
            words: array_map(
                static fn (array $word): TranscriptWord => new TranscriptWord(
                    (string) ($word['text'] ?? ''),
                    (int) ($word['start_ms'] ?? 0),
                    (int) ($word['end_ms'] ?? 0),
                    isset($word['confidence']) ? (float) $word['confidence'] : null,
                ),
                $words,
            ),
            segments: array_map(
                static fn (array $segment): TranscriptSegment => new TranscriptSegment(
                    (string) ($segment['text'] ?? ''),
                    (int) ($segment['start_ms'] ?? 0),
                    (int) ($segment['end_ms'] ?? 0),
                ),
                $segments,
            ),
            language: isset($payload['language']) ? (string) $payload['language'] : null,
        );
    }
}
