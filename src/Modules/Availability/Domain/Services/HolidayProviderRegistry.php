<?php

declare(strict_types=1);

namespace Modules\Availability\Domain\Services;

final readonly class HolidayProviderRegistry
{
    private const string DEFAULT_COUNTRY = 'PT';

    /**
     * @var array<string, HolidayProvider>
     */
    private array $providers;

    /**
     * @param  iterable<HolidayProvider>  $providers
     */
    public function __construct(iterable $providers)
    {
        $indexed = [];

        foreach ($providers as $provider) {
            $indexed[strtoupper($provider->countryCode())] = $provider;
        }

        $this->providers = $indexed;
    }

    public function forCountry(?string $countryCode): HolidayProvider
    {
        $code = strtoupper((string) ($countryCode ?? self::DEFAULT_COUNTRY));

        return $this->providers[$code]
            ?? $this->providers[self::DEFAULT_COUNTRY];
    }
}
