<?php

declare(strict_types=1);

namespace Modules\CvJobStudio\Domain\Services;

/**
 * Strips benefits, EEO and legal boilerplate from posting text by an
 * ES/EN/PT heading lexicon before it reaches an LLM purpose (T-155, NFR-16).
 * Requirement lines are always kept.
 */
final readonly class JdTextTrimmer
{
    /** @var list<string> */
    private const array BOILERPLATE_HEADINGS = [
        'benefits', 'beneficios', 'benefícios', 'what we offer', 'lo que ofrecemos',
        'equal opportunity', 'igualdad de oportunidades', 'igualdade de oportunidades',
        'eeo', 'legal notice', 'aviso legal', 'privacy notice', 'aviso de privacidad',
        'about the company', 'sobre nosotros', 'sobre nós', 'perks',
    ];

    #[\NoDiscard]
    public function trim(string $text, int $maxChars): string
    {
        $lines = preg_split('/\r\n|\r|\n/', $text) ?: [];
        $kept = [];
        $skipping = false;

        foreach ($lines as $line) {
            $normalized = mb_strtolower(trim($line));

            if ($normalized !== '' && $this->isBoilerplateHeading($normalized)) {
                $skipping = true;

                continue;
            }

            if ($normalized === '') {
                $skipping = false;

                continue;
            }

            if (! $skipping) {
                $kept[] = $line;
            }
        }

        $collapsed = (string) preg_replace('/[ \t]+/', ' ', implode("\n", $kept));

        return mb_substr(trim($collapsed), 0, $maxChars);
    }

    private function isBoilerplateHeading(string $line): bool
    {
        return in_array($line, self::BOILERPLATE_HEADINGS, true)
            || in_array(rtrim($line, ':'), self::BOILERPLATE_HEADINGS, true);
    }
}
