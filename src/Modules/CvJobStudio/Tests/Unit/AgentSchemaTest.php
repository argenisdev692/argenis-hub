<?php

declare(strict_types=1);

use Illuminate\JsonSchema\JsonSchemaTypeFactory;
use Modules\CvJobStudio\Infrastructure\Ai\CvStructureParserAgent;
use Modules\CvJobStudio\Infrastructure\Ai\ExtractRequirementsAgent;
use Modules\CvJobStudio\Infrastructure\Ai\JudgeCvAgent;
use Modules\CvJobStudio\Infrastructure\Ai\NarrateMatchAgent;
use Modules\CvJobStudio\Infrastructure\Ai\RewriteCvAgent;
use Modules\CvJobStudio\Infrastructure\Ai\TailorCvAgent;
use Modules\CvJobStudio\Infrastructure\Ai\TranslateCvAgent;

/*
| Agent schema contract (AllProvidersFailed regression): the installed
| JsonSchema SDK accepts `object()` properties as an array or closure —
| never as named arguments. A named-argument schema throws
| "Unknown named parameter" at build time inside every provider call,
| which failover turns into AllProvidersFailed. This test builds every
| studio agent schema against the real factory so the mismatch can never
| ship silently again.
 */
it('builds every studio agent schema against the installed JsonSchema SDK', function (): void {
    $schema = new JsonSchemaTypeFactory;

    expect(array_keys((new ExtractRequirementsAgent)->schema($schema)))->toBe(['requirements', 'responsibilities'])
        ->and(array_keys((new RewriteCvAgent)->schema($schema)))->toBe(['sections', 'cut_notes'])
        ->and(array_keys((new TranslateCvAgent)->schema($schema)))->toBe(['sections'])
        ->and(array_keys((new TailorCvAgent)->schema($schema)))->toBe(['summary', 'skills', 'provenance'])
        ->and(array_keys((new JudgeCvAgent)->schema($schema)))->toBe([
            'verdict', 'verdict_reasons', 'strengths', 'improvements', 'keyword_gaps', 'xyz_gaps', 'metric_questions',
        ])
        ->and(array_keys((new NarrateMatchAgent)->schema($schema)))->toBe(['why', 'gaps'])
        ->and(array_keys((new CvStructureParserAgent)->schema($schema)))->toBe(['profile_facts', 'entries', 'bullets', 'skills']);
});
