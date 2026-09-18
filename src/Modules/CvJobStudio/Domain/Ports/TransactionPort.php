<?php

declare(strict_types=1);

namespace Modules\CvJobStudio\Domain\Ports;

/**
 * Transaction demarcation for cross-aggregate use-cases (ingest writes four
 * tables; outcome recording touches applications + baselines). The Cvs port
 * contract forbids the Application layer from reaching for the `DB` facade,
 * so demarcation lives behind this Domain-owned port; the Infrastructure
 * implementation is a thin wrapper around `DB::transaction()` (the 95% case
 * per ARCHITECTURE-PHP — no UnitOfWork, no generic bus).
 */
interface TransactionPort
{
    /**
     * @template T
     *
     * @param  callable(): T  $work
     * @return T
     */
    public function atomic(callable $work): mixed;
}
