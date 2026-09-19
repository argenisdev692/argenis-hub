<?php

declare(strict_types=1);

namespace Modules\LeadScout\Application\Commands;

use Carbon\CarbonImmutable;
use Modules\LeadScout\Domain\Ports\SuppressionRepositoryPort;
use Modules\LeadScout\Domain\Ports\TabularFileReaderPort;
use Modules\LeadScout\Domain\ValueObjects\CanonicalDomain;

/**
 * DGC opposition-list import (spec FR-17, T074, Lei 41/2004 art. 13-B):
 * CSV/XLSX (≤ 10 MB) matched by NIPC, domain or normalized name. Rows land
 * as `dgc_list` suppressions with the list period; `ChannelAdvisor` blocks
 * PT email while listed and while the last import is under 3 months old.
 */
final readonly class ImportDgcListHandler
{
    private const array TAX_KEYS = ['nipc', 'nif', 'tax_id', 'contribuinte', 'vat'];

    private const array DOMAIN_KEYS = ['dominio', 'dominio_empresa', 'domínio', 'domain', 'site', 'url', 'website'];

    private const array NAME_KEYS = ['nombre', 'nome', 'name', 'empresa', 'company', 'denominacion', 'denominação'];

    private const int MAX_BYTES = 10 * 1024 * 1024;

    private const string REASON = 'DGC opposition list (Lei 41/2004 art. 13-B).';

    public function __construct(
        private TabularFileReaderPort $reader,
        private SuppressionRepositoryPort $suppressions,
    ) {}

    /**
     * @return array{imported: int, skipped: int, invalid: int}
     */
    public function handle(string $file, ?string $period = null): array
    {
        $report = ['imported' => 0, 'skipped' => 0, 'invalid' => 0];
        $now = CarbonImmutable::now();
        $period ??= $now->format('Y').'-Q'.$now->quarter;

        foreach ($this->reader->rows($file, self::MAX_BYTES) as $row) {
            $this->importRow($row, $period, $report);
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

        $created = $this->suppressions->recordDgcListing($taxId, $domain, $name, $period, self::REASON);
        $report[$created ? 'imported' : 'skipped']++;
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
}
