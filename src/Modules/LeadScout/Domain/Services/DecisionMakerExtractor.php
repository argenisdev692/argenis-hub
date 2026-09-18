<?php

declare(strict_types=1);

namespace Modules\LeadScout\Domain\Services;

use Carbon\CarbonImmutable;
use Modules\LeadScout\Domain\ValueObjects\RoleTaxonomy;

/**
 * Deterministic decisor extraction (spec US-11, FR-23/24, Q17): JSON-LD,
 * team blocks, about-phrases and named offer contacts — never IA, never
 * professional networks, never guessed emails, never images.
 *
 * Only allowed-category titles persist (the rest count toward
 * `team_size_observed` and their names are dropped). Emails attach only
 * from the same block on the company domain; generic mailboxes never
 * attach to a person. Opposed hashes are skipped outright.
 */
final readonly class DecisionMakerExtractor
{
    /**
     * @var list<string>
     */
    private const array GENERIC_MAILBOXES = [
        'info', 'geral', 'hola', 'contact', 'contacto', 'hello', 'hola', 'office', 'admin',
    ];

    /**
     * @param  array<int, array{url: string, markdown: string}>  $pages
     * @param  array<int, array{name: string, role: string}>  $offerContacts
     * @param  list<string>  $opposedHashes
     * @return array{candidates: list<array{name: string, title: string, category: string, email: ?string, email_kind: ?string, profile_url: ?string, evidence_url: string, excerpt: string, captured_at: string}>, teamSize: ?int}
     */
    #[\NoDiscard]
    public function extract(
        array $pages,
        string $companyDomain,
        array $offerContacts = [],
        array $opposedHashes = [],
        ?CarbonImmutable $now = null,
    ): array {
        $now ??= CarbonImmutable::now();
        $candidates = [];
        $cards = 0;

        foreach ($pages as $page) {
            $markdown = $page['markdown'] ?? '';

            if (trim($markdown) === '') {
                continue;
            }

            foreach ($this->fromJsonLd($markdown, $page['url']) as $candidate) {
                $candidates[] = $candidate;
            }

            [$blocks, $count] = $this->fromTeamBlocks($markdown, $page['url'], $companyDomain);
            $cards += $count;

            foreach ($blocks as $candidate) {
                $candidates[] = $candidate;
            }

            foreach ($this->fromAboutPhrases($markdown, $page['url']) as $candidate) {
                $candidates[] = $candidate;
            }
        }

        foreach ($offerContacts as $contact) {
            $candidate = $this->candidate(
                (string) ($contact['name'] ?? ''),
                (string) ($contact['role'] ?? ''),
                null,
                null,
                '',
                $companyDomain,
                $now,
            );

            if ($candidate !== null) {
                $candidates[] = $candidate;
            }
        }

        $deduped = [];
        $seen = [];

        foreach ($candidates as $candidate) {
            $hash = $this->personHash($candidate['name'], $companyDomain);

            if (in_array($hash, $opposedHashes, true) || isset($seen[$hash])) {
                continue;
            }

            $seen[$hash] = true;
            $candidate['captured_at'] = $now->toDateTimeString();
            $deduped[] = $candidate;
        }

        return ['candidates' => $deduped, 'teamSize' => $cards > 0 ? $cards : null];
    }

    #[\NoDiscard('Person hash must be captured')]
    public static function personHash(string $name, string $domain): string
    {
        $normalized = (string) preg_replace('/\s+/', ' ', mb_strtolower(trim($name)));

        return hash('sha256', $normalized.'|'.mb_strtolower($domain));
    }

    /**
     * @return list<array{name: string, title: string, category: string, email: ?string, email_kind: ?string, profile_url: ?string, evidence_url: string, excerpt: string, captured_at: string}>
     */
    private function fromJsonLd(string $markdown, string $url): array
    {
        $candidates = [];

        if (preg_match_all('/<script[^>]*type=["\']application\/ld\+json["\'][^>]*>(.*?)<\/script>/is', $markdown, $blocks) === 0) {
            // Markdown converters usually drop script tags; also scan fenced leftovers.
            if (preg_match_all('/```json(.*?)```/is', $markdown, $blocks) === 0) {
                return [];
            }
        }

        foreach ($blocks[1] as $json) {
            $data = json_decode(trim($json), true);

            if (! is_array($data)) {
                continue;
            }

            foreach ($this->walkJsonLdPersons($data) as $person) {
                $candidate = $this->candidate(
                    (string) ($person['name'] ?? ''),
                    (string) ($person['title'] ?? ''),
                    null,
                    $person['profile'] ?? null,
                    $url,
                    '',
                    null,
                );

                if ($candidate !== null) {
                    $candidate['evidence_url'] = $url;
                    $candidates[] = $candidate;
                }
            }
        }

        return $candidates;
    }

    /**
     * @param  array<string, mixed>  $node
     * @return list<array{name: string, title: string, profile: ?string}>
     */
    private function walkJsonLdPersons(array $node): array
    {
        $persons = [];
        $type = $node['@type'] ?? null;
        $types = is_array($type) ? $type : [$type];

        if (in_array('Person', $types, true) && isset($node['name'])) {
            $jobTitle = $node['jobTitle'] ?? null;
            $sameAs = $node['sameAs'] ?? null;

            $persons[] = [
                'name' => (string) $node['name'],
                'title' => is_string($jobTitle) ? $jobTitle : '',
                'profile' => is_string($sameAs) ? $sameAs : (is_array($sameAs) ? ((string) ($sameAs[0] ?? '')) : null),
            ];
        }

        foreach (['founder', 'founders', 'employee', 'employees', 'member', 'members'] as $key) {
            if (! isset($node[$key])) {
                continue;
            }

            $entries = is_array($node[$key]) && array_is_list($node[$key]) ? $node[$key] : [$node[$key]];

            foreach ($entries as $entry) {
                if (is_array($entry)) {
                    if (isset($entry['name']) && ! isset($entry['@type'])) {
                        $entry['@type'] = 'Person';
                    }

                    foreach ($this->walkJsonLdPersons($entry) as $person) {
                        $persons[] = $person;
                    }
                }
            }
        }

        return $persons;
    }

    /**
     * @return array{0: list<array{name: string, title: string, category: string, email: ?string, email_kind: ?string, profile_url: ?string, evidence_url: string, excerpt: string, captured_at: string}>, 1: int}
     */
    private function fromTeamBlocks(string $markdown, string $url, string $companyDomain): array
    {
        $candidates = [];
        $cards = 0;

        foreach (preg_split('/\r\n|\r|\n/', $markdown) ?: [] as $line) {
            $line = trim($line);

            if ($line === '') {
                continue;
            }

            // "**Ana Ruiz** — CTO" / "Ana Ruiz, CEO" / "| Ana Ruiz | CTO |".
            if (preg_match('/^(?:\*\*)?(\p{Lu}\p{Ll}+(?: \p{Lu}\p{Ll}+){1,3})(?:\*\*)?\s*(?:—|-|,|\|)\s*([^|<>\n]{3,80})/u', $line, $m) !== 1) {
                continue;
            }

            $name = trim($m[1]);
            $title = trim($m[2]);

            // Skip obvious non-people ("Nuestro equipo, fundado en 2020" guard).
            if (preg_match('/\b(equipo|team|nuestro|anos|años|fundad|desde|since)\b/i', $name) === 1) {
                continue;
            }

            $cards++;

            $email = $this->blockEmail($line, $companyDomain);
            $profile = $this->blockProfileUrl($line);

            $candidate = $this->candidate($name, $title, $email, $profile, $url, $companyDomain, null);

            if ($candidate !== null) {
                $candidates[] = $candidate;
            }
        }

        // "Somos 12" style counts complement card counting.
        if (preg_match_all('/(?:somos|team of|equipa de|equipo de)\s+(\d+)/i', $markdown, $m) > 0) {
            foreach ($m[1] as $number) {
                $cards = max($cards, (int) $number);
            }
        }

        return [$candidates, $cards];
    }

    /**
     * @return list<array{name: string, title: string, category: string, email: ?string, email_kind: ?string, profile_url: ?string, evidence_url: string, excerpt: string, captured_at: string}>
     */
    private function fromAboutPhrases(string $markdown, string $url): array
    {
        $candidates = [];
        $patterns = [
            '/fundad[ao]s?\s+(?:por|by)\s+(\p{Lu}\p{Ll}+(?: \p{Lu}\p{Ll}+){1,2})/u' => 'Fundador',
            '/(?:CEO|director general|diretor geral|sócio-gerente|socio-gerente|sócia-gerente|socia-gerente)\s*:\s*(\p{Lu}\p{Ll}+(?: \p{Lu}\p{Ll}+){1,2})/iu' => null,
            '/(?:dirigida|liderada|led)\s+(?:por|by)\s+(\p{Lu}\p{Ll}+(?: \p{Lu}\p{Ll}+){1,2})/u' => 'CEO',
        ];

        foreach ($patterns as $pattern => $defaultTitle) {
            if (preg_match_all($pattern, $markdown, $matches, PREG_SET_ORDER) === 0) {
                continue;
            }

            foreach ($matches as $match) {
                $title = $defaultTitle ?? trim((string) preg_replace('/\s*:\s*.*/u', '', $match[0]));
                $candidate = $this->candidate(trim($match[1]), $title, null, null, $url, '', null);

                if ($candidate !== null) {
                    $candidates[] = $candidate;
                }
            }
        }

        return $candidates;
    }

    private function blockEmail(string $line, string $companyDomain): ?string
    {
        if (preg_match_all('/[a-z0-9._%+-]+@[a-z0-9.-]+\.[a-z]{2,}/i', $line, $emails) === 0) {
            return null;
        }

        foreach ($emails[0] as $email) {
            $parts = explode('@', mb_strtolower($email));

            if (count($parts) !== 2) {
                continue;
            }

            [$local, $host] = $parts;
            $host = (string) preg_replace('/^www\./', '', $host);

            if ($host !== mb_strtolower($companyDomain)) {
                continue;
            }

            if (in_array($local, self::GENERIC_MAILBOXES, true)) {
                continue;
            }

            return $email;
        }

        return null;
    }

    private function blockProfileUrl(string $line): ?string
    {
        if (preg_match('/\((https?:\/\/(?:www\.)?(?:linkedin\.com|github\.com|facebook\.com|instagram\.com|x\.com)[^)\s]*)\)/i', $line, $m) === 1) {
            return $m[1];
        }

        return null;
    }

    /**
     * @return array{name: string, title: string, category: string, email: ?string, email_kind: ?string, profile_url: ?string, evidence_url: string, excerpt: string, captured_at: string}|null
     */
    private function candidate(
        string $name,
        string $title,
        ?string $email,
        ?string $profileUrl,
        string $url,
        string $companyDomain,
        ?CarbonImmutable $now,
    ): ?array {
        $name = trim($name);
        $title = trim($title);

        if ($name === '' || mb_strlen($name) > 80 || substr_count($name, ' ') < 1) {
            return null;
        }

        $category = RoleTaxonomy::classify($title);

        if ($category === null) {
            return null;
        }

        return [
            'name' => $name,
            'title' => $title,
            'category' => $category->value,
            'email' => $email,
            'email_kind' => $email === null ? null : 'nominative',
            'profile_url' => $profileUrl,
            'evidence_url' => $url,
            'excerpt' => mb_substr("{$name} — {$title}", 0, 300),
            'captured_at' => ($now ?? CarbonImmutable::now())->toDateTimeString(),
        ];
    }
}
