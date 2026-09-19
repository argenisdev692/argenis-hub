<?php

declare(strict_types=1);

namespace Modules\LeadScout\Infrastructure\Persistence;

use Illuminate\Database\ConnectionInterface;
use Modules\LeadScout\Domain\Ports\TransactionPort;

final readonly class DatabaseTransaction implements TransactionPort
{
    public function __construct(private ConnectionInterface $connection) {}

    public function run(callable $work): mixed
    {
        return $this->connection->transaction(static fn (): mixed => $work());
    }
}
