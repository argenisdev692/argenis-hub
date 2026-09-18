<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;

uses()->group('pgsql');

beforeEach(function (): void {
    ensureLocalPostgres();

    // No RefreshDatabase here by design: it would migrate the default
    // (sqlite) connection in setUp, before this hook can switch. Schema
    // assertions need no rollback — migrate the pgsql database directly.
    try {
        Artisan::call('migrate', ['--database' => 'pgsql_testing', '--force' => true]);
    } catch (Throwable) {
        $this->markTestSkipped('Local PostgreSQL (pgsql_testing) is not available.');
    }

    config()->set('database.default', 'pgsql_testing');
});

/**
 * @return list<string>
 */
function scoutTables(): array
{
    return [
        'scout_profiles',
        'scout_sources',
        'scout_suppressions',
        'scout_decision_rules',
        'scout_ai_settings',
        'scout_budgets',
        'scout_companies',
        'scout_job_postings',
        'scout_job_posting_sources',
        'scout_search_queries',
        'scout_fetched_pages',
        'scout_fetch_attempts',
        'scout_signals',
        'scout_score_results',
        'scout_score_reasons',
        'scout_contacts',
        'scout_contact_channels',
        'scout_contact_objections',
        'scout_privacy_requests',
        'scout_outreaches',
        'scout_outreach_stage_events',
        'scout_opportunities',
    ];
}

it('has all 22 scout tables with RLS enabled', function (): void {
    foreach (scoutTables() as $table) {
        $row = DB::selectOne(
            "SELECT relrowsecurity FROM pg_class WHERE relname = ? AND relkind = 'r'",
            [$table],
        );

        expect($row)->not->toBeNull("table {$table} is missing")
            ->and((bool) $row->relrowsecurity)->toBeTrue("RLS is not enabled on {$table}");
    }

    expect(scoutTables())->toHaveCount(22);
});

it('enforces the current-row partial uniques', function (): void {
    foreach ([
        'uq_scout_profiles_current' => 'CREATE UNIQUE INDEX uq_scout_profiles_current',
        'uq_scout_score_results_current' => 'CREATE UNIQUE INDEX uq_scout_score_results_current',
        'uq_scout_contacts_primary' => 'CREATE UNIQUE INDEX uq_scout_contacts_primary',
        'uq_scout_suppressions_domain' => 'CREATE UNIQUE INDEX uq_scout_suppressions_domain',
    ] as $name => $prefix) {
        $row = DB::selectOne(
            'SELECT indexdef FROM pg_indexes WHERE indexname = ?',
            [$name],
        );

        expect($row)->not->toBeNull("partial index {$name} is missing")
            ->and(str_starts_with($row->indexdef, $prefix))->toBeTrue()
            ->and(str_contains($row->indexdef, 'WHERE'))->toBeTrue();
    }
});

it('enforces the terms and sent CHECK constraints', function (): void {
    foreach (['ck_scout_sources_terms', 'ck_scout_outreaches_sent'] as $name) {
        $row = DB::selectOne(
            "SELECT 1 AS ok FROM pg_constraint WHERE conname = ? AND contype = 'c'",
            [$name],
        );

        expect($row)->not->toBeNull("CHECK constraint {$name} is missing");
    }
});
