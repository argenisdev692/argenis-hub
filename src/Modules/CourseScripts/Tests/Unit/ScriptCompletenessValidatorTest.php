<?php

declare(strict_types=1);

use Modules\CourseScripts\Domain\Services\BibleRegistry;
use Modules\CourseScripts\Domain\Services\ContactDataValidator;
use Modules\CourseScripts\Domain\Services\ScriptCompletenessValidator;
use Modules\CourseScripts\Domain\Services\TableArithmeticValidator;
use Modules\CourseScripts\Domain\ValueObjects\ScriptDraft;

function completenessValidator(): ScriptCompletenessValidator
{
    return new ScriptCompletenessValidator(new TableArithmeticValidator, new ContactDataValidator, new BibleRegistry);
}

/**
 * A one-section draft whose section shows `$prompt` and narrates `$narration`.
 *
 * @param  array<string, mixed>|null  $exercise
 */
function draftWith(string $narration, string $prompt, ?array $exercise = null): ScriptDraft
{
    $segment = static fn (string $type, string $text = '', string $prompt = ''): array => [
        'type' => $type, 'text' => $text, 'prompt' => $prompt, 'items' => [], 'table_columns' => [],
        'table_rows' => [], 'read_aloud' => false, 'practice_file' => null,
    ];

    return new ScriptDraft(
        recordingFormat: 'Grabación de pantalla con narración',
        learningObjectives: ['Crear un artifact'],
        continuityNote: '',
        usesTool: true,
        sections: [[
            'number' => '3', 'parent_number' => null, 'title' => 'DEMO', 'kind' => 'demo', 'minutes' => 2,
            'purpose' => '', 'demo_label' => 'DEMO 1', 'demo_purpose' => '', 'practice_files' => [],
            'segments' => [$segment('narration', $narration), $segment('on_screen_prompt', prompt: $prompt)],
        ]],
        coverageMap: [],
        taughtSummary: '',
        practicePlan: ['warranted' => false, 'artifacts' => []],
        closing: [
            'summary_points' => ['Un artifact es un documento vivo'],
            'next_video_handoff' => '',
            'recording_notes' => ['preparation' => ['Abrir claude.ai'], 'tools_required' => ['Claude'], 'organisations_used' => []],
            'verification_checklist' => ['DEMO 1 crea un artifact'],
            'practice_exercise' => $exercise ?? [
                'title' => 'Tu panel de gastos',
                'scenario' => 'Cada mes repasas tus gastos en una hoja que nadie entiende.',
                'task' => 'Pide a Claude un artifact que convierta tus gastos en un panel interactivo.',
                'success_criteria' => ['El panel se actualiza al cambiar una cifra'],
            ],
        ],
    );
}

const ARTIFACT_PROMPT = 'Crea un artifact interactivo que muestre mis gastos mensuales por categoría en un gráfico de barras';

it('accepts narration that summarises the prompt instead of reading it', function (): void {
    $draft = draftWith('"Ahora le pedimos a Claude un panel visual de gastos. Fíjate en lo que devuelve."', ARTIFACT_PROMPT);

    expect(completenessValidator()->violations($draft, false, null))->toBe([]);
});

it('flags narration that reads the prompt aloud word for word', function (): void {
    $draft = draftWith('"Escribo: crea un artifact interactivo que muestre mis gastos mensuales por categoría en un gráfico de barras."', ARTIFACT_PROMPT);

    expect(completenessValidator()->segments($draft))
        ->toBe(['Section 3 narration reads the on-screen prompt aloud word for word: the prompt is pasted, so the narration must summarise its intent in one sentence instead.']);
});

it('lets the narration quote a short phrase of the prompt', function (): void {
    $draft = draftWith('"Pedimos un gráfico de barras por categoría, nada más."', ARTIFACT_PROMPT);

    expect(completenessValidator()->segments($draft))->toBe([]);
});

it('requires a practical exercise with a task and success criteria', function (): void {
    $draft = draftWith('"Pedimos un panel de gastos."', ARTIFACT_PROMPT, ['title' => '', 'scenario' => '', 'task' => '', 'success_criteria' => []]);

    expect(completenessValidator()->closing($draft, false, null))->toBe([
        'The practical exercise (EJERCICIO PRÁCTICO) for the student needs a realistic scenario and a task.',
        'The practical exercise (EJERCICIO PRÁCTICO) needs success criteria the student can check.',
    ]);
});
