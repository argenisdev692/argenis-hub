<?php

declare(strict_types=1);

namespace Modules\LeadScout\Domain\Services;

use Carbon\CarbonImmutable;

/**
 * Public company data as a legal person (spec FR-38, allowlist
 * `NORMATIVA-RGPD.md` §11): denomination, legal form, tax ID, registry,
 * city, foundation year, services, sectors, site languages, client
 * companies (as companies), public URLs — each with evidence.
 *
 * Deterministic and IA-free. NOTHING outside the allowlist is ever read:
 * there is no phone, full-address or image pattern in this class, by
 * design. A natural person (freelancer/sole trader, FR-39) is reported via
 * `is_natural_person` and the caller stores no identification at all.
 */
final readonly class PublicCompanyDataExtractor
{
    /**
     * @var array<string, list<string>>
     */
    private const array LEGAL_FORMS = [
        'company' => ['s.l.', 's.l.u.', 's.a.', 's.l', 'lda.', 'lda', 'unipessoal', 's.a', 'ltd', 'gmbh', 'sarl', 'srl', 'b.v.', 'bv'],
        'natural' => ['autónomo', 'autonomo', 'freelancer', 'empresário em nome individual', 'empresario em nome individual', 'empresario individual', 'eni', 'sole trader', 'self-employed', 'autónoma'],
    ];

    /**
     * @param  array<int, array{url: string, page_type: ?string, markdown: string}>  $pages
     * @return array{data: array<string, mixed>, evidence: array<string, array{url: string, excerpt: string, captured_at: string}>, is_natural_person: bool}
     */
    #[\NoDiscard]
    public function extract(array $pages, ?CarbonImmutable $now = null): array
    {
        $now ??= CarbonImmutable::now();
        $data = [];
        $evidence = [];

        $set = function (string $field, mixed $value, string $url, string $excerpt) use (&$data, &$evidence, $now): void {
            if (! isset($data[$field]) && $value !== null && $value !== [] && $value !== '') {
                $data[$field] = $value;
                $evidence[$field] = ['url' => $url, 'excerpt' => mb_substr($excerpt, 0, 300), 'captured_at' => $now->toDateTimeString()];
            }
        };

        $publicUrls = [];

        foreach ($pages as $page) {
            $url = $page['url'] ?? '';
            $markdown = $page['markdown'] ?? '';
            $type = $page['page_type'] ?? null;

            if (trim($markdown) === '') {
                continue;
            }

            if ($type !== null) {
                $publicUrls[$type] = $url;
            }

            $this->fromJsonLd($markdown, $url, $set);
            $this->fromLegalText($markdown, $url, $set);
            $this->fromFooter($markdown, $url, $set);
        }

        if ($publicUrls !== []) {
            $data['public_urls'] = $publicUrls;
        }

        $data['services'] = $this->services($pages);
        $data['sectors'] = $this->sectors($pages);
        $data['site_languages'] = $this->siteLanguages($pages);
        $data['client_companies'] = $this->clientCompanies($pages);

        return [
            'data' => $data,
            'evidence' => $evidence,
            'is_natural_person' => $this->isNaturalPerson($data, $pages),
        ];
    }

    /**
     * @param  callable(string, mixed, string, string): void  $set
     */
    private function fromJsonLd(string $markdown, string $url, callable $set): void
    {
        if (preg_match_all('/<script[^>]*type=["\']application\/ld\+json["\'][^>]*>(.*?)<\/script>/is', $markdown, $blocks) === 0
            && preg_match_all('/```json(.*?)```/is', $markdown, $blocks) === 0) {
            return;
        }

        foreach ($blocks[1] as $json) {
            $data = json_decode(trim($json), true);

            if (! is_array($data)) {
                continue;
            }

            $org = $this->findOrganization($data);

            if ($org === null) {
                continue;
            }

            $set('legal_name', $this->str($org['legalName'] ?? $org['name'] ?? null), $url, 'JSON-LD Organization');
            $set('tax_id', $this->str($org['taxID'] ?? $org['vatID'] ?? null), $url, 'JSON-LD Organization taxID');

            $founding = $this->str($org['foundingDate'] ?? null);

            if ($founding !== null && preg_match('/(19|20)\d\d/', $founding, $m) === 1) {
                $set('founded_year', (int) $m[0], $url, 'JSON-LD Organization foundingDate');
            }

            $address = $org['address'] ?? null;

            if (is_array($address)) {
                $set('city', $this->str($address['addressLocality'] ?? null), $url, 'JSON-LD Organization address');
            }
        }
    }

    /**
     * @param  array<string, mixed>  $node
     * @return array<string, mixed>|null
     */
    private function findOrganization(array $node): ?array
    {
        $type = $node['@type'] ?? null;
        $types = is_array($type) ? $type : [$type];

        if (in_array('Organization', $types, true)) {
            return $node;
        }

        foreach ($node as $value) {
            if (is_array($value)) {
                $list = array_is_list($value) ? $value : [$value];

                foreach ($list as $entry) {
                    if (is_array($entry)) {
                        $found = $this->findOrganization($entry);

                        if ($found !== null) {
                            return $found;
                        }
                    }
                }
            }
        }

        return null;
    }

    /**
     * @param  callable(string, mixed, string, string): void  $set
     */
    private function fromLegalText(string $markdown, string $url, callable $set): void
    {
        $isLegal = preg_match('/aviso legal|termos|privacy|privacidad|mentions l.gales/i', $url) === 1
            || preg_match('/aviso legal|raz.o social|denomina..o social|NIF|NIPC|CIF|registro mercantil|conservat.ria/i', $markdown) === 1;

        if (! $isLegal) {
            return;
        }

        foreach (explode("\n", $markdown) as $line) {
            $line = trim($line);

            if ($line === '') {
                continue;
            }

            foreach ([...self::LEGAL_FORMS['company'], ...self::LEGAL_FORMS['natural']] as $form) {
                if (preg_match('/^(.{2,120}?)\s*,?\s*'.preg_quote($form, '/').'\.?$/i', $line, $m) === 1) {
                    $set('legal_name', trim($m[1]).', '.mb_strtoupper($form), $url, $line);
                    $set('legal_form', $form, $url, $line);

                    break;
                }
            }

            if (preg_match('/\b([A-Z]\d{7}[A-Z0-9]|\d{8}[A-Z]|\d{9}|\d{8}-\d)\b/', $line, $m) === 1) {
                $set('tax_id', $m[1], $url, $line);
            }

            if (preg_match('/(registro mercantil|conservat.ria|CRC|tomo|libro|folio|matr.cula).{0,120}/i', $line, $m) === 1) {
                $set('registry_info', trim($line), $url, $line);
            }

            if (preg_match('/\b(fundada?|founded|criada?)\b.{0,40}(19|20)\d\d/i', $line) === 1
                && preg_match('/(19|20)\d\d/', $line, $m) === 1) {
                $set('founded_year', (int) $m[0], $url, $line);
            }
        }
    }

    /**
     * @param  callable(string, mixed, string, string): void  $set
     */
    private function fromFooter(string $markdown, string $url, callable $set): void
    {
        $lines = array_slice(array_filter(array_map(trim(...), explode("\n", $markdown))), -15);

        foreach ($lines as $line) {
            if (preg_match('/(Rua|Avenida|Av\.|Calle|C\/)\s+[A-ZÁÉÍÓÚÇa-z][^,]{2,60},\s*([A-ZÁÉÍÓÚÇa-z][a-záéíóúç ]{2,40})/u', $line, $m) === 1) {
                // Street lines are SKIPPED on purpose (no full addresses, FR-38).
                continue;
            }

            if (preg_match('/\b(Lisboa|Porto|Braga|Coimbra|Madrid|Barcelona|Valencia|Sevilla|Málaga|Bilbao|Berlin|Amsterdam|Paris)\b/u', $line, $m) === 1) {
                $set('city', $m[1], $url, $line);
            }
        }
    }

    /**
     * @param  array<int, array{url: string, page_type: ?string, markdown: string}>  $pages
     * @return list<string>
     */
    private function services(array $pages): array
    {
        $services = [];

        foreach ($pages as $page) {
            if (($page['page_type'] ?? null) !== 'services') {
                continue;
            }

            foreach (explode("\n", $page['markdown'] ?? '') as $line) {
                if (preg_match('/^#{1,3}\s+(.{3,80})$/', trim($line), $m) === 1
                    && preg_match('/(servicios|services|serviços)/i', $m[1]) !== 1) {
                    $services[] = trim($m[1]);
                }
            }
        }

        return array_values(array_unique(array_slice($services, 0, 20)));
    }

    /**
     * @param  array<int, array{url: string, page_type: ?string, markdown: string}>  $pages
     * @return list<string>
     */
    private function sectors(array $pages): array
    {
        $map = [
            'hospitality' => ['hotel', 'restaurante', 'reservas', 'booking'],
            'marketing' => ['marketing', 'seo', 'publicidad'],
            'saas' => ['saas', 'software'],
            'enterprise' => ['crm', 'erp', 'industria'],
            'retail' => ['retail', 'tienda', 'ecommerce', 'loja'],
            'health' => ['salud', 'saúde', 'health'],
            'finance' => ['finanzas', 'finanças', 'banca', 'finance'],
            'education' => ['educación', 'educação', 'education'],
        ];

        $joined = mb_strtolower(implode("\n", array_column($pages, 'markdown')));
        $sectors = [];

        foreach ($map as $sector => $keywords) {
            foreach ($keywords as $keyword) {
                if (str_contains($joined, $keyword)) {
                    $sectors[] = $sector;

                    break;
                }
            }
        }

        return $sectors;
    }

    /**
     * @param  array<int, array{url: string, page_type: ?string, markdown: string}>  $pages
     * @return list<string>
     */
    private function siteLanguages(array $pages): array
    {
        $joined = mb_strtolower(implode("\n", array_column($pages, 'markdown')));
        $languages = [];

        if (preg_match('/\b(nosotros|servicios|contacto|empresa)\b/', $joined) === 1) {
            $languages[] = 'es';
        }

        if (preg_match('/\b(nós|serviços|contacto|empresa|equipa)\b/', $joined) === 1) {
            $languages[] = 'pt';
        }

        if (preg_match('/\b(services|about us|contact|company|team)\b/', $joined) === 1) {
            $languages[] = 'en';
        }

        return $languages;
    }

    /**
     * @param  array<int, array{url: string, page_type: ?string, markdown: string}>  $pages
     * @return list<string>
     */
    private function clientCompanies(array $pages): array
    {
        $clients = [];

        foreach ($pages as $page) {
            foreach (explode("\n", $page['markdown'] ?? '') as $line) {
                if (preg_match('/clientes?/i', $line) !== 1) {
                    continue;
                }

                if (preg_match_all('/\b(\p{Lu}\p{Ll}+(?: \p{Lu}\p{Ll}+){0,2})\b/u', $line, $m) > 0) {
                    foreach ($m[1] as $name) {
                        if (! preg_match('/^(Nuestros|Nossos|Our|Los|As|The|Clientes|Clients)$/i', $name)) {
                            $clients[] = $name;
                        }
                    }
                }
            }
        }

        return array_values(array_unique(array_slice($clients, 0, 10)));
    }

    /**
     * @param  array<string, mixed>  $data
     * @param  array<int, array{url: string, page_type: ?string, markdown: string}>  $pages
     */
    private function isNaturalPerson(array $data, array $pages): bool
    {
        $form = mb_strtolower((string) ($data['legal_form'] ?? ''));

        foreach (self::LEGAL_FORMS['natural'] as $cue) {
            if ($form !== '' && str_contains($form, $cue)) {
                return true;
            }
        }

        $taxId = (string) ($data['tax_id'] ?? '');

        // ES DNI (8 digits + letter) and PT personal NIF (starts 1/2/3).
        if (preg_match('/^\d{8}[A-Z]$/', $taxId) === 1 || preg_match('/^[123]\d{8}$/', $taxId) === 1) {
            return true;
        }

        $joined = implode("\n", array_column($pages, 'markdown'));
        $joinedLower = mb_strtolower($joined);

        foreach (self::LEGAL_FORMS['natural'] as $cue) {
            // Whole-word match: `eni` must not fire inside `bienvenidos`.
            if (preg_match('/(?<![\p{L}\p{N}_])'.preg_quote(mb_strtolower($cue), '/').'(?![\p{L}\p{N}_])/u', $joinedLower) === 1) {
                return true;
            }
        }

        return preg_match('/\b(soy|sou)\b.{0,60}(freelance|desarrollador|desenvolvedor)/i', $joined) === 1;
    }

    private function str(mixed $value): ?string
    {
        $value = is_string($value) ? trim($value) : null;

        return $value === null || $value === '' ? null : mb_substr($value, 0, 255);
    }
}
