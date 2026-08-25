<?php

declare(strict_types=1);

namespace Shared\Infrastructure\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Redis;
use Illuminate\Support\Str;
use Throwable;

/**
 * Verifies that the configured Redis server (Upstash) is actually reachable.
 *
 * This exists because a bad Redis config poisons `tinker` itself — the usual
 * way you would check — leaving no obvious route to a straight answer. It also
 * round-trips a real key per connection, because a successful PING only proves
 * the socket opened: `SELECT`ing a database Upstash does not have fails later,
 * on the first actual read or write.
 *
 * The password is never printed. The host is, because you need to see whether
 * the `tls://` prefix survived.
 */
final class PingRedisCommand extends Command
{
    protected $signature = 'redis:ping';

    protected $description = 'Check that every configured Redis connection accepts a command round-trip.';

    public function handle(): int
    {
        $failed = false;

        foreach (['default', 'cache'] as $name) {
            $failed = $this->check($name) || $failed;
        }

        if ($failed) {
            $this->newLine();
            $this->line('Upstash checklist: host must carry the <options=bold>tls://</> prefix,');
            $this->line('port 6379, and every *_DB value must be <options=bold>0</> (Upstash has no other).');

            return self::FAILURE;
        }

        $this->newLine();
        $this->info('All Redis connections are healthy.');

        return self::SUCCESS;
    }

    /**
     * @return bool true when the connection is broken
     */
    private function check(string $name): bool
    {
        /** @var array<string, mixed> $config */
        $config = config("database.redis.{$name}", []);

        $this->line(sprintf(
            '<options=bold>%s</> → %s:%s db=%s',
            $name,
            $config['host'] ?? '(no host)',
            $config['port'] ?? '(no port)',
            $config['database'] ?? '(no db)',
        ));

        try {
            $connection = Redis::connection($name);

            $connection->ping();

            // A PING can succeed on a connection whose SELECT will later fail,
            // so prove a real key round-trips before calling this healthy.
            $probeKey = 'health:ping:'.Str::uuid7();
            $connection->setex($probeKey, 10, 'ok');
            $value = $connection->get($probeKey);
            $connection->del($probeKey);

            if ($value !== 'ok') {
                $this->error('  round-trip returned an unexpected value');

                return true;
            }
        } catch (Throwable $e) {
            $this->error('  '.$e->getMessage());

            return true;
        }

        $this->info('  ok');

        return false;
    }
}
