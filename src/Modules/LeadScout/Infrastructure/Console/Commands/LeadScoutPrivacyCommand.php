<?php

declare(strict_types=1);

namespace Modules\LeadScout\Infrastructure\Console\Commands;

use Illuminate\Console\Command;
use Modules\LeadScout\Application\Commands\HandlePrivacyRequestHandler;
use Modules\LeadScout\Infrastructure\Persistence\Eloquent\Models\ScoutSuppressionEloquentModel;
use Shared\Domain\Ports\AuditPort;

/**
 * Privacy administration (spec FR-29/FR-43, T073/T084).
 *
 * `search|export|erase|object` serve data-subject rights by name/email
 * fragment (min 3 chars); each request is ledgered without PII.
 * `lift-suppression` is the SOLE way back from a suppression, allowed only
 * when the company itself re-initiates contact, with evidence recorded.
 */
final class LeadScoutPrivacyCommand extends Command
{
    protected $signature = 'lead-scout:privacy {action : search|export|erase|object|lift-suppression} {--name=} {--email=} {--domain=} {--evidence=} {--output= : Export destination (outside the git-tracked tree)}';

    protected $description = 'Privacy administration for LeadScout personal data';

    public function handle(HandlePrivacyRequestHandler $privacy, AuditPort $audit): int
    {
        $action = (string) $this->argument('action');

        if ($action === 'lift-suppression') {
            return $this->liftSuppression($audit);
        }

        if (! in_array($action, ['search', 'export', 'erase', 'object'], true)) {
            $this->error("Unknown action '{$action}'. Use search|export|erase|object|lift-suppression.");

            return self::FAILURE;
        }

        $query = $this->subjectQuery();

        if ($query === null) {
            $this->error('Pass --name= or --email= with at least 3 characters.');

            return self::FAILURE;
        }

        return match ($action) {
            'search' => $this->runSearch($privacy, $query),
            'export' => $this->runExport($privacy, $query),
            'erase' => $this->runErase($privacy, $query),
            default => $this->runObject($privacy, $query),
        };
    }

    private function subjectQuery(): ?string
    {
        foreach (['name', 'email'] as $option) {
            $value = $this->option($option);

            if (is_string($value) && mb_strlen(trim($value)) >= 3) {
                return trim($value);
            }
        }

        return null;
    }

    private function runSearch(HandlePrivacyRequestHandler $privacy, string $query): int
    {
        $rows = $privacy->search($query);

        if ($rows === []) {
            $this->info('No matches.');
        }

        $this->table(['uuid', 'company', 'role', 'anonymized'], array_map(static fn (array $row): array => [
            $row['uuid'],
            $row['company'],
            (string) ($row['role'] ?? '—'),
            $row['anonymized'] ? 'yes' : 'no',
        ], $rows));

        return self::SUCCESS;
    }

    private function runExport(HandlePrivacyRequestHandler $privacy, string $query): int
    {
        $output = $this->option('output');
        $path = is_string($output) && trim($output) !== ''
            ? trim($output)
            : storage_path('app/private/lead-scout-privacy/privacy-export-'.now()->format('Ymd-His').'.json');

        if (! $this->isAllowedDestination($path)) {
            $this->error('Export destination must be outside the git-tracked tree (use storage/ or a path outside the project).');

            return self::FAILURE;
        }

        $records = $privacy->export($query);

        if (! is_dir(dirname($path))) {
            mkdir(dirname($path), 0755, true);
        }

        file_put_contents($path, json_encode($records, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));
        $this->info('Exported '.count($records)." record(s) to {$path}.");

        return self::SUCCESS;
    }

    private function runErase(HandlePrivacyRequestHandler $privacy, string $query): int
    {
        $report = $privacy->erase($query);
        $this->info("Anonymized {$report['anonymized']} contact(s).");

        return self::SUCCESS;
    }

    private function runObject(HandlePrivacyRequestHandler $privacy, string $query): int
    {
        $report = $privacy->object($query);
        $this->info("Anonymized {$report['anonymized']} contact(s), {$report['objections']} new objection(s).");

        return self::SUCCESS;
    }

    private function isAllowedDestination(string $path): bool
    {
        $base = realpath(base_path()) ?: base_path();
        $storage = realpath(storage_path()) ?: storage_path();
        $parent = realpath(dirname($path));

        // Non-existent yet: resolve against the closest existing ancestor.
        if ($parent === false) {
            $parent = $base;
            $probe = dirname($path);
            while (! is_dir($probe) && dirname($probe) !== $probe) {
                $probe = dirname($probe);
            }
            $parent = realpath($probe) ?: $base;
        }

        // Outside the project → always allowed; inside → only under storage/.
        return ! str_starts_with((string) $parent, (string) $base)
            || str_starts_with((string) $parent, (string) $storage);
    }

    private function liftSuppression(AuditPort $audit): int
    {
        $domain = $this->option('domain');
        $evidence = $this->option('evidence');

        if (! is_string($domain) || trim($domain) === '') {
            $this->error('Pass --domain=.');

            return self::FAILURE;
        }

        if (! is_string($evidence) || trim($evidence) === '') {
            $this->error('Lifting a suppression requires --evidence (only when the company re-initiates contact).');

            return self::FAILURE;
        }

        $row = ScoutSuppressionEloquentModel::query()
            ->where('canonical_domain', mb_strtolower(trim($domain)))
            ->first();

        if ($row === null) {
            $this->error('No suppression for that domain.');

            return self::FAILURE;
        }

        $row->delete();
        $audit->log('lead-scout.suppression.lifted', $row, ['domain' => $row->canonical_domain, 'evidence' => mb_substr(trim($evidence), 0, 500)]);

        $this->info("Suppression lifted for {$row->canonical_domain}.");

        return self::SUCCESS;
    }
}
