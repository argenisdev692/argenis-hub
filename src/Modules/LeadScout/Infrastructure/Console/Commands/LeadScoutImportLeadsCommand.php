<?php

declare(strict_types=1);

namespace Modules\LeadScout\Infrastructure\Console\Commands;

use App\Models\User;
use Illuminate\Console\Command;
use Modules\LeadScout\Application\Commands\ImportLeadsHandler;

/**
 * Imports the operator ICP list (spec FR-20, T029): reads the CSV
 * (`nombre,url,nota[,sent_at,channel,variant,stage]`, no header) and hands
 * the rows to `ImportLeadsHandler`, then prints its report.
 */
final class LeadScoutImportLeadsCommand extends Command
{
    protected $signature = 'lead-scout:import-leads {file : CSV path with nombre,url,nota[,sent_at,channel,variant,stage]} {--operator= : Operator user uuid (defaults to the first user)}';

    protected $description = 'Import the ICP agency list (CSV) with optional pre-system contact history';

    public function handle(ImportLeadsHandler $import): int
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

        $lines = file($path, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES) ?: [];
        $report = $import->handle(array_map(str_getcsv(...), $lines), $operator->id, basename($path));

        foreach ($report['warnings'] as $warning) {
            $this->warn($warning);
        }

        $this->info("Created {$report['created']}, merged {$report['merged']}, invalid {$report['invalid']}, suppressed {$report['suppressed']}, outreaches {$report['outreaches']}.");

        return self::SUCCESS;
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
