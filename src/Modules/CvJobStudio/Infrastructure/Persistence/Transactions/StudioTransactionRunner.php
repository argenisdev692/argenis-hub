<?php

declare(strict_types=1);

namespace Modules\CvJobStudio\Infrastructure\Persistence\Transactions;

use Illuminate\Support\Facades\DB;
use Modules\CvJobStudio\Domain\Ports\TransactionPort;

final readonly class StudioTransactionRunner implements TransactionPort
{
    public function atomic(callable $work): mixed
    {
        return DB::transaction($work);
    }
}
