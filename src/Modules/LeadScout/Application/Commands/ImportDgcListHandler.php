<?php

declare(strict_types=1);

namespace Modules\LeadScout\Application\Commands;

use Modules\LeadScout\Domain\Enums\SuppressionSource;
use Modules\LeadScout\Domain\ValueObjects\CanonicalDomain;
use Modules\LeadScout\Infrastructure\Persistence\Eloquent\Models\ScoutSuppressionEloquentModel;
use Spatie\SimpleExcel\SimpleExcelReader;

/**
 * DGC opposition-list import (spec FR-17, T074, Lei 41/2004 art. 13-B):
 * CSV/XLSX (≤ 10 MB) matched by NIPC, domain or normalized name. Rows land
 * as `dgc_list` suppressions with the list period; `ChannelAdvisor` blocks
 * PT email while listed and while the last import is under 3 months old.
 *
 * @return array{imported: int, skipped: int, invalid: int}
 */
final readonly class ImportDgcListHandler
{
    private const array TAX_KEYS = ['nipc', 'nif', 'tax_id', 'contribuinte', 'vat'];

    private const array DOMAIN_KEYS = ['dominio', 'dominio_empresa', 'domínio', 'domain', 'site', 'url', 'website'];

    private const array NAME_KEYS = ['nombre', 'nome', 'name', 'empresa', 'company', 'denominacion', 'denominação'];

    public function handle(string $file, ?string $period = null): array
    {
        $this->assertFile($file);

        $report = ['imported' => 0, 'skipped' => 0, 'invalid' => 0];
        $period ??= now()->format('Y').'-Q'.((int) ceil((int) now()->format('n') / 3));

        foreach (SimpleExcelReader::create($file)->headersToSnakeCase()->getRows() as $row) {
            $this->importRow(is_array($row) ? $row : [], $period, $report);
        }

        return $report;
    }

    /**
     * @param  array{imported: int, skipped: int, invalid: int}  $report
     */
    private function importRow(array $row, string $period, array &$report): void
    {
        $flat = [];
        foreach ($row as $key => $value) {
            $flat[mb_strtolower(trim((string) $key))] = is_string($value) ? trim($value) : $value;
        }

        $taxId = $this->pick($flat, self::TAX_KEYS);
        $domain = $this->canonicalizeDomain($this->pick($flat, self::DOMAIN_KEYS));
        $name = $this->normalizeName($this->pick($flat, self::NAME_KEYS));

        if ($taxId === null && $domain === null && $name === null) {
            $report['invalid']++;

            return;
        }

        $existing = $this->findExisting($taxId, $domain, $name);

        if ($existing !== null) {
            $existing->update([
                'tax_id' => $taxId ?? $existing->tax_id,
                'canonical_domain' => $domain ?? $existing->canonical_domain,
                'name' => $name ?? $existing->name,
                'source' => SuppressionSource::DgcList->value,
                'list_period' => $period,
            ]);
            $report['skipped']++;

            return;
        }

        ScoutSuppressionEloquentModel::query()->create([
            'canonical_domain' => $domain,
            'tax_id' => $taxId,
            'name' => $name,
            'source' => SuppressionSource::DgcList->value,
            'reason' => 'DGC opposition list (Lei 41/2004 art. 13-B).',
            'list_period' => $period,
        ]);
        $report['imported']++;
    }

    /**
     * @param  array<string, mixed>  $row
     */
    private function pick(array $row, array $keys): ?string
    {
        foreach ($keys as $key) {
            $value = $row[$key] ?? null;

            if (is_string($value) && trim($value) !== '') {
                return trim($value);
            }

            if (is_int($value) || is_float($value)) {
                return (string) $value;
            }
        }

        return null;
    }

    private function canonicalizeDomain(?string $value): ?string
    {
        if ($value === null || $value === '') {
            return null;
        }

        try {
            return preg_match('#^[a-z][a-z0-9+.-]*://#i', $value) === 1
                ? CanonicalDomain::fromUrl($value)->value
                : (new CanonicalDomain($value))->value;
        } catch (\InvalidArgumentException) {
            return null;
        }
    }

    private function normalizeName(?string $value): ?string
    {
        if ($value === null) {
            return null;
        }

        $normalized = (string) preg_replace('/\s+/', ' ', trim($value));

        return mb_strlen($normalized) >= 2 ? $normalized : null;
    }

    private function findExisting(?string $taxId, ?string $domain, ?string $name): ?ScoutSuppressionEloquentModel
    {
        return ScoutSuppressionEloquentModel::query()
            ->where(static function ($query) use ($taxId, $domain, $name): void {
                $query->when($domain !== null, static fn ($q) => $q->where('canonical_domain', $domain))
                    ->when($taxId !== null, static fn ($q) => $q->orWhere('tax_id', $taxId))
                    ->when($name !== null, static fn ($q) => $q->orWhere('name', $name));
            })
            ->first();
    }

    private function assertFile(string $file): void
    {
        if (! is_file($file) || ! is_readable($file)) {
            throw new \InvalidArgumentException("File not found or unreadable: {$file}.");
        }

        if ((filesize($file) ?: 0) > 10 * 1024 * 1024) {
            throw new \InvalidArgumentException('File exceeds the 10 MB limit.');
        }

        $extension = mb_strtolower((string) pathinfo($file, PATHINFO_EXTENSION));

        if (! in_array($extension, ['csv', 'xlsx'], true)) {
            throw new \InvalidArgumentException("Unsupported extension '{$extension}': use CSV or XLSX.");
        }
    }
}
