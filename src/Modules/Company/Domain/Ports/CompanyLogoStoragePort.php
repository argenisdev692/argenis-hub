<?php

declare(strict_types=1);

namespace Modules\Company\Domain\Ports;

use Modules\Company\Domain\Enums\LogoVariant;
use SplFileInfo;

/**
 * Brand-mark storage contract.
 *
 * Kept separate from {@see CompanyRepositoryPort} because the two have different
 * reasons to change and different failure modes — the database row survives an
 * object-store outage, and a logo upload that half-succeeds must not leave the
 * record pointing at nothing.
 *
 * Logos are the one class of upload in this project stored with public
 * visibility: they are rendered by mail clients and by external landing pages,
 * neither of which can follow an expiring signed URL.
 */
interface CompanyLogoStoragePort
{
    /**
     * Re-encode and store an uploaded brand mark, returning its object key.
     */
    #[\NoDiscard('store() returns the object key that must be persisted.')]
    public function store(LogoVariant $variant, SplFileInfo $file): string;

    /**
     * Remove a superseded object. Never throws — a leaked object is preferable
     * to a failed update.
     */
    public function delete(?string $key): void;

    /**
     * Resolve stored keys to absolute public URLs, falling back to the bundled
     * asset for any variant that was never uploaded.
     *
     * @param  array<string, string|null>  $keys  {@see LogoVariant} value → object key
     * @return array<string, string> {@see LogoVariant} value → absolute URL
     */
    #[\NoDiscard('urls() returns the resolved URL map.')]
    public function urls(array $keys): array;
}
