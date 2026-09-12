<?php

declare(strict_types=1);

namespace Modules\CourseScripts\Infrastructure\Parsing;

/**
 * The words an index uses to name its own parts.
 *
 * Kept in one place because the module accepts an index on **any** subject from
 * **any** author — Claude, Cursor, Microsoft 365 Copilot, anything — and those
 * authors do not agree on vocabulary. One calls a grouping a `BLOQUE`, the next
 * a `MÓDULO`, the next a `Part`. One numbers `VÍDEO 12`, the next `Lesson 12`,
 * the next just writes `## 12. Title`.
 *
 * Treating any single vocabulary as canonical is precisely the hardcoding this
 * module must not do: an index that used none of the expected words would parse
 * to nothing and be rejected as "not a course index" when it was simply written
 * in other words.
 *
 * These lists are therefore hints that *raise confidence*, never requirements.
 * {@see MarkdownIndexParser} falls back to structural extraction — headings,
 * numbered lists, table rows — when no keyword appears at all.
 */
final class IndexVocabulary
{
    /**
     * Words that introduce a GROUPING of points (a block, module, section).
     *
     * @var list<string>
     */
    public const array GROUP_KEYWORDS = [
        'BLOQUE', 'MODULO', 'MÓDULO', 'SECCION', 'SECCIÓN', 'UNIDAD', 'PARTE', 'CAPITULO', 'CAPÍTULO', 'NIVEL',
        'BLOCK', 'MODULE', 'SECTION', 'UNIT', 'PART', 'CHAPTER', 'LEVEL', 'STAGE', 'PHASE', 'TRACK',
    ];

    /**
     * Words that introduce an individual POINT (the unit a script is written for).
     *
     * @var list<string>
     */
    public const array POINT_KEYWORDS = [
        'VIDEO', 'VÍDEO', 'LECCION', 'LECCIÓN', 'CLASE', 'EPISODIO', 'TEMA', 'PUNTO', 'PILDORA', 'PÍLDORA',
        'LESSON', 'CLASS', 'EPISODE', 'TOPIC', 'POINT', 'STEP', 'CHAPTER', 'SESSION', 'CLIP',
    ];

    /**
     * Optional per-point brief labels. Present in a rich index (the reference
     * course writes all six for all 48 points), absent in a bare table of
     * contents — which is the common case and the reason research exists
     * (spec FR-4a).
     *
     * Each entry maps a brief field to the label patterns that introduce it, in
     * the languages seen so far. An unrecognised label is not an error; the
     * field simply stays null and research fills the gap.
     *
     * @var array<string, list<string>>
     */
    public const array BRIEF_LABELS = [
        'objective' => [
            'Objetivo del v[íi]deo', 'Objetivo de la lecci[óo]n', 'Objetivo', 'Prop[óo]sito',
            'Objective', 'Goal', 'Purpose', 'Aim',
        ],
        'learningAreas' => [
            '[ÁA]reas de aprendizaje', '[ÁA]reas', 'Contenidos tratados',
            'Learning areas', 'Topics covered', 'Covers',
        ],
        'studentObjectives' => [
            'Objetivos del alumno', 'Objetivos del estudiante', 'El alumno aprender[áa]',
            'Learning objectives', 'Student objectives', 'You will learn',
        ],
        'mandatoryContent' => [
            'Contenido m[íi]nimo obligatorio', 'Contenido obligatorio', 'Contenido m[íi]nimo', 'Debe incluir',
            'Required content', 'Must cover', 'Mandatory content',
        ],
        'errorsToAvoid' => [
            'Errores a evitar', 'Errores comunes', 'Qu[ée] no hacer',
            'Errors to avoid', 'Common mistakes', 'Avoid',
        ],
        'expectedResult' => [
            'Resultado esperado', 'Resultado',
            'Expected result', 'Outcome', 'Expected outcome',
        ],
    ];

    /**
     * A regex alternation of every group keyword.
     */
    public static function groupPattern(): string
    {
        return implode('|', array_map(preg_quote(...), self::GROUP_KEYWORDS));
    }

    /**
     * A regex alternation of every point keyword.
     */
    public static function pointPattern(): string
    {
        return implode('|', array_map(preg_quote(...), self::POINT_KEYWORDS));
    }

    /**
     * Every brief label, as one alternation — used as the terminator set when
     * capturing a field's value, so a field stops at the next label rather than
     * at a guessed boundary.
     */
    public static function allBriefLabelsPattern(): string
    {
        $patterns = [];

        foreach (self::BRIEF_LABELS as $labels) {
            foreach ($labels as $label) {
                $patterns[] = $label;
            }
        }

        return implode('|', $patterns);
    }

    /**
     * @return list<string>
     */
    public static function labelsFor(string $field): array
    {
        return self::BRIEF_LABELS[$field] ?? [];
    }
}
