<?php

declare(strict_types=1);

namespace Modules\VideoEdits\Infrastructure\Console\Commands;

use Illuminate\Console\Command;
use Modules\VideoEdits\Application\Commands\SweepStaleVideoEditsHandler;

/**
 * Times out edits that stopped reporting progress, resolves cut reviews left
 * unanswered and deletes drafts that were never submitted (spec 001-video-edit
 * AD-14, D17). Scheduled every five minutes in routes/console.php; registered
 * by VideoEditsServiceProvider.
 */
final class SweepStaleVideoEditsCommand extends Command
{
    protected $signature = 'video-edits:sweep';

    protected $description = 'Time out stuck video edits, resolve expired cut reviews and delete abandoned drafts.';

    public function handle(SweepStaleVideoEditsHandler $sweep): int
    {
        $result = $sweep->handle();

        $this->components->info(sprintf(
            'Timed out %d video edit(s); resolved %d expired cut review(s); deleted %d abandoned draft(s).',
            $result['timed_out'],
            $result['expired_reviews'],
            $result['expired_drafts'],
        ));

        return self::SUCCESS;
    }
}
