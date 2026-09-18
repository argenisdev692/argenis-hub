<?php

declare(strict_types=1);

namespace Modules\CvJobStudio\Application\DTOs;

use Spatie\LaravelData\Attributes\MapInputName;
use Spatie\LaravelData\Attributes\MapOutputName;
use Spatie\LaravelData\Data;
use Spatie\LaravelData\Mappers\SnakeCaseMapper;

/**
 * Explicit scoring inputs for slice 1. The CV-structure tables (Phase I)
 * will feed these rows; until then the caller supplies them, which keeps
 * scoring deterministic and provider-free (NFR-1, FR-37).
 */
#[MapInputName(SnakeCaseMapper::class)]
#[MapOutputName(SnakeCaseMapper::class)]
final class ScorePostingInputData extends Data
{
    /**
     * @param  list<array{name: string, evidence: string, position_ratio: float}>  $cvSkills
     * @param  array{title_cosine: float, responsibility_cosine: float}  $similarities
     * @param  array{experience: float|null, location: float|null, education: float|null, language: float|null}  $signals
     * @param  array{readable: bool, credential_ok: bool, evidence_ok: bool}  $capContext
     */
    public function __construct(
        public readonly array $cvSkills = [],
        public readonly array $similarities = ['title_cosine' => 0.0, 'responsibility_cosine' => 0.0],
        public readonly array $signals = [],
        public readonly array $capContext = ['readable' => true, 'credential_ok' => true, 'evidence_ok' => true],
    ) {}

    /** @return array<string, mixed> */
    public static function rules(): array
    {
        return [
            'cv_skills' => ['nullable', 'array', 'max:500'],
            'cv_skills.*.name' => ['required_with:cv_skills', 'string', 'max:255'],
            'cv_skills.*.evidence' => ['required_with:cv_skills', 'string', 'in:list_only,in_bullet,both'],
            'cv_skills.*.position_ratio' => ['required_with:cv_skills', 'numeric', 'min:0', 'max:1'],
            'similarities.title_cosine' => ['nullable', 'numeric', 'min:-1', 'max:1'],
            'similarities.responsibility_cosine' => ['nullable', 'numeric', 'min:-1', 'max:1'],
            'signals.experience' => ['nullable', 'numeric', 'min:0', 'max:100'],
            'signals.location' => ['nullable', 'numeric', 'min:0', 'max:100'],
            'signals.education' => ['nullable', 'numeric', 'min:0', 'max:100'],
            'signals.language' => ['nullable', 'numeric', 'min:0', 'max:100'],
            'cap_context.readable' => ['nullable', 'boolean'],
            'cap_context.credential_ok' => ['nullable', 'boolean'],
            'cap_context.evidence_ok' => ['nullable', 'boolean'],
        ];
    }
}
