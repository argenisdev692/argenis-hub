<?php

declare(strict_types=1);

namespace Modules\Company\Domain\Ports;

use Modules\Company\Domain\Exceptions\CompanyNotConfigured;
use Modules\Company\Domain\ValueObjects\CompanySnapshot;

/**
 * Persistence contract for the singleton company record.
 *
 * There is no `create()`: the row is provisioned by `CompanySeeder`. The record
 * can be soft-deleted and restored (a reversible admin action guarded by
 * DELETE_COMPANY_DATA / RESTORE_COMPANY_DATA) — while it is trashed every
 * branding consumer falls back to the app defaults (the shared `CompanyProfile`
 * kernel service).
 */
interface CompanyRepositoryPort
{
    /**
     * The one active company record.
     *
     * @throws CompanyNotConfigured when the installation was never seeded, or
     *                              the record is currently soft-deleted
     */
    public function current(): CompanySnapshot;

    /**
     * Persist the snapshot and return it as re-read from storage.
     *
     * @throws CompanyNotConfigured when the row disappeared between read and write
     */
    #[\NoDiscard('save() returns the persisted snapshot, including refreshed timestamps.')]
    public function save(CompanySnapshot $company): CompanySnapshot;

    /**
     * Soft-delete the active company record.
     *
     * @throws CompanyNotConfigured when there is no active record to delete
     */
    public function delete(): void;

    /**
     * Restore the most recently soft-deleted company record and return it.
     *
     * @throws CompanyNotConfigured when there is no soft-deleted record to restore
     */
    #[\NoDiscard('restore() returns the restored snapshot.')]
    public function restore(): CompanySnapshot;

    /**
     * Whether a soft-deleted company record exists and can be restored.
     */
    public function trashedExists(): bool;
}
