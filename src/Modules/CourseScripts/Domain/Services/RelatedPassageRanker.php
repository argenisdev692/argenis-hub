<?php

declare(strict_types=1);

namespace Modules\CourseScripts\Domain\Services;

/**
 * RAG-light ranking over accepted-script passages (keyword overlap).
 *
 * Pure and deterministic: no I/O, no randomness — the same focus and
 * candidates always yield the same bytes, so cacheable prompt layers built
 * from its output stay stable across the calls of one video.
 */
final readonly class RelatedPassageRanker
{
    private const array STOPWORDS = [
        'para', 'como', 'este', 'esta', 'estos', 'estas', 'entre', 'desde', 'donde',
        'cuando', 'porque', 'sobre', 'hasta', 'the', 'with', 'from', 'that', 'this',
        'have', 'more', 'video', 'curso',
    ];

    /**
     * @param  list<array{label: string, text: string}>  $candidates
     * @return list<string> labelled passages, best first
     */
    #[\NoDiscard]
    public function rank(string $focus, array $candidates, int $topK, int $maxChars): array
    {
        $terms = $this->terms($focus);

        if ($terms === [] || $candidates === [] || $topK <= 0 || $maxChars <= 0) {
            return [];
        }

        $scored = [];

        foreach ($candidates as $index => $candidate) {
            $score = $this->score($terms, (string) ($candidate['text'] ?? ''));

            if ($score > 0) {
                $scored[] = ['score' => $score, 'index' => $index, 'candidate' => $candidate];
            }
        }

        usort($scored, static fn (array $a, array $b): int => $b['score'] <=> $a['score'] ?: $a['index'] <=> $b['index']);

        $perPassage = max(200, (int) ceil($maxChars / max(1, $topK)));
        $picked = [];

        foreach (array_slice($scored, 0, $topK) as $row) {
            $text = mb_substr(trim((string) $row['candidate']['text']), 0, $perPassage);

            if ($text !== '') {
                $picked[] = trim((string) $row['candidate']['label']).': '.$text;
            }
        }

        return $picked;
    }

    /**
     * @return list<string>
     */
    #[\NoDiscard]
    public function terms(string $text): array
    {
        $words = preg_split('/[^\p{L}\p{N}]+/u', mb_strtolower($text)) ?: [];

        $terms = array_values(array_unique(array_filter(
            $words,
            static fn (string $word): bool => mb_strlen($word) >= 4 && ! in_array($word, self::STOPWORDS, true),
        )));

        return $terms;
    }

    private function score(array $terms, string $text): int
    {
        $haystack = mb_strtolower($text);
        $score = 0;

        foreach ($terms as $term) {
            if ($haystack !== '' && mb_strpos($haystack, $term) !== false) {
                $score++;
            }
        }

        return $score;
    }
}
