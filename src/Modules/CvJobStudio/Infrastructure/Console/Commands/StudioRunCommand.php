<?php

declare(strict_types=1);

namespace Modules\CvJobStudio\Infrastructure\Console\Commands;

use App\Models\User;
use Illuminate\Console\Command;
use Modules\CvJobStudio\Application\Commands\StartRunHandler;

final class StudioRunCommand extends Command
{
    protected $signature = 'studio:run {profile : Profile uuid} {--user= : Operator user uuid (defaults to the first user)}';

    protected $description = 'Start a CV Job Studio discovery run for a profile.';

    public function handle(StartRunHandler $start): int
    {
        $userUuid = $this->option('user');
        $user = $userUuid !== null
            ? User::query()->where('uuid', $userUuid)->first()
            : User::query()->orderBy('id')->first();

        if ($user === null) {
            $this->error('No user found.');

            return self::FAILURE;
        }

        $run = $start->handle((string) $this->argument('profile'), (int) $user->id);

        $this->info("Run {$run->uuid} queued.");

        return self::SUCCESS;
    }
}
