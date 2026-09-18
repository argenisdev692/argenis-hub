<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Schema;

function studioModuleFiles(string $subpath): array
{
    $files = [];
    $iterator = new RecursiveIteratorIterator(new RecursiveDirectoryIterator(base_path("src/Modules/CvJobStudio/{$subpath}")));

    foreach ($iterator as $file) {
        if ($file->isFile() && $file->getExtension() === 'php') {
            $files[] = $file->getPathname();
        }
    }

    return $files;
}

function studioFileUses(string $path, string $needle): bool
{
    return str_contains((string) file_get_contents($path), $needle);
}

it('keeps the domain free of framework imports (T-101)', function (): void {
    // Ports name their aggregate model in the contract (Cvs precedent:
    // CvRepositoryPort references CvEloquentModel) — purity applies to
    // Services, ValueObjects, Enums and Exceptions.
    $violations = [];

    foreach (['Domain/Services', 'Domain/ValueObjects', 'Domain/Enums', 'Domain/Exceptions'] as $subpath) {
        foreach (studioModuleFiles($subpath) as $file) {
            foreach (['use Illuminate\\', 'Eloquent', 'Facades\\', 'Http\\', 'parse_url('] as $forbidden) {
                if (studioFileUses($file, $forbidden)) {
                    $violations[] = basename($file).": {$forbidden}";
                }
            }
        }
    }

    expect($violations)->toBeEmpty();
});

it('keeps controllers thin: no domain services or repositories in HTTP (T-101)', function (): void {
    $violations = [];

    foreach (studioModuleFiles('Infrastructure/Http') as $file) {
        foreach (['Domain\\Services\\', 'Persistence\\Repositories\\'] as $forbidden) {
            if (studioFileUses($file, $forbidden)) {
                $violations[] = basename($file).": {$forbidden}";
            }
        }
    }

    expect($violations)->toBeEmpty();
});

it('keeps migrations SQLite-portable: json, never jsonb (T-112, CHG-17)', function (): void {
    $violations = [];

    foreach (glob(database_path('migrations/*studio*.php')) ?: [] as $file) {
        if (studioFileUses($file, '->jsonb(')) {
            $violations[] = basename($file);
        }
    }

    expect($violations)->toBeEmpty();
});

it('ships no JsonResource and no scoring literals outside rules/config (T-101, SC-11)', function (): void {
    $resources = [];
    $literals = [];

    foreach (studioModuleFiles('') as $file) {
        if (str_contains($file, 'Tests')) {
            continue;
        }

        if (studioFileUses($file, 'JsonResource')) {
            $resources[] = basename($file);
        }

        if (str_contains($file, 'Domain'.DIRECTORY_SEPARATOR.'Services')) {
            // Docblocks document the ruleset; only executable literals fail.
            $code = (string) preg_replace(['#/\*.*?\*/#s', '#//.*$#m'], '', (string) file_get_contents($file));

            foreach (['0.45', '0.25', '0.30', '0.15/0.85', '69.55'] as $literal) {
                if (str_contains($code, $literal)) {
                    $literals[] = basename($file).": {$literal}";
                }
            }
        }
    }

    expect($resources)->toBeEmpty()->and($literals)->toBeEmpty();
});

it('sends no request path to any link_only or resolve_only host (T-045, SC-16)', function (): void {
    $hosts = config('cv-job-studio.never_fetch_hosts', []);

    expect($hosts)->toContain('linkedin.com')
        ->and($hosts)->toContain('indeed.com')
        ->and($hosts)->toContain('tecnoempleo.com')
        ->and($hosts)->toContain('glassdoor.com');

    $violations = [];

    foreach (studioModuleFiles('Infrastructure/Sources') as $file) {
        foreach ($hosts as $host) {
            if (studioFileUses($file, "'{$host}'") || studioFileUses($file, "\"{$host}\"")) {
                $violations[] = basename($file).": {$host}";
            }
        }
    }

    foreach (studioModuleFiles('Infrastructure/Fetching') as $file) {
        foreach ($hosts as $host) {
            if (studioFileUses($file, $host)) {
                $violations[] = basename($file).": {$host}";
            }
        }
    }

    expect($violations)->toBeEmpty();
});

it('uses no MCP on any production path (T-046)', function (): void {
    $violations = [];

    foreach (studioModuleFiles('') as $file) {
        if (str_contains($file, 'Tests')) {
            continue;
        }

        $contents = strtolower((string) file_get_contents($file));

        if (str_contains($contents, 'mcp') && ! str_contains($contents, 'mcp-is-not-production')) {
            $violations[] = basename($file);
        }
    }

    expect($violations)->toBeEmpty();
});

it('holds the full studio schema on the test connection (T-112, sqlite leg)', function (): void {
    $tables = [
        'studio_profiles', 'studio_postings', 'studio_posting_texts', 'studio_requirements',
        'studio_gate_results', 'studio_scores', 'studio_skill_matches', 'studio_skill_relations',
        'studio_sources', 'studio_source_locales', 'studio_source_companies', 'studio_posting_sources',
        'studio_posting_sightings', 'studio_vocabulary', 'studio_query_templates', 'studio_query_experiments',
        'studio_channel_baselines', 'studio_runs', 'studio_applications', 'studio_insight_reports',
        'studio_budgets', 'studio_provider_calls', 'studio_embeddings', 'studio_cv_structures',
        'studio_cv_entries', 'studio_cv_bullets', 'studio_cv_skills', 'studio_cv_audits',
        'studio_metric_answers', 'studio_cv_versions', 'studio_exports',
    ];

    $missing = array_filter($tables, static fn (string $table): bool => ! Schema::hasTable($table));

    expect($missing)->toBeEmpty()->and($tables)->toHaveCount(31);
});
