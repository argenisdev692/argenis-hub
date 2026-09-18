<?php

declare(strict_types=1);
use Symfony\Component\HttpKernel\Exception\HttpException;

it('refuses a supabase host for pgsql_testing', function (): void {
    config()->set('database.connections.pgsql_testing.host', 'aws-0-eu-central-1.pooler.supabase.com');

    expect(fn (): mixed => ensureLocalPostgres())->toThrow(HttpException::class);
});

it('allows a local host for pgsql_testing', function (): void {
    config()->set('database.connections.pgsql_testing.host', '127.0.0.1');

    expect(ensureLocalPostgres())->toBeNull();
});
