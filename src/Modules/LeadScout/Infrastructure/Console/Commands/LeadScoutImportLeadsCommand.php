<?php

declare(strict_types=1);

namespace Modules\LeadScout\Infrastructure\Console\Commands;

use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Modules\LeadScout\Application\Commands\CreateManualLeadHandler;
use Modules\LeadScout\Application\DTOs\CreateManualLeadData;
use Modules\LeadScout\Domain\Enums\OutreachChannel;
use Modules\LeadScout\Domain\Enums\OutreachKind;
use Modules\LeadScout\Domain\Enums\OutreachStage;
use Modules\LeadScout\Domain\Exceptions\SuppressedException;
use Modules\LeadScout\Domain\Ports\CompanyRepositoryPort;
use Modules\LeadScout\Domain\ValueObjects\CanonicalDomain;
use Modules\LeadScout\Infrastructure\Persistence\Eloquent\Models\ScoutCompanyEloquentModel;
use Modules\LeadScout\Infrastructure\Persistence\Eloquent\Models\ScoutOutreachEloquentModel;
use Modules\LeadScout\Infrastructure\Persistence\Eloquent\Models\ScoutOutreachStageEventEloquentModel;

/**
 * Imports the operator ICP list (spec FR-20, T029): CSV `nombre,url,nota`
 * with optional pre-system contact columns (`sent_at,channel,variant,stage`)
 * so the Phase-0 manual outreach counts toward the decision-rule sample.
 * Invalid rows are reported, never aborting the run.
 */
final class LeadScoutImportLeadsCommand extends Command
{
    protected $signature = 'lead-scout:import-leads {file : CSV path with nombre,url,nota[,sent_at,channel,variant,stage]} {--operator= : Operator user uuid (defaults to the first user)}';

    protected $description = 'Import the ICP agency list (CSV) with optional pre-system contact history';

    public function handle(CreateManualLeadHandler $create): int
    {
        $path = (string) $this->argument('file');

        if (! is_file($path) || ! is_readable($path)) {
            $this->error("File not found or unreadable: {$path}");

            return self::FAILURE;
        }

        $operator = $this->resolveOperator();

        if ($operator === null) {
            $this->error('No operator user found. Pass --operator=<user-uuid>.');

            return self::FAILURE;
        }

        $report = ['created' => 0, 'merged' => 0, 'invalid' => 0, 'suppressed' => 0, 'outreaches' => 0];
        $rows = array_map(str_getcsv(...), file($path, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES) ?: []);

        foreach ($rows as $number => $row) {
            $this->importRow($row, $number + 1, $operator->id, basename($path), $create, $report);
        }

        $this->info("Created {$report['created']}, merged {$report['merged']}, invalid {$report['invalid']}, suppressed {$report['suppressed']}, outreaches {$report['outreaches']}.");

        return self::SUCCESS;
    }

    /**
     * @param  array{created: int, merged: int, invalid: int, suppressed: int, outreaches: int}  $report
     */
    private function importRow(
        mixed $row,
        int $number,
        int $operatorId,
        string $originRef,
        CreateManualLeadHandler $create,
        array &$report,
    ): void {
        if (! is_array($row) || count($row) < 2 || trim((string) ($row[0] ?? '')) === '' || trim((string) ($row[1] ?? '')) === '') {
            $report['invalid']++;
            $this->warn("Row {$number}: expected nombre,url[,nota,sent_at,channel,variant,stage] — skipped.");

            return;
        }

        [$name, $url, $note, $sentAt, $channel, $variant, $stage] = [...$row, null, null, null, null, null];

        try {
            $data = CreateManualLeadData::from([
                'name' => trim((string) $name),
                'url' => trim((string) $url),
                'note' => $note === null || trim((string) $note) === '' ? null : trim((string) $note),
            ]);
        } catch (\Exception $e) {
            $report['invalid']++;
            $this->warn("Row {$number}: invalid payload ({$e->getMessage()}) — skipped.");

            return;
        }

        try {
            $existed = $this->findCompany($data);
            $company = $create->handle($data, $operatorId);
            $existed === null ? $report['created']++ : $report['merged']++;
        } catch (SuppressedException) {
            $report['suppressed']++;
            $this->warn("Row {$number}: suppressed company — skipped.");

            return;
        } catch (\Exception $e) {
            $report['invalid']++;
            $this->warn("Row {$number}: {$e->getMessage()} — skipped.");

            return;
        }

        if ($sentAt !== null && trim((string) $sentAt) !== '') {
            $this->recordPriorContact($company, $number, $operatorId, $originRef, $sentAt, $channel, $variant, $stage, $report);
        }
    }

    private function findCompany(CreateManualLeadData $data): ?ScoutCompanyEloquentModel
    {
        try {
            $domain = CanonicalDomain::fromUrl($data->url)->value;
        } catch (\InvalidArgumentException) {
            return null;
        }

        return app(CompanyRepositoryPort::class)->findByDomain($domain);
    }

    /**
     * @param  array{created: int, merged: int, invalid: int, suppressed: int, outreaches: int}  $report
     */
    private function recordPriorContact(
        ScoutCompanyEloquentModel $company,
        int $number,
        int $operatorId,
        string $originRef,
        mixed $sentAt,
        mixed $channel,
        mixed $variant,
        mixed $stage,
        array &$report,
    ): void {
        try {
            $sent = CarbonImmutable::parse(trim((string) $sentAt));
        } catch (\Exception) {
            $report['invalid']++;
            $this->warn("Row {$number}: invalid sent_at — contact not recorded.");

            return;
        }

        $medium = OutreachChannel::tryFrom(mb_strtolower(trim((string) ($channel ?? 'email')))) ?? OutreachChannel::Email;
        $toStage = OutreachStage::tryFrom(mb_strtolower(trim((string) ($stage ?? 'sent')))) ?? OutreachStage::Sent;

        DB::transaction(function () use ($company, $operatorId, $originRef, $sent, $medium, $variant, $toStage, &$report): void {
            $outreach = ScoutOutreachEloquentModel::query()->create([
                'company_id' => $company->id,
                'operator_id' => $operatorId,
                'outreach_kind' => OutreachKind::ContractorOffer->value,
                'send_medium' => $medium->value,
                'sender_kind' => (string) config('lead-scout.sender_kind_default', 'personal_mailbox'),
                'stage' => OutreachStage::Sent->value,
                'variant' => $variant === null || trim((string) $variant) === '' ? null : mb_strtolower(trim((string) $variant)),
                'sent_at' => $sent->toDateTimeString(),
                'stage_changed_at' => $sent->toDateTimeString(),
                'notes' => "Imported pre-system contact ({$originRef}).",
            ]);

            ScoutOutreachStageEventEloquentModel::query()->create([
                'outreach_id' => $outreach->id,
                'from_stage' => null,
                'to_stage' => OutreachStage::Sent->value,
                'operator_id' => $operatorId,
                'created_at' => $sent->toDateTimeString(),
            ]);

            if ($toStage !== OutreachStage::Sent) {
                $outreach->update(['stage' => $toStage->value]);

                ScoutOutreachStageEventEloquentModel::query()->create([
                    'outreach_id' => $outreach->id,
                    'from_stage' => OutreachStage::Sent->value,
                    'to_stage' => $toStage->value,
                    'operator_id' => $operatorId,
                ]);
            }

            $report['outreaches']++;
        });
    }

    private function resolveOperator(): ?User
    {
        $uuid = $this->option('operator');

        if (is_string($uuid) && $uuid !== '') {
            return User::query()->where('uuid', $uuid)->first();
        }

        return User::query()->oldest('id')->first();
    }
}
