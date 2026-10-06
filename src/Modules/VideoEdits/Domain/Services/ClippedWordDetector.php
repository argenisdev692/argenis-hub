<?php

declare(strict_types=1);

namespace Modules\VideoEdits\Domain\Services;

use Modules\VideoEdits\Domain\Enums\AiRecommendationKind;
use Modules\VideoEdits\Domain\ValueObjects\AiCutProposal;
use Modules\VideoEdits\Domain\ValueObjects\AiRecommendation;
use Modules\VideoEdits\Domain\ValueObjects\Transcript;
use Modules\VideoEdits\Domain\ValueObjects\TranscriptWord;
use Modules\VideoEdits\Infrastructure\Media\FilterGraphBuilder;

/**
 * Counts the words the microphone never got, from Whisper's own truncation
 * signal — the "umbral de captación muy bajo" the round-2 supervisor review
 * raised twice.
 *
 * **What can and cannot be fixed in an edit.** The output side is already handled
 * by the loudness stage in {@see FilterGraphBuilder}:
 * bringing the programme to −16 LUFS is what makes the quiet words audible. What
 * no timeline can do is invent the syllables that never reached the preamp, so
 * this reports them instead. The remedy is a desk change — gain up, or closer —
 * and the user can only make that if they know which words failed.
 *
 * **Why Whisper's hyphens are the signal.** When the input stops mid-syllable the
 * transcriber writes the fragment with a trailing hyphen ("co-", "infor-"). That
 * is the one piece of evidence in the pipeline that separates "the speaker said
 * this badly" from "the recording never contained this" — see
 * {@see TranscriptWord::isTruncated()}, which this shares with
 * {@see CutEdgeTrimmer}.
 *
 * **Why words inside a proposed cut are excluded.** {@see CutEdgeTrimmer} already
 * absorbs exactly these fragments at a seam, so counting them here would report a
 * problem the pipeline has already solved. Passing the proposals in keeps the
 * count honest: what is left are the clipped words that will actually be audible
 * in the delivered video.
 *
 * **Where the fix goes.** Not into the timeline — a fragment that was never
 * recorded cannot be reconstructed — but into the capture chain described in
 * `guia_audio_obs.md`: the mic's own gain knob first, then the OBS compressor's
 * threshold and output gain. That advice is deliberately specific to that setup,
 * because "turn up the microphone" is no use to someone whose signal is already
 * at a healthy -18 to -12 dB and still losing syllables.
 *
 * Deterministic like every other measurement in this module — the model is not
 * asked, so the numbers are exact and the finding cannot hallucinate.
 */
final readonly class ClippedWordDetector
{
    public function __construct(
        private int $minClippedWords = 3,
        private int $maxReportedWords = 20,
    ) {}

    /**
     * The clipped words that will survive the proposed cuts.
     *
     * @param  list<AiCutProposal>  $proposals  cut ranges, in word indices, to exclude
     * @return list<TranscriptWord>
     */
    #[\NoDiscard]
    public function clippedWords(Transcript $transcript, array $proposals = []): array
    {
        $ranges = self::proposalRanges($transcript, $proposals);
        $clipped = [];

        foreach ($transcript->words as $word) {
            if ($word->isTruncated() && ! self::isInsideAny($word, $ranges)) {
                $clipped[] = $word;
            }
        }

        return $clipped;
    }

    /**
     * The finding as a report row, or null when the count is below the floor.
     *
     * The floor exists because a single truncated fragment is what any normal
     * false start produces; a handful across the recording is a pattern, and a
     * pattern is what changes someone's microphone setup.
     *
     * @param  list<AiCutProposal>  $proposals
     */
    #[\NoDiscard]
    public function recommendation(Transcript $transcript, array $proposals = []): ?AiRecommendation
    {
        $clipped = $this->clippedWords($transcript, $proposals);

        if (count($clipped) < $this->minClippedWords) {
            return null;
        }

        $shown = array_slice($clipped, 0, $this->maxReportedWords);
        $fragments = array_map(
            static fn (TranscriptWord $word): string => sprintf('"%s" (%s)', trim($word->text, ' "'), self::timecode($word->startMs)),
            $shown,
        );
        $first = $shown[0]->startMs;
        $last = array_last($shown)->startMs;

        return str_starts_with(mb_strtolower($transcript->language ?? 'es'), 'es')
            ? new AiRecommendation(
                kind: AiRecommendationKind::UnclearWord,
                title: sprintf('%d palabras cortadas por el micrófono', count($clipped)),
                detail: sprintf(
                    'Whisper las oyó terminar a mitad de sílaba, lo que apunta a que el micrófono no llegó a captar el resto: %s%s. Sube primero la ganancia de la perilla del Fifine y, si con eso no basta, baja el umbral del Compressor de OBS (-18 dB) o sube su Output Gain; la normalización de salida rescata las palabras débiles, pero no puede reconstruir sílabas que no se grabaron.',
                    implode(', ', $fragments),
                    count($clipped) > count($shown) ? sprintf(' (y %d más)', count($clipped) - count($shown)) : '',
                ),
                startMs: $first,
                endMs: $last,
            )
            : new AiRecommendation(
                kind: AiRecommendationKind::UnclearWord,
                title: sprintf('%d words clipped by the microphone', count($clipped)),
                detail: sprintf(
                    'Whisper heard them stop mid-syllable, which points at the microphone never capturing the rest: %s%s. Raise the mic gain knob first and, failing that, lower the OBS Compressor threshold (-18 dB) or raise its output gain: output normalisation rescues quiet words but cannot rebuild syllables that were never recorded.',
                    implode(', ', $fragments),
                    count($clipped) > count($shown) ? sprintf(' (and %d more)', count($clipped) - count($shown)) : '',
                ),
                startMs: $first,
                endMs: $last,
            );
    }

    /**
     * @param  list<AiCutProposal>  $proposals
     * @return list<array{0: int, 1: int}> millisecond bounds, resolved from the proposals' word indices
     */
    private static function proposalRanges(Transcript $transcript, array $proposals): array
    {
        $wordCount = count($transcript->words);
        $ranges = [];

        foreach ($proposals as $proposal) {
            // The adapter already dropped anything out of bounds, but this is
            // read from the port's output and must not assume it.
            $start = max(0, min($wordCount - 1, $proposal->startWordIndex));
            $end = max(0, min($wordCount - 1, $proposal->endWordIndex));

            if ($end < $start) {
                continue;
            }

            $ranges[] = [
                $transcript->words[$start]->startMs,
                $transcript->words[$end]->endMs,
            ];
        }

        return $ranges;
    }

    /**
     * @param  list<array{0: int, 1: int}>  $ranges  millisecond bounds
     */
    private static function isInsideAny(TranscriptWord $word, array $ranges): bool
    {
        foreach ($ranges as [$startMs, $endMs]) {
            if ($word->startMs <= $endMs && $word->endMs >= $startMs) {
                return true;
            }
        }

        return false;
    }

    private static function timecode(int $milliseconds): string
    {
        $seconds = intdiv($milliseconds, 1_000);

        return sprintf('%d:%02d', intdiv($seconds, 60), $seconds % 60);
    }
}
