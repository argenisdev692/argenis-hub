<?php

declare(strict_types=1);

use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Modules\LeadScout\Domain\Services\ChannelAdvisor;
use Modules\LeadScout\Infrastructure\Persistence\Eloquent\Models\ScoutSuppressionEloquentModel;

uses(RefreshDatabase::class);

beforeEach(function (): void {
    $this->seed(RolePermissionSeeder::class);
});

function dgcCsv(array $rows): string
{
    $path = sys_get_temp_dir().'/lead-scout-dgc-test-'.uniqid().'.csv';
    $handle = fopen($path, 'wb');
    fputcsv($handle, ['nipc', 'dominio', 'nome']);
    foreach ($rows as $row) {
        fputcsv($handle, $row);
    }
    fclose($handle);

    return $path;
}

it('imports the DGC list matched by NIPC, domain or name', function (): void {
    $file = dgcCsv([
        ['512345678', 'opositora.example.pt', 'Opositora Lda'],
        ['', 'solo-dominio.example.pt', 'Solo Dominio'],
        ['', '', ''],
    ]);

    try {
        $this->artisan('lead-scout:import-dgc', ['file' => $file, '--period' => '2026-Q3'])
            ->assertSuccessful();
    } finally {
        unlink($file);
    }

    $listed = ScoutSuppressionEloquentModel::query()->where('source', 'dgc_list')->get();

    expect($listed)->toHaveCount(2)
        ->and($listed->pluck('list_period')->unique()->all())->toBe(['2026-Q3'])
        ->and(ScoutSuppressionEloquentModel::query()->where('tax_id', '512345678')->exists())->toBeTrue()
        ->and(ScoutSuppressionEloquentModel::query()->where('canonical_domain', 'solo-dominio.example.pt')->exists())->toBeTrue();
});

it('does not duplicate on re-import and rejects bad files', function (): void {
    $file = dgcCsv([['512345678', 'opositora.example.pt', 'Opositora Lda']]);

    try {
        $this->artisan('lead-scout:import-dgc', ['file' => $file, '--period' => '2026-Q3'])->assertSuccessful();
        $this->artisan('lead-scout:import-dgc', ['file' => $file, '--period' => '2026-Q4'])->assertSuccessful();
    } finally {
        unlink($file);
    }

    expect(ScoutSuppressionEloquentModel::query()->where('source', 'dgc_list')->count())->toBe(1)
        ->and(ScoutSuppressionEloquentModel::query()->value('list_period'))->toBe('2026-Q4');

    $bad = sys_get_temp_dir().'/lead-scout-dgc-test-'.uniqid().'.txt';
    file_put_contents($bad, 'nipc');

    try {
        $this->artisan('lead-scout:import-dgc', ['file' => $bad])->assertFailed();
    } finally {
        unlink($bad);
    }

    $this->artisan('lead-scout:import-dgc', ['file' => sys_get_temp_dir().'/missing-dgc.csv'])->assertFailed();
});

it('blocks PT email while listed and while the list is stale', function (): void {
    $advisor = app(ChannelAdvisor::class);
    $channel = [
        'uuid' => 'chan-1',
        'type' => 'generic_email',
        'url' => null,
        'generic_email' => 'geral@example.pt',
        'status' => 'active',
        'audience' => null,
    ];
    $rules = [
        ['country' => 'PT', 'medium' => 'email', 'mailbox' => 'generic', 'decision' => 'allow', 'legal_status' => 'verified'],
    ];

    $listed = $advisor->advise([$channel], [
        'country' => 'PT', 'has_offer' => false, 'is_employment_offer' => false,
        'discovery_without_offer' => true, 'has_decisor' => true, 'nominative_email' => null,
        'dgc_listed' => true, 'dgc_list_stale' => false,
    ], $rules);

    expect($listed['ranked'][0]['allowed'])->toBeFalse()
        ->and($listed['ranked'][0]['blocked_reason'])->toContain('DGC');

    $stale = $advisor->advise([$channel], [
        'country' => 'PT', 'has_offer' => false, 'is_employment_offer' => false,
        'discovery_without_offer' => true, 'has_decisor' => true, 'nominative_email' => null,
        'dgc_listed' => false, 'dgc_list_stale' => true,
    ], $rules);

    expect($stale['ranked'][0]['allowed'])->toBeFalse()
        ->and($stale['ranked'][0]['blocked_reason'])->toContain('3 months');
});
