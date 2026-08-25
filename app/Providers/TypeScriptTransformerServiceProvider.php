<?php

declare(strict_types=1);

namespace App\Providers;

use Spatie\LaravelTypeScriptTransformer\LaravelData\LaravelDataTypeScriptTransformerExtension;
use Spatie\LaravelTypeScriptTransformer\TypeScriptTransformerApplicationServiceProvider as BaseTypeScriptTransformerServiceProvider;
use Spatie\TypeScriptTransformer\Formatters\PrettierFormatter;
use Spatie\TypeScriptTransformer\Transformers\EnumTransformer;
use Spatie\TypeScriptTransformer\TypeScriptTransformerConfigFactory;
use Spatie\TypeScriptTransformer\Writers\GlobalNamespaceWriter;

/**
 * Generates `resources/js/generated/generated.d.ts` from the backend Spatie Data
 * classes and enums. That file is the single source of truth for every
 * backend-shaped TypeScript type on the frontend — see `.claude/FRONTEND/SKILL.md` §13.
 *
 * Regenerate with `php artisan typescript:transform` (part of the module
 * finalization pipeline in `.claude/rules/rules.md`).
 */
class TypeScriptTransformerServiceProvider extends BaseTypeScriptTransformerServiceProvider
{
    protected function configure(TypeScriptTransformerConfigFactory $config): void
    {
        $config
            // laravel-data support: Data classes, Lazy/Optional unwrapping,
            // MapOutputName/SnakeCaseMapper, PaginatedDataCollection aliases.
            // NOTE: do NOT use Spatie\LaravelData\...\DataTypeScriptTransformer —
            // that class targets laravel-typescript-transformer v2.
            ->extension(new LaravelDataTypeScriptTransformerExtension)
            ->transformer(EnumTransformer::class)
            // Modules autoload from src/, not app/ — the published stub only
            // scans app_path() and would emit zero module DTOs.
            ->transformDirectories(app_path(), base_path('src'))
            ->outputDirectory(resource_path('js/generated'))
            ->writer(new GlobalNamespaceWriter('generated.d.ts'))
            ->formatter(PrettierFormatter::class);
    }
}
