<?php

declare(strict_types=1);

namespace Modules\Company\Domain\ValueObjects;

use Modules\Company\Domain\Enums\LogoVariant;
use Modules\Company\Domain\Enums\SocialChannel;

/**
 * The whole company record, as the Domain sees it.
 *
 * This module has no `Domain/Entities/` folder on purpose: the company is a
 * singleton settings record with no lifecycle, no state machine and no invariant
 * spanning more than one field, so an aggregate root would be ceremony. What it
 * does need is a framework-free shape the repository port can speak in, which is
 * what this snapshot is — the same role `AuthSessionSnapshot` plays in the Auth
 * module.
 *
 * Columns outside this shape (`user_id`, `signature_path`, the timestamps) are
 * deliberately absent: the repository writes only the fields listed here, so
 * anything missing is left untouched rather than nulled.
 */
final readonly class CompanySnapshot
{
    /**
     * @param  array<string, string|null>  $logos  {@see LogoVariant} value → stored object key
     * @param  array<string, WebUrl|null>  $socials  {@see SocialChannel} value → profile URL
     */
    public function __construct(
        public string $uuid,
        public string $companyName,
        public ?string $legalName,
        public ?string $description,
        public ?WebUrl $website,
        public ?string $email,
        public ?string $phone,
        public ?string $addressLine1,
        public ?string $addressLine2,
        public ?PostalCode $postalCode,
        public ?string $city,
        public ?string $state,
        public ?string $country,
        public ?string $countryCode,
        public ?GeoCoordinates $coordinates,
        public array $logos,
        public array $socials,
        public ?string $nifNipc,
        public ?string $nie,
        public ?string $bankBeneficiary,
        public ?string $bankIban,
        public ?string $bankBic,
        public ?string $bankName,
        public ?string $invoiceNotes,
        public ?string $createdAt,
        public ?string $updatedAt,
    ) {}

    /**
     * Stored object key for one brand mark, or `null` when it was never uploaded.
     */
    public function logo(LogoVariant $variant): ?string
    {
        return $this->logos[$variant->value] ?? null;
    }

    /**
     * Profile URL for one channel, or `null` when the company is not on it.
     */
    public function social(SocialChannel $channel): ?WebUrl
    {
        return $this->socials[$channel->value] ?? null;
    }

    /**
     * The single generic wither.
     *
     * `clone($this, [...])` can only lift the write-once guard on readonly
     * properties from inside the declaring class, which is why every partial
     * update funnels through here instead of each caller cloning directly.
     *
     * @param  array<string, mixed>  $changes  property name → replacement value
     */
    #[\NoDiscard('with() returns a new snapshot; the original is unchanged.')]
    public function with(array $changes): self
    {
        return clone ($this, $changes);
    }

    /**
     * Replace the brand marks, keeping any variant passed as `null`.
     *
     * Uploading a new dark logo must not wipe the white one, so this merges over
     * the current map rather than replacing it.
     *
     * @param  array<string, string>  $keys  {@see LogoVariant} value → new object key
     */
    #[\NoDiscard('withLogos() returns a new snapshot; the original is unchanged.')]
    public function withLogos(array $keys): self
    {
        return $this->with(['logos' => [...$this->logos, ...$keys]]);
    }
}
