<?php

declare(strict_types=1);

namespace Modules\CvJobStudio\Infrastructure\Console\Commands;

use App\Models\User;
use Illuminate\Console\Command;
use Modules\CvJobStudio\Infrastructure\Persistence\Eloquent\Models\StudioSkillRelationEloquentModel;

/**
 * ESCO import creates `origin = esco`, `status = pending` rows ONLY (T-124,
 * Q16): a taxonomy proposal never grants credit until the candidate confirms
 * it. ESCO version recorded per row.
 */
final class StudioImportEscoSkillsCommand extends Command
{
    protected $signature = 'studio:import-esco-skills {file : CSV path with from_skill,to_skill,kind} {--esco-version= : ESCO version label} {--user= : Operator user uuid}';

    protected $description = 'Import ESCO skill relations as pending proposals.';

    public function handle(): int
    {
        $path = (string) $this->argument('file');

        if (! is_file($path)) {
            $this->error("File {$path} not found.");

            return self::FAILURE;
        }

        $userUuid = $this->option('user');
        $user = $userUuid !== null
            ? User::query()->where('uuid', $userUuid)->first()
            : User::query()->orderBy('id')->first();

        if ($user === null) {
            $this->error('No user found.');

            return self::FAILURE;
        }

        $imported = 0;
        $handle = fopen($path, 'r');

        if ($handle === false) {
            $this->error("Cannot read {$path}.");

            return self::FAILURE;
        }

        while (($row = fgetcsv($handle)) !== false) {
            if (count($row) < 3 || ($row[0] ?? '') === '') {
                continue;
            }

            StudioSkillRelationEloquentModel::query()->firstOrCreate(
                [
                    'user_id' => $user->id,
                    'from_skill' => trim((string) $row[0]),
                    'to_skill' => trim((string) $row[1]),
                    'kind' => trim((string) $row[2]),
                ],
                [
                    'origin' => 'esco:'.($this->option('esco-version') ?? 'unknown'),
                    'status' => 'pending',
                ],
            );

            $imported++;
        }

        fclose($handle);

        $this->info("Imported {$imported} pending relation(s).");

        return self::SUCCESS;
    }
}
