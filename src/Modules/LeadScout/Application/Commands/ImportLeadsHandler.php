<?php

declare(strict_types=1);

namespace Modules\LeadScout\Application\Commands;

use Carbon\CarbonImmutable;
use Illuminate\Contracts\Config\Repository as Config;
use Modules\LeadScout\Application\DTOs\CreateManualLeadData;
use Modules\LeadScout\Domain\Entities\Company;
use Modules\LeadScout\Domain\Enums\MessageVariant;
use Modules\LeadScout\Domain\Enums\OutreachChannel;
use Modules\LeadScout\Domain\Enums\OutreachStage;
use Modules\LeadScout\Domain\Exceptions\SuppressedException;
use Modules\LeadScout\Domain\Ports\CompanyRepositoryPort;
use Modules\LeadScout\Domain\Ports\OutreachRepositoryPort;
use Modules\LeadScout\Domain\ValueObjects\CanonicalDomain;

/**
 * Imports the operator ICP list (spec FR-20, T029): rows of
 * `nombre,url,nota` with optional pre-system contact columns
 * (`sent_at,channel,variant,stage`) so the Phase-0 manual outreach counts
 * toward the decision-rule sample. Each row goes through the manual-lead
 * use-case (suppression wins, known domains merge). Invalid rows are
 * reported, never aborting the run.
 */
final readonly class ImportLeadsHandler
{
    public function __construct(
        private CreateManualLeadHandler $createLead,
        private CompanyRepositoryPort $companies,
        private OutreachRepositoryPort $outreaches,
        private Config $config,
    ) {}

    /**
     * @param  iterable<int, list<string|null>>  $rows  CSV rows, first row numbered 1
     * @return array{created: int, merged: int, invalid: int, suppressed: int, outreaches: int, warnings: list<string>}
     */
    public function handle(iterable $rows, int $operatorId, string $originRef): array
    {
        $report = ['created' => 0, 'merged' => 0, 'invalid' => 0, 'suppressed' => 0, 'outreaches' => 0, 'warnings' => []];
        $number = 0;

        foreach ($rows as $row) {
            $this->importRow($row, ++$number, $operatorId, $originRef, $report);
        }

        return $report;
    }

    /**
     * @param  list<string|null>  $row
     * @param  array{created: int, merged: int, invalid: int, suppressed: int, outreaches: int, warnings: list<string>}  $report
     */
    private function importRow(array $row, int $number, int $operatorId, string $originRef, array &$report): void
    {
        if (count($row) < 2 || trim((string) $row[0]) === '' || trim((string) $row[1]) === '') {
            self::skip($report, 'invalid', "Row {$number}: expected nombre,url[,nota,sent_at,channel,variant,stage] — skipped.");

            return;
        }

        [$name, $url, $note, $sentAt, $channel, $variant, $stage] = [...$row, null, null, null, null, null];

        try {
            $data = CreateManualLeadData::from([
                'name' => trim((string) $name),
                'url' => trim((string) $url),
                'note' => self::blankToNull($note),
            ]);
        } catch (\Exception $e) {
            self::skip($report, 'invalid', "Row {$number}: invalid payload ({$e->getMessage()}) — skipped.");

            return;
        }

        try {
            $existed = $this->knownCompany($data->url) !== null;
            $company = $this->createLead->handle($data, $operatorId);
            $report[$existed ? 'merged' : 'created']++;
        } catch (SuppressedException) {
            self::skip($report, 'suppressed', "Row {$number}: suppressed company — skipped.");

            return;
        } catch (\Exception $e) {
            self::skip($report, 'invalid', "Row {$number}: {$e->getMessage()} — skipped.");

            return;
        }

        if (self::blankToNull($sentAt) !== null) {
            $this->recordPriorContact($company, $number, $operatorId, $originRef, (string) $sentAt, $channel, $variant, $stage, $report);
        }
    }

    /**
     * @param  array{created: int, merged: int, invalid: int, suppressed: int, outreaches: int, warnings: list<string>}  $report
     */
    private function recordPriorContact(
        Company $company,
        int $number,
        int $operatorId,
        string $originRef,
        string $sentAt,
        ?string $channel,
        ?string $variant,
        ?string $stage,
        array &$report,
    ): void {
        try {
            $sent = CarbonImmutable::parse(trim($sentAt));
        } catch (\Exception) {
            self::skip($report, 'invalid', "Row {$number}: invalid sent_at — contact not recorded.");

            return;
        }

        $this->outreaches->recordImportedContact(
            companyId: $company->id,
            operatorId: $operatorId,
            medium: OutreachChannel::tryFrom(mb_strtolower(trim($channel ?? 'email'))) ?? OutreachChannel::Email,
            variant: self::blankToNull($variant) === null ? null : MessageVariant::tryFrom(mb_strtolower(trim((string) $variant))),
            senderKind: (string) $this->config->get('lead-scout.sender_kind_default', 'personal_mailbox'),
            sentAt: $sent,
            reachedStage: OutreachStage::tryFrom(mb_strtolower(trim($stage ?? 'sent'))) ?? OutreachStage::Sent,
            notes: "Imported pre-system contact ({$originRef}).",
        );

        $report['outreaches']++;
    }

    private function knownCompany(string $url): ?Company
    {
        try {
            return $this->companies->byDomain(CanonicalDomain::fromUrl($url)->value);
        } catch (\InvalidArgumentException) {
            return null;
        }
    }

    /**
     * @param  array{created: int, merged: int, invalid: int, suppressed: int, outreaches: int, warnings: list<string>}  $report
     */
    private static function skip(array &$report, string $counter, string $warning): void
    {
        $report[$counter]++;
        $report['warnings'][] = $warning;
    }

    private static function blankToNull(?string $value): ?string
    {
        return $value === null || trim($value) === '' ? null : trim($value);
    }
}
