<?php

declare(strict_types=1);

namespace Modules\CourseScripts\Infrastructure\Rendering;

/**
 * Deliverable headings in the course language, reusing the author's own
 * wording from GUIDE/MODULE-VIDEOS for Spanish.
 */
final class RenderVocabulary
{
    private const array LABELS = [
        'es' => [
            'script' => 'GUIÓN – VÍDEO',
            'technical' => 'INFORMACIÓN TÉCNICA',
            'duration' => 'Duración',
            'minutes' => 'minutos',
            'minute' => 'minuto',
            'block' => 'Bloque',
            'format' => 'Formato',
            'video' => 'Vídeo',
            'objectives' => 'OBJETIVOS DE APRENDIZAJE',
            'continuity' => 'Continuidad',
            'prompt' => 'PROMPT',
            'expected_result' => 'RESULTADO ESPERADO EN PANTALLA',
            'actions' => 'ACCIONES EN PANTALLA',
            'show' => 'MOSTRAR EN PANTALLA',
            'read_aloud' => 'leer en voz alta',
            'table' => 'TABLA EN PANTALLA',
            'note' => 'NOTA PARA EL PRESENTADOR',
            'practice_file' => 'Archivo de práctica',
            'summary' => 'RESUMEN',
            'next_video' => 'PRÓXIMO VÍDEO',
            'recording_notes' => 'NOTAS TÉCNICAS PARA LA GRABACIÓN',
            'preparation' => 'Preparación previa',
            'during_recording' => 'Durante la grabación',
            'tools' => 'Conectores y herramientas necesarias',
            'continuity_notes' => 'Continuidad con vídeos anteriores y siguientes',
            'organisations' => 'Organizaciones ficticias',
            'checklist' => 'VERIFICACIÓN FINAL',
            'end' => 'FIN DEL GUIÓN VÍDEO',
            'version' => 'Versión',
            'prompts_title' => 'PROMPTS EN PANTALLA · VÍDEO',
            'prompts_intro' => 'Pega cada prompt en el orden indicado. Ten preparados antes los archivos de práctica que se indican.',
            'paste_first' => 'Tener preparado antes',
            'section' => 'Sección',
            'files' => 'Archivos',
            'use_in' => 'Usar en',
            'instructor_note' => 'NOTA PARA EL INSTRUCTOR',
            'contrasts' => 'Diferencias diseñadas',
            'dimension' => 'Dimensión',
            'intended_effect' => 'Efecto buscado',
            'fictional' => 'Datos ficticios: todas las organizaciones, personas y datos de contacto son inventados.',
            'ungrounded' => 'Aviso: este guion se escribió sin fuentes de investigación externas.',
            'not_passed' => 'Aviso: este guion no superó la segunda revisión; revisa las observaciones.',
        ],
        'en' => [
            'script' => 'SCRIPT – VIDEO',
            'technical' => 'TECHNICAL INFORMATION',
            'duration' => 'Duration',
            'minutes' => 'minutes',
            'minute' => 'minute',
            'block' => 'Block',
            'format' => 'Format',
            'video' => 'Video',
            'objectives' => 'LEARNING OBJECTIVES',
            'continuity' => 'Continuity',
            'prompt' => 'PROMPT',
            'expected_result' => 'EXPECTED RESULT ON SCREEN',
            'actions' => 'ON-SCREEN ACTIONS',
            'show' => 'SHOW ON SCREEN',
            'read_aloud' => 'read aloud',
            'table' => 'TABLE ON SCREEN',
            'note' => 'PRESENTER NOTE',
            'practice_file' => 'Practice file',
            'summary' => 'SUMMARY',
            'next_video' => 'NEXT VIDEO',
            'recording_notes' => 'TECHNICAL RECORDING NOTES',
            'preparation' => 'Preparation',
            'during_recording' => 'During recording',
            'tools' => 'Connectors and tools required',
            'continuity_notes' => 'Continuity with previous and next videos',
            'organisations' => 'Fictional organisations',
            'checklist' => 'FINAL CHECKLIST',
            'end' => 'END OF SCRIPT VIDEO',
            'version' => 'Version',
            'prompts_title' => 'ON-SCREEN PROMPTS · VIDEO',
            'prompts_intro' => 'Paste each prompt in order. Have the listed practice files ready first.',
            'paste_first' => 'Have ready first',
            'section' => 'Section',
            'files' => 'Files',
            'use_in' => 'Use in',
            'instructor_note' => 'NOTE FOR THE INSTRUCTOR',
            'contrasts' => 'Designed differences',
            'dimension' => 'Dimension',
            'intended_effect' => 'Intended effect',
            'fictional' => 'Fictional data: every organisation, person and contact detail is invented.',
            'ungrounded' => 'Notice: this script was written without external research sources.',
            'not_passed' => 'Notice: this script did not pass the second review; check the objections.',
        ],
    ];

    /**
     * @return array<string, string>
     */
    public static function for(string $language): array
    {
        return self::LABELS[$language] ?? self::LABELS['es'];
    }
}
