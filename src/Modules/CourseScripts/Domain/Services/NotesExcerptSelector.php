<?php

declare(strict_types=1);

namespace Modules\CourseScripts\Domain\Services;

use Modules\CourseScripts\Domain\ValueObjects\NotesExcerpt;

/**
 * Chooses which of the author's notes a video is written from (FR-4d · D15).
 *
 * The video's own notes and any file assigned to it always come first, in
 * full up to the budget. Course notes and unassigned content files are cut
 * into paragraphs and ranked by word overlap with the video's title, topic and
 * brief — deterministic, no embeddings, no new dependency.
 */
final readonly class NotesExcerptSelector
{
    private const int MIN_WORD_LENGTH = 4;

    private const array STOPWORDS = [
        'para', 'como', 'desde', 'sobre', 'entre', 'hasta', 'cada', 'este', 'esta', 'estos', 'estas', 'pero', 'porque',
        'with', 'from', 'that', 'this', 'what', 'when', 'your', 'into', 'about', 'their', 'there',
    ];

    public function __construct(
        private int $budgetChars = 6000,
        private int $maxExcerpts = 8,
    ) {}

    /**
     * @param  list<array{id: string, name: string, text: string}>  $assignedDocuments
     * @param  list<array{id: string, name: string, text: string}>  $unassignedDocuments
     * @return list<NotesExcerpt>
     */
    #[\NoDiscard]
    public function select(
        string $focusText,
        ?string $videoNotes,
        array $assignedDocuments,
        ?string $courseNotes,
        array $unassignedDocuments,
    ): array {
        $excerpts = [];
        $remaining = $this->budgetChars;

        $take = function (string $sourceId, string $label, string $text) use (&$excerpts, &$remaining): void {
            $text = trim($text);

            if ($text === '' || $remaining <= 0 || count($excerpts) >= $this->maxExcerpts) {
                return;
            }

            $clipped = mb_substr($text, 0, $remaining);
            $excerpts[] = new NotesExcerpt($sourceId, $label, $clipped);
            $remaining -= mb_strlen($clipped);
        };

        if ($videoNotes !== null) {
            $take('video_notes', 'Apuntes del autor para este vídeo', $videoNotes);
        }

        foreach ($assignedDocuments as $document) {
            $take('document:'.$document['id'], $document['name'], $document['text']);
        }

        $focus = $this->words($focusText);
        $candidates = [];

        if ($courseNotes !== null) {
            foreach ($this->paragraphs($courseNotes) as $paragraph) {
                $candidates[] = ['id' => 'course_notes', 'label' => 'Notas generales del curso', 'text' => $paragraph];
            }
        }

        foreach ($unassignedDocuments as $document) {
            foreach ($this->paragraphs($document['text']) as $paragraph) {
                $candidates[] = ['id' => 'document:'.$document['id'], 'label' => $document['name'], 'text' => $paragraph];
            }
        }

        foreach ($candidates as $position => $candidate) {
            $candidates[$position]['score'] = count(array_intersect_key($this->words($candidate['text']), $focus));
            $candidates[$position]['position'] = $position;
        }

        $relevant = array_filter($candidates, static fn (array $candidate): bool => $candidate['score'] > 0);
        usort($relevant, static fn (array $a, array $b): int => [$b['score'], $a['position']] <=> [$a['score'], $b['position']]);

        foreach ($relevant as $candidate) {
            $take($candidate['id'], $candidate['label'], $candidate['text']);
        }

        return $excerpts;
    }

    /**
     * @return list<string>
     */
    private function paragraphs(string $text): array
    {
        $blocks = preg_split('/\n\s*\n|(?=\n#{1,6}\s)/u', $text) ?: [];

        return array_values(array_filter(array_map(trim(...), $blocks), static fn (string $block): bool => mb_strlen($block) >= 20));
    }

    /**
     * @return array<string, true>
     */
    private function words(string $text): array
    {
        $ascii = TextNormaliser::ascii(mb_strtolower($text));
        $tokens = preg_split('/[^a-z0-9]+/', $ascii) ?: [];
        $words = [];

        foreach ($tokens as $token) {
            if (mb_strlen($token) >= self::MIN_WORD_LENGTH && ! in_array($token, self::STOPWORDS, true)) {
                $words[$token] = true;
            }
        }

        return $words;
    }
}
