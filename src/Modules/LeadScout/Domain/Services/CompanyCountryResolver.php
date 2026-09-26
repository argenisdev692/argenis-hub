<?php

declare(strict_types=1);

namespace Modules\LeadScout\Domain\Services;

/**
 * Which country a company is actually in, from evidence on its own site —
 * never from the search that happened to surface it (spec FR-37, FR-44).
 *
 * The bug this exists to fix: discovery wrote `country` from the planned
 * query, so a Portuguese search labelled an India company `PT`, a Hungarian
 * `.hu` company with an `Kft.` legal form `PT`, and a `.pl` company `ES`.
 * Because `timezone_overlap_hours` was derived from that same value, the
 * `geo_contract` and `remote` subscores were computed from a country nobody
 * had checked.
 *
 * Signals are ranked by how hard they are to fake by accident, and every one of
 * them comes from the FR-38 allowlist (`legal_name`, `legal_form`, `tax_id`,
 * `registry_info`, `city`, `site_languages`) — none of them requires storing a
 * new personal field. A phone number would be the easiest country signal there
 * is, and it is deliberately NOT used: it identifies a natural person, so
 * reading it belongs to a lawful-basis decision, not to a country resolver.
 *
 *  1. `jsonld`   — `addressCountry` in JSON-LD Organization. A legal person
 *                   states where it is registered; the strongest public signal.
 *  2. `tax_id`   — the EU VAT number prefix IS the country (`PT123…`, `DE123…`,
 *                   `ESX123…`). Self-declared, structured, and nearly impossible
 *                   to get wrong for a company that has one.
 *  3. `legal_form` — `Kft.` is Hungarian, `S.L.` Spanish, `Lda` Portuguese,
 *                   `sp. z o.o.` Polish. The suffix is a jurisdiction claim.
 *  4. `city`     — a city in the allowlist locates the company exactly.
 *  5. `language` — the site's own languages. Weakest, and only when it is
 *                   unambiguous: `es` alone can be a Spanish firm serving Latin
 *                   America, so this never wins against anything above it.
 *  6. `cctld`    — `.pt`, `.pl`, `.hu`. Corroboration ONLY. A company can hold
 *                   a ccTLD it does not operate from, and a neutral TLD
 *                   (`.com`, `.dev`) says nothing at all, so this never
 *                   overrides a stronger signal and never invents one.
 *
 * With no evidence the answer is `null` (unknown), never a guess. An unknown
 * country must lower confidence rather than be filled in — a wrong country
 * corrupts the score silently, while an unknown one is visible and reviewable.
 */
final readonly class CompanyCountryResolver
{
    public const string SOURCE_JSONLD = 'jsonld';

    public const string SOURCE_TAX_ID = 'tax_id';

    public const string SOURCE_LEGAL_FORM = 'legal_form';

    public const string SOURCE_CITY = 'city';

    public const string SOURCE_LANGUAGE = 'language';

    public const string SOURCE_CCTLD = 'cctld';

    public const string SOURCE_NONE = 'unknown';

    /**
     * EU VAT number prefix → ISO2. Self-declared by the company and
     * structured, so it is the second-hardest country signal after a stated
     * registered address. `registry_info` (a chamber-of-commerce number) uses
     * the same prefixes where it does, so it is read through the same path.
     *
     * @var array<string, string>
     */
    private const array VAT_PREFIXES = [
        'AT' => 'ATU', 'BE' => 'BE', 'BG' => 'BG', 'CY' => 'CY', 'CZ' => 'CZ',
        'DE' => 'DE', 'DK' => 'DK', 'EE' => 'EE', 'ES' => 'ES', 'FI' => 'FI',
        'FR' => 'FR', 'GR' => 'EL', 'HR' => 'HR', 'HU' => 'HU', 'IE' => 'IE',
        'IT' => 'IT', 'LT' => 'LT', 'LU' => 'LU', 'LV' => 'LV', 'MT' => 'MT',
        'NL' => 'NL', 'PL' => 'PL', 'PT' => 'PT', 'RO' => 'RO', 'SE' => 'SE',
        'SI' => 'SI', 'SK' => 'SK', 'GB' => 'GB',
    ];

    /**
     * Cities the FR-38 extractor already records, mapped to their country.
     * A city in the allowlist is an exact location, which is why it outranks
     * the site's language.
     *
     * @var array<string, string>
     */
    private const array CITIES = [
        'lisboa' => 'PT', 'porto' => 'PT', 'braga' => 'PT', 'coimbra' => 'PT',
        'madrid' => 'ES', 'barcelona' => 'ES', 'valencia' => 'ES', 'sevilla' => 'ES',
        'málaga' => 'ES', 'málaga' => 'ES', 'bilbao' => 'ES', 'zaragoza' => 'ES',
        'berlin' => 'DE', 'münchen' => 'DE', 'hamburg' => 'DE', 'münchen' => 'DE',
        'paris' => 'FR', 'lyon' => 'FR', 'marseille' => 'FR', 'toulouse' => 'FR',
        'roma' => 'IT', 'milano' => 'IT', 'napoli' => 'IT', 'torino' => 'IT',
        'amsterdam' => 'NL', 'rotterdam' => 'NL', 'utrecht' => 'NL',
        'brussels' => 'BE', 'antwerpen' => 'BE', 'gent' => 'BE',
        'dublin' => 'IE', 'cork' => 'IE',
        'london' => 'GB', 'manchester' => 'GB', 'edinburgh' => 'GB', 'bristol' => 'GB',
        'warszawa' => 'PL', 'kraków' => 'PL', 'krakow' => 'PL', 'wrocław' => 'PL', 'gdańsk' => 'PL',
        'praha' => 'CZ', 'brno' => 'CZ',
        'budapest' => 'HU', 'wien' => 'AT', 'vienna' => 'AT',
        'zürich' => 'CH', 'zurich' => 'CH', 'genève' => 'CH',
        'stockholm' => 'SE', 'göteborg' => 'SE', 'oslo' => 'NO', 'københavn' => 'DK',
        'helsinki' => 'FI', 'tallinn' => 'EE', 'riga' => 'LV', 'vilnius' => 'LT',
        'varšava' => 'PL', 'bucureşti' => 'RO', 'bucuresti' => 'RO', 'athina' => 'GR',
    ];

    /**
     * ISO 3166-1 alpha-2 → E.164 calling code, for the phone signal.
     *
     * Only countries the discovery waves actually target; an unmapped code is
     * not a country claim, it is a number we cannot place.
     *
     * @var array<string, string>
     */
    private const array CALLING_CODES = [
        'PT' => '351', 'ES' => '34', 'FR' => '33', 'DE' => '49', 'IT' => '39',
        'NL' => '31', 'BE' => '32', 'IE' => '353', 'GB' => '44', 'AT' => '43',
        'CH' => '41', 'DK' => '45', 'SE' => '46', 'NO' => '47', 'FI' => '358',
        'PL' => '48', 'CZ' => '420', 'SK' => '421', 'HU' => '36', 'RO' => '40',
        'BG' => '359', 'GR' => '30', 'HR' => '385', 'SI' => '386', 'EE' => '372',
        'LV' => '371', 'LT' => '370', 'UA' => '380', 'US' => '1', 'CA' => '1',
        'IN' => '91', 'AR' => '54', 'UY' => '598', 'CL' => '56', 'CO' => '57',
        'MX' => '52', 'BR' => '55', 'AU' => '61', 'NZ' => '64', 'ZA' => '27',
    ];

    /**
     * ccTLD → ISO2. Consulted only as corroboration (see the class docblock).
     *
     * @var array<string, string>
     */
    private const array CCTLD = [
        'pt' => 'PT', 'es' => 'ES', 'fr' => 'FR', 'de' => 'DE', 'it' => 'IT',
        'nl' => 'NL', 'be' => 'BE', 'ie' => 'IE', 'uk' => 'GB', 'at' => 'AT',
        'ch' => 'CH', 'dk' => 'DK', 'se' => 'SE', 'no' => 'NO', 'fi' => 'FI',
        'pl' => 'PL', 'cz' => 'CZ', 'sk' => 'SK', 'hu' => 'HU', 'ro' => 'RO',
        'bg' => 'BG', 'gr' => 'GR', 'hr' => 'HR', 'si' => 'SI', 'ee' => 'EE',
        'lv' => 'LV', 'lt' => 'LT', 'ua' => 'UA', 'us' => 'US', 'ca' => 'CA',
        'in' => 'IN', 'ar' => 'AR', 'uy' => 'UY', 'cl' => 'CL', 'co' => 'CO',
        'mx' => 'MX', 'br' => 'BR', 'au' => 'AU', 'nz' => 'NZ', 'za' => 'ZA',
    ];

    /**
     * Locale subtags that identify a country on their own. `en` is deliberately
     * absent: it is not a country, and mapping it to GB is how "English site"
     * turns into "British company".
     *
     * @var array<string, string>
     */
    private const array LOCALE_REGIONS = [
        'pt' => 'PT', 'pt-pt' => 'PT', 'pt-br' => 'BR',
        'es' => 'ES', 'es-es' => 'ES', 'es-mx' => 'MX', 'es-ar' => 'AR',
        'es-cl' => 'CL', 'es-co' => 'CO', 'es-uy' => 'UY',
        'fr' => 'FR', 'fr-be' => 'BE', 'fr-ch' => 'CH',
        'de' => 'DE', 'de-at' => 'AT', 'de-ch' => 'CH',
        'it' => 'IT', 'nl' => 'NL', 'nl-be' => 'BE',
        'pl' => 'PL', 'cs' => 'CZ', 'sk' => 'SK', 'hu' => 'HU',
        'ro' => 'RO', 'bg' => 'BG', 'el' => 'GR', 'hr' => 'HR',
        'sl' => 'SI', 'et' => 'EE', 'lv' => 'LV', 'lt' => 'LT',
        'da' => 'DK', 'sv' => 'SE', 'nb' => 'NO', 'fi' => 'FI',
    ];

    /**
     * Legal-form suffixes that identify a jurisdiction UNIQUELY, written
     * DOTLESS ({@see fromLegalForm()} strips the punctuation first, because
     * "S.L." and "sp. z o.o." are single abbreviations, not two tokens). Matched
     * longest-first so "unipessoal lda" cannot be read as "lda".
     *
     * The catalog is deliberately short. A suffix that is merely *widespread* in
     * one country is not a country claim, and including it would manufacture
     * exactly the confident wrong answer this class exists to prevent:
     *
     *  - `Ltd`    → GB, IE, AU, NZ, SG, ZA, HK **and India** (the "Pvt Ltd"
     *                that gave this catalog its first false positive)
     *  - `GmbH`   → DE *and* AT
     *  - `S.r.l.` → IT *and* RO
     *  - `S.p.A.` → IT, ES *and* PL
     *  - `B.V.`   → NL *and* BE
     *  - `A/S`    → DK, NO, SE
     *
     * Those are omitted rather than guessed. Losing a resolvable company is
     * recoverable and visible; a wrong country silently corrupts the
     * `geo_contract` and `remote` subscores, which is how a 150-person India
     * company ended up in a Portuguese shortlist.
     *
     * @var array<string, string>
     */
    private const array LEGAL_FORM_HINTS = [
        // Hungary: no other country uses these.
        'unipessoal kft' => 'HU', 'zrt' => 'HU', 'nyrt' => 'HU', 'kft' => 'HU',
        // Poland: Slovakia and the Czech Republic use s.r.o., never sp. z o.o.
        'sp z oo' => 'PL', 'sp z o o' => 'PL',
        // Portugal: "Unipessoal Lda" exists in no other jurisdiction, and a bare
        // "Lda" in an EU context is Portuguese (Brazil writes LTDA/EIRELI, and
        // the token boundary keeps "ltda" from matching here anyway).
        'unipessoal lda' => 'PT', 'lda' => 'PT',
        // Nordics: single-country forms.
        'oyj' => 'FI', 'aps' => 'DK',
        // France: SARL/SAS are French; Romania and Luxembourg use different forms.
        'sarl' => 'FR', 'eurl' => 'FR', 'sas u' => 'FR', 'sas' => 'FR',
        // Spain: the SL family in an EU context. Latin America uses S.A. de C.V.
        // and S.A.S. differently, so those do not land here.
        'slu' => 'ES', 'sll' => 'ES', 'scp' => 'ES', 'sau' => 'ES', 's l' => 'ES', 'sl' => 'ES',
    ];

    /**
     * @param  array<string, string|null>  $signals  `jsonld_country`, `tax_id`,
     *                                               `legal_form`, `city`,
     *                                               `site_languages` as raw text
     * @return array{country: ?string, source: string, evidence_url: ?string}
     */
    public function resolve(array $signals, string $domain, ?string $evidenceUrl = null): array
    {
        $ranked = [
            self::SOURCE_JSONLD => $this->fromJsonLd($signals['jsonld_country'] ?? null),
            self::SOURCE_TAX_ID => $this->fromTaxId($signals['tax_id'] ?? null),
            self::SOURCE_LEGAL_FORM => $this->fromLegalForm($signals['legal_form'] ?? null),
            self::SOURCE_CITY => $this->fromCity($signals['city'] ?? null),
            self::SOURCE_LANGUAGE => $this->fromLanguages($signals['site_languages'] ?? null),
        ];

        foreach ($ranked as $source => $country) {
            if ($country !== null) {
                return [
                    'country' => $country,
                    'source' => $source,
                    // Only the two strongest signals carry a citable excerpt; the
                    // weaker ones are a reading of an allowlisted field.
                    'evidence_url' => in_array($source, [self::SOURCE_JSONLD, self::SOURCE_TAX_ID], true)
                        ? $evidenceUrl
                        : null,
                ];
            }
        }

        $ccTld = self::CCTLD[mb_strtolower($this->tld($domain))] ?? null;

        if ($ccTld !== null) {
            return ['country' => $ccTld, 'source' => self::SOURCE_CCTLD, 'evidence_url' => null];
        }

        return ['country' => null, 'source' => self::SOURCE_NONE, 'evidence_url' => null];
    }

    /**
     * An EU VAT number is `XX` + digits, except Spain (`ESX…`/`ESZ…`) and
     * Greece (`EL`). Matched on the prefix only, so a number is never
     * reconstructed — the digits are not stored here, only their country.
     */
    private function fromTaxId(?string $raw): ?string
    {
        if ($raw === null) {
            return null;
        }

        if (preg_match('/\b([A-Z]{2})\s?[0-9A-Z]{2,12}\b/u', mb_strtoupper(trim($raw)), $m) !== 1) {
            return null;
        }

        $prefix = $m[1];

        return self::VAT_PREFIXES[$prefix] ?? null;
    }

    private function fromCity(?string $raw): ?string
    {
        if ($raw === null) {
            return null;
        }

        $city = mb_strtolower(trim($raw));

        if ($city === '') {
            return null;
        }

        return self::foldedCities()[$city]
            ?? self::foldedCities()[self::foldAccents($city)]
            ?? null;
    }

    /**
     * The city catalog normalized to NFD with the combining marks removed, so
     * «Málaga», «Málaga» and «Malaga» all fold to `malaga`. A page may write
     * any of the three and a city is an exact location, so the fold has to
     * handle the precomposed form too — `\p{Mn}` alone does not, because in
     * `á` there is no separate mark to strip.
     *
     * @return array<string, string>
     */
    private static function foldedCities(): array
    {
        static $folded = null;

        if ($folded !== null) {
            return $folded;
        }

        $folded = [];

        foreach (self::CITIES as $city => $iso) {
            $folded[self::foldAccents($city)] = $iso;
        }

        return $folded;
    }

    /**
     * Decompose to NFD, drop the combining marks, recompose. `Normalizer` is
     * in ext-intl, which this application requires; a hand-rolled
     * accent table would miss one and silently stop matching a city.
     */
    private static function foldAccents(string $value): string
    {
        $normalized = \Normalizer::normalize($value, \Normalizer::FORM_D);

        if ($normalized === false) {
            return $value;
        }

        return (string) preg_replace('/\p{Mn}/u', '', $normalized);
    }

    /**
     * Only a single, unambiguous language resolves. Two or more languages mean
     * the site serves several markets, and "es + en" does not mean Spain.
     *
     * @param  string|null  $raw  a JSON-encoded list or a comma-separated string
     */
    private function fromLanguages(?string $raw): ?string
    {
        if ($raw === null) {
            return null;
        }

        $decoded = json_decode($raw, true);
        $languages = is_array($decoded)
            ? array_values($decoded)
            : array_map(trim(...), explode(',', $raw));

        $codes = [];

        foreach ($languages as $language) {
            $code = mb_strtolower(trim((string) $language));

            if ($code !== '') {
                $codes[$code] = true;
            }
        }

        // More than one distinct language means the site serves more than one
        // market, and a language the catalog cannot place ("en") is the purest
        // example: «es + en» is a Spanish-language site that also serves English
        // readers, which is most of the web. Resolving that to ES is exactly the
        // guess this class exists to avoid, so any second language blocks it.
        if (count($codes) !== 1) {
            return null;
        }

        $code = (string) array_key_first($codes);

        return self::LOCALE_REGIONS[$code] ?? null;
    }

    /**
     * `addressCountry` is either `"PT"` or `{"@type":"Country","name":"Portugal"}`.
     */
    private function fromJsonLd(?string $raw): ?string
    {
        if ($raw === null || trim($raw) === '') {
            return null;
        }

        $value = trim($raw);

        if (str_contains($value, '{')) {
            $decoded = json_decode($value, true);

            if (! is_array($decoded)) {
                return null;
            }

            $value = (string) ($decoded['name'] ?? $decoded['identifier'] ?? '');
        }

        return $this->byName($value);
    }

    private function fromLegalForm(?string $raw): ?string
    {
        if ($raw === null) {
            return null;
        }

        // "S.L." and "sp. z o.o." are one abbreviation each: the punctuation is
        // decoration, so it becomes whitespace before any token is matched.
        $value = mb_strtolower(trim($raw));
        $value = (string) preg_replace('/[.,()]/u', ' ', $value);
        $value = trim((string) preg_replace('/\s+/u', ' ', $value));

        if ($value === '') {
            return null;
        }

        $hints = self::LEGAL_FORM_HINTS;
        uksort($hints, static fn (string $a, string $b): int => mb_strlen($b) <=> mb_strlen($a));

        foreach ($hints as $hint => $iso) {
            // Whole-token match: a hint must not fire inside a longer word.
            if (preg_match('/(?<![a-z])'.preg_quote($hint, '/').'(?![a-z])/u', $value) === 1) {
                return $iso;
            }
        }

        return null;
    }

    /**
     * Country names as they appear in JSON-LD and legal notices.
     */
    private function byName(string $value): ?string
    {
        $normalized = mb_strtolower(trim($value));

        if (preg_match('/^[a-z]{2}$/', $normalized) === 1) {
            $iso = mb_strtoupper($normalized);

            return isset(self::CALLING_CODES[$iso]) ? $iso : null;
        }

        $names = [
            'portugal' => 'PT', 'spain' => 'ES', 'españa' => 'ES', 'espana' => 'ES',
            'france' => 'FR', 'germany' => 'DE', 'deutschland' => 'DE',
            'italy' => 'IT', 'italia' => 'IT', 'netherlands' => 'NL',
            'belgium' => 'BE', 'ireland' => 'IE', 'united kingdom' => 'GB',
            'austria' => 'AT', 'switzerland' => 'CH', 'denmark' => 'DK',
            'sweden' => 'SE', 'norway' => 'NO', 'finland' => 'FI',
            'poland' => 'PL', 'polska' => 'PL', 'czech republic' => 'CZ',
            'slovakia' => 'SK', 'hungary' => 'HU', 'magyarorszag' => 'HU',
            'romania' => 'RO', 'bulgaria' => 'BG', 'greece' => 'GR',
            'croatia' => 'HR', 'slovenia' => 'SI', 'estonia' => 'EE',
            'latvia' => 'LV', 'lithuania' => 'LT', 'ukraine' => 'UA',
            'united states' => 'US', 'united states of america' => 'US',
            'usa' => 'US', 'canada' => 'CA', 'india' => 'IN',
            'argentina' => 'AR', 'uruguay' => 'UY', 'chile' => 'CL',
            'colombia' => 'CO', 'mexico' => 'MX', 'brasil' => 'BR', 'brazil' => 'BR',
            'australia' => 'AU', 'new zealand' => 'NZ', 'south africa' => 'ZA',
        ];

        return $names[$normalized] ?? null;
    }

    private function tld(string $domain): string
    {
        $host = (string) preg_replace('/^www\./', '', mb_strtolower(trim($domain)));

        $dot = mb_strrchr($host, '.');

        // `mb_strrchr` returns the dot too; the catalog is keyed without it.
        return $dot === false ? '' : mb_ltrim($dot, '.');
    }
}
