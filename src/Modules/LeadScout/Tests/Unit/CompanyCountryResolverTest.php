<?php

declare(strict_types=1);

use Modules\LeadScout\Domain\Services\CompanyCountryResolver;

/*
| Country resolution from evidence on the company's own site, never from the
| search that surfaced it (FR-37).
|
| A real run recorded a 150-person India company as `PT` because a Portuguese
| search had found it, a Hungarian company with a `.hu` domain and a `Kft.`
| legal form as `PT`, and a `.pl` company as `ES`. Because
| `timezone_overlap_hours` was derived from that same value, the `geo_contract`
| (weight 10) and `remote` (weight 5) subscores were computed from a country
| nobody had checked.
|
| Every signal here comes from the FR-38 allowlist. A phone number would be the
| easiest signal available and is deliberately absent: it identifies a natural
| person, so reading it is a lawful-basis decision, not a country problem.
*/

function countryOf(array $signals, string $domain, ?string $evidenceUrl = null): array
{
    return (new CompanyCountryResolver)->resolve($signals, $domain, $evidenceUrl);
}

it('reads the country a legal person states in JSON-LD (FR-37)', function (): void {
    expect(countryOf(['jsonld_country' => 'PT'], 'acme.pt')['country'])->toBe('PT')
        ->and(countryOf(['jsonld_country' => 'Portugal'], 'acme.com')['country'])->toBe('PT')
        ->and(countryOf(['jsonld_country' => '{"@type":"Country","name":"Portugal"}'], 'acme.com')['country'])->toBe('PT')
        ->and(countryOf(['jsonld_country' => 'ES'], 'soft-pro.pl')['country'])->toBe('ES')
        ->and(countryOf(['jsonld_country' => 'PT'], 'acme.pt')['source'])->toBe(CompanyCountryResolver::SOURCE_JSONLD);
});

it('reads the country from the EU VAT prefix the company publishes (FR-37)', function (): void {
    expect(countryOf(['tax_id' => 'PT123456789'], 'example.com')['country'])->toBe('PT')
        ->and(countryOf(['tax_id' => 'DE 123 456 789'], 'example.com')['country'])->toBe('DE')
        // Spain's NIF is `X`/`Z` + digits, not a plain two-letter prefix.
        ->and(countryOf(['tax_id' => 'ESX12345678'], 'example.com')['country'])->toBe('ES')
        ->and(countryOf(['tax_id' => 'PL5252441489'], 'example.com')['country'])->toBe('PL')
        ->and(countryOf(['tax_id' => 'PT123456789'], 'example.com')['source'])->toBe(CompanyCountryResolver::SOURCE_TAX_ID)
        // An unknown prefix is not a country claim.
        ->and(countryOf(['tax_id' => 'XX999'], 'example.com')['country'])->toBeNull();
});

it('reads the jurisdiction from a legal form suffix (FR-37)', function (): void {
    expect(countryOf(['legal_form' => 'Kft.'], 'chronosweb.hu')['country'])->toBe('HU')
        ->and(countryOf(['legal_form' => 'sp. z o.o.'], 'soft-pro.pl')['country'])->toBe('PL')
        ->and(countryOf(['legal_form' => 'S.L.'], 'spanish.example')['country'])->toBe('ES')
        ->and(countryOf(['legal_form' => 'Unipessoal Lda'], 'portuguese.example')['country'])->toBe('PT')
        // A hint must match a whole token and never fire inside a longer word.
        ->and(countryOf(['legal_form' => 'Krakovia'], 'example.com')['country'])->toBeNull()
        ->and(countryOf(['legal_form' => ''], 'example.com')['country'])->toBeNull();
});

it('refuses the legal forms that are shared across countries (FR-37)', function (): void {
    // "Pvt Ltd" is Indian, "Ltd" British, and both are correct at different
    // times — which is exactly why the suffix is not a country claim. The same
    // applies to every Commonwealth/Latin-American form. Guessing here is what
    // put an India company in a Portuguese shortlist.
    expect(countryOf(['legal_form' => 'Tuvoc Technologies Pvt Ltd'], 'tuvoc.com')['country'])->toBeNull()
        ->and(countryOf(['legal_form' => 'Acme Holdings Ltd'], 'acme.com')['country'])->toBeNull()
        // GmbH is German AND Austrian; S.r.l. is Italian AND Romanian; S.p.A.
        // is Italian, Spanish and Polish. All three are in the target market,
        // so mislabelling one is a real error, not a rounding error.
        ->and(countryOf(['legal_form' => 'Acme GmbH'], 'acme.com')['country'])->toBeNull()
        ->and(countryOf(['legal_form' => 'Acme S.r.l.'], 'acme.com')['country'])->toBeNull()
        ->and(countryOf(['legal_form' => 'Acme S.p.A.'], 'acme.com')['country'])->toBeNull()
        ->and(countryOf(['legal_form' => 'Acme B.V.'], 'acme.com')['country'])->toBeNull();
});

it('reads the country from a city the extractor already records (FR-37)', function (): void {
    expect(countryOf(['city' => 'Lisboa'], 'example.com')['country'])->toBe('PT')
        ->and(countryOf(['city' => 'Warszawa'], 'example.com')['country'])->toBe('PL')
        ->and(countryOf(['city' => 'Berlin'], 'example.com')['country'])->toBe('DE')
        // Diacritics-insensitive: a page may write "Malaga" for "Málaga".
        ->and(countryOf(['city' => 'Malaga'], 'example.com')['country'])->toBe('ES')
        ->and(countryOf(['city' => 'Atlantis'], 'example.com')['country'])->toBeNull();
});

it('reads a single unambiguous site language, and never a mixed one (FR-37)', function (): void {
    expect(countryOf(['site_languages' => '["pt"]'], 'example.com')['country'])->toBe('PT')
        ->and(countryOf(['site_languages' => '["es"]'], 'example.com')['country'])->toBe('ES')
        // "es + en" does not mean Spain: the site serves several markets, so
        // the language is not allowed to become a country.
        ->and(countryOf(['site_languages' => '["es","en"]'], 'example.com')['country'])->toBeNull()
        // English alone is a language, not a country.
        ->and(countryOf(['site_languages' => '["en"]'], 'example.com')['country'])->toBeNull();
});

it('uses the ccTLD only as corroboration and only for a country TLD (FR-37)', function (): void {
    $pl = countryOf([], 'soft-pro.pl');

    expect($pl['country'])->toBe('PL')
        ->and($pl['source'])->toBe(CompanyCountryResolver::SOURCE_CCTLD)
        // A neutral TLD says nothing and must not become an answer.
        ->and(countryOf([], 'tuvoc.com')['country'])->toBeNull()
        ->and(countryOf([], 'appdesign.dev')['country'])->toBeNull()
        // Every stronger signal wins over the ccTLD.
        ->and(countryOf(['jsonld_country' => 'ES'], 'soft-pro.pl')['country'])->toBe('ES')
        ->and(countryOf(['tax_id' => 'DE123456789'], 'soft-pro.pl')['country'])->toBe('DE')
        ->and(countryOf(['legal_form' => 'Kft.'], 'soft-pro.pl')['country'])->toBe('HU')
        ->and(countryOf(['city' => 'Lisboa'], 'soft-pro.pl')['country'])->toBe('PT');
});

it('answers unknown instead of guessing, so a wrong country cannot pass silently (FR-37)', function (): void {
    $resolved = countryOf([], 'alicantedevelopers.com');

    expect($resolved['country'])->toBeNull()
        ->and($resolved['source'])->toBe(CompanyCountryResolver::SOURCE_NONE)
        ->and($resolved['evidence_url'])->toBeNull();
});

it('ranks the signals jsonld, tax id, legal form, city, language, then ccTLD (FR-37)', function (): void {
    $all = [
        'jsonld_country' => 'ES',
        'tax_id' => 'PT123456789',
        'legal_form' => 'Kft.',
        'city' => 'Berlin',
        'site_languages' => '["pt"]',
    ];

    expect(countryOf($all, 'example.pl')['source'])->toBe(CompanyCountryResolver::SOURCE_JSONLD)
        ->and(countryOf(['tax_id' => 'PT123456789', 'city' => 'Berlin'], 'example.pl')['source'])->toBe(CompanyCountryResolver::SOURCE_TAX_ID)
        ->and(countryOf(['legal_form' => 'Kft.', 'city' => 'Berlin'], 'example.pl')['source'])->toBe(CompanyCountryResolver::SOURCE_LEGAL_FORM)
        ->and(countryOf(['city' => 'Berlin', 'site_languages' => '["pt"]'], 'example.pl')['source'])->toBe(CompanyCountryResolver::SOURCE_CITY)
        ->and(countryOf(['site_languages' => '["pt"]'], 'example.pl')['source'])->toBe(CompanyCountryResolver::SOURCE_LANGUAGE)
        ->and(countryOf([], 'example.pl')['source'])->toBe(CompanyCountryResolver::SOURCE_CCTLD);
});

it('cites the excerpt only for the two signals that carry one (FR-37)', function (): void {
    $url = 'https://acme.com/legal';

    expect(countryOf(['jsonld_country' => 'PT'], 'acme.com', $url)['evidence_url'])->toBe($url)
        ->and(countryOf(['tax_id' => 'PT123456789'], 'acme.com', $url)['evidence_url'])->toBe($url)
        // A city or a legal form is a reading of an allowlisted field, not a
        // verbatim excerpt, so it must not be presented as one.
        ->and(countryOf(['city' => 'Lisboa'], 'acme.com', $url)['evidence_url'])->toBeNull()
        ->and(countryOf([], 'acme.com', $url)['evidence_url'])->toBeNull();
});
