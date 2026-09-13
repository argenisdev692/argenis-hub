<?php

declare(strict_types=1);

namespace Modules\CourseScripts\Domain\Enums;

/**
 * The typed parts a section is composed of (FR-30), mirroring the reference
 * scripts: quoted narration, PROMPT:, ACCIONES EN PANTALLA:, MOSTRAR EN
 * PANTALLA, TABLA EN PANTALLA.
 */
enum SegmentType: string
{
    case Narration = 'narration';
    case OnScreenPrompt = 'on_screen_prompt';
    case ExpectedResult = 'expected_result';
    case OnScreenActions = 'on_screen_actions';
    case ShowOnScreen = 'show_on_screen';
    case OnScreenTable = 'on_screen_table';
    case PresenterNote = 'presenter_note';

    /**
     * Prompts only exist when the course teaches a tool to type into (FR-30a).
     */
    public function requiresTool(): bool
    {
        return $this === self::OnScreenPrompt;
    }
}
