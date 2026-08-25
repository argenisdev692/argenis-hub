<?php

declare(strict_types=1);

namespace Shared\Support;

/**
 * ISO-3166 alpha-2 members of the Schengen Area (incl. associated states).
 * Used for invoice locale and cross-border VAT notice rules.
 */
final class EuSchengenCountries
{
    /** @var list<string> */
    private const array CODES = [
        'AT', 'BE', 'BG', 'CH', 'CZ', 'DE', 'DK', 'EE', 'ES', 'FI',
        'FR', 'GR', 'HR', 'HU', 'IS', 'IT', 'LI', 'LT', 'LU', 'LV',
        'MT', 'NL', 'NO', 'PL', 'PT', 'RO', 'SE', 'SI', 'SK',
    ];

    public static function includes(?string $countryCode): bool
    {
        if ($countryCode === null || $countryCode === '') {
            return false;
        }

        return in_array(strtoupper($countryCode), self::CODES, true);
    }
}
