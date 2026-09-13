<?php

declare(strict_types=1);

namespace Modules\CourseScripts\Domain\Services;

/**
 * FR-39b: contact data in a practice artifact looks real but is invented. A
 * denylist cannot prove a domain is unregistered; it catches the obvious
 * failures — real mail providers and big brands — and any link to a site.
 */
final readonly class ContactDataValidator
{
    /**
     * @param  list<string>  $domainDenylist
     */
    public function __construct(
        private array $domainDenylist = [],
    ) {}

    /**
     * @param  list<array<string, mixed>>  $blocks
     * @return list<string>
     */
    #[\NoDiscard]
    public function violations(string $fileName, array $blocks): array
    {
        $text = $this->text($blocks);
        $violations = [];

        if (preg_match_all('/[A-Z0-9._%+-]+@([A-Z0-9.-]+\.[A-Z]{2,})/iu', $text, $matches) > 0) {
            foreach (array_unique(array_map(strtolower(...), $matches[1])) as $domain) {
                foreach ($this->domainDenylist as $denied) {
                    $denied = strtolower($denied);

                    if ($domain === $denied || str_ends_with($domain, '.'.$denied)) {
                        $violations[] = sprintf('"%s" uses a real email domain (%s); invent one from the fictional organisation name.', $fileName, $domain);
                    }
                }
            }
        }

        if (preg_match('/\bhttps?:\/\/\S+/iu', $text) === 1) {
            $violations[] = sprintf('"%s" contains a web link; practice artifacts must not point to real sites.', $fileName);
        }

        return $violations;
    }

    /**
     * @param  list<array<string, mixed>>  $blocks
     */
    private function text(array $blocks): string
    {
        $parts = [];

        foreach ($blocks as $block) {
            $parts[] = (string) ($block['text'] ?? '');
            $parts = [...$parts, ...array_map(strval(...), (array) ($block['items'] ?? []))];
            $parts = [...$parts, ...array_map(strval(...), (array) ($block['table_header'] ?? []))];

            foreach ((array) ($block['table_rows'] ?? []) as $row) {
                $parts = [...$parts, ...array_map(strval(...), (array) $row)];
            }

            foreach ((array) ($block['pairs'] ?? []) as $pair) {
                $parts[] = ($pair['key'] ?? '').' '.($pair['value'] ?? '');
            }
        }

        return implode("\n", $parts);
    }
}
