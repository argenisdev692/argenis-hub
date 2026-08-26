<?php

declare(strict_types=1);

namespace Modules\Company\Domain\Ports;

use Modules\Company\Domain\Exceptions\CompanyNotConfigured;
use Modules\Company\Domain\ValueObjects\CompanySnapshot;

/**
 * Persistence contract for the singleton company record.
 *
 * There is no `create()` and no `delete()`: the row is provisioned by
 * `CompanySeeder` and lives for the life of the installation. Modelling those
 * operations would only invite an endpoint that should not exist.
 */
interface CompanyRepositoryPort
{
    /**
     * The one company record.
     *
     * @throws CompanyNotConfigured when the installation was never seeded
     */
    public function current(): CompanySnapshot;

    /**
     * Persist the snapshot and return it as re-read from storage.
     *
     * @throws CompanyNotConfigured when the row disappeared between read and write
     */
    #[\NoDiscard('save() returns the persisted snapshot, including refreshed timestamps.')]
    public function save(CompanySnapshot $company): CompanySnapshot;
}
