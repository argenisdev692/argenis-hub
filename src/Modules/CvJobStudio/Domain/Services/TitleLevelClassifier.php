<?php

declare(strict_types=1);

namespace Modules\CvJobStudio\Domain\Services;

/**
 * Title seniority step relative to the profile band (Q13, FR-46). "Senior"
 * titles with no stated years floor are an opportunity factor, never a fit
 * cap. EN/ES/PT lexicon.
 */
final readonly class TitleLevelClassifier
{
    /** @var array<string, int> */
    private const array STEPS = [
        'junior' => 0, 'júnior' => 0, 'jr' => 0, 'trainee' => 0, 'intern' => 0,
        'mid' => 1, 'pleno' => 1, 'pleno/senior' => 1, 'semi-senior' => 1, 'sénior' => 2,
        'senior' => 2, 'sr' => 2, 'sênior' => 2,
        'lead' => 3, 'líder técnico' => 3, 'tech lead' => 3, 'staff' => 3,
        'principal' => 4, 'head' => 4, 'architect' => 3, 'arquiteto' => 3, 'arquitecto' => 3,
        'manager' => 3, 'director' => 4, 'vp' => 4, 'cto' => 4,
    ];

    #[\NoDiscard]
    public function stepOf(string $title): ?int
    {
        $haystack = mb_strtolower($title);

        foreach (self::STEPS as $token => $step) {
            if (preg_match('/\b'.preg_quote($token, '/').'\b/u', $haystack) === 1) {
                return $step;
            }
        }

        return null;
    }
}
