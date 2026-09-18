<?php

declare(strict_types=1);

namespace Modules\LeadScout\Domain\Services;

use Modules\LeadScout\Domain\ValueObjects\SkillTaxonomy;

/**
 * Deterministic ATS-markdown CV parser (spec FR-1, FR-10, A9/A10).
 *
 * Reads the operator's `## **SECTION**` CV (TECHNICAL SKILLS with
 * `**Label:** a · b · c` lines, `### **Role - Company`** jobs, PROJECTS with
 * URLs, LANGUAGES) into confirmed/potential skills, citable proof points and
 * languages. No IA, no embeddings, no stored text — same input always yields
 * the same profile. The contact line (phone, email, networks) is ignored.
 */
final readonly class CvProfileParser
{
    /**
     * @var array<string, list<string>>
     */
    private const array SECTOR_KEYWORDS = [
        'hospitality' => ['reserv', 'booking', 'hotel', 'restaurant', 'restaurante'],
        'marketing' => ['landing', 'performance', 'rendimiento', 'seo', 'lighthouse'],
        'saas' => ['saas', 'modular', 'platform', 'plataforma', 'subscription', 'onboarding'],
        'enterprise' => ['crm', 'legacy', 'erp', 'inventario', 'inventory', 'control'],
    ];

    /**
     * @var array<string, string>
     */
    private const array LANGUAGE_CODES = [
        'spanish' => 'es', 'español' => 'es', 'espanhol' => 'es',
        'portuguese' => 'pt', 'portugués' => 'pt', 'português' => 'pt',
        'english' => 'en', 'inglés' => 'en', 'inglês' => 'en',
    ];

    /**
     * @param  list<string>  $watchList
     * @return array{confirmed: list<string>, potential: list<string>, proofPoints: list<array{title: string, summary: string, technologies: list<string>, sector: ?string, result: ?string, url: ?string, order: int}>, languages: array<string, string>}
     */
    #[\NoDiscard]
    public function parse(string $rawText, array $watchList): array
    {
        $lines = preg_split('/\r\n|\r|\n/', $rawText) ?: [];
        $lines = $this->stripContactLines($lines);
        $sections = $this->splitSections($lines);

        $skillBody = implode("\n", [
            ...$sections['technical skills'],
            ...$sections['work experience'],
            ...$sections['projects'],
        ]);

        $split = SkillTaxonomy::split($skillBody, $watchList);

        return [
            'confirmed' => $split['confirmed'],
            'potential' => $split['potential'],
            'proofPoints' => $this->parseProjects($sections['projects']),
            'languages' => $this->parseLanguages($sections['languages']),
        ];
    }

    /**
     * @param  list<string>  $lines
     * @return list<string>
     */
    private function stripContactLines(array $lines): array
    {
        $head = array_slice($lines, 0, 8);
        $skip = [];

        foreach ($head as $index => $line) {
            if (preg_match('/[a-z0-9._%+-]+@[a-z0-9.-]+\.[a-z]{2,}/i', $line) === 1
                || preg_match('/\+\d[\d\s().-]{7,}/', $line) === 1
                || preg_match('/(linkedin|github|x\.com|twitter)\.com/i', $line) === 1) {
                $skip[$index] = true;
            }
        }

        return array_values(array_filter(
            $lines,
            static fn (string $line, int $index): bool => ! isset($skip[$index]),
            ARRAY_FILTER_USE_BOTH,
        ));
    }

    /**
     * @param  list<string>  $lines
     * @return array{technical skills: list<string>, work experience: list<string>, projects: list<string>, languages: list<string>}
     */
    private function splitSections(array $lines): array
    {
        $sections = ['technical skills' => [], 'work experience' => [], 'projects' => [], 'languages' => []];
        $current = null;

        foreach ($lines as $line) {
            if (preg_match('/^##(?!#)\s*\*{0,2}\s*(.+?)\s*\*{0,2}\s*$/', trim($line), $matches) === 1) {
                $name = strtolower(trim($matches[1], "* \t"));
                $current = array_key_exists($name, $sections) ? $name : null;

                continue;
            }

            if ($current !== null) {
                $sections[$current][] = $line;
            }
        }

        return $sections;
    }

    /**
     * @param  list<string>  $lines
     * @return list<array{title: string, summary: string, technologies: list<string>, sector: ?string, result: ?string, url: ?string, order: int}>
     */
    private function parseProjects(array $lines): array
    {
        $blocks = [];
        $current = null;

        foreach ($lines as $line) {
            if (preg_match('/^###\s*\*{0,2}\s*(.+?)\s*\*{0,2}\s*$/', trim($line), $matches) === 1) {
                if ($current !== null) {
                    $blocks[] = $current;
                }
                $current = ['title' => trim($matches[1], "* \t"), 'lines' => []];

                continue;
            }

            if ($current !== null && trim($line) !== '') {
                $current['lines'][] = $line;
            }
        }

        if ($current !== null) {
            $blocks[] = $current;
        }

        $points = [];

        foreach ($blocks as $order => $block) {
            $text = implode("\n", $block['lines']);
            preg_match('#https?://[^\s)>\]]+#', $text, $url);

            $summary = '';
            foreach ($block['lines'] as $line) {
                $line = trim((string) preg_replace('/\[([^\]]+)\]\([^)]+\)/', '$1', $line));
                if ($line !== '' && ! str_starts_with($line, 'http')) {
                    $summary = $line;

                    break;
                }
            }

            $result = null;
            foreach ($block['lines'] as $line) {
                if (preg_match('/\d+\s*(%|x\b)|€|\$|\d+%/', $line) === 1) {
                    $result = trim($line);

                    break;
                }
            }

            $points[] = [
                'title' => $block['title'],
                'summary' => $summary,
                'technologies' => ProofPointMatcher::detectTechnologies($text),
                'sector' => self::detectSector($text),
                'result' => $result,
                'url' => isset($url[0]) ? rtrim($url[0], '.,;)') : null,
                'order' => $order,
            ];
        }

        return $points;
    }

    #[\NoDiscard('Detected sector must be captured')]
    public static function detectSector(string $text): ?string
    {
        $lower = mb_strtolower($text);

        foreach (self::SECTOR_KEYWORDS as $sector => $keywords) {
            foreach ($keywords as $keyword) {
                if (str_contains($lower, $keyword)) {
                    return $sector;
                }
            }
        }

        return null;
    }

    /**
     * @param  list<string>  $lines
     * @return array<string, string>
     */
    private function parseLanguages(array $lines): array
    {
        $languages = [];

        foreach ($lines as $line) {
            $line = trim($line, " \t-*•");

            if (! str_contains($line, ':')) {
                continue;
            }

            [$name, $level] = array_map(trim(...), explode(':', $line, 2));
            $name = strtolower(trim($name, '* '));
            $code = self::LANGUAGE_CODES[$name] ?? null;

            if ($code !== null && $level !== '') {
                $languages[$code] = trim($level, '* ');
            }
        }

        return $languages;
    }
}
