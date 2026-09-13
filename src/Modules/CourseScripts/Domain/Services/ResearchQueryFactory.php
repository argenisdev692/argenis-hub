<?php

declare(strict_types=1);

namespace Modules\CourseScripts\Domain\Services;

/**
 * Builds web-research queries from the course and its videos (FR-13a/b).
 *
 * Author text is untrusted: search operators, quotes and control characters
 * are stripped so an index cannot steer the search engine (`site:`, `OR`,
 * exact-phrase tricks) or smuggle markup into a query.
 */
final readonly class ResearchQueryFactory
{
    private const int MAX_QUERY_CHARS = 200;

    /**
     * @param  list<string>  $groupTitles
     * @return list<string>
     */
    #[\NoDiscard]
    public function subjectQueries(string $courseTitle, array $groupTitles, string $language, int $max): array
    {
        $title = $this->sanitise($courseTitle);

        if ($title === '' || $max <= 0) {
            return [];
        }

        $suffixes = $language === 'en'
            ? ['guide', 'best practices', 'latest updates']
            : ['guía práctica', 'buenas prácticas', 'novedades'];

        $queries = [$title];

        foreach ($suffixes as $suffix) {
            $queries[] = $title.' '.$suffix;
        }

        foreach ($groupTitles as $groupTitle) {
            $clean = $this->sanitise($groupTitle);

            if ($clean !== '') {
                $queries[] = $title.' '.$clean;
            }
        }

        return array_slice($this->unique($queries), 0, $max);
    }

    /**
     * Fewer queries when the author's notes already carry the substance —
     * research supplements notes, it does not replace them (DEC-6).
     */
    #[\NoDiscard]
    public function videoQueries(string $courseTitle, string $videoTitle, ?string $topic, ?string $objective, bool $hasRichNotes, int $max): array
    {
        $video = $this->sanitise($videoTitle);

        if ($video === '' || $max <= 0) {
            return [];
        }

        $subject = $this->sanitise($courseTitle);
        $limit = $hasRichNotes ? min(1, $max) : $max;

        $queries = [trim($subject.' '.$video)];

        if ($topic !== null && $this->sanitise($topic) !== '') {
            $queries[] = trim($this->sanitise($topic).' '.$video);
        }

        if ($objective !== null && $this->sanitise($objective) !== '') {
            $queries[] = $this->sanitise($objective);
        }

        return array_slice($this->unique($queries), 0, $limit);
    }

    #[\NoDiscard]
    public function sanitise(string $text): string
    {
        return $text
            |> (static fn (string $value): string => preg_replace('/[\x00-\x1F\x7F]/u', ' ', $value) ?? '')
            |> (static fn (string $value): string => preg_replace('/\b[a-z]+:\S*/iu', ' ', $value) ?? '')
            |> (static fn (string $value): string => str_replace(['"', '\'', '`', '<', '>', '{', '}', '[', ']', '|', '(', ')'], ' ', $value))
            |> (static fn (string $value): string => preg_replace('/(^|\s)[-+]+(?=\S)/u', '$1', $value) ?? '')
            |> (static fn (string $value): string => preg_replace('/\b(?:OR|AND|NOT)\b/u', ' ', $value) ?? '')
            |> (static fn (string $value): string => preg_replace('/\s+/u', ' ', $value) ?? '')
            |> trim(...)
            |> (static fn (string $value): string => mb_substr($value, 0, self::MAX_QUERY_CHARS));
    }

    /**
     * @param  list<string>  $queries
     * @return list<string>
     */
    private function unique(array $queries): array
    {
        $seen = [];
        $result = [];

        foreach ($queries as $query) {
            $key = mb_strtolower($query);

            if ($query !== '' && ! isset($seen[$key])) {
                $seen[$key] = true;
                $result[] = $query;
            }
        }

        return $result;
    }
}
