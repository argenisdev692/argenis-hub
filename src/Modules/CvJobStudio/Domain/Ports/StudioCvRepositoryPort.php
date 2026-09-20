<?php

declare(strict_types=1);

namespace Modules\CvJobStudio\Domain\Ports;

use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Modules\CvJobStudio\Application\DTOs\StudioCvAuditData;
use Modules\CvJobStudio\Application\DTOs\StudioCvVersionData;
use Modules\CvJobStudio\Domain\ValueObjects\CvStructureSnapshot;
use Modules\CvJobStudio\Domain\ValueObjects\CvVersionSnapshot;

/**
 * The Studio's own CV artefacts: parsed structures (entries, bullets,
 * skills), generated versions, audits + metric answers and export records.
 * `Modules\Cvs` stays read-only behind `CvSourcePort` (GAP-A1). Every read
 * is owner-scoped (OWASP §11).
 */
interface StudioCvRepositoryPort
{
    /**
     * Stores a parse as a new structure (re-runnable — `raw_text` stays the
     * source of truth), with portfolio projects appended as `project` entries.
     *
     * @param  array{profile_facts: array<string, mixed>, entries: list<array<string, mixed>>, bullets: list<array<string, mixed>>, skills: list<array<string, mixed>>, parser_version: string}  $parsed
     * @param  list<array{title: string, description: string|null, url: string|null}>  $projects
     * @return array{uuid: string, bullets: list<array{id: int, text: string}>}
     */
    public function storeStructure(int $userId, int $cvId, string $sourceTextHash, array $parsed, array $projects): array;

    public function confirmStructure(string $uuid, int $userId): bool;

    public function structureForUser(string $uuid, int $userId): ?CvStructureSnapshot;

    /** @return array<int, string> bullet id => text */
    public function bulletsForStructure(int $structureId, int $userId): array;

    /** @param  array<string, mixed>  $attributes */
    public function createVersion(array $attributes): StudioCvVersionData;

    public function versionForUser(string $uuid, int $userId): ?CvVersionSnapshot;

    /** @return LengthAwarePaginator<int, StudioCvVersionData> */
    public function paginateVersions(int $userId, int $perPage): LengthAwarePaginator;

    /** @param  array<string, mixed>  $attributes */
    public function createAudit(array $attributes): StudioCvAuditData;

    /** @return array{id: int, cv_id: int}|null */
    public function auditRefForUser(string $uuid, int $userId): ?array;

    /**
     * @param  array<string, string>  $answers  question => answer
     * @return int answers stored
     */
    public function saveMetricAnswers(int $auditId, int $cvId, int $userId, array $answers): int;

    /**
     * @param  array<string, mixed>  $attributes
     * @return array{uuid: string, path: string, verified: bool}
     */
    public function recordExport(array $attributes): array;
}
